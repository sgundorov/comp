const http = require('http');
http.get('http://localhost/comp/invo.php?action=columnFilterOptions&col=sotr', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const arr = JSON.parse(d);
    console.log('Sotr filter options:');
    arr.forEach(s => console.log('  id=' + s.id, 'name=' + JSON.stringify(s.name)));
  });
});
