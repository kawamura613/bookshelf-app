<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    /**
     * 読書計画を6件投入する。
     *
     * 期日は Carbon::today() を起点に相対指定し、バッチ処理
     * （期限切れ判定・3日前／当日／3日後の通知）の動作確認に
     * 必要なパターンを網羅する。6件はすべて異なる書籍に紐付ける。
     */
    public function run(): void
    {
        $userIds = User::pluck('id', 'email');
        $bookIds = Book::orderBy('id')->pluck('id');

        if ($userIds->isEmpty() || $bookIds->count() < 6) {
            return;
        }

        $today = Carbon::today();

        $plans = [
            // 1) 期日まで余裕がある計画
            [
                'email' => 'yamada@example.com',
                'target_date' => $today->copy()->addDays(3),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ],
            // 2) 期日が当日の計画（当日通知の対象）
            [
                'email' => 'yamada@example.com',
                'target_date' => $today->copy(),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ],
            // 3) 期日を過ぎた計画（バッチで失効＋3日後通知の対象）
            [
                'email' => 'yamada@example.com',
                'target_date' => $today->copy()->subDays(3),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ],
            // 4) 期日が1週間後の計画
            [
                'email' => 'yamada@example.com',
                'target_date' => $today->copy()->addDays(7),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ],
            // 5) 期日前に読み終えた完了済みの計画
            [
                'email' => 'yamada@example.com',
                'target_date' => $today->copy()->subDays(10),
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => $today->copy()->subDays(5),
            ],
            // 6) 別ユーザーの進行中の計画
            [
                'email' => 'suzuki@example.com',
                'target_date' => $today->copy()->addDays(5),
                'status' => ReadingPlanStatus::InProgress,
                'completed_at' => null,
            ],
        ];

        foreach ($plans as $index => $plan) {
            ReadingPlan::create([
                'user_id' => $userIds[$plan['email']],
                'book_id' => $bookIds[$index],
                'target_date' => $plan['target_date'],
                'status' => $plan['status'],
                'completed_at' => $plan['completed_at'],
            ]);
        }
    }
}
