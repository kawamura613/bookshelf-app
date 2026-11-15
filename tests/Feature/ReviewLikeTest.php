<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    private Review $review;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->book = Book::factory()->create();
        $this->book->genres()->attach(Genre::factory()->create()->id);
        $this->review = Review::factory()->create([
            'book_id' => $this->book->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_追加_解除_再追加の3ステップでトグルできる(): void
    {
        // 1. 追加
        $this->actingAs($this->user)
            ->from(route('books.show', $this->book))
            ->post(route('reviews.like', $this->review))
            ->assertRedirect(route('books.show', $this->book));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $this->user->id,
            'review_id' => $this->review->id,
        ]);
        $this->assertDatabaseCount('review_likes', 1);

        // 2. 解除
        $this->actingAs($this->user)->post(route('reviews.like', $this->review));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $this->user->id,
            'review_id' => $this->review->id,
        ]);
        $this->assertDatabaseCount('review_likes', 0);

        // 3. 再追加
        $this->actingAs($this->user)->post(route('reviews.like', $this->review));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $this->user->id,
            'review_id' => $this->review->id,
        ]);
        $this->assertDatabaseCount('review_likes', 1);
    }

    public function test_自分のレビューにもいいねできる(): void
    {
        $ownReview = Review::factory()->create([
            'book_id' => $this->book->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)->post(route('reviews.like', $ownReview));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $this->user->id,
            'review_id' => $ownReview->id,
        ]);
    }

    public function test_トグル後は元の画面へ戻る(): void
    {
        $this->actingAs($this->user)
            ->from(route('books.index'))
            ->post(route('reviews.like', $this->review))
            ->assertRedirect(route('books.index'));
    }

    public function test_複数ユーザーがいいねできいいね数が増える(): void
    {
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $this->actingAs($user)->post(route('reviews.like', $this->review));
        }

        $this->assertDatabaseCount('review_likes', 3);
        $this->assertSame(3, $this->review->fresh()->likedByUsers()->count());
    }

    public function test_書籍詳細でいいね状態の表示が切り替わる(): void
    {
        $this->actingAs($this->user)
            ->get(route('books.show', $this->book))
            ->assertSee('いいね (0)');

        $this->user->likedReviews()->attach($this->review->id);

        $this->actingAs($this->user)
            ->get(route('books.show', $this->book))
            ->assertSee('いいね済み (1)');
    }

    public function test_ゲストはいいねできない(): void
    {
        $this->post(route('reviews.like', $this->review))->assertRedirect('/login');

        $this->assertDatabaseCount('review_likes', 0);
    }
}
