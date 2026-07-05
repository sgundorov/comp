const http = require('http');
const fs = require('fs');
http.get('http://localhost/comp/group.php?sort=note%3Aasc', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    fs.writeFileSync('C:/xa/htdocs/comp/temp/rendered.html', d);
    const lines = d.split('\n');
    console.log('Total lines:', lines.length);
    for (let i = 830; i < 850 && i < lines.length; i++) {
      console.log((i + 1) + ': ' + lines[i]);
    }
  });
});
