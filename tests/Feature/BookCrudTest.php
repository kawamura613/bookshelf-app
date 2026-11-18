<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Genre $genre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->genre = Genre::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2026-11-15',
            'description' => 'テストの説明です。',
            'image_url' => 'https://placehold.co/300x400?text=1',
            'genres' => [$this->genre->id],
        ], $overrides);
    }

    public function test_一覧は最新順に10件ずつページネーションされる(): void
    {
        Book::factory()->count(11)->create();

        $response = $this->get(route('books.index'))->assertOk();

        $books = $response->viewData('books');
        $this->assertCount(10, $books);
        $this->assertSame(11, $books->total());
        $this->assertSame(2, $books->lastPage());

        $this->assertCount(1, $this->get(route('books.index').'?page=2')->viewData('books'));
    }

    public function test_一覧は平均評価を集計して渡す(): void
    {
        $book = Book::factory()->create();
        Review::factory()->rating(5)->create(['book_id' => $book->id]);
        Review::factory()->rating(3)->create(['book_id' => $book->id]);

        $books = $this->get(route('books.index'))->viewData('books');

        // 一覧の Blade は reviews_avg_rating のみ参照するため withAvg だけを付与している。
        $this->assertSame(4.0, (float) $books->first()->reviews_avg_rating);
    }

    public function test_一覧はジャンルを_eager_loading_する(): void
    {
        $book = Book::factory()->create();
        $book->genres()->attach($this->genre->id);

        $books = $this->get(route('books.index'))->viewData('books');

        $this->assertTrue($books->first()->relationLoaded('genres'));
        $this->assertSame($this->genre->name, $books->first()->genres->first()->name);
    }

    public function test_詳細に書籍情報とジャンルとレビューが表示される(): void
    {
        $book = Book::factory()->create(['title' => '詳細テスト書籍', 'author' => '詳細著者']);
        $book->genres()->attach($this->genre->id);
        $reviewer = User::factory()->create(['name' => 'レビュー投稿者']);
        Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $reviewer->id,
            'comment' => 'レビュー本文テスト',
        ]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('詳細テスト書籍')
            ->assertSee('詳細著者')
            ->assertSee($this->genre->name)
            ->assertSee('レビュー投稿者')
            ->assertSee('レビュー本文テスト');
    }

    public function test_書籍を登録でき詳細へリダイレクトされる(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('books.store'), $this->validPayload());

        $book = Book::where('isbn', '9781234567897')->firstOrFail();

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', '書籍を登録しました。');

        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $this->genre->id,
        ]);
    }

    public function test_書籍登録はログイン中のユーザーが登録者になる(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)->post(route('books.store'), $this->validPayload([
            'user_id' => $other->id,
        ]));

        $this->assertDatabaseHas('books', [
            'isbn' => '9781234567897',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_書籍登録のバリデーションエラーが日本語で返る(): void
    {
        $response = $this->actingAs($this->user)
            ->from(route('books.create'))
            ->post(route('books.store'), [
                'title' => '',
                'author' => '',
                'isbn' => '123',
                'published_date' => 'not-a-date',
                'image_url' => 'not-a-url',
                'genres' => [],
            ]);

        $response->assertRedirect(route('books.create'));
        $response->assertSessionHasErrors([
            'title' => 'タイトルを入力してください。',
            'author' => '著者を入力してください。',
            'isbn' => 'ISBNは13桁の数字で入力してください。',
            'published_date' => '出版日は有効な日付を入力してください。',
            'image_url' => '画像URLは有効なURL形式で入力してください。',
            'genres' => 'ジャンルを1つ以上選択してください。',
        ]);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_書籍登録で_isbn_が重複するとエラーになる(): void
    {
        Book::factory()->create(['isbn' => '9781234567897']);

        $this->actingAs($this->user)
            ->from(route('books.create'))
            ->post(route('books.store'), $this->validPayload())
            ->assertSessionHasErrors(['isbn' => 'このISBNは既に登録されています。']);

        $this->assertDatabaseCount('books', 1);
    }

    public function test_書籍登録で存在しないジャンルを指定するとエラーになる(): void
    {
        $this->actingAs($this->user)
            ->from(route('books.create'))
            ->post(route('books.store'), $this->validPayload(['genres' => [9999]]))
            ->assertSessionHasErrors(['genres.0' => '選択されたジャンルは存在しません。']);
    }

    public function test_作成者は編集画面を開ける(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $book->genres()->attach($this->genre->id);

        $this->actingAs($this->user)
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertSee($book->title);
    }

    public function test_書籍を更新でき詳細へリダイレクトされる(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $book->genres()->attach($this->genre->id);
        $newGenre = Genre::factory()->create();

        $response = $this->actingAs($this->user)->put(route('books.update', $book), $this->validPayload([
            'title' => '更新後のタイトル',
            'isbn' => '9789876543210',
            'genres' => [$newGenre->id],
        ]));

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', '書籍情報を更新しました。');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後のタイトル',
            'isbn' => '9789876543210',
        ]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $newGenre->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $this->genre->id]);
    }

    public function test_更新時は自身の_isbn_を一意制約から除外する(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id, 'isbn' => '9781234567897']);
        $book->genres()->attach($this->genre->id);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validPayload(['title' => '据え置き更新']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '据え置き更新']);
    }

    public function test_更新時に他書籍の_isbn_を指定するとエラーになる(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id, 'isbn' => '9781111111117']);
        Book::factory()->create(['isbn' => '9782222222228']);

        $this->actingAs($this->user)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), $this->validPayload(['isbn' => '9782222222228']))
            ->assertSessionHasErrors(['isbn' => 'このISBNは既に登録されています。']);
    }

    public function test_他ユーザーは編集画面にアクセスできず_403_になる(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('books.edit', $book))->assertForbidden();
    }

    public function test_他ユーザーは更新できず_403_になる(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id, 'title' => '元のタイトル']);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->put(route('books.update', $book), $this->validPayload(['title' => '乗っ取り']))
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '元のタイトル']);
    }

    public function test_他ユーザーは削除できず_403_になる(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $other = User::factory()->create();

        $this->actingAs($other)->delete(route('books.destroy', $book))->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_書籍を削除でき一覧へリダイレクトされる(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('books.destroy', $book));

        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を削除しました。');
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_書籍削除で関連データも削除される(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $book->genres()->attach($this->genre->id);
        $review = Review::factory()->create(['book_id' => $book->id, 'user_id' => $this->user->id]);
        $review->likedByUsers()->attach($this->user->id);
        $this->user->favoriteBooks()->attach($book->id);

        $this->actingAs($this->user)->delete(route('books.destroy', $book));

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('review_likes', 0);
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_詳細画面で作成者だけに編集削除ボタンが表示される(): void
    {
        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $book->genres()->attach($this->genre->id);
        $other = User::factory()->create();

        $this->actingAs($this->user)->get(route('books.show', $book))
            ->assertSee(route('books.edit', $book));

        $this->actingAs($other)->get(route('books.show', $book))
            ->assertDontSee(route('books.edit', $book));
    }

    // ---------- isbn / published_date の任意項目化 ----------

    public function test_isbn_と_published_date_を省略して登録できる(): void
    {
        $payload = $this->validPayload();
        unset($payload['isbn'], $payload['published_date']);

        $this->actingAs($this->user)
            ->post(route('books.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_isbn_と_published_date_が空文字でも登録できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validPayload([
                'isbn' => '',
                'published_date' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_isbn_を入力した場合は13桁チェックが効く(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validPayload(['isbn' => '123']))
            ->assertSessionHasErrors(['isbn' => 'ISBNは13桁の数字で入力してください。']);
    }

    public function test_isbn_が未登録の書籍が複数あっても一意制約に違反しない(): void
    {
        Book::factory()->create(['isbn' => null]);
        $payload = $this->validPayload();
        unset($payload['isbn']);

        $this->actingAs($this->user)
            ->post(route('books.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Book::whereNull('isbn')->count());
    }

    public function test_更新で_isbn_と_published_date_を未登録に戻せる(): void
    {
        $book = Book::factory()->create([
            'user_id' => $this->user->id,
            'isbn' => '9781234567897',
            'published_date' => '2026-11-15',
        ]);
        $book->genres()->attach($this->genre->id);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validPayload([
                'isbn' => '',
                'published_date' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'isbn' => null,
            'published_date' => null,
        ]);
    }
}
