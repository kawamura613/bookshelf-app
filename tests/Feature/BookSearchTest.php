<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * 一覧に渡された書籍のタイトルを順番どおりに取り出す。
     *
     * @return array<int, string>
     */
    private function titlesFrom(TestResponse $response): array
    {
        /** @var LengthAwarePaginator<Book> $books */
        $books = $response->viewData('books');

        return $books->pluck('title')->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createBook(string $title, string $author = '著者', array $attributes = []): Book
    {
        return Book::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'title' => $title,
            'author' => $author,
        ], $attributes));
    }

    // ---------- キーワード検索 ----------

    public function test_キーワードでタイトルを部分一致検索できる(): void
    {
        $this->createBook('リーダブルコード');
        $this->createBook('吾輩は猫である');

        $titles = $this->titlesFrom($this->get(route('books.index', ['keyword' => 'コード'])));

        $this->assertSame(['リーダブルコード'], $titles);
    }

    public function test_キーワードで著者を部分一致検索できる(): void
    {
        $this->createBook('吾輩は猫である', '夏目漱石');
        $this->createBook('リーダブルコード', 'Dustin Boswell');

        $titles = $this->titlesFrom($this->get(route('books.index', ['keyword' => '漱石'])));

        $this->assertSame(['吾輩は猫である'], $titles);
    }

    public function test_キーワードが空文字なら絞り込まれない(): void
    {
        $this->createBook('書籍A');
        $this->createBook('書籍B');

        $titles = $this->titlesFrom($this->get(route('books.index', ['keyword' => '  '])));

        $this->assertCount(2, $titles);
    }

    public function test_検索結果が0件でもエラーにならない(): void
    {
        $this->createBook('書籍A');

        $response = $this->get(route('books.index', ['keyword' => '該当なし']))->assertOk();

        $this->assertSame([], $this->titlesFrom($response));
    }

    // ---------- ジャンル絞り込み ----------

    public function test_ジャンルで絞り込める(): void
    {
        $technical = Genre::factory()->create(['name' => '技術書']);
        $novel = Genre::factory()->create(['name' => '小説']);

        $this->createBook('リーダブルコード')->genres()->attach($technical->id);
        $this->createBook('吾輩は猫である')->genres()->attach($novel->id);

        $titles = $this->titlesFrom($this->get(route('books.index', ['genre' => $technical->id])));

        $this->assertSame(['リーダブルコード'], $titles);
    }

    public function test_キーワードとジャンルを併用できる(): void
    {
        $technical = Genre::factory()->create();

        $this->createBook('リーダブルコード')->genres()->attach($technical->id);
        $this->createBook('Clean Code')->genres()->attach($technical->id);
        $this->createBook('コード進行の教科書');

        $titles = $this->titlesFrom($this->get(route('books.index', [
            'keyword' => 'コード',
            'genre' => $technical->id,
        ])));

        $this->assertSame(['リーダブルコード'], $titles);
    }

    public function test_ジャンル選択プルダウン用のジャンル一覧が渡る(): void
    {
        $genre = Genre::factory()->create(['name' => '技術書']);

        $response = $this->get(route('books.index'))->assertOk();

        $this->assertSame([$genre->name], $response->viewData('genres')->pluck('name')->all());
    }

    public function test_存在しないジャンルを指定するとバリデーションエラーになる(): void
    {
        $this->get(route('books.index', ['genre' => 9999]))
            ->assertSessionHasErrors(['genre' => '指定されたジャンルは存在しません。']);
    }

    // ---------- 並び替え ----------

    public function test_既定は登録が新しい順(): void
    {
        $this->createBook('古い書籍', '著者', ['created_at' => now()->subDay()]);
        $this->createBook('新しい書籍', '著者', ['created_at' => now()]);

        $this->assertSame(
            ['新しい書籍', '古い書籍'],
            $this->titlesFrom($this->get(route('books.index')))
        );
    }

    public function test_newest_と_latest_はどちらも新しい順になる(): void
    {
        $this->createBook('古い書籍', '著者', ['created_at' => now()->subDay()]);
        $this->createBook('新しい書籍', '著者', ['created_at' => now()]);

        foreach (['newest', 'latest'] as $sort) {
            $this->assertSame(
                ['新しい書籍', '古い書籍'],
                $this->titlesFrom($this->get(route('books.index', ['sort' => $sort]))),
                "sort={$sort}"
            );
        }
    }

    public function test_oldest_は登録が古い順になる(): void
    {
        $this->createBook('古い書籍', '著者', ['created_at' => now()->subDay()]);
        $this->createBook('新しい書籍', '著者', ['created_at' => now()]);

        $this->assertSame(
            ['古い書籍', '新しい書籍'],
            $this->titlesFrom($this->get(route('books.index', ['sort' => 'oldest'])))
        );
    }

    public function test_title_はタイトルの昇順になる(): void
    {
        $this->createBook('B書籍');
        $this->createBook('A書籍');
        $this->createBook('C書籍');

        $this->assertSame(
            ['A書籍', 'B書籍', 'C書籍'],
            $this->titlesFrom($this->get(route('books.index', ['sort' => 'title'])))
        );
    }

    public function test_rating_は平均評価の高い順でレビューなしは末尾になる(): void
    {
        $low = $this->createBook('低評価');
        $high = $this->createBook('高評価');
        $this->createBook('レビューなし');

        Review::factory()->create(['book_id' => $low->id, 'user_id' => $this->user->id, 'rating' => 2]);
        Review::factory()->create(['book_id' => $high->id, 'user_id' => $this->user->id, 'rating' => 5]);

        $this->assertSame(
            ['高評価', '低評価', 'レビューなし'],
            $this->titlesFrom($this->get(route('books.index', ['sort' => 'rating'])))
        );
    }

    public function test_不正な並び順はバリデーションエラーになる(): void
    {
        $this->get(route('books.index', ['sort' => 'bogus']))
            ->assertSessionHasErrors(['sort' => '並び順の指定が正しくありません。']);
    }

    // ---------- ページネーションへの条件引き継ぎ ----------

    public function test_検索条件がページネーションリンクに維持される(): void
    {
        $genre = Genre::factory()->create();

        foreach (range(1, 12) as $index) {
            $this->createBook("検索対象書籍{$index}")->genres()->attach($genre->id);
        }

        $response = $this->get(route('books.index', [
            'keyword' => '検索対象',
            'genre' => $genre->id,
            'sort' => 'title',
        ]))->assertOk();

        /** @var LengthAwarePaginator<Book> $books */
        $books = $response->viewData('books');

        $this->assertSame(12, $books->total());
        $this->assertCount(10, $books->items());

        $nextPageUrl = $books->nextPageUrl();
        $this->assertNotNull($nextPageUrl);
        $this->assertStringContainsString('keyword=', $nextPageUrl);
        $this->assertStringContainsString('genre='.$genre->id, $nextPageUrl);
        $this->assertStringContainsString('sort=title', $nextPageUrl);
    }

    public function test_ルート_スラッシュ_でも同じ検索ができる(): void
    {
        $this->createBook('リーダブルコード');
        $this->createBook('吾輩は猫である');

        $this->assertSame(
            ['リーダブルコード'],
            $this->titlesFrom($this->get('/?keyword=コード'))
        );
    }
}
