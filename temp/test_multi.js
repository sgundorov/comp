const http = require('http');
const boundary = '----FormBoundary' + Date.now();

function addField(name, value) {
  return '--' + boundary + '\r\nContent-Disposition: form-data; name="' + name + '"\r\n\r\n' + value + '\r\n';
}

let body = '';
body += addField('mode', 'new');
body += addField('id', '0');
body += addField('number', '102');
body += addField('date', '2026-06-21');
body += addField('time', '12:00');
body += addField('client_id', '3062');
body += addField('state', 'Черновик');
body += addField('store_id', '5');
body += addField('payment_type', 'Наличные');
body += addField('discount', '0');
body += addField('sum_discount', '0');
body += addField('sum', '0');
body += addField('sum_nds', '0');
body += addField('sum_plat', '0');
body += addField('date_plat', '');
body += addField('sotr_id', '2');
body += addField('pos', '0');
body += addField('note', '');
body += addField('cli_name', '');
body += addField('store_name', '');
body += addField('sotr_name', '');
body += '--' + boundary + '--\r\n';

const req = http.request({
  hostname: 'localhost',
  path: '/comp/invo_form.php',
  method: 'POST',
  headers: {
    'Content-Type': 'multipart/form-data; boundary=' + boundary,
    'Content-Length': Buffer.byteLength(body),
    'X-Requested-With': 'XMLHttpRequest'
  }
}, res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Status:', res.statusCode);
    try {
      const j = JSON.parse(d);
      console.log('ok:', j.ok, 'mode:', j.mode, 'id:', j.id);
      if (j.error) console.log('ERROR:', j.error);
    } catch(e) {
      console.log('Not JSON, first 500:', d.substring(0, 500));
    }
  });
});
req.write(body);
req.end();
