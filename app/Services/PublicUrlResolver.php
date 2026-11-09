<?php

namespace App\Services;

use Closure;

/**
 * Validates an external HTTP URL and resolves every address before a fetch.
 *
 * @phpstan-type ResolvedPublicUrl array{url: string, host: string, port: int, addresses: list<string>, literal: bool}
 */
class PublicUrlResolver
{
    /** @var Closure(string): array<int, mixed> */
    private Closure $dnsLookup;

    /**
     * @param  (Closure(string): array<int, mixed>)|null  $dnsLookup
     */
    public function __construct(?Closure $dnsLookup = null)
    {
        $this->dnsLookup = $dnsLookup ?? static function (string $host): array {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if (! is_array($records)) {
                return [];
            }

            $addresses = [];

            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $addresses[] = $record['ip'];
                }

                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }

            return array_values(array_unique($addresses));
        };
    }

    /**
     * @return array{url: string, host: string, port: int, addresses: list<string>, literal: bool}|null
     */
    public function resolve(string $url): ?array
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)
            || array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)) {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) {
            return null;
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $literal = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if ($host === '' || str_ends_with($host, '.')) {
            return null;
        }

        if (! $this->isSafeHostName($host, $literal)) {
            return null;
        }

        if ($literal) {
            $addresses = [$host];
        } else {
            try {
                $addresses = ($this->dnsLookup)($host);
            } catch (\Throwable) {
                return null;
            }
        }

        if ($addresses === [] || count($addresses) > 32) {
            return null;
        }

        $normalized = [];

        foreach ($addresses as $address) {
            if (! is_string($address) || ! $this->isPublicAddress($address)) {
                return null;
            }

            $packed = inet_pton($address);

            if ($packed === false) {
                return null;
            }

            $normalized[] = inet_ntop($packed);
        }

        return [
            'url' => $url,
            'host' => $host,
            'port' => $port,
            'addresses' => array_values(array_unique($normalized)),
            'literal' => $literal,
        ];
    }

    private function isSafeHostName(string $host, bool $literal): bool
    {
        if ($literal) {
            return $this->isPublicAddress($host);
        }

        if (preg_match('/^(?:(?:0x[0-9a-f]+)|[0-9]+)(?:\.(?:(?:0x[0-9a-f]+)|[0-9]+))*$/i', $host) === 1) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return false;
        }

        foreach (['localhost', 'local', 'internal', 'test', 'invalid', 'example', 'onion'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicAddress(string $address): bool
    {
        $packed = inet_pton($address);

        if ($packed === false || filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            foreach ([
                '0.0.0.0/8',
                '100.64.0.0/10',
                '192.0.0.0/24',
                '192.0.2.0/24',
                '192.88.99.0/24',
                '198.18.0.0/15',
                '198.51.100.0/24',
                '203.0.113.0/24',
                '224.0.0.0/4',
                '240.0.0.0/4',
            ] as $cidr) {
                if ($this->addressInCidr($address, $cidr)) {
                    return false;
                }
            }

            return true;
        }

        // Only global-unicast IPv6 (2000::/3) is eligible for an outbound fetch.
        if ((ord($packed[0]) & 0xE0) !== 0x20) {
            return false;
        }

        foreach ([
            '2001::/23',
            '2001:db8::/32',
            '2002::/16',
            '3fff::/20',
        ] as $cidr) {
            if ($this->addressInCidr($address, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function addressInCidr(string $address, string $cidr): bool
    {
        [$network, $prefixLength] = explode('/', $cidr, 2);
        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);

        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $prefixLength = (int) $prefixLength;
        $wholeBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }
}
