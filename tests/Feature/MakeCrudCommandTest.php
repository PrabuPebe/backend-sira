<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeCrudCommandTest extends TestCase
{
    protected string $originalRoutes;
    protected string $originalProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalRoutes = File::get(base_path('routes/api.php'));
        $this->originalProvider = File::get(app_path('Providers/RepositoryServiceProvider.php'));
    }

    protected function tearDown(): void
    {
        File::put(base_path('routes/api.php'), $this->originalRoutes);
        File::put(app_path('Providers/RepositoryServiceProvider.php'), $this->originalProvider);
        parent::tearDown();
    }

    public function test_it_generates_all_crud_files_for_new_entity(): void
    {
        $this->artisan('make:crud', ['name' => 'Department'])
            ->assertSuccessful();

        $this->assertTrue(File::exists(app_path('Models/Department.php')));
        $this->assertTrue(File::exists(app_path('Repositories/Contracts/DepartmentRepositoryInterface.php')));
        $this->assertTrue(File::exists(app_path('Repositories/Eloquent/DepartmentRepository.php')));
        $this->assertTrue(File::exists(app_path('Services/Contracts/DepartmentServiceInterface.php')));
        $this->assertTrue(File::exists(app_path('Services/DepartmentService.php')));
        $this->assertTrue(File::exists(app_path('Http/Requests/Department/StoreDepartmentRequest.php')));
        $this->assertTrue(File::exists(app_path('Http/Requests/Department/UpdateDepartmentRequest.php')));
        $this->assertTrue(File::exists(app_path('Http/Controllers/Api/DepartmentController.php')));

        $routeContent = File::get(base_path('routes/api.php'));
        $this->assertStringContainsString('departments', $routeContent);

        $providerContent = File::get(app_path('Providers/RepositoryServiceProvider.php'));
        $this->assertStringContainsString('DepartmentRepositoryInterface', $providerContent);
        $this->assertStringContainsString('DepartmentServiceInterface', $providerContent);

        // Cleanup generated test files
        File::delete(app_path('Models/Department.php'));
        File::delete(app_path('Repositories/Contracts/DepartmentRepositoryInterface.php'));
        File::delete(app_path('Repositories/Eloquent/DepartmentRepository.php'));
        File::delete(app_path('Services/Contracts/DepartmentServiceInterface.php'));
        File::delete(app_path('Services/DepartmentService.php'));
        File::deleteDirectory(app_path('Http/Requests/Department'));
        File::delete(app_path('Http/Controllers/Api/DepartmentController.php'));
        foreach (glob(database_path('migrations/*_create_departments_table.php')) as $migration) {
            File::delete($migration);
        }
    }
}
