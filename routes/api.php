<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
 * 公開API v1（AP01〜AP05）— 基本機能では認証をかけない確定仕様。
 * RouteServiceProvider により 'api' プレフィックスが付くため、
 * 実際のURLは /api/v1/books となる。
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/books', [BookController::class, 'index'])->name('api.v1.books.index');
    Route::post('/books', [BookController::class, 'store'])->name('api.v1.books.store');
    Route::get('/books/{book}', [BookController::class, 'show'])->name('api.v1.books.show');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('api.v1.books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('api.v1.books.destroy');
});
