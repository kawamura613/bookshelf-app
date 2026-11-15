<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * 各ユーザーに3〜5冊のお気に入りを設定する。
     */
    public function run(): void
    {
        $bookIds = Book::pluck('id', 'isbn');

        $favorites = [
            // 山田太郎（5冊）
            'yamada@example.com' => [
                '9784873115658', // リーダブルコード
                '9784048930598', // Clean Code
                '9784101010014', // 吾輩は猫である
                '9784822289607', // FACTFULNESS
                '9784309226712', // サピエンス全史
            ],
            // 鈴木花子（4冊）
            'suzuki@example.com' => [
                '9784422100524', // 人を動かす
                '9784863940246', // 7つの習慣
                '9784478025819', // 嫌われる勇気
                '9784163902302', // 火花
            ],
            // 田中一郎（3冊）
            'tanaka@example.com' => [
                '9784873115658', // リーダブルコード
                '9784048930598', // Clean Code
                '9784822251468', // コンテナ物語
            ],
            // 佐藤美咲（5冊）
            'sato@example.com' => [
                '9784101010014', // 吾輩は猫である
                '9784101010021', // 坊っちゃん
                '9784163902302', // 火花
                '9784478025819', // 嫌われる勇気
                '9784309226712', // サピエンス全史
            ],
            // 高橋健太（4冊）
            'takahashi@example.com' => [
                '9784309226712', // サピエンス全史
                '9784822289607', // FACTFULNESS
                '9784822251468', // コンテナ物語
                '9784422100524', // 人を動かす
            ],
        ];

        foreach ($favorites as $email => $isbnList) {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                continue;
            }

            $user->favorites()->syncWithoutDetaching(
                collect($isbnList)->map(fn (string $isbn): int => $bookIds[$isbn])->all()
            );
        }
    }
}
