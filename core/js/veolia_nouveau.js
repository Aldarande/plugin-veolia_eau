// core/js/veolia_nouveau.js
// JavaScript to handle the test button in the equipment config modal.

jQuery(document).ready(function($){
  $('#btn_test_veolia_nouveau').off('click').on('click', function() {
    $('#veolia_nouveau_test_result').text('Test en cours...');
    // Gather fields from form (using the eqLogicAttr inputs)
    const cfg = {};
    $('[data-l1key="configuration"]').each(function(){
      const l2 = $(this).attr('data-l2key');
      if (!l2) return;
      cfg[l2] = $(this).val();
    });

    const post = {
      action: 'test',
      data: JSON.stringify(cfg)
    };

    $.ajax({
      type: 'POST',
      url: 'plugins/plugin-veolia_eau/core/ajax/veolia_nouveau.ajax.php',
      data: post,
      dataType: 'json',
      error: function(xhr, status, err) {
        $('#veolia_nouveau_test_result').text('Erreur réseau');
      },
      success: function(res) {
        if (!res || !res.state) {
          $('#veolia_nouveau_test_result').text('Réponse inattendue');
          return;
        }
        if (res.state === 'ok') {
          $('#veolia_nouveau_test_result').html('<span style="color:green">Succès: ' + res.message + '</span>');
          // Optionally show parsed CSV lines
          if (res.rows) {
            let html = '<ul>';
            res.rows.forEach(function(r){ html += '<li>' + (r.date || r.date_raw) + ' => ' + r.m3 + ' m3</li>'; });
            html += '</ul>';
            $('#veolia_nouveau_test_result').append('<div style="margin-top:8px">' + html + '</div>');
          }
        } else {
          $('#veolia_nouveau_test_result').html('<span style="color:red">Erreur: ' + (res.message || 'erreur') + '</span>');
        }
      }
    });
  });
});
