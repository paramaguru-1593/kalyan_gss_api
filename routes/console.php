<?php

use App\Exceptions\DocumanApiException;
use App\Exceptions\ThirdPartyApiException;
use App\Services\DocumanTokenService;
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