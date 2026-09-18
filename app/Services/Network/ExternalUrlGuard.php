<?php

namespace App\Services\Network;

use App\Contracts\HostResolver;
use App\Exceptions\AiServiceException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class ExternalUrlGuard
{
    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * Validate an external HTTP target before a network request is made.
     */
    public function validate(string $url, string $service): string
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            $this->reject($service, 'URL tidak valid.');
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            $this->reject($service, 'URL tidak valid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            $this->reject($service, 'URL hanya boleh menggunakan HTTP atau HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $this->reject($service, 'URL dengan kredensial tidak diizinkan.');
        }

        if (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535)) {
            $this->reject($service, 'Port URL tidak valid.');
        }

        $normalizedHost = strtolower(rtrim($host, '.'));
        if ($this->isInternalHostname($normalizedHost)) {
            $this->reject($service, 'Host internal tidak diizinkan.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $addresses = $this->resolver->resolve($normalizedHost);
        }

        if ($addresses === []) {
            $this->reject($service, 'Host tidak dapat di-resolve.');
        }

        foreach ($addresses as $address) {
            if (! is_string($address) || ! $this->isPublicIp($address)) {
                $this->reject($service, 'URL mengarah ke jaringan privat atau terlarang.');
            }
        }

        return $url;
    }

    public function resolveRedirect(string $currentUrl, string $location, string $service): string
    {
        if ($location === '') {
            $this->reject($service, 'Redirect tidak memiliki tujuan yang valid.');
        }

        try {
            $resolved = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
        } catch (\Throwable $exception) {
            throw AiServiceException::permanent(
                $service,
                'Tujuan redirect tidak valid.',
                previous: $exception,
            );
        }

        return $this->validate($resolved, $service);
    }

    private function isInternalHostname(string $host): bool
    {
        foreach (['localhost', 'localhost.localdomain', 'metadata.google.internal', 'metadata.google'] as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                return true;
            }
        }

        foreach (['.local', '.internal', '.lan', '.home', '.home.arpa'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isPublicIp(string $address): bool
    {
        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        $packed = inet_pton($address);
        if ($packed === false) {
            return false;
        }

        // PHP's reserved-range flag does not reject multicast ranges.
        if (strlen($packed) === 4) {
            $firstOctet = ord($packed[0]);

            return $firstOctet < 224;
        }

        // ff00::/8 is IPv6 multicast.
        return ord($packed[0]) !== 0xFF;
    }

    private function reject(string $service, string $message): never
    {
        throw AiServiceException::permanent($service, $message);
    }
}
