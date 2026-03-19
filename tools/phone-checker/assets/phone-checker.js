(function ($) {
  'use strict';

  var $scanBtn    = $('#wptools-phone-scan');
  var $clearBtn   = $('#wptools-phone-clear');
  var $status     = $('#wptools-phone-status');
  var $results    = $('#wptools-phone-results');

  /* ── Run Scan ── */
  $scanBtn.on('click', function () {
    $scanBtn.prop('disabled', true);
    $clearBtn.prop('disabled', true);
    $status
      .removeClass('is-error')
      .addClass('is-loading')
      .html('<span class="wptools-phone-spinner"></span> Scanning&hellip; this may take a moment.');

    var selectedTypes = [];
    $('.wptools-phone-type-cb:checked').each(function () {
      selectedTypes.push($(this).val());
    });

    $.post(wptoolsPhoneData.ajaxUrl, {
      action:     'wptools_phone_run_scan',
      nonce:      wptoolsPhoneData.nonce,
      post_types: selectedTypes,
    })
    .done(function (response) {
      if (!response.success) {
        phone_show_error('Scan failed: ' + (response.data || 'Unknown error.'));
        return;
      }

      var data  = response.data;
      var label = data.count === 0
        ? 'Scan complete — no unlinked phone numbers found.'
        : 'Scan complete. Found ' + data.count + ' unlinked phone number' + (data.count !== 1 ? 's.' : '.');

      $status.removeClass('is-loading').text(label);
      $scanBtn.text('Re-scan Now').prop('disabled', false);
      $clearBtn.show().prop('disabled', false);

      phone_render_table(data.results);
    })
    .fail(function () {
      phone_show_error('Request failed. Please try again.');
    });
  });

  /* ── Clear Cache ── */
  $clearBtn.on('click', function () {
    $clearBtn.prop('disabled', true);

    $.post(wptoolsPhoneData.ajaxUrl, {
      action: 'wptools_phone_clear_cache',
      nonce:  wptoolsPhoneData.nonce,
    })
    .done(function (response) {
      if (response.success) {
        $results.html('<div class="wptools-phone-empty"><p>Cache cleared. Click <strong>Run Scan</strong> to scan again.</p></div>');
        $scanBtn.text('Run Scan');
        $clearBtn.hide();
        $status.removeClass('is-loading is-error').text('');
      }
      $clearBtn.prop('disabled', false);
    });
  });

  /* ── Live filter ── */
  $(document).on('input', '#wptools-phone-filter', function () {
    var term  = $(this).val().toLowerCase().trim();
    var $rows = $('#wptools-phone-table tbody tr');

    $rows.each(function () {
      var text = $(this).text().toLowerCase();
      $(this).toggleClass('wptools-phone-row-hidden', term !== '' && text.indexOf(term) === -1);
    });
  });

  /* ── Column sort ── */
  $(document).on('click', '#wptools-phone-table th[data-col]', function () {
    var col    = parseInt($(this).data('col'), 10);
    var $table = $('#wptools-phone-table');
    var $tbody = $table.find('tbody');
    var asc    = $(this).data('sort-asc') !== true;

    var rows = $tbody.find('tr').get();
    rows.sort(function (a, b) {
      var aText = $(a).find('td').eq(col).text().trim().toLowerCase();
      var bText = $(b).find('td').eq(col).text().trim().toLowerCase();
      return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });

    $table.find('th').removeData('sort-asc').find('.wptools-phone-sort-arrow').remove();
    $(this).data('sort-asc', asc)
      .append('<span class="wptools-phone-sort-arrow">' + (asc ? '&#9650;' : '&#9660;') + '</span>');

    $.each(rows, function (i, row) {
      $tbody.append(row);
    });
  });

  /* ── CSV export ── */
  $(document).on('click', '#wptools-phone-export', function () {
    var rows = ['"Title","URL","Post Type","Unlinked Phone","Format Matched"'];

    $('#wptools-phone-table tbody tr:not(.wptools-phone-row-hidden)').each(function () {
      var $tds = $(this).find('td');
      rows.push([
        phone_csv($tds.eq(0).find('strong').text().trim()),
        phone_csv($tds.eq(0).find('a').attr('href') || ''),
        phone_csv($tds.eq(1).text().trim()),
        phone_csv($tds.eq(2).text().trim()),
        phone_csv($tds.eq(3).text().trim()),
      ].join(','));
    });

    var blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'unlinked-phones.csv';
    link.click();
  });

  /* ── Helpers ── */

  function phone_csv(val) {
    return '"' + String(val).replace(/"/g, '""') + '"';
  }

  function phone_show_error(msg) {
    $status.removeClass('is-loading').addClass('is-error').text(msg);
    $scanBtn.prop('disabled', false);
    $clearBtn.prop('disabled', false);
  }

  function phone_render_table(results) {
    if (!results || results.length === 0) {
      $results.html('<div class="notice notice-success inline wptools-phone-notice"><p>&#10003; No unlinked phone numbers found.</p></div>');
      return;
    }

    var postIds = {};
    results.forEach(function (r) { postIds[r.post_id] = true; });
    var postCount = Object.keys(postIds).length;

    var summary = '<div class="notice notice-warning inline wptools-phone-notice"><p>' +
      'Found <strong>' + results.length + ' unlinked phone number' + (results.length !== 1 ? 's' : '') + '</strong> ' +
      'across <strong>' + postCount + ' post' + (postCount !== 1 ? 's' : '') + '</strong>.' +
      '</p></div>';

    var controls = '<div class="wptools-phone-table-controls">' +
      '<input type="text" id="wptools-phone-filter" class="regular-text" placeholder="Filter by title, phone, or post type&hellip;" />' +
      '<button id="wptools-phone-export" class="button">Export CSV</button>' +
      '</div>';

    var thead = '<thead><tr>' +
      '<th class="col-title" data-col="0">Page / Post Title</th>' +
      '<th class="col-type"  data-col="1">Post Type</th>' +
      '<th class="col-phone" data-col="2">Unlinked Phone</th>' +
      '<th class="col-fmt"   data-col="3">Format Matched</th>' +
      '<th class="col-actions">Actions</th>' +
      '</tr></thead>';

    var tbody = '<tbody>';
    results.forEach(function (r) {
      var editBtn = r.edit_url
        ? '<a href="' + phone_esc(r.edit_url) + '" class="button button-small" target="_blank">Edit Post</a>'
        : '';

      tbody += '<tr>' +
        '<td class="col-title"><strong>' + phone_esc(r.title) + '</strong>' +
          '<div class="row-actions"><span><a href="' + phone_esc(r.url) + '" target="_blank">View</a></span></div></td>' +
        '<td class="col-type"><span class="wptools-phone-badge">' + phone_esc(r.post_type) + '</span></td>' +
        '<td class="col-phone"><code>' + phone_esc(r.match) + '</code></td>' +
        '<td class="col-fmt"><span class="wptools-phone-fmt">' + phone_esc(r.pattern) + '</span></td>' +
        '<td class="col-actions">' + editBtn + '</td>' +
        '</tr>';
    });
    tbody += '</tbody>';

    $results.html(
      summary + controls +
      '<table class="wp-list-table widefat fixed striped wptools-phone-table" id="wptools-phone-table">' +
      thead + tbody + '</table>'
    );
  }

  function phone_esc(str) {
    return $('<div>').text(str).html();
  }

}(jQuery));
