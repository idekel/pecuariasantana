<?php

namespace Tests\Unit\Enums;

use App\Enums\ProjectType;
use PHPUnit\Framework\TestCase;

class ProjectTypeTest extends TestCase
{
    public function test_hens_projects_yield_eggs(): void
    {
        $this->assertSame('eggs', ProjectType::Hens->yieldUnit());
    }

    public function test_meat_chickens_projects_yield_pounds(): void
    {
        $this->assertSame('pounds', ProjectType::MeatChickens->yieldUnit());
    }

    public function test_hens_accept_whole_number_quantities(): void
    {
        $this->assertTrue(ProjectType::Hens->isValidYieldQuantity(12));
        $this->assertTrue(ProjectType::Hens->isValidYieldQuantity(12.0));
        $this->assertTrue(ProjectType::Hens->isValidYieldQuantity(0));
    }

    public function test_hens_reject_fractional_quantities(): void
    {
        $this->assertFalse(ProjectType::Hens->isValidYieldQuantity(12.5));
    }

    public function test_meat_chickens_accept_fractional_quantities(): void
    {
        $this->assertTrue(ProjectType::MeatChickens->isValidYieldQuantity(12.75));
        $this->assertTrue(ProjectType::MeatChickens->isValidYieldQuantity(0));
    }

    public function test_negative_quantities_are_invalid_for_any_type(): void
    {
        $this->assertFalse(ProjectType::Hens->isValidYieldQuantity(-1));
        $this->assertFalse(ProjectType::MeatChickens->isValidYieldQuantity(-0.5));
    }
}
