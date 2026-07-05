const http = require('http');
const params = new URLSearchParams();
params.set('mode', 'new');
params.set('id', '0');
params.set('number', '100');
params.set('date', '2026-06-21');
params.set('time', '12:00');
params.set('client_id', '3062');
params.set('state', 'Черновик');
params.set('store_id', '5');
params.set('payment_type', 'Наличные');
params.set('discount', '0');
params.set('sum_discount', '0');
params.set('sum', '0');
params.set('sum_nds', '0');
params.set('sum_plat', '0');
params.set('date_plat', '');
params.set('sotr_id', '2');
params.set('pos', '0');
params.set('note', '');
params.set('cli_name', '');
params.set('store_name', '');
params.set('sotr_name', '');
const postData = params.toString();

const req = http.request({
  hostname: 'localhost', path: '/comp/invo_form.php', method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(postData), 'X-Requested-With': 'XMLHttpRequest' }
}, res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    try {
      const j = JSON.parse(d);
      console.log('ok:', j.ok, 'mode:', j.mode, 'id:', j.id);
      if (j.error) console.log('ERROR:', j.error);
    } catch(e) {
      console.log('Not JSON:', d.substring(0, 300));
    }
  });
});
req.write(postData);
req.end();
