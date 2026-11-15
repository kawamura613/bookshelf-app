<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    /**
     * 書籍一覧（PG01）。
     *
     * 最新順に10件ずつページネーション表示する。
     * Blade は $book->genres と $book->reviews_avg_rating を参照するため、
     * ジャンルを Eager Loading し、平均評価を withAvg() で集計する。
     */
    public function index(): View
    {
        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate(10);

        return view('books.index', ['books' => $books]);
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
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
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
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
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
