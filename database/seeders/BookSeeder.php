<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * 書籍データを11件投入し、ジャンルを紐付ける。
     *
     * 登録者は全ユーザーからランダムに選び、
     * 書籍の所有者が分散したデータを再現する。
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        $genreIds = Genre::pluck('id', 'name');

        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師である苦沙弥先生の家に住む猫の視点から、明治の知識人たちの滑稽な日常を描いた長編小説。風刺とユーモアに満ちた文体が魅力です。',
                'genres' => ['小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人間関係の原則を体系化した不朽の名著。相手の立場に立って考えることの重要性を、豊富な実例とともに説いています。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => '読みやすいコードを書くための具体的なテクニックを解説した一冊。命名、コメント、制御フローの改善など、実務で即座に使える知見が詰まっています。',
                'genres' => ['技術書'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '人格を磨き、主体的に生きるための7つの原則を提示する自己啓発書の古典。私的成功から公的成功へと至る道筋を示します。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '四国の中学校に赴任した江戸っ子教師が、型破りな言動で周囲と衝突しながら奮闘する姿を描いた青春小説。痛快な語り口が心地よい作品です。',
                'genres' => ['小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => '認知革命、農業革命、科学革命という三つの転換点から人類二十万年の歩みを読み解く壮大な通史。歴史観が大きく揺さぶられます。',
                'genres' => ['歴史', '科学'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => '保守しやすいコードを書くための原則と実践をまとめた技術書。関数の分割やエラー処理など、設計判断の指針が豊富に示されています。',
                'genres' => ['技術書'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学の思想を哲人と青年の対話形式で解説した一冊。課題の分離という考え方が、対人関係の悩みを軽くしてくれます。',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => '売れない漫才師と師匠の十年間を描いた芥川賞受賞作。笑いに人生を捧げた者の覚悟と切なさが静かに迫ってきます。',
                'genres' => ['小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => 'データに基づいて世界を正しく見るための十の思考法を紹介する一冊。思い込みを自覚させられる問いかけが秀逸です。',
                'genres' => ['ビジネス', '科学'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_date' => '2007-01-18',
                'description' => '一つの鉄の箱が物流を一変させ、世界経済のかたちを変えていく過程を追ったノンフィクション。規格化の持つ力を実感できます。',
                'genres' => ['ビジネス', '歴史'],
            ],
        ];

        foreach ($books as $index => $book) {
            $created = Book::firstOrCreate(
                ['isbn' => $book['isbn']],
                [
                    'user_id' => $users->random()->id,
                    'title' => $book['title'],
                    'author' => $book['author'],
                    'published_date' => $book['published_date'],
                    'description' => $book['description'],
                    'image_url' => 'https://placehold.co/300x400?text='.($index + 1),
                ]
            );

            $created->genres()->sync(
                collect($book['genres'])->map(fn (string $name): int => $genreIds[$name])->all()
            );
        }
    }
}
