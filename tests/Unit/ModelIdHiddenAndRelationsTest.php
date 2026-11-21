<?php

namespace Tests\Unit;

use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportTimeline;
use App\Models\User;
use App\Models\UserBuildingScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelIdHiddenAndRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_models_hide_internal_id_and_foreign_keys_from_array_and_json(): void
    {
        $user = User::create([
            'full_name' => 'Dr. Budi',
            'email' => 'budi@uii.ac.id',
            'password' => 'secret123',
            'role' => 'PELAPOR',
            'reporter_type' => 'DOSEN',
        ]);

        $location = Location::create([
            'name' => 'Gedung FTI',
            'code' => 'FTI',
            'type' => 'BUILDING',
        ]);

        $category = DamageCategory::create([
            'name' => 'Kelistrikan',
        ]);

        $report = Report::create([
            'ticket_no' => 'RPT-2026-0001',
            'reporter_id' => $user->id,
            'building_id' => $location->id,
            'room_name' => 'Ruang 3.4',
            'floor' => '3',
            'category_id' => $category->id,
            'description' => 'AC mati total di ruang dosen',
        ]);

        $timeline = ReportTimeline::create([
            'report_id' => $report->id,
            'actor_id' => $user->id,
            'event_type' => 'SUBMITTED',
            'to_status' => 'DITERIMA',
            'note' => 'Laporan dibuat oleh pelapor',
        ]);

        $notification = Notification::create([
            'recipient_id' => $user->id,
            'report_id' => $report->id,
            'type' => 'NEW_REPORT',
            'message' => 'Laporan Anda telah berhasil dibuat dengan nomor tiket RPT-2026-0001',
        ]);

        // 1. Assert UUID is generated automatically
        $this->assertNotEmpty($user->uuid);
        $this->assertNotEmpty($location->uuid);
        $this->assertNotEmpty($category->uuid);
        $this->assertNotEmpty($report->uuid);
        $this->assertNotEmpty($timeline->uuid);
        $this->assertNotEmpty($notification->uuid);

        // 2. Assert ID and Foreign Keys are NOT present in toArray() serialization
        $userArray = $user->toArray();
        $this->assertArrayNotHasKey('id', $userArray);

        $locationArray = $location->toArray();
        $this->assertArrayNotHasKey('id', $locationArray);
        $this->assertArrayNotHasKey('parent_id', $locationArray);

        $categoryArray = $category->toArray();
        $this->assertArrayNotHasKey('id', $categoryArray);

        $reportArray = $report->toArray();
        $this->assertArrayNotHasKey('id', $reportArray);
        $this->assertArrayNotHasKey('reporter_id', $reportArray);
        $this->assertArrayNotHasKey('building_id', $reportArray);
        $this->assertArrayNotHasKey('category_id', $reportArray);
        $this->assertArrayNotHasKey('assigned_technician_id', $reportArray);

        $timelineArray = $timeline->toArray();
        $this->assertArrayNotHasKey('id', $timelineArray);
        $this->assertArrayNotHasKey('report_id', $timelineArray);
        $this->assertArrayNotHasKey('actor_id', $timelineArray);

        $notificationArray = $notification->toArray();
        $this->assertArrayNotHasKey('id', $notificationArray);
        $this->assertArrayNotHasKey('recipient_id', $notificationArray);
        $this->assertArrayNotHasKey('report_id', $notificationArray);

        // 3. Assert Database internal relations work using ID
        $this->assertEquals($location->id, $report->building->id);
        $this->assertEquals($user->id, $report->reporter->id);
        $this->assertEquals($category->id, $report->category->id);
        $this->assertEquals($report->id, $timeline->report->id);
    }

    public function test_user_building_scopes_relationship(): void
    {
        $adminUniv = User::create([
            'full_name' => 'Admin Universitas',
            'email' => 'admin.univ@uii.ac.id',
            'password' => 'secret123',
            'role' => 'ADMIN_UNIV',
        ]);

        $teknisi = User::create([
            'full_name' => 'Teknisi Joko',
            'email' => 'joko@uii.ac.id',
            'password' => 'secret123',
            'role' => 'TEKNISI',
        ]);

        $gedungFti = Location::create([
            'name' => 'Gedung FTI',
            'type' => 'BUILDING',
        ]);

        UserBuildingScope::create([
            'user_id' => $teknisi->id,
            'building_id' => $gedungFti->id,
            'granted_by' => $adminUniv->id,
        ]);

        $this->assertCount(1, $teknisi->buildings);
        $this->assertEquals('Gedung FTI', $teknisi->buildings->first()->name);

        // Assert pivot table integer IDs are NOT leaked
        $userArray = $teknisi->load('buildings')->toArray();
        $this->assertArrayNotHasKey('id', $userArray['buildings'][0]);
        $this->assertArrayNotHasKey('pivot', $userArray['buildings'][0]);
    }
}
