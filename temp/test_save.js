const http = require('http');

// Simulate a full form POST like the form-modal-handler would do
const params = new URLSearchParams();
params.set('mode', 'edit');
params.set('id', '1');
params.set('number', '1');
params.set('date', '2026-06-20');
params.set('time', '10:00');
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

const options = {
  hostname: 'localhost',
  path: '/comp/invo_form.php',
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    'Content-Length': Buffer.byteLength(postData),
    'X-Requested-With': 'XMLHttpRequest'
  }
};

const req = http.request(options, res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Status:', res.statusCode);
    console.log('Content-Type:', res.headers['content-type']);
    try {
      const j = JSON.parse(d);
      console.log('ok:', j.ok);
      console.log('mode:', j.mode);
      console.log('id:', j.id);
      if (j.error) console.log('error:', j.error);
      if (j.html) console.log('html length:', j.html.length);
    } catch(e) {
      console.log('Not JSON! First 500:', d.substring(0, 500));
    }
  });
});
req.write(postData);
req.end();
