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
 * Opt-out Einstellung im BuddyPress-Profil (Notifications-Tab)
 * und im WP-Admin Profil.
 */
defined('ABSPATH') || exit;

class BPJS_Profile_Settings {

    public static function init(): void {
        // BuddyPress: im Notifications-Tab anzeigen
        add_action('bp_notification_settings', [self::class, 'bp_notification_field']);
        add_action('bp_core_save_notification_settings', [self::class, 'bp_save']);

        // WP-Admin Profil
        add_action('show_user_profile',  [self::class, 'admin_profile_field']);
        add_action('edit_user_profile',  [self::class, 'admin_profile_field']);
        add_action('personal_options_update',  [self::class, 'admin_profile_save']);
        add_action('edit_user_profile_update', [self::class, 'admin_profile_save']);
    }

    // ── BuddyPress Notifications-Tab ──────────────────────────────────

    public static function bp_notification_field(): void {
        $user_id = bp_displayed_user_id() ?: get_current_user_id();
        $optout  = BPJS_Notifications::is_optout($user_id);
        ?>
        <table class="notification-settings" id="bpjs-notifications">
            <thead>
                <tr>
                    <th class="title"><?php _e('Sparring-Benachrichtigungen', 'bp-jitsi-sparring'); ?></th>
                    <th class="yes"><?php _e('Ja', 'bp-jitsi-sparring'); ?></th>
                    <th class="no"><?php _e('Nein', 'bp-jitsi-sparring'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('E-Mail wenn jemand eine Sparring-Session startet', 'bp-jitsi-sparring'); ?></td>
                    <td class="yes">
                        <input type="radio" name="bpjs_email_optout" value="0" <?php checked($optout, false); ?> />
                    </td>
                    <td class="no">
                        <input type="radio" name="bpjs_email_optout" value="1" <?php checked($optout, true); ?> />
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public static function bp_save(): void {
        $user_id = get_current_user_id();
        if (!$user_id) return;
        if (!isset($_POST['bpjs_email_optout'])) return;

        $optout = (bool) intval($_POST['bpjs_email_optout']);
        BPJS_Notifications::set_optout($user_id, $optout);
    }

    // ── WP-Admin Profil ───────────────────────────────────────────────

    public static function admin_profile_field(\WP_User $user): void {
        $optout = BPJS_Notifications::is_optout($user->ID);
        ?>
        <h3><?php _e('Sparring-Benachrichtigungen', 'bp-jitsi-sparring'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php _e('E-Mail bei neuer Sparring-Session', 'bp-jitsi-sparring'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="bpjs_email_optout" value="1" <?php checked($optout); ?> />
                        <?php _e('Keine E-Mails bei Sparring-Sessions', 'bp-jitsi-sparring'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function admin_profile_save(int $user_id): void {
        if (!current_user_can('edit_user', $user_id)) return;
        $optout = isset($_POST['bpjs_email_optout']) && $_POST['bpjs_email_optout'] == '1';
        BPJS_Notifications::set_optout($user_id, $optout);
    }
}
