const http = require('http');
const FormData = require('form-data');

const form = new FormData();
form.append('mode', 'new');
form.append('id', '0');
form.append('number', '101');
form.append('date', '2026-06-21');
form.append('time', '12:00');
form.append('client_id', '3062');
form.append('state', 'Черновик');
form.append('store_id', '5');
form.append('payment_type', 'Наличные');
form.append('discount', '0');
form.append('sum_discount', '0');
form.append('sum', '0');
form.append('sum_nds', '0');
form.append('sum_plat', '0');
form.append('date_plat', '');
form.append('sotr_id', '2');
form.append('pos', '0');
form.append('note', '');
form.append('cli_name', '');
form.append('store_name', '');
form.append('sotr_name', '');

const req = http.request({
  hostname: 'localhost',
  path: '/comp/invo_form.php',
  method: 'POST',
  headers: {
    ...form.getHeaders(),
    'X-Requested-With': 'XMLHttpRequest'
  }
}, res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Status:', res.statusCode);
    console.log('Content-Type:', res.headers['content-type']);
    try {
      const j = JSON.parse(d);
      console.log('ok:', j.ok, 'mode:', j.mode, 'id:', j.id);
      if (j.error) console.log('ERROR:', j.error);
    } catch(e) {
      console.log('Not JSON, first 500:', d.substring(0, 500));
    }
  });
});
form.pipe(req);
