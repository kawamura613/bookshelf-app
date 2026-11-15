<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->realText(80),
        ];
    }

    /**
     * 指定した評価値を持つ状態。
     */
    public function rating(int $rating): static
    {
        return $this->state(fn (array $attributes): array => [
            'rating' => $rating,
        ]);
    }
}
