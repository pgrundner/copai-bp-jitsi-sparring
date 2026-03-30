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

class BPJS_DB {

    const TABLE = 'bp_jitsi_sessions';

    /**
     * Datenbanktabelle beim Plugin-Aktivieren anlegen.
     */
    public static function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            room_name   VARCHAR(255)    NOT NULL,
            room_url    VARCHAR(512)    NOT NULL,
            topic       VARCHAR(255)    NOT NULL DEFAULT '',
            category    VARCHAR(100)    NOT NULL DEFAULT '',
            scope       ENUM('community','group') NOT NULL DEFAULT 'community',
            group_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            max_participants TINYINT   NOT NULL DEFAULT 0,
            status      ENUM('live','ended') NOT NULL DEFAULT 'live',
            activity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME        NOT NULL,
            ended_at    DATETIME                 DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status  (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** Session speichern */
    public static function insert(array $data): int {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            array_merge(['created_at' => current_time('mysql')], $data)
        );
        return (int) $wpdb->insert_id;
    }

    /** Session nach ID holen */
    public static function get(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE id = %d", $id)
        );
    }

    /** Status einer Session aktualisieren */
    public static function end_session(int $id): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . self::TABLE,
            ['status' => 'ended', 'ended_at' => current_time('mysql')],
            ['id' => $id]
        );
    }

    /** Activity-ID speichern */
    public static function set_activity_id(int $session_id, int $activity_id): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . self::TABLE,
            ['activity_id' => $activity_id],
            ['id' => $session_id]
        );
    }

    /** Alle laufenden Sessions */
    public static function get_live(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE status = 'live' ORDER BY created_at DESC"
        ) ?: [];
    }
}
