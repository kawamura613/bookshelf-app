<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class RunDailyReadingPlanTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reading-plans:run-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '期日を過ぎた読書計画を期限切れにし、リマインダー通知を送信する日次バッチ';

    /**
     * 日次バッチを実行する。
     *
     * 失効処理を先に行うことで、期日から3日経過した計画が
     * 同じ実行内で期限切れ通知の対象になる。
     */
    public function handle(): int
    {
        $today = Carbon::today();

        $expiredCount = $this->expireOverduePlans($today);
        $this->info("期限切れに更新した読書計画: {$expiredCount}件");

        $this->remindersFor($today)->each(function (Collection $plans, string $timing): void {
            $sentCount = $this->sendReminders($plans, ReminderTiming::from($timing));
            $this->info("{$timing} の通知を送信: {$sentCount}件");
        });

        return self::SUCCESS;
    }

    /**
     * 期日を過ぎた進行中の計画を期限切れに更新し、件数を返す。
     */
    private function expireOverduePlans(Carbon $today): int
    {
        return ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress)
            ->whereDate('target_date', '<', $today)
            ->update(['status' => ReadingPlanStatus::Expired]);
    }

    /**
     * 通知タイミングごとの対象計画を取得する。
     *
     * @return Collection<string, Collection<int, ReadingPlan>>
     */
    private function remindersFor(Carbon $today): Collection
    {
        return collect([
            ReminderTiming::ThreeDaysBefore->value => [
                ReadingPlanStatus::InProgress,
                $today->copy()->addDays(3),
            ],
            ReminderTiming::OnDueDate->value => [
                ReadingPlanStatus::InProgress,
                $today->copy(),
            ],
            ReminderTiming::ThreeDaysAfter->value => [
                ReadingPlanStatus::Expired,
                $today->copy()->subDays(3),
            ],
        ])->map(fn (array $condition): Collection => $this->plansFor(...$condition));
    }

    /**
     * 指定の状態・期日に一致する計画を取得する。
     *
     * @return Collection<int, ReadingPlan>
     */
    private function plansFor(ReadingPlanStatus $status, Carbon $targetDate): Collection
    {
        return ReadingPlan::query()
            ->with(['user', 'book'])
            ->where('status', $status)
            ->whereDate('target_date', $targetDate)
            ->get();
    }

    /**
     * 対象計画の所有者へリマインダー通知を送信し、送信件数を返す。
     *
     * @param  Collection<int, ReadingPlan>  $plans
     */
    private function sendReminders(Collection $plans, ReminderTiming $timing): int
    {
        $plans->each(fn (ReadingPlan $plan): null => Notification::send(
            $plan->user,
            new ReadingPlanReminderNotification($plan, $timing)
        ));

        return $plans->count();
    }
}
