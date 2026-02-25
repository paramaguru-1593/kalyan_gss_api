<?php

use App\Exceptions\ThirdPartyApiException;
use App\Services\ThirdPartyAuthService;
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

// Schedule::call(function (): void {
//     $auth = app(ThirdPartyAuthService::class);

//     dd($auth);
// })
// ->name('schedule:thirdparty-refresh-token')
// ->everyMinute();