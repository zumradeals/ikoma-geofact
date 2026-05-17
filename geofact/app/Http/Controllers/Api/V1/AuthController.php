<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\JwtService;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct(private readonly JwtService $jwtService) {}

    /**
     * Authentifie un utilisateur et retourne un JWT GEOFACT (C-12.2).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
                    ->where('status', 'active')
                    ->first();

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            $this->auditFailedLogin($request);
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides.',
                'errors'  => ['auth' => 'Email ou mot de passe incorrect.'],
            ], 401);
        }

        $token = $this->jwtService->generateToken($user);

        $user->update(['last_login_at' => now()]);

        $this->auditLogin($user, $request);

        return response()->json([
            'success' => true,
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user'       => [
                    'id'              => $user->id,
                    'email'           => $user->email,
                    'role'            => $user->role,
                    'organization_id' => $user->organization_id,
                ],
            ],
            'message' => 'Authentification réussie.',
        ]);
    }

    /**
     * Renouvelle le token JWT courant.
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $token    = JWTAuth::getToken();
            $newToken = JWTAuth::refresh($token);

            return response()->json([
                'success' => true,
                'data'    => [
                    'token'      => $newToken,
                    'token_type' => 'Bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
                'message' => 'Token renouvelé.',
            ]);
        } catch (JWTException $e) {
            Log::warning('geofact.auth.refresh_failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de renouveler le token.',
                'errors'  => ['auth' => $e->getMessage()],
            ], 401);
        }
    }

    /**
     * Révoque le token courant (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = JWTAuth::getToken();
        if ($token) {
            $this->jwtService->invalidateToken((string) $token);
        }

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
            'data'    => [],
        ]);
    }

    private function auditLogin(User $user, Request $request): void
    {
        try {
            AuditLog::create([
                'id'              => Str::uuid()->toString(),
                'actor_id'        => $user->id,
                'actor_role'      => $user->role,
                'organization_id' => $user->organization_id,
                'action'          => 'auth.login',
                'resource_type'   => 'user',
                'resource_id'     => $user->id,
                'result'          => 'success',
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
                'payload'         => ['email' => $user->email],
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('geofact.audit.write_failed', ['error' => $e->getMessage()]);
        }
    }

    private function auditFailedLogin(Request $request): void
    {
        try {
            AuditLog::create([
                'id'              => Str::uuid()->toString(),
                'actor_id'        => 'anonymous',
                'actor_role'      => 'unknown',
                'organization_id' => null,
                'action'          => 'auth.login.failed',
                'resource_type'   => 'user',
                'resource_id'     => null,
                'result'          => 'rejected',
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
                'payload'         => ['email' => $request->email],
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('geofact.audit.write_failed', ['error' => $e->getMessage()]);
        }
    }
}
