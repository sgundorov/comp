const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Get context around occurrence #2 (inside openFormModal)
    const pos = 68308;
    const before = d.substring(pos - 400, pos);
    const after = d.substring(pos, pos + 200);
    console.log('BEFORE _invF.addEventListener (occurrence #2):');
    console.log(before);
    console.log('\nAFTER:');
    console.log(after);
  });
});
