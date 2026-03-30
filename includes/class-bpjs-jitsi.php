<?php
/**
 * @author  Peter Grundner <peter.grundner@murbit.at>
 * @date    September 2025
 * @license MIT License – https://opensource.org/licenses/MIT
 *
 * Gefördert durch: Community of Practice AI KA210 – VET 4603C73C
 * Finanziert von der Europäischen Union. Die geäußerten Ansichten und Meinungen
 * entsprechen ausschließlich denen des Autors und spiegeln nicht zwingend die der
 * Europäischen Union oder der OeAD-GmbH wider.
 */
defined('ABSPATH') || exit;

class BPJS_Jitsi {

    /**
     * Jitsi-Server-URL aus den Einstellungen.
     * Bei JaaS: https://8x8.vc/{app_id}
     */
    public static function server_url(): string {
        if (get_option('bpjs_mode', 'direct') === 'jaas') {
            $app_id = get_option('bpjs_jaas_app_id', '');
            if ($app_id) return 'https://8x8.vc/' . $app_id;
        }
        return rtrim(get_option('bpjs_server_url', 'https://meet.jit.si'), '/');
    }

    /**
     * Raumname generieren.
     */
    public static function generate_room_name(string $username): string {
        $slug = sanitize_title($username);
        $uid  = substr(md5(uniqid('', true)), 0, 8);
        return "sparring-{$slug}-{$uid}";
    }

    /**
     * Join-URL für Host (Moderator) oder Gast bauen.
     * Bei JaaS: JWT anhängen.
     * Ohne JaaS: direkter Link.
     */
    public static function build_room_url(string $room_name, int $session_id = 0, bool $is_moderator = false, int $user_id = 0): string {
        $base = self::server_url() . '/' . rawurlencode($room_name);

        $app_id = get_option('bpjs_mode', 'direct') === 'jaas' ? get_option('bpjs_jaas_app_id', '') : '';
        if ($app_id && $user_id) {
            $jwt = BPJS_JWT::generate($user_id, $room_name, $is_moderator);
            if ($jwt) {
                return $base . '?jwt=' . $jwt;
            }
        }

        return $base;
    }

    /**
     * Raum anlegen und URLs zurückgeben (Host + Gast separat).
     */
    public static function create_room(int $user_id): array {
        $user      = get_userdata($user_id);
        $username  = $user ? $user->user_login : 'user' . $user_id;
        $room_name = self::generate_room_name($username);

        return [
            'room_name'  => $room_name,
            // Host-URL (Moderator-JWT)
            'room_url'   => self::build_room_url($room_name, 0, true,  $user_id),
            // Gast-URL (Gast-JWT oder direkter Link)
            'guest_url'  => self::build_room_url($room_name, 0, false, 0),
        ];
    }
}
