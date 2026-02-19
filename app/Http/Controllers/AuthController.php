<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Login by mobile_number. If customer exists, use it; otherwise create and allow login.
     * Stores access token in personal_access_tokens and returns it. On second login, name is included when present.
     */
    public function login(Request $request)
    {
        $request->validate([
            'mobile_number' => 'required|string|max:50',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
        ]);

        $mobileNumber = $request->input('mobile_number');

        $customer = Customer::firstOrCreate(
            ['mobile_no' => $mobileNumber],
            [
                'first_name' => $request->input('first_name', ''),
                'last_name' => $request->input('last_name', ''),
                'status' => 'active',
            ]
        );

        // Update name if provided on subsequent logins
        if ($request->filled('first_name') || $request->filled('last_name')) {
            if ($request->filled('first_name')) {
                $customer->first_name = $request->input('first_name');
            }
            if ($request->filled('last_name')) {
                $customer->last_name = $request->input('last_name');
            }
            $customer->save();
        }

        $customer->last_login_at = now();
        $customer->save();

        $tokenResult = $customer->createToken('Personal Access Token');
        $token = $tokenResult->plainTextToken;

        // ✅ KYC check
        $kycUpdated = (
            !empty($customer->id_proof_type) &&
            !empty($customer->id_proof_number) &&
            !empty($customer->id_proof_front_side_url)
        );

        $userPayload = [
            'id' => $customer->id,
            'mobile_no' => $customer->mobile_no,
            'email' => $customer->email,
        ];
        if ($customer->full_name !== null) {
            $userPayload['name'] = $customer->full_name;
            $userPayload['first_name'] = $customer->first_name;
            $userPayload['last_name'] = $customer->last_name;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged in successfully',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $userPayload,
            'kyc_updated' => $kycUpdated
        ]);
    }

    /**
     * Logout: revoke current Bearer token. Requires authentication via Bearer token.
     */
    public function logout(Request $request)
    {
        $customer = $request->user('customer-api');

        if ($customer) {
            $customer->currentAccessToken()->delete();
        }

        return response()->json([
            'code' => 200,
            'message' => 'Logged out successfully',
        ]);
    }
}
