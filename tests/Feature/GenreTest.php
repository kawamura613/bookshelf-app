<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_一覧に各ジャンルの書籍数が表示される(): void
    {
        $withBooks = Genre::factory()->create(['name' => '書籍ありジャンル']);
        $withBooks->books()->attach(Book::factory()->count(3)->create()->pluck('id'));
        $empty = Genre::factory()->create(['name' => '書籍なしジャンル']);

        $response = $this->actingAs($this->user)->get(route('genres.index'))->assertOk();

        $genres = $response->viewData('genres');
        $this->assertSame(3, $genres->firstWhere('id', $withBooks->id)->books_count);
        $this->assertSame(0, $genres->firstWhere('id', $empty->id)->books_count);

        $response->assertSee('書籍ありジャンル')->assertSee('3冊')->assertSee('0冊');
    }

    public function test_詳細は紐づく書籍を10件ずつページネーションする(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->count(11)->create()->pluck('id'));

        $response = $this->actingAs($this->user)->get(route('genres.show', $genre))->assertOk();

        $books = $response->viewData('books');
        $this->assertCount(10, $books);
        $this->assertSame(11, $books->total());
        $this->assertSame(2, $books->lastPage());

        $this->assertCount(
            1,
            $this->actingAs($this->user)->get(route('genres.show', $genre).'?page=2')->viewData('books')
        );
    }

    public function test_詳細は書籍のジャンルを_eager_loading_する(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genre->id);

        $books = $this->actingAs($this->user)->get(route('genres.show', $genre))->viewData('books');

        $this->assertTrue($books->first()->relationLoaded('genres'));
    }

    public function test_書籍が0件のジャンル詳細は空メッセージを表示する(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs($this->user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('このジャンルの書籍はまだ登録されていません。');
    }

    public function test_ジャンルを登録でき一覧へリダイレクトされる(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => '新しいジャンル']);

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを作成しました。');
        $this->assertDatabaseHas('genres', ['name' => '新しいジャンル']);
    }

    public function test_ジャンル登録の未入力バリデーションが日本語で返る(): void
    {
        $this->actingAs($this->user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'ジャンル名を入力してください。']);

        $this->assertDatabaseCount('genres', 0);
    }

    public function test_ジャンル登録で名前が重複するとエラーになる(): void
    {
        Genre::factory()->create(['name' => '重複ジャンル']);

        $this->actingAs($this->user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => '重複ジャンル'])
            ->assertSessionHasErrors(['name' => 'このジャンル名は既に登録されています。']);

        $this->assertSame(1, Genre::where('name', '重複ジャンル')->count());
    }

    public function test_ジャンル登録で255文字を超えるとエラーになる(): void
    {
        $this->actingAs($this->user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), ['name' => str_repeat('あ', 256)])
            ->assertSessionHasErrors(['name' => 'ジャンル名は255文字以内で入力してください。']);
    }

    public function test_編集画面に現在の名前が表示される(): void
    {
        $genre = Genre::factory()->create(['name' => '編集前ジャンル']);

        $this->actingAs($this->user)
            ->get(route('genres.edit', $genre))
            ->assertOk()
            ->assertSee('編集前ジャンル');
    }

    public function test_ジャンルを更新でき一覧へリダイレクトされる(): void
    {
        $genre = Genre::factory()->create(['name' => '更新前']);

        $response = $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '更新後']);

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを更新しました。');
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '更新後']);
    }

    public function test_更新時は自身の名前を一意制約から除外する(): void
    {
        $genre = Genre::factory()->create(['name' => '据え置きジャンル']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '据え置きジャンル'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '据え置きジャンル']);
    }

    public function test_更新時に他ジャンルと重複する名前を指定するとエラーになる(): void
    {
        $genre = Genre::factory()->create(['name' => '自分']);
        Genre::factory()->create(['name' => '他人']);

        $this->actingAs($this->user)
            ->from(route('genres.edit', $genre))
            ->put(route('genres.update', $genre), ['name' => '他人'])
            ->assertSessionHasErrors(['name' => 'このジャンル名は既に登録されています。']);

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '自分']);
    }

    public function test_書籍が紐付くジャンルは削除できずエラーメッセージが返る(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->create()->id);

        $response = $this->actingAs($this->user)->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('error', 'このジャンルには書籍が紐付いているため削除できません。');
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_削除拒否のエラーメッセージが画面に表示される(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->create()->id);

        $this->actingAs($this->user)
            ->delete(route('genres.destroy', $genre));

        $this->actingAs($this->user)
            ->get(route('genres.index'))
            ->assertSee('このジャンルには書籍が紐付いているため削除できません。');
    }

    public function test_書籍が紐付かないジャンルは削除できる(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->actingAs($this->user)->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを削除しました。');
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    public function test_ゲストはジャンルを操作できない(): void
    {
        $genre = Genre::factory()->create();

        $this->post(route('genres.store'), ['name' => 'ゲスト登録'])->assertRedirect('/login');
        $this->put(route('genres.update', $genre), ['name' => 'ゲスト更新'])->assertRedirect('/login');
        $this->delete(route('genres.destroy', $genre))->assertRedirect('/login');

        $this->assertDatabaseMissing('genres', ['name' => 'ゲスト登録']);
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => $genre->name]);
    }
}
