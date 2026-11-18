<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Throwable;

class BookController extends Controller
{
    /**
     * 1ページあたりの表示件数。
     */
    private const PER_PAGE = 10;

    /**
     * Google Books API の書籍検索エンドポイント。
     */
    private const GOOGLE_BOOKS_ENDPOINT = 'https://www.googleapis.com/books/v1/volumes';

    /**
     * Google Books API へのリクエストのタイムアウト秒数。
     */
    private const GOOGLE_BOOKS_TIMEOUT = 10;

    /**
     * 書籍一覧（PG01）。キーワード検索・ジャンル絞り込み・並び替えに対応する。
     *
     * Blade は $book->genres と $book->reviews_avg_rating、および
     * ジャンル選択プルダウン用の $genres を参照するため、ジャンルを
     * Eager Loading し、平均評価を withAvg() で集計する。
     * 検索条件はページネーションリンクに withQueryString() で引き継ぐ。
     */
    public function index(IndexBookRequest $request): View
    {
        $validated = $request->validated();

        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $genreId = $validated['genre'] ?? null;
        $sort = $validated['sort'] ?? 'newest';

        $query = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->when(
                $keyword !== '',
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $inner): Builder => $inner
                        ->where('title', 'like', '%'.$keyword.'%')
                        ->orWhere('author', 'like', '%'.$keyword.'%')
                )
            )
            ->when(
                $genreId !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'genres',
                    fn (Builder $inner): Builder => $inner->where('genres.id', $genreId)
                )
            );

        return view('books.index', [
            'books' => $this->paginateSorted($query, $sort),
            'genres' => Genre::orderBy('id')->get(),
        ]);
    }

    /**
     * 指定された並び順を適用してページネーションする。
     *
     * - newest / latest（既定）：登録日が新しい順
     * - oldest：登録日が古い順
     * - title：タイトルの昇順
     * - rating：平均評価の高い順。レビューが無い書籍（平均評価が NULL）は末尾に置く
     *
     * @return LengthAwarePaginator<Book>
     */
    private function paginateSorted(Builder $query, string $sort): LengthAwarePaginator
    {
        $sorted = match ($sort) {
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title'),
            'rating' => $query
                ->orderByRaw('reviews_avg_rating IS NULL')
                ->orderByDesc('reviews_avg_rating')
                ->latest(),
            default => $query->latest(),
        };

        return $sorted->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * ISBN から Google Books API の書籍情報を取得して JSON で返す。
     *
     * 提供済み Blade（books/create・edit.blade.php）の JavaScript が
     * title / author / published_date / description / image_url と
     * エラー時の error キーを参照する。
     */
    public function searchByIsbn(string $isbn): JsonResponse
    {
        if (preg_match('/\A[0-9]{13}\z/', $isbn) !== 1) {
            return $this->isbnError('ISBNは13桁で入力してください。', Response::HTTP_BAD_REQUEST);
        }

        try {
            $response = Http::timeout(self::GOOGLE_BOOKS_TIMEOUT)
                ->get(self::GOOGLE_BOOKS_ENDPOINT, array_filter([
                    'q' => 'isbn:'.$isbn,
                    'key' => config('services.google_books.key'),
                ]));
        } catch (Throwable $e) {
            report($e);

            return $this->isbnError('API通信エラーが発生しました。', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($response->status() === Response::HTTP_TOO_MANY_REQUESTS) {
            return $this->isbnError(
                'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。',
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        if ($response->failed()) {
            return $this->isbnError('API通信エラーが発生しました。', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $volumeInfo = $response->json('items.0.volumeInfo');

        if (! is_array($volumeInfo)) {
            return $this->isbnError('書籍が見つかりませんでした。', Response::HTTP_NOT_FOUND);
        }

        return response()->json($this->formatVolumeInfo($volumeInfo));
    }

    /**
     * ISBN検索のエラーレスポンスを組み立てる。
     */
    private function isbnError(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }

    /**
     * Google Books API の volumeInfo をフォームの入力項目へ対応付ける。
     *
     * @param  array<string, mixed>  $volumeInfo
     * @return array<string, string|null>
     */
    private function formatVolumeInfo(array $volumeInfo): array
    {
        $authors = is_array($volumeInfo['authors'] ?? null) ? $volumeInfo['authors'] : [];
        $thumbnail = $volumeInfo['imageLinks']['thumbnail']
            ?? $volumeInfo['imageLinks']['smallThumbnail']
            ?? null;

        return [
            'title' => isset($volumeInfo['title']) ? (string) $volumeInfo['title'] : null,
            'author' => $authors === [] ? null : implode(', ', $authors),
            'published_date' => isset($volumeInfo['publishedDate'])
                ? (string) $volumeInfo['publishedDate']
                : null,
            'description' => isset($volumeInfo['description'])
                ? (string) $volumeInfo['description']
                : null,
            // Google Books のサムネイルは http で返ることがあるため https に揃える。
            'image_url' => $thumbnail === null
                ? null
                : preg_replace('/\Ahttp:/', 'https:', (string) $thumbnail),
        ];
    }

    /**
     * 書籍詳細（PG02）。ゲストも閲覧可。
     *
     * Blade が参照する関連データをまとめて Eager Loading する。
     * - $book->genres
     * - $book->reviews（新しい順）/ $review->user / $review->likedByUsers
     * - Auth::user()->favoriteBooks / ->likedReviews（お気に入り・いいね状態）
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews' => fn (HasMany $query): HasMany => $query->latest(),
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        Auth::user()?->load(['favoriteBooks', 'likedReviews']);

        return view('books.show', ['book' => $book]);
    }

    /**
     * 書籍登録フォーム（PG03）。
     */
    public function create(): View
    {
        return view('books.create', ['genres' => Genre::orderBy('id')->get()]);
    }

    /**
     * 書籍を登録し、中間テーブルにジャンルを紐付ける。
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $book = Book::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'] ?? null,
            'published_date' => $validated['published_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍編集フォーム（PG04）。作成者本人のみ。
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');

        return view('books.edit', [
            'book' => $book,
            'genres' => Genre::orderBy('id')->get(),
        ]);
    }

    /**
     * 書籍を更新し、ジャンルの紐付けを同期する。作成者本人のみ。
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $validated = $request->validated();

        $book->update([
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'] ?? null,
            'published_date' => $validated['published_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除する。作成者本人のみ。
     *
     * 関連データ（book_genre / reviews / favorites / review_likes）は
     * 外部キーの ON DELETE CASCADE により削除される。
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
