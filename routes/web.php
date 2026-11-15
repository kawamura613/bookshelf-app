<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

/*
 * 書籍一覧（PG01）— 公開ページ。
 * '/' と '/books' の両方で同じアクションを表示する。
 * ルート名は '/books' 側に付与し、route('books.index') は /books を返す。
 */
Route::get('/', [BookController::class, 'index']);
Route::get('/books', [BookController::class, 'index'])->name('books.index');

/*
 * 書籍の登録・編集・削除（PG03 / PG04）— 認証必須。
 * '/books/create' は '/books/{book}' より先に定義する必要がある。
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});

/*
 * 書籍詳細（PG02）— 公開ページ（ゲスト閲覧可）。
 * '/books/create' の後に定義する。
 */
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

/*
|--------------------------------------------------------------------------
| 次フェーズで実装予定のルート（プレースホルダ）
|--------------------------------------------------------------------------
|
| 提供済み Blade が route() でURLを生成するため、ルート定義自体が
| 存在しないと RouteNotFoundException となり書籍ページが表示できない。
| 下記は名前とURLのみを確保したプレースホルダで、実処理は各機能の
| フェーズでコントローラーに差し替える。
|
| - layouts/navigation : ranking.index / favorites.index / genres.index
| - books/show         : favorites.toggle / reviews.*
|
*/
Route::get('/ranking', fn () => abort(404))->name('ranking.index');
Route::get('/genres', fn () => abort(404))->name('genres.index');

Route::middleware('auth')->group(function (): void {
    Route::get('/favorites', fn () => abort(404))->name('favorites.index');
    Route::post('/favorites/{book}', fn () => abort(404))->name('favorites.toggle');
    Route::post('/books/{book}/reviews', fn () => abort(404))->name('reviews.store');
    Route::post('/reviews/{review}/like', fn () => abort(404))->name('reviews.like');
    Route::get('/reviews/{review}/edit', fn () => abort(404))->name('reviews.edit');
    Route::put('/reviews/{review}', fn () => abort(404))->name('reviews.update');
    Route::delete('/reviews/{review}', fn () => abort(404))->name('reviews.destroy');
});
