<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\OtpSmsLog;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Send OTP to customer mobile. Finds customer by mobile_no, enforces daily limits,
     * creates otp_verifications record, updates customers OTP fields, sends SMS via Qikberry,
     * and logs to otp_sms_logs.
     *
     * @return array{success: bool, message: string, data?: array, error?: string}
     */
    public function sendOtp(string $mobile, ?string $ipAddress = null): array
    {
        $mobile = $this->normalizeMobile($mobile);
        $customer = Customer::where('mobile_no', $mobile)->first();

        if (! $customer) {
            return [
                'success' => false,
                'message' => 'Customer not found for the given mobile number.',
                'error' => 'CUSTOMER_NOT_FOUND',
            ];
        }

        $maxRequests = config('otp.max_requests');
        $resendCooldown = config('otp.resend_cooldown');
        $otpLastSentAt = $customer->otp_last_sent_at;

        if ($otpLastSentAt) {
            $startOfToday = now()->startOfDay();
            if ($otpLastSentAt->lt($startOfToday)) {
                $customer->otp_request_count = 0;
                $customer->saveQuietly();
            }
        }

        if ($customer->otp_request_count >= $maxRequests) {
            return [
                'success' => false,
                'message' => 'Maximum OTP requests reached for today. Please try again tomorrow.',
                'error' => 'OTP_MAX_REQUESTS_EXCEEDED',
            ];
        }

        if ($otpLastSentAt && $otpLastSentAt->addSeconds($resendCooldown)->isFuture()) {
            $waitSeconds = (int) $otpLastSentAt->addSeconds($resendCooldown)->diffInSeconds(now(), false);
            return [
                'success' => false,
                'message' => 'Please wait before requesting a new OTP.',
                'error' => 'OTP_RESEND_COOLDOWN',
                'retry_after_seconds' => max(0, $waitSeconds),
            ];
        }

        $otp = $this->generateOtp();
        $expiresAt = now()->addSeconds(config('otp.expiry_seconds'));

        // try {
            return DB::transaction(function () use (
                $customer,
                $mobile,
                $otp,
                $expiresAt,
                $ipAddress
            ): array {
                $otpVerification = OtpVerification::create([
                    'customer_id' => $customer->id,
                    'mobile' => $mobile,
                    'otp' => $otp,
                    'expires_at' => $expiresAt,
                    'request_count' => 1,
                    'verify_attempts' => 0,
                    'is_verified' => false,
                    'ip_address' => $ipAddress,
                ]);

                $customer->update([
                    'latest_otp' => $otp,
                    'otp_expires_at' => $expiresAt,
                    'otp_request_count' => $customer->otp_request_count + 1,
                    'otp_last_sent_at' => now(),
                ]);
                $customer->refresh();

                $sendResult = $this->sendSmsViaQikberry($mobile, $otp);

                OtpSmsLog::create([
                    'customer_id' => $customer->id,
                    'otp_verification_id' => $otpVerification->id,
                    'mobile' => $mobile,
                    'message_id' => $sendResult['message_id'] ?? null,
                    'sender' => $sendResult['sender'] ?? config('otp.qikberry.sender'),
                    'template_id' => $sendResult['template_id'] ?? config('otp.qikberry.template_id'),
                    'charges' => $sendResult['charges'] ?? null,
                    'request_payload' => $sendResult['request_payload'] ?? null,
                    'response_payload' => $sendResult['response_payload'] ?? null,
                    'status' => $sendResult['status'] ?? 'unknown',
                ]);

                return [
                    'success' => true,
                    'message' => 'OTP sent successfully.',
                    'data' => [
                        'expires_in_seconds' => config('otp.expiry_seconds'),
                        'mobile' => $this->maskMobile($mobile),
                    ],
                ];
            });
        // } catch (\Throwable $e) {
        //     Log::error('OTP send failed', [
        //         'mobile' => $this->maskMobile($mobile),
        //         'error' => $e->getMessage(),
        //         'trace' => $e->getTraceAsString(),
        //     ]);

        //     return [
        //         'success' => false,
        //         'message' => 'Failed to send OTP. Please try again later.',
        //         'error' => 'OTP_SEND_FAILED',
        //     ];
        // }
    }

    /**
     * Verify OTP for a customer by mobile and plain OTP. Uses latest unverified
     * otp_verifications record, checks expiry and attempt limits, then compares with stored OTP.
     *
     * @return array{success: bool, message: string, data?: array, error?: string}
     */
    public function verifyOtp(string $mobile, string $otp, ?string $ipAddress = null): array
    {
        $mobile = $this->normalizeMobile($mobile);
        $customer = Customer::where('mobile_no', $mobile)->first();

        if (! $customer) {
            return [
                'success' => false,
                'message' => 'Customer not found for the given mobile number.',
                'error' => 'CUSTOMER_NOT_FOUND',
            ];
        }

        // Testing bypass: allow OTP verification with a fixed master code.
        // Enabled automatically in non-production; can be forced via env var.
        $masterCode = (string) env('OTP_TEST_MASTER_CODE', '1234');
        $enabledEnv = env('OTP_TEST_BYPASS_ENABLED');
        $bypassEnabled = $enabledEnv !== null
            ? filter_var($enabledEnv, FILTER_VALIDATE_BOOLEAN)
            : ! app()->environment('production');

        if ($bypassEnabled && hash_equals($masterCode, (string) $otp)) {
            return [
                'success' => true,
                'message' => 'OTP verified successfully.',
                'data' => [
                    'mobile' => $this->maskMobile($mobile),
                    'verified_at' => now()->toIso8601String(),
                ],
            ];
        }

        $maxVerifyAttempts = config('otp.max_verify_attempts');

        $otpVerification = OtpVerification::where('customer_id', $customer->id)
            ->where('is_verified', false)
            ->orderByDesc('id')
            ->first();

        if (! $otpVerification) {
            return [
                'success' => false,
                'message' => 'No pending OTP found. Please request a new OTP.',
                'error' => 'NO_PENDING_OTP',
            ];
        }

        if ($otpVerification->isExpired()) {
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new OTP.',
                'error' => 'OTP_EXPIRED',
            ];
        }

        if ($otpVerification->verify_attempts >= $maxVerifyAttempts) {
            return [
                'success' => false,
                'message' => 'Maximum verification attempts exceeded. Please request a new OTP.',
                'error' => 'OTP_MAX_VERIFY_ATTEMPTS_EXCEEDED',
            ];
        }

        // try {
            $valid = hash_equals((string) $otpVerification->otp, (string) $otp);

            if (! $valid) {
                $otpVerification->increment('verify_attempts');
                $customer->increment('otp_verify_attempts');

                $remaining = $maxVerifyAttempts - $otpVerification->fresh()->verify_attempts;

                return [
                    'success' => false,
                    'message' => 'Invalid OTP.',
                    // 'message' => 'Invalid OTP. ' . ($remaining > 0 ? "You have {$remaining} attempt(s) remaining." : 'Please request a new OTP.'),
                    'error' => 'INVALID_OTP',
                    'attempts_remaining' => max(0, $remaining),
                ];
            }

            DB::transaction(function () use ($otpVerification, $customer, $ipAddress): void {
                $otpVerification->update([
                    'is_verified' => true,
                    'ip_address' => $ipAddress,
                ]);

                $customer->update([
                    'latest_otp' => null,
                    'otp_expires_at' => null,
                    'otp_verify_attempts' => 0,
                ]);
            });

            return [
                'success' => true,
                'message' => 'OTP verified successfully.',
                'data' => [
                    'mobile' => $this->maskMobile($mobile),
                    'verified_at' => now()->toIso8601String(),
                ],
            ];
        // } catch (\Throwable $e) {
        //     Log::error('OTP verify failed', [
        //         'mobile' => $this->maskMobile($mobile),
        //         'error' => $e->getMessage(),
        //     ]);

        //     return [
        //         'success' => false,
        //         'message' => 'Verification failed. Please try again.',
        //         'error' => 'OTP_VERIFY_FAILED',
        //     ];
        // }
    }

    /**
     * Send SMS via Qikberry API. Builds message from template, posts to /v1/sms/messages.
     *
     * @return array{message_id?: string, sender?: string, template_id?: string, charges?: float, request_payload?: array, response_payload?: array, status: string}
     */
    protected function sendSmsViaQikberry(string $mobile, string $otp): array
    {
        $baseUrl = config('otp.qikberry.base_url');
        $apiKey = config('otp.qikberry.api_key');
        $sender = config('otp.qikberry.sender');
        $templateId = config('otp.qikberry.template_id');
        $service = config('otp.qikberry.service');
        $templateMessage = config('otp.qikberry.template_message');
        $message = str_replace('{#numeric#}', $otp, $templateMessage);

        $to = $this->formatE164($mobile);
        $requestPayload = [
            'to' => $to,
            'sender' => $sender,
            'service' => $service,
            'template_id' => $templateId,
            'message' => $message,
        ];
        // dd($requestPayload);

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->post("{$baseUrl}/v1/sms/messages", $requestPayload);

        $responsePayload = $response->json();
        $status = $response->successful() ? 'sent' : 'failed';
        $messageId = null;
        $charges = null;

        if ($response->successful() && isset($responsePayload['data'][0])) {
            $first = $responsePayload['data'][0];
            $messageId = $first['message_id'] ?? null;
            $charges = $first['charges'] ?? null;
        }

        return [
            'message_id' => $messageId,
            'sender' => $sender,
            'template_id' => $templateId,
            'charges' => $charges,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'status' => $status,
        ];
    }

    protected function generateOtp(): string
    {
        return (string) random_int(1000, 9999);
    }

    protected function normalizeMobile(string $mobile): string
    {
        $mobile = preg_replace('/\D/', '', $mobile);
        if (strlen($mobile) === 10 && ! str_starts_with($mobile, '0')) {
            return $mobile;
        }
        if (strlen($mobile) === 12 && str_starts_with($mobile, '91')) {
            return substr($mobile, 2);
        }
        return $mobile;
    }

    protected function formatE164(string $mobile): string
    {
        $mobile = $this->normalizeMobile($mobile);
        return '+91' . $mobile;
    }

    protected function maskMobile(string $mobile): string
    {
        $mobile = $this->normalizeMobile($mobile);
        if (strlen($mobile) < 4) {
            return '****';
        }
        return substr($mobile, 0, 2) . '****' . substr($mobile, -2);
    }
}
