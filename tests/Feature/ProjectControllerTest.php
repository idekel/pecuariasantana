<?php

namespace Tests\Feature;

use App\Models\Project;
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

    public function test_it_includes_the_yield_count_for_the_current_month_only(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        YieldRecord::factory()->for($project)->create(['produced_on' => now()->startOfMonth()]);
        YieldRecord::factory()->for($project)->create(['produced_on' => now()->endOfMonth()]);
        YieldRecord::factory()->for($project)->create(['produced_on' => now()->subMonthNoOverflow()]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/projects');

        $response->assertOk()->assertJsonPath('data.0.current_month_yields_count', 2);
    }

    public function test_guest_cannot_list_projects(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertUnauthorized();
    }
}
