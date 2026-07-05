const http = require('http');
http.get('http://localhost/comp/invo_form.php?mode=new&ajax=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const j = JSON.parse(d);
    console.log('ok:', j.ok);
    console.log('Has apply btn:', j.html.indexOf('apply') > -1);
    console.log('Has data-form-modal:', j.html.indexOf('data-form-modal') > -1);
    console.log('Has data-lookup:', j.html.indexOf('data-lookup') > -1);
    console.log('Has data-countries:', j.html.indexOf('data-countries') > -1);
    console.log('Has tab-container:', j.html.indexOf('tab-container') > -1);
  });
});
