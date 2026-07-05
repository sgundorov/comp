const http = require('http');
http.get('http://localhost/comp/group.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const lines = d.split('\n');
    console.log('Lines 370-400:');
    for (let i = 369; i < Math.min(400, lines.length); i++) {
      console.log((i+1) + ': ' + lines[i].substring(0, 120));
    }
  });
});
