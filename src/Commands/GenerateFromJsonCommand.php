<?php

/*
|--------------------------------------------------------------------------
| GenerateFromJsonCommand - AI-Native Laravel Generator
|--------------------------------------------------------------------------
| 
| RÉSUMÉ ARCHITECTURE (1013 lignes):
| Cette commande est le cœur du package ai-native-laravel. Elle parse un 
| schéma JSON et génère tous les composants Laravel nécessaires avec un 
| système intelligent de versioning et cleanup.
|
| FONCTIONNALITÉS PRINCIPALES:
| ✅ Génération complète: Models, Migrations, Controllers, Routes, Auth
| ✅ Système de manifest: Tracking et versioning de tous les fichiers
| ✅ Modes: --clean (défaut), --merge, --preview
| ✅ Auto-configuration: .env, config Laravel depuis le JSON
| ✅ Cleanup intelligent: Supprime uniquement les fichiers obsolètes
| ✅ Authentification: AuthController + routes Sanctum/basic auto
| ✅ Support fichiers: Upload/download endpoints automatiques
| ✅ Documentation: JSON_KEYWORDS.md avec tous les mots-clés
|
| WORKFLOW PRINCIPAL:
| 1. Parse et valide le JSON schema
| 2. Détecte les changements via hash comparison
| 3. Mode preview: affiche ce qui sera fait
| 4. Mode clean: nettoie les fichiers obsolètes
| 5. Génère tous les composants via 9 générateurs spécialisés  
| 6. Configure l'environnement Laravel automatiquement
| 7. Génère endpoints upload/download pour champs file/files automatiquement
| 8. Sauvegarde manifest + historique pour rollback
|
| MARQUES-PAGES NAVIGATION:
| @INIT       - Configuration et initialisation (lignes 80-124)
| @HANDLE     - Point d'entrée principal (lignes 126-248)  
| @MODELS     - Génération Models (lignes 250-271)
| @MIGRATIONS - Génération Migrations (lignes 273-336)
| @CONTROLLERS- Génération Controllers avec support fichiers (lignes 338-365)
| @ROUTES     - Génération Routes API + routes fichiers (lignes 367-618)
| @CONFIG     - Configuration environnement (lignes 630-711)
| @FACTORIES  - Génération Factories (lignes 713-738)
| @SEEDERS    - Génération Seeders (lignes 740-859)
| @POLICIES   - Génération Policies (lignes 771-796)
| @OBSERVERS  - Génération Observers (lignes 798-821)
| @AUTH       - Génération Auth components (lignes 909-975)
| @UTILS      - Utilitaires et helpers (lignes 977-1115)
|
| GÉNÉRATEURS UTILISÉS:
| • ModelGenerator: Modèles Eloquent avec relations, scopes, casts
| • MigrationGenerator: Migrations avec foreign keys, indexes
| • ControllerGenerator: Controllers API CRUD + endpoints upload/download
| • AuthGenerator: Controllers auth (login/register/logout)
| • FactoryGenerator: Model factories pour testing
| • SeederGenerator: Seeders avec ordre de dépendances
| • PolicyGenerator: Authorization policies
| • ObserverGenerator: Model observers pour lifecycle events
|
| HELPERS SYSTÈME:
| • ManifestManager: Tracking fichiers + versioning + cleanup
| • ConfigManager: Configuration fichiers Laravel
| • EnvManager: Gestion fichier .env automatique
|
*/

namespace AiNative\Laravel\Commands;

use AiNative\Laravel\Generators\ControllerGenerator;
use AiNative\Laravel\Generators\MigrationGenerator;
use AiNative\Laravel\Generators\ModelGenerator;
use AiNative\Laravel\Generators\FactoryGenerator;
use AiNative\Laravel\Generators\SeederGenerator;
use AiNative\Laravel\Generators\PolicyGenerator;
use AiNative\Laravel\Generators\ObserverGenerator;
use AiNative\Laravel\Generators\AuthGenerator;
use AiNative\Laravel\Parsers\SchemaParser;
use AiNative\Laravel\Helpers\ConfigManager;
use AiNative\Laravel\Helpers\EnvManager;
use AiNative\Laravel\Helpers\ManifestManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

// @INIT - Configuration et initialisation
class GenerateFromJsonCommand extends Command
{
    protected $signature = 'ai-native:generate 
                            {schema : The JSON schema file path}
                            {--only= : Generate only specific components (models,migrations,controllers,routes)}
                            {--clean : Clean previous generation and regenerate all (default)}
                            {--merge : Try to merge with existing files}
                            {--preview : Show what would be generated/cleaned without creating files}
                            {--dry-run : Alias for --preview}'

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
    protected AuthGenerator $authGenerator;
    protected ConfigManager $configManager;
    protected EnvManager $envManager;
    protected ManifestManager $manifestManager;

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
        $this->authGenerator = new AuthGenerator($files);
        $this->configManager = new ConfigManager();
        $this->envManager = new EnvManager();
        $this->manifestManager = new ManifestManager($files);
    }

    // @HANDLE - Point d'entrée principal avec workflow complet
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

        // Parse options with new system
        $onlyComponents = $this->option('only') ? explode(',', $this->option('only')) : null;
        $cleanMode = $this->option('clean') || (!$this->option('merge') && !$this->option('preview') && !$this->option('dry-run'));
        $mergeMode = $this->option('merge');
        $previewMode = $this->option('preview') || $this->option('dry-run');
        
        $schema = $this->parser->getSchema();
        
        // Check if schema has changed
        $hasChanged = $this->manifestManager->hasSchemaChanged($schema);
        
        if (!$hasChanged && !$previewMode) {
            $this->info('Schema unchanged since last generation. Use --clean to force regeneration.');
            return 0;
        }

        // Show what will happen
        if ($previewMode) {
            return $this->showPreview($schema, $onlyComponents, $cleanMode);
        }

        // Handle cleanup if in clean mode
        if ($cleanMode && $hasChanged) {
            $this->handleCleanup();
        }

        // Set the new schema in manifest
        $this->manifestManager->setSchema($schema);

        // Auto-configure environment based on schema
        $this->configureEnvironmentFromSchema();

        // Generate components
        if ($this->shouldGenerate('models', $onlyComponents)) {
            $this->generateModels($cleanMode);
        }

        if ($this->shouldGenerate('migrations', $onlyComponents)) {
            $this->generateMigrations($cleanMode);
        }

        if ($this->shouldGenerate('controllers', $onlyComponents)) {
            $this->generateControllers($cleanMode);
        }

        if ($this->shouldGenerate('factories', $onlyComponents)) {
            $this->generateFactories($cleanMode);
        }

        if ($this->shouldGenerate('seeders', $onlyComponents)) {
            $this->generateSeeders($cleanMode);
        }

        if ($this->shouldGenerate('policies', $onlyComponents)) {
            $this->generatePolicies($cleanMode);
        }

        if ($this->shouldGenerate('observers', $onlyComponents)) {
            $this->generateObservers($cleanMode);
        }

        if ($this->shouldGenerate('routes', $onlyComponents)) {
            $this->generateRoutes($cleanMode);
        }

        // Generate authentication controller and routes if auth is enabled
        $authConfig = $this->parser->getAuthConfig();
        if ($authConfig['enabled'] ?? false) {
            $this->generateAuthComponents($cleanMode);
        }

        // Generate pivot tables
        $this->generatePivotTables($cleanMode);

        // Generate custom routes
        $this->generateCustomRoutes($cleanMode);

        // Generate service providers for policies and observers
        $this->generateServiceProviders($cleanMode);

        // Remove welcome.blade.php if it exists
        $this->removeWelcomeFile();

        // Save manifest and history
        $this->manifestManager->save();
        $this->manifestManager->saveToHistory();

        $this->info('Generation completed successfully!');
        $this->showNextSteps();

        return 0;
    }

    // @MODELS - Génération des modèles Eloquent
    protected function generateModels(bool $cleanMode): void
    {
        $this->info('Generating Models...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $fileName = Str::studly($modelName) . '.php';
            $filePath = app_path("Models/{$fileName}");
            $relativePath = "app/Models/{$fileName}";
            
            $content = $this->modelGenerator->generate($modelName, $this->parser);
            
            $this->ensureDirectoryExists(dirname($filePath));
            $this->files->put($filePath, $content);
            $this->info("  Created: Models/{$fileName}");
            
            // Track in manifest
            $this->manifestManager->addGeneratedFile('models', $relativePath, [
                'model_name' => $modelName
            ]);
        }
    }

    // @MIGRATIONS - Génération des migrations avec gestion des tables existantes
    protected function generateMigrations(bool $cleanMode): void
    {
        $this->info('Generating Migrations...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $tableName = $this->parser->getModelTable($modelName);
            
            // Skip Laravel's default tables unless it's users
            if (in_array($tableName, ['users', 'password_resets', 'failed_jobs', 'personal_access_tokens'])) {
                if ($tableName === 'users') {
                    // For users table, always modify existing instead of creating new
                    $this->modifyExistingUsersTable($modelName);
                }
                continue;
            }
            
            // Clean up existing migrations for this table
            $existingMigrations = glob(database_path("migrations/*_create_{$tableName}_table.php"));
            foreach ($existingMigrations as $oldMigration) {
                $this->files->delete($oldMigration);
            }
            
            $timestamp = now()->format('Y_m_d_His') . rand(10, 99);
            $fileName = "{$timestamp}_create_{$tableName}_table.php";
            $filePath = database_path("migrations/{$fileName}");
            $relativePath = "database/migrations/{$fileName}";
            
            $content = $this->migrationGenerator->generate($modelName, $this->parser);
            
            $this->files->put($filePath, $content);
            $this->info("  Created: migrations/{$fileName}");
            
            // Track in manifest
            $this->manifestManager->addGeneratedFile('migrations', $relativePath, [
                'table_name' => $tableName,
                'model_name' => $modelName
            ]);
        }
    }
    
    protected function modifyExistingUsersTable(string $modelName): void
    {
        $model = $this->parser->getModel($modelName);
        $fields = $model['fields'] ?? [];
        
        // Generate modification migration for users table
        $timestamp = now()->format('Y_m_d_His') . rand(10, 99);
        $fileName = "{$timestamp}_modify_users_table.php";
        $filePath = database_path("migrations/{$fileName}");
        
        $content = $this->migrationGenerator->generateModifyUsersTable($modelName, $this->parser);
        $relativePath = "database/migrations/{$fileName}";
        
        $this->files->put($filePath, $content);
        $this->info("  Created: migrations/{$fileName} (modify existing users table)");
        
        // Track in manifest
        $this->manifestManager->addGeneratedFile('migrations', $relativePath, [
            'table_name' => 'users',
            'model_name' => $modelName,
            'type' => 'modify'
        ]);
    }

    // @CONTROLLERS - Génération des contrôleurs API avec CRUD
    protected function generateControllers(bool $cleanMode): void
    {
        $this->info('Generating Controllers...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $routes = $this->parser->getModelRoutes($modelName);
            if (empty($routes)) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Controller.php';
            $filePath = app_path("Http/Controllers/{$fileName}");
            $relativePath = "app/Http/Controllers/{$fileName}";
            
            $content = $this->controllerGenerator->generate($modelName, $this->parser);
            
            $this->ensureDirectoryExists(dirname($filePath));
            $this->files->put($filePath, $content);
            $this->info("  Created: Http/Controllers/{$fileName}");
            
            // Track in manifest
            $this->manifestManager->addGeneratedFile('controllers', $relativePath, [
                'model_name' => $modelName,
                'routes' => $routes
            ]);
        }
    }

    // @ROUTES - Génération des routes API avec gestion intelligente
    protected function generateRoutes(bool $cleanMode): void
    {
        $this->info('Generating API Routes...');
        
        $routesContent = $this->generateApiRoutesContent();
        $filePath = base_path('routes/api.php');
        $relativePath = "routes/api.php";
        
            // Check if AI-Native routes already exist and replace them
            if (strpos($existingContent, '// AI-Native Generated Routes') !== false) {
                // Replace existing AI-Native routes
                $pattern = '/\/\/ AI-Native Generated Routes.*?(?=\/\*|$)/s';
                $newContent = preg_replace($pattern, $this->getGeneratedRoutesSection(), $existingContent);
                $this->files->put($filePath, $newContent);
                $this->info("  Updated existing AI-Native routes in: routes/api.php");
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
        
        // Track in manifest
        $this->manifestManager->addGeneratedFile('routes', $relativePath, [
            'modified' => true,
            'section' => 'AI-Native Generated Routes'
        ]);
    }

    protected function generatePivotTables(bool $cleanMode): void
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
            if (!empty($existingMigrations) ) {
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

    protected function generateCustomRoutes(bool $cleanMode): void
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
            
            // Add file upload/download routes if model has file fields
            $this->addFileRoutesToGeneration($routes, $modelName, $resourceName, $controllerName);
            
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
            
            // Add file upload/download routes if model has file fields
            $this->addFileRoutesToGeneration($routes, $modelName, $resourceName, $controllerName);
            
            $routes[] = "";
        }
        
        $routes[] = "});";
        
        return implode("\n", $routes);
    }

    protected function addFileRoutesToGeneration(array &$routes, string $modelName, string $resourceName, string $controllerName): void
    {
        $model = $this->parser->getModel($modelName);
        $fields = $model['fields'] ?? [];
        $hasFileFields = false;
        $fileFields = [];

        foreach ($fields as $fieldName => $fieldDefinition) {
            $fieldType = $this->parser->parseFieldType($fieldName, $fieldDefinition);
            if (isset($fieldType['is_file']) && $fieldType['is_file']) {
                $hasFileFields = true;
                $fileFields[] = $fieldName;
            }
        }

        if ($hasFileFields) {
            $routes[] = "    // File upload/download routes for {$modelName}";
            
            foreach ($fileFields as $fieldName) {
                $methodName = Str::studly($fieldName);
                $routes[] = "    Route::post('{$resourceName}/{{$modelName}}/upload/{$fieldName}', [{$controllerName}::class, 'upload{$methodName}']);";
                $routes[] = "    Route::get('{$resourceName}/{{$modelName}}/download/{$fieldName}', [{$controllerName}::class, 'download{$methodName}']);";
            }
        }
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

    // @CONFIG - Configuration automatique environnement Laravel depuis JSON
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

    // @FACTORIES - Génération des factories pour testing
    protected function generateFactories(bool $cleanMode): void
    {
        $this->info('Generating Model Factories...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (!isset($model['factory'])) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Factory.php';
            $filePath = database_path("factories/{$fileName}");
            
            
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

    // @SEEDERS - Génération des seeders avec ordre de dépendances
    protected function generateSeeders(bool $cleanMode): void
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
        $this->updateDatabaseSeeder();
    }

    // @POLICIES - Génération des policies d'autorisation
    protected function generatePolicies(bool $cleanMode): void
    {
        $this->info('Generating Policies...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (!isset($model['policies']) || empty($model['policies'])) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Policy.php';
            $filePath = app_path("Policies/{$fileName}");
            
            
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

    // @OBSERVERS - Génération des observers pour lifecycle events
    protected function generateObservers(bool $cleanMode): void
    {
        $this->info('Generating Observers...');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $model = $this->parser->getModel($modelName);
            if (!isset($model['observers']) || empty($model['observers'])) {
                continue;
            }
            
            $fileName = Str::studly($modelName) . 'Observer.php';
            $filePath = app_path("Observers/{$fileName}");
            
            
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

    protected function updateDatabaseSeeder(): void
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
            // Always regenerate completely in clean mode
            $content = $this->seederGenerator->generateDatabaseSeeder($this->parser);
            $this->files->put($databaseSeederPath, $content);
            $this->info("  Regenerated: seeders/DatabaseSeeder.php");
        } else {
            // Create new
            $content = $this->seederGenerator->generateDatabaseSeeder($this->parser);
            $this->files->put($databaseSeederPath, $content);
            $this->info("  Created: seeders/DatabaseSeeder.php");
        }
        
        // Track in manifest
        $this->manifestManager->addGeneratedFile('seeders', 'database/seeders/DatabaseSeeder.php', [
            'modified' => true,
            'seeders_included' => $seedersToInclude
        ]);
    }
    
    // Service providers pour policies et observers
    protected function generateServiceProviders(bool $cleanMode): void
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
            $content = $this->policyGenerator->generatePolicyServiceProvider($this->parser);
            $this->files->put($authServiceProviderPath, $content);
            $this->info('  Updated: Providers/AuthServiceProvider.php');
            
            // Track in manifest
            $this->manifestManager->addGeneratedFile('config', 'app/Providers/AuthServiceProvider.php', [
                'modified' => true
            ]);
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
            $content = $this->observerGenerator->generateObserverServiceProvider($this->parser);
            $this->files->put($observerServiceProviderPath, $content);
            $this->info('  Created: Providers/ObserverServiceProvider.php');
            
            // Track in manifest
            $this->manifestManager->addGeneratedFile('config', 'app/Providers/ObserverServiceProvider.php', [
                'modified' => true
            ]);
        }
    }

    // @AUTH - Génération des composants d'authentification (AuthController + routes)
    protected function generateAuthComponents(bool $cleanMode): void
    {
        $this->info('Generating Authentication Components...');
        
        // Generate AuthController
        $fileName = 'AuthController.php';
        $filePath = app_path("Http/Controllers/{$fileName}");
        $relativePath = "app/Http/Controllers/{$fileName}";
        
        $content = $this->authGenerator->generateAuthController($this->parser);
        
        $this->ensureDirectoryExists(dirname($filePath));
        $this->files->put($filePath, $content);
        $this->info("  Created: Http/Controllers/{$fileName}");
        
        // Track in manifest
        $this->manifestManager->addGeneratedFile('controllers', $relativePath, [
            'type' => 'auth',
            'provider' => $this->parser->getAuthConfig()['provider'] ?? 'sanctum'
        ]);

        // Add authentication routes to api.php
        $this->addAuthRoutes();
    }

    protected function addAuthRoutes(): void
    {
        $routesPath = base_path('routes/api.php');
        $authRoutes = $this->authGenerator->generateAuthRoutes($this->parser);
        
        if ($this->files->exists($routesPath)) {
            $existingContent = $this->files->get($routesPath);
            
            // Check if auth routes already exist
            if (strpos($existingContent, 'AuthController') === false) {
                // Add import for AuthController
                $importLine = "use App\\Http\\Controllers\\AuthController;";
                if (strpos($existingContent, $importLine) === false) {
                    // Find the last use statement and add after it
                    $pattern = '/(use App\\\\Http\\\\Controllers\\\\[^;]+;)/';
                    if (preg_match_all($pattern, $existingContent, $matches)) {
                        $lastUse = end($matches[0]);
                        $existingContent = str_replace($lastUse, $lastUse . "\n" . $importLine, $existingContent);
                    } else {
                        // Add after the first use statement
                        $existingContent = preg_replace('/^use /', $importLine . "\n\nuse ", $existingContent, 1);
                    }
                }
                
                // Add auth routes at the top, before other routes
                $routesSection = "\n// AI-Native Authentication Routes\n" . $authRoutes . "\n\n";
                
                // Find the first Route:: declaration and add before it
                if (preg_match('/^(.*?)(\n\/\/ AI-Native Generated Routes|Route::)/s', $existingContent, $matches)) {
                    $beforeRoutes = $matches[1];
                    $restOfContent = substr($existingContent, strlen($beforeRoutes));
                    $newContent = $beforeRoutes . $routesSection . $restOfContent;
                } else {
                    $newContent = $existingContent . $routesSection;
                }
                
                $this->files->put($routesPath, $newContent);
                $this->info("  Added authentication routes to: routes/api.php");
            }
        }
    }

    // @UTILS - Utilitaires: next steps, preview, cleanup, helpers
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

    protected function showPreview(array $schema, ?array $onlyComponents, bool $cleanMode): int
    {
        $this->info('Generation Preview');
        $this->line('================');
        
        if ($cleanMode) {
            $filesToCleanup = $this->manifestManager->getFilesToCleanup($schema);
            if (!empty($filesToCleanup)) {
                $this->warn('Files that would be DELETED:');
                foreach ($filesToCleanup as $file) {
                    $this->line("  - {$file}");
                }
                $this->line('');
            }
        }

        $this->info('Files that would be CREATED/UPDATED:');
        
        foreach ($this->parser->getModelNames() as $modelName) {
            $studlyName = ucfirst($modelName);
            $model = $this->parser->getModel($modelName);
            
            if ($this->shouldGenerate('models', $onlyComponents)) {
                $this->line("  + app/Models/{$studlyName}.php");
            }
            
            if ($this->shouldGenerate('controllers', $onlyComponents) && !empty($model['routes'])) {
                $this->line("  + app/Http/Controllers/{$studlyName}Controller.php");
            }
            
            if ($this->shouldGenerate('migrations', $onlyComponents)) {
                $tableName = $this->parser->getModelTable($modelName);
                $this->line("  + database/migrations/*_create_{$tableName}_table.php");
            }
            
            if ($this->shouldGenerate('factories', $onlyComponents) && isset($model['factory'])) {
                $this->line("  + database/factories/{$studlyName}Factory.php");
            }
            
            if ($this->shouldGenerate('seeders', $onlyComponents) && ($model['seeder'] ?? false)) {
                $this->line("  + database/seeders/{$studlyName}Seeder.php");
            }
            
            if ($this->shouldGenerate('policies', $onlyComponents) && !empty($model['policies'])) {
                $this->line("  + app/Policies/{$studlyName}Policy.php");
            }
            
            if ($this->shouldGenerate('observers', $onlyComponents) && !empty($model['observers'])) {
                $this->line("  + app/Observers/{$studlyName}Observer.php");
            }
        }

        if ($this->shouldGenerate('routes', $onlyComponents)) {
            $this->line("  ~ routes/api.php (AI-Native section)");
        }

        $this->line("  ~ .ai-native-manifest.json");
        $this->line("  + .ai-native/history/<timestamp>_<hash>.json");
        
        $this->line('');
        $this->info('Use --clean to execute this generation plan.');
        
        return 0;
    }

    protected function handleCleanup(): void
    {
        $this->info('Cleaning up obsolete files...');
        
        $deletedFiles = $this->manifestManager->cleanupObsoleteFiles();
        
        if (!empty($deletedFiles)) {
            foreach ($deletedFiles as $file) {
                $this->line("  Deleted: {$file}");
            }
        } else {
            $this->line('  No obsolete files found.');
        }
        
        $this->line('');
    }

    protected function removeWelcomeFile(): void
    {
        $welcomePath = resource_path('views/welcome.blade.php');
        
        if ($this->files->exists($welcomePath)) {
            $this->files->delete($welcomePath);
            $this->info('  Removed: resources/views/welcome.blade.php');
        }
    }
}