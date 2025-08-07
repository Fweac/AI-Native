<?php

namespace AiNative\Laravel\Generators;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Support\Str;

class AuthGenerator extends BaseGenerator
{
    public function generateAuthController(SchemaParser $parser): string
    {
        $authConfig = $parser->getAuthConfig();
        $provider = $authConfig['provider'] ?? 'sanctum';
        
        $stub = $this->getAuthControllerStub($provider);
        
        return str_replace([
            '{{ namespace }}',
            '{{ class }}'
        ], [
            'App\\Http\\Controllers',
            'AuthController'
        ], $stub);
    }
    
    protected function getAuthControllerStub(string $provider): string
    {
        if ($provider === 'sanctum') {
            return $this->getSanctumControllerStub();
        }
        
        return $this->getBasicAuthControllerStub();
    }
    
    protected function getSanctumControllerStub(): string
    {
        return '<?php

namespace {{ namespace }};

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class {{ class }} extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $request->validate([
            \'name\' => \'required|string|max:255\',
            \'email\' => \'required|string|email|max:255|unique:users\',
            \'password\' => \'required|string|min:8|confirmed\',
        ]);

        $user = User::create([
            \'name\' => $request->name,
            \'email\' => $request->email,
            \'password\' => Hash::make($request->password),
        ]);

        $token = $user->createToken(\'auth-token\')->plainTextToken;

        return response()->json([
            \'user\' => $user,
            \'token\' => $token,
            \'token_type\' => \'Bearer\',
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $request->validate([
            \'email\' => \'required|email\',
            \'password\' => \'required\',
        ]);

        $user = User::where(\'email\', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                \'email\' => [\'The provided credentials are incorrect.\'],
            ]);
        }

        $token = $user->createToken(\'auth-token\')->plainTextToken;

        return response()->json([
            \'user\' => $user,
            \'token\' => $token,
            \'token_type\' => \'Bearer\',
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            \'message\' => \'Successfully logged out\',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}';
    }
    
    protected function getBasicAuthControllerStub(): string
    {
        return '<?php

namespace {{ namespace }};

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class {{ class }} extends Controller
{
    /**
     * Login user
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            \'email\' => \'required|email\',
            \'password\' => \'required\',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            return response()->json([
                \'user\' => Auth::user(),
                \'message\' => \'Login successful\',
            ]);
        }

        return response()->json([
            \'message\' => \'The provided credentials do not match our records.\',
        ], 401);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            \'message\' => \'Successfully logged out\',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}';
    }

    public function generateAuthRoutes(SchemaParser $parser): string
    {
        $authConfig = $parser->getAuthConfig();
        $provider = $authConfig['provider'] ?? 'sanctum';
        
        if ($provider === 'sanctum') {
            return $this->generateSanctumRoutes();
        }
        
        return $this->generateBasicAuthRoutes();
    }
    
    protected function generateSanctumRoutes(): string
    {
        return '// Authentication Routes (Sanctum)
Route::post(\'/register\', [AuthController::class, \'register\']);
Route::post(\'/login\', [AuthController::class, \'login\']);

Route::middleware(\'auth:sanctum\')->group(function () {
    Route::post(\'/logout\', [AuthController::class, \'logout\']);
    Route::get(\'/user\', [AuthController::class, \'user\']);
});';
    }
    
    protected function generateBasicAuthRoutes(): string
    {
        return '// Authentication Routes
Route::post(\'/login\', [AuthController::class, \'login\']);

Route::middleware(\'auth\')->group(function () {
    Route::post(\'/logout\', [AuthController::class, \'logout\']);
    Route::get(\'/user\', [AuthController::class, \'user\']);
});';
    }
}