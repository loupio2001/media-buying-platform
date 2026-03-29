<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignPlatformController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\ReportController;
use Tests\TestCase;

class ApiControllersStructureContractTest extends TestCase
{
    public function test_all_external_api_controllers_extend_api_controller(): void
    {
        $controllers = [
            PlatformController::class,
            ClientController::class,
            CampaignController::class,
            CampaignPlatformController::class,
            BriefController::class,
            ReportController::class,
            NotificationController::class,
        ];

        foreach ($controllers as $controllerClass) {
            $this->assertTrue(
                is_subclass_of($controllerClass, ApiController::class),
                "{$controllerClass} must extend " . ApiController::class
            );
        }
    }

    public function test_external_api_controllers_do_not_use_direct_response_json_calls(): void
    {
        $files = [
            'app/Http/Controllers/Api/PlatformController.php',
            'app/Http/Controllers/Api/ClientController.php',
            'app/Http/Controllers/Api/CampaignController.php',
            'app/Http/Controllers/Api/CampaignPlatformController.php',
            'app/Http/Controllers/Api/BriefController.php',
            'app/Http/Controllers/Api/ReportController.php',
            'app/Http/Controllers/Api/NotificationController.php',
        ];

        foreach ($files as $file) {
            $content = file_get_contents(base_path($file));

            $this->assertNotFalse($content, "Unable to read {$file}");
            $this->assertStringNotContainsString('response()->json(', $content, "{$file} should rely on ApiController helpers.");
        }
    }
}
