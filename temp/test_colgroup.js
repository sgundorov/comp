const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the colgroup
    const colIdx = d.indexOf('<colgroup>');
    if (colIdx > -1) {
      const colEnd = d.indexOf('</colgroup>', colIdx);
      const colgroup = d.substring(colIdx, colEnd + 11);
      // Find the note col
      const noteIdx = colgroup.indexOf('col-note');
      if (noteIdx > -1) {
        console.log('Note col:', colgroup.substring(noteIdx - 10, noteIdx + 60));
      }
    }
    
    // Check the rendered CSS
    const nowrapIdx = d.indexOf('white-space: nowrap');
    if (nowrapIdx > -1) {
      console.log('CSS:', d.substring(nowrapIdx - 20, nowrapIdx + 80));
    }
  });
});
