const http = require('http');

// Test with INVALID data to see validation errors
const params = new URLSearchParams();
params.set('mode', 'edit');
params.set('id', '1');
params.set('number', '1');
params.set('date', '');
params.set('client_id', '0');
params.set('state', 'Черновик');
params.set('store_id', '0');
params.set('sotr_id', '0');
params.set('ajax', '1');

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
    const j = JSON.parse(d);
    console.log('ok:', j.ok);
    console.log('Has errors:', j.html.indexOf('flash--error') > -1);
    if (!j.ok) {
      // Extract error messages
      const errors = j.html.match(/flash--error[^<]*<[^>]*>[^<]*/g);
      if (errors) errors.forEach(e => console.log('Error:', e.substring(0, 100)));
    }
    console.log('Has form still:', j.html.indexOf('data-form-modal') > -1);
  });
});
req.write(postData);
req.end();
