<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_リレーションは_belongsto_を返す(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Book)->user());
    }

    public function test_genres_リレーションは_belongstomany_で_book_genre_テーブルを使う(): void
    {
        $relation = (new Book)->genres();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('book_genre', $relation->getTable());
        $this->assertSame('book_genre.book_id', $relation->getQualifiedForeignPivotKeyName());
        $this->assertSame('book_genre.genre_id', $relation->getQualifiedRelatedPivotKeyName());
    }

    public function test_reviews_リレーションは_hasmany_を返す(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Book)->reviews());
    }

    public function test_favorited_by_リレーションは_belongstomany_で_favorites_テーブルを使う(): void
    {
        $relation = (new Book)->favoritedBy();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('favorites', $relation->getTable());
    }

    public function test_fillable_に必要な項目が含まれる(): void
    {
        $this->assertSame([
            'user_id', 'title', 'author', 'isbn',
            'published_date', 'description', 'image_url',
        ], (new Book)->getFillable());
    }

    public function test_published_date_は_date_にキャストされる(): void
    {
        $book = Book::factory()->create(['published_date' => '2026-11-16']);

        $this->assertSame('date', (new Book)->getCasts()['published_date']);
        $this->assertInstanceOf(Carbon::class, $book->fresh()->published_date);
        $this->assertSame('2026-11-16', $book->fresh()->published_date->format('Y-m-d'));
    }

    public function test_published_date_が_null_の場合はそのまま_null_になる(): void
    {
        $book = Book::factory()->create(['published_date' => null]);

        $this->assertNull($book->fresh()->published_date);
    }

    public function test_登録者を取得できる(): void
    {
        $user = User::factory()->create(['name' => '登録者テスト']);
        $book = Book::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $book->user);
        $this->assertSame('登録者テスト', $book->user->name);
    }

    public function test_ジャンルとレビューを取得できる(): void
    {
        $book = Book::factory()->create();
        $book->genres()->attach(Genre::factory()->count(2)->create()->pluck('id'));
        Review::factory()->count(3)->create(['book_id' => $book->id]);

        $this->assertCount(2, $book->genres);
        $this->assertCount(3, $book->reviews);
    }

    public function test_平均評価とレビュー件数を集計できる(): void
    {
        $book = Book::factory()->create();
        Review::factory()->rating(5)->create(['book_id' => $book->id]);
        Review::factory()->rating(4)->create(['book_id' => $book->id]);

        $aggregated = Book::withAvg('reviews', 'rating')->withCount('reviews')->find($book->id);

        $this->assertSame(4.5, (float) $aggregated->reviews_avg_rating);
        $this->assertSame(2, $aggregated->reviews_count);
    }

    public function test_レビューが無い場合の平均評価は_null(): void
    {
        $book = Book::factory()->create();

        $aggregated = Book::withAvg('reviews', 'rating')->withCount('reviews')->find($book->id);

        $this->assertNull($aggregated->reviews_avg_rating);
        $this->assertSame(0, $aggregated->reviews_count);
    }

    public function test_削除すると関連データも削除される(): void
    {
        $book = Book::factory()->create();
        $user = User::factory()->create();
        $book->genres()->attach(Genre::factory()->create()->id);
        $review = Review::factory()->create(['book_id' => $book->id, 'user_id' => $user->id]);
        $review->likedByUsers()->attach($user->id);
        $user->favoriteBooks()->attach($book->id);

        $book->delete();

        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('review_likes', 0);
    }

    public function test_お気に入り登録したユーザーを取得できる(): void
    {
        $book = Book::factory()->create();
        $user = User::factory()->create(['name' => 'お気に入り登録者']);
        $other = User::factory()->create();

        $user->favoriteBooks()->attach($book->id);

        $this->assertSame(['お気に入り登録者'], $book->favoritedBy->pluck('name')->all());
        $this->assertFalse($book->favoritedBy->contains($other->id));
    }

    public function test_reading_plans_リレーションは_hasmany_を返す(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Book)->readingPlans());
    }

    public function test_書籍に紐づく読書計画を取得できる(): void
    {
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create(['book_id' => $book->id]);
        ReadingPlan::factory()->create();

        $this->assertSame([$plan->id], $book->readingPlans->pluck('id')->all());
    }

    public function test_書籍を削除すると読書計画も削除される(): void
    {
        $book = Book::factory()->create();
        ReadingPlan::factory()->create(['book_id' => $book->id]);

        $book->delete();

        $this->assertDatabaseCount('reading_plans', 0);
    }
}
