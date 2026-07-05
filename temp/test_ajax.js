const http = require('http');
http.get('http://localhost/comp/invo_form.php?mode=new&ajax=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const j = JSON.parse(d);
    console.log('ok:', j.ok);
    console.log('has data-form-modal:', j.html.indexOf('data-form-modal') > -1);
    console.log('has form action:', j.html.indexOf('action="invo_form.php"') > -1);
    console.log('has submit btn:', j.html.indexOf('type="submit"') > -1);
  });
});
