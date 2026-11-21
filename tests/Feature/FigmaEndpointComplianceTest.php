<?php

namespace Tests\Feature;

use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBuildingScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FigmaEndpointComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_summary_returns_all_four_dashboard_cards(): void
    {
        $building = Location::create([
            'name' => 'KH Mas Mansur',
            'code' => 'KMM',
            'faculty' => 'FTI UII',
            'floor_count' => 4,
            'is_active' => true,
        ]);

        $teknisi = User::create([
            'full_name' => 'Budi Santoso',
            'email' => 'budi.santoso@uii.ac.id',
            'password' => bcrypt('password123'),
            'role' => 'TEKNISI',
            'is_active' => true,
        ]);

        UserBuildingScope::create([
            'user_id' => $teknisi->id,
            'building_id' => $building->id,
            'granted_by' => $teknisi->id,
        ]);

        $response = $this->actingAs($teknisi, 'sanctum')->getJson('/api/dashboard/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'available_tasks',
                'my_active_tasks',
                'perlu_konfirmasi',
                'pending_confirmation_tasks',
                'completed_this_month',
                'selesai_bulan_ini',
            ],
            'msg',
            'status',
        ]);
    }

    public function test_kaur_can_view_reports_and_technicians(): void
    {
        $building = Location::create([
            'name' => 'KH Mas Mansur',
            'code' => 'KMM',
            'faculty' => 'FTI UII',
            'floor_count' => 4,
            'is_active' => true,
        ]);

        $kaur = User::create([
            'full_name' => 'Kaur FTI',
            'email' => 'kaur@uii.ac.id',
            'password' => bcrypt('password123'),
            'role' => 'ADMIN_UNIT',
            'is_active' => true,
        ]);

        UserBuildingScope::create([
            'user_id' => $kaur->id,
            'building_id' => $building->id,
            'granted_by' => $kaur->id,
        ]);

        $teknisi = User::create([
            'full_name' => 'Teknisi 1',
            'email' => 'tek1@uii.ac.id',
            'password' => bcrypt('password123'),
            'role' => 'TEKNISI',
            'is_active' => true,
        ]);

        UserBuildingScope::create([
            'user_id' => $teknisi->id,
            'building_id' => $building->id,
            'granted_by' => $kaur->id,
        ]);

        // Test GET /api/reports
        $resReports = $this->actingAs($kaur, 'sanctum')->getJson('/api/reports?per_page=5');
        $resReports->assertStatus(200);

        // Test GET /api/reports/technicians
        $resTechs = $this->actingAs($kaur, 'sanctum')->getJson('/api/reports/technicians');
        $resTechs->assertStatus(200);
        $resTechs->assertJsonFragment(['full_name' => 'Teknisi 1']);
    }

    public function test_admin_univ_can_view_and_update_user(): void
    {
        $admin = User::create([
            'full_name' => 'Admin Univ',
            'email' => 'admin@uii.ac.id',
            'password' => bcrypt('password123'),
            'role' => 'ADMIN_UNIV',
            'is_active' => true,
        ]);

        $targetUser = User::create([
            'full_name' => 'Target Staff',
            'email' => 'staff@uii.ac.id',
            'password' => bcrypt('password123'),
            'role' => 'TEKNISI',
            'is_active' => true,
        ]);

        // GET /api/admin/users/detail?uuid=...
        $resDetail = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users/detail?uuid=' . $targetUser->uuid);
        $resDetail->assertStatus(200);
        $resDetail->assertJsonFragment(['email' => 'staff@uii.ac.id']);

        // PUT /api/admin/users?uuid=...
        $resUpdate = $this->actingAs($admin, 'sanctum')->putJson('/api/admin/users?uuid=' . $targetUser->uuid, [
            'full_name' => 'Target Staff Updated',
            'email' => 'staff_updated@uii.ac.id',
        ]);
        $resUpdate->assertStatus(200);
        $resUpdate->assertJsonFragment(['full_name' => 'Target Staff Updated']);
    }

    public function test_dashboard_statistics(): void
    {
        $pimpinan = User::create([
            'full_name' => 'Rektor / Pimpinan',
            'email' => 'pimpinan@uii.ac.id',
            'password' => bcrypt('password123'),
            'role' => 'PIMPINAN',
            'is_active' => true,
        ]);

        $resStats = $this->actingAs($pimpinan, 'sanctum')->getJson('/api/dashboard/statistics?month=11&year=2026');
        $resStats->assertStatus(200);
        $resStats->assertJsonStructure([
            'data' => [
                'period',
                'summary' => [
                    'total_reports',
                    'new_reports',
                    'resolved_reports',
                    'completion_rate_percentage',
                ],
                'daily_trends',
                'status_distribution',
                'category_distribution',
                'service_performance' => [
                    'average_response_time_hours',
                    'average_resolution_time_hours',
                ],
            ],
            'msg',
            'status',
        ]);
    }
}
