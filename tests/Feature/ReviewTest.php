<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->book = Book::factory()->create();
        $this->book->genres()->attach(Genre::factory()->create()->id);
    }

    public function test_レビューを投稿でき書籍詳細へリダイレクトされる(): void
    {
        $response = $this->actingAs($this->user)->post(route('reviews.store', $this->book), [
            'rating' => 4,
            'comment' => 'とても良い本でした。',
        ]);

        $response->assertRedirect(route('books.show', $this->book));
        $response->assertSessionHas('success', 'レビューを投稿しました。');

        $this->assertDatabaseHas('reviews', [
            'book_id' => $this->book->id,
            'user_id' => $this->user->id,
            'rating' => 4,
            'comment' => 'とても良い本でした。',
        ]);
    }

    public function test_同一ユーザーが同じ書籍へ複数回投稿できる(): void
    {
        $this->actingAs($this->user)->post(route('reviews.store', $this->book), [
            'rating' => 5, 'comment' => '1件目のレビュー。',
        ]);
        $this->actingAs($this->user)->post(route('reviews.store', $this->book), [
            'rating' => 3, 'comment' => '2件目のレビュー。',
        ]);

        $this->assertDatabaseCount('reviews', 2);
        $this->assertSame(2, Review::where([
            'book_id' => $this->book->id,
            'user_id' => $this->user->id,
        ])->count());
    }

    public function test_レビュー投稿のバリデーションエラーが日本語で返る(): void
    {
        $response = $this->actingAs($this->user)
            ->from(route('books.show', $this->book))
            ->post(route('reviews.store', $this->book), ['rating' => '', 'comment' => '']);

        $response->assertRedirect(route('books.show', $this->book));
        $response->assertSessionHasErrors([
            'rating' => '評価を選択してください。',
            'comment' => 'コメントを入力してください。',
        ]);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_評価が範囲外だとエラーになる(): void
    {
        $this->actingAs($this->user)
            ->from(route('books.show', $this->book))
            ->post(route('reviews.store', $this->book), ['rating' => 6, 'comment' => 'テスト'])
            ->assertSessionHasErrors(['rating' => '評価は1から5の間で選択してください。']);
    }

    public function test_コメントが1000文字を超えるとエラーになる(): void
    {
        $this->actingAs($this->user)
            ->from(route('books.show', $this->book))
            ->post(route('reviews.store', $this->book), [
                'rating' => 3,
                'comment' => str_repeat('あ', 1001),
            ])
            ->assertSessionHasErrors(['comment' => 'コメントは1000文字以内で入力してください。']);
    }

    public function test_ゲストはレビューを投稿できない(): void
    {
        $this->post(route('reviews.store', $this->book), ['rating' => 3, 'comment' => 'テスト'])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_投稿者は編集画面を開ける(): void
    {
        $review = Review::factory()->create([
            'book_id' => $this->book->id,
            'user_id' => $this->user->id,
            'comment' => '編集前のコメント',
        ]);

        $this->actingAs($this->user)
            ->get(route('reviews.edit', $review))
            ->assertOk()
            ->assertSee($this->book->title)
            ->assertSee('編集前のコメント');
    }

    public function test_他ユーザーは編集画面にアクセスできず_403_になる(): void
    {
        $review = Review::factory()->create(['book_id' => $this->book->id, 'user_id' => $this->user->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('reviews.edit', $review))
            ->assertForbidden();
    }

    public function test_レビューを更新でき書籍詳細へリダイレクトされる(): void
    {
        $review = Review::factory()->create(['book_id' => $this->book->id, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->put(route('reviews.update', $review), [
            'rating' => 2,
            'comment' => '更新後のコメント。',
        ]);

        $response->assertRedirect(route('books.show', $this->book->id));
        $response->assertSessionHas('success', 'レビューを更新しました。');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 2,
            'comment' => '更新後のコメント。',
        ]);
    }

    public function test_レビュー更新のバリデーションエラーが返る(): void
    {
        $review = Review::factory()->create(['book_id' => $this->book->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), ['rating' => 0, 'comment' => ''])
            ->assertSessionHasErrors([
                'rating' => '評価は1から5の間で選択してください。',
                'comment' => 'コメントを入力してください。',
            ]);
    }

    public function test_他ユーザーは更新できず_403_になる(): void
    {
        $review = Review::factory()->create([
            'book_id' => $this->book->id,
            'user_id' => $this->user->id,
            'comment' => '元のコメント',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('reviews.update', $review), ['rating' => 1, 'comment' => '乗っ取り'])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'comment' => '元のコメント']);
    }

    public function test_レビューを削除でき書籍詳細へリダイレクトされる(): void
    {
        $review = Review::factory()->create(['book_id' => $this->book->id, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('reviews.destroy', $review));

        $response->assertRedirect(route('books.show', $this->book->id));
        $response->assertSessionHas('success', 'レビューを削除しました。');
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_他ユーザーは削除できず_403_になる(): void
    {
        $review = Review::factory()->create(['book_id' => $this->book->id, 'user_id' => $this->user->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_レビュー削除で関連いいねも削除される(): void
    {
        $review = Review::factory()->create(['book_id' => $this->book->id, 'user_id' => $this->user->id]);
        $review->likedByUsers()->attach(User::factory()->count(2)->create()->pluck('id'));

        $this->assertDatabaseCount('review_likes', 2);

        $this->actingAs($this->user)->delete(route('reviews.destroy', $review));

        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('review_likes', 0);
    }

    public function test_詳細画面で投稿者だけに編集削除リンクが表示される(): void
    {
        $review = Review::factory()->create(['book_id' => $this->book->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->get(route('books.show', $this->book))
            ->assertSee(route('reviews.edit', $review));

        $this->actingAs(User::factory()->create())->get(route('books.show', $this->book))
            ->assertDontSee(route('reviews.edit', $review));
    }
}
