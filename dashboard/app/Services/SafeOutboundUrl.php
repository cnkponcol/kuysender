<?php

namespace App\Services;

use InvalidArgumentException;

class SafeOutboundUrl
{
    public static function assert(string $url, bool $httpsOnly = true, array $allowedPrivateTargets = []): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) throw new InvalidArgumentException('Invalid URL.');
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) throw new InvalidArgumentException('Only HTTP/HTTPS URLs are allowed.');
        if (isset($parts['user']) || isset($parts['pass'])) throw new InvalidArgumentException('Credentials in URL are not allowed.');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $target = $port ? $host.':'.$port : $host;
        $allowedPrivateTargets = array_map('strtolower', $allowedPrivateTargets);
        $trustedPrivate = in_array($target, $allowedPrivateTargets, true) || in_array($host, $allowedPrivateTargets, true);

        if ($httpsOnly && $scheme !== 'https' && !$trustedPrivate) throw new InvalidArgumentException('HTTPS URL is required.');
        if (in_array($host, ['localhost', 'localhost.localdomain'], true) && !$trustedPrivate) throw new InvalidArgumentException('Local URLs are not allowed.');

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        if (!$records && filter_var($host, FILTER_VALIDATE_IP)) $records = [['ip' => $host]];
        if (!$records) throw new InvalidArgumentException('Host could not be resolved.');
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!$ip) continue;
            if (!$trustedPrivate && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Private or reserved network targets are not allowed.');
            }
        }
        return $url;
    }
}
