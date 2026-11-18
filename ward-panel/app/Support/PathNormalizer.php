<?php

namespace App\Support;

class PathNormalizer
{
    /**
     * @param  list<string>  $maskKeys
     */
    public static function normalize(string $path, array $maskKeys): string
    {
        $path = mb_substr($path, 0, 2048);

        $queryPos = mb_strpos($path, '?');

        if ($queryPos === false) {
            return $path;
        }

        $uri = mb_substr($path, 0, $queryPos);
        $query = mb_substr($path, $queryPos + 1);

        parse_str($query, $params);

        $masked = self::maskParams($params, $maskKeys);
        $encoded = http_build_query($masked);

        return $encoded === '' ? $uri : $uri.'?'.$encoded;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<string>  $maskKeys
     * @return array<string, mixed>
     */
    private static function maskParams(array $params, array $maskKeys): array
    {
        $lookup = [];

        foreach ($maskKeys as $key) {
            $lookup[mb_strtolower($key)] = true;
        }

        $masked = [];

        foreach ($params as $key => $value) {
            $name = mb_strtolower((string) $key);

            if (isset($lookup[$name])) {
                $masked[$key] = '***';

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $masked[$key] = self::maskParams($value, $maskKeys);

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }
}
