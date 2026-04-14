(function ($) {
  'use strict';

  /* ── Init ── */

  var ajaxUrl = wptoolsImageconvData.ajaxUrl;
  var nonce   = wptoolsImageconvData.nonce;

  /* ── Fetch images on load ── */

  function imageconv_fetch_images(params) {
    $('#wptools-imageconv-loading').show();
    $('#wptools-imageconv-table').hide();
    $('#wptools-imageconv-empty').hide();
    $('#wptools-imageconv-actions').hide();

    var postData = $.extend({
      action: 'wptools_imageconv_get_images',
      nonce:  nonce,
      page:   1,
    }, params || {});

    $.post(ajaxUrl, postData)
    .done(function (response) {
      $('#wptools-imageconv-loading').hide();

      if (!response.success) {
        imageconv_show_error('Failed to load images: ' + (response.data || 'Unknown error.'));
        return;
      }

      var data = response.data;

      if (data.total === 0) {
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
      var thumbHtml = img.thumbnail_html || '';

      html += '<tr>' +
        '<td class="wptools-imageconv-col-cb">' +
          '<input type="checkbox" class="wptools-imageconv-image-cb" data-id="' + imageconv_esc(String(img.attachment_id)) + '" />' +
        '</td>' +
        '<td class="wptools-imageconv-col-thumb">' + thumbHtml + '</td>' +
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

  /* ── Modal ── */

  function imageconv_output_name(filename) {
    var dot  = filename.lastIndexOf('.');
    var ext  = dot !== -1 ? filename.slice(dot + 1).toLowerCase() : '';
    var base = dot !== -1 ? filename.slice(0, dot) : filename;
    if (ext === 'webp') {
      return base + '-min.webp';
    }
    return base + '.webp';
  }

  function imageconv_open_modal(ids) {
    var $list = $('#wptools-imageconv-modal-list');
    var html  = '';

    ids.forEach(function (id) {
      var $row     = $('input.wptools-imageconv-image-cb[data-id="' + id + '"]').closest('tr');
      var filename = $row.find('.wptools-imageconv-col-name').text().trim();
      var outName  = imageconv_output_name(filename);

      html += '<li>' +
        imageconv_esc(filename) + ' &rarr; ' + imageconv_esc(outName) +
      '</li>';
    });

    var label = ids.length === 1 ? '1 image' : ids.length + ' images';
    $('#wptools-imageconv-modal-summary').text('The following ' + label + ' will be processed:');
    $list.html(html);
    $('#wptools-imageconv-delete-original').prop('checked', false);
    $('#wptools-imageconv-modal').show();
  }

  /* ── Processing ── */

  function imageconv_js_format_bytes(bytes) {
    if (bytes >= 1048576) { return (bytes / 1048576).toFixed(1) + ' MB'; }
    if (bytes >= 1024)    { return (bytes / 1024).toFixed(1) + ' KB'; }
    return bytes + ' B';
  }

  function imageconv_update_progress(done, total) {
    var $btn = $('#wptools-imageconv-process-btn');
    $btn.text('Processing ' + done + ' / ' + total + '…').prop('disabled', true);
  }

  function imageconv_process_next(ids, deleteOriginal, index, results) {
    if (index >= ids.length) {
      imageconv_show_results(ids, results);
      return;
    }

    var id = ids[index];

    $.post(ajaxUrl, {
      action:          'wptools_imageconv_process',
      attachment_id:   id,
      delete_original: deleteOriginal ? 1 : 0,
      nonce:           nonce,
    })
    .done(function (response) {
      if (response.success) {
        results.push({ id: id, success: true, data: response.data });
      } else {
        var errMsg = (response.data && response.data.error) ? response.data.error : (response.data || 'Unknown error.');
        results.push({ id: id, success: false, error: errMsg });
      }
      imageconv_update_progress(index + 1, ids.length);
      imageconv_process_next(ids, deleteOriginal, index + 1, results);
    })
    .fail(function () {
      results.push({ id: id, success: false, error: 'Request failed.' });
      imageconv_update_progress(index + 1, ids.length);
      imageconv_process_next(ids, deleteOriginal, index + 1, results);
    });
  }

  /* ── Results ── */

  function imageconv_show_results(ids, results) {
    var html = '';

    results.forEach(function (r) {
      if (r.success) {
        var d        = r.data;
        var savings  = d.savings_bytes > 0
          ? imageconv_esc(imageconv_js_format_bytes(d.savings_bytes)) + ' (' + imageconv_esc(String(d.savings_pct)) + '%)'
          : '0 B (0%)';
        var savClass = d.savings_bytes > 0 ? 'wptools-imageconv-savings-positive' : 'wptools-imageconv-savings-zero';

        html += '<tr>' +
          '<td>' + imageconv_esc(d.original_name) + '</td>' +
          '<td>' + imageconv_esc(d.output_name) + '</td>' +
          '<td>' + imageconv_esc(imageconv_js_format_bytes(d.original_size)) + '</td>' +
          '<td>' + imageconv_esc(imageconv_js_format_bytes(d.output_size)) + '</td>' +
          '<td><span class="' + savClass + '">' + savings + '</span></td>' +
          '<td><span class="wptools-imageconv-status-ok">Done</span></td>' +
          '</tr>';
      } else {
        html += '<tr>' +
          '<td colspan="5">' + imageconv_esc(String(r.id)) + '</td>' +
          '<td><span class="wptools-imageconv-status-error">' + imageconv_esc(r.error) + '</span></td>' +
          '</tr>';
      }
    });

    $('#wptools-imageconv-results-tbody').html(html);
    $('#wptools-imageconv-results').show();

    $('#wptools-imageconv-process-btn').text('Convert / Compress').prop('disabled', false);

    // Before/after preview: single-image success only
    if (ids.length === 1 && results.length === 1 && results[0].success) {
      var d = results[0].data;
      if (d.output_url) {
        $('#wptools-imageconv-preview-after').attr('src', d.output_url).attr('width', '').attr('height', '');
      }
      if (d.original_url) {
        $('#wptools-imageconv-preview-before').attr('src', d.original_url).attr('width', '').attr('height', '');
      }
      if (d.original_url || d.output_url) {
        $('#wptools-imageconv-preview').show();
      }
    }
  }

  /* ── Convert/Compress button → open modal ── */

  $('#wptools-imageconv-process-btn').on('click', function () {
    var ids = [];
    $('.wptools-imageconv-image-cb:checked').each(function () {
      ids.push($(this).data('id'));
    });
    if (ids.length === 0) { return; }
    imageconv_open_modal(ids);
  });

  /* ── Modal: cancel ── */

  $('#wptools-imageconv-modal-cancel').on('click', function () {
    $('#wptools-imageconv-modal').hide();
  });

  /* ── Modal: confirm → start processing ── */

  $('#wptools-imageconv-modal-confirm').on('click', function () {
    var ids = [];
    $('.wptools-imageconv-image-cb:checked').each(function () {
      ids.push($(this).data('id'));
    });
    var deleteOriginal = $('#wptools-imageconv-delete-original').prop('checked');
    $('#wptools-imageconv-modal').hide();
    imageconv_update_progress(0, ids.length);
    imageconv_process_next(ids, deleteOriginal, 0, []);
  });

  /* ── Filter helpers ── */

  function imageconv_get_filter_params() {
    var sortVal = $('#wptools-imageconv-sort').val() || 'date-DESC';
    var sortParts = sortVal.split('-');
    return {
      search:  $('#wptools-imageconv-search').val() || '',
      type:    $('#wptools-imageconv-type').val()   || '',
      year:    parseInt($('#wptools-imageconv-year').val(),  10) || 0,
      month:   parseInt($('#wptools-imageconv-month').val(), 10) || 0,
      orderby: sortParts[0] || 'date',
      order:   sortParts[1] || 'DESC',
    };
  }

  /* ── Filter panel toggle ── */

  $('#wptools-imageconv-filter-toggle').on('click', function () {
    $('#wptools-imageconv-filter-panel').toggleClass('wptools-imageconv-filter-panel-open');
    $(this).toggleClass('is-active');
  });

  /* ── Search input (debounced) ── */

  var imageconv_search_timer = null;

  $('#wptools-imageconv-search').on('input', function () {
    clearTimeout(imageconv_search_timer);
    imageconv_search_timer = setTimeout(function () {
      imageconv_fetch_images(imageconv_get_filter_params());
    }, 400);
  });

  /* ── Format / year / month selects ── */

  $('#wptools-imageconv-type, #wptools-imageconv-year, #wptools-imageconv-month').on('change', function () {
    imageconv_fetch_images(imageconv_get_filter_params());
  });

  $('#wptools-imageconv-sort').on('change', function () {
    imageconv_fetch_images(imageconv_get_filter_params());
  });

  /* ── Boot ── */

  imageconv_fetch_images(imageconv_get_filter_params());

}(jQuery));
