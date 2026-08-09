<?php

namespace Tests\Unit\Models;

use App\Enums\ProjectType;
use App\Exceptions\CannotGrantAccessToOwnerException;
use App\Exceptions\InvalidSaleQuantityException;
use App\Exceptions\InvalidYieldQuantityException;
use App\Exceptions\UnauthorizedProjectActionException;
use App\Models\Project;
use App\Models\Sale;
use App\Models\User;
use App\Models\YieldRecord;
use App\Notifications\SaleRecordedNotification;
use App\Notifications\YieldRecordedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_owns_the_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->assertTrue($project->isOwnedBy($owner));
    }

    public function test_stranger_does_not_own_the_project(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->assertFalse($project->isOwnedBy($stranger));
    }

    public function test_owner_has_access_to_their_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->assertTrue($project->hasAccess($owner));
    }

    public function test_user_without_a_grant_does_not_have_access(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->assertFalse($project->hasAccess($stranger));
    }

    public function test_owner_can_grant_access_to_another_user(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();

        $project->grantAccessTo($collaborator, $owner);

        $this->assertTrue($project->hasAccess($collaborator));
        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $collaborator->id,
        ]);
    }

    public function test_non_owner_cannot_grant_access(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->expectException(UnauthorizedProjectActionException::class);

        $project->grantAccessTo($collaborator, $stranger);
    }

    public function test_granting_access_twice_does_not_create_duplicate_grants(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();

        $project->grantAccessTo($collaborator, $owner);
        $project->grantAccessTo($collaborator, $owner);

        $this->assertDatabaseCount('project_user', 1);
    }

    public function test_granting_access_to_the_owner_is_rejected(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();

        $this->expectException(CannotGrantAccessToOwnerException::class);

        $project->grantAccessTo($owner, $owner);
    }

    public function test_owner_can_revoke_a_collaborators_access(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $project->grantAccessTo($collaborator, $owner);

        $project->revokeAccessFrom($collaborator, $owner);

        $this->assertFalse($project->hasAccess($collaborator));
        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $collaborator->id,
        ]);
    }

    public function test_non_owner_cannot_revoke_access(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->create();
        $project->grantAccessTo($collaborator, $owner);

        $this->expectException(UnauthorizedProjectActionException::class);

        $project->revokeAccessFrom($collaborator, $stranger);
    }

    public function test_owner_can_record_a_yield(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();

        $yield = $project->recordYield($owner, 12, '2026-08-06');

        $this->assertInstanceOf(YieldRecord::class, $yield);
        $this->assertDatabaseHas('yields', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'quantity' => 12,
            'produced_on' => '2026-08-06',
        ]);
    }

    public function test_recording_a_yield_notifies_the_owner_by_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();

        $yield = $project->recordYield($owner, 12, '2026-08-06');

        Notification::assertSentTo(
            $owner,
            YieldRecordedNotification::class,
            fn ($notification) => $notification->yield->is($yield)
        );
    }

    public function test_recording_a_yield_by_a_collaborator_still_notifies_the_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();
        $project->grantAccessTo($collaborator, $owner);

        $project->recordYield($collaborator, 45.5, '2026-08-06');

        Notification::assertSentTo($owner, YieldRecordedNotification::class);
        Notification::assertNotSentTo($collaborator, YieldRecordedNotification::class);
    }

    public function test_collaborator_with_access_can_record_a_yield(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();
        $project->grantAccessTo($collaborator, $owner);

        $yield = $project->recordYield($collaborator, 45.5, '2026-08-06');

        $this->assertSame($collaborator->id, $yield->user_id);
        $this->assertEquals(45.5, $yield->quantity);
    }

    public function test_user_without_access_cannot_record_a_yield(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();

        $this->expectException(UnauthorizedProjectActionException::class);

        $project->recordYield($stranger, 12, '2026-08-06');
    }

    public function test_hens_project_rejects_fractional_egg_counts(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();

        $this->expectException(InvalidYieldQuantityException::class);

        $project->recordYield($owner, 12.5, '2026-08-06');
    }

    public function test_meat_chickens_project_accepts_fractional_pounds(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();

        $yield = $project->recordYield($owner, 32.75, '2026-08-06');

        $this->assertEquals(32.75, $yield->quantity);
    }

    public function test_recording_a_negative_quantity_is_rejected(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();

        $this->expectException(InvalidYieldQuantityException::class);

        $project->recordYield($owner, -5, '2026-08-06');
    }

    public function test_owner_can_record_a_sale(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();

        $sale = $project->recordSale($owner, 12, 6.50, '2026-08-06');

        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertDatabaseHas('sales', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'quantity' => 12,
            'amount' => 6.5,
            'sold_on' => '2026-08-06',
        ]);
    }

    public function test_recording_a_sale_notifies_the_owner_by_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();

        $sale = $project->recordSale($owner, 12, 6.50, '2026-08-06');

        Notification::assertSentTo(
            $owner,
            SaleRecordedNotification::class,
            fn ($notification) => $notification->sale->is($sale)
        );
    }

    public function test_recording_a_sale_by_a_collaborator_still_notifies_the_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();
        $project->grantAccessTo($collaborator, $owner);

        $project->recordSale($collaborator, 45.5, 120, '2026-08-06');

        Notification::assertSentTo($owner, SaleRecordedNotification::class);
        Notification::assertNotSentTo($collaborator, SaleRecordedNotification::class);
    }

    public function test_collaborator_with_access_can_record_a_sale(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->meatChickens()->create();
        $project->grantAccessTo($collaborator, $owner);

        $sale = $project->recordSale($collaborator, 45.5, 120, '2026-08-06');

        $this->assertSame($collaborator->id, $sale->user_id);
        $this->assertEquals(45.5, $sale->quantity);
    }

    public function test_user_without_access_cannot_record_a_sale(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();

        $this->expectException(UnauthorizedProjectActionException::class);

        $project->recordSale($stranger, 12, 6.50, '2026-08-06');
    }

    public function test_hens_project_rejects_fractional_egg_counts_for_sales(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner, 'owner')->hens()->create();

        $this->expectException(InvalidSaleQuantityException::class);

        $project->recordSale($owner, 12.5, 6.50, '2026-08-06');
    }

    public function test_project_type_is_cast_to_an_enum(): void
    {
        $project = Project::factory()->hens()->create();

        $this->assertSame(ProjectType::Hens, $project->type);
    }
}
