<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * 各レビューに0〜3人のユーザーがいいねを付ける。
     *
     * 投稿者自身によるいいねは除外する。いいね数は固定パターンで
     * 決定するため、実行結果は常に同一になる。
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $reviews = Review::orderBy('id')->get();

        if ($users->isEmpty() || $reviews->isEmpty()) {
            return;
        }

        // レビュー1件あたりのいいね数（0〜3）を定める固定パターン。
        $likeCounts = [
            2, 1, 3, 0, 2, 3, 1, 2, 0, 1, 3, 2, 1, 0, 2, 3,
            1, 2, 3, 0, 1, 2, 0, 3, 1, 2, 1, 0, 3, 2, 1, 2,
        ];

        $userCount = $users->count();

        foreach ($reviews as $index => $review) {
            $likeCount = $likeCounts[$index % count($likeCounts)];

            if ($likeCount === 0) {
                continue;
            }

            $likerIds = [];

            for ($offset = 0; $offset < $userCount && count($likerIds) < $likeCount; $offset++) {
                $candidate = $users[($index + $offset) % $userCount];

                // 自分のレビューにはいいねを付けない。
                if ($candidate->id === $review->user_id) {
                    continue;
                }

                $likerIds[] = $candidate->id;
            }

            $review->likedBy()->syncWithoutDetaching($likerIds);
        }
    }
}
