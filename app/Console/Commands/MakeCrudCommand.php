<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud {name : The name of the class/model (e.g. Facility)} {--table= : The database table name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate full N-Tier CRUD architecture (Migration, Model, Repository, Service, Requests, Controller with Swagger)';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawName = trim($this->argument('name'));
        if (empty($rawName)) {
            $this->error('The name argument is required.');
            return self::FAILURE;
        }

        $modelName = Str::studly(Str::singular($rawName));
        $tableName = $this->option('table') ?: Str::snake(Str::pluralStudly($modelName));
        $modelVariable = Str::camel($modelName);
        $modelLower = Str::lower(Str::headline($modelName));
        $modelPluralLower = Str::lower(Str::headline(Str::plural($modelName)));
        $modelLabel = Str::headline($modelName);
        $modelPluralLabel = Str::headline(Str::plural($modelName));
        $routeSlug = Str::kebab(Str::pluralStudly($modelName));

        $this->info("Creating N-Tier CRUD boilerplate for: {$modelName} (table: {$tableName})...");

        $replacements = [
            '{{modelName}}' => $modelName,
            '{{tableName}}' => $tableName,
            '{{modelVariable}}' => $modelVariable,
            '{{modelLower}}' => $modelLower,
            '{{modelPluralLower}}' => $modelPluralLower,
            '{{modelLabel}}' => $modelLabel,
            '{{modelPluralLabel}}' => $modelPluralLabel,
            '{{routeSlug}}' => $routeSlug,
        ];

        // 1. Generate Migration
        $this->generateMigration($tableName, $replacements);

        // 2. Generate Model
        $this->generateFileFromStub(
            __DIR__ . '/Stubs/model.stub',
            app_path("Models/{$modelName}.php"),
            $replacements,
            'Model'
        );

        // 3. Generate Repository Interface
        $this->generateFileFromStub(
            __DIR__ . '/Stubs/repository_interface.stub',
            app_path("Repositories/Contracts/{$modelName}RepositoryInterface.php"),
            $replacements,
            'Repository Interface'
        );

        // 4. Generate Repository Implementation
        $this->generateFileFromStub(
            __DIR__ . '/Stubs/repository_eloquent.stub',
            app_path("Repositories/Eloquent/{$modelName}Repository.php"),
            $replacements,
            'Repository Implementation'
        );

        // 5. Generate Service Interface
        $this->generateFileFromStub(
            __DIR__ . '/Stubs/service_interface.stub',
            app_path("Services/Contracts/{$modelName}ServiceInterface.php"),
            $replacements,
            'Service Interface'
        );

        // 6. Generate Service Implementation
        $this->generateFileFromStub(
            __DIR__ . '/Stubs/service.stub',
            app_path("Services/{$modelName}Service.php"),
            $replacements,
            'Service Implementation'
        );

        // 7. Generate Form Requests
        $this->generateFileFromStub(
            __DIR__ . '/Stubs/request_store.stub',
            app_path("Http/Requests/{$modelName}/Store{$modelName}Request.php"),
            $replacements,
            'Store Form Request'
        );
        $this->generateFileFromStub(
            __DIR__ . '/Stubs/request_update.stub',
            app_path("Http/Requests/{$modelName}/Update{$modelName}Request.php"),
            $replacements,
            'Update Form Request'
        );

        // 8. Generate Controller (with Swagger attributes)
        $this->generateFileFromStub(
            __DIR__ . '/Stubs/controller.stub',
            app_path("Http/Controllers/Api/{$modelName}Controller.php"),
            $replacements,
            'API Controller (with Swagger)'
        );

        // 9. Auto-Register Route
        $this->registerRoute($routeSlug, $modelName);

        // 10. Auto-Register Bindings in RepositoryServiceProvider
        $this->registerBindings($modelName);

        $this->newLine();
        $this->info("CRUD for {$modelName} created successfully!");
        $this->line("• Route: <info>/api/{$routeSlug}</info>");
        $this->line("• Model: <info>app/Models/{$modelName}.php</info>");
        $this->line("• Repository: <info>app/Repositories/Eloquent/{$modelName}Repository.php</info>");
        $this->line("• Service: <info>app/Services/{$modelName}Service.php</info>");
        $this->line("• Controller: <info>app/Http/Controllers/Api/{$modelName}Controller.php</info>");
        $this->newLine();
        $this->comment("Next steps:");
        $this->line("1. Run migrations: <comment>php artisan migrate</comment>");
        $this->line("2. Regenerate Swagger: <comment>php artisan l5-swagger:generate</comment>");

        return self::SUCCESS;
    }

    protected function generateMigration(string $tableName, array $replacements): void
    {
        $existing = glob(database_path("migrations/*_create_{$tableName}_table.php"));
        if (! empty($existing)) {
            $this->warn("Migration for table [{$tableName}] already exists: " . basename($existing[0]));
            return;
        }

        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_create_{$tableName}_table.php";
        $destination = database_path("migrations/{$fileName}");

        $content = $this->getStubContent(__DIR__ . '/Stubs/migration.stub', $replacements);
        $this->files->put($destination, $content);
        $this->info("Migration created: database/migrations/{$fileName}");
    }

    protected function generateFileFromStub(string $stubPath, string $destinationPath, array $replacements, string $label): void
    {
        $directory = dirname($destinationPath);
        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        if ($this->files->exists($destinationPath)) {
            $this->warn("{$label} already exists: {$destinationPath}");
            return;
        }

        $content = $this->getStubContent($stubPath, $replacements);
        $this->files->put($destinationPath, $content);
        $this->info("{$label} created: " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $destinationPath));
    }

    protected function getStubContent(string $stubPath, array $replacements): string
    {
        $content = $this->files->get($stubPath);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function registerRoute(string $routeSlug, string $modelName): void
    {
        $apiRoutePath = base_path('routes/api.php');
        if (! $this->files->exists($apiRoutePath)) {
            return;
        }

        $content = $this->files->get($apiRoutePath);
        $controllerClass = "App\\Http\\Controllers\\Api\\{$modelName}Controller";
        $routeDefinition = "Route::apiResource('{$routeSlug}', \\{$controllerClass}::class);";

        if (str_contains($content, $routeDefinition) || str_contains($content, "'{$routeSlug}'")) {
            $this->warn("Route for [{$routeSlug}] already registered in routes/api.php");
            return;
        }

        $content .= "\n" . $routeDefinition . "\n";
        $this->files->put($apiRoutePath, $content);
        $this->info("Route registered: /api/{$routeSlug} in routes/api.php");
    }

    protected function registerBindings(string $modelName): void
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');
        if (! $this->files->exists($providerPath)) {
            return;
        }

        $content = $this->files->get($providerPath);
        $repoInterface = "\\App\\Repositories\\Contracts\\{$modelName}RepositoryInterface::class";
        $repoImpl = "\\App\\Repositories\\Eloquent\\{$modelName}Repository::class";
        $serviceInterface = "\\App\\Services\\Contracts\\{$modelName}ServiceInterface::class";
        $serviceImpl = "\\App\\Services\\{$modelName}Service::class";

        if (str_contains($content, $repoInterface)) {
            $this->warn("Bindings for [{$modelName}] already exist in RepositoryServiceProvider.php");
            return;
        }

        $bindingLines = "        {$repoInterface} => {$repoImpl},\n";
        $bindingLines .= "        {$serviceInterface} => {$serviceImpl},\n";
        $bindingLines .= "        // [BINDINGS_PLACEHOLDER]";

        $content = str_replace('// [BINDINGS_PLACEHOLDER]', $bindingLines, $content);
        $this->files->put($providerPath, $content);
        $this->info("Bindings registered in RepositoryServiceProvider.php");
    }
}
