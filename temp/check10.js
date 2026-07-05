const http = require('http');
http.get('http://localhost/comp/group.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const lines = d.split('\n');
    console.log('Lines 470-490:');
    for (let i = 469; i < Math.min(490, lines.length); i++) {
      console.log((i+1) + ': ' + lines[i].substring(0, 120));
    }
  });
});
