<?php

namespace AiNative\Laravel\Commands;

use AiNative\Laravel\Generators\ControllerGenerator;
use AiNative\Laravel\Generators\MigrationGenerator;
use AiNative\Laravel\Generators\ModelGenerator;
use AiNative\Laravel\Generators\FactoryGenerator;
use AiNative\Laravel\Generators\SeederGenerator;
use AiNative\Laravel\Generators\PolicyGenerator;
use AiNative\Laravel\Generators\ObserverGenerator;
use AiNative\Laravel\Parsers\SchemaParser;
use AiNative\Laravel\Helpers\ConfigManager;
use AiNative\Laravel\Helpers\EnvManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class GenerateFromJsonCommand extends Command
{
    protected $signature = 'ai-native:generate 
                            {schema : The JSON schema file path}
                            {--only= : Generate only specific components (models,migrations,controllers,routes)}
                            {--force : Overwrite existing files}
                            {--dry-run : Show what would be generated without creating files}';

    protected $description = 'Generate Laravel components from AI-Native JSON schema';

    protected Filesystem $files;
    protected SchemaParser $parser;
    protected ModelGenerator $modelGenerator;
    protected MigrationGenerator $migrationGenerator;
    protected ControllerGenerator $controllerGenerator;
    protected FactoryGenerator $factoryGenerator;
    protected SeederGenerator $seederGenerator;
    protected PolicyGenerator $policyGenerator;
    protected ObserverGenerator $observerGenerator;
    protected ConfigManager $configManager;
    protected EnvManager $envManager;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        
        $this->files = $files;
        $this->parser = new SchemaParser();
        $this->modelGenerator = new ModelGenerator($files);
        $this->migrationGenerator = new MigrationGenerator($files);
        $this->controllerGenerator = new ControllerGenerator($files);
        $this->factoryGenerator = new FactoryGenerator($files);
        $this->seederGenerator = new SeederGenerator($files);
        $this->policyGenerator = new PolicyGenerator($files);
        $this->observerGenerator = new ObserverGenerator($files);
        $this->configManager = new ConfigManager();
        $this->envManager = new EnvManager();
    }

    public function handle(): int
    {
        $schemaPath = $this->argument('schema');
        
        if (!$this->files->exists($schemaPath)) {
            $this->error("Schema file not found: {$schemaPath}");
            return 1;
        }

        try {
            $this->parser->loadSchema($schemaPath);
        } catch (\Exception $e) {
            $this->error("Error parsing schema: " . $e->getMessage());
            return 1;
        }

        // Validate schema
        $errors = $this->parser->validate();
        if (!empty($errors)) {
            $this->error('Schema validation failed:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
            return 1;
        }

        $this->info('AI-Native Laravel Generator');
        $this->info('Project: ' . $this->parser->getProjectName());
        $this->line('');

        $onlyComponents = $this->option('only') ? explode(',', $this->option('only')) : null;
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Auto-configure environment based on schema
        if (!$dryRun) {
            $this->configureEnvironmentFromSchema();
        }

        // Generate components
        if ($this->shouldGenerate('models', $onlyComponents)) {
            $this->generateModels($dryRun, $force);
        }

        if ($this->shouldGenerate('migrations', $onlyComponents)) {
            $this->generateMigrations($dryRun, $force);
        }

        if ($this->shouldGenerate('controllers', $onlyComponents)) {
            $this->generateControllers($dryRun, $force);
        }

        if ($this->shouldGenerate('factories', $onlyComponents)) {
            $this->generateFactories($dryRun, $force);
        }

        if ($this->shouldGenerate('seeders', $onlyComponents)) {
            $this->generateSeeders($dryRun, $force);
        }

        if ($this->shouldGenerate('policies', $onlyComponents)) {
            $this->generatePolicies($dryRun, $force);
        }

        if ($this->shouldGenerate('observers', $onlyComponents)) {
            $this->generateObservers($dryRun, $force);
        }

        if ($this->shouldGenerate('routes', $onlyComponents)) {
            $this->generateRoutes($dryRun, $force);
        }

        // Generate pivot tables
        $this->generatePivotTables($dryRun, $force);

        // Generate custom routes
        $this->generateCustomRoutes($dryRun, $force);

        // Generate service providers for policies and observers
        if (!$dryRun) {
            $this->generateServiceProviders($force);
        }

        if ($dryRun) {
            $this->info('Dry run completed. No files were created.');
        } else {
            $this->info('Generation completed successfully!');
            $this->showNextSteps();
        }

        return 0;
    }

    protected function generateModels(bool $dryRun, bool $force): void
    {
        $this->info('Generating Models...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $fileName = Str::studly($modelName) . '.php';
            $filePath = app_path("Models/{$fileName}");
            
            if ($this->files->exists($filePath) && !$force) {
                $this->warn("  Model {$modelName} already exists (use --force to overwrite)");
                continue;
            }
            
            $content = $this->modelGenerator->generate($modelName, $this->parser);
            
            if ($dryRun) {
                $this->line("  Would create: {$filePath}");
            } else {
                $this->ensureDirectoryExists(dirname($filePath));
                $this->files->put($filePath, $content);
                $this->info("  Created: Models/{$fileName}");
            }
        }
    }

    protected function generateMigrations(bool $dryRun, bool $force): void
    {
        $this->info('Generating Migrations...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $tableName = $this->parser->getModelTable($modelName);
            
            // Skip Laravel's default tables unless forced
            if (in_array($tableName, ['users', 'password_resets', 'failed_jobs', 'personal_access_tokens'])) {
                if ($tableName === 'users') {
                    // For users table, always modify existing instead of creating new
                    $this->modifyExistingUsersTable($modelName, $dryRun);
                } else {
                    if (!$force) {
                        $this->warn("  Skipping Laravel default table: {$tableName} (use --force to overwrite)");
                    }
                }
                continue;
            }
            
            $timestamp = now()->format('Y_m_d_His') . rand(10, 99);
            $fileName = "{$timestamp}_create_{$tableName}_table.php";
            $filePath = database_path("migrations/{$fileName}");
            
            // Check if migration already exists
            $existingMigrations = glob(database_path("migrations/*_create_{$tableName}_table.php"));
            if (!empty($existingMigrations) && !$force) {
                $this->warn("  Migration for {$tableName} already exists (use --force to overwrite)");
                continue;
            }
            
            $content = $this->migrationGenerator->generate($modelName, $this->parser);
            
            if ($dryRun) {
                $this->line("  Would create: {$filePath}");
            } else {
                if (!empty($existingMigrations) && $force) {
                    // Remove old migration
                    foreach ($existingMigrations as $oldMigration) {
                        $this->files->delete($oldMigration);
                    }
                }
                
                $this->files->put($filePath, $content);
                $this->info("  Created: migrations/{$fileName}");
            }
        }
    }
    
    protected function modifyExistingUsersTable(string $modelName, bool $dryRun): void
    {
        $model = $this->parser->getModel($modelName);
        $fields = $model['fields'] ?? [];
        
        // Generate modification migration for users table
        $timestamp = now()->format('Y_m_d_His') . rand(10, 99);
        $fileName = "{$timestamp}_modify_users_table.php";
        $filePath = database_path("migrations/{$fileName}");
        
        $content = $this->migrationGenerator->generateModifyUsersTable($modelName, $this->parser);
        
        if ($dryRun) {
            $this->line("  Would create: {$filePath}");
        } else {
            $this->files->put($filePath, $content);
            $this->info("  Created: migrations/{$fileName} (modify existing users table)");
        }
    }

    protected function generateControllers(bool $dryRun, bool $force): void
    {
        $this->info('Generating Controllers...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $routes = $this->parser->getModelRoutes($modelName);
            if (empty($routes)) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Controller.php';
            $filePath = app_path("Http/Controllers/{$fileName}");
            
            if ($this->files->exists($filePath) && !$force) {
                $this->warn("  Controller {$modelName}Controller already exists (use --force to overwrite)");
                continue;
            }
            
            $content = $this->controllerGenerator->generate($modelName, $this->parser);
            
            if ($dryRun) {
                $this->line("  Would create: {$filePath}");
            } else {
                $this->ensureDirectoryExists(dirname($filePath));
                $this->files->put($filePath, $content);
                $this->info("  Created: Http/Controllers/{$fileName}");
            }
        }
    }

    protected function generateRoutes(bool $dryRun, bool $force): void
    {
        $this->info('Generating API Routes...');
        
        $routesContent = $this->generateApiRoutesContent();
        $filePath = base_path('routes/api.php');
        
        if ($dryRun) {
            $this->line("  Would update: routes/api.php");
            $this->line("  Route preview:");
            $this->line($routesContent);
        } else {
            if ($this->files->exists($filePath)) {
                // Read existing content
                $existingContent = $this->files->get($filePath);
                
                // Check if AI-Native routes already exist
                if (strpos($existingContent, '// AI-Native Generated Routes') !== false) {
                    if ($force) {
                        // Replace existing AI-Native routes
                        $pattern = '/\/\/ AI-Native Generated Routes.*?(?=\/\*|$)/s';
                        $newContent = preg_replace($pattern, $this->getGeneratedRoutesSection(), $existingContent);
                        $this->files->put($filePath, $newContent);
                        $this->info("  Updated existing AI-Native routes in: routes/api.php");
                    } else {
                        $this->warn("  AI-Native routes already exist (use --force to overwrite)");
                        return;
                    }
                } else {
                    // Append to existing file
                    $newContent = $existingContent . "\n\n" . $this->getGeneratedRoutesSection();
                    $this->files->put($filePath, $newContent);
                    $this->info("  Added AI-Native routes to: routes/api.php");
                }
            } else {
                // Create new file
                $this->files->put($filePath, $routesContent);
                $this->info("  Created: routes/api.php");
            }
        }
    }

    protected function generatePivotTables(bool $dryRun, bool $force): void
    {
        $pivots = $this->parser->getPivots();
        
        if (empty($pivots)) {
            return;
        }
        
        $this->info('Generating Pivot Table Migrations...');
        
        foreach ($pivots as $pivotName => $pivotConfig) {
            $timestamp = now()->format('Y_m_d_His') . rand(10, 99);
            $fileName = "{$timestamp}_create_{$pivotName}_table.php";
            $filePath = database_path("migrations/{$fileName}");
            
            $existingMigrations = glob(database_path("migrations/*_create_{$pivotName}_table.php"));
            if (!empty($existingMigrations) && !$force) {
                $this->warn("  Pivot migration for {$pivotName} already exists");
                continue;
            }
            
            $content = $this->migrationGenerator->generatePivotMigration($pivotName, $pivotConfig);
            
            if ($dryRun) {
                $this->line("  Would create: {$filePath}");
            } else {
                $this->files->put($filePath, $content);
                $this->info("  Created: migrations/{$fileName}");
            }
        }
    }

    protected function generateCustomRoutes(bool $dryRun, bool $force): void
    {
        $customRoutes = $this->parser->getCustomRoutes();
        
        if (empty($customRoutes)) {
            return;
        }
        
        $this->info('Custom routes found in schema. Please add them manually to your routes file.');
        
        foreach ($customRoutes as $route) {
            $method = strtoupper($route['method']);
            $uri = $route['uri'];
            $controller = $route['controller'];
            $this->line("  {$method} {$uri} -> {$controller}");
        }
    }

    protected function generateApiRoutesContent(): string
    {
        $routes = [];
        $middlewareConfig = $this->parser->getMiddlewareConfig();
        $globalMiddlewares = $this->parser->getGlobalMiddlewares();
        
        $routes[] = "<?php";
        $routes[] = "";
        $routes[] = "use Illuminate\Support\Facades\Route;";
        
        // Add controller imports
        foreach ($this->parser->getModelNames() as $modelName) {
            $controllerName = Str::studly($modelName) . 'Controller';
            $routes[] = "use App\\Http\\Controllers\\{$controllerName};";
        }
        
        $routes[] = "";
        $routes[] = "// AI-Native Generated Routes";
        
        if (!empty($globalMiddlewares)) {
            $middlewareString = "'" . implode("', '", $globalMiddlewares) . "'";
            $routes[] = "Route::middleware([{$middlewareString}])->group(function () {";
        } else {
            $routes[] = "Route::group(function () {";
        }
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $modelRoutes = $this->parser->getModelRoutes($modelName);
            if (empty($modelRoutes)) {
                continue;
            }
            
            $controllerName = Str::studly($modelName) . 'Controller';
            $resourceName = Str::plural(Str::snake($modelName, '-'));
            
            $routes[] = "    // {$modelName} routes";
            
            foreach ($modelRoutes as $route) {
                switch ($route) {
                    case 'list':
                    case 'index':
                        $routes[] = "    Route::get('{$resourceName}', [{$controllerName}::class, 'index']);";
                        break;
                    case 'show':
                        $routes[] = "    Route::get('{$resourceName}/{{$modelName}}', [{$controllerName}::class, 'show']);";
                        break;
                    case 'create':
                    case 'store':
                        $routes[] = "    Route::post('{$resourceName}', [{$controllerName}::class, 'store']);";
                        break;
                    case 'update':
                        $routes[] = "    Route::put('{$resourceName}/{{$modelName}}', [{$controllerName}::class, 'update']);";
                        break;
                    case 'delete':
                    case 'destroy':
                        $routes[] = "    Route::delete('{$resourceName}/{{$modelName}}', [{$controllerName}::class, 'destroy']);";
                        break;
                }
            }
            
            $routes[] = "";
        }
        
        $routes[] = "});";
        
        return implode("\n", $routes);
    }

    protected function getGeneratedRoutesSection(): string
    {
        $routes = [];
        $globalMiddlewares = $this->parser->getGlobalMiddlewares();
        
        // Add controller imports
        foreach ($this->parser->getModelNames() as $modelName) {
            $controllerName = Str::studly($modelName) . 'Controller';
            $routes[] = "use App\\Http\\Controllers\\{$controllerName};";
        }
        
        $routes[] = "";
        $routes[] = "// AI-Native Generated Routes";
        
        if (!empty($globalMiddlewares)) {
            $middlewareString = "'" . implode("', '", $globalMiddlewares) . "'";
            $routes[] = "Route::middleware([{$middlewareString}])->group(function () {";
        } else {
            $routes[] = "Route::group(function () {";
        }
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $modelRoutes = $this->parser->getModelRoutes($modelName);
            if (empty($modelRoutes)) {
                continue;
            }
            
            $controllerName = Str::studly($modelName) . 'Controller';
            $resourceName = Str::plural(Str::snake($modelName, '-'));
            
            $routes[] = "    // {$modelName} routes";
            
            foreach ($modelRoutes as $route) {
                switch ($route) {
                    case 'list':
                    case 'index':
                        $routes[] = "    Route::get('{$resourceName}', [{$controllerName}::class, 'index']);";
                        break;
                    case 'show':
                        $routes[] = "    Route::get('{$resourceName}/{{$modelName}}', [{$controllerName}::class, 'show']);";
                        break;
                    case 'create':
                    case 'store':
                        $routes[] = "    Route::post('{$resourceName}', [{$controllerName}::class, 'store']);";
                        break;
                    case 'update':
                        $routes[] = "    Route::put('{$resourceName}/{{$modelName}}', [{$controllerName}::class, 'update']);";
                        break;
                    case 'delete':
                    case 'destroy':
                        $routes[] = "    Route::delete('{$resourceName}/{{$modelName}}', [{$controllerName}::class, 'destroy']);";
                        break;
                }
            }
            
            $routes[] = "";
        }
        
        $routes[] = "});";
        
        return implode("\n", $routes);
    }

    protected function shouldGenerate(string $component, ?array $onlyComponents): bool
    {
        return $onlyComponents === null || in_array($component, $onlyComponents);
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    protected function suggestRouteServiceProviderUpdate(): void
    {
        // No longer needed since we use api.php directly
    }

    protected function configureEnvironmentFromSchema(): void
    {
        $this->info('Auto-configuring environment from schema...');
        
        $schema = $this->parser->getSchema();
        
        // Configure app settings if specified
        if (isset($schema['meta']['app'])) {
            $this->envManager->setAppConfig($schema['meta']['app']);
            $this->info('  Application configuration updated');
        }
        
        // Use project name from meta for APP_NAME if not set in app config
        if (isset($schema['meta']['project']) && !isset($schema['meta']['app']['name'])) {
            $this->envManager->setAppConfig(['name' => $schema['meta']['project']]);
            $this->info('  Application name set to: ' . $schema['meta']['project']);
        }

        // Configure database if specified
        if (isset($schema['meta']['database'])) {
            $this->envManager->setDatabaseConfig($schema['meta']['database']);
            $this->info('  Database configuration updated');
        }
        
        // Configure mail if specified
        if (isset($schema['meta']['mail'])) {
            $this->envManager->setMailConfig($schema['meta']['mail']);
            $this->info('  Mail configuration updated');
        }
        
        // Configure cache if specified
        if (isset($schema['meta']['cache'])) {
            $this->envManager->setCacheConfig($schema['meta']['cache']);
            $this->info('  Cache configuration updated');
        }
        
        // Configure queues if specified
        if (isset($schema['meta']['queues'])) {
            $this->envManager->setQueueConfig($schema['meta']['queues']);
            $this->info('  Queue configuration updated');
        }
        
        // Configure auth if specified
        if (isset($schema['meta']['auth'])) {
            $this->envManager->setAuthConfig($schema['meta']['auth']);
            
            // Publish Sanctum config if needed
            if (($schema['meta']['auth']['provider'] ?? null) === 'sanctum') {
                $this->configManager->publishSanctumConfig();
            }
            $this->info('  Authentication configuration updated');
        }
        
        // Configure CORS if specified
        if (isset($schema['meta']['cors'])) {
            $this->configManager->updateCorsConfig($schema['meta']['cors']);
            $this->info('  CORS configuration updated');
        }
        
        // Configure storage disks if specified
        if (isset($schema['storage']['disks'])) {
            $this->configManager->updateFilesystemConfig($schema['storage']);
            $this->createStorageDirectories($schema['storage']['disks']);
            $this->info('  Storage configuration updated');
        }
    }
    
    protected function createStorageDirectories(array $disks): void
    {
        foreach ($disks as $disk => $driver) {
            if ($driver === 'public') {
                $path = storage_path("app/public/{$disk}");
            } else {
                $path = storage_path("app/private/{$disk}");
            }
            
            if (!$this->files->isDirectory($path)) {
                $this->files->makeDirectory($path, 0755, true);
            }
        }
    }

    protected function generateFactories(bool $dryRun, bool $force): void
    {
        $this->info('Generating Model Factories...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (!isset($model['factory'])) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Factory.php';
            $filePath = database_path("factories/{$fileName}");
            
            if ($this->files->exists($filePath) && !$force) {
                $this->warn("  Factory {$modelName}Factory already exists (use --force to overwrite)");
                continue;
            }
            
            $content = $this->factoryGenerator->generate($modelName, $this->parser);
            
            if ($dryRun) {
                $this->line("  Would create: {$filePath}");
            } else {
                $this->ensureDirectoryExists(dirname($filePath));
                $this->files->put($filePath, $content);
                $this->info("  Created: factories/{$fileName}");
            }
        }
    }

    protected function generateSeeders(bool $dryRun, bool $force): void
    {
        $this->info('Generating Seeders...');
        
        // Generate individual model seeders
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (!isset($model['seeder']) || $model['seeder'] !== true) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Seeder.php';
            $filePath = database_path("seeders/{$fileName}");
            
            if ($this->files->exists($filePath) && !$force) {
                $this->warn("  Seeder {$modelName}Seeder already exists (use --force to overwrite)");
                continue;
            }
            
            $content = $this->seederGenerator->generate($modelName, $this->parser);
            
            if ($dryRun) {
                $this->line("  Would create: {$filePath}");
            } else {
                $this->ensureDirectoryExists(dirname($filePath));
                $this->files->put($filePath, $content);
                $this->info("  Created: seeders/{$fileName}");
            }
        }
        
        // Generate/Update DatabaseSeeder
        if (!$dryRun) {
            $this->updateDatabaseSeeder($force);
        }
    }

    protected function generatePolicies(bool $dryRun, bool $force): void
    {
        $this->info('Generating Policies...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (!isset($model['policies']) || empty($model['policies'])) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Policy.php';
            $filePath = app_path("Policies/{$fileName}");
            
            if ($this->files->exists($filePath) && !$force) {
                $this->warn("  Policy {$modelName}Policy already exists (use --force to overwrite)");
                continue;
            }
            
            $content = $this->policyGenerator->generate($modelName, $this->parser);
            
            if ($dryRun) {
                $this->line("  Would create: {$filePath}");
            } else {
                $this->ensureDirectoryExists(dirname($filePath));
                $this->files->put($filePath, $content);
                $this->info("  Created: Policies/{$fileName}");
            }
        }
    }

    protected function generateObservers(bool $dryRun, bool $force): void
    {
        $this->info('Generating Observers...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (!isset($model['observers']) || empty($model['observers'])) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Observer.php';
            $filePath = app_path("Observers/{$fileName}");
            
            if ($this->files->exists($filePath) && !$force) {
                $this->warn("  Observer {$modelName}Observer already exists (use --force to overwrite)");
                continue;
            }
            
            $content = $this->observerGenerator->generate($modelName, $this->parser);
            
            if ($dryRun) {
                $this->line("  Would create: {$filePath}");
            } else {
                $this->ensureDirectoryExists(dirname($filePath));
                $this->files->put($filePath, $content);
                $this->info("  Created: Observers/{$fileName}");
            }
        }
    }

    protected function updateDatabaseSeeder(bool $force): void
    {
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');
        
        // Check if there are any seeders to include
        $seedersToInclude = [];
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (isset($model['seeder']) && $model['seeder'] === true) {
                $seedersToInclude[] = Str::studly($modelName) . 'Seeder';
            }
        }
        
        if (empty($seedersToInclude)) {
            return;
        }
        
        if ($this->files->exists($databaseSeederPath)) {
            if ($force) {
                // Regenerate completely
                $content = $this->seederGenerator->generateDatabaseSeeder($this->parser);
                $this->files->put($databaseSeederPath, $content);
                $this->info("  Regenerated: seeders/DatabaseSeeder.php");
            } else {
                // Try to merge with existing
                $this->mergeWithExistingDatabaseSeeder($databaseSeederPath, $seedersToInclude);
            }
        } else {
            // Create new
            $content = $this->seederGenerator->generateDatabaseSeeder($this->parser);
            $this->files->put($databaseSeederPath, $content);
            $this->info("  Created: seeders/DatabaseSeeder.php");
        }
    }
    
    protected function mergeWithExistingDatabaseSeeder(string $filePath, array $seedersToInclude): void
    {
        $existingContent = $this->files->get($filePath);
        
        // Check if it already has AI-Native seeders
        if (strpos($existingContent, '// AI-Native Generated Seeders') !== false) {
            // Replace the AI-Native section
            $newSeedersSection = $this->generateSeedersSection($seedersToInclude);
            $pattern = '/\/\/ AI-Native Generated Seeders.*?(?=\s*\]\s*\)\s*;)/s';
            $newContent = preg_replace($pattern, $newSeedersSection, $existingContent);
            $this->files->put($filePath, $newContent);
            $this->info("  Updated AI-Native seeders in: seeders/DatabaseSeeder.php");
        } else {
            // Add AI-Native seeders to existing call array
            $newSeedersSection = $this->generateSeedersSection($seedersToInclude);
            
            // Find the $this->call([ section and add our seeders
            if (preg_match('/(\$this->call\(\[\s*)(.*?)(\s*\]\);)/s', $existingContent, $matches)) {
                $beforeCall = $matches[1];
                $existingCalls = trim($matches[2]);
                $afterCall = $matches[3];
                
                $newCalls = $existingCalls;
                if (!empty($existingCalls) && !str_ends_with($existingCalls, ',')) {
                    $newCalls .= ',';
                }
                if (!empty($newCalls)) {
                    $newCalls .= "\n\n";
                }
                $newCalls .= $newSeedersSection;
                
                $newContent = str_replace($matches[0], $beforeCall . $newCalls . $afterCall, $existingContent);
                $this->files->put($filePath, $newContent);
                $this->info("  Added AI-Native seeders to: seeders/DatabaseSeeder.php");
            }
        }
    }
    
    protected function generateSeedersSection(array $seedersToInclude): string
    {
        $lines = [];
        $lines[] = "            // AI-Native Generated Seeders";
        foreach ($seedersToInclude as $seederClass) {
            $lines[] = "            {$seederClass}::class,";
        }
        return implode("\n", $lines);
    }

    protected function generateServiceProviders(bool $force): void
    {
        // Generate AuthServiceProvider for policies
        $hasAnyPolicies = false;
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (isset($model['policies']) && !empty($model['policies'])) {
                $hasAnyPolicies = true;
                break;
            }
        }
        
        if ($hasAnyPolicies) {
            $authServiceProviderPath = app_path('Providers/AuthServiceProvider.php');
            if (!$this->files->exists($authServiceProviderPath) || $force) {
                $content = $this->policyGenerator->generatePolicyServiceProvider($this->parser);
                $this->files->put($authServiceProviderPath, $content);
                $this->info('  Updated: Providers/AuthServiceProvider.php');
            }
        }
        
        // Generate ObserverServiceProvider for observers
        $hasAnyObservers = false;
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (isset($model['observers']) && !empty($model['observers'])) {
                $hasAnyObservers = true;
                break;
            }
        }
        
        if ($hasAnyObservers) {
            $observerServiceProviderPath = app_path('Providers/ObserverServiceProvider.php');
            if (!$this->files->exists($observerServiceProviderPath) || $force) {
                $content = $this->observerGenerator->generateObserverServiceProvider($this->parser);
                $this->files->put($observerServiceProviderPath, $content);
                $this->info('  Created: Providers/ObserverServiceProvider.php');
            }
        }
    }

    protected function showNextSteps(): void
    {
        $this->info('');
        $this->info('Next steps:');
        $this->line('1. Run migrations: php artisan migrate');
        
        // Check for seeders
        $hasAnySeeder = false;
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (isset($model['seeder']) && $model['seeder'] === true) {
                $hasAnySeeder = true;
                break;
            }
        }
        
        if ($hasAnySeeder) {
            $this->line('2. Run seeders: php artisan db:seed');
        }
        
        $authConfig = $this->parser->getAuthConfig();
        if ($authConfig['enabled'] ?? false) {
            $this->line('3. Install Sanctum: php artisan sanctum:install');
            $this->line('4. Publish Sanctum migrations: php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"');
        }
        
        // Check for observers service provider
        $hasAnyObservers = false;
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (isset($model['observers']) && !empty($model['observers'])) {
                $hasAnyObservers = true;
                break;
            }
        }
        
        if ($hasAnyObservers) {
            $this->line('5. Add ObserverServiceProvider to config/app.php providers array');
        }
        
        $this->line('6. Test your API endpoints');
        $this->info('');
        $this->info('Your environment has been auto-configured based on your schema!');
        $this->info('Generated components: Models, Migrations, Controllers, Routes, Factories, Seeders, Policies, Observers');
    }
}