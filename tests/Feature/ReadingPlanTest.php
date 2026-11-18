<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->book = Book::factory()->create();
    }

    // ---------- 一覧 ----------

    public function test_未ログインではアクセスできない(): void
    {
        $this->get(route('reading-plans.index'))->assertRedirect(route('login'));
    }

    public function test_一覧は自分の計画だけを表示する(): void
    {
        $mine = ReadingPlan::factory()->create(['user_id' => $this->user->id]);
        ReadingPlan::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->user)->get(route('reading-plans.index'))->assertOk();

        $this->assertSame([$mine->id], $response->viewData('readingPlans')->pluck('id')->all());
        $this->assertNull($response->viewData('currentStatus'));
    }

    public function test_一覧は状態で絞り込める(): void
    {
        $inProgress = ReadingPlan::factory()->create(['user_id' => $this->user->id]);
        $completed = ReadingPlan::factory()->completed()->create(['user_id' => $this->user->id]);
        $expired = ReadingPlan::factory()->expired()->create(['user_id' => $this->user->id]);

        $cases = [
            'in_progress' => $inProgress->id,
            'completed' => $completed->id,
            'expired' => $expired->id,
        ];

        foreach ($cases as $status => $expectedId) {
            $response = $this->actingAs($this->user)
                ->get(route('reading-plans.index', ['status' => $status]))
                ->assertOk();

            $this->assertSame([$expectedId], $response->viewData('readingPlans')->pluck('id')->all(), $status);
            $this->assertSame($status, $response->viewData('currentStatus'), $status);
        }
    }

    public function test_不正な状態の指定はバリデーションエラーになる(): void
    {
        $this->actingAs($this->user)
            ->get(route('reading-plans.index', ['status' => 'bogus']))
            ->assertSessionHasErrors(['status' => '状態の指定が正しくありません。']);
    }

    public function test_一覧は期日の昇順で並ぶ(): void
    {
        $later = ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'target_date' => Carbon::today()->addDays(10),
        ]);
        $sooner = ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'target_date' => Carbon::today()->addDay(),
        ]);

        $response = $this->actingAs($this->user)->get(route('reading-plans.index'))->assertOk();

        $this->assertSame([$sooner->id, $later->id], $response->viewData('readingPlans')->pluck('id')->all());
    }

    // ---------- 作成 ----------

    public function test_作成画面に書籍一覧が渡る(): void
    {
        $response = $this->actingAs($this->user)->get(route('reading-plans.create'))->assertOk();

        $this->assertSame([$this->book->id], $response->viewData('books')->pluck('id')->all());
    }

    public function test_読書計画を作成できる(): void
    {
        $targetDate = Carbon::today()->addDays(5);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => $this->book->id,
                'target_date' => $targetDate->format('Y-m-d'),
            ])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を作成しました。');

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'status' => ReadingPlanStatus::InProgress->value,
            'completed_at' => null,
        ]);
    }

    public function test_期日が本日でも作成できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => $this->book->id,
                'target_date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_作成のバリデーションエラーが日本語で返る(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => '',
                'target_date' => '',
            ])
            ->assertSessionHasErrors([
                'book_id' => '書籍を選択してください。',
                'target_date' => '期日を入力してください。',
            ]);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_存在しない書籍は作成できない(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => 9999,
                'target_date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors(['book_id' => '選択された書籍は存在しません。']);
    }

    public function test_過去の期日は作成できない(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => $this->book->id,
                'target_date' => Carbon::yesterday()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors(['target_date' => '期日は本日以降の日付を入力してください。']);
    }

    public function test_同じ書籍で進行中の計画が既にあると作成できない(): void
    {
        ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => $this->book->id,
                'target_date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors(['book_id' => 'この書籍は既に進行中の読書計画が存在します。']);

        $this->assertDatabaseCount('reading_plans', 1);
    }

    public function test_完了済みや期限切れの計画があれば同じ書籍で再度作成できる(): void
    {
        ReadingPlan::factory()->completed()->create([
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => $this->book->id,
                'target_date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reading_plans', 2);
    }

    public function test_他ユーザーの進行中の計画は重複判定に影響しない(): void
    {
        ReadingPlan::factory()->create([
            'user_id' => User::factory()->create()->id,
            'book_id' => $this->book->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => $this->book->id,
                'target_date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();
    }

    // ---------- 編集・更新 ----------

    public function test_所有者は編集画面を開ける(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('reading-plans.edit', $plan))
            ->assertOk();

        $this->assertSame($plan->id, $response->viewData('readingPlan')->id);
    }

    public function test_他ユーザーは編集画面を開けず_403_になる(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();
    }

    public function test_完了済みの計画は編集画面を開けず_403_になる(): void
    {
        $plan = ReadingPlan::factory()->completed()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();
    }

    public function test_期日を更新できる(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => $this->user->id]);
        $newDate = Carbon::today()->addDays(14);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => $newDate->format('Y-m-d')])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を更新しました。');

        $this->assertSame($newDate->format('Y-m-d'), $plan->fresh()->target_date->format('Y-m-d'));
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
    }

    public function test_期限切れの計画を更新すると進行中に戻る(): void
    {
        $plan = ReadingPlan::factory()->expired()->create(['user_id' => $this->user->id]);
        $this->assertSame(ReadingPlanStatus::Expired, $plan->status);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => Carbon::today()->addDays(3)->format('Y-m-d'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
    }

    public function test_更新で過去の期日は指定できない(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => Carbon::yesterday()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors(['target_date' => '期日は本日以降の日付を入力してください。']);
    }

    public function test_他ユーザーは更新できず_403_になる(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertForbidden();
    }

    // ---------- 完了 ----------

    public function test_読書計画を完了できる(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を完了しました。');

        $fresh = $plan->fresh();
        $this->assertSame(ReadingPlanStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_期限切れの計画も完了できる(): void
    {
        $plan = ReadingPlan::factory()->expired()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.complete', $plan))
            ->assertSessionHas('success', '読書計画を完了しました。');

        $this->assertSame(ReadingPlanStatus::Completed, $plan->fresh()->status);
    }

    public function test_完了済みの計画を再度完了すると_403_になる(): void
    {
        $plan = ReadingPlan::factory()->completed()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.complete', $plan))
            ->assertForbidden();
    }

    public function test_他ユーザーは完了できず_403_になる(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.complete', $plan))
            ->assertForbidden();
    }

    // ---------- 削除 ----------

    public function test_読書計画を削除できる(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を削除しました。');

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_完了済みの計画も削除できる(): void
    {
        $plan = ReadingPlan::factory()->completed()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertSessionHas('success', '読書計画を削除しました。');

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_削除すると紐づく通知も削除される(): void
    {
        $target = ReadingPlan::factory()->create(['user_id' => $this->user->id]);
        $other = ReadingPlan::factory()->create(['user_id' => $this->user->id]);

        Notification::send($this->user, new ReadingPlanReminderNotification($target, ReminderTiming::OnDueDate));
        Notification::send($this->user, new ReadingPlanReminderNotification($other, ReminderTiming::OnDueDate));

        $this->assertSame(2, DB::table('notifications')->count());

        $this->actingAs($this->user)
            ->delete(route('reading-plans.destroy', $target))
            ->assertSessionHas('success', '読書計画を削除しました。');

        // 削除した計画の通知だけが消え、他の計画の通知は残る。
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('notifications')->where('data->reading_plan_id', $target->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('data->reading_plan_id', $other->id)->count());
    }

    public function test_他ユーザーは削除できず_403_になる(): void
    {
        $plan = ReadingPlan::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }
}
