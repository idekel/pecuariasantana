<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_access_can_list_sales_history_for_a_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sale::factory()->for($project)->create();
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->id}/sales");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_user_with_access_can_filter_sales_history_by_date_range(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sale::factory()->for($project)->create(['sold_on' => '2026-08-01']);
        Sale::factory()->for($project)->create(['sold_on' => '2026-08-05']);
        Sale::factory()->for($project)->create(['sold_on' => '2026-07-01']);
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->id}/sales?start_date=2026-08-01&end_date=2026-08-06");

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_user_without_access_cannot_list_sales_history(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($stranger);

        $response = $this->getJson("/api/projects/{$project->id}/sales");

        $response->assertForbidden();
    }

    public function test_guest_cannot_list_sales_history(): void
    {
        $project = Project::factory()->create();

        $response = $this->getJson("/api/projects/{$project->id}/sales");

        $response->assertUnauthorized();
    }

    public function test_user_with_access_can_record_a_sale(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/sales", [
            'quantity' => 12,
            'amount' => 6.50,
            'sold_on' => '2026-08-06',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quantity', 12)
            ->assertJsonPath('data.amount', 6.5)
            ->assertJsonPath('data.unit', 'eggs');
        $this->assertDatabaseHas('sales', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'quantity' => 12,
            'amount' => 6.5,
        ]);
    }

    public function test_collaborator_can_record_a_sale(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();
        $project->grantAccessTo($collaborator, $owner);
        Sanctum::actingAs($collaborator);

        $response = $this->postJson("/api/projects/{$project->id}/sales", [
            'quantity' => 45.5,
            'amount' => 120,
            'sold_on' => '2026-08-06',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('sales', [
            'project_id' => $project->id,
            'user_id' => $collaborator->id,
        ]);
    }

    public function test_user_without_access_cannot_record_a_sale(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        Sanctum::actingAs($stranger);

        $response = $this->postJson("/api/projects/{$project->id}/sales", [
            'quantity' => 12,
            'amount' => 6.50,
            'sold_on' => '2026-08-06',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_recording_a_sale_requires_quantity_amount_and_sold_on(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/sales", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['quantity', 'amount', 'sold_on']);
    }

    public function test_hens_project_rejects_fractional_egg_counts(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/sales", [
            'quantity' => 12.5,
            'amount' => 6.50,
            'sold_on' => '2026-08-06',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
    }

    public function test_guest_cannot_record_a_sale(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/projects/{$project->id}/sales", [
            'quantity' => 12,
            'amount' => 6.50,
            'sold_on' => '2026-08-06',
        ]);

        $response->assertUnauthorized();
    }
}
