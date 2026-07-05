const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Check InlineEdit.fields - should NOT have number or sum as editable
    const idx = d.indexOf('InlineEdit.init');
    if (idx > -1) {
      const chunk = d.substring(idx, idx + 2000);
      // Look for field entries
      const fieldMatches = chunk.match(/"(\w+)":\s*\{[^}]*\}/g);
      if (fieldMatches) {
        console.log('InlineEdit editable fields:');
        fieldMatches.forEach(f => console.log('  ', f.substring(0, 80)));
      }
      
      // Check if number appears as editable (dbField:"number")
      console.log('\nHas number as editable:', chunk.indexOf('dbField:"number"') > -1 || chunk.indexOf("dbField: 'number'") > -1);
      console.log('Has sum as editable:', chunk.indexOf('dbField:"sum"') > -1 || chunk.indexOf("dbField: 'sum'") > -1);
    }
  });
});
