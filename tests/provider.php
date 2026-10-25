<?php
// Test-only provider transport. Tokens use real RS256 signatures; no real Frica accounts or network calls.
function frica_test_provider($preempt, $args, $url) {
    if (strpos($url, 'https://api.frica.id/') !== 0) { return $preempt; }
    $private = file_get_contents(dirname(rtrim(getenv('WP_ROOT'), '/')) . '/test-signing.pem');
    $key = openssl_pkey_get_private($private);
    $details = openssl_pkey_get_details($key);
    $encode = static function ($data) { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); };
    $path = wp_parse_url($url, PHP_URL_PATH);
    $data = [];
    if ($path === '/.well-known/jwks.json') {
        $data = ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => 'suite-key', 'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e'])]]];
    } elseif ($path === '/oauth/token') {
        $body = $args['body'];
        $spec = get_option('frica_suite_' . ($body['code'] ?? ''), []);
        if (!$spec || ($body['client_id'] ?? '') !== 'fcli_wordpress_suite'
            || ($body['redirect_uri'] ?? '') !== FricaID\WordPress\Plugin::callback_url()
            || FricaID\WordPress\OIDC::challenge($body['code_verifier'] ?? '') !== $spec['challenge']) {
            return ['response' => ['code' => 400], 'body' => '{}', 'headers' => []];
        }
        $claims = array_merge(['iss' => 'https://frica.id', 'sub' => $spec['sub'], 'aud' => 'fcli_wordpress_suite',
            'iat' => time(), 'exp' => time() + 900, 'nonce' => $spec['nonce'], 'token_use' => 'id'], $spec['claims'] ?? []);
        $jwt = FricaID\Vendor\Firebase\JWT\JWT::encode($claims, $private, 'RS256', 'suite-key');
        if (!empty($spec['tamper'])) { $parts = explode('.', $jwt); $parts[2][0] = $parts[2][0] === 'A' ? 'B' : 'A'; $jwt = implode('.', $parts); }
        $data = ['id_token' => $jwt, 'access_token' => $body['code'], 'token_type' => 'Bearer', 'refresh_token' => 'refresh-' . $body['code']];
    } elseif ($path === '/oauth/userinfo') {
        $code = substr($args['headers']['Authorization'] ?? '', 7);
        $spec = get_option('frica_suite_' . $code, []);
        $data = array_merge(['sub' => $spec['sub'], 'email' => $spec['email'], 'email_verified' => true, 'name' => 'Frica Member'], $spec['profile'] ?? []);
    } elseif ($path === '/oauth/revoke') {
        update_option('frica_suite_revoked', $args['body']['token']);
    } else { throw new RuntimeException('Unexpected provider endpoint'); }
    return ['response' => ['code' => 200], 'body' => wp_json_encode($data), 'headers' => []];
}
add_filter('pre_http_request', 'frica_test_provider', 10, 3);
