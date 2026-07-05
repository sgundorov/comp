const http = require('http');
// Get the standalone page to extract the embedded subtable init code
http.get('http://localhost/comp/invo_form.php?mode=edit&id=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the EmbeddedSubTable.create call
    const idx = d.indexOf('EmbeddedSubTable.create');
    if (idx > -1) {
      // Go back to find function start
      const funcStart = d.lastIndexOf('function initD2Table', idx);
      const funcEnd = d.indexOf('}', d.indexOf('ColumnResize.init', idx)) + 1;
      console.log('Init function:');
      console.log(d.substring(funcStart, funcEnd + 50));
    }
  });
});
