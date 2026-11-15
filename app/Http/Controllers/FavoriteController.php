<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * お気に入り一覧（PG10）。
     *
     * Blade は $books（ページネータ）を参照する。
     * お気に入り登録が新しい順に10件ずつ表示する。
     */
    public function index(): View
    {
        $books = Auth::user()
            ->favoriteBooks()
            ->with('genres')
            ->orderByPivot('created_at', 'desc')
            ->paginate(10);

        return view('favorites.index', ['books' => $books]);
    }

    /**
     * お気に入り登録を切り替える。処理後は元の画面へ戻す。
     */
    public function toggle(Book $book): RedirectResponse
    {
        Auth::user()->favoriteBooks()->toggle($book->id);

        return back();
    }
}
