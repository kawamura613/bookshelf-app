<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Genre>
 */
class GenreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * genres.name は一意制約があるため連番を用いて衝突を避ける。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'ジャンル'.fake()->unique()->numberBetween(1, 1000000),
        ];
    }
}
