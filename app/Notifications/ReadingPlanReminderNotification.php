<?php

namespace App\Notifications;

use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingPlanReminderNotification extends Notification
{
    use Queueable;

    /**
     * 通知対象の読書計画と、どのタイミングの通知かを受け取る。
     */
    public function __construct(
        private readonly ReadingPlan $readingPlan,
        private readonly ReminderTiming $timing,
    ) {}

    /**
     * 配信チャンネル。確定仕様により database のみを使用する。
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * notifications テーブルの data に保存する内容。
     *
     * 通知一覧（notifications/index.blade.php）が参照する
     * timing / title / body のキーを必ず含める。reading_plan_id は
     * 読書計画の削除時に関連通知を特定するために保持する。
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $bookTitle = $this->readingPlan->book->title;
        $targetDate = $this->readingPlan->target_date->format('Y-m-d');

        return [
            'timing' => $this->timing->value,
            'title' => $this->timing->title(),
            'body' => $this->timing->body($bookTitle, $targetDate),
            'reading_plan_id' => $this->readingPlan->id,
            'book_id' => $this->readingPlan->book_id,
            'book_title' => $bookTitle,
            'target_date' => $targetDate,
        ];
    }
}
