<?php
/**
 * @author  Peter Grundner <peter.grundner@murbit.at>
 * @date    September 2025
 * @license MIT License – https://opensource.org/licenses/MIT
 * @project Community of Practice AI KA210 – VET 4603C73C
 *
 * Förderhinweis:
 * 
 * Von der Europäischen Union finanziert. Die geäußerten Ansichten und Meinungen entsprechen jedoch 
 * ausschließlich denen des Autors bzw. der Autoren und spiegeln nicht zwingend die der Europäischen 
 * Union oder der OeAD-GmbH wider. 
 * Weder die Europäische Union noch die OeAD-GmbH können dafür verantwortlich gemacht werden.
 */
defined('ABSPATH') || exit;

class BPJS_REST_API {

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        $ns = 'bpjs/v1';

        register_rest_route($ns, '/sessions', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'create_session'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);

        register_rest_route($ns, '/sessions/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_session'],
            'permission_callback' => '__return_true',
        ]);

        // Frischen Gast-JWT holen (beim Beitritt)
        register_rest_route($ns, '/sessions/(?P<id>\d+)/join', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_join_url'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);

        register_rest_route($ns, '/sessions/(?P<id>\d+)/end', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'end_session'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);

        register_rest_route($ns, '/sessions/(?P<id>\d+)/feedback', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'submit_feedback'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    /** POST /sessions */
    public static function create_session(WP_REST_Request $request): WP_REST_Response {
        $user_id  = get_current_user_id();
        $topic    = sanitize_text_field($request->get_param('topic') ?? '');
        $category = sanitize_text_field($request->get_param('category') ?? '');
        $scope    = in_array($request->get_param('scope'), ['community', 'group'], true)
                    ? $request->get_param('scope') : 'community';
        $group_id = (int) ($request->get_param('group_id') ?? 0);
        $max_p    = max(0, min(50, (int) ($request->get_param('max_participants') ?? 0)));

        if (empty($topic)) {
            return new WP_REST_Response(['error' => __('Thema ist erforderlich.', 'bp-jitsi-sparring')], 400);
        }

        $room = BPJS_Jitsi::create_room($user_id);

        $session_id = BPJS_DB::insert([
            'user_id'          => $user_id,
            'room_name'        => $room['room_name'],
            'room_url'         => $room['guest_url'],
            'topic'            => $topic,
            'category'         => $category,
            'scope'            => $scope,
            'group_id'         => $group_id,
            'max_participants' => $max_p,
            'status'           => 'live',
        ]);

        $session     = BPJS_DB::get($session_id);
        $activity_id = BPJS_Activity::post_activity($session);
        if ($activity_id) {
            BPJS_DB::set_activity_id($session_id, $activity_id);
        }

        BPJS_Notifications::notify($session);

        // Host bekommt frischen Moderator-JWT
        $host_url = BPJS_Jitsi::build_room_url($room['room_name'], $session_id, true, $user_id);

        return new WP_REST_Response([
            'session_id'  => $session_id,
            'room_url'    => $host_url,
            'guest_url'   => $room['guest_url'],
            'activity_id' => $activity_id,
        ], 201);
    }

    /** GET /sessions/{id} */
    public static function get_session(WP_REST_Request $request): WP_REST_Response {
        $session = BPJS_DB::get((int) $request['id']);
        if (!$session) {
            return new WP_REST_Response(['error' => 'Not found'], 404);
        }
        $participants = (int) get_option("bpjs_participants_{$session->id}", 1);
        return new WP_REST_Response([
            'id'           => (int) $session->id,
            'status'       => $session->status,
            'topic'        => $session->topic,
            'category'     => $session->category,
            'room_url'     => $session->room_url,
            'participants' => $participants,
            'created_at'   => $session->created_at,
        ]);
    }

    /**
     * GET /sessions/{id}/join
     * Generiert frischen JWT für den eingeloggten User → gibt Join-URL zurück.
     */
    public static function get_join_url(WP_REST_Request $request): WP_REST_Response {
        $session = BPJS_DB::get((int) $request['id']);
        if (!$session || $session->status !== 'live') {
            return new WP_REST_Response(['error' => 'Session nicht verfügbar.'], 404);
        }

        $user_id  = get_current_user_id();
        $is_host  = (int) $session->user_id === $user_id;
        $join_url = BPJS_Jitsi::build_room_url($session->room_name, $session->id, $is_host, $user_id);

        return new WP_REST_Response(['url' => $join_url]);
    }

    /** POST /sessions/{id}/end */
    public static function end_session(WP_REST_Request $request): WP_REST_Response {
        $session = BPJS_DB::get((int) $request['id']);
        if (!$session) {
            return new WP_REST_Response(['error' => 'Not found'], 404);
        }
        if ((int) $session->user_id !== get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_REST_Response(['error' => 'Forbidden'], 403);
        }
        BPJS_DB::end_session($session->id);
        if ($session->activity_id) {
            $session->status = 'ended';
            BPJS_Activity::update_activity_ended($session->activity_id, $session);
        }
        return new WP_REST_Response(['success' => true]);
    }

    /** POST /sessions/{id}/feedback */
    public static function submit_feedback(WP_REST_Request $request): WP_REST_Response {
        $session = BPJS_DB::get((int) $request['id']);
        if (!$session || $session->status !== 'ended') {
            return new WP_REST_Response(['error' => 'Session nicht beendet.'], 400);
        }
        $rating  = max(1, min(5, (int) ($request->get_param('rating') ?? 3)));
        $comment = sanitize_textarea_field($request->get_param('comment') ?? '');
        add_comment_meta(0, "bpjs_feedback_{$session->id}_" . get_current_user_id(), [
            'rating'  => $rating,
            'comment' => $comment,
        ]);
        return new WP_REST_Response(['success' => true]);
    }
}
