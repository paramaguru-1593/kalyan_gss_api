<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Send OTP to the given mobile number.
     * Rate limited; customer must exist in customers table.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:50'],
        ]);

        // $mobile = $validated['mobile'];
        $mobile = 9361901823;

        $result = $this->otpService->sendOtp(
            $mobile,
            $request->ip()
        );

        $statusCode = $result['success'] ? 200 : 422;
        return response()->json($result, $statusCode);
    }

    /**
     * Verify OTP for the given mobile number.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:50'],
            'otp' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ], [
            'otp.required' => 'OTP is required.',
            'otp.size' => 'OTP must be 4 digits.',
            'otp.regex' => 'OTP must be numeric.',
        ]);

        // $mobile = $validated['mobile'];
        $mobile = 9361901823;

        $result = $this->otpService->verifyOtp(
            $mobile,
            $validated['otp'],
            $request->ip()
        );

        $statusCode = $result['success'] ? 200 : 422;
        return response()->json($result, $statusCode);
    }
}
