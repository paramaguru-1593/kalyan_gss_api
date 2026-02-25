<?php

namespace App\Services;

use App\Exceptions\ThirdPartyApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for third-party APIs. Ensures a valid Bearer token and attaches it to every request.
 */
class ThirdPartyApiService
{
    private const CONFIG_KEY = 'thirdparty.mykalyan';

    public function __construct(
        private readonly ThirdPartyAuthService $authService
    ) {
    }

    /**
     * GET request to the third-party API.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     * @throws ThirdPartyApiException
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('get', $path, ['query' => $query])->json() ?? [];
    }

    /**
     * GET request with access_token in query string (e.g. MyKalyan externals APIs).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     * @throws ThirdPartyApiException
     */
    public function getWithAccessTokenInQuery(string $path, array $query = []): array
    {
        $token = $this->authService->getValidToken();
        $query['access_token'] = $token;
        return $this->request('get', $path, ['query' => $query], false)->json() ?? [];
    }

    /**
     * POST request to the third-party API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws ThirdPartyApiException
     */
    public function post(string $path, array $data = []): array
    {
        return $this->request('post', $path, ['json' => $data])->json() ?? [];
    }

    /**
     * PUT request to the third-party API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws ThirdPartyApiException
     */
    public function put(string $path, array $data = []): array
    {
        return $this->request('put', $path, ['json' => $data])->json() ?? [];
    }

    /**
     * PATCH request to the third-party API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws ThirdPartyApiException
     */
    public function patch(string $path, array $data = []): array
    {
        return $this->request('patch', $path, ['json' => $data])->json() ?? [];
    }

    /**
     * DELETE request to the third-party API.
     *
     * @return array<string, mixed>
     * @throws ThirdPartyApiException
     */
    public function delete(string $path): array
    {
        return $this->request('delete', $path)->json() ?? [];
    }

    /**
     * Send a request with Bearer token (or optionally no auth header when token is in query).
     *
     * @param  array{ query?: array, json?: array, headers?: array }  $options
     * @param  bool  $attachBearer  When true, adds Authorization: Bearer {token}
     * @throws ThirdPartyApiException
     */
    public function request(string $method, string $path, array $options = [], bool $attachBearer = true): Response
    {
        $baseUrl = rtrim(config(self::CONFIG_KEY . '.base_url', ''), '/');
        $path = ltrim($path, '/');
        $url = $baseUrl . '/' . $path;

        $request = Http::timeout(30)
            ->acceptJson()
            ->withHeaders($options['headers'] ?? []);

        if ($attachBearer) {
            $token = $this->authService->getValidToken();
            $request = $request->withToken($token);
        }

        $hasJson = isset($options['json']);
        if ($hasJson) {
            $request = $request->asJson();
        }

        $response = match (strtolower($method)) {
            'get' => $request->get($url, $options['query'] ?? []),
            'post' => $request->post($url, $options['json'] ?? []),
            'put' => $request->put($url, $options['json'] ?? []),
            'patch' => $request->patch($url, $options['json'] ?? []),
            'delete' => $request->delete($url),
            default => throw new ThirdPartyApiException("Unsupported HTTP method: {$method}"),
        };

        if (! $response->successful()) {
            Log::warning('Third-party API request failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new ThirdPartyApiException(
                $response->json('error.message') ?? 'Third-party API request failed',
                $response->status(),
                null,
                $response->json(),
                $response->status()
            );
        }

        return $response;
    }
}
