<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\IndexReadingPlanRequest;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminderNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧（PG13）— 認証必須。
     *
     * ログインユーザーの計画のみを対象とし、status クエリパラメータで
     * 絞り込む。Blade は $readingPlans と $currentStatus を参照する。
     */
    public function index(IndexReadingPlanRequest $request): View
    {
        $status = $request->validated()['status'] ?? null;

        $readingPlans = Auth::user()
            ->readingPlans()
            ->with('book')
            ->when(
                $status !== null,
                fn (Builder $query): Builder => $query->where('status', $status)
            )
            ->orderBy('target_date')
            ->get();

        return view('reading-plans.index', [
            'readingPlans' => $readingPlans,
            'currentStatus' => $status,
        ]);
    }

    /**
     * 読書計画の作成フォーム。
     *
     * Blade は $books（選択プルダウン用）を参照する。
     */
    public function create(): View
    {
        return view('reading-plans.create', [
            'books' => Book::orderBy('title')->get(),
        ]);
    }

    /**
     * 読書計画を登録する。状態は常に進行中で開始する。
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Auth::user()->readingPlans()->create([
            'book_id' => $validated['book_id'],
            'target_date' => $validated['target_date'],
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を作成しました。');
    }

    /**
     * 読書計画の編集フォーム。所有者本人かつ未完了の計画のみ。
     *
     * Blade は $readingPlan を参照する。
     */
    public function edit(ReadingPlan $plan): View
    {
        $this->authorize('update', $plan);

        $plan->load('book');

        return view('reading-plans.edit', ['readingPlan' => $plan]);
    }

    /**
     * 読書計画の期日を更新する。所有者本人かつ未完了の計画のみ。
     *
     * 期限切れの計画は期日が本日以降へ変更されるため、状態を進行中に戻す。
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validated();

        $plan->update([
            'target_date' => $validated['target_date'],
            'status' => $plan->status === ReadingPlanStatus::Expired
                ? ReadingPlanStatus::InProgress
                : $plan->status,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 読書計画を読了（完了）にする。所有者本人かつ未完了の計画のみ。
     */
    public function complete(ReadingPlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $plan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を完了しました。');
    }

    /**
     * 読書計画を削除する。所有者本人のみ。
     *
     * 計画が消えた後に通知だけが残らないよう、紐づくリマインダー通知を
     * 同一トランザクション内でまとめて削除する。
     */
    public function destroy(ReadingPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        DB::transaction(function () use ($plan): void {
            DB::table('notifications')
                ->where('type', ReadingPlanReminderNotification::class)
                ->where('data->reading_plan_id', $plan->id)
                ->delete();

            $plan->delete();
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }
}
