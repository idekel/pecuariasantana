<?php

namespace Database\Factories;

use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(ProjectType::cases()),
        ];
    }

    /**
     * Indicate that the project is a hens (egg-laying) project.
     */
    public function hens(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProjectType::Hens,
        ]);
    }

    /**
     * Indicate that the project is a meat chickens project.
     */
    public function meatChickens(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProjectType::MeatChickens,
        ]);
    }
}
