<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Login with mobile_number only.
     * No database or model check.
     */
    public function login(Request $request)
    {
        $request->validate([
            'mobile_number' => 'required|string|max:50',
        ]);

        $mobileNumber = $request->input('mobile_number');

        // Generate a simple random token
        $token = Str::random(60);

        return response()->json([
            'status' => 'success',
            'message' => 'Logged in successfully',
            'token' => $token,
            'mobile_number' => $mobileNumber,
        ]);
    }

    /**
     * Logout without any DB or model.
     */
    public function logout(Request $request)
    {
        $request->validate([
            'mobile_number' => 'required|string|max:50',
        ]);

        return response()->json([
            'code' => 200,
            'message' => "Logged out successfully",
            'token' => null,
            'mobile_number' => $request->input('mobile_number'),
        ]);
    }
}
