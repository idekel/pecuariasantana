<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_access_can_get_expense_history_for_a_date_range(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Expense::factory()->for($project)->create(['amount' => 10, 'incurred_on' => '2026-08-01']);
        Expense::factory()->for($project)->create(['amount' => 5, 'incurred_on' => '2026-08-05']);
        Expense::factory()->for($project)->create(['amount' => 100, 'incurred_on' => '2026-07-01']);
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->id}/expenses?start_date=2026-08-01&end_date=2026-08-06");

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_user_without_access_cannot_get_expense_history(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($stranger);

        $response = $this->getJson("/api/projects/{$project->id}/expenses?start_date=2026-08-01&end_date=2026-08-06");

        $response->assertForbidden();
    }

    public function test_expense_history_requires_start_and_end_date(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->id}/expenses");

        $response->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_guest_cannot_get_expense_history(): void
    {
        $project = Project::factory()->create();

        $response = $this->getJson("/api/projects/{$project->id}/expenses?start_date=2026-08-01&end_date=2026-08-06");

        $response->assertUnauthorized();
    }

    public function test_user_with_access_can_record_an_expense(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/expenses", [
            'amount' => 150.75,
            'type' => 'feed',
            'incurred_on' => '2026-08-06',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', 150.75)
            ->assertJsonPath('data.type', 'feed');
        $this->assertDatabaseHas('expenses', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'amount' => 150.75,
            'type' => 'feed',
        ]);
    }

    public function test_collaborator_can_record_an_expense(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $project->grantAccessTo($collaborator, $owner);
        Sanctum::actingAs($collaborator);

        $response = $this->postJson("/api/projects/{$project->id}/expenses", [
            'amount' => 50,
            'type' => 'equipment',
            'incurred_on' => '2026-08-06',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('expenses', [
            'project_id' => $project->id,
            'user_id' => $collaborator->id,
        ]);
    }

    public function test_user_without_access_cannot_record_an_expense(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($stranger);

        $response = $this->postJson("/api/projects/{$project->id}/expenses", [
            'amount' => 50,
            'type' => 'other',
            'incurred_on' => '2026-08-06',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_recording_an_expense_requires_amount_type_and_incurred_on(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/expenses", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['amount', 'type', 'incurred_on']);
    }

    public function test_recording_an_expense_rejects_an_invalid_type(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/expenses", [
            'amount' => 50,
            'type' => 'not_a_real_type',
            'incurred_on' => '2026-08-06',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_guest_cannot_record_an_expense(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/projects/{$project->id}/expenses", [
            'amount' => 50,
            'type' => 'other',
            'incurred_on' => '2026-08-06',
        ]);

        $response->assertUnauthorized();
    }
}
