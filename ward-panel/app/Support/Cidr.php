<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;

class Cidr
{
    public static function isValid(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (! str_contains($value, '/')) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        }

        [$ip, $prefix] = explode('/', $value, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix)) {
            return false;
        }

        $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
        $bits = (int) $prefix;

        return $bits >= 0 && $bits <= $max;
    }

    public static function normalize(string $value): string
    {
        $value = trim($value);

        if (! str_contains($value, '/')) {
            $bits = filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;

            return $value.'/'.$bits;
        }

        return $value;
    }

    public static function containsIp(string $cidr, string $ip): bool
    {
        return IpUtils::checkIp($ip, self::normalize($cidr));
    }

    public static function overlaps(string $first, string $second): bool
    {
        $a = self::normalize($first);
        $b = self::normalize($second);

        return self::containsIp($b, self::networkAddress($a))
            || self::containsIp($a, self::networkAddress($b));
    }

    public static function networkAddress(string $cidr): string
    {
        $normalized = self::normalize($cidr);
        [$ip] = explode('/', $normalized, 2);

        return $ip;
    }
}
