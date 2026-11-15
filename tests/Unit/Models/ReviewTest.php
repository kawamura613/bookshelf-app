<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_リレーションは_belongsto_を返す(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Review)->book());
    }

    public function test_user_リレーションは_belongsto_を返す(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Review)->user());
    }

    public function test_liked_by_users_リレーションは_belongstomany_で_review_likes_テーブルを使う(): void
    {
        $relation = (new Review)->likedByUsers();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('review_likes', $relation->getTable());
        $this->assertSame('review_likes.review_id', $relation->getQualifiedForeignPivotKeyName());
        $this->assertSame('review_likes.user_id', $relation->getQualifiedRelatedPivotKeyName());
    }

    public function test_fillable_に必要な項目が含まれる(): void
    {
        $this->assertSame(['book_id', 'user_id', 'rating', 'comment'], (new Review)->getFillable());
    }

    public function test_rating_は整数にキャストされる(): void
    {
        $review = Review::factory()->create(['rating' => '4']);

        $this->assertSame('integer', (new Review)->getCasts()['rating']);
        $this->assertIsInt($review->fresh()->rating);
        $this->assertSame(4, $review->fresh()->rating);
    }

    public function test_書籍と投稿者を取得できる(): void
    {
        $book = Book::factory()->create(['title' => '対象書籍']);
        $user = User::factory()->create(['name' => '投稿者']);
        $review = Review::factory()->create(['book_id' => $book->id, 'user_id' => $user->id]);

        $this->assertSame('対象書籍', $review->book->title);
        $this->assertSame('投稿者', $review->user->name);
    }

    public function test_いいねしたユーザーを取得できる(): void
    {
        $review = Review::factory()->create();
        $users = User::factory()->count(3)->create();

        $review->likedByUsers()->attach($users->pluck('id'));

        $this->assertCount(3, $review->likedByUsers);
        $this->assertNotNull($review->likedByUsers->first()->pivot->created_at);
    }

    public function test_同一ユーザーが同じ書籍に複数レビューを投稿できる(): void
    {
        $book = Book::factory()->create();
        $user = User::factory()->create();

        Review::factory()->count(2)->create(['book_id' => $book->id, 'user_id' => $user->id]);

        $this->assertDatabaseCount('reviews', 2);
        $this->assertCount(2, $book->fresh()->reviews);
    }

    public function test_削除すると関連いいねも削除される(): void
    {
        $review = Review::factory()->create();
        $review->likedByUsers()->attach(User::factory()->count(2)->create()->pluck('id'));

        $this->assertDatabaseCount('review_likes', 2);

        $review->delete();

        $this->assertDatabaseCount('review_likes', 0);
    }
}
