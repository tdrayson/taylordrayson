<?php

namespace App\Support;

/**
 * Whether a URL handed to us by a stranger is safe to go and fetch.
 *
 * A webmention endpoint fetches whatever `source` says, which without this is
 * a request forger: point it at 169.254.169.254 or a service on localhost and
 * the server makes the call on the sender's behalf.
 *
 * DNS is resolved and every answer checked, so a hostname cannot be used to
 * launder a private address. A rebinding attack between this check and the
 * fetch is still theoretically open; pinning the resolved IP into the request
 * is the fix if that ever stops being theoretical.
 */
final class SafeUrl
{
    /** @var list<string> */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '224.0.0.0/4', '240.0.0.0/4',
    ];

    public static function fetchable(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'] ?? '';

        if ($host === '') {
            return false;
        }

        $addresses = self::addressesFor($host);

        // An unresolvable host is not fetchable either, and returning false
        // here means the loop below cannot vacuously pass it.
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every address the host resolves to, or the literal itself when the host
     * is already an IP.
     *
     * @return list<string>
     */
    private static function addressesFor(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }

    private static function isPublic(string $address): bool
    {
        // The flags cover IPv6 and the well-known v4 ranges; the explicit list
        // below catches the ones PHP does not treat as reserved, notably the
        // 169.254 link-local block cloud metadata services live on.
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (str_contains($address, ':')) {
            return true;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if (self::inRange($address, $range)) {
                return false;
            }
        }

        return true;
    }

    private static function inRange(string $address, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);

        $mask = -1 << (32 - (int) $bits);

        return (ip2long($address) & $mask) === (ip2long($subnet) & $mask);
    }
}
