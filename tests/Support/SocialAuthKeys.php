<?php

namespace Tests\Support;

use Firebase\JWT\JWT;

/** Throwaway keys and JWKS for social sign-in tests. */
trait SocialAuthKeys
{
    /** @return array{private: string, public: string, n: string, e: string} */
    protected static function rsaKey(): array
    {
        $resource = openssl_pkey_new(self::opensslOptions(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]));
        openssl_pkey_export($resource, $private, null, self::opensslOptions());
        $details = openssl_pkey_get_details($resource);

        return [
            'private' => $private,
            'public' => $details['key'],
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];
    }

    /** @return array{private: string, public: string} P-256, like Apple's .p8 keys */
    protected static function ecKey(): array
    {
        $resource = openssl_pkey_new(self::opensslOptions(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']));
        openssl_pkey_export($resource, $private, null, self::opensslOptions());

        return ['private' => $private, 'public' => openssl_pkey_get_details($resource)['key']];
    }

    /**
     * @param  array<string, array{n: string, e: string}>  $keys  by kid
     * @return array{keys: list<array<string, string>>}
     */
    protected static function jwks(array $keys): array
    {
        $jwks = ['keys' => []];
        foreach ($keys as $kid => $key) {
            $jwks['keys'][] = ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $kid, 'n' => $key['n'], 'e' => $key['e']];
        }

        return $jwks;
    }

    /** @param array<string, mixed> $data */
    protected static function b64(array $data): string
    {
        return JWT::urlsafeB64Encode(json_encode($data));
    }

    /** @param array<string, mixed> $options */
    private static function opensslOptions(array $options = []): array
    {
        // Windows PHP builds (XAMPP) ship openssl.cnf next to php.exe but don't point OpenSSL at it.
        $bundledConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (getenv('OPENSSL_CONF') === false && is_file($bundledConfig)) {
            $options['config'] = $bundledConfig;
        }

        return $options;
    }
}
