<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     *
     * Body: { email, password, school_slug }
     *
     * Returns: { token, user, school }
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'school_slug' => 'required|string',
        ]);

        // Resolve school
        $tenant = Tenant::where('slug', $data['school_slug'])->first();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'school_slug' => ['No school found with that identifier.'],
            ]);
        }

        // Find user belonging to this school
        $user = User::where('email', $data['email'])
            ->where('tenant_id', $tenant->id)
            ->where('role', 'school_admin')
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // Revoke old mobile tokens, then issue a fresh one
        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'school' => [
                'id'   => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/auth/logout
     *
     * Revokes the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/v1/auth/me
     *
     * Returns the authenticated user + school info.
     */
    public function me(Request $request): JsonResponse
    {
        $user   = $request->user();
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'two_factor_enabled' => $user->two_factor_enabled,
            ],
            'school' => $tenant ? [
                'id'     => $tenant->id,
                'slug'   => $tenant->slug,
                'name'   => $tenant->name,
                'email'  => $tenant->email,
                'status' => $tenant->status,
            ] : null,
        ]);
    }
}
