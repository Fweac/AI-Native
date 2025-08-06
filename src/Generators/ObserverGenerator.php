<?php

namespace AiNative\Laravel\Generators;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ObserverGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function generate(string $modelName, SchemaParser $parser): string
    {
        $model = $parser->getModel($modelName);
        $observers = $model['observers'] ?? [];
        
        $className = Str::studly($modelName) . 'Observer';
        $modelClass = Str::studly($modelName);
        $modelVariable = Str::camel($modelName);
        
        $content = [];
        $content[] = "<?php";
        $content[] = "";
        $content[] = "namespace App\\Observers;";
        $content[] = "";
        $content[] = "use App\\Models\\{$modelClass};";
        $content[] = "use Illuminate\\Support\\Facades\\Cache;";
        $content[] = "use Illuminate\\Support\\Facades\\Log;";
        $content[] = "use Illuminate\\Support\\Str;";
        $content[] = "";
        $content[] = "class {$className}";
        $content[] = "{";
        
        // Standard Laravel model events
        $standardEvents = [
            'retrieved', 'creating', 'created', 'updating', 'updated', 
            'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored'
        ];
        
        foreach ($standardEvents as $event) {
            if (isset($observers[$event])) {
                $this->addObserverMethod($content, $event, $observers[$event], $modelClass, $modelVariable);
            }
        }
        
        // Add custom observer methods
        foreach ($observers as $event => $actions) {
            if (!in_array($event, $standardEvents)) {
                $this->addObserverMethod($content, $event, $actions, $modelClass, $modelVariable);
            }
        }
        
        $content[] = "}";
        
        return implode("\n", $content);
    }

    protected function addObserverMethod(array &$content, string $event, mixed $actions, string $modelClass, string $modelVariable): void
    {
        $content[] = "    /**";
        $content[] = "     * Handle the {$modelClass} \"{$event}\" event.";
        $content[] = "     */";
        $content[] = "    public function {$event}({$modelClass} \${$modelVariable}): void";
        $content[] = "    {";
        
        if (is_string($actions)) {
            $this->addObserverAction($content, $actions, $modelVariable);
        } elseif (is_array($actions)) {
            foreach ($actions as $action) {
                $this->addObserverAction($content, $action, $modelVariable);
            }
        }
        
        $content[] = "    }";
        $content[] = "";
    }

    protected function addObserverAction(array &$content, string $action, string $modelVariable): void
    {
        switch ($action) {
            case 'generateUuid':
                $content[] = "        if (empty(\${$modelVariable}->id)) {";
                $content[] = "            \${$modelVariable}->id = Str::uuid();";
                $content[] = "        }";
                break;
                
            case 'clearCache':
                $content[] = "        Cache::tags(['{$modelVariable}s'])->flush();";
                break;
                
            case 'updateSearchIndex':
                $content[] = "        // Update search index";
                $content[] = "        // \${$modelVariable}->searchable();";
                break;
                
            case 'cleanupFiles':
                $content[] = "        if (\${$modelVariable}->isDirty('avatar') && \${$modelVariable}->getOriginal('avatar')) {";
                $content[] = "            \Storage::delete(\${$modelVariable}->getOriginal('avatar'));";
                $content[] = "        }";
                break;
                
            case 'logActivity':
                $content[] = "        Log::info('{$modelVariable} activity', [";
                $content[] = "            'model_id' => \${$modelVariable}->id,";
                $content[] = "            'model_type' => get_class(\${$modelVariable}),";
                $content[] = "            'event' => 'updated'";
                $content[] = "        ]);";
                break;
                
            case 'moveChildrenToParent':
                $content[] = "        if (\${$modelVariable}->children()->count() > 0) {";
                $content[] = "            \${$modelVariable}->children()->update([";
                $content[] = "                'parent_id' => \${$modelVariable}->parent_id";
                $content[] = "            ]);";
                $content[] = "        }";
                break;
                
            case 'clearProjectCache':
                $content[] = "        if (\${$modelVariable}->project) {";
                $content[] = "            Cache::tags(['project_' . \${$modelVariable}->project->id])->flush();";
                $content[] = "        }";
                break;
                
            case 'updateProjectProgress':
                $content[] = "        if (\${$modelVariable}->project) {";
                $content[] = "            \${$modelVariable}->project->updateProgress();";
                $content[] = "        }";
                break;
                
            case 'clearProjectsCache':
                $content[] = "        Cache::tags(['projects'])->flush();";
                break;
                
            default:
                // Custom action - generate method call
                $methodName = Str::camel($action);
                $content[] = "        \$this->{$methodName}(\${$modelVariable});";
                break;
        }
    }

    public function generateObserverServiceProvider(SchemaParser $parser): string
    {
        $content = [];
        $content[] = "<?php";
        $content[] = "";
        $content[] = "namespace App\\Providers;";
        $content[] = "";
        $content[] = "use Illuminate\\Support\\ServiceProvider;";
        $content[] = "";
        
        // Add model and observer imports
        $modelsWithObservers = [];
        foreach ($parser->getModelNames() as $modelName) {
            $model = $parser->getModel($modelName);
            if (isset($model['observers']) && !empty($model['observers'])) {
                $modelClass = Str::studly($modelName);
                $content[] = "use App\\Models\\{$modelClass};";
                $content[] = "use App\\Observers\\{$modelClass}Observer;";
                $modelsWithObservers[] = $modelName;
            }
        }
        
        $content[] = "";
        $content[] = "class ObserverServiceProvider extends ServiceProvider";
        $content[] = "{";
        $content[] = "    /**";
        $content[] = "     * Register services.";
        $content[] = "     */";
        $content[] = "    public function register(): void";
        $content[] = "    {";
        $content[] = "        //";
        $content[] = "    }";
        $content[] = "";
        $content[] = "    /**";
        $content[] = "     * Bootstrap services.";
        $content[] = "     */";
        $content[] = "    public function boot(): void";
        $content[] = "    {";
        
        foreach ($modelsWithObservers as $modelName) {
            $modelClass = Str::studly($modelName);
            $content[] = "        {$modelClass}::observe({$modelClass}Observer::class);";
        }
        
        $content[] = "    }";
        $content[] = "}";
        
        return implode("\n", $content);
    }

    protected function addCustomObserverMethods(array &$content, array $observers, string $modelVariable): void
    {
        $customMethods = [];
        
        foreach ($observers as $event => $actions) {
            if (is_string($actions)) {
                $actions = [$actions];
            }
            
            foreach ($actions as $action) {
                if (!in_array($action, ['generateUuid', 'clearCache', 'updateSearchIndex', 'cleanupFiles', 'logActivity', 'moveChildrenToParent', 'clearProjectCache', 'updateProjectProgress', 'clearProjectsCache'])) {
                    $customMethods[] = $action;
                }
            }
        }
        
        $customMethods = array_unique($customMethods);
        
        foreach ($customMethods as $method) {
            $methodName = Str::camel($method);
            $content[] = "    /**";
            $content[] = "     * Handle custom {$method} action.";
            $content[] = "     */";
            $content[] = "    protected function {$methodName}({$modelVariable} \${$modelVariable}): void";
            $content[] = "    {";
            $content[] = "        // TODO: Implement {$method} logic";
            $content[] = "        // Add your custom logic here";
            $content[] = "    }";
            $content[] = "";
        }
    }
}