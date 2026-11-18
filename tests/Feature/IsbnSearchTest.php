<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://www.googleapis.com/books/v1/volumes*';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * Google Books API の正常レスポンス。
     *
     * @return array<string, mixed>
     */
    private function volumeResponse(): array
    {
        return [
            'totalItems' => 1,
            'items' => [
                [
                    'volumeInfo' => [
                        'title' => '吾輩は猫である',
                        'authors' => ['夏目漱石', '別の著者'],
                        'publishedDate' => '1905-01-01',
                        'description' => '猫の視点から描いた長編小説。',
                        'imageLinks' => [
                            'smallThumbnail' => 'http://books.google.com/small.jpg',
                            'thumbnail' => 'http://books.google.com/thumb.jpg',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_成功時は_200_でフォームの項目名で返る(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->volumeResponse())]);

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertOk()
            ->assertExactJson([
                'title' => '吾輩は猫である',
                'author' => '夏目漱石, 別の著者',
                'published_date' => '1905-01-01',
                'description' => '猫の視点から描いた長編小説。',
                // http のサムネイルは https に揃える。
                'image_url' => 'https://books.google.com/thumb.jpg',
            ]);
    }

    public function test_リクエストに_isbn_の検索条件が付与される(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->volumeResponse())]);

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertOk();

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'q=isbn%3A9784101010014');
        });
    }

    public function test_api_キーが未設定ならキーを付与しない(): void
    {
        config(['services.google_books.key' => null]);
        Http::fake([self::ENDPOINT => Http::response($this->volumeResponse())]);

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertOk();

        Http::assertSent(fn ($request): bool => ! str_contains($request->url(), 'key='));
    }

    public function test_api_キーが設定されていればキーを付与する(): void
    {
        config(['services.google_books.key' => 'test-api-key']);
        Http::fake([self::ENDPOINT => Http::response($this->volumeResponse())]);

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertOk();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'key=test-api-key'));
    }

    public function test_任意項目が欠けていても_null_で返る(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'totalItems' => 1,
            'items' => [['volumeInfo' => ['title' => 'タイトルのみ']]],
        ])]);

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertOk()
            ->assertExactJson([
                'title' => 'タイトルのみ',
                'author' => null,
                'published_date' => null,
                'description' => null,
                'image_url' => null,
            ]);
    }

    public function test_13桁の数字以外は_400_になる(): void
    {
        Http::fake();

        foreach (['123', '97841010100149', 'abcdefghijklm'] as $isbn) {
            $this->actingAs($this->user)
                ->getJson(route('books.isbn', ['isbn' => $isbn]))
                ->assertStatus(400)
                ->assertExactJson(['error' => 'ISBNは13桁で入力してください。']);
        }

        Http::assertNothingSent();
    }

    public function test_書籍が見つからない場合は_404_になる(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['totalItems' => 0])]);

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertNotFound()
            ->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }

    public function test_クォータ超過は_429_になる(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => []], 429)]);

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertStatus(429)
            ->assertExactJson([
                'error' => 'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。',
            ]);
    }

    public function test_api_がエラーを返した場合は_500_になる(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => []], 503)]);

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertStatus(500)
            ->assertExactJson(['error' => 'API通信エラーが発生しました。']);
    }

    public function test_通信エラーは_500_になる(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs($this->user)
            ->getJson(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertStatus(500)
            ->assertExactJson(['error' => 'API通信エラーが発生しました。']);
    }

    public function test_未ログインではアクセスできない(): void
    {
        Http::fake();

        $this->get(route('books.isbn', ['isbn' => '9784101010014']))
            ->assertRedirect(route('login'));

        Http::assertNothingSent();
    }
}
