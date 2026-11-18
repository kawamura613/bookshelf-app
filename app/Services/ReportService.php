<?php

namespace App\Services;

use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * 高評価書籍・ジャンル別評価傾向で表示する最大件数。
     */
    private const TOP_LIMIT = 5;

    /**
     * 高評価書籍として扱う平均評価の下限。
     */
    private const HIGH_RATING_THRESHOLD = 4;

    /**
     * マイ読書レポート（PG12）の統計をまとめて組み立てる。
     *
     * 集計は手続き的なループを使わず、Collection メソッドで宣言的に行う。
     * 返り値のキー構造は reports/index.blade.php が参照する
     * $stats['summary'] / ['rating_distribution'] / ['top_rated_books'] / ['genre_ratings']
     * に対応する。
     *
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        // レビュー・書籍・ジャンルをまとめて読み込み、以降の集計はメモリ上で行う（N+1対策）。
        $reviews = $user->reviews()->with('book.genres')->get();

        return [
            'summary' => $this->summary($reviews),
            'rating_distribution' => $this->ratingDistribution($reviews),
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];
    }

    /**
     * 基本サマリー：総レビュー数・読了冊数・平均評価。
     *
     * 読了冊数はレビューしたユニークな書籍数で数える。
     *
     * @param  Collection<int, Review>  $reviews
     * @return array<string, int|float>
     */
    private function summary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => round((float) ($reviews->avg('rating') ?? 0), 2),
        ];
    }

    /**
     * 評価分布：1〜5星それぞれの件数。
     *
     * Blade は添字 0〜4 を「$index + 1 星」として扱い、
     * 最大値（->max()）から棒グラフの割合を算出する。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, int>
     */
    private function ratingDistribution(Collection $reviews): Collection
    {
        $countsByRating = $reviews->countBy('rating');

        return collect(range(1, 5))
            ->map(fn (int $rating): int => $countsByRating->get($rating, 0))
            ->values();
    }

    /**
     * 高評価書籍TOP5：平均評価が4以上の書籍を高い順に最大5件。
     *
     * Blade は id / title / author / rating（★の繰り返しに使う整数）を参照する。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array<string, mixed>>
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->groupBy('book_id')
            ->map(fn (Collection $bookReviews): array => [
                'id' => $bookReviews->first()->book->id,
                'title' => $bookReviews->first()->book->title,
                'author' => $bookReviews->first()->book->author,
                'average_rating' => (float) $bookReviews->avg('rating'),
            ])
            ->filter(fn (array $book): bool => $book['average_rating'] >= self::HIGH_RATING_THRESHOLD)
            ->sortByDesc('average_rating')
            ->take(self::TOP_LIMIT)
            ->map(fn (array $book): array => [
                'id' => $book['id'],
                'title' => $book['title'],
                'author' => $book['author'],
                'rating' => (int) round($book['average_rating']),
            ])
            ->values();
    }

    /**
     * ジャンル別評価傾向TOP5：平均評価の高い順に最大5件。
     *
     * 1件のレビューは書籍に紐づく全ジャンルへ計上する。
     * Blade は id / name / count / average_rating を参照する。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array<string, mixed>>
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review): Collection => $review->book->genres->map(
                fn (Genre $genre): array => ['genre' => $genre, 'rating' => $review->rating]
            ))
            ->groupBy(fn (array $row): int => $row['genre']->id)
            ->map(fn (Collection $rows): array => [
                'id' => $rows->first()['genre']->id,
                'name' => $rows->first()['genre']->name,
                'count' => $rows->count(),
                'average_rating' => round((float) $rows->avg('rating'), 2),
            ])
            ->sortByDesc('average_rating')
            ->take(self::TOP_LIMIT)
            ->values();
    }
}
