<?php

namespace AiNative\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use AiNative\Laravel\Helpers\ConfigManager;
use AiNative\Laravel\Helpers\EnvManager;

class InstallCommand extends Command
{
    protected $signature = 'ai-native:install 
                            {--sanctum : Install Laravel Sanctum}
                            {--force : Overwrite existing files}';

    protected $description = 'Install AI-Native Laravel package dependencies and configuration';

    protected Filesystem $files;
    protected ConfigManager $configManager;
    protected EnvManager $envManager;

    public function __construct(Filesystem $files, ConfigManager $configManager, EnvManager $envManager)
    {
        parent::__construct();
        $this->files = $files;
        $this->configManager = $configManager;
        $this->envManager = $envManager;
    }

    public function handle(): int
    {
        $this->info('Installing AI-Native Laravel...');
        $this->line('');

        // Configure Laravel for API usage
        $this->configureApiRoutes();

        // Publish configuration
        $this->publishConfiguration();

        // Install Sanctum if requested or configure existing installation
        if ($this->option('sanctum') || $this->shouldInstallSanctum()) {
            $this->installSanctum();
        }

        // Configure basic environment
        $this->configureEnvironment();

        // Create directories and files
        $this->createNecessaryDirectories();

        // Create example schema
        $this->createExampleSchema();

        // Update gitignore
        $this->updateGitignore();

        $this->info('');
        $this->info('AI-Native Laravel installed successfully!');
        $this->showUsageInstructions();

        return 0;
    }

    protected function publishConfiguration(): void
    {
        $this->info('Publishing configuration...');

        // Create config file
        $configPath = config_path('ai-native.php');
        
        if ($this->files->exists($configPath) && !$this->option('force')) {
            $this->warn('  Configuration file already exists (use --force to overwrite)');
            return;
        }

        $configContent = $this->getConfigContent();
        $this->files->put($configPath, $configContent);
        $this->info('  Created: config/ai-native.php');
    }

    protected function installSanctum(): void
    {
        $this->info('Installing Laravel Sanctum...');

        // Check if Sanctum is already installed
        $composerPath = base_path('composer.json');
        $composer = json_decode($this->files->get($composerPath), true);
        
        if (isset($composer['require']['laravel/sanctum'])) {
            $this->warn('  Laravel Sanctum already installed');
            return;
        }

        // Install Sanctum via Composer
        $this->line('  Installing Sanctum via Composer...');
        exec('composer require laravel/sanctum', $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->error('  Failed to install Sanctum via Composer');
            return;
        }

        // Publish Sanctum configuration
        $this->call('vendor:publish', ['--provider' => 'Laravel\\Sanctum\\SanctumServiceProvider']);
        
        $this->info('  Sanctum installed successfully');
    }

    protected function createExampleSchema(): void
    {
        $this->info('Creating example schema...');

        $schemaPath = base_path('ai-native-schema.example.json');
        
        if ($this->files->exists($schemaPath) && !$this->option('force')) {
            $this->warn('  Example schema already exists');
            return;
        }

        $exampleSchema = $this->getExampleSchema();
        $this->files->put($schemaPath, $exampleSchema);
        $this->info('  Created: ai-native-schema.example.json');
    }

    protected function updateGitignore(): void
    {
        $gitignorePath = base_path('.gitignore');
        
        if (!$this->files->exists($gitignorePath)) {
            return;
        }

        $gitignoreContent = $this->files->get($gitignorePath);
        $aiNativeEntries = [
            '# AI-Native generated files',
            '/ai-native-schema.json',
            '/routes/ai-native-api.php'
        ];

        $needsUpdate = false;
        foreach ($aiNativeEntries as $entry) {
            if (strpos($gitignoreContent, $entry) === false) {
                $needsUpdate = true;
                break;
            }
        }

        if ($needsUpdate) {
            $gitignoreContent .= "\n" . implode("\n", $aiNativeEntries) . "\n";
            $this->files->put($gitignorePath, $gitignoreContent);
            $this->info('Updated .gitignore');
        }
    }

    protected function getConfigContent(): string
    {
        return <<<'CONFIG'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI-Native Default Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains default settings for the AI-Native
    | Laravel package. These settings can be overridden in your schema files.
    |
    */

    'defaults' => [
        'auth' => [
            'enabled' => true,
            'provider' => 'sanctum',
            'guards' => ['web', 'api'],
        ],

        'database' => [
            'connection' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'cache' => [
            'enabled' => true,
            'default_ttl' => 3600,
            'tags' => ['ai-native'],
        ],

        'pagination' => [
            'per_page' => 15,
            'max_per_page' => 100,
        ],

        'validation' => [
            'stop_on_first_failure' => false,
        ],
    ],

    'generators' => [
        'models' => [
            'namespace' => 'App\\Models',
            'path' => 'app/Models',
        ],

        'controllers' => [
            'namespace' => 'App\\Http\\Controllers',
            'path' => 'app/Http/Controllers',
        ],

        'migrations' => [
            'path' => 'database/migrations',
        ],

        'routes' => [
            'file' => 'routes/ai-native-api.php',
            'prefix' => 'api',
            'middleware' => ['api'],
        ],
    ],

    'storage' => [
        'default_disk' => 'public',
        'max_file_size' => '10MB',
        'allowed_extensions' => [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'svg'],
            'documents' => ['pdf', 'doc', 'docx', 'txt'],
            'archives' => ['zip', 'rar', '7z'],
        ],
    ],
];
CONFIG;
    }

    protected function getExampleSchema(): string
    {
        return <<<'SCHEMA'
{
  "meta": {
    "project": "BlogAPI",
    "version": "1.0.0",
    "description": "Simple blog API example",
    "auth": {
      "enabled": true,
      "provider": "sanctum"
    },
    "middlewares": [
      "auth:sanctum",
      "throttle:60,1"
    ]
  },
  "models": {
    "User": {
      "table": "users",
      "fields": {
        "name": "string|required|max:255",
        "email": "string|email|unique|required",
        "password": "string|required|min:8",
        "role": "enum:admin,author,reader|default:reader"
      },
      "relations": {
        "posts": "hasMany:Post"
      },
      "routes": ["list", "show", "create", "update"],
      "factory": {
        "count": 10
      },
      "seeder": true
    },
    "Post": {
      "table": "posts",
      "fields": {
        "user_id": "foreign:users|required",
        "title": "string|required|max:255",
        "slug": "string|unique|required",
        "content": "longText|required",
        "excerpt": "text|nullable",
        "published": "boolean|default:false",
        "published_at": "timestamp|nullable"
      },
      "relations": {
        "author": "belongsTo:User,user_id"
      },
      "routes": ["list", "show", "create", "update", "delete"],
      "scopes": {
        "published": "where:published,true"
      },
      "factory": {
        "count": 50
      },
      "seeder": true
    }
  }
}
SCHEMA;
    }

    protected function configureApiRoutes(): void
    {
        $this->info('Configuring API routes...');
        
        $this->configManager->ensureApiRoutesEnabled();
        $this->configManager->createApiRoutesFile();
        
        $this->info('  API routes configured successfully');
    }

    protected function shouldInstallSanctum(): bool
    {
        // Auto-install Sanctum if not already present
        $composerPath = base_path('composer.json');
        if (!$this->files->exists($composerPath)) {
            return false;
        }

        $composer = json_decode($this->files->get($composerPath), true);
        return !isset($composer['require']['laravel/sanctum']);
    }

    protected function configureEnvironment(): void
    {
        $this->info('Configuring environment...');
        
        // Set basic environment variables for AI-Native
        $envValues = [
            'AI_NATIVE_ENABLED' => true,
            'AI_NATIVE_CACHE_TTL' => 3600,
        ];

        $this->envManager->updateEnvValues($envValues);
        $this->info('  Environment configured successfully');
    }

    protected function createNecessaryDirectories(): void
    {
        $this->info('Creating necessary directories...');
        
        $directories = [
            storage_path('app/public'),
            storage_path('app/private'),
        ];

        foreach ($directories as $dir) {
            if (!$this->files->isDirectory($dir)) {
                $this->files->makeDirectory($dir, 0755, true);
                $this->info("  Created directory: {$dir}");
            }
        }
    }

    protected function showUsageInstructions(): void
    {
        $this->line('');
        $this->info('Usage Instructions:');
        $this->line('');
        $this->line('1. Create your schema file (you can start with the example):');
        $this->line('   cp ai-native-schema.example.json my-schema.json');
        $this->line('');
        $this->line('2. Generate your Laravel components:');
        $this->line('   php artisan ai-native:generate my-schema.json');
        $this->line('');
        $this->line('3. Run migrations:');
        $this->line('   php artisan migrate');
        $this->line('');
        $this->line('4. (Optional) Seed your database:');
        $this->line('   php artisan db:seed');
        $this->line('');
        $this->info('Available commands:');
        $this->line('  ai-native:generate   Generate components from schema');
        $this->line('  ai-native:validate   Validate a schema file');
        $this->line('  ai-native:install    Install package dependencies');
        $this->line('');
        $this->info('Your Laravel project is now configured for AI-Native development!');
        $this->info('The package will automatically configure your environment based on your schema.');
    }
}