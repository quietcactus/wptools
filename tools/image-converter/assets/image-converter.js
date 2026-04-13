(function ($) {
  'use strict';

  /* ── Init ── */

  var ajaxUrl = wptoolsImageconvData.ajaxUrl;
  var nonce   = wptoolsImageconvData.nonce;

  /* ── Helpers ── */

  function imageconv_show_error(msg) {
    console.error('[WPTools Image Converter] ' + msg);
  }

  /* ── Stub: test AJAX wiring ── */
  // Phase 2 and 3 will replace this with real UI event bindings.
  // The stub is intentionally left for manual UAT verification only.

}(jQuery));
