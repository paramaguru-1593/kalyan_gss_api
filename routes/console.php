<?php

use App\Exceptions\DocumanApiException;
use App\Exceptions\ThirdPartyApiException;
use App\Models\CustomerLedgerCollection;
use App\Models\CustomerSchemeDetail;
use App\Models\SchemeEnrollment;
use App\Models\SchemePayment;
use App\Services\DocumanTokenService;
use App\Services\ThirdPartyAuthService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('thirdparty:refresh-token', function (): int {
    try {
        app(ThirdPartyAuthService::class)->refreshToken();
        $this->info('Third-party token refreshed. Check third_party_tokens table.');
        return self::SUCCESS;
    } catch (ThirdPartyApiException $e) {
        $this->error($e->getMessage());
        return self::FAILURE;
    }
})->purpose('Call login API and update third_party_tokens (access_token, expires_at, updated_at)');

Artisan::command('documan:refresh-token {name?}', function (?string $name = null): int {
    $name = $name ?? config('documan.default_token_name', 'default');
    try {
        app(DocumanTokenService::class)->refreshToken($name);
        $this->info("Docman token refreshed for name: {$name}. Check documan_access_tokens table.");
        return self::SUCCESS;
    } catch (DocumanApiException $e) {
        $this->error($e->getMessage());
        Log::warning('Docman refresh-token failed', ['name' => $name, 'message' => $e->getMessage()]);
        return self::FAILURE;
    }
})->purpose('Call Docman login API and create/update documan_access_tokens (expires_at = now + 1 day)');

/*
|--------------------------------------------------------------------------
| Refresh token only when expires_at is <= 5 minutes from now. Then update
| third_party_tokens (access_token, expires_at, updated_at) from login API.
| Otherwise no API call and no DB update.
|--------------------------------------------------------------------------
*/
Schedule::call(function (): void {
    try {
        $auth = app(ThirdPartyAuthService::class);
        $auth->getValidToken();
    } catch (ThirdPartyApiException $e) {
        Log::warning('Scheduled third-party token refresh failed', [
            'message' => $e->getMessage(),
        ]);
    }
})
->name('schedule:thirdparty-refresh-token')   // ✅ MUST COME FIRST
->withoutOverlapping()
->everyMinute();

/*
|--------------------------------------------------------------------------
| Docman: ensure valid token for default name (refreshes if within 5 min of expiry).
|--------------------------------------------------------------------------
*/
Schedule::call(function (): void {
    try {
        $name = config('documan.default_token_name', 'default');
        app(DocumanTokenService::class)->getValidToken($name);
    } catch (DocumanApiException $e) {
        Log::warning('Scheduled Docman token refresh failed', [
            'message' => $e->getMessage(),
        ]);
    }
})
->name('schedule:documan-refresh-token')
->withoutOverlapping()
->everyMinute();

// Schedule::call(function (): void {
//     $auth = app(ThirdPartyAuthService::class);

//     dd($auth);
// })
// ->name('schedule:thirdparty-refresh-token')
// ->everyMinute();

/*
|--------------------------------------------------------------------------
| Monthly customer ledger collection creation.
| Creates one row per enrollment for current EMI month if missing.
| payment_status = completed when payment exists, otherwise pending.
|--------------------------------------------------------------------------
*/
Artisan::command('ledger:create-monthly-collections', function (): int {
    $now = now();
    $emiMonth = strtoupper($now->format('M-Y'));
    $processed = 0;
    $created = 0;

    SchemeEnrollment::query()
        ->where(function ($query) {
            $query->whereNull('status')
                ->orWhereRaw('UPPER(status) <> ?', ['COMPLETED']);
        })
        ->orderBy('id')
        ->chunkById(200, function ($enrollments) use ($now, $emiMonth, &$processed, &$created) {
            foreach ($enrollments as $enrollment) {
                $processed++;

                $detail = CustomerSchemeDetail::query()->firstOrCreate(
                    ['enrollment_no' => (string) $enrollment->enrollment_id],
                    [
                        'customer_id' => $enrollment->customer_id,
                        'scheme_enrollment_id' => $enrollment->id,
                        'scheme_id' => $enrollment->scheme_id,
                        'scheme_name' => $enrollment->scheme_name,
                        'scheme_status' => $enrollment->status,
                        'join_date' => $enrollment->enrollment_date,
                        'maturity_date' => $enrollment->maturity_date,
                        'emi' => (float) $enrollment->installment_amount,
                        'paid_amount' => (float) $enrollment->paid_amount,
                        'remaining_amount' => (float) $enrollment->pending_amount,
                    ]
                );

                $exists = CustomerLedgerCollection::query()
                    ->where('customer_scheme_detail_id', $detail->id)
                    ->where('emi_month', $emiMonth)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $successPayment = SchemePayment::query()
                    ->where('scheme_enrollment_id', $enrollment->id)
                    ->whereRaw('UPPER(status) = ?', ['SUCCESS'])
                    ->whereBetween('payment_date', [
                        Carbon::parse($now)->startOfMonth(),
                        Carbon::parse($now)->endOfMonth(),
                    ])
                    ->orderByDesc('payment_date')
                    ->orderByDesc('id')
                    ->first();

                CustomerLedgerCollection::create([
                    'customer_scheme_detail_id' => $detail->id,
                    'reference_no' => $successPayment?->billdesk_reference,
                    'mop' => $successPayment ? 'Online' : null,
                    'remarks' => $successPayment ? 'Auto-created monthly entry (paid)' : 'Auto-created monthly entry',
                    'collection_date' => $successPayment?->payment_date?->toDateString(),
                    'amount' => $successPayment ? (float) $successPayment->amount : (float) $enrollment->installment_amount,
                    'issued_date' => $successPayment?->payment_date?->toDateString(),
                    'cheque_number' => null,
                    'emi_month' => $emiMonth,
                    'payment_status' => $successPayment ? 'completed' : 'pending',
                    'gold_rate' => null,
                    'gold_weight' => null,
                ]);

                $created++;
            }
        });

    $this->info("Monthly ledger sync done. Processed: {$processed}, Created: {$created}, EMI Month: {$emiMonth}");

    return self::SUCCESS;
})->purpose('Create monthly customer ledger collection rows with pending/completed status');

Schedule::command('ledger:create-monthly-collections')
    ->name('schedule:ledger-create-monthly-collections')
    ->withoutOverlapping()
    ->monthlyOn(1, '00:05');