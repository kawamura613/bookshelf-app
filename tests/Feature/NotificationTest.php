<?php

namespace Tests\Feature;

use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ReadingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plan = ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => Book::factory()->create(['title' => '対象の書籍'])->id,
        ]);
    }

    private function notify(ReminderTiming $timing, ?User $user = null): DatabaseNotification
    {
        $target = $user ?? $this->user;

        Notification::send($target, new ReadingPlanReminderNotification($this->plan, $timing));

        return $target->notifications()->latest()->firstOrFail();
    }

    public function test_未ログインではアクセスできない(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_通知が0件でも表示できる(): void
    {
        $response = $this->actingAs($this->user)->get(route('notifications.index'))->assertOk();

        $this->assertCount(0, $response->viewData('notifications'));
        $response->assertSee('通知はありません。');
    }

    public function test_データベース通知として保存され_blade_が参照するキーを持つ(): void
    {
        $notification = $this->notify(ReminderTiming::OnDueDate);

        $this->assertSame('on_due_date', $notification->data['timing']);
        $this->assertSame('読書計画の期日は本日です', $notification->data['title']);
        $this->assertStringContainsString('対象の書籍', $notification->data['body']);
        $this->assertSame($this->plan->id, $notification->data['reading_plan_id']);
        $this->assertNull($notification->read_at);
        $this->assertSame(ReadingPlanReminderNotification::class, $notification->type);
    }

    public function test_3つの通知タイミングすべてを保存できる(): void
    {
        collect(ReminderTiming::cases())->each(fn (ReminderTiming $timing) => $this->notify($timing));

        $timings = $this->user->notifications()->get()->pluck('data.timing')->sort()->values();

        $this->assertSame(
            ['on_due_date', 'three_days_after', 'three_days_before'],
            $timings->all()
        );
    }

    public function test_一覧は自分の通知だけを表示する(): void
    {
        $mine = $this->notify(ReminderTiming::OnDueDate);
        $this->notify(ReminderTiming::OnDueDate, User::factory()->create());

        $response = $this->actingAs($this->user)->get(route('notifications.index'))->assertOk();

        $this->assertSame([$mine->id], $response->viewData('notifications')->pluck('id')->all());
    }

    public function test_一覧は新しい順に並ぶ(): void
    {
        $older = $this->notify(ReminderTiming::ThreeDaysBefore);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = $this->notify(ReminderTiming::OnDueDate);

        $response = $this->actingAs($this->user)->get(route('notifications.index'))->assertOk();

        $this->assertSame(
            [$newer->id, $older->id],
            $response->viewData('notifications')->pluck('id')->all()
        );
    }

    public function test_通知を既読にできる(): void
    {
        $notification = $this->notify(ReminderTiming::OnDueDate);

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', '通知を既読にしました。');

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(0, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_他ユーザーの通知は既読にできず_404_になる(): void
    {
        $other = User::factory()->create();
        $notification = $this->notify(ReminderTiming::OnDueDate, $other);

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_存在しない通知は_404_になる(): void
    {
        $this->actingAs($this->user)
            ->post(route('notifications.read', '00000000-0000-0000-0000-000000000000'))
            ->assertNotFound();
    }

    public function test_ナビゲーションに未読件数が表示される(): void
    {
        $this->notify(ReminderTiming::OnDueDate);
        $this->notify(ReminderTiming::ThreeDaysBefore);

        $this->assertSame(2, $this->user->fresh()->unreadNotifications()->count());

        // ナビゲーションは未読件数をバッジとして描画する。
        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('未読');
    }
}
