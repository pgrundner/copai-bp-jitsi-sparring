<?php
defined('ABSPATH') || exit;

class BPJS_Settings {

    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function add_menu(): void {
        add_options_page(
            __('BP Jitsi Sparring', 'bp-jitsi-sparring'),
            __('BP Jitsi Sparring', 'bp-jitsi-sparring'),
            'manage_options',
            'bp-jitsi-sparring',
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void {
        register_setting('bpjs_settings', 'bpjs_mode',            ['sanitize_callback' => 'sanitize_text_field',    'default' => 'direct']);
        register_setting('bpjs_settings', 'bpjs_server_url',      ['sanitize_callback' => 'esc_url_raw',            'default' => 'https://meet.jit.si']);
        register_setting('bpjs_settings', 'bpjs_jaas_app_id',     ['sanitize_callback' => 'sanitize_text_field',    'default' => '']);
        register_setting('bpjs_settings', 'bpjs_jaas_key_id',     ['sanitize_callback' => 'sanitize_text_field',    'default' => '']);
        register_setting('bpjs_settings', 'bpjs_jaas_private_key',['sanitize_callback' => 'sanitize_textarea_field','default' => '']);
        register_setting('bpjs_settings', 'bpjs_email_enabled',   ['sanitize_callback' => 'absint',                 'default' => 1]);
        register_setting('bpjs_settings', 'bpjs_email_subject',   ['sanitize_callback' => 'sanitize_text_field',    'default' => '🔴 Spontanes Sparring von %s – jetzt live!']);
        register_setting('bpjs_settings', 'bpjs_email_template',  ['sanitize_callback' => 'sanitize_textarea_field','default' => BPJS_Notifications::default_template()]);
        register_setting('bpjs_settings', 'bpjs_default_scope',   ['sanitize_callback' => 'sanitize_text_field',    'default' => 'community']);

        add_settings_section('bpjs_server', __('Jitsi-Server', 'bp-jitsi-sparring'), [self::class, 'section_server'], 'bp-jitsi-sparring');
        add_settings_section('bpjs_email',  __('E-Mail',       'bp-jitsi-sparring'), '__return_false',                'bp-jitsi-sparring');

        add_settings_field('bpjs_mode',          __('Modus',             'bp-jitsi-sparring'), [self::class, 'field_mode'],          'bp-jitsi-sparring', 'bpjs_server');
        add_settings_field('bpjs_server_url',    __('Jitsi-Server URL',  'bp-jitsi-sparring'), [self::class, 'field_server_url'],    'bp-jitsi-sparring', 'bpjs_server');
        add_settings_field('bpjs_jaas_app_id',   'JaaS App ID',                                [self::class, 'field_jaas_app_id'],   'bp-jitsi-sparring', 'bpjs_server');
        add_settings_field('bpjs_jaas_key_id',   'JaaS Key ID',                                [self::class, 'field_jaas_key_id'],   'bp-jitsi-sparring', 'bpjs_server');
        add_settings_field('bpjs_jaas_private_key', 'JaaS Private Key',                        [self::class, 'field_jaas_private_key'], 'bp-jitsi-sparring', 'bpjs_server');
        add_settings_field('bpjs_default_scope', __('Standard-Scope',   'bp-jitsi-sparring'), [self::class, 'field_default_scope'], 'bp-jitsi-sparring', 'bpjs_server');
        add_settings_field('bpjs_email_enabled', __('E-Mails aktiviert','bp-jitsi-sparring'), [self::class, 'field_email_enabled'], 'bp-jitsi-sparring', 'bpjs_email');
        add_settings_field('bpjs_email_subject', __('E-Mail Betreff',   'bp-jitsi-sparring'), [self::class, 'field_email_subject'], 'bp-jitsi-sparring', 'bpjs_email');
        add_settings_field('bpjs_email_template','E-Mail Template',                            [self::class, 'field_email_template'],'bp-jitsi-sparring', 'bpjs_email');
    }

    // ── Sections ──

    public static function section_server(): void {
        echo '<p>' . __('Wähle ob du einen eigenen/öffentlichen Jitsi-Server (direkt) oder 8x8 JaaS mit JWT-Authentifizierung verwenden möchtest.', 'bp-jitsi-sparring') . '</p>';
    }

    // ── Felder ──

    public static function field_mode(): void {
        $val = get_option('bpjs_mode', 'direct');
        ?>
        <fieldset>
            <label style="display:block;margin-bottom:8px">
                <input type="radio" name="bpjs_mode" value="direct" <?php checked($val, 'direct'); ?> />
                <strong><?php _e('Direkt (kein JWT)', 'bp-jitsi-sparring'); ?></strong> —
                <?php _e('Beliebiger Jitsi-Server, kein Login nötig. Host muss zuerst beitreten.', 'bp-jitsi-sparring'); ?>
            </label>
            <label style="display:block">
                <input type="radio" name="bpjs_mode" value="jaas" <?php checked($val, 'jaas'); ?> />
                <strong>8x8 JaaS (mit JWT)</strong> —
                <?php _e('Host wird automatisch Moderator, kein Lobby-Problem. Kostenlos auf', 'bp-jitsi-sparring'); ?>
                <a href="https://jaas.8x8.vc" target="_blank">jaas.8x8.vc</a>
            </label>
        </fieldset>
        <script>
        (function(){
            function toggle() {
                var mode = document.querySelector('input[name="bpjs_mode"]:checked').value;
                document.querySelectorAll('.bpjs-jaas-row').forEach(function(el){
                    el.closest('tr').style.display = (mode === 'jaas') ? '' : 'none';
                });
                document.querySelector('.bpjs-direct-row').closest('tr').style.display =
                    (mode === 'direct') ? '' : 'none';
            }
            document.querySelectorAll('input[name="bpjs_mode"]').forEach(function(r){
                r.addEventListener('change', toggle);
            });
            document.addEventListener('DOMContentLoaded', toggle);
            toggle();
        })();
        </script>
        <?php
    }

    public static function field_server_url(): void {
        $val = esc_url(get_option('bpjs_server_url', 'https://meet.jit.si'));
        echo "<span class='bpjs-direct-row'>";
        echo "<input type='url' name='bpjs_server_url' value='{$val}' class='regular-text' />";
        echo "<p class='description'>" . __('z.B.', 'bp-jitsi-sparring') . " <code>https://meet.jit.si</code>, <code>https://meet.ffmuc.net</code>, eigener Server …</p>";
        echo "</span>";
    }

    public static function field_jaas_app_id(): void {
        $val = esc_attr(get_option('bpjs_jaas_app_id', ''));
        echo "<span class='bpjs-jaas-row'>";
        echo "<input type='text' name='bpjs_jaas_app_id' value='{$val}' class='regular-text' placeholder='vpaas-magic-cookie-abc123' />";
        echo "</span>";
    }

    public static function field_jaas_key_id(): void {
        $val = esc_attr(get_option('bpjs_jaas_key_id', ''));
        echo "<span class='bpjs-jaas-row'>";
        echo "<input type='text' name='bpjs_jaas_key_id' value='{$val}' class='regular-text' placeholder='vpaas-magic-cookie-abc123/meinkey' />";
        echo "</span>";
    }

    public static function field_jaas_private_key(): void {
        $val = esc_textarea(get_option('bpjs_jaas_private_key', ''));
        echo "<span class='bpjs-jaas-row'>";
        echo "<textarea name='bpjs_jaas_private_key' rows='8' cols='60' placeholder='-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----'>{$val}</textarea>";
        echo "<p class='description'>PEM-Format (RSA Private Key aus dem JaaS Dashboard)</p>";
        echo "</span>";
    }

    public static function field_default_scope(): void {
        $val = get_option('bpjs_default_scope', 'community');
        echo "<select name='bpjs_default_scope'>";
        echo "<option value='community'" . selected($val, 'community', false) . ">" . __('Community-weit', 'bp-jitsi-sparring') . "</option>";
        echo "<option value='group'"     . selected($val, 'group',     false) . ">" . __('Nur Gruppe',     'bp-jitsi-sparring') . "</option>";
        echo "</select>";
    }

    public static function field_email_enabled(): void {
        $val = get_option('bpjs_email_enabled', 1);
        echo "<input type='checkbox' name='bpjs_email_enabled' value='1'" . checked(1, $val, false) . " />";
    }

    public static function field_email_subject(): void {
        $val = esc_attr(get_option('bpjs_email_subject', '🔴 Spontanes Sparring von %s – jetzt live!'));
        echo "<input type='text' name='bpjs_email_subject' value='{$val}' class='regular-text' />";
        echo "<p class='description'><code>%s</code> = Name des Hosts</p>";
    }

    public static function field_email_template(): void {
        $val = esc_textarea(get_option('bpjs_email_template', BPJS_Notifications::default_template()));
        echo "<textarea name='bpjs_email_template' rows='10' cols='60'>{$val}</textarea>";
        echo "<p class='description'>Platzhalter: <code>{recipient_name} {username} {topic} {category} {room_url}</code></p>";
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('BP Jitsi Sparring – Einstellungen', 'bp-jitsi-sparring'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bpjs_settings');
                do_settings_sections('bp-jitsi-sparring');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
