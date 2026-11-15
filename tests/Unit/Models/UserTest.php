<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_リレーションは_hasmany_を返す(): void
    {
        $this->assertInstanceOf(HasMany::class, (new User)->books());
    }

    public function test_reviews_リレーションは_hasmany_を返す(): void
    {
        $this->assertInstanceOf(HasMany::class, (new User)->reviews());
    }

    public function test_favorite_books_リレーションは_belongstomany_で_favorites_テーブルを使う(): void
    {
        $relation = (new User)->favoriteBooks();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('favorites', $relation->getTable());
        $this->assertSame('favorites.user_id', $relation->getQualifiedForeignPivotKeyName());
        $this->assertSame('favorites.book_id', $relation->getQualifiedRelatedPivotKeyName());
    }

    public function test_liked_reviews_リレーションは_belongstomany_で_review_likes_テーブルを使う(): void
    {
        $relation = (new User)->likedReviews();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('review_likes', $relation->getTable());
        $this->assertSame('review_likes.user_id', $relation->getQualifiedForeignPivotKeyName());
        $this->assertSame('review_likes.review_id', $relation->getQualifiedRelatedPivotKeyName());
    }

    public function test_登録した書籍を取得できる(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(2)->create(['user_id' => $user->id]);
        Book::factory()->create();

        $this->assertCount(2, $user->books);
        $this->assertInstanceOf(Book::class, $user->books->first());
    }

    public function test_投稿したレビューを取得できる(): void
    {
        $user = User::factory()->create();
        Review::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->reviews);
        $this->assertInstanceOf(Review::class, $user->reviews->first());
    }

    public function test_お気に入り書籍を取得でき_ピボットにタイムスタンプが入る(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(2)->create();

        $user->favoriteBooks()->attach($books->pluck('id'));

        $this->assertCount(2, $user->favoriteBooks);
        $this->assertNotNull($user->favoriteBooks->first()->pivot->created_at);
    }

    public function test_いいねしたレビューを取得でき_ピボットにタイムスタンプが入る(): void
    {
        $user = User::factory()->create();
        $reviews = Review::factory()->count(2)->create();

        $user->likedReviews()->attach($reviews->pluck('id'));

        $this->assertCount(2, $user->likedReviews);
        $this->assertNotNull($user->likedReviews->first()->pivot->created_at);
    }

    public function test_パスワードはハッシュ化され_hidden_に含まれる(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame('password', $user->password);
        $this->assertContains('password', $user->getHidden());
        $this->assertContains('remember_token', $user->getHidden());
    }
}
