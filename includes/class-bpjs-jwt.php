<?php
/**
 * JWT-Generator für 8x8 JaaS (RS256)
 * Docs: https://developer.8x8.com/jaas/docs/api-keys-jwt
 */
defined('ABSPATH') || exit;

class BPJS_JWT {

    /**
     * JWT für einen User generieren.
     *
     * @param int    $user_id      WordPress User ID
     * @param string $room_name    Jitsi Raumname
     * @param bool   $is_moderator Host = true, Gast = false
     */
    public static function generate(int $user_id, string $room_name, bool $is_moderator = false): ?string {
        $app_id     = get_option('bpjs_jaas_app_id', '');
        $key_id     = get_option('bpjs_jaas_key_id', '');
        $private_key = get_option('bpjs_jaas_private_key', '');

        if (!$app_id || !$key_id || !$private_key) {
            return null; // Keine JaaS-Konfiguration → kein JWT
        }

        $user   = get_userdata($user_id);
        $name   = $user ? $user->display_name : 'Gast';
        $email  = $user ? $user->user_email   : '';
        $avatar = get_avatar_url($user_id, ['size' => 64]);

        $now = time();

        // Header
        $header = self::base64url_encode(json_encode([
            'alg' => 'RS256',
            'kid' => $app_id . '/' . $key_id,
            'typ' => 'JWT',
        ]));

        // Payload
        $payload = self::base64url_encode(json_encode([
            'iss'     => 'chat',
            'aud'     => 'jitsi',
            'iat'     => $now,
            'nbf'     => $now - 10,
            'exp'     => $now + 7200, // 2 Stunden gültig
            'sub'     => $app_id,
            'room'    => $room_name,
            'context' => [
                'user' => [
                    'id'        => (string) $user_id,
                    'name'      => $name,
                    'email'     => $email,
                    'avatar'    => $avatar,
                    'moderator' => $is_moderator,
                ],
                'features' => [
                    'livestreaming'     => false,
                    'outbound-call'     => false,
                    'transcription'     => false,
                    'recording'         => false,
                ],
            ],
        ]));

        // Signatur mit RS256
        $signing_input = $header . '.' . $payload;
        $pkey = openssl_pkey_get_private($private_key);
        if (!$pkey) {
            error_log('BPJS JWT: Ungültiger Private Key');
            return null;
        }

        $signature = '';
        if (!openssl_sign($signing_input, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            error_log('BPJS JWT: Signierung fehlgeschlagen');
            return null;
        }

        return $signing_input . '.' . self::base64url_encode($signature);
    }

    private static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
