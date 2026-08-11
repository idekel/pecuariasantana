<?php

namespace Tests\Unit\Models;

use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_a_project(): void
    {
        $project = Project::factory()->create();
        $expense = Expense::factory()->for($project)->create();

        $this->assertTrue($expense->project->is($project));
    }

    public function test_it_belongs_to_the_user_who_recorded_it(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($expense->recordedBy->is($user));
    }

    public function test_amount_type_and_date_are_cast(): void
    {
        $expense = Expense::factory()->create([
            'amount' => '45.50',
            'type' => 'feed',
            'incurred_on' => '2026-08-06',
        ]);

        $this->assertIsFloat($expense->amount);
        $this->assertSame(ExpenseType::Feed, $expense->type);
        $this->assertSame('2026-08-06', $expense->incurred_on->toDateString());
    }

    public function test_it_is_stored_in_the_expenses_table(): void
    {
        $expense = Expense::factory()->create();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }
}
