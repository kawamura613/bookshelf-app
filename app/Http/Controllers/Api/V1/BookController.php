<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    /**
     * 1ページあたりの既定件数。
     */
    private const DEFAULT_PER_PAGE = 20;

    /**
     * 書籍一覧（AP01）。
     *
     * keyword（title または author の部分一致）と genre_id で絞り込める。
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when(
                isset($validated['keyword']),
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $inner): Builder => $inner
                        ->where('title', 'like', '%'.$validated['keyword'].'%')
                        ->orWhere('author', 'like', '%'.$validated['keyword'].'%')
                )
            )
            ->when(
                isset($validated['genre_id']),
                fn (Builder $query): Builder => $query->whereHas(
                    'genres',
                    fn (Builder $inner): Builder => $inner->where('genres.id', $validated['genre_id'])
                )
            )
            ->latest()
            ->paginate($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        return BookResource::collection($books);
    }

    /**
     * 書籍詳細（AP02）。
     */
    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews.user'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * 書籍作成（AP03）。201 Created を返す。
     *
     * 認証（auth:sanctum）が必須で、作成者は常にトークンの所有者となる。
     */
    public function store(StoreBookRequest $request): JsonResponse
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

        $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * 書籍更新（AP04）。200 OK を返す。
     *
     * 認証（auth:sanctum）が必須で、BookPolicy により作成者本人のみ許可する。
     * 作成者（user_id）は更新対象にしない。
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
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

        $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * 書籍削除（AP05）。204 No Content を返す。
     *
     * 認証（auth:sanctum）が必須で、BookPolicy により作成者本人のみ許可する。
     * 関連データ（book_genre / reviews / favorites / review_likes）は
     * 外部キーの ON DELETE CASCADE により削除される。
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }
}
