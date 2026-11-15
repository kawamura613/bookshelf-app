<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;

class RankingController extends Controller
{
    /**
     * 評価ランキング TOP 10（PG11）— 公開ページ。
     *
     * レビューが1件以上ある書籍のみを対象とし、
     * 平均評価の降順 → レビュー件数の降順 → 登録が新しい順 で並べる。
     * Blade は $rankedBooks と各書籍の reviews_avg_rating / reviews_count を参照する。
     */
    public function index(): View
    {
        $rankedBooks = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->has('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->latest()
            ->take(10)
            ->get();

        return view('ranking.index', ['rankedBooks' => $rankedBooks]);
    }
}
