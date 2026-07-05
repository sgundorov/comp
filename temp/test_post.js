const http = require('http');

// Test POST save
const postData = 'mode=edit&id=1&number=1&date=2026-06-20&time=10:00&client_id=3062&state=%D0%A7%D0%B5%D1%80%D0%BD%D0%BE%D0%B2%D0%B8%D0%BA&store_id=5&sotr_id=2&payment_type=%D0%9D%D0%B0%D0%BB%D0%B8%D1%87%D0%BD%D1%8B%D0%B5&discount=0&sum_discount=0&sum=0&sum_nds=0&sum_plat=0&date_plat=&pos=0&note=&ajax=1';

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
      console.log('Response:', JSON.stringify(j).substring(0, 200));
    } catch(e) {
      console.log('Not JSON, first 200:', d.substring(0, 200));
    }
  });
});
req.write(postData);
req.end();
