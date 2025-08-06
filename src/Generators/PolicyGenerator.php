<?php

namespace AiNative\Laravel\Generators;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class PolicyGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function generate(string $modelName, SchemaParser $parser): string
    {
        $model = $parser->getModel($modelName);
        $policies = $model['policies'] ?? [];
        
        $className = Str::studly($modelName) . 'Policy';
        $modelClass = Str::studly($modelName);
        $modelVariable = Str::camel($modelName);
        
        $content = [];
        $content[] = "<?php";
        $content[] = "";
        $content[] = "namespace App\\Policies;";
        $content[] = "";
        $content[] = "use App\\Models\\User;";
        $content[] = "use App\\Models\\{$modelClass};";
        $content[] = "use Illuminate\\Auth\\Access\\HandlesAuthorization;";
        $content[] = "use Illuminate\\Auth\\Access\\Response;";
        $content[] = "";
        $content[] = "class {$className}";
        $content[] = "{";
        $content[] = "    use HandlesAuthorization;";
        $content[] = "";
        
        // Add policy methods
        $standardMethods = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];
        
        foreach ($standardMethods as $method) {
            if (isset($policies[$method])) {
                $this->addPolicyMethod($content, $method, $policies[$method], $modelClass, $modelVariable);
            } else {
                // Add default policy method
                $this->addDefaultPolicyMethod($content, $method, $modelClass, $modelVariable);
            }
        }
        
        // Add custom policy methods
        foreach ($policies as $method => $rule) {
            if (!in_array($method, $standardMethods)) {
                $this->addPolicyMethod($content, $method, $rule, $modelClass, $modelVariable);
            }
        }
        
        $content[] = "}";
        
        return implode("\n", $content);
    }

    protected function addPolicyMethod(array &$content, string $method, string $rule, string $modelClass, string $modelVariable): void
    {
        $content[] = "    /**";
        $content[] = "     * Determine whether the user can {$method} models.";
        $content[] = "     */";
        
        // Determine method signature based on method type
        if (in_array($method, ['viewAny', 'create'])) {
            $signature = "public function {$method}(User \$user): Response";
            $modelParam = "";
        } else {
            $signature = "public function {$method}(User \$user, {$modelClass} \${$modelVariable}): Response";
            $modelParam = ", \${$modelVariable}";
        }
        
        $content[] = "    {$signature}";
        $content[] = "    {";
        
        // Parse and generate authorization logic
        $authLogic = $this->parseAuthorizationRule($rule, $modelVariable);
        $content[] = "        return {$authLogic};";
        
        $content[] = "    }";
        $content[] = "";
    }

    protected function addDefaultPolicyMethod(array &$content, string $method, string $modelClass, string $modelVariable): void
    {
        $content[] = "    /**";
        $content[] = "     * Determine whether the user can {$method} models.";
        $content[] = "     */";
        
        if (in_array($method, ['viewAny', 'create'])) {
            $signature = "public function {$method}(User \$user): Response";
        } else {
            $signature = "public function {$method}(User \$user, {$modelClass} \${$modelVariable}): Response";
        }
        
        $content[] = "    {$signature}";
        $content[] = "    {";
        
        // Default authorization logic
        switch ($method) {
            case 'viewAny':
            case 'view':
                $content[] = "        return Response::allow();";
                break;
            case 'create':
                $content[] = "        return \$user ? Response::allow() : Response::deny();";
                break;
            case 'update':
            case 'delete':
                $content[] = "        return \$user->id === \${$modelVariable}->user_id ? Response::allow() : Response::deny();";
                break;
            case 'restore':
            case 'forceDelete':
                $content[] = "        return \$user->isAdmin() ? Response::allow() : Response::deny();";
                break;
        }
        
        $content[] = "    }";
        $content[] = "";
    }

    protected function parseAuthorizationRule(string $rule, string $modelVariable): string
    {
        // Handle OR conditions (pipe separated)
        if (str_contains($rule, '|')) {
            $conditions = explode('|', $rule);
            $parsedConditions = array_map(fn($condition) => $this->parseSingleCondition(trim($condition), $modelVariable), $conditions);
            return "(" . implode(" || ", $parsedConditions) . ") ? Response::allow() : Response::deny()";
        }
        
        // Handle AND conditions (comma separated)
        if (str_contains($rule, ',')) {
            $conditions = explode(',', $rule);
            $parsedConditions = array_map(fn($condition) => $this->parseSingleCondition(trim($condition), $modelVariable), $conditions);
            return "(" . implode(" && ", $parsedConditions) . ") ? Response::allow() : Response::deny()";
        }
        
        // Single condition
        $condition = $this->parseSingleCondition($rule, $modelVariable);
        return "{$condition} ? Response::allow() : Response::deny()";
    }

    protected function parseSingleCondition(string $condition, string $modelVariable): string
    {
        // Handle role-based conditions
        if (str_starts_with($condition, 'role:')) {
            $roles = explode(',', str_replace('role:', '', $condition));
            $roleChecks = array_map(fn($role) => "\$user->hasRole('{$role}')", $roles);
            return "(" . implode(" || ", $roleChecks) . ")";
        }
        
        // Handle owner condition
        if ($condition === 'owner') {
            return "\$user->id === \${$modelVariable}->user_id";
        }
        
        // Handle authenticated condition
        if ($condition === 'authenticated') {
            return "\$user !== null";
        }
        
        // Handle public condition
        if ($condition === 'public') {
            return "\${$modelVariable}->is_public === true";
        }
        
        // Handle collaborator condition (for project-like models)
        if ($condition === 'collaborator') {
            return "\${$modelVariable}->collaborators->contains(\$user->id)";
        }
        
        // Handle project member condition
        if ($condition === 'projectMember') {
            return "\${$modelVariable}->project->collaborators->contains(\$user->id) || \${$modelVariable}->project->user_id === \$user->id";
        }
        
        // Handle project owner condition
        if ($condition === 'projectOwner') {
            return "\${$modelVariable}->project->user_id === \$user->id";
        }
        
        // Handle assignee condition
        if ($condition === 'assignee') {
            return "\${$modelVariable}->user_id === \$user->id";
        }
        
        // Handle custom field conditions
        if (str_contains($condition, ':')) {
            [$field, $value] = explode(':', $condition, 2);
            if ($field === 'user_id' || $field === 'owner_id') {
                return "\$user->id === \${$modelVariable}->{$field}";
            }
            return "\${$modelVariable}->{$field} === '{$value}'";
        }
        
        // Default: treat as method call
        return "\$user->{$condition}()";
    }

    public function generatePolicyServiceProvider(SchemaParser $parser): string
    {
        $content = [];
        $content[] = "<?php";
        $content[] = "";
        $content[] = "namespace App\\Providers;";
        $content[] = "";
        $content[] = "use Illuminate\\Foundation\\Support\\Providers\\AuthServiceProvider as ServiceProvider;";
        $content[] = "use Illuminate\\Support\\Facades\\Gate;";
        $content[] = "";
        
        // Add model imports
        foreach ($parser->getModelNames() as $modelName) {
            $model = $parser->getModel($modelName);
            if (isset($model['policies'])) {
                $modelClass = Str::studly($modelName);
                $content[] = "use App\\Models\\{$modelClass};";
                $content[] = "use App\\Policies\\{$modelClass}Policy;";
            }
        }
        
        $content[] = "";
        $content[] = "class AuthServiceProvider extends ServiceProvider";
        $content[] = "{";
        $content[] = "    /**";
        $content[] = "     * The model to policy mappings for the application.";
        $content[] = "     *";
        $content[] = "     * @var array<class-string, class-string>";
        $content[] = "     */";
        $content[] = "    protected \$policies = [";
        
        foreach ($parser->getModelNames() as $modelName) {
            $model = $parser->getModel($modelName);
            if (isset($model['policies'])) {
                $modelClass = Str::studly($modelName);
                $content[] = "        {$modelClass}::class => {$modelClass}Policy::class,";
            }
        }
        
        $content[] = "    ];";
        $content[] = "";
        $content[] = "    /**";
        $content[] = "     * Register any authentication / authorization services.";
        $content[] = "     */";
        $content[] = "    public function boot(): void";
        $content[] = "    {";
        $content[] = "        \$this->registerPolicies();";
        $content[] = "";
        $content[] = "        //";
        $content[] = "    }";
        $content[] = "}";
        
        return implode("\n", $content);
    }
}