/**
 * BP Jitsi Sparring – Frontend JS
 * Handles: Modal, Session-Start, Live-Polling, Session-Beenden, Feedback
 *
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

(function ($) {
    'use strict';

    const REST  = bpjsData.restUrl;
    const NONCE = bpjsData.nonce;
    const ME    = parseInt(bpjsData.userId, 10);
    const POLL  = parseInt(bpjsData.pollInterval, 10) || 15000;

    // ── Modal öffnen/schließen ───────────────────────────────────────────────

    $(document).on('click', '#bpjs-open-modal', function () {
        // Default-Scope + Gruppe aus Gruppen-Kontext übernehmen (falls vorhanden)
        var $wrapper = $(this).closest('[data-bpjs-default-scope]');
        if ($wrapper.length) {
            var defaultScope   = $wrapper.data('bpjs-default-scope');
            var defaultGroupId = $wrapper.data('bpjs-default-group');
            // Scope-Select auf "group" stellen und passende Option auswählen
            $('#bpjs-scope option').filter(function () {
                return $(this).val() === 'group' && parseInt($(this).data('group-id'), 10) === defaultGroupId;
            }).prop('selected', true);
        }
        $('#bpjs-modal').removeAttr('hidden');
        $('body').css('overflow', 'hidden');
        $('#bpjs-topic').focus();
    });

    $(document).on('click', '.bpjs-modal-close, .bpjs-modal-backdrop', function () {
        closeModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    function closeModal() {
        $('#bpjs-modal').attr('hidden', true);
        $('body').css('overflow', '');
    }

    // ── Session starten ─────────────────────────────────────────────────────

    $(document).on('submit', '#bpjs-start-form', function (e) {
        e.preventDefault();

        const $form  = $(this);
        const $btn   = $form.find('.bpjs-btn-primary');
        const $error = $form.find('.bpjs-error');

        // Gruppe ermitteln
        const $scopeOpt = $form.find('#bpjs-scope option:selected');
        const scope      = $form.find('#bpjs-scope').val() === 'group' && $scopeOpt.data('group-id')
                           ? 'group' : 'community';
        const groupId    = scope === 'group' ? ($scopeOpt.data('group-id') || 0) : 0;

        const payload = {
            topic:            $form.find('#bpjs-topic').val().trim(),
            category:         $form.find('#bpjs-category').val().trim(),
            scope:            scope,
            group_id:         groupId,
            max_participants: parseInt($form.find('#bpjs-max').val(), 10) || 0,
        };

        if (!payload.topic) {
            showError($error, 'Bitte gib ein Thema ein.');
            return;
        }

        $btn.prop('disabled', true).text('⏳ Starten…');
        $error.attr('hidden', true).text('');

        $.ajax({
            url:    REST + 'sessions',
            method: 'POST',
            data:   JSON.stringify(payload),
            contentType: 'application/json',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', NONCE);
            },
        })
        .done(function (res) {
            closeModal();
            // Host öffnet Moderator-URL (mit JWT), Seite lädt Activity-Eintrag
            window.open(res.room_url, '_blank', 'noopener,noreferrer');
            setTimeout(function () { location.reload(); }, 800);
        })
        .fail(function (xhr) {
            const msg = xhr.responseJSON && xhr.responseJSON.error
                        ? xhr.responseJSON.error
                        : 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
            showError($error, msg);
            $btn.prop('disabled', false).html('🔴 Session starten');
        });
    });

    // ── Live-Polling: Teilnehmerzahl aktualisieren ───────────────────────────

    function startPolling($entry) {
        const sessionId = parseInt($entry.data('session-id'), 10);
        if (!sessionId) return;

        setInterval(function () {
            $.getJSON(REST + 'sessions/' + sessionId)
             .done(function (data) {
                 $entry.find('.bpjs-participants').text(data.participants || 1);

                 // Session wurde beendet → Badge tauschen
                 if (data.status === 'ended') {
                     $entry.find('.bpjs-live-badge').replaceWith('<span class="bpjs-ended-badge">✅ Beendet</span>');
                     $entry.find('.bpjs-join-btn').remove();
                     $entry.find('.bpjs-end-btn').remove();
                 }
             });
        }, POLL);
    }

    // "Jetzt beitreten" → frischen JWT vom Server holen
    $(document).on('click', '.bpjs-join-btn', function (e) {
        e.preventDefault();
        var $btn       = $(this);
        var sessionId  = parseInt($btn.closest('.bpjs-activity-entry').data('session-id'), 10);
        if (!sessionId || !ME) {
            window.open($btn.attr('href'), '_blank', 'noopener,noreferrer');
            return;
        }
        $btn.text('⏳ Verbinde…');
        $.ajax({
            url: REST + 'sessions/' + sessionId + '/join',
            method: 'GET',
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', NONCE); },
        })
        .done(function (res) {
            window.open(res.url, '_blank', 'noopener,noreferrer');
            $btn.html('Jetzt beitreten');
        })
        .fail(function () {
            window.open($btn.attr('href'), '_blank', 'noopener,noreferrer');
            $btn.html('Jetzt beitreten');
        });
    });

    // Polling für alle Live-Einträge auf der Seite starten
    $(document).ready(function () {
        $('.bpjs-activity-entry').each(function () {
            if ($(this).find('.bpjs-live-badge').length) {
                startPolling($(this));
            }
        });

        // "Session beenden" Button für den Host einblenden
        renderEndButtons();
        renderFeedbackForms();
    });

    // ── Session beenden ─────────────────────────────────────────────────────

    function renderEndButtons() {
        // Nur wenn Nutzer eingeloggt ist, fügen wir ggf. End-Button hinzu.
        // Die User-ID wird per wp_localize_script übergeben.
        if (!ME) return;

        $('.bpjs-activity-entry').each(function () {
            const $entry    = $(this);
            const sessionId = parseInt($entry.data('session-id'), 10);
            if (!sessionId) return;

            // Prüfen ob dieser Nutzer der Host ist (via REST)
            $.getJSON(REST + 'sessions/' + sessionId).done(function (data) {
                if (data.status !== 'live') return;
                // Wir zeigen den Button für alle Live-Sessions –
                // der Server prüft dann, ob der User der Host ist.
                if ($entry.find('.bpjs-end-btn').length === 0) {
                    $entry.find('.bpjs-join-btn').after(
                        $('<button>')
                            .addClass('bpjs-end-btn')
                            .attr('data-session-id', sessionId)
                            .text('Session beenden')
                    );
                }
            });
        });
    }

    $(document).on('click', '.bpjs-end-btn', function () {
        if (!confirm('Session wirklich beenden?')) return;

        const $btn      = $(this);
        const sessionId = parseInt($btn.data('session-id'), 10);

        $btn.prop('disabled', true).text('⏳ Beende…');

        $.ajax({
            url:    REST + 'sessions/' + sessionId + '/end',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', NONCE);
            },
        })
        .done(function () {
            const $entry = $btn.closest('.bpjs-activity-entry');
            $entry.find('.bpjs-live-badge').replaceWith('<span class="bpjs-ended-badge">✅ Beendet</span>');
            $entry.find('.bpjs-join-btn').remove();
            $btn.remove();
            renderFeedbackForms();
        })
        .fail(function () {
            alert('Konnte Session nicht beenden. Bist du der Host?');
            $btn.prop('disabled', false).text('Session beenden');
        });
    });

    // ── Feedback ────────────────────────────────────────────────────────────

    function renderFeedbackForms() {
        $('.bpjs-activity-entry').each(function () {
            const $entry = $(this);
            if (!$entry.find('.bpjs-ended-badge').length) return;
            if ($entry.find('.bpjs-feedback').length) return;

            const sessionId = parseInt($entry.data('session-id'), 10);

            $entry.append(
                '<div class="bpjs-feedback">' +
                    '<h4>Wie war die Session?</h4>' +
                    '<div class="bpjs-stars">' +
                        [1,2,3,4,5].map(function(n){
                            return '<span class="bpjs-star" data-rating="' + n + '">⭐</span>';
                        }).join('') +
                    '</div>' +
                    '<textarea placeholder="Optionaler Kommentar…" rows="3"></textarea>' +
                    '<br><button class="bpjs-btn-primary bpjs-feedback-send" data-session="' + sessionId + '" style="margin-top:10px;font-size:13px;padding:8px 16px">Feedback senden</button>' +
                '</div>'
            );
        });
    }

    // Stern-Hover
    $(document).on('mouseenter', '.bpjs-star', function () {
        const rating = parseInt($(this).data('rating'), 10);
        $(this).closest('.bpjs-stars').find('.bpjs-star').each(function () {
            $(this).toggleClass('active', parseInt($(this).data('rating'), 10) <= rating);
        });
    }).on('mouseleave', '.bpjs-stars', function () {
        const selected = parseInt($(this).find('.bpjs-star.selected').data('rating'), 10) || 0;
        $(this).find('.bpjs-star').each(function () {
            $(this).toggleClass('active', parseInt($(this).data('rating'), 10) <= selected);
        });
    }).on('click', '.bpjs-star', function () {
        $(this).addClass('selected').siblings().removeClass('selected');
    });

    // Feedback absenden
    $(document).on('click', '.bpjs-feedback-send', function () {
        const $btn       = $(this);
        const $feedback  = $btn.closest('.bpjs-feedback');
        const sessionId  = parseInt($btn.data('session'), 10);
        const rating     = parseInt($feedback.find('.bpjs-star.selected').data('rating'), 10) || 3;
        const comment    = $feedback.find('textarea').val().trim();

        $btn.prop('disabled', true).text('⏳ Sende…');

        $.ajax({
            url:    REST + 'sessions/' + sessionId + '/feedback',
            method: 'POST',
            data:   JSON.stringify({ rating, comment }),
            contentType: 'application/json',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', NONCE);
            },
        })
        .done(function () {
            $feedback.html('<p style="color:#27ae60;font-weight:bold">✅ Danke für dein Feedback!</p>');
        })
        .fail(function () {
            $btn.prop('disabled', false).text('Feedback senden');
        });
    });

    // ── Hilfsfunktionen ─────────────────────────────────────────────────────

    function showError($el, msg) {
        $el.text(msg).removeAttr('hidden');
    }

}(jQuery));
