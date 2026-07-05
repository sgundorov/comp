const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const idx = d.indexOf('InlineEdit.init');
    if (idx > -1) {
      const chunk = d.substring(idx, idx + 3000);
      // Find "fields:" and get next 1000 chars
      const fi = chunk.indexOf('fields:');
      if (fi > -1) {
        const fieldsChunk = chunk.substring(fi, fi + 1500);
        console.log('Fields section:', fieldsChunk.substring(0, 1200));
      }
    }
  });
});
