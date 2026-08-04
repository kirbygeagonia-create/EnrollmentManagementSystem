<?php

namespace Tests\Feature;

use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAccessSmokeTest extends TestCase
{
    public function test_admin_can_access_all_module_index_pages(): void
    {
        // This smoke test runs against the real MySQL database (not sqlite)
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'ems',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);
        DB::purge('mysql');

        $admin = Staffusers::where('username', 'staff8')->firstOrFail();

        $routes = [
            'dashboard',
            'admission.index',
            'exam.index',
            'evaluation.index',
            'assessment.index',
            'accounting.index',
            'clearance.index',
            'blocking.index',
            'registrar.index',
            'clinic.index',
            'id.index',
            'admin.reference-data.index',
            'admin.users.index',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route));
            $this->assertEquals(200, $response->status(), "Route [{$route}] returned {$response->status()} for admin");
        }
    }
}
