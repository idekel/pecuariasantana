<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_a_project(): void
    {
        $project = Project::factory()->create();
        $sale = Sale::factory()->for($project)->create();

        $this->assertTrue($sale->project->is($project));
    }

    public function test_it_belongs_to_the_user_who_recorded_it(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($sale->recordedBy->is($user));
    }

    public function test_its_unit_is_derived_from_its_projects_type(): void
    {
        $hensSale = Sale::factory()->for(Project::factory()->hens())->create();
        $meatSale = Sale::factory()->for(Project::factory()->meatChickens())->create();

        $this->assertSame('eggs', $hensSale->unit());
        $this->assertSame('pounds', $meatSale->unit());
    }

    public function test_quantity_amount_and_date_are_cast(): void
    {
        $sale = Sale::factory()->create([
            'quantity' => '18.00',
            'amount' => '45.50',
            'sold_on' => '2026-08-06',
        ]);

        $this->assertIsFloat($sale->quantity);
        $this->assertIsFloat($sale->amount);
        $this->assertSame('2026-08-06', $sale->sold_on->toDateString());
    }

    public function test_it_is_stored_in_the_sales_table(): void
    {
        $sale = Sale::factory()->create();

        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    }
}
