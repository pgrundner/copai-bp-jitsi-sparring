<?php
defined('ABSPATH') || exit;

class BPJS_Notifications {

    /**
     * Community oder Gruppe über eine neue Sparring-Session informieren.
     */
    public static function notify(object $session): void {
        // E-Mail-Benachrichtigungen deaktiviert?
        if (!get_option('bpjs_email_enabled', 1)) return;

        $recipients = self::get_recipients($session);
        if (empty($recipients)) return;

        $host      = get_userdata($session->user_id);
        $host_name = $host ? $host->display_name : __('Jemand', 'bp-jitsi-sparring');
        $subject   = self::render_subject($host_name, $session);
        $template  = get_option('bpjs_email_template', self::default_template());

        foreach ($recipients as $user) {
            // Host selbst keine Mail schicken
            if ((int) $user->ID === (int) $session->user_id) continue;

            $body = self::render_body($template, $user->display_name, $host_name, $session);
            wp_mail($user->user_email, $subject, $body, self::headers());
        }
    }

    /** Empfänger je nach Scope ermitteln (ohne Opt-outs) */
    private static function get_recipients(object $session): array {
        if ($session->scope === 'group' && $session->group_id && function_exists('groups_get_group_members')) {
            $result = groups_get_group_members(['group_id' => $session->group_id, 'per_page' => 500]);
            $members = $result['members'] ?? [];
        } else {
            $members = get_users(['fields' => ['ID', 'display_name', 'user_email'], 'number' => 1000]);
        }

        // Opt-outs herausfiltern
        return array_filter($members, function ($user) {
            return !get_user_meta($user->ID, 'bpjs_email_optout', true);
        });
    }

    /** Opt-out für den aktuellen User setzen/entfernen */
    public static function set_optout(int $user_id, bool $optout): void {
        if ($optout) {
            update_user_meta($user_id, 'bpjs_email_optout', 1);
        } else {
            delete_user_meta($user_id, 'bpjs_email_optout');
        }
    }

    public static function is_optout(int $user_id): bool {
        return (bool) get_user_meta($user_id, 'bpjs_email_optout', true);
    }

    /** Betreff rendern */
    private static function render_subject(string $host_name, object $session): string {
        /* translators: %s = Displayname des Hosts */
        return sprintf(
            get_option('bpjs_email_subject', '🔴 Spontanes Sparring von %s – jetzt live!'),
            $host_name
        );
    }

    /** Template-Platzhalter ersetzen */
    private static function render_body(string $template, string $recipient, string $host, object $session): string {
        return str_replace(
            ['{recipient_name}', '{username}', '{topic}', '{category}', '{room_url}'],
            [esc_html($recipient), esc_html($host), esc_html($session->topic), esc_html($session->category), esc_url($session->room_url)],
            $template
        );
    }

    private static function headers(): array {
        return ['Content-Type: text/plain; charset=UTF-8'];
    }

    /** Standard-E-Mail-Template */
    public static function default_template(): string {
        return "Hallo {recipient_name},

{username} sucht gerade einen Sparring-Partner!

Thema:    {topic}
Kategorie: {category}

👉 Jetzt beitreten: {room_url}

Viel Spaß beim Austausch!
Dein Community-Team";
    }
}
