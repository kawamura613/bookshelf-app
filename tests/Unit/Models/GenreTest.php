<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_リレーションは_belongstomany_で_book_genre_テーブルを使う(): void
    {
        $relation = (new Genre)->books();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertSame('book_genre', $relation->getTable());
        $this->assertSame('book_genre.genre_id', $relation->getQualifiedForeignPivotKeyName());
        $this->assertSame('book_genre.book_id', $relation->getQualifiedRelatedPivotKeyName());
    }

    public function test_fillable_は_name_のみ(): void
    {
        $this->assertSame(['name'], (new Genre)->getFillable());
    }

    public function test_紐づく書籍を取得できる(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(3)->create();

        $genre->books()->attach($books->pluck('id'));

        $this->assertCount(3, $genre->books);
        $this->assertInstanceOf(Book::class, $genre->books->first());
    }

    public function test_書籍数を_withcount_で集計できる(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->count(2)->create()->pluck('id'));
        $empty = Genre::factory()->create();

        $this->assertSame(2, Genre::withCount('books')->find($genre->id)->books_count);
        $this->assertSame(0, Genre::withCount('books')->find($empty->id)->books_count);
    }

    public function test_name_は一意である(): void
    {
        Genre::create(['name' => '一意テスト']);

        $this->expectException(QueryException::class);

        Genre::create(['name' => '一意テスト']);
    }
}
