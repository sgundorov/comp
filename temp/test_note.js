const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Status:', res.statusCode);
    const w = d.match(/<b>Warning<\/b>/g);
    console.log('Warnings:', w ? w.length : 0);
    console.log('note nowrap:', d.indexOf('white-space: nowrap') > -1);
    console.log('note auto width:', d.indexOf("note' => 'auto'") > -1);
  });
});
