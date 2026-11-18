<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * レポートを開いて $stats を取り出す。
     *
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        /** @var TestResponse $response */
        $response = $this->actingAs($this->user)->get(route('reports.index'))->assertOk();

        return $response->viewData('stats');
    }

    private function reviewBook(Book $book, int $rating, ?User $user = null): Review
    {
        return Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => ($user ?? $this->user)->id,
            'rating' => $rating,
        ]);
    }

    public function test_未ログインではアクセスできない(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_レビューが0件でも表示できる(): void
    {
        $stats = $this->stats();

        $this->assertSame(0, $stats['summary']['total_reviews']);
        $this->assertSame(0, $stats['summary']['books_read']);
        $this->assertSame(0.0, $stats['summary']['average_rating']);
        $this->assertSame([0, 0, 0, 0, 0], $stats['rating_distribution']->all());
        $this->assertCount(0, $stats['top_rated_books']);
        $this->assertCount(0, $stats['genre_ratings']);
    }

    // ---------- 基本サマリー ----------

    public function test_基本サマリーを集計できる(): void
    {
        $first = Book::factory()->create();
        $second = Book::factory()->create();

        // 同じ書籍に2件、別の書籍に1件（読了冊数はユニーク書籍数で数える）。
        $this->reviewBook($first, 5);
        $this->reviewBook($first, 3);
        $this->reviewBook($second, 4);

        $summary = $this->stats()['summary'];

        $this->assertSame(3, $summary['total_reviews']);
        $this->assertSame(2, $summary['books_read']);
        $this->assertSame(4.0, $summary['average_rating']);
    }

    public function test_他ユーザーのレビューは集計に含まれない(): void
    {
        $book = Book::factory()->create();
        $this->reviewBook($book, 5);
        $this->reviewBook($book, 1, User::factory()->create());

        $summary = $this->stats()['summary'];

        $this->assertSame(1, $summary['total_reviews']);
        $this->assertSame(5.0, $summary['average_rating']);
    }

    // ---------- 評価分布 ----------

    public function test_評価分布は1から5星の件数を昇順で返す(): void
    {
        $book = Book::factory()->create();

        collect([1, 3, 3, 5, 5, 5])->each(fn (int $rating) => $this->reviewBook($book, $rating));

        /** @var Collection<int, int> $distribution */
        $distribution = $this->stats()['rating_distribution'];

        // 添字0が1星、添字4が5星。
        $this->assertSame([1, 0, 2, 0, 3], $distribution->all());
        $this->assertSame(3, $distribution->max());
    }

    // ---------- 高評価書籍TOP5 ----------

    public function test_高評価書籍は平均4以上のみを平均評価の高い順に返す(): void
    {
        $best = Book::factory()->create(['title' => '最高評価', 'author' => '著者A']);
        $good = Book::factory()->create(['title' => '高評価', 'author' => '著者B']);
        $low = Book::factory()->create(['title' => '低評価', 'author' => '著者C']);

        $this->reviewBook($best, 5);
        $this->reviewBook($good, 4);
        $this->reviewBook($low, 3);

        $top = $this->stats()['top_rated_books'];

        $this->assertSame(['最高評価', '高評価'], $top->pluck('title')->all());
        $this->assertSame([$best->id, $good->id], $top->pluck('id')->all());
        $this->assertSame(['著者A', '著者B'], $top->pluck('author')->all());
        // Blade が str_repeat('★', ...) で使うため整数であること。
        $this->assertSame([5, 4], $top->pluck('rating')->all());
    }

    public function test_高評価書籍は書籍ごとの平均で判定される(): void
    {
        $book = Book::factory()->create();
        // 平均 3.5 なので対象外。
        $this->reviewBook($book, 5);
        $this->reviewBook($book, 2);

        $this->assertCount(0, $this->stats()['top_rated_books']);
    }

    public function test_高評価書籍は最大5件までになる(): void
    {
        collect(range(1, 7))->each(function (int $index): void {
            $this->reviewBook(Book::factory()->create(['title' => "書籍{$index}"]), 5);
        });

        $top = $this->stats()['top_rated_books'];

        $this->assertCount(5, $top);
        // Blade が $index で順位色を引くため 0 起点の連番であること。
        $this->assertSame([0, 1, 2, 3, 4], $top->keys()->all());
    }

    // ---------- ジャンル別評価傾向TOP5 ----------

    public function test_ジャンル別評価傾向を平均の高い順に返す(): void
    {
        $technical = Genre::factory()->create(['name' => '技術書']);
        $novel = Genre::factory()->create(['name' => '小説']);

        $highBook = Book::factory()->create();
        $highBook->genres()->attach($technical->id);
        $lowBook = Book::factory()->create();
        $lowBook->genres()->attach($novel->id);

        $this->reviewBook($highBook, 5);
        $this->reviewBook($highBook, 4);
        $this->reviewBook($lowBook, 2);

        $genres = $this->stats()['genre_ratings'];

        $this->assertSame(['技術書', '小説'], $genres->pluck('name')->all());
        $this->assertSame([$technical->id, $novel->id], $genres->pluck('id')->all());
        $this->assertSame([2, 1], $genres->pluck('count')->all());
        $this->assertSame([4.5, 2.0], $genres->pluck('average_rating')->all());
    }

    public function test_複数ジャンルの書籍は全ジャンルに計上される(): void
    {
        $first = Genre::factory()->create();
        $second = Genre::factory()->create();

        $book = Book::factory()->create();
        $book->genres()->attach([$first->id, $second->id]);
        $this->reviewBook($book, 5);

        $genres = $this->stats()['genre_ratings'];

        $this->assertCount(2, $genres);
        $this->assertSame([1, 1], $genres->pluck('count')->all());
    }

    public function test_ジャンル未設定の書籍はジャンル別集計に現れない(): void
    {
        $this->reviewBook(Book::factory()->create(), 5);

        $this->assertCount(0, $this->stats()['genre_ratings']);
    }

    public function test_ジャンル別評価傾向は最大5件までになる(): void
    {
        collect(range(1, 7))->each(function (int $index): void {
            $genre = Genre::factory()->create(['name' => "ジャンル{$index}"]);
            $book = Book::factory()->create();
            $book->genres()->attach($genre->id);
            $this->reviewBook($book, 5);
        });

        $genres = $this->stats()['genre_ratings'];

        $this->assertCount(5, $genres);
        $this->assertSame([0, 1, 2, 3, 4], $genres->keys()->all());
    }
}
