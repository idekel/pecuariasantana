<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\User;
use App\Models\YieldRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YieldRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_a_project(): void
    {
        $project = Project::factory()->create();
        $yield = YieldRecord::factory()->for($project)->create();

        $this->assertTrue($yield->project->is($project));
    }

    public function test_it_belongs_to_the_user_who_recorded_it(): void
    {
        $user = User::factory()->create();
        $yield = YieldRecord::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($yield->recordedBy->is($user));
    }

    public function test_its_unit_is_derived_from_its_projects_type(): void
    {
        $hensYield = YieldRecord::factory()->for(Project::factory()->hens())->create();
        $meatYield = YieldRecord::factory()->for(Project::factory()->meatChickens())->create();

        $this->assertSame('eggs', $hensYield->unit());
        $this->assertSame('pounds', $meatYield->unit());
    }

    public function test_quantity_and_date_are_cast(): void
    {
        $yield = YieldRecord::factory()->create([
            'quantity' => '18.00',
            'produced_on' => '2026-08-06',
        ]);

        $this->assertIsFloat($yield->quantity);
        $this->assertSame('2026-08-06', $yield->produced_on->toDateString());
    }

    public function test_it_is_stored_in_the_yields_table(): void
    {
        $yield = YieldRecord::factory()->create();

        $this->assertDatabaseHas('yields', ['id' => $yield->id]);
    }
}
