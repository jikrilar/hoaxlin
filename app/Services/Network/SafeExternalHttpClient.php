<?php

namespace App\Services\Network;

use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class SafeExternalHttpClient
{
    public function __construct(private readonly ExternalUrlGuard $guard) {}

    /**
     * @return array{response: Response, url: string}
     */
    public function get(string $url, PendingRequest $request, string $service): array
    {
        $maximumRedirects = max(0, (int) config('services.external_http.max_redirects', 3));
        $redirects = 0;
        $currentUrl = $url;
        $request->withOptions(['allow_redirects' => false]);

        while (true) {
            $this->guard->validate($currentUrl, $service);
            $response = $request->get($currentUrl);

            if (! $this->isRedirect($response)) {
                return ['response' => $response, 'url' => $currentUrl];
            }

            if ($redirects >= $maximumRedirects) {
                throw AiServiceException::permanent($service, 'Batas redirect URL terlampaui.');
            }

            $location = $response->header('Location');
            if (! is_string($location) || trim($location) === '') {
                throw AiServiceException::permanent($service, 'Redirect tidak memiliki tujuan yang valid.');
            }

            $currentUrl = $this->guard->resolveRedirect($currentUrl, trim($location), $service);
            $redirects++;
        }
    }

    private function isRedirect(Response $response): bool
    {
        return in_array($response->status(), [301, 302, 303, 307, 308], true);
    }
}
