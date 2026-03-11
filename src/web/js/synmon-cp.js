(function ($) {
    'use strict';

    // ── Step Editor ──────────────────────────────────────────────────────────

    function reindexSteps() {
        $('#step-list .synmon-step-row').each(function (i) {
            $(this).find('[name]').each(function () {
                var name = $(this).attr('name');
                var newName = name.replace(/steps\[\d+\]/, 'steps[' + i + ']');
                $(this).attr('name', newName);
            });
            $(this).find('[data-step-index]').attr('data-step-index', i);
        });
    }

    // Add step via AJAX
    $(document).on('click', '#btn-add-step', function () {
        var $btn    = $(this);
        var type    = $('#new-step-type').val();
        var index   = $('#step-list .synmon-step-row').length;
        var csrfToken = $('meta[name="csrf-token"]').attr('content') ||
                        (window.Craft && Craft.csrfTokenValue) || '';

        $btn.prop('disabled', true);

        $.ajax({
            url: Craft.getCpUrl('synmon/suites/add-step'),
            method: 'POST',
            data: { type: type, index: index, CRAFT_CSRF_TOKEN: csrfToken },
            dataType: 'json',
        }).done(function (resp) {
            if (resp.success) {
                $('#step-list').append(resp.html);
                reindexSteps();
                initSortable();
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Remove step
    $(document).on('click', '.btn-remove-step', function () {
        $(this).closest('.synmon-step-row').remove();
        reindexSteps();
    });

    // ── Sortable drag-to-reorder ─────────────────────────────────────────────
    function initSortable() {
        var el = document.getElementById('step-list');
        if (!el || typeof Sortable === 'undefined') return;

        if (el._sortable) el._sortable.destroy();

        el._sortable = Sortable.create(el, {
            handle: '.synmon-drag-handle',
            animation: 150,
            onEnd: function () { reindexSteps(); }
        });
    }

    // Re-index before form submit
    $('form#suite-form').on('submit', function () {
        reindexSteps();
    });

    // ── Run Suite button ──────────────────────────────────────────────────────
    $(document).on('click', '.btn-run-suite', function (e) {
        e.preventDefault();
        var $btn    = $(this);
        var suiteId = $btn.data('suite-id');
        var csrfToken = (window.Craft && Craft.csrfTokenValue) || '';

        $btn.prop('disabled', true).text('Wird gestartet…');

        $.ajax({
            url: Craft.getCpUrl('synmon/suites/run'),
            method: 'POST',
            data: { suiteId: suiteId, CRAFT_CSRF_TOKEN: csrfToken },
            dataType: 'json',
        }).done(function (resp) {
            if (resp.success) {
                Craft.cp.displayNotice(resp.message || 'Suite gestartet.');
            }
        }).always(function () {
            $btn.prop('disabled', false).text('▶ Jetzt starten');
        });
    });

    // ── Toggle Suite enabled ──────────────────────────────────────────────────
    $(document).on('change', '.suite-toggle', function () {
        var $toggle = $(this);
        var suiteId = $toggle.data('suite-id');
        var csrfToken = (window.Craft && Craft.csrfTokenValue) || '';

        $.ajax({
            url: Craft.getCpUrl('synmon/suites/toggle'),
            method: 'POST',
            data: { suiteId: suiteId, CRAFT_CSRF_TOKEN: csrfToken },
            dataType: 'json',
        }).done(function (resp) {
            if (!resp.success) {
                $toggle.prop('checked', !$toggle.prop('checked'));
            }
        });
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    $(document).ready(function () {
        initSortable();
    });

})(jQuery);
