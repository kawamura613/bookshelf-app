<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_FOUND_JSON = ['error' => '書籍が見つかりませんでした。'];

    private const FORBIDDEN_JSON = ['error' => 'この操作を実行する権限がありません。'];

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
            'title' => 'API書籍',
            'author' => 'API著者',
            'isbn' => '9781234567897',
            'published_date' => '2026-11-15',
            'description' => 'APIの説明。',
            'image_url' => 'https://placehold.co/300x400?text=1',
            'genres' => [$this->genre->id],
        ], $overrides);
    }

    // ---------- AP01: 一覧 ----------

    public function test_ap01_一覧は認証なしで取得でき_data_と_meta_を含む(): void
    {
        $book = Book::factory()->create();
        $book->genres()->attach($this->genre->id);

        $response = $this->getJson('/api/v1/books')->assertOk();

        $response->assertJsonStructure([
            'data' => [['id', 'user_id', 'title', 'author', 'isbn', 'published_date',
                'description', 'image_url', 'genres' => [['id', 'name']],
                'average_rating', 'reviews_count', 'created_at', 'updated_at']],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('meta.per_page', 20);
    }

    public function test_ap01_一覧には_reviews_が含まれない(): void
    {
        $book = Book::factory()->create();
        Review::factory()->create(['book_id' => $book->id]);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonMissingPath('data.0.reviews');
    }

    public function test_ap01_accept_ヘッダーなしでも_json_が返る(): void
    {
        Book::factory()->create();

        $this->get('/api/v1/books')
            ->assertOk()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_ap01_平均評価とレビュー件数が集計される(): void
    {
        $book = Book::factory()->create();
        Review::factory()->rating(5)->create(['book_id' => $book->id]);
        Review::factory()->rating(4)->create(['book_id' => $book->id]);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('data.0.average_rating', 4.5)
            ->assertJsonPath('data.0.reviews_count', 2);
    }

    public function test_ap01_レビューが無い書籍の平均評価は_null(): void
    {
        Book::factory()->create();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('data.0.average_rating', null)
            ->assertJsonPath('data.0.reviews_count', 0);
    }

    public function test_ap01_キーワードでタイトルを部分一致検索できる(): void
    {
        Book::factory()->create(['title' => 'リーダブルコード']);
        Book::factory()->create(['title' => '吾輩は猫である']);

        $response = $this->getJson('/api/v1/books?keyword='.urlencode('リーダブル'))->assertOk();

        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.title', 'リーダブルコード');
    }

    public function test_ap01_キーワードで著者を部分一致検索できる(): void
    {
        Book::factory()->create(['title' => '坊っちゃん', 'author' => '夏目漱石']);
        Book::factory()->create(['title' => '別の本', 'author' => '別の著者']);

        $this->getJson('/api/v1/books?keyword='.urlencode('夏目'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.author', '夏目漱石');
    }

    public function test_ap01_ヒットしないキーワードは空配列を返す(): void
    {
        Book::factory()->create(['title' => 'ある本']);

        $this->getJson('/api/v1/books?keyword='.urlencode('存在しない書名'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_ap01_ジャンルで絞り込める(): void
    {
        $target = Book::factory()->create(['title' => '対象書籍']);
        $target->genres()->attach($this->genre->id);
        $other = Book::factory()->create(['title' => '対象外書籍']);
        $other->genres()->attach(Genre::factory()->create()->id);

        $this->getJson("/api/v1/books?genre_id={$this->genre->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', '対象書籍');
    }

    public function test_ap01_キーワードとジャンルを併用できる(): void
    {
        $hit = Book::factory()->create(['title' => 'Clean Code']);
        $hit->genres()->attach($this->genre->id);
        $titleOnly = Book::factory()->create(['title' => 'Clean Architecture']);
        $titleOnly->genres()->attach(Genre::factory()->create()->id);

        $this->getJson("/api/v1/books?keyword=Clean&genre_id={$this->genre->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Clean Code');
    }

    public function test_ap01_ページネーションが機能する(): void
    {
        Book::factory()->count(25)->create();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/v1/books?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_ap01_per_page_を指定できる(): void
    {
        Book::factory()->count(7)->create();

        $this->getJson('/api/v1/books?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_ap01_per_page_が上限を超えると_422_になる(): void
    {
        $this->getJson('/api/v1/books?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page' => '1ページあたりの件数は100以下で指定してください。']);
    }

    public function test_ap01_不正なクエリパラメータは_422_になる(): void
    {
        $this->getJson('/api/v1/books?genre_id=9999&page=0&per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'genre_id' => '指定されたジャンルは存在しません。',
                'page' => 'ページ番号は1以上で指定してください。',
                'per_page' => '1ページあたりの件数は1以上で指定してください。',
            ]);
    }

    public function test_ap01_accept_ヘッダーなしでも_422_json_が返る(): void
    {
        $this->get('/api/v1/books?per_page=101')
            ->assertStatus(422)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['message', 'errors']);
    }

    // ---------- AP02: 詳細 ----------

    public function test_ap02_詳細を取得でき_reviews_を含む(): void
    {
        $book = Book::factory()->create();
        $book->genres()->attach($this->genre->id);
        $reviewer = User::factory()->create(['name' => 'レビュー投稿者']);
        Review::factory()->rating(5)->create([
            'book_id' => $book->id,
            'user_id' => $reviewer->id,
            'comment' => 'APIレビュー本文',
        ]);

        $response = $this->getJson("/api/v1/books/{$book->id}")->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'id', 'user_id', 'title', 'author', 'isbn', 'published_date',
                'description', 'image_url', 'genres' => [['id', 'name']],
                'average_rating', 'reviews_count',
                'reviews' => [['id', 'user_name', 'rating', 'comment', 'created_at']],
                'created_at', 'updated_at',
            ],
        ]);
        $response->assertJsonPath('data.id', $book->id);
        $response->assertJsonPath('data.reviews_count', 1);
        $response->assertJsonPath('data.average_rating', 5);
        $response->assertJsonPath('data.reviews.0.user_name', 'レビュー投稿者');
        $response->assertJsonPath('data.reviews.0.comment', 'APIレビュー本文');
        $response->assertJsonPath('data.genres.0.name', $this->genre->name);
    }

    public function test_ap02_出版日は_y_m_d_形式で返る(): void
    {
        $book = Book::factory()->create(['published_date' => '2026-11-16']);

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.published_date', '2026-11-16');
    }

    public function test_ap02_存在しないidは_404_で確定仕様のjsonを返す(): void
    {
        $this->getJson('/api/v1/books/9999')
            ->assertNotFound()
            ->assertExactJson(self::NOT_FOUND_JSON);
    }

    public function test_ap02_accept_ヘッダーなしでも_404_jsonを返す(): void
    {
        $this->get('/api/v1/books/9999')
            ->assertNotFound()
            ->assertExactJson(self::NOT_FOUND_JSON);
    }

    // ---------- AP03: 登録 ----------

    public function test_ap03_書籍を登録でき_201_を返す(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/books', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'API書籍');
        $response->assertJsonPath('data.user_id', $this->user->id);
        $response->assertJsonPath('data.genres.0.id', $this->genre->id);
        $response->assertJsonPath('data.average_rating', null);
        $response->assertJsonPath('data.reviews_count', 0);

        $this->assertDatabaseHas('books', ['isbn' => '9781234567897', 'user_id' => $this->user->id]);
        $this->assertDatabaseHas('book_genre', ['genre_id' => $this->genre->id]);
    }

    public function test_ap03_複数ジャンルを紐付けられる(): void
    {
        Sanctum::actingAs($this->user);

        $second = Genre::factory()->create();

        $this->postJson('/api/v1/books', $this->validPayload([
            'genres' => [$this->genre->id, $second->id],
        ]))->assertCreated()->assertJsonCount(2, 'data.genres');
    }

    public function test_ap03_バリデーションエラーは_422_で日本語メッセージを返す(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/books', [
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'published_date' => 'not-a-date',
            'image_url' => 'not-a-url',
            'genres' => [],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'title' => 'タイトルを入力してください。',
            'author' => '著者を入力してください。',
            'isbn' => 'ISBNは13桁の数字で入力してください。',
            'published_date' => '出版日は有効な日付を入力してください。',
            'image_url' => '画像URLは有効なURL形式で入力してください。',
            'genres' => 'ジャンルを1つ以上指定してください。',
        ]);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_ap03_isbn_が重複すると_422_になる(): void
    {
        Sanctum::actingAs($this->user);

        Book::factory()->create(['isbn' => '9781234567897']);

        $this->postJson('/api/v1/books', $this->validPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isbn' => 'このISBNは既に登録されています。']);
    }

    public function test_ap03_存在しないジャンルを指定すると_422_になる(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/books', $this->validPayload(['genres' => [9999]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['genres.0' => '指定されたジャンルは存在しません。']);
    }

    public function test_ap03_accept_ヘッダーなしでも_422_json_が返る(): void
    {
        Sanctum::actingAs($this->user);

        $this->post('/api/v1/books', ['title' => ''])
            ->assertStatus(422)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['message', 'errors']);
    }

    // ---------- AP04: 更新 ----------

    public function test_ap04_書籍を更新でき_200_を返す(): void
    {
        Sanctum::actingAs($this->user);

        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $book->genres()->attach($this->genre->id);
        $newGenre = Genre::factory()->create();

        $response = $this->putJson("/api/v1/books/{$book->id}", $this->validPayload([
            'title' => '更新後のAPI書籍',
            'isbn' => '9789876543210',
            'genres' => [$newGenre->id],
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.title', '更新後のAPI書籍');
        // 作成者は更新対象にしない。
        $response->assertJsonPath('data.user_id', $this->user->id);
        $response->assertJsonCount(1, 'data.genres');
        $response->assertJsonPath('data.genres.0.id', $newGenre->id);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'isbn' => '9789876543210']);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $this->genre->id]);
    }

    public function test_ap04_自身の_isbn_は一意制約から除外される(): void
    {
        Sanctum::actingAs($this->user);

        $book = Book::factory()->create(['user_id' => $this->user->id, 'isbn' => '9781234567897']);
        $book->genres()->attach($this->genre->id);

        $this->putJson("/api/v1/books/{$book->id}", $this->validPayload(['title' => '据え置き更新']))
            ->assertOk()
            ->assertJsonPath('data.title', '据え置き更新')
            ->assertJsonPath('data.isbn', '9781234567897');
    }

    public function test_ap04_他書籍の_isbn_を指定すると_422_になる(): void
    {
        Sanctum::actingAs($this->user);

        $book = Book::factory()->create(['user_id' => $this->user->id, 'isbn' => '9781111111117']);
        Book::factory()->create(['isbn' => '9782222222228']);

        $this->putJson("/api/v1/books/{$book->id}", $this->validPayload(['isbn' => '9782222222228']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isbn' => 'このISBNは既に登録されています。']);
    }

    public function test_ap04_存在しないidは_404_で確定仕様のjsonを返す(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/v1/books/9999', $this->validPayload())
            ->assertNotFound()
            ->assertExactJson(self::NOT_FOUND_JSON);
    }

    // ---------- AP05: 削除 ----------

    public function test_ap05_書籍を削除でき_204_を返す(): void
    {
        Sanctum::actingAs($this->user);

        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_ap05_削除で関連データも削除される(): void
    {
        Sanctum::actingAs($this->user);

        $book = Book::factory()->create(['user_id' => $this->user->id]);
        $book->genres()->attach($this->genre->id);
        $review = Review::factory()->create(['book_id' => $book->id, 'user_id' => $this->user->id]);
        $review->likedByUsers()->attach($this->user->id);
        $this->user->favoriteBooks()->attach($book->id);

        $this->deleteJson("/api/v1/books/{$book->id}")->assertNoContent();

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('review_likes', 0);
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_ap05_存在しないidは_404_で確定仕様のjsonを返す(): void
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson('/api/v1/books/9999')
            ->assertNotFound()
            ->assertExactJson(self::NOT_FOUND_JSON);
    }

    public function test_ap05_削除済みidへの再削除は_404_になる(): void
    {
        Sanctum::actingAs($this->user);

        $book = Book::factory()->create(['user_id' => $this->user->id]);

        $this->deleteJson("/api/v1/books/{$book->id}")->assertNoContent();
        $this->deleteJson("/api/v1/books/{$book->id}")
            ->assertNotFound()
            ->assertExactJson(self::NOT_FOUND_JSON);
    }

    // ---------- AP06: Sanctum トークン認証 ----------

    public function test_ap06_参照系は認証なしで利用できる(): void
    {
        $book = Book::factory()->create();

        $this->getJson('/api/v1/books')->assertOk();
        $this->getJson("/api/v1/books/{$book->id}")->assertOk();
    }

    public function test_ap06_未認証の登録は_401_になる(): void
    {
        $this->postJson('/api/v1/books', $this->validPayload())->assertUnauthorized();

        $this->assertDatabaseCount('books', 0);
    }

    public function test_ap06_未認証の更新は_401_になる(): void
    {
        $book = Book::factory()->create(['title' => '変更前']);

        $this->putJson("/api/v1/books/{$book->id}", $this->validPayload())->assertUnauthorized();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '変更前']);
    }

    public function test_ap06_未認証の削除は_401_になる(): void
    {
        $book = Book::factory()->create();

        $this->deleteJson("/api/v1/books/{$book->id}")->assertUnauthorized();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_ap06_登録者は常に認証ユーザーになる(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($this->user);

        // リクエストボディの user_id は無視される。
        $this->postJson('/api/v1/books', $this->validPayload(['user_id' => $other->id]))
            ->assertCreated()
            ->assertJsonPath('data.user_id', $this->user->id);

        $this->assertDatabaseHas('books', ['isbn' => '9781234567897', 'user_id' => $this->user->id]);
    }

    public function test_ap06_他ユーザーの書籍は更新できず_403_になる(): void
    {
        $book = Book::factory()->create(['title' => '他人の本']);
        $book->genres()->attach($this->genre->id);

        Sanctum::actingAs($this->user);

        $this->putJson("/api/v1/books/{$book->id}", $this->validPayload())
            ->assertForbidden()
            ->assertExactJson(self::FORBIDDEN_JSON);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '他人の本']);
    }

    public function test_ap06_他ユーザーの書籍は削除できず_403_になる(): void
    {
        $book = Book::factory()->create();

        Sanctum::actingAs($this->user);

        $this->deleteJson("/api/v1/books/{$book->id}")
            ->assertForbidden()
            ->assertExactJson(self::FORBIDDEN_JSON);

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_ap06_公開apiでは_isbn_と_published_date_が必須のまま維持される(): void
    {
        Sanctum::actingAs($this->user);

        $payload = $this->validPayload();
        unset($payload['isbn'], $payload['published_date']);

        $this->postJson('/api/v1/books', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'isbn' => 'ISBNを入力してください。',
                'published_date' => '出版日を入力してください。',
            ]);
    }
}
