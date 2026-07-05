const http = require('http');

// Simulate the exact fetch the form-modal handler would make
const url = 'http://localhost/comp/invo_form.php?mode=new&ajax=1';
http.get(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }, res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Status:', res.statusCode);
    console.log('Content-Type:', res.headers['content-type']);
    try {
      const j = JSON.parse(d);
      console.log('ok:', j.ok);
      console.log('mode:', j.mode);
      console.log('html length:', j.html ? j.html.length : 0);
      
      // Check the HTML for form validity
      if (j.html) {
        console.log('Has form:', j.html.indexOf('<form') > -1);
        console.log('Has data-form-modal:', j.html.indexOf('data-form-modal') > -1);
        console.log('Has submit button:', j.html.indexOf('type="submit"') > -1);
        console.log('Has invo_form.php action:', j.html.indexOf('action="invo_form.php"') > -1);
      }
    } catch(e) {
      console.log('NOT JSON!');
      console.log('First 500:', d.substring(0, 500));
    }
  });
});
