<?php

namespace App\Support;

use RuntimeException;

class PolicySigner
{
    /**
     * @return array{secret: string, public: string}
     */
    public function keys(): array
    {
        $fromEnv = config('ward.policy_private_key');
        $publicEnv = config('ward.policy_public_key');

        if (is_string($fromEnv) && $fromEnv !== '' && is_string($publicEnv) && $publicEnv !== '') {
            return [
                'secret' => $fromEnv,
                'public' => $publicEnv,
            ];
        }

        $path = (string) config('ward.policy_key_path');

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (is_array($decoded) && isset($decoded['secret'], $decoded['public'])) {
                return [
                    'secret' => (string) $decoded['secret'],
                    'public' => (string) $decoded['public'],
                ];
            }
        }

        if (app()->isProduction()) {
            throw new RuntimeException('Policy signing keys are not configured.');
        }

        return $this->generateAndStore($path);
    }

    /**
     * @return array{secret: string, public: string}
     */
    public function generateAndStore(?string $path = null): array
    {
        $path ??= (string) config('ward.policy_key_path');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $pair = sodium_crypto_sign_keypair();
        $keys = [
            'secret' => sodium_bin2base64(sodium_crypto_sign_secretkey($pair), SODIUM_BASE64_VARIANT_ORIGINAL),
            'public' => sodium_bin2base64(sodium_crypto_sign_publickey($pair), SODIUM_BASE64_VARIANT_ORIGINAL),
        ];

        file_put_contents($path, json_encode($keys, JSON_THROW_ON_ERROR));
        chmod($path, 0600);

        return $keys;
    }

    public function sign(string $message): string
    {
        $keys = $this->keys();
        $secret = sodium_base642bin($keys['secret'], SODIUM_BASE64_VARIANT_ORIGINAL);

        return sodium_bin2base64(
            sodium_crypto_sign_detached($message, $secret),
            SODIUM_BASE64_VARIANT_ORIGINAL,
        );
    }

    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    public static function signingMessage(int $version, string $hash): string
    {
        return $version.'|'.$hash;
    }
}
