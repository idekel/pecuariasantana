<?php

namespace Database\Factories;

use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 1, 1000),
            'type' => fake()->randomElement(ExpenseType::cases()),
            'incurred_on' => fake()->date(),
        ];
    }
}
