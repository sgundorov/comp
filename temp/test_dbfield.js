const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const idx = d.indexOf('InlineEdit.init');
    if (idx > -1) {
      // Find the fields object - extract the JSON between "fields:" and the next comma+newline
      const chunk = d.substring(idx, idx + 3000);
      // Find all dbField values
      const dbFields = chunk.match(/dbField:\s*"?(\w+)"?/g);
      console.log('dbField entries:', dbFields);
    }
  });
});
