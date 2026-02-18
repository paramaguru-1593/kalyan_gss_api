<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'status' => "fail",
                'message' => 'Email or password is incorrect'
            ], 401);
        }

        $user = Auth::user();

        // Eager load role if relationship exists
        $user->loadMissing('role'); // assuming User model has `role()` relation to MlUserRole

        $allowedRoles = explode(',', env('ALLOWED_LOGIN_ROLES'));
        $userRole = optional($user->role)->name;
        // if (!in_array($userRole, $allowedRoles)) {
        //     return response()->json([
        //         'status' => "fail",
        //         'message' => "You are not allowed to login."
        //     ], 403);
        // }
        // dd($user->isactive);
        if ($user->isactive !== 'Y') {
            return response()->json([
                'status' => "fail",
                'message' => "Your account is inactive. Please contact admin."
            ], 403);
        }

        // Create personal access token and related entries (wrapped to surface DB/token errors clearly)
        try {
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;

            if ($request->remember_me) {
                $token->expires_at = now()->addWeeks(1);
            }

            $token->save();

            // Update remember_token (if still needed)
            $user->update([
                'remember_token' => $tokenResult->accessToken,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Log and return a clear JSON error instead of a generic 500 HTML page
            \Illuminate\Support\Facades\Log::error('Token creation or DB operation failed during login', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Token creation failed or database tables are missing. Please check migration for personal_access_tokens (Sanctum) or oauth tables (Passport).',
                'debug' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => "success",
            'message' => 'Logged in successfully',
            'id' => $user->ml_user_id,
            'agent_email' => $user->email,
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => optional($token->expires_at)->toDateTimeString(),
            'role' => optional($user->role)->name,
            'customer_role' => $user->customer_role ?? null,
            'latest_dailed_profile' => $user->in_call === 'Y' ? $user->latest_called_profile_id : null,
        ]);
    }


    public function logout(Request $request)
    {

        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $rememberToken = DB::table('ml_user_v2')->where('ml_user_id', $request->user_id)->update([
            'remember_token' => null,
            'updated_at' => Carbon::now('Asia/Kolkata')
        ]);

        if ($rememberToken) {
            return response()->json([
                'code' => 200,
                'message' => "Logged out successfully",
            ]);
        } else {
            return response()->json([
                'message' => "User not found"
            ], 404);
        }

    }
}
