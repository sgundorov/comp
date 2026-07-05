const http = require('http');
http.get('http://localhost/comp/group.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const sr = /<script[^>]*>([\s\S]*?)<\/script>/gi;
    let m; let i = 0;
    while ((m = sr.exec(d)) !== null) {
      const c = m[1].trim();
      if (c.length < 10) continue;
      i++;
      if (i === 2) {
        const lines = c.split('\n');
        console.log('Script #2:', lines.length, 'lines');
        if (c.includes('RowSelect')) console.log('  HAS RowSelect');
        else console.log('  MISSING RowSelect');
        if (c.includes('InlineEdit')) console.log('  HAS InlineEdit');
        else console.log('  MISSING InlineEdit');
        if (c.includes('ColumnResize')) console.log('  HAS ColumnResize');
        else console.log('  MISSING ColumnResize');
        if (c.includes('bindTableKeyboardShortcuts')) console.log('  HAS keyboard');
        else console.log('  MISSING keyboard');
      }
    }
  });
});
