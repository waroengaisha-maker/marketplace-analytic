<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShopeeApiController;
use Tests\TestCase;

class ControllerDependencyInjectionTest extends TestCase
{
    public function test_dashboard_controller_is_resolvable_from_the_container(): void
    {
        $this->assertInstanceOf(DashboardController::class, app(DashboardController::class));
    }

    public function test_shopee_api_controller_is_resolvable_from_the_container(): void
    {
        $this->assertInstanceOf(ShopeeApiController::class, app(ShopeeApiController::class));
    }
}
