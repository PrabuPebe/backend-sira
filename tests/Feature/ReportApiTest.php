<?php

namespace Tests\Feature;

use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $pelapor;
    protected User $teknisi;
    protected User $kaur;
    protected User $adminUniv;
    protected Location $gedungFti;
    protected Location $gedungFbe;
    protected DamageCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Setup Master Data
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

        $this->category = DamageCategory::create([
            'name' => 'Kelistrikan / Lampu',
            'description' => 'Kerusakan instalasi listrik, stop kontak, saklar, dan lampu mati',
        ]);

        // Setup Users
        $this->pelapor = User::create([
            'full_name' => 'Ahmad Fauzi',
            'email' => 'ahmad@uii.ac.id',
            'password' => Hash::make('password'),
            'role' => 'PELAPOR',
            'reporter_type' => 'MAHASISWA',
            'is_active' => true,
        ]);

        $this->teknisi = User::create([
            'full_name' => 'Bambang Teknisi',
            'email' => 'teknisi.fti@uii.ac.id',
            'password' => Hash::make('password'),
            'role' => 'TEKNISI',
            'is_active' => true,
        ]);

        $this->kaur = User::create([
            'full_name' => 'Slamet Kaur',
            'email' => 'kaur.fti@uii.ac.id',
            'password' => Hash::make('password'),
            'role' => 'ADMIN_UNIT',
            'is_active' => true,
        ]);

        $this->adminUniv = User::create([
            'full_name' => 'Admin Universitas',
            'email' => 'admin.univ@uii.ac.id',
            'password' => Hash::make('password'),
            'role' => 'ADMIN_UNIV',
            'is_active' => true,
        ]);

        // Bind scopes for Kaur and Teknisi to FTI
        DB::table('user_building_scopes')->insert([
            ['user_id' => $this->teknisi->id, 'building_id' => $this->gedungFti->id, 'granted_by' => $this->adminUniv->id],
            ['user_id' => $this->kaur->id, 'building_id' => $this->gedungFti->id, 'granted_by' => $this->adminUniv->id],
        ]);
    }

    protected function authenticateAs(User $user): string
    {
        $this->app['auth']->forgetGuards();
        return $user->createToken('test_token')->plainTextToken;
    }

    public function test_pelapor_can_create_report_with_photo_and_no_id_leaks(): void
    {
        $token = $this->authenticateAs($this->pelapor);

        $file = UploadedFile::fake()->image('lampu_mati.jpg', 640, 480);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Lab Komputer 3',
                'floor' => '2',
                'category_uuid' => $this->category->uuid,
                'description' => 'Lampu LED di baris tengah berkedip keras lalu mati.',
                'photo' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'msg',
                'data' => [
                    'uuid',
                    'ticket_no',
                    'room_name',
                    'floor',
                    'description',
                    'status',
                    'priority',
                    'building' => ['uuid', 'name', 'code'],
                    'category' => ['uuid', 'name'],
                    'photos' => [
                        '*' => ['uuid', 'photo_url', 'photo_type'],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('reporter_id', $data);
        $this->assertArrayNotHasKey('building_id', $data);
        $this->assertArrayNotHasKey('category_id', $data);
        $this->assertStringStartsWith('RPT-' . date('Y') . '-', $data['ticket_no']);
        $this->assertEquals('DITERIMA', $data['status']);
        $this->assertEquals('NORMAL', $data['priority']);
    }

    public function test_public_feed_masks_pelapor_name_and_hides_ids(): void
    {
        $token = $this->authenticateAs($this->pelapor);

        $file = UploadedFile::fake()->image('meja_rusak.jpg');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang 2.1',
                'category_uuid' => $this->category->uuid,
                'description' => 'Meja patah',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $response = $this->getJson('/api/reports/public');

        $response->assertStatus(200);

        $items = $response->json('data.data');
        $this->assertNotEmpty($items);
        $first = $items[0];

        $this->assertArrayNotHasKey('id', $first);
        $this->assertArrayNotHasKey('reporter_id', $first);
        $this->assertArrayNotHasKey('building_id', $first);

        // Nama 'Ahmad Fauzi' harus tersamar seperti 'A*** F****'
        $this->assertNotEquals('Ahmad Fauzi', $first['reporter']['full_name']);
        $this->assertStringContainsString('*', $first['reporter']['full_name']);
    }

    public function test_pelapor_can_view_my_reports(): void
    {
        $token = $this->authenticateAs($this->pelapor);

        $file = UploadedFile::fake()->image('test.jpg');
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang 1.1',
                'category_uuid' => $this->category->uuid,
                'description' => 'AC tidak dingin',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reports/my-reports');

        $response->assertStatus(200);
        $items = $response->json('data.data');
        $this->assertNotEmpty($items);
        $this->assertArrayNotHasKey('id', $items[0]);
    }

    public function test_user_can_like_and_unlike_report_via_query_param(): void
    {
        $token = $this->authenticateAs($this->pelapor);

        $file = UploadedFile::fake()->image('like_test.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang 3.2',
                'category_uuid' => $this->category->uuid,
                'description' => 'Stop kontak berasap',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $uuid = $createRes->json('data.uuid');

        // 1. Like report
        $likeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/reports/like?uuid={$uuid}");

        $likeRes->assertStatus(200)
            ->assertJson([
                'data' => [
                    'liked' => true,
                    'like_count' => 1,
                ],
            ]);

        // 2. Unlike report
        $unlikeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/reports/like?uuid={$uuid}");

        $unlikeRes->assertStatus(200)
            ->assertJson([
                'data' => [
                    'liked' => false,
                    'like_count' => 0,
                ],
            ]);
    }

    public function test_technician_can_self_assign_report_via_query_param(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $file = UploadedFile::fake()->image('self_assign.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang 4.1',
                'category_uuid' => $this->category->uuid,
                'description' => 'Pintu rusak',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $uuid = $createRes->json('data.uuid');

        // Switch to Teknisi
        $teknisiToken = $this->authenticateAs($this->teknisi);
        $assignRes = $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->postJson("/api/reports/self-assign?uuid={$uuid}");

        $assignRes->assertStatus(200);
        $data = $assignRes->json('data');
        $this->assertEquals('DIPROSES', $data['status']);
        $this->assertArrayNotHasKey('id', $data);

        // Pastikan timeline terbentuk
        $report = Report::where('uuid', $uuid)->first();
        $this->assertDatabaseHas('report_timelines', [
            'report_id' => $report->id,
            'actor_id' => $this->teknisi->id,
            'event_type' => 'STATUS_CHANGED',
        ]);
    }

    public function test_kaur_can_assign_technician_and_technician_can_accept(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $file = UploadedFile::fake()->image('kaur_assign.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Lab Jaringan',
                'category_uuid' => $this->category->uuid,
                'description' => 'Kabel LAN putus',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $uuid = $createRes->json('data.uuid');

        // Switch to Kaur
        $kaurToken = $this->authenticateAs($this->kaur);
        $assignRes = $this->withHeader('Authorization', 'Bearer ' . $kaurToken)
            ->postJson("/api/reports/assign?uuid={$uuid}", [
                'technician_uuid' => $this->teknisi->uuid,
            ]);

        $assignRes->assertStatus(200);

        // Switch to Teknisi
        $teknisiToken = $this->authenticateAs($this->teknisi);
        $acceptRes = $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->postJson("/api/reports/respond-assignment?uuid={$uuid}", [
                'action' => 'ACCEPT',
            ]);

        $acceptRes->assertStatus(200);
        $data = $acceptRes->json('data');
        $this->assertEquals('DIPROSES', $data['status']);
    }

    public function test_technician_cannot_finish_report_without_proof_photo(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $file = UploadedFile::fake()->image('finish_test.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang 1.2',
                'category_uuid' => $this->category->uuid,
                'description' => 'Lampu gantung putus',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $uuid = $createRes->json('data.uuid');

        // Switch to Teknisi
        $teknisiToken = $this->authenticateAs($this->teknisi);
        $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->postJson("/api/reports/self-assign?uuid={$uuid}");

        // Coba selesaikan tanpa foto bukti (harus 422)
        $finishRes = $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->postJson("/api/reports/status?uuid={$uuid}", [
                'status' => 'SELESAI',
                'note' => 'Sudah diperbaiki tapi lupa foto',
            ]);

        $finishRes->assertStatus(422);

        // Selesaikan DENGAN foto bukti
        $proofFile = UploadedFile::fake()->image('bukti_selesai.jpg');
        $finishSuccessRes = $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->post("/api/reports/status?uuid={$uuid}", [
                'status' => 'SELESAI',
                'note' => 'Lampu sudah diganti baru dan berfungsi',
                'proof_photo' => $proofFile,
            ], ['Accept' => 'application/json']);

        $finishSuccessRes->assertStatus(200);
        $this->assertEquals('SELESAI', $finishSuccessRes->json('data.status'));
    }

    public function test_pelapor_can_confirm_completion_with_rating(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $file = UploadedFile::fake()->image('confirm_test.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang Kelas 101',
                'category_uuid' => $this->category->uuid,
                'description' => 'Kursi patah',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $uuid = $createRes->json('data.uuid');

        // Teknisi self-assign dan selesaikan
        $teknisiToken = $this->authenticateAs($this->teknisi);
        $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->postJson("/api/reports/self-assign?uuid={$uuid}");

        $proofFile = UploadedFile::fake()->image('bukti.jpg');
        $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->post("/api/reports/status?uuid={$uuid}", [
                'status' => 'SELESAI',
                'proof_photo' => $proofFile,
            ], ['Accept' => 'application/json']);

        // Switch back to Pelapor
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $confirmRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->postJson("/api/reports/confirm?uuid={$uuid}", [
                'rating' => 5,
                'review' => 'Pengerjaan sangat cepat dan rapi. Terima kasih!',
            ]);

        $confirmRes->assertStatus(201);
        $this->assertArrayNotHasKey('id', $confirmRes->json('data'));

        $report = Report::where('uuid', $uuid)->first();
        $this->assertDatabaseHas('report_confirmations', [
            'report_id' => $report->id,
            'rating' => 5,
        ]);
    }

    public function test_pelapor_can_reopen_report_within_7_days(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $file = UploadedFile::fake()->image('reopen_test.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang Kelas 202',
                'category_uuid' => $this->category->uuid,
                'description' => 'Proyektor mati',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $uuid = $createRes->json('data.uuid');

        $teknisiToken = $this->authenticateAs($this->teknisi);
        $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->postJson("/api/reports/self-assign?uuid={$uuid}");

        $proofFile = UploadedFile::fake()->image('bukti.jpg');
        $this->withHeader('Authorization', 'Bearer ' . $teknisiToken)
            ->post("/api/reports/status?uuid={$uuid}", [
                'status' => 'SELESAI',
                'proof_photo' => $proofFile,
            ], ['Accept' => 'application/json']);

        // Pelapor mengajukan sanggahan / reopen
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $reopenRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->postJson("/api/reports/reopen?uuid={$uuid}", [
                'reason' => 'Proyektor masih tidak mau menyala jika dihubungkan ke HDMI',
            ]);

        $reopenRes->assertStatus(200);
        $this->assertEquals('DIPROSES', $reopenRes->json('data.status'));
    }

    public function test_kaur_can_move_building_via_query_param(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $file = UploadedFile::fake()->image('move_test.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang Rektorat Lama',
                'category_uuid' => $this->category->uuid,
                'description' => 'Kerusakan di gedung lain',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $uuid = $createRes->json('data.uuid');

        // Kaur move building
        $kaurToken = $this->authenticateAs($this->kaur);
        $moveRes = $this->withHeader('Authorization', 'Bearer ' . $kaurToken)
            ->postJson("/api/reports/move-building?uuid={$uuid}", [
                'target_building_uuid' => $this->gedungFbe->uuid,
                'reason' => 'Salah input gedung oleh pelapor',
            ]);

        $moveRes->assertStatus(200);
        $this->assertEquals($this->gedungFbe->uuid, $moveRes->json('data.building.uuid'));
    }

    public function test_detail_by_uuid_and_ticket_no_via_query_param(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $file = UploadedFile::fake()->image('detail_test.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang 5.5',
                'category_uuid' => $this->category->uuid,
                'description' => 'Kipas angin copot',
                'photo' => $file,
            ], ['Accept' => 'application/json']);

        $uuid = $createRes->json('data.uuid');
        $ticketNo = $createRes->json('data.ticket_no');

        // 1. By UUID
        $resUuid = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->getJson("/api/reports/detail?uuid={$uuid}");
        $resUuid->assertStatus(200);
        $this->assertEquals($uuid, $resUuid->json('data.uuid'));
        $this->assertArrayNotHasKey('id', $resUuid->json('data'));

        // 2. By Ticket No
        $resTicket = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->getJson("/api/reports/detail?ticket_no={$ticketNo}");
        $resTicket->assertStatus(200);
        $this->assertEquals($ticketNo, $resTicket->json('data.ticket_no'));
        $this->assertArrayNotHasKey('id', $resTicket->json('data'));
    }

    public function test_new_update_requires_authentication(): void
    {
        $res = $this->getJson('/api/new-update');
        $res->assertStatus(401);

        $res2 = $this->getJson('/api/reports/new-update');
        $res2->assertStatus(401);
    }

    public function test_new_update_returns_latest_updates_with_required_fields_and_no_id(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);

        // Buat laporan 1
        $file1 = UploadedFile::fake()->image('lampu1.jpg');
        $rep1 = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Lab Komputer 3',
                'floor' => '3',
                'category_uuid' => $this->category->uuid,
                'description' => 'Lampu LED redup dan berkedip terus',
                'photo' => $file1,
            ], ['Accept' => 'application/json']);
        $rep1->assertStatus(201);
        $ticket1 = $rep1->json('data.ticket_no');
        $uuid1 = $rep1->json('data.uuid');

        // Panggil endpoint /api/new-update
        $res = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->getJson('/api/new-update');

        $res->assertStatus(200);
        $dataList = $res->json('data.data');
        $this->assertNotEmpty($dataList);

        $firstItem = $dataList[0];

        // Verifikasi semua field yang wajib ada sesuai permintaan user:
        // no tiket, title kerusakan, lokasi, tanggal, status, uuid
        $this->assertArrayHasKey('ticket_no', $firstItem);
        $this->assertArrayHasKey('no_tiket', $firstItem);
        $this->assertArrayHasKey('title_kerusakan', $firstItem);
        $this->assertArrayHasKey('title', $firstItem);
        $this->assertArrayHasKey('lokasi', $firstItem);
        $this->assertArrayHasKey('tanggal', $firstItem);
        $this->assertArrayHasKey('status', $firstItem);
        $this->assertArrayHasKey('uuid', $firstItem);

        // Verifikasi nilai data
        $this->assertEquals($ticket1, $firstItem['ticket_no']);
        $this->assertEquals($uuid1, $firstItem['uuid']);
        $this->assertEquals('DITERIMA', $firstItem['status']);
        $this->assertEquals($this->category->name, $firstItem['title_kerusakan']);
        $this->assertStringContainsString('Lab Komputer 3', $firstItem['lokasi']);
        $this->assertNotEmpty($firstItem['tanggal']);

        // Verifikasi bahwa ID internal tidak keluar sama sekali!
        $this->assertArrayNotHasKey('id', $firstItem);
        $this->assertArrayNotHasKey('reporter_id', $firstItem);
        $this->assertArrayNotHasKey('building_id', $firstItem);
        $this->assertArrayNotHasKey('category_id', $firstItem);
        $this->assertArrayNotHasKey('pivot', $firstItem);

        // Uji juga via /api/reports/new-update
        $resReportsAlias = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->getJson('/api/reports/new-update');
        $resReportsAlias->assertStatus(200);
        $this->assertEquals($ticket1, $resReportsAlias->json('data.data.0.ticket_no'));
    }

    public function test_new_update_view_timeline_and_filters(): void
    {
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $file = UploadedFile::fake()->image('saklar.jpg');

        $rep = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->post('/api/reports', [
                'building_uuid' => $this->gedungFti->uuid,
                'room_name' => 'Ruang Dosen 2',
                'floor' => '2',
                'category_uuid' => $this->category->uuid,
                'description' => 'Saklar lampu pecah',
                'photo' => $file,
            ], ['Accept' => 'application/json']);
        $rep->assertStatus(201);
        $uuid = $rep->json('data.uuid');

        // Teknisi update status
        $tekToken = $this->authenticateAs($this->teknisi);
        $this->withHeader('Authorization', 'Bearer ' . $tekToken)
            ->postJson("/api/reports/self-assign?uuid={$uuid}")
            ->assertStatus(200);

        // Pelapor query new-update dengan view=timeline
        $pelaporToken = $this->authenticateAs($this->pelapor);
        $resTimeline = $this->withHeader('Authorization', 'Bearer ' . $pelaporToken)
            ->getJson('/api/new-update?view=timeline');

        $resTimeline->assertStatus(200);
        $timelineItems = $resTimeline->json('data.data');
        $this->assertNotEmpty($timelineItems);

        $item = $timelineItems[0];
        $this->assertArrayHasKey('ticket_no', $item);
        $this->assertArrayHasKey('title_kerusakan', $item);
        $this->assertArrayHasKey('lokasi', $item);
        $this->assertArrayHasKey('tanggal', $item);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('uuid', $item);
        $this->assertArrayNotHasKey('id', $item);
    }
}
