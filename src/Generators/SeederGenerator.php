<?php

namespace AiNative\Laravel\Generators;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class SeederGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function generate(string $modelName, SchemaParser $parser): string
    {
        $model = $parser->getModel($modelName);
        $factory = $model['factory'] ?? [];
        $count = $factory['count'] ?? 10;
        
        $className = Str::studly($modelName) . 'Seeder';
        $modelClass = Str::studly($modelName);
        
        $content = [];
        $content[] = "<?php";
        $content[] = "";
        $content[] = "namespace Database\\Seeders;";
        $content[] = "";
        $content[] = "use App\\Models\\{$modelClass};";
        $content[] = "use Illuminate\\Database\\Seeder;";
        
        // Add related model imports for relationships
        $relations = $model['relations'] ?? [];
        $relatedModels = $this->getRelatedModels($relations, $parser);
        foreach ($relatedModels as $relatedModel) {
            $content[] = "use App\\Models\\{$relatedModel};";
        }
        
        $content[] = "";
        $content[] = "class {$className} extends Seeder";
        $content[] = "{";
        $content[] = "    /**";
        $content[] = "     * Run the database seeds.";
        $content[] = "     */";
        $content[] = "    public function run(): void";
        $content[] = "    {";
        
        // Create basic factory instances
        if ($this->hasSimpleRelationships($relations)) {
            $content[] = "        {$modelClass}::factory({$count})->create();";
        } else {
            // Handle complex relationships
            $this->addComplexSeeding($content, $modelName, $model, $parser, $count);
        }
        
        $content[] = "    }";
        $content[] = "}";
        
        return implode("\n", $content);
    }

    public function generateDatabaseSeeder(SchemaParser $parser): string
    {
        $content = [];
        $content[] = "<?php";
        $content[] = "";
        $content[] = "namespace Database\\Seeders;";
        $content[] = "";
        $content[] = "use Illuminate\\Database\\Seeder;";
        $content[] = "";
        $content[] = "class DatabaseSeeder extends Seeder";
        $content[] = "{";
        $content[] = "    /**";
        $content[] = "     * Seed the application's database.";
        $content[] = "     */";
        $content[] = "    public function run(): void";
        $content[] = "    {";
        $content[] = "        \$this->call([";
        
        // Add seeders in dependency order
        $orderedModels = $this->getModelsInDependencyOrder($parser);
        
        foreach ($orderedModels as $modelName) {
            $model = $parser->getModel($modelName);
            if (isset($model['seeder']) && $model['seeder'] === true) {
                $seederClass = Str::studly($modelName) . 'Seeder';
                $content[] = "            {$seederClass}::class,";
            }
        }
        
        $content[] = "        ]);";
        $content[] = "    }";
        $content[] = "}";
        
        return implode("\n", $content);
    }

    protected function getRelatedModels(array $relations, SchemaParser $parser): array
    {
        $relatedModels = [];
        
        foreach ($relations as $relationName => $relationDef) {
            $parts = explode(':', $relationDef);
            $relationType = $parts[0];
            
            switch ($relationType) {
                case 'belongsTo':
                case 'hasOne':
                case 'hasMany':
                    $relatedModel = $parts[1] ?? null;
                    if ($relatedModel && $parser->getModel($relatedModel)) {
                        $relatedModels[] = Str::studly($relatedModel);
                    }
                    break;
                case 'belongsToMany':
                    $relatedModel = $parts[1] ?? null;
                    if ($relatedModel && $parser->getModel($relatedModel)) {
                        $relatedModels[] = Str::studly($relatedModel);
                    }
                    break;
            }
        }
        
        return array_unique($relatedModels);
    }

    protected function hasSimpleRelationships(array $relations): bool
    {
        foreach ($relations as $relationName => $relationDef) {
            $parts = explode(':', $relationDef);
            $relationType = $parts[0];
            
            // If we have belongsTo or complex relationships, it's not simple
            if (in_array($relationType, ['belongsTo', 'belongsToMany', 'morphTo', 'morphMany'])) {
                return false;
            }
        }
        
        return true;
    }

    protected function addComplexSeeding(array &$content, string $modelName, array $model, SchemaParser $parser, int $count): void
    {
        $relations = $model['relations'] ?? [];
        $modelClass = Str::studly($modelName);
        
        // Check if we need to create parent records first
        $needsParents = [];
        foreach ($relations as $relationName => $relationDef) {
            $parts = explode(':', $relationDef);
            $relationType = $parts[0];
            
            if ($relationType === 'belongsTo') {
                $relatedModel = $parts[1] ?? null;
                $foreignKey = $parts[2] ?? null;
                
                if ($relatedModel && $parser->getModel($relatedModel)) {
                    $needsParents[] = [
                        'model' => Str::studly($relatedModel),
                        'relation' => $relationName,
                        'foreign_key' => $foreignKey
                    ];
                }
            }
        }
        
        if (!empty($needsParents)) {
            $content[] = "        // Create related records first";
            foreach ($needsParents as $parent) {
                $content[] = "        \${$parent['relation']}s = {$parent['model']}::factory(5)->create();";
            }
            $content[] = "";
        }
        
        $content[] = "        // Create {$modelClass} records";
        
        if (!empty($needsParents)) {
            $content[] = "        {$modelClass}::factory({$count})";
            foreach ($needsParents as $parent) {
                $content[] = "            ->create([";
                $foreignKey = $parent['foreign_key'] ?? Str::snake($parent['relation']) . '_id';
                $content[] = "                '{$foreignKey}' => \${$parent['relation']}s->random()->id,";
                $content[] = "            ]);";
            }
        } else {
            $content[] = "        {$modelClass}::factory({$count})->create();";
        }
        
        // Handle many-to-many relationships
        $manyToManyRelations = [];
        foreach ($relations as $relationName => $relationDef) {
            $parts = explode(':', $relationDef);
            if ($parts[0] === 'belongsToMany') {
                $relatedModel = $parts[1] ?? null;
                if ($relatedModel && $parser->getModel($relatedModel)) {
                    $manyToManyRelations[] = [
                        'model' => Str::studly($relatedModel),
                        'relation' => $relationName
                    ];
                }
            }
        }
        
        if (!empty($manyToManyRelations)) {
            $content[] = "";
            $content[] = "        // Attach many-to-many relationships";
            foreach ($manyToManyRelations as $relation) {
                $content[] = "        \${$relation['relation']}s = {$relation['model']}::factory(10)->create();";
                $content[] = "        {$modelClass}::all()->each(function (\${$modelName}) use (\${$relation['relation']}s) {";
                $content[] = "            \${$modelName}->{$relation['relation']}()->attach(";
                $content[] = "                \${$relation['relation']}s->random(rand(1, 3))->pluck('id')->toArray()";
                $content[] = "            );";
                $content[] = "        });";
            }
        }
    }

    protected function getModelsInDependencyOrder(SchemaParser $parser): array
    {
        $models = $parser->getModelNames();
        $ordered = [];
        $dependencies = [];
        
        // Build dependency graph
        foreach ($models as $modelName) {
            $model = $parser->getModel($modelName);
            $relations = $model['relations'] ?? [];
            
            $modelDependencies = [];
            foreach ($relations as $relationName => $relationDef) {
                $parts = explode(':', $relationDef);
                if ($parts[0] === 'belongsTo') {
                    $relatedModel = $parts[1] ?? null;
                    if ($relatedModel && in_array($relatedModel, $models)) {
                        $modelDependencies[] = $relatedModel;
                    }
                }
            }
            
            $dependencies[$modelName] = $modelDependencies;
        }
        
        // Topological sort
        $visited = [];
        $temp = [];
        
        foreach ($models as $model) {
            if (!isset($visited[$model])) {
                $this->topologicalSortUtil($model, $dependencies, $visited, $temp, $ordered);
            }
        }
        
        return array_reverse($ordered);
    }

    protected function topologicalSortUtil(string $model, array $dependencies, array &$visited, array &$temp, array &$ordered): void
    {
        $temp[$model] = true;
        
        foreach ($dependencies[$model] ?? [] as $dependency) {
            if (isset($temp[$dependency])) {
                // Circular dependency detected, skip
                continue;
            }
            
            if (!isset($visited[$dependency])) {
                $this->topologicalSortUtil($dependency, $dependencies, $visited, $temp, $ordered);
            }
        }
        
        unset($temp[$model]);
        $visited[$model] = true;
        $ordered[] = $model;
    }
}