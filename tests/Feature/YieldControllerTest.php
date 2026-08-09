<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\YieldRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class YieldControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_access_can_list_yields_for_a_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        YieldRecord::factory()->for($project)->create();
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->id}/yields");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_user_without_access_cannot_list_yields(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($stranger);

        $response = $this->getJson("/api/projects/{$project->id}/yields");

        $response->assertForbidden();
    }

    public function test_user_with_access_can_get_yield_summary_for_a_date_range(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        YieldRecord::factory()->for($project)->create(['quantity' => 10, 'produced_on' => '2026-08-01']);
        YieldRecord::factory()->for($project)->create(['quantity' => 5, 'produced_on' => '2026-08-05']);
        YieldRecord::factory()->for($project)->create(['quantity' => 100, 'produced_on' => '2026-07-01']);
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->id}/yields/summary?start_date=2026-08-01&end_date=2026-08-06");

        $response->assertOk()
            ->assertJsonPath('total', '15.00')
            ->assertJsonPath('unit', 'eggs');
    }

    public function test_user_without_access_cannot_get_yield_summary(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($stranger);

        $response = $this->getJson("/api/projects/{$project->id}/yields/summary?start_date=2026-08-01&end_date=2026-08-06");

        $response->assertForbidden();
    }

    public function test_yield_summary_requires_start_and_end_date(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->id}/yields/summary");

        $response->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_user_with_access_can_record_a_yield(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/yields", [
            'quantity' => 12,
            'produced_on' => '2026-08-06',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quantity', 12)
            ->assertJsonPath('data.unit', 'eggs');
        $this->assertDatabaseHas('yields', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'quantity' => 12,
        ]);
    }

    public function test_collaborator_can_record_a_yield(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();
        $project->grantAccessTo($collaborator, $owner);
        Sanctum::actingAs($collaborator);

        $response = $this->postJson("/api/projects/{$project->id}/yields", [
            'quantity' => 45.5,
            'produced_on' => '2026-08-06',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('yields', [
            'project_id' => $project->id,
            'user_id' => $collaborator->id,
        ]);
    }

    public function test_user_without_access_cannot_record_a_yield(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        Sanctum::actingAs($stranger);

        $response = $this->postJson("/api/projects/{$project->id}/yields", [
            'quantity' => 12,
            'produced_on' => '2026-08-06',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('yields', 0);
    }

    public function test_recording_a_yield_requires_quantity_and_produced_on(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/yields", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['quantity', 'produced_on']);
    }

    public function test_hens_project_rejects_fractional_egg_counts(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/projects/{$project->id}/yields", [
            'quantity' => 12.5,
            'produced_on' => '2026-08-06',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
    }

    public function test_user_with_access_can_view_a_yield(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $yield = YieldRecord::factory()->for($project)->create();
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/yields/{$yield->id}");

        $response->assertOk()->assertJsonPath('data.id', $yield->id);
    }

    public function test_user_without_access_cannot_view_a_yield(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $yield = YieldRecord::factory()->for($project)->create();
        Sanctum::actingAs($stranger);

        $response = $this->getJson("/api/yields/{$yield->id}");

        $response->assertForbidden();
    }

    public function test_user_with_access_can_update_a_yield(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();
        $yield = YieldRecord::factory()->for($project)->create(['quantity' => 10]);
        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/yields/{$yield->id}", [
            'quantity' => 22.5,
            'produced_on' => '2026-08-05',
        ]);

        $response->assertOk()->assertJsonPath('data.quantity', 22.5);
        $this->assertDatabaseHas('yields', [
            'id' => $yield->id,
            'quantity' => 22.5,
            'produced_on' => '2026-08-05',
        ]);
    }

    public function test_user_without_access_cannot_update_a_yield(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $yield = YieldRecord::factory()->for($project)->create();
        Sanctum::actingAs($stranger);

        $response = $this->putJson("/api/yields/{$yield->id}", [
            'quantity' => 5,
            'produced_on' => '2026-08-05',
        ]);

        $response->assertForbidden();
    }

    public function test_user_with_access_can_delete_a_yield(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $yield = YieldRecord::factory()->for($project)->create();
        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/yields/{$yield->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('yields', ['id' => $yield->id]);
    }

    public function test_user_without_access_cannot_delete_a_yield(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $yield = YieldRecord::factory()->for($project)->create();
        Sanctum::actingAs($stranger);

        $response = $this->deleteJson("/api/yields/{$yield->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('yields', ['id' => $yield->id]);
    }

    public function test_guest_cannot_access_yield_endpoints(): void
    {
        $project = Project::factory()->create();
        $yield = YieldRecord::factory()->for($project)->create();

        $this->getJson("/api/projects/{$project->id}/yields")->assertUnauthorized();
        $this->getJson("/api/yields/{$yield->id}")->assertUnauthorized();
    }
}
