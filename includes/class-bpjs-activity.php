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

class BPJS_Activity {

    public static function init(): void {
        // "Sparring starten" Button im BuddyPress Member-Header
        add_action('bp_member_header_actions', [self::class, 'render_start_button']);
        // Button auch im eigenen Profil (Member-Plugin-Tab)
        add_action('bp_before_member_activity_post_form', [self::class, 'render_start_button_own']);
        // Button in der Gruppenansicht (Header + Activity-Tab)
        add_action('bp_group_header_actions',            [self::class, 'render_start_button_group']);
        add_action('bp_before_group_activity_post_form', [self::class, 'render_start_button_group']);
    }

    /** Button nur für eingeloggte User auf fremden Profilen */
    public static function render_start_button(): void {
        if (!is_user_logged_in()) return;
        self::output_button();
    }

    /**
     * Button in der Gruppenansicht — nur für eingeloggte Mitglieder der Gruppe.
     * Scope wird automatisch auf "group" vorbelegt + Gruppe vorausgewählt.
     */
    public static function render_start_button_group(): void {
        if (!is_user_logged_in()) return;
        if (!function_exists('groups_get_current_group')) return;

        $group = groups_get_current_group();
        if (!$group) return;

        // Nur Mitglieder der Gruppe sehen den Button
        if (!groups_is_user_member(get_current_user_id(), $group->id)) return;

        // Aktuelle Gruppe als einzige Option übergeben
        $groups = [['id' => $group->id, 'name' => $group->name]];

        // Default-Scope auf "group" setzen (JS liest data-default-scope)
        echo '<div data-bpjs-default-scope="group" data-bpjs-default-group="' . (int) $group->id . '">';
        include BPJS_PLUGIN_DIR . 'templates/modal-start.php';
        echo '</div>';
    }

    /** Button auf eigenem Profil */
    public static function render_start_button_own(): void {
        if (!is_user_logged_in()) return;
        if (!bp_is_my_profile()) return;
        self::output_button();
    }

    private static function output_button(): void {
        $groups = [];
        if (function_exists('groups_get_user_groups')) {
            $user_groups = groups_get_user_groups(get_current_user_id());
            if (!empty($user_groups['groups'])) {
                foreach ($user_groups['groups'] as $gid) {
                    $g = groups_get_group($gid);
                    if ($g) $groups[] = ['id' => $g->id, 'name' => $g->name];
                }
            }
        }
        include BPJS_PLUGIN_DIR . 'templates/modal-start.php';
    }

    /**
     * BuddyPress Activity-Eintrag erstellen.
     * Gibt die Activity-ID zurück.
     */
    public static function post_activity(object $session): int {
        if (!function_exists('bp_activity_add')) return 0;

        $user      = get_userdata($session->user_id);
        $name      = $user ? $user->display_name : 'Jemand';
        $content   = self::render_activity_content($session);

        $activity_id = bp_activity_add([
            'user_id'           => $session->user_id,
            'component'         => 'bpjs',
            'type'              => 'bpjs_sparring',
            'action'            => sprintf(
                '<a href="%s">%s</a> %s',
                bp_core_get_user_domain($session->user_id),
                esc_html($name),
                __('sucht einen Sparring-Partner', 'bp-jitsi-sparring')
            ),
            'content'           => $content,
            'hide_sitewide'     => false,
            'item_id'           => $session->id,
        ]);

        return (int) $activity_id;
    }

    /** HTML-Inhalt des Activity-Eintrags */
    public static function render_activity_content(object $session): string {
        $status_badge = $session->status === 'live'
            ? '<span class="bpjs-live-badge">🔴 LIVE</span>'
            : '<span class="bpjs-ended-badge">✅ Beendet</span>';

        $join_btn = $session->status === 'live'
            ? sprintf(
                '<a class="bpjs-join-btn" href="%s" target="_blank" rel="noopener noreferrer">%s</a>
                 <p class="bpjs-join-hint">%s</p>',
                esc_url($session->room_url),
                __('Jetzt beitreten', 'bp-jitsi-sparring'),
                __('💡 Der Host öffnet den Raum zuerst – danach einfach beitreten.', 'bp-jitsi-sparring')
              )
            : '';

        $topic    = esc_html($session->topic);
        $category = esc_html($session->category);

        return sprintf(
            '<div class="bpjs-activity-entry" data-session-id="%d">
                %s
                <div class="bpjs-topic"><strong>%s:</strong> %s</div>
                <div class="bpjs-category"><strong>%s:</strong> %s</div>
                <div class="bpjs-counter">👥 <span class="bpjs-participants">1</span> %s</div>
                %s
            </div>',
            (int) $session->id,
            $status_badge,
            __('Thema', 'bp-jitsi-sparring'), $topic,
            __('Kategorie', 'bp-jitsi-sparring'), $category,
            __('Teilnehmer aktiv', 'bp-jitsi-sparring'),
            $join_btn
        );
    }

    /**
     * Activity-Inhalt nach Session-Ende aktualisieren.
     */
    public static function update_activity_ended(int $activity_id, object $session): void {
        if (!function_exists('bp_activity_get') || !$activity_id) return;

        $session->status = 'ended';
        $new_content = self::render_activity_content($session);

        // BuddyPress Activity direkt per wpdb aktualisieren
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'bp_activity',
            ['content' => $new_content],
            ['id' => $activity_id]
        );
    }
}
