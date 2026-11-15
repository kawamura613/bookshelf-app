<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
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

    public function test_追加_解除_再追加の3ステップでトグルできる(): void
    {
        // 1. 追加
        $this->actingAs($this->user)
            ->post(route('favorites.toggle', $this->book), [], ['HTTP_REFERER' => route('books.show', $this->book)])
            ->assertRedirect(route('books.show', $this->book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
        ]);
        $this->assertDatabaseCount('favorites', 1);

        // 2. 解除
        $this->actingAs($this->user)->post(route('favorites.toggle', $this->book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
        ]);
        $this->assertDatabaseCount('favorites', 0);

        // 3. 再追加
        $this->actingAs($this->user)->post(route('favorites.toggle', $this->book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
        ]);
        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_トグル後は元の画面へ戻る(): void
    {
        $this->actingAs($this->user)
            ->from(route('books.index'))
            ->post(route('favorites.toggle', $this->book))
            ->assertRedirect(route('books.index'));
    }

    public function test_お気に入り一覧に登録した書籍が表示される(): void
    {
        $this->user->favoriteBooks()->attach($this->book->id);

        $this->actingAs($this->user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee($this->book->title)
            ->assertSee($this->book->author);
    }

    public function test_お気に入り一覧は10件ずつページネーションされる(): void
    {
        $books = Book::factory()->count(11)->create();
        $this->user->favoriteBooks()->attach($books->pluck('id'));

        $response = $this->actingAs($this->user)->get(route('favorites.index'))->assertOk();

        $paginated = $response->viewData('books');
        $this->assertCount(10, $paginated);
        $this->assertSame(11, $paginated->total());
        $this->assertSame(2, $paginated->lastPage());

        $this->assertCount(
            1,
            $this->actingAs($this->user)->get(route('favorites.index').'?page=2')->viewData('books')
        );
    }

    public function test_お気に入り一覧は他ユーザーの登録を含まない(): void
    {
        $other = User::factory()->create();
        $otherBook = Book::factory()->create(['title' => '他人のお気に入り書籍']);
        $other->favoriteBooks()->attach($otherBook->id);
        $this->user->favoriteBooks()->attach($this->book->id);

        $this->actingAs($this->user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee($this->book->title)
            ->assertDontSee('他人のお気に入り書籍');
    }

    public function test_お気に入りが0件なら空メッセージを表示する(): void
    {
        $this->actingAs($this->user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('お気に入りに登録された書籍はありません。');
    }

    public function test_ゲストはお気に入り一覧にアクセスできない(): void
    {
        $this->get(route('favorites.index'))->assertRedirect('/login');
    }

    public function test_ゲストはお気に入りをトグルできない(): void
    {
        $this->post(route('favorites.toggle', $this->book))->assertRedirect('/login');

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_書籍詳細でお気に入り状態が切り替わる(): void
    {
        $this->actingAs($this->user)
            ->get(route('books.show', $this->book))
            ->assertSee('お気に入りに追加');

        $this->user->favoriteBooks()->attach($this->book->id);

        $this->actingAs($this->user)
            ->get(route('books.show', $this->book))
            ->assertSee('お気に入りから削除');
    }
}
