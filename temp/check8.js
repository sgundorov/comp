const http = require('http');
http.get('http://localhost/comp/group.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const lines = d.split('\n');
    console.log('Lines 475-510:');
    for (let i = 474; i < Math.min(510, lines.length); i++) {
      console.log((i+1) + ': ' + lines[i].substring(0, 120));
    }
    console.log('\nLines 500-520:');
    for (let i = 499; i < Math.min(520, lines.length); i++) {
      console.log((i+1) + ': ' + lines[i].substring(0, 120));
    }
  });
});
