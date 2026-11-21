<?php

namespace Tests\Feature;

use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBuildingScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAndDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUniv;
    protected User $kaur;
    protected Location $gedungFti;
    protected Location $gedungFbe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUniv = User::create([
            'full_name' => 'Admin Universitas',
            'email' => 'admin.univ@uii.ac.id',
            'password' => Hash::make('password'),
            'role' => 'ADMIN_UNIV',
            'is_active' => true,
        ]);

        $this->gedungFti = Location::create([
            'name' => 'Gedung KH Mas Mansur (FTI)',
            'code' => 'FTI',
            'type' => 'GEDUNG',
        ]);

        $this->gedungFbe = Location::create([
            'name' => 'Gedung Prof Dr Ace Partadiredja (FBE)',
            'code' => 'FBE',
            'type' => 'GEDUNG',
        ]);

        $this->kaur = User::create([
            'full_name' => 'Slamet Kaur',
            'email' => 'kaur.fti@uii.ac.id',
            'password' => Hash::make('password'),
            'role' => 'ADMIN_UNIT',
            'is_active' => true,
        ]);

        DB::table('user_building_scopes')->insert([
            'user_id' => $this->kaur->id,
            'building_id' => $this->gedungFti->id,
            'granted_by' => $this->adminUniv->id,
        ]);
    }

    protected function authenticateAs(User $user): string
    {
        $this->app['auth']->forgetGuards();
        return $user->createToken('test_token')->plainTextToken;
    }

    public function test_admin_univ_can_create_user_and_set_building_scopes(): void
    {
        $adminToken = $this->authenticateAs($this->adminUniv);

        // 1. Create Staff User
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/admin/users', [
                'full_name' => 'Teknisi Elektro Baru',
                'email' => 'teknisi.elektro@uii.ac.id',
                'password' => 'PassTeknisi123!',
                'role' => 'TEKNISI',
                'building_uuids' => [$this->gedungFti->uuid],
            ]);

        $createRes->assertStatus(201);
        $userData = $createRes->json('data');
        $this->assertArrayNotHasKey('id', $userData);
        $userUuid = $userData['uuid'];

        // 2. Update Building Scopes via Query Param (?uuid=...)
        $scopeRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/admin/users/scopes?uuid={$userUuid}", [
                'building_uuids' => [$this->gedungFti->uuid, $this->gedungFbe->uuid],
            ]);

        $scopeRes->assertStatus(200);
        $this->assertArrayNotHasKey('id', $scopeRes->json('data'));
        $this->assertCount(2, $scopeRes->json('data.buildings'));
    }

    public function test_admin_univ_can_toggle_user_status_via_query_param(): void
    {
        $adminToken = $this->authenticateAs($this->adminUniv);

        $targetUser = User::create([
            'full_name' => 'User To Deactivate',
            'email' => 'deactivate@uii.ac.id',
            'password' => Hash::make('secret'),
            'role' => 'PELAPOR',
            'is_active' => true,
        ]);

        $toggleRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/admin/users/toggle-status?uuid={$targetUser->uuid}");

        $toggleRes->assertStatus(200);
        $this->assertFalse($toggleRes->json('data.is_active'));
        $this->assertArrayNotHasKey('id', $toggleRes->json('data'));

        $targetUser->refresh();
        $this->assertFalse($targetUser->is_active);
    }

    public function test_dashboard_summary_returns_aggregated_metrics(): void
    {
        $category = DamageCategory::create(['name' => 'Sipil / Gedung']);
        $pelapor = User::create([
            'full_name' => 'Pelapor Test',
            'email' => 'pelapor@uii.ac.id',
            'password' => Hash::make('secret'),
            'role' => 'PELAPOR',
            'is_active' => true,
        ]);

        Report::create([
            'ticket_no' => 'RPT-2026-0001',
            'reporter_id' => $pelapor->id,
            'building_id' => $this->gedungFti->id,
            'category_id' => $category->id,
            'room_name' => 'Ruang 101',
            'description' => 'Tembok retak',
            'status' => 'DITERIMA',
            'priority' => 'URGEN',
        ]);

        $adminToken = $this->authenticateAs($this->adminUniv);

        $res = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/dashboard/summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'diterima',
                    'diproses',
                    'selesai',
                    'active_urgen',
                    'active_major_damage',
                ],
            ]);

        $this->assertEquals(1, $res->json('data.total'));
        $this->assertEquals(1, $res->json('data.active_urgen'));
    }

    public function test_dashboard_summary_accessible_by_pelapor_scoped_to_own_reports(): void
    {
        $category = DamageCategory::create(['name' => 'Sipil / Gedung']);

        $pelaporA = User::create([
            'full_name' => 'Pelapor A',
            'email' => 'pelapora@uii.ac.id',
            'password' => Hash::make('secret'),
            'role' => 'PELAPOR',
            'is_active' => true,
        ]);

        $pelaporB = User::create([
            'full_name' => 'Pelapor B',
            'email' => 'pelaporb@uii.ac.id',
            'password' => Hash::make('secret'),
            'role' => 'PELAPOR',
            'is_active' => true,
        ]);

        // Pelapor A reports (2 reports: 1 DITERIMA with URGEN, 1 SELESAI)
        Report::create([
            'ticket_no' => 'RPT-2026-0001',
            'reporter_id' => $pelaporA->id,
            'building_id' => $this->gedungFti->id,
            'category_id' => $category->id,
            'room_name' => 'Ruang 101',
            'description' => 'Kerusakan 1',
            'status' => 'DITERIMA',
            'priority' => 'URGEN',
        ]);
        Report::create([
            'ticket_no' => 'RPT-2026-0002',
            'reporter_id' => $pelaporA->id,
            'building_id' => $this->gedungFti->id,
            'category_id' => $category->id,
            'room_name' => 'Ruang 102',
            'description' => 'Kerusakan 2',
            'status' => 'SELESAI',
            'priority' => 'SEDANG',
        ]);

        // Pelapor B report (1 report: DIPROSES)
        Report::create([
            'ticket_no' => 'RPT-2026-0003',
            'reporter_id' => $pelaporB->id,
            'building_id' => $this->gedungFbe->id,
            'category_id' => $category->id,
            'room_name' => 'Ruang 201',
            'description' => 'Kerusakan 3',
            'status' => 'DIPROSES',
            'priority' => 'RENDAH',
        ]);

        // 1. Pelapor A checks summary
        $tokenA = $this->authenticateAs($pelaporA);
        $resA = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/dashboard/summary');

        $resA->assertStatus(200);
        $this->assertEquals(2, $resA->json('data.total'));
        $this->assertEquals(1, $resA->json('data.diterima'));
        $this->assertEquals(0, $resA->json('data.diproses'));
        $this->assertEquals(1, $resA->json('data.selesai'));
        $this->assertEquals(1, $resA->json('data.active_urgen'));

        // 2. Pelapor B checks summary
        $tokenB = $this->authenticateAs($pelaporB);
        $resB = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/dashboard/summary');

        $resB->assertStatus(200);
        $this->assertEquals(1, $resB->json('data.total'));
        $this->assertEquals(0, $resB->json('data.diterima'));
        $this->assertEquals(1, $resB->json('data.diproses'));
        $this->assertEquals(0, $resB->json('data.selesai'));
        $this->assertEquals(0, $resB->json('data.active_urgen'));
    }

    public function test_dashboard_summary_allows_teknisi(): void
    {
        $teknisi = User::create([
            'full_name' => 'Teknisi Test',
            'email' => 'teknisi.test@uii.ac.id',
            'password' => Hash::make('secret'),
            'role' => 'TEKNISI',
            'is_active' => true,
        ]);

        $token = $this->authenticateAs($teknisi);
        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard/summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'available_tasks',
                    'my_active_tasks',
                    'my_completed_tasks',
                    'total_tasks',
                ],
            ]);
    }

    public function test_dashboard_summary_requires_authentication(): void
    {
        $res = $this->getJson('/api/dashboard/summary');
        $res->assertStatus(401);
    }

    public function test_dashboard_summary_validates_query_parameters(): void
    {
        $adminToken = $this->authenticateAs($this->adminUniv);

        // Invalid date format
        $resDate = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/dashboard/summary?start_date=bukan-tanggal');
        $resDate->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);

        // Non-existent building UUID
        $resBuilding = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/dashboard/summary?building_uuid=00000000-0000-0000-0000-000000000000');
        $resBuilding->assertStatus(422)
            ->assertJsonValidationErrors(['building_uuid']);
    }

    public function test_export_pronect_csv_streams_file_with_query_param(): void
    {
        $adminToken = $this->authenticateAs($this->adminUniv);

        $res = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->get("/api/reports/export?building_uuid={$this->gedungFti->uuid}");

        $res->assertStatus(200);
        $this->assertTrue(str_contains($res->headers->get('content-type'), 'text/csv'));
        $this->assertTrue(str_contains($res->headers->get('content-disposition'), 'attachment'));
    }

    public function test_notifications_index_and_mark_read_via_query_param(): void
    {
        $category = DamageCategory::create(['name' => 'Fasilitas Umum']);
        $report = Report::create([
            'ticket_no' => 'RPT-2026-9999',
            'reporter_id' => $this->adminUniv->id,
            'building_id' => $this->gedungFti->id,
            'category_id' => $category->id,
            'room_name' => 'Lobby',
            'description' => 'Pintu otomatis macet',
            'status' => 'DITERIMA',
        ]);

        $notif = Notification::create([
            'recipient_id' => $this->adminUniv->id,
            'report_id' => $report->id,
            'type' => 'NEW_REPORT',
            'message' => 'Laporan fasilitas baru masuk ke sistem.',
            'is_read' => false,
            'created_at' => now(),
        ]);

        $adminToken = $this->authenticateAs($this->adminUniv);

        // 1. List notifications
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/notifications');

        $listRes->assertStatus(200);
        $items = $listRes->json('data.data');
        $this->assertNotEmpty($items);
        $this->assertArrayNotHasKey('id', $items[0]);
        $this->assertArrayNotHasKey('recipient_id', $items[0]);

        // 2. Mark specific notification as read via ?uuid=...
        $markRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/notifications/mark-read?uuid={$notif->uuid}");

        $markRes->assertStatus(200);
        $notif->refresh();
        $this->assertTrue($notif->is_read);

        // 3. Mark all as read via ?all=1
        $markAllRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/notifications/mark-read?all=1');

        $markAllRes->assertStatus(200);
    }
}
