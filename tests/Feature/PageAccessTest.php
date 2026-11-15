<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    private function book(): Book
    {
        $book = Book::factory()->create();
        $book->genres()->attach(Genre::factory()->create()->id);

        return $book;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function 公開ページ(): array
    {
        return [
            'ルート' => ['/'],
            '書籍一覧' => ['/books'],
            'ランキング' => ['/ranking'],
        ];
    }

    /**
     * @dataProvider 公開ページ
     */
    public function test_公開ページはゲストでも_200_で表示される(string $uri): void
    {
        $this->book();

        $this->get($uri)->assertOk();
    }

    public function test_書籍詳細はゲストでも_200_で表示される(): void
    {
        $book = $this->book();

        $this->get(route('books.show', $book))->assertOk();
    }

    public function test_レビューがある書籍詳細もゲストで表示できる(): void
    {
        $book = $this->book();
        Review::factory()->count(2)->create(['book_id' => $book->id]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee($book->title);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function 認証必須ページ(): array
    {
        return [
            '書籍登録' => ['get', '/books/create'],
            'お気に入り一覧' => ['get', '/favorites'],
            'ジャンル一覧' => ['get', '/genres'],
            'ジャンル登録' => ['get', '/genres/create'],
        ];
    }

    /**
     * @dataProvider 認証必須ページ
     */
    public function test_認証必須ページはゲストだとログイン画面へリダイレクトされる(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertRedirect('/login');
    }

    public function test_書籍編集ページはゲストだとログイン画面へリダイレクトされる(): void
    {
        $book = $this->book();

        $this->get(route('books.edit', $book))->assertRedirect('/login');
    }

    public function test_ジャンル詳細と編集ページはゲストだとログイン画面へリダイレクトされる(): void
    {
        $genre = Genre::factory()->create();

        $this->get(route('genres.show', $genre))->assertRedirect('/login');
        $this->get(route('genres.edit', $genre))->assertRedirect('/login');
    }

    public function test_レビュー編集ページはゲストだとログイン画面へリダイレクトされる(): void
    {
        $review = Review::factory()->create();

        $this->get(route('reviews.edit', $review))->assertRedirect('/login');
    }

    public function test_認証済みなら認証必須ページが_200_で表示される(): void
    {
        $user = User::factory()->create();
        $book = $this->book();
        $genre = Genre::factory()->create();

        $this->actingAs($user)->get('/books/create')->assertOk();
        $this->actingAs($user)->get('/favorites')->assertOk();
        $this->actingAs($user)->get('/genres')->assertOk();
        $this->actingAs($user)->get('/genres/create')->assertOk();
        $this->actingAs($user)->get(route('genres.show', $genre))->assertOk();
        $this->actingAs($user)->get(route('genres.edit', $genre))->assertOk();
    }

    public function test_存在しない書籍の詳細は_404_になる(): void
    {
        $this->get('/books/9999')->assertNotFound();
    }
}
