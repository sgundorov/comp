const http = require('http');
http.get('http://localhost/comp/invo_form.php?mode=edit&id=1&ajax=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const j = JSON.parse(d);
    const html = j.html;
    // Find the embedded subtable scripts section
    const scriptsIdx = html.indexOf('EmbeddedSubTable');
    if (scriptsIdx > -1) {
      console.log('EmbeddedSubTable found at:', scriptsIdx);
      console.log(html.substring(scriptsIdx, scriptsIdx + 500));
    } else {
      console.log('EmbeddedSubTable NOT in AJAX response');
    }
    
    // Check if render_embedded_subtable is in the HTML
    const embIdx = html.indexOf('id="d2-table"');
    if (embIdx > -1) {
      console.log('\nTable HTML found');
    }
  });
});
