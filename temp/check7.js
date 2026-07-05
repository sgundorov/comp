const http = require('http');
http.get('http://localhost/comp/group.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find all <script> tags and check their positions
    const sr = /<script[^>]*>/gi;
    let m;
    while ((m = sr.exec(d)) !== null) {
      const pos = m.index;
      const line = d.substring(0, pos).split('\n').length;
      console.log('Line ' + line + ': ' + m[0].substring(0, 60));
    }
    console.log('\nSearching for RowSelect...');
    const idx = d.indexOf('RowSelect');
    if (idx >= 0) {
      const line = d.substring(0, idx).split('\n').length;
      console.log('Found at line ' + line + ': ' + d.substring(idx, idx + 80));
    } else {
      console.log('NOT FOUND');
    }
    console.log('\nSearching for ColumnResize...');
    const idx2 = d.indexOf('ColumnResize');
    if (idx2 >= 0) {
      const line = d.substring(0, idx2).split('\n').length;
      console.log('Found at line ' + line);
    } else {
      console.log('NOT FOUND');
    }
  });
});
