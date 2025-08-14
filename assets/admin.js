/**
 * WC Anti-Fraud Pro Lite — Admin UI
 * - SelectWoo product multi-select (with hidden CSV mirror for PHP)
 * - Debounced settings search
 * - Logic preview (cart rules)
 * - Compact help toggle
 *
 * Dependencies:
 * - jQuery
 * - selectWoo (loaded by WooCommerce)
 * - window.WCA_PRESETS (localized in PHP; provides ajax + currency)
 */
(function ($) {
  'use strict';

  /* -----------------------------
   * Utilities
   * --------------------------- */

  // Small debounce utility
  function debounce(fn, wait) {
    var t;
    return function () {
      var ctx = this,
        args = arguments;
      clearTimeout(t);
      t = setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };
  }

  // Safe getter for localized data
  function getPresets() {
    return typeof window.WCA_PRESETS === 'object' && window.WCA_PRESETS
      ? window.WCA_PRESETS
      : {
          ajax: {
            url: ajaxurl || '',
            action: 'woocommerce_json_search_products_and_variations',
            security: '',
          },
          currency: { symbol: '' },
        };
  }

  // Read numeric input safely
  function readFloat($input, def) {
    var v = parseFloat(($input.val() || '').toString().replace(/[^\d.]/g, ''));
    return isNaN(v) ? def || 0 : v;
  }

  /* -----------------------------
   * Product picker (SelectWoo)
   * - #wca-flag-products  (multiple)
   * - Hidden CSV mirror:  #wca-flag-products-csv (name="wca_opts_ext[flag_product_ids]")
   * --------------------------- */

  function wcaInitProductSelect() {
    var $el = $('#wca-flag-products');
    if (!$el.length) return;

    // Ensure SelectWoo exists
    if (typeof $el.selectWoo !== 'function') {
      // If selectWoo isn't available yet, retry once after a tick
      setTimeout(wcaInitProductSelect, 150);
      return;
    }

    var PRESETS = getPresets();

    // Init or re-init
    try {
      $el.selectWoo('destroy');
    } catch (e) {}
    $el
      .selectWoo({
        width: '100%',
        placeholder: 'Search products…',
        minimumInputLength: 2,
        ajax: {
          url: PRESETS.ajax.url,
          dataType: 'json',
          delay: 200,
          data: function (params) {
            return {
              term: params.term || '',
              action: PRESETS.ajax.action,
              security: PRESETS.ajax.security,
              exclude: $el.val() || [],
            };
          },
          processResults: function (data) {
            // WC returns an object: { id: "Product Name (#ID)", ... }
            var results = [];
            try {
              $.each(data || {}, function (id, text) {
                results.push({ id: id, text: text });
              });
            } catch (err) {
              // fallback: no results
            }
            return { results: results };
          },
          transport: function (params, success, failure) {
            var request = $.ajax(params);
            request.then(success);
            request.fail(function (xhr) {
              failure(xhr);
            });
            return request;
          },
        },
        allowClear: true,
      })
      .on('change', function () {
        syncFlagCSV();
        wcaUpdateCartPreview();
      });

    // Create/find hidden CSV input (what PHP expects)
    syncFlagCSV(); // initial sync
  }

  // Ensure the backend receives CSV even if UI is multi-select
  function syncFlagCSV() {
    var $select = $('#wca-flag-products');
    var ids = ($select.val() || [])
      .map(function (v) {
        return parseInt(v, 10);
      })
      .filter(Boolean);
    var csv = ids.join(',');
    var $csv = $('#wca-flag-products-csv');

    if (!$csv.length) {
      // Insert right after the select, keep same setting key
      $csv = $('<input/>', {
        type: 'hidden',
        id: 'wca-flag-products-csv',
        name: 'wca_opts_ext[flag_product_ids]',
      }).insertAfter($select);
    }
    $csv.val(csv);
  }

  /* -----------------------------
   * Cart rules live summary box
   * --------------------------- */
  function wcaUpdateCartPreview() {
    var $box = $('#wca-cart-preview');
    if (!$box.length) return;

    var PRESETS = getPresets();
    var sym = (PRESETS.currency && PRESETS.currency.symbol) || '';

    var ids = ($('#wca-flag-products').val() || [])
      .map(function (v) {
        return parseInt(v, 10);
      })
      .filter(Boolean);
    var mode = ($('#wca-flag-match').val() || 'any').toLowerCase();

    var list = ids.length ? ids.join(', ') : '—';
    var modeText =
      mode === 'all'
        ? 'ALL selected products must be present'
        : 'ANY selected product may be present';

    // Read switches & values safely
    var onlyFlagged = $('input[name="wca_opts_ext[block_if_only_flagged]"]').is(':checked');
    var guestFlag = $('input[name="wca_opts_ext[block_guest_for_flagged]"]').is(':checked');
    var requireLoginBelow = $('input[name="wca_opts_ext[require_login_below]"]').is(':checked');
    var $lowValInput = $(
      'input[name="wca_opts_ext[low_value_threshold]"], input[name="wca_opts_ext[low_value_threshold]"].wca-num'
    );
    var lowVal = readFloat($lowValInput, 0);

    var lines = [];
    lines.push('<strong>Flagged Product IDs:</strong> ' + list);
    lines.push('<strong>Match mode:</strong> ' + modeText);
    if (onlyFlagged) {
      lines.push('• Block checkout if the cart contains ONLY flagged products.');
    }
    if (guestFlag) {
      lines.push(
        '• Block guest checkout if cart ' +
          (mode === 'all' ? 'contains ALL' : 'contains ANY') +
          ' flagged product(s).'
      );
    }
    if (requireLoginBelow) {
      lines.push('• Require login if order total < ' + sym + lowVal.toFixed(2) + '.');
    }

    $box.html(
      '<div class="wca-preview-box">' +
        (lines.length
          ? lines
              .map(function (l) {
                return '<div>' + l + '</div>';
              })
              .join('')
          : '—') +
        '</div>'
    );
  }

  /* -----------------------------
   * Search filter (debounced)
   * --------------------------- */
  function wcaInitSearch() {
    var $search = $('#wca-search');
    if (!$search.length) return;

    var run = debounce(function () {
      var q = ($search.val() || '').toLowerCase();
      $('.wca-field').each(function () {
        var $f = $(this);
        var hay = (($f.data('search') || '') + '').toLowerCase();
        $f.toggle(!q || hay.indexOf(q) !== -1);
      });
    }, 120);

    $search.on('input', run);
  }

  /* -----------------------------
   * Help toggles (card “?” button)
   * --------------------------- */
  function wcaInitHelpToggles() {
    $(document).on('click', '.wca-help', function () {
      var $card = $(this).closest('.wca-card');
      var $note = $card.find('.wca-help-note');
      if (!$note.length) return;
      var hidden = $note.attr('hidden');
      if (typeof hidden === 'undefined' || hidden === false) {
        $note.attr('hidden', true);
      } else {
        $note.removeAttr('hidden');
      }
    });
  }

  /* -----------------------------
   * Bind events that impact preview
   * --------------------------- */
  function wcaBindPreviewTriggers() {
    $(document).on(
      'change',
      [
        'input[name="wca_opts_ext[block_if_only_flagged]"]',
        'input[name="wca_opts_ext[block_guest_for_flagged]"]',
        'input[name="wca_opts_ext[require_login_below]"]',
        'input[name="wca_opts_ext[low_value_threshold]"]',
        '#wca-flag-match',
      ].join(','),
      debounce(wcaUpdateCartPreview, 60)
    );
  }

  /* -----------------------------
   * Boot
   * --------------------------- */
  $(function () {
    wcaInitSearch();
    wcaInitHelpToggles();
    wcaInitProductSelect();
    wcaBindPreviewTriggers();
    // Initial paint
    wcaUpdateCartPreview();
  });
})(jQuery);
