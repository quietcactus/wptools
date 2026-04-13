(function ($) {
  'use strict';

  /* ── Init ── */

  var ajaxUrl = wptoolsImageconvData.ajaxUrl;
  var nonce   = wptoolsImageconvData.nonce;

  /* ── Fetch images on load ── */

  function imageconv_fetch_images() {
    $('#wptools-imageconv-loading').show();
    $('#wptools-imageconv-table').hide();
    $('#wptools-imageconv-empty').hide();
    $('#wptools-imageconv-actions').hide();

    $.post(ajaxUrl, {
      action: 'wptools_imageconv_get_images',
      nonce:  nonce,
    })
    .done(function (response) {
      $('#wptools-imageconv-loading').hide();

      if (!response.success) {
        imageconv_show_error('Failed to load images: ' + (response.data || 'Unknown error.'));
        return;
      }

      var data = response.data;

      if (data.count === 0) {
        $('#wptools-imageconv-empty').show();
        return;
      }

      imageconv_render_rows(data.images);
      $('#wptools-imageconv-table').show();
      $('#wptools-imageconv-actions').show();
    })
    .fail(function () {
      $('#wptools-imageconv-loading').hide();
      imageconv_show_error('Request failed. Please reload the page and try again.');
    });
  }

  /* ── Render rows ── */

  function imageconv_render_rows(images) {
    var html = '';

    images.forEach(function (img) {
      var mimeShort = imageconv_mime_short(img.mime_type);

      html += '<tr>' +
        '<td class="wptools-imageconv-col-cb">' +
          '<input type="checkbox" class="wptools-imageconv-image-cb" data-id="' + imageconv_esc(String(img.attachment_id)) + '" />' +
        '</td>' +
        '<td class="wptools-imageconv-col-name">' + imageconv_esc(img.filename) + '</td>' +
        '<td class="wptools-imageconv-col-type">' +
          '<span class="wptools-imageconv-badge wptools-imageconv-badge-' + imageconv_esc(mimeShort) + '">' +
            imageconv_esc(mimeShort.toUpperCase()) +
          '</span>' +
        '</td>' +
        '<td class="wptools-imageconv-col-size">' + imageconv_esc(img.file_size_label) + '</td>' +
        '</tr>';
    });

    $('#wptools-imageconv-tbody').html(html);
  }

  /* ── Select all ── */

  $('#wptools-imageconv-select-all').on('change', function () {
    var checked = $(this).prop('checked');
    $('.wptools-imageconv-image-cb').prop('checked', checked);
    imageconv_update_count();
  });

  /* ── Individual checkbox state ── */

  $(document).on('change', '.wptools-imageconv-image-cb', function () {
    var total      = $('.wptools-imageconv-image-cb').length;
    var checked    = $('.wptools-imageconv-image-cb:checked').length;
    var $selectAll = $('#wptools-imageconv-select-all');

    if (checked === 0) {
      $selectAll.prop('checked', false).prop('indeterminate', false);
    } else if (checked === total) {
      $selectAll.prop('checked', true).prop('indeterminate', false);
    } else {
      $selectAll.prop('checked', false).prop('indeterminate', true);
    }

    imageconv_update_count();
  });

  /* ── Helpers ── */

  function imageconv_update_count() {
    var count = $('.wptools-imageconv-image-cb:checked').length;
    var $count = $('#wptools-imageconv-selected-count');
    var $btn   = $('#wptools-imageconv-process-btn');

    if (count === 0) {
      $count.text('').hide();
      $btn.prop('disabled', true);
    } else {
      $count.text(count + ' selected').show();
      $btn.prop('disabled', false);
    }
  }

  function imageconv_mime_short(mimeType) {
    if (mimeType === 'image/jpeg') { return 'jpg'; }
    if (mimeType === 'image/png')  { return 'png'; }
    if (mimeType === 'image/webp') { return 'webp'; }
    return mimeType;
  }

  function imageconv_esc(str) {
    return $('<div>').text(str).html();
  }

  function imageconv_show_error(msg) {
    console.error('[WPTools Image Converter] ' + msg);
  }

  /* ── Boot ── */

  imageconv_fetch_images();

}(jQuery));
