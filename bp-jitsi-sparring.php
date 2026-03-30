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
/**
 * Plugin Name: BP Jitsi Sparring
 * Plugin URI:  https://github.com/your-repo/bp-jitsi-sparring
 * Description: Spontane Jitsi-Sparring-Sessions für BuddyPress-Communities.
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: bp-jitsi-sparring
 */

defined('ABSPATH') || exit;

define('BPJS_VERSION',   '1.0.0');
define('BPJS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BPJS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Abhängigkeiten prüfen
function bpjs_check_dependencies() {
    if (!function_exists('buddypress')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>BP Jitsi Sparring:</strong> BuddyPress ist erforderlich.</p></div>';
        });
        return false;
    }
    return true;
}

// Alle Klassen laden
function bpjs_load() {
    if (!bpjs_check_dependencies()) return;

    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-db.php';
    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-jwt.php';
    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-jitsi.php';
    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-notifications.php';
    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-profile-settings.php';
    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-activity.php';
    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-rest-api.php';
    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-settings.php';

    BPJS_Activity::init();
    BPJS_REST_API::init();
    BPJS_Settings::init();
    BPJS_Profile_Settings::init();

    // Frontend-Assets laden
    add_action('wp_enqueue_scripts', 'bpjs_enqueue_assets');

    // Custom Join-URL: /bpjs-join/?room=...
    add_action('init',     'bpjs_register_rewrite');
    add_action('template_redirect', 'bpjs_handle_join_page');
}
add_action('plugins_loaded', 'bpjs_load');

function bpjs_enqueue_assets() {
    if (!is_user_logged_in()) return;
    wp_enqueue_style('bpjs-style', BPJS_PLUGIN_URL . 'assets/sparring.css', [], BPJS_VERSION);
    wp_enqueue_script('bpjs-script', BPJS_PLUGIN_URL . 'assets/sparring.js', ['jquery'], BPJS_VERSION, true);
    wp_localize_script('bpjs-script', 'bpjsData', [
        'restUrl'  => esc_url_raw(rest_url('bpjs/v1/')),
        'nonce'    => wp_create_nonce('wp_rest'),
        'userId'   => get_current_user_id(),
        'pollInterval' => 15000, // 15 Sekunden
    ]);
}

/** Rewrite-Tag für /bpjs-join/ */
function bpjs_register_rewrite(): void {
    add_rewrite_tag('%bpjs_join%', '([^&]+)');
    add_rewrite_rule('^bpjs-join/?$', 'index.php?bpjs_join=1', 'top');
}

/** Join-Seite ausliefern */
function bpjs_handle_join_page(): void {
    if (!get_query_var('bpjs_join')) return;
    include BPJS_PLUGIN_DIR . 'templates/join-room.php';
    exit;
}

// Aktivierung: DB-Tabelle erstellen
register_activation_hook(__FILE__, function() {
    require_once BPJS_PLUGIN_DIR . 'includes/class-bpjs-db.php';
    BPJS_DB::create_table();
});
