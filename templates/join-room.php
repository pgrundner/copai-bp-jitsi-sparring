<?php
/**
 * @author  Peter Grundner <peter.grundner@murbit.at>
 * @date    September 2025
 * @license MIT License – https://opensource.org/licenses/MIT
 * @project Community of Practice AI 2023-2-AT01-KA210-VET-000169864
 *
 * Förderhinweis:
 * 
 * Von der Europäischen Union finanziert. Die geäußerten Ansichten und Meinungen entsprechen jedoch 
 * ausschließlich denen des Autors bzw. der Autoren und spiegeln nicht zwingend die der Europäischen 
 * Union oder der OeAD-GmbH wider. 
 * Weder die Europäische Union noch die OeAD-GmbH können dafür verantwortlich gemacht werden.
 */
/**
 * Jitsi Join-Seite mit iFrame API
 * Aufgerufen via: /bpjs-join/?room=RAUMNAME&session_id=ID
 */
defined('ABSPATH') || exit;

$room_name  = sanitize_text_field($_GET['room'] ?? '');
$session_id = absint($_GET['session_id'] ?? 0);
$server     = rtrim(get_option('bpjs_server_url', 'https://meet.jit.si'), '/');
$domain     = preg_replace('#^https?://#', '', $server);

if (!$room_name) {
    wp_die(__('Ungültiger Raumname.', 'bp-jitsi-sparring'));
}

$user      = wp_get_current_user();
$disp_name = $user->ID ? $user->display_name : __('Gast', 'bp-jitsi-sparring');
$avatar    = get_avatar_url($user->ID ?: 0, ['size' => 64]);

get_header();
?>
<style>
  body, html { margin: 0; padding: 0; background: #111; }
  #bpjs-meet-wrapper { position: fixed; inset: 0; display: flex; flex-direction: column; }
  #bpjs-meet-bar {
    background: #1d1d1d; color: #fff; padding: 8px 20px;
    font-size: 13px; display: flex; align-items: center; gap: 12px; flex-shrink: 0;
  }
  #bpjs-meet-bar a { color: #aaa; text-decoration: none; font-size: 12px; }
  #bpjs-meet-bar a:hover { color: #fff; }
  #jitsi-container { flex: 1; }
</style>

<div id="bpjs-meet-wrapper">
  <div id="bpjs-meet-bar">
    🎯 <strong><?php echo esc_html($room_name); ?></strong>
    &nbsp;|&nbsp;
    <a href="<?php echo esc_url(function_exists('bp_get_activity_directory_permalink') ? bp_get_activity_directory_permalink() : home_url()); ?>">
      ← <?php esc_html_e('Zurück zur Community', 'bp-jitsi-sparring'); ?>
    </a>
  </div>
  <div id="jitsi-container"></div>
</div>

<script src="https://<?php echo esc_js($domain); ?>/external_api.js"></script>
<script>
(function() {
  var api = new JitsiMeetExternalAPI(<?php echo json_encode($domain); ?>, {
    roomName: <?php echo json_encode($room_name); ?>,
    parentNode: document.getElementById('jitsi-container'),
    width:  '100%',
    height: '100%',
    userInfo: {
      displayName: <?php echo json_encode($disp_name); ?>,
      avatarURL:   <?php echo json_encode($avatar); ?>,
    },
    configOverwrite: {
      prejoinPageEnabled:  false,
      disableDeepLinking:  true,
      startWithAudioMuted: false,
      startWithVideoMuted: false,
      lobby: { enabled: false },
      enableLobbyChat:     false,
      autoKnockLobby:      false,
    },
    interfaceConfigOverwrite: {
      SHOW_JITSI_WATERMARK:      false,
      SHOW_WATERMARK_FOR_GUESTS: false,
      TOOLBAR_BUTTONS: [
        'microphone','camera','desktop','fullscreen','fodeviceselection',
        'hangup','profile','chat','settings','raisehand','videoquality',
        'filmstrip','tileview','mute-everyone',
      ],
    },
  });

  api.addEventListener('readyToClose', function() {
    <?php if ($session_id && is_user_logged_in()) : ?>
    fetch(<?php echo json_encode(rest_url('bpjs/v1/sessions/' . $session_id . '/end')); ?>, {
      method: 'POST',
      headers: { 'X-WP-Nonce': <?php echo json_encode(wp_create_nonce('wp_rest')); ?> },
    }).finally(function() {
      window.location = <?php echo json_encode(function_exists('bp_get_activity_directory_permalink') ? bp_get_activity_directory_permalink() : home_url()); ?>;
    });
    <?php else : ?>
    window.location = <?php echo json_encode(function_exists('bp_get_activity_directory_permalink') ? bp_get_activity_directory_permalink() : home_url()); ?>;
    <?php endif; ?>
  });
})();
</script>
<?php get_footer(); ?>
