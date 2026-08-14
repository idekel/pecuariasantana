<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Sale;
use App\Models\User;
use App\Models\YieldRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_projects_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.is_owner', true);
    }

    public function test_it_lists_projects_the_user_has_been_granted_access_to(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $project->grantAccessTo($collaborator, $owner);
        Sanctum::actingAs($collaborator);

        $response = $this->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.is_owner', false);
    }

    public function test_it_does_not_list_projects_the_user_has_no_access_to(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($stranger);

        $response = $this->getJson('/api/projects');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guest_cannot_list_projects(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertUnauthorized();
    }

    public function test_user_with_access_can_get_the_balance_between_sold_and_yielded_quantities(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();
        YieldRecord::factory()->for($project)->create(['quantity' => 10]);
        YieldRecord::factory()->for($project)->create(['quantity' => 5]);
        Sale::factory()->for($project)->create(['quantity' => 8]);
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/projects/{$project->id}/balance");

        $response->assertOk()
            ->assertJsonPath('project_id', $project->id)
            ->assertJsonPath('unit', 'eggs')
            ->assertJsonPath('total_yield', '15.00')
            ->assertJsonPath('total_sold', '8.00')
            ->assertJsonPath('difference', '7.00');
    }

    public function test_user_without_access_cannot_get_the_balance(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($stranger);

        $response = $this->getJson("/api/projects/{$project->id}/balance");

        $response->assertForbidden();
    }

    public function test_guest_cannot_get_the_balance(): void
    {
        $project = Project::factory()->create();

        $response = $this->getJson("/api/projects/{$project->id}/balance");

        $response->assertUnauthorized();
    }
}
