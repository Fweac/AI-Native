<?php

namespace AiNative\Laravel\Generators;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Support\Str;

class ControllerGenerator extends BaseGenerator
{
    public function generate(string $modelName, SchemaParser $parser): string
    {
        $model = $parser->getModel($modelName);
        $routes = $parser->getModelRoutes($modelName);
        $filters = $parser->getModelFilters($modelName);
        $policies = $parser->getModelPolicies($modelName);
        $hooks = $parser->getModelHooks($modelName);
        $validationRules = $parser->getValidationRules($modelName);
        $fileFields = $this->getFileFields($modelName, $parser);
        
        $className = Str::studly($modelName) . 'Controller';
        $modelClass = Str::studly($modelName);
        $variableName = Str::camel($modelName);
        $routeParameter = Str::snake($modelName);
        
        $methods = $this->generateMethods($routes, $modelClass, $variableName, $routeParameter, $filters, $validationRules, $hooks);
        
        // Add file upload/download methods if there are file fields
        if (!empty($fileFields)) {
            $fileMethods = $this->generateFileMethods($modelClass, $variableName, $fileFields);
            $methods .= "\n\n    " . $fileMethods;
        }
        $imports = $this->generateImports($modelClass, !empty($fileFields));
        $middlewares = $this->generateMiddlewares($policies);
        
        $stub = $this->getStub('controller');
        
        return str_replace([
            '{{ namespace }}',
            '{{ imports }}',
            '{{ class }}',
            '{{ model }}',
            '{{ middlewares }}',
            '{{ methods }}'
        ], [
            'App\\Http\\Controllers',
            $imports,
            $className,
            $modelClass,
            $middlewares,
            $methods
        ], $stub);
    }

    protected function generateMethods(array $routes, string $modelClass, string $variableName, string $routeParameter, array $filters, array $validationRules, array $hooks): string
    {
        $methods = [];
        
        foreach ($routes as $route) {
            switch ($route) {
                case 'list':
                case 'index':
                    $methods[] = $this->generateIndexMethod($modelClass, $variableName, $filters);
                    break;
                case 'show':
                    $methods[] = $this->generateShowMethod($modelClass, $variableName, $routeParameter);
                    break;
                case 'create':
                case 'store':
                    $methods[] = $this->generateStoreMethod($modelClass, $variableName, $validationRules, $hooks);
                    break;
                case 'update':
                    $methods[] = $this->generateUpdateMethod($modelClass, $variableName, $routeParameter, $validationRules, $hooks);
                    break;
                case 'delete':
                case 'destroy':
                    $methods[] = $this->generateDestroyMethod($modelClass, $variableName, $routeParameter, $hooks);
                    break;
            }
        }
        
        return implode("\n\n    ", $methods);
    }

    protected function generateIndexMethod(string $modelClass, string $variableName, array $filters): string
    {
        $queryBuilder = "\${$variableName}Query = {$modelClass}::query();";
        
        // Add default filters
        if (isset($filters['index'])) {
            $indexFilters = $filters['index'];
            
            if (isset($indexFilters['where'])) {
                foreach ($indexFilters['where'] as $field => $value) {
                    if ($value === '$auth.id') {
                        $queryBuilder .= "\n        \${$variableName}Query->where('{$field}', auth()->id());";
                    } else {
                        $queryBuilder .= "\n        \${$variableName}Query->where('{$field}', '{$value}');";
                    }
                }
            }
            
            if (isset($indexFilters['orderBy'])) {
                $orderField = $indexFilters['orderBy'][0];
                $orderDirection = $indexFilters['orderBy'][1] ?? 'asc';
                $queryBuilder .= "\n        \${$variableName}Query->orderBy('{$orderField}', '{$orderDirection}');";
            }
            
            if (isset($indexFilters['with'])) {
                $relationships = implode("', '", $indexFilters['with']);
                $queryBuilder .= "\n        \${$variableName}Query->with(['{$relationships}']);";
            }
        }
        
        // Add search functionality
        $queryBuilder .= "\n\n        // Apply search filters from request\n";
        $queryBuilder .= "        if (\$request->has('search')) {\n";
        $queryBuilder .= "            \${$variableName}Query->where(function (\$q) use (\$request) {\n";
        $queryBuilder .= "                // Add searchable fields based on your model\n";
        $queryBuilder .= "                \$searchTerm = \$request->get('search');\n";
        $queryBuilder .= "                // Example: \$q->where('title', 'LIKE', \"%{\$searchTerm}%\");\n";
        $queryBuilder .= "            });\n";
        $queryBuilder .= "        }";
        
        return <<<METHOD
public function index(Request \$request): JsonResponse
    {
        {$queryBuilder}
        
        \${$variableName}s = \${$variableName}Query->paginate(\$request->get('per_page', 15));
        
        return response()->json(\${$variableName}s);
    }
METHOD;
    }

    protected function generateShowMethod(string $modelClass, string $variableName, string $routeParameter): string
    {
        return <<<METHOD
public function show({$modelClass} \${$variableName}): JsonResponse
    {
        return response()->json(\${$variableName});
    }
METHOD;
    }

    protected function generateStoreMethod(string $modelClass, string $variableName, array $validationRules, array $hooks): string
    {
        $validation = '';
        if (!empty($validationRules)) {
            $rulesArray = [];
            foreach ($validationRules as $field => $rule) {
                $rulesArray[] = "            '{$field}' => '{$rule}'";
            }
            $rulesString = implode(",\n", $rulesArray);
            $validation = <<<VALIDATION
\$validated = \$request->validate([
{$rulesString}
        ]);
VALIDATION;
        } else {
            $validation = '$validated = $request->all();';
        }
        
        $beforeCreateHook = '';
        $afterCreateHook = '';
        
        if (isset($hooks['beforeCreate'])) {
            $beforeCreateHook = "\n        // Before create hook\n        \$validated = \$this->handleBeforeCreate(\$validated);";
        }
        
        if (isset($hooks['afterCreate'])) {
            $afterCreateHook = "\n\n        // After create hook\n        \$this->handleAfterCreate(\${$variableName});";
        }
        
        return <<<METHOD
public function store(Request \$request): JsonResponse
    {
        {$validation}{$beforeCreateHook}
        
        \${$variableName} = {$modelClass}::create(\$validated);{$afterCreateHook}
        
        return response()->json(\${$variableName}, 201);
    }
METHOD;
    }

    protected function generateUpdateMethod(string $modelClass, string $variableName, string $routeParameter, array $validationRules, array $hooks): string
    {
        $validation = '';
        if (!empty($validationRules)) {
            $rulesArray = [];
            foreach ($validationRules as $field => $rule) {
                // For updates, make most rules optional except required ones
                $updateRule = str_replace('required', 'sometimes', $rule);
                $rulesArray[] = "            '{$field}' => '{$updateRule}'";
            }
            $rulesString = implode(",\n", $rulesArray);
            $validation = <<<VALIDATION
\$validated = \$request->validate([
{$rulesString}
        ]);
VALIDATION;
        } else {
            $validation = '$validated = $request->all();';
        }
        
        $beforeUpdateHook = '';
        $afterUpdateHook = '';
        
        if (isset($hooks['beforeUpdate'])) {
            $beforeUpdateHook = "\n        // Before update hook\n        \$validated = \$this->handleBeforeUpdate(\${$variableName}, \$validated);";
        }
        
        if (isset($hooks['afterUpdate'])) {
            $afterUpdateHook = "\n\n        // After update hook\n        \$this->handleAfterUpdate(\${$variableName});";
        }
        
        return <<<METHOD
public function update(Request \$request, {$modelClass} \${$variableName}): JsonResponse
    {
        {$validation}{$beforeUpdateHook}
        
        \${$variableName}->update(\$validated);{$afterUpdateHook}
        
        return response()->json(\${$variableName});
    }
METHOD;
    }

    protected function generateDestroyMethod(string $modelClass, string $variableName, string $routeParameter, array $hooks): string
    {
        $beforeDeleteHook = '';
        $afterDeleteHook = '';
        
        if (isset($hooks['beforeDelete'])) {
            $beforeDeleteHook = "\n        // Before delete hook\n        \$this->handleBeforeDelete(\${$variableName});";
        }
        
        if (isset($hooks['afterDelete'])) {
            $afterDeleteHook = "\n\n        // After delete hook\n        \$this->handleAfterDelete(\${$variableName});";
        }
        
        return <<<METHOD
public function destroy({$modelClass} \${$variableName}): JsonResponse
    {{$beforeDeleteHook}
        
        \${$variableName}->delete();{$afterDeleteHook}
        
        return response()->json(null, 204);
    }
METHOD;
    }

    protected function generateImports(string $modelClass, bool $hasFileFields = false): string
    {
        $imports = [
            'use App\\Http\\Controllers\\Controller;',
            'use App\\Models\\' . $modelClass . ';',
            'use Illuminate\\Http\\JsonResponse;',
            'use Illuminate\\Http\\Request;'
        ];
        
        if ($hasFileFields) {
            $imports[] = 'use Illuminate\\Support\\Facades\\Storage;';
            $imports[] = 'use Illuminate\\Http\\Response;';
        }
        
        return implode("\n", $imports);
    }

    protected function generateMiddlewares(array $policies): string
    {
        if (empty($policies)) {
            return '';
        }
        
        $middlewares = [];
        foreach ($policies as $action => $policy) {
            // Convert policies to middleware (this is a simplified version)
            if (strpos($policy, 'role:') === 0) {
                $roles = str_replace('role:', '', $policy);
                $middlewares[] = "\$this->middleware('role:{$roles}')->only(['{$action}']);";
            } elseif ($policy === 'authenticated') {
                $middlewares[] = "\$this->middleware('auth:sanctum')->only(['{$action}']);";
            }
        }
        
        if (empty($middlewares)) {
            return '';
        }
        
        $middlewareString = implode("\n        ", $middlewares);
        
        return <<<MIDDLEWARE
public function __construct()
    {
        {$middlewareString}
    }
MIDDLEWARE;
    }

    protected function getFileFields(string $modelName, SchemaParser $parser): array
    {
        $model = $parser->getModel($modelName);
        $fields = $model['fields'] ?? [];
        $fileFields = [];

        foreach ($fields as $fieldName => $fieldDefinition) {
            $fieldType = $parser->parseFieldType($fieldName, $fieldDefinition);
            if (isset($fieldType['is_file']) && $fieldType['is_file']) {
                $fileFields[$fieldName] = [
                    'multiple' => $fieldType['multiple'] ?? false,
                    'disk' => $fieldType['storage_disk'] ?? 'public',
                    'validation' => $fieldType['validations'] ?? 'file'
                ];
            }
        }

        return $fileFields;
    }

    protected function generateFileMethods(string $modelClass, string $variableName, array $fileFields): string
    {
        $methods = [];
        
        foreach ($fileFields as $fieldName => $config) {
            $methodName = Str::studly($fieldName);
            $disk = $config['disk'];
            $multiple = $config['multiple'];
            
            // Generate upload method
            if ($multiple) {
                $methods[] = $this->generateMultipleFileUploadMethod($modelClass, $variableName, $fieldName, $methodName, $disk);
            } else {
                $methods[] = $this->generateSingleFileUploadMethod($modelClass, $variableName, $fieldName, $methodName, $disk);
            }
            
            // Generate download method
            $methods[] = $this->generateFileDownloadMethod($modelClass, $variableName, $fieldName, $methodName, $disk, $multiple);
        }
        
        return implode("\n\n    ", $methods);
    }

    protected function generateSingleFileUploadMethod(string $modelClass, string $variableName, string $fieldName, string $methodName, string $disk): string
    {
        return <<<METHOD
public function upload{$methodName}(Request \$request, {$modelClass} \${$variableName}): JsonResponse
{
    \$request->validate([
        'file' => 'required|file|max:10240', // 10MB max
    ]);

    \$file = \$request->file('file');
    \$path = \$file->store('{$fieldName}', '{$disk}');

    \${$variableName}->update(['{$fieldName}' => \$path]);

    return response()->json([
        'message' => 'File uploaded successfully',
        'path' => \$path,
        'url' => \Storage::disk('{$disk}')->url(\$path)
    ]);
}
METHOD;
    }

    protected function generateMultipleFileUploadMethod(string $modelClass, string $variableName, string $fieldName, string $methodName, string $disk): string
    {
        return <<<METHOD
public function upload{$methodName}(Request \$request, {$modelClass} \${$variableName}): JsonResponse
{
    \$request->validate([
        'files' => 'required|array',
        'files.*' => 'file|max:10240', // 10MB max per file
    ]);

    \$paths = [];
    foreach (\$request->file('files') as \$file) {
        \$paths[] = \$file->store('{$fieldName}', '{$disk}');
    }

    \$existing = \${$variableName}->{$fieldName} ?: [];
    \$allPaths = array_merge(\$existing, \$paths);
    
    \${$variableName}->update(['{$fieldName}' => \$allPaths]);

    return response()->json([
        'message' => 'Files uploaded successfully',
        'paths' => \$paths,
        'urls' => array_map(fn(\$path) => \Storage::disk('{$disk}')->url(\$path), \$paths)
    ]);
}
METHOD;
    }

    protected function generateFileDownloadMethod(string $modelClass, string $variableName, string $fieldName, string $methodName, string $disk, bool $multiple): string
    {
        if ($multiple) {
            return <<<METHOD
public function download{$methodName}(Request \$request, {$modelClass} \${$variableName}): JsonResponse
{
    \$files = \${$variableName}->{$fieldName} ?: [];
    
    if (empty(\$files)) {
        return response()->json(['message' => 'No files found'], 404);
    }

    \$urls = [];
    foreach (\$files as \$path) {
        if (\Storage::disk('{$disk}')->exists(\$path)) {
            \$urls[] = [
                'path' => \$path,
                'url' => \Storage::disk('{$disk}')->url(\$path),
                'name' => basename(\$path)
            ];
        }
    }

    return response()->json([
        'files' => \$urls
    ]);
}
METHOD;
        } else {
            return <<<METHOD
public function download{$methodName}({$modelClass} \${$variableName})
{
    \$path = \${$variableName}->{$fieldName};
    
    if (!\$path || !\Storage::disk('{$disk}')->exists(\$path)) {
        abort(404, 'File not found');
    }

    return \Storage::disk('{$disk}')->download(\$path);
}
METHOD;
        }
    }

    protected function getStub(string $type): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

{{ imports }}

class {{ class }} extends Controller
{
    {{ middlewares }}

    {{ methods }}
}
STUB;
    }
}