const http = require('http');

// Simulate what happens in browser: openFormModal -> fetch -> bindForm -> extra_open
http.get('http://localhost/comp/invo_form.php?mode=new&ajax=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const j = JSON.parse(d);
    const html = j.html;
    
    // Simulate what the browser does:
    // 1. body.innerHTML = html
    // 2. form = body.querySelector('form[data-form-modal]')
    // Does the form exist?
    const hasForm = html.indexOf('data-form-modal') > -1;
    console.log('Has data-form-modal:', hasForm);
    
    // 3. bindForm(form) adds submit listener
    // 4. _invoSave adds capture submit listener
    
    // Key question: can we actually submit this form?
    // Test POST with the form data
    const params = new URLSearchParams();
    params.set('mode', 'new');
    params.set('id', '0');
    params.set('number', '99');
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
    const opts = {
      hostname: 'localhost',
      path: '/comp/invo_form.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postData),
        'X-Requested-With': 'XMLHttpRequest'
      }
    };
    
    const req = http.request(opts, res2 => {
      let d2 = '';
      res2.on('data', c => d2 += c);
      res2.on('end', () => {
        try {
          const j2 = JSON.parse(d2);
          console.log('\nPOST result:', JSON.stringify(j2).substring(0, 200));
        } catch(e) {
          console.log('\nPOST not JSON:', d2.substring(0, 300));
        }
      });
    });
    req.write(postData);
    req.end();
  });
});
