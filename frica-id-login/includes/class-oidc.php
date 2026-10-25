<?php
namespace FricaID\WordPress;

use FricaID\Vendor\Firebase\JWT\JWT;
use FricaID\Vendor\Firebase\JWT\JWK;

if (!defined('ABSPATH')) { exit; }

final class OIDC {
    public const ISSUER = 'https://frica.id';
    public const API = 'https://api.frica.id';

    public static function random(): string {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function challenge(string $verifier): string {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function request(string $path, array $args = []): array {
        // Endpoints are fixed, HTTPS only; never follow remote redirects or disable TLS validation.
        $response = wp_remote_request(self::API . $path, array_merge([
            'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
            'limit_response_size' => 131072, 'headers' => ['Accept' => 'application/json'],
        ], $args));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new \RuntimeException('provider_unavailable');
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) { throw new \RuntimeException('invalid_response'); }
        return $data;
    }

    public static function keys(bool $refresh = false): array {
        $keys = $refresh ? false : get_transient('frica_id_jwks');
        if (!is_array($keys)) {
            $data = self::request('/.well-known/jwks.json');
            $keys = [];
            foreach (($data['keys'] ?? []) as $key) {
                if (is_array($key) && ($key['kty'] ?? '') === 'RSA' && ($key['use'] ?? 'sig') === 'sig'
                    && ($key['alg'] ?? 'RS256') === 'RS256' && !empty($key['kid']) && !isset($key['d'])) {
                    $keys[] = $key;
                }
            }
            if (!$keys) { throw new \RuntimeException('invalid_keys'); }
            set_transient('frica_id_jwks', $keys, 3600);
        }
        return ['keys' => $keys];
    }

    public static function validate_id_token(string $token, string $client, string $nonce): array {
        if (strlen($token) > 32768) { throw new \RuntimeException('invalid_token'); }
        // The bundled JWT library enforces the signature, RS256 algorithm and temporal checks.
        // Refresh once for key rotation; never trust token-supplied URLs or keys.
        $claims = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $claims = (array) JWT::decode($token, JWK::parseKeySet(self::keys($attempt === 1), 'RS256'));
                break;
            } catch (\Throwable $error) {
                if ($attempt === 1) { throw new \RuntimeException('invalid_token'); }
            }
        }
        $now = time();
        $aud = $claims['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];
        if (($claims['iss'] ?? '') !== self::ISSUER || !in_array($client, $audiences, true)
            || (count($audiences) > 1 && ($claims['azp'] ?? '') !== $client)
            || (isset($claims['azp']) && $claims['azp'] !== $client)
            || !is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255
            || !is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])
            || !is_int($claims['exp'] ?? null) || $claims['exp'] <= $now
            || !is_int($claims['iat'] ?? null) || $claims['iat'] > $now + 30 || $claims['iat'] < $now - 1200
            || ($claims['token_use'] ?? '') !== 'id') {
            throw new \RuntimeException('invalid_claims');
        }
        return $claims;
    }

    public static function authenticate(string $code, string $client, array $flow): array {
        $tokens = self::request('/oauth/token', [
            'method' => 'POST', 'body' => [
                'grant_type' => 'authorization_code', 'client_id' => $client, 'code' => $code,
                'redirect_uri' => $flow['callback'], 'code_verifier' => $flow['verifier'],
            ],
        ]);
        try {
            if (!is_string($tokens['id_token'] ?? null) || !is_string($tokens['access_token'] ?? null)
                || strtolower($tokens['token_type'] ?? '') !== 'bearer') {
                throw new \RuntimeException('invalid_response');
            }
            $claims = self::validate_id_token($tokens['id_token'], $client, $flow['nonce']);
            $profile = self::request('/oauth/userinfo', [
                'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $tokens['access_token']],
            ]);
            if (!is_string($profile['sub'] ?? null) || !hash_equals($claims['sub'], $profile['sub'])) {
                throw new \RuntimeException('subject_mismatch');
            }
            if (($profile['email_verified'] ?? false) !== true || !is_string($profile['email'] ?? null)
                || !is_email($profile['email'])) {
                throw new \RuntimeException('email_unverified');
            }
            return $profile;
        } finally {
            // Frica currently returns a refresh token even without offline_access. Never retain it.
            if (is_string($tokens['refresh_token'] ?? null)) {
                wp_remote_post(self::API . '/oauth/revoke', [
                    'timeout' => 5, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 1024,
                    'body' => ['token' => $tokens['refresh_token'], 'client_id' => $client],
                ]);
            }
        }
    }
}
