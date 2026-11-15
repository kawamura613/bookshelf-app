<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 指定した評価の配列でレビューを持つ書籍を作成する。
     *
     * @param  array<int, int>  $ratings
     */
    private function bookWithRatings(string $title, array $ratings): Book
    {
        $book = Book::factory()->create(['title' => $title]);
        $book->genres()->attach(Genre::factory()->create()->id);

        foreach ($ratings as $rating) {
            Review::factory()->rating($rating)->create(['book_id' => $book->id]);
        }

        return $book;
    }

    public function test_ランキングはゲストでも表示できる(): void
    {
        $this->bookWithRatings('公開テスト', [5]);

        $this->get(route('ranking.index'))->assertOk();
    }

    public function test_平均評価の降順で並ぶ(): void
    {
        $this->bookWithRatings('平均3', [3]);
        $this->bookWithRatings('平均5', [5]);
        $this->bookWithRatings('平均4', [4]);

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertSame(['平均5', '平均4', '平均3'], $ranked->pluck('title')->all());
        $this->assertSame(5.0, (float) $ranked->first()->reviews_avg_rating);
    }

    public function test_レビューが0件の書籍は除外される(): void
    {
        $this->bookWithRatings('レビューあり', [4]);
        $noReview = Book::factory()->create(['title' => 'レビューなし']);
        $noReview->genres()->attach(Genre::factory()->create()->id);

        $response = $this->get(route('ranking.index'))->assertOk();
        $ranked = $response->viewData('rankedBooks');

        $this->assertCount(1, $ranked);
        $this->assertSame('レビューあり', $ranked->first()->title);
        $this->assertFalse($ranked->contains('id', $noReview->id));
        $response->assertDontSee('レビューなし');
    }

    public function test_同点の場合はレビュー件数の多い順に並ぶ(): void
    {
        $this->bookWithRatings('同点_レビュー1件', [5]);
        $this->bookWithRatings('同点_レビュー3件', [5, 5, 5]);
        $this->bookWithRatings('同点_レビュー2件', [5, 5]);

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertSame([
            '同点_レビュー3件',
            '同点_レビュー2件',
            '同点_レビュー1件',
        ], $ranked->pluck('title')->all());
    }

    public function test_最大10件までしか表示されない(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->bookWithRatings("書籍{$i}", [5]);
        }

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertCount(10, $ranked);
    }

    public function test_平均評価とレビュー件数が集計されて渡る(): void
    {
        $this->bookWithRatings('集計テスト', [5, 4, 3]);

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');
        $book = $ranked->first();

        $this->assertSame(4.0, (float) $book->reviews_avg_rating);
        $this->assertSame(3, $book->reviews_count);
        $this->assertTrue($book->relationLoaded('genres'));
    }

    public function test_レビューが1件もなければ空メッセージを表示する(): void
    {
        Book::factory()->create();

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('まだレビューが投稿された書籍がありません。');
    }

    public function test_小数の平均評価が正しく集計される(): void
    {
        $this->bookWithRatings('小数テスト', [5, 4]);

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertSame(4.5, (float) $ranked->first()->reviews_avg_rating);
    }
}
