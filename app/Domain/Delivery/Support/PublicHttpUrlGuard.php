<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

use RuntimeException;

final class PublicHttpUrlGuard
{
    public function resolve(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('Webhook destinations must use HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new RuntimeException('Webhook destinations cannot contain credentials or fragments.');
        }

        $port = (int) ($parts['port'] ?? 443);

        if ($port !== 443) {
            throw new RuntimeException('Webhook destinations must use HTTPS port 443.');
        }

        $host = strtolower((string) $parts['host']);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->resolveHost($host);
        $publicAddresses = array_values(array_filter($addresses, static fn (string $address): bool => filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false));

        if ($publicAddresses === []) {
            throw new RuntimeException('Webhook destination did not resolve to a public IP address.');
        }

        return [
            'url' => $url,
            'host' => $host,
            'port' => $port,
            'ip' => $publicAddresses[0],
        ];
    }

    private function resolveHost(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = (string) $record['ip'];
            } elseif (isset($record['ipv6'])) {
                $addresses[] = (string) $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
