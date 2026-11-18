<?php

namespace App\Enums;

enum ReminderTiming: string
{
    case ThreeDaysBefore = 'three_days_before';
    case OnDueDate = 'on_due_date';
    case ThreeDaysAfter = 'three_days_after';

    /**
     * 通知の件名。
     *
     * 通知一覧（notifications/index.blade.php）の data['title'] に入る。
     */
    public function title(): string
    {
        return match ($this) {
            self::ThreeDaysBefore => '読書計画の期日が3日後です',
            self::OnDueDate => '読書計画の期日は本日です',
            self::ThreeDaysAfter => '読書計画の期日から3日が経過しました',
        };
    }

    /**
     * 通知の本文。
     *
     * 通知一覧（notifications/index.blade.php）の data['body'] に入る。
     */
    public function body(string $bookTitle, string $targetDate): string
    {
        return match ($this) {
            self::ThreeDaysBefore => "「{$bookTitle}」の期日（{$targetDate}）が3日後に迫っています。",
            self::OnDueDate => "「{$bookTitle}」の期日は本日（{$targetDate}）です。",
            self::ThreeDaysAfter => "「{$bookTitle}」は期日（{$targetDate}）を過ぎたため期限切れになりました。",
        };
    }
}
