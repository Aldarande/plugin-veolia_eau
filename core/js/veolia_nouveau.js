// Updated JS to handle challenge and optional save
jQuery(document).ready(function($){
  $('#btn_test_veolia_nouveau').off('click').on('click', function() {
    $('#veolia_nouveau_test_result').text('Test en cours...');
    const cfg = {};
    $('[data-l1key="configuration"]').each(function(){
      const l2 = $(this).attr('data-l2key');
      if (!l2) return;
      cfg[l2] = $(this).val();
    });
    // include eq_id if present in form
    const eqId = $('input[name="id"]').val();
    if (eqId) cfg['eq_id'] = eqId;
    cfg['save'] = true; // ask server to persist tokens on success

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
          if (res.rows) {
            let html = '<ul>';
            res.rows.forEach(function(r){ html += '<li>' + (r.date || r.date_raw) + ' => ' + r.m3 + ' m3</li>'; });
            html += '</ul>';
            $('#veolia_nouveau_test_result').append('<div style="margin-top:8px">' + html + '</div>');
          }
        } else if (res.state === 'challenge') {
          const challenge = res.challenge;
          const code = prompt('Un challenge est requis ('+challenge.name+'). Entrez le code reçu :');
          if (!code) { $('#veolia_nouveau_test_result').text('Challenge annulé'); return; }
          // send challenge response
          const challengePost = {
            action: 'respond_challenge',
            client_id: cfg['client_id'],
            challenge_name: challenge.name,
            session: challenge.session,
            response: code,
            username: cfg['username'],
            contract_id: cfg['contract_id'],
            numero_pds: cfg['numero_pds'],
            date_debut: cfg['date_debut'],
            base_host: cfg['base_host'] || 'https://prd-ael-sirius-backend.istefr.fr',
            save: true,
            eq_id: cfg['eq_id'] || 0
          };
          $.ajax({
            type: 'POST',
            url: 'plugins/plugin-veolia_eau/core/ajax/veolia_nouveau.ajax.php',
            data: challengePost,
            dataType: 'json',
            success: function(res2) {
              if (res2.state === 'ok') {
                $('#veolia_nouveau_test_result').html('<span style="color:green">Succès après challenge.</span>');
                if (res2.rows) {
                  let html = '<ul>';
                  res2.rows.forEach(function(r){ html += '<li>' + (r.date || r.date_raw) + ' => ' + r.m3 + ' m3</li>'; });
                  html += '</ul>';
                  $('#veolia_nouveau_test_result').append('<div style="margin-top:8px">' + html + '</div>');
                }
              } else {
                $('#veolia_nouveau_test_result').html('<span style="color:red">Erreur challenge: ' + (res2.message||'') + '</span>');
              }
            },
            error: function(){ $('#veolia_nouveau_test_result').text('Erreur réseau challenge'); }
          });
        } else {
          $('#veolia_nouveau_test_result').html('<span style="color:red">Erreur: ' + (res.message || 'erreur') + '</span>');
        }
      }
    });
  });
});
