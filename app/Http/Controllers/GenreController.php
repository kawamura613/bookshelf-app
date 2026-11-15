<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class GenreController extends Controller
{
    /**
     * ジャンル一覧（PG05）。
     *
     * Blade が $genre->books_count を参照するため withCount('books') で集計する。
     */
    public function index(): View
    {
        $genres = Genre::query()
            ->withCount('books')
            ->orderBy('id')
            ->get();

        return view('genres.index', ['genres' => $genres]);
    }

    /**
     * ジャンル登録フォーム（PG06）。
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルを登録する。
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create(['name' => $request->validated()['name']]);

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを作成しました。');
    }

    /**
     * ジャンル詳細（PG07）。
     *
     * 紐づく書籍を10件ずつページネーション表示する。
     * Blade が各書籍の $book->genres を参照するため Eager Loading する。
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()
            ->with('genres')
            ->orderByDesc('books.created_at')
            ->paginate(10);

        return view('genres.show', [
            'genre' => $genre,
            'books' => $books,
        ]);
    }

    /**
     * ジャンル編集フォーム（PG08）。
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', ['genre' => $genre]);
    }

    /**
     * ジャンルを更新する。
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update(['name' => $request->validated()['name']]);

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを更新しました。');
    }

    /**
     * ジャンルを削除する。
     *
     * 確定仕様により、書籍が1冊以上紐付いている場合は削除せずエラーを返す。
     * （book_genre.genre_id は ON DELETE RESTRICT のため、DB側でも削除は拒否される）
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->count() > 0) {
            return redirect()
                ->route('genres.index')
                ->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }

        $genre->delete();

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
