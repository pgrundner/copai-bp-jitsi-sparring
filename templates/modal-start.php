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
?>
<?php defined('ABSPATH') || exit; ?>

<div id="bpjs-modal" class="bpjs-modal" role="dialog" aria-modal="true" aria-labelledby="bpjs-modal-title" hidden>
    <div class="bpjs-modal-backdrop"></div>
    <div class="bpjs-modal-box">
        <button class="bpjs-modal-close" aria-label="<?php esc_attr_e('Schließen', 'bp-jitsi-sparring'); ?>">✕</button>
        <h2 id="bpjs-modal-title">🎯 <?php esc_html_e('Sparring starten', 'bp-jitsi-sparring'); ?></h2>
        <p class="bpjs-modal-desc"><?php esc_html_e('Starte eine spontane Session und lass die Community wissen, dass du einen Sparring-Partner suchst.', 'bp-jitsi-sparring'); ?></p>

        <form id="bpjs-start-form">
            <?php wp_nonce_field('bpjs_start', 'bpjs_nonce'); ?>

            <label for="bpjs-topic"><?php esc_html_e('Thema *', 'bp-jitsi-sparring'); ?></label>
            <input type="text" id="bpjs-topic" name="topic" required maxlength="255"
                   placeholder="<?php esc_attr_e('Worum geht es?', 'bp-jitsi-sparring'); ?>" />

            <label for="bpjs-category"><?php esc_html_e('Kategorie', 'bp-jitsi-sparring'); ?></label>
            <input type="text" id="bpjs-category" name="category" maxlength="100"
                   placeholder="<?php esc_attr_e('z.B. Business, Technik, Kreatives…', 'bp-jitsi-sparring'); ?>" />

            <label for="bpjs-scope"><?php esc_html_e('Sichtbarkeit', 'bp-jitsi-sparring'); ?></label>
            <select id="bpjs-scope" name="scope">
                <option value="community"><?php esc_html_e('Gesamte Community', 'bp-jitsi-sparring'); ?></option>
                <?php if (!empty($groups)) : ?>
                    <option value="group" disabled>── <?php esc_html_e('Nur Gruppe', 'bp-jitsi-sparring'); ?> ──</option>
                    <?php foreach ($groups as $g) : ?>
                        <option value="group" data-group-id="<?php echo (int) $g['id']; ?>">
                            <?php echo esc_html($g['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <label for="bpjs-max"><?php esc_html_e('Max. Teilnehmer (0 = unbegrenzt)', 'bp-jitsi-sparring'); ?></label>
            <input type="number" id="bpjs-max" name="max_participants" min="0" max="50" value="0" />

            <div class="bpjs-form-actions">
                <button type="submit" class="bpjs-btn-primary">
                    🔴 <?php esc_html_e('Session starten', 'bp-jitsi-sparring'); ?>
                </button>
                <button type="button" class="bpjs-btn-secondary bpjs-modal-close">
                    <?php esc_html_e('Abbrechen', 'bp-jitsi-sparring'); ?>
                </button>
            </div>

            <p class="bpjs-error" role="alert" hidden></p>
        </form>
    </div>
</div>

<!-- Trigger-Button (wird im BP-Header angezeigt) -->
<button class="bpjs-trigger-btn" id="bpjs-open-modal">
    🎯 <?php esc_html_e('Sparring starten', 'bp-jitsi-sparring'); ?>
</button>
