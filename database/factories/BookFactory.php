<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * books.isbn は13桁かつ一意制約があるため、978 始まりの連番で生成する。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'isbn' => (string) fake()->unique()->numberBetween(9780000000000, 9789999999999),
            'published_date' => fake()->date('Y-m-d'),
            'description' => fake()->realText(100),
            'image_url' => 'https://placehold.co/300x400?text='.fake()->numberBetween(1, 999),
        ];
    }

    /**
     * 説明と画像URLが未設定の状態。
     */
    public function withoutOptionals(): static
    {
        return $this->state(fn (array $attributes): array => [
            'description' => null,
            'image_url' => null,
        ]);
    }
}
