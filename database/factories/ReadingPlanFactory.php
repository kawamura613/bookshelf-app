<?php

namespace Database\Factories;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * 既定は本日より後を期日とする進行中の計画。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'target_date' => Carbon::today()->addDays(7),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ];
    }

    /**
     * 期限切れの状態。期日は既定で3日前にする。
     */
    public function expired(?Carbon $targetDate = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'target_date' => $targetDate ?? Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::Expired,
            'completed_at' => null,
        ]);
    }

    /**
     * 完了済みの状態。
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => Carbon::today()->subDay(),
        ]);
    }
}
