<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminderNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RunDailyReadingPlanTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function plan(int $daysFromToday, ReadingPlanStatus $status = ReadingPlanStatus::InProgress): ReadingPlan
    {
        return ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'target_date' => Carbon::today()->addDays($daysFromToday),
            'status' => $status,
        ]);
    }

    private function runDaily(): void
    {
        $this->artisan('reading-plans:run-daily')->assertSuccessful();
    }

    /**
     * 指定の読書計画に送られた通知のタイミングを取り出す。
     *
     * @return array<int, string>
     */
    private function timingsFor(ReadingPlan $plan): array
    {
        return DB::table('notifications')
            ->where('type', ReadingPlanReminderNotification::class)
            ->where('data->reading_plan_id', $plan->id)
            ->get()
            ->map(fn (object $row): string => json_decode($row->data, true)['timing'])
            ->all();
    }

    // ---------- 失効処理 ----------

    public function test_期日を過ぎた進行中の計画は期限切れになる(): void
    {
        $overdue = $this->plan(-1);
        $today = $this->plan(0);
        $future = $this->plan(1);

        $this->runDaily();

        $this->assertSame(ReadingPlanStatus::Expired, $overdue->fresh()->status);
        // 期日が本日の計画はまだ失効させない。
        $this->assertSame(ReadingPlanStatus::InProgress, $today->fresh()->status);
        $this->assertSame(ReadingPlanStatus::InProgress, $future->fresh()->status);
    }

    public function test_完了済みの計画は失効しない(): void
    {
        $completed = ReadingPlan::factory()->completed()->create([
            'user_id' => $this->user->id,
            'target_date' => Carbon::today()->subDays(10),
        ]);

        $this->runDaily();

        $this->assertSame(ReadingPlanStatus::Completed, $completed->fresh()->status);
    }

    // ---------- リマインダー通知 ----------

    public function test_期日3日前の計画に_three_days_before_が送られる(): void
    {
        $plan = $this->plan(3);

        $this->runDaily();

        $this->assertSame(['three_days_before'], $this->timingsFor($plan));
    }

    public function test_期日当日の計画に_on_due_date_が送られる(): void
    {
        $plan = $this->plan(0);

        $this->runDaily();

        $this->assertSame(['on_due_date'], $this->timingsFor($plan));
    }

    public function test_期日から3日経過した計画に_three_days_after_が送られる(): void
    {
        $plan = $this->plan(-3, ReadingPlanStatus::Expired);

        $this->runDaily();

        $this->assertSame(['three_days_after'], $this->timingsFor($plan));
    }

    public function test_同一実行で失効した計画も_three_days_after_の対象になる(): void
    {
        // 失効処理が先に走るため、進行中のままでも同じ実行で通知される。
        $plan = $this->plan(-3);

        $this->runDaily();

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
        $this->assertSame(['three_days_after'], $this->timingsFor($plan));
    }

    public function test_通知対象外の期日には通知されない(): void
    {
        $plans = collect([1, 2, 4, 5, -1, -2, -4])->map(fn (int $days): ReadingPlan => $this->plan($days));

        $this->runDaily();

        $plans->each(fn (ReadingPlan $plan) => $this->assertSame([], $this->timingsFor($plan)));
    }

    public function test_完了済みの計画には通知されない(): void
    {
        $plan = ReadingPlan::factory()->completed()->create([
            'user_id' => $this->user->id,
            'target_date' => Carbon::today(),
        ]);

        $this->runDaily();

        $this->assertSame([], $this->timingsFor($plan));
    }

    public function test_期限切れの計画は期日当日通知の対象にならない(): void
    {
        // status が期限切れのため on_due_date の条件（進行中）に合致しない。
        $plan = ReadingPlan::factory()->expired(Carbon::today())->create(['user_id' => $this->user->id]);

        $this->runDaily();

        $this->assertSame([], $this->timingsFor($plan));
    }

    public function test_通知は計画の所有者に届く(): void
    {
        $otherUser = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'target_date' => Carbon::today(),
        ]);

        $this->runDaily();

        $this->assertSame(1, $otherUser->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $this->user->fresh()->unreadNotifications()->count());
        $this->assertSame(['on_due_date'], $this->timingsFor($plan));
    }

    public function test_複数の計画をまとめて処理できる(): void
    {
        $before = $this->plan(3);
        $due = $this->plan(0);
        $after = $this->plan(-3);
        $overdue = $this->plan(-10);

        $this->runDaily();

        $this->assertSame(['three_days_before'], $this->timingsFor($before));
        $this->assertSame(['on_due_date'], $this->timingsFor($due));
        $this->assertSame(['three_days_after'], $this->timingsFor($after));
        $this->assertSame([], $this->timingsFor($overdue));
        $this->assertSame(ReadingPlanStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(3, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_スケジュールに毎日20時で登録されている(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains($event->command ?? '', 'reading-plans:run-daily'));

        $this->assertCount(1, $events);
        $this->assertSame('0 20 * * *', $events->first()->expression);
    }
}
