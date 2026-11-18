<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
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
 * 評価ランキング（PG11）— 公開ページ。
 */
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

/*
 * 書籍の登録・編集・削除（PG03 / PG04）— 認証必須。
 * '/books/create' は '/books/{book}' より先に定義する必要がある。
 */
Route::middleware('auth')->group(function (): void {
    /*
     * ISBN検索（Google Books API 連携）。
     * 登録・編集フォームの JavaScript から呼ばれる JSON エンドポイント。
     * APIキーのクォータを保護するため認証必須とする。
     * '/books/{book}' は1セグメントのみに一致するため衝突しない。
     */
    Route::get('/books/isbn/{isbn}', [BookController::class, 'searchByIsbn'])->name('books.isbn');

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
 * レビュー（PG09）・お気に入り（PG10）・レビューいいね — 認証必須。
 * 更新・削除は ReviewPolicy で投稿者本人のみに制限する。
 */
Route::middleware('auth')->group(function (): void {
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
});

/*
 * ジャンル管理（PG05〜PG08）— 全ルート認証必須。
 * 確定仕様により、ログインユーザーであれば誰でも全操作が可能（Policy なし）。
 * '/genres/create' は '/genres/{genre}' より先に定義する必要がある。
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/genres', [GenreController::class, 'index'])->name('genres.index');
    Route::get('/genres/create', [GenreController::class, 'create'])->name('genres.create');
    Route::post('/genres', [GenreController::class, 'store'])->name('genres.store');
    Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');
    Route::get('/genres/{genre}/edit', [GenreController::class, 'edit'])->name('genres.edit');
    Route::put('/genres/{genre}', [GenreController::class, 'update'])->name('genres.update');
    Route::delete('/genres/{genre}', [GenreController::class, 'destroy'])->name('genres.destroy');
});

/*
 * マイ読書レポート（PG12）— 認証必須。
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});

/*
 * 読書計画（PG13）— 全ルート認証必須。
 * 編集・更新・完了・削除は ReadingPlanPolicy で所有者本人に制限する。
 * '/reading-plans/create' は '/reading-plans/{plan}' 形式より先に定義する。
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/reading-plans', [ReadingPlanController::class, 'index'])->name('reading-plans.index');
    Route::get('/reading-plans/create', [ReadingPlanController::class, 'create'])->name('reading-plans.create');
    Route::post('/reading-plans', [ReadingPlanController::class, 'store'])->name('reading-plans.store');
    Route::get('/reading-plans/{plan}/edit', [ReadingPlanController::class, 'edit'])->name('reading-plans.edit');
    Route::put('/reading-plans/{plan}', [ReadingPlanController::class, 'update'])->name('reading-plans.update');
    Route::post('/reading-plans/{plan}/complete', [ReadingPlanController::class, 'complete'])->name('reading-plans.complete');
    Route::delete('/reading-plans/{plan}', [ReadingPlanController::class, 'destroy'])->name('reading-plans.destroy');
});

/*
 * 通知（PG14）— 認証必須。他ユーザーの通知は 404 となる。
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});
