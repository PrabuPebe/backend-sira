<?php

namespace Tests\Feature;

use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_facility_via_post(): void
    {
        $payload = [
            'name' => 'Central Hospital',
        ];

        $response = $this->postJson('/api/facilities', $payload);

        $response->assertStatus(201)
            ->assertExactJson([
                'msg' => 'Facility created successfully',
                'status' => 201,
            ]);

        $this->assertDatabaseHas('facilities', [
            'name' => 'Central Hospital',
        ]);

        $facility = Facility::first();
        $this->assertNotNull($facility->id);
        $this->assertNotNull($facility->uuid);
    }

    public function test_it_can_get_all_facilities_via_get(): void
    {
        Facility::create(['name' => 'Facility 1']);
        Facility::create(['name' => 'Facility 2']);

        $response = $this->getJson('/api/facilities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'name', 'created_at', 'updated_at'],
                ],
                'msg',
                'status',
            ])
            ->assertJson([
                'msg' => 'Facilities retrieved successfully',
                'status' => 200,
            ]);

        // Assert ID is not exposed
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertArrayNotHasKey('id', $data[0]);
    }

    public function test_it_can_get_single_facility_by_uuid_via_query_param(): void
    {
        $facility = Facility::create(['name' => 'Special Clinic']);

        $response = $this->getJson("/api/facilities/detail?uuid={$facility->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'uuid' => $facility->uuid,
                    'name' => 'Special Clinic',
                ],
                'msg' => 'Facility retrieved successfully',
                'status' => 200,
            ]);

        // Assert ID is not exposed
        $this->assertArrayNotHasKey('id', $response->json('data'));
    }

    public function test_it_can_update_a_facility_via_put_with_query_param(): void
    {
        $facility = Facility::create(['name' => 'Old Name']);

        $response = $this->putJson("/api/facilities?uuid={$facility->uuid}", [
            'name' => 'Brand New Name',
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'msg' => 'Facility updated successfully',
                'status' => 200,
            ]);

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'name' => 'Brand New Name',
        ]);
    }

    public function test_it_can_delete_a_facility_via_delete_with_query_param(): void
    {
        $facility = Facility::create(['name' => 'To Be Deleted']);

        $response = $this->deleteJson("/api/facilities?uuid={$facility->uuid}");

        $response->assertStatus(200)
            ->assertExactJson([
                'msg' => 'Facility deleted successfully',
                'status' => 204,
            ]);

        $this->assertDatabaseMissing('facilities', [
            'id' => $facility->id,
        ]);
    }

    public function test_it_returns_400_when_uuid_query_param_is_missing(): void
    {
        $response = $this->getJson('/api/facilities/detail');
        $response->assertStatus(400);

        $response = $this->putJson('/api/facilities', ['name' => 'No UUID']);
        $response->assertStatus(400);

        $response = $this->deleteJson('/api/facilities');
        $response->assertStatus(400);
    }

    public function test_it_returns_404_when_facility_uuid_not_found(): void
    {
        $response = $this->getJson('/api/facilities/detail?uuid=00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }
}
