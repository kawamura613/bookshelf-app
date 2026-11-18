<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * 評価（1〜5）別のコメントテンプレート。
     *
     * @var array<int, array<int, string>>
     */
    private const COMMENT_TEMPLATES = [
        5 => [
            '素晴らしい本でした！',
            '人生が変わりました。',
            '何度も読み返しています。',
        ],
        4 => [
            'とても参考になりました。',
            '読みやすくておすすめです。',
            '期待通りの内容でした。',
        ],
        3 => [
            '普通でした。',
            '可もなく不可もなく。',
            '期待したほどではなかった。',
        ],
        2 => [
            '少し期待外れでした。',
            '内容が薄い印象。',
            'もう少し深掘りしてほしかった。',
        ],
        1 => [
            '残念ながら合いませんでした。',
            '期待と違いました。',
        ],
    ];

    /**
     * 各書籍に 2〜4 件のレビューを投入する。
     *
     * 評価は 1〜5 の全範囲から選び、評価別の日本語テンプレートを
     * コメントに割り当てる。同一書籍に同一ユーザーが重複して投稿
     * しないよう、ユーザーをシャッフルして先頭から割り当てる。
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        if ($users->count() < 2 || $books->isEmpty()) {
            return;
        }

        $maxReviewers = min(4, $users->count());

        foreach ($books as $book) {
            $reviewers = $users->shuffle()->take(rand(2, $maxReviewers));

            foreach ($reviewers as $reviewer) {
                $rating = rand(1, 5);
                $templates = self::COMMENT_TEMPLATES[$rating];

                Review::create([
                    'book_id' => $book->id,
                    'user_id' => $reviewer->id,
                    'rating' => $rating,
                    'comment' => $templates[array_rand($templates)],
                ]);
            }
        }
    }
}
