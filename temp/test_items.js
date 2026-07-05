const http = require('http');
http.get('http://localhost/comp/invo_form.php?mode=edit&id=1&ajax=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const j = JSON.parse(d);
    const html = j.html;
    console.log('Has embedded-subtable:', html.indexOf('embedded-subtable') > -1);
    console.log('Has tab-pane[1]:', html.indexOf('data-tab-index="1"') > -1);
    
    // Check if items data is present
    const itemsIdx = html.indexOf('data-items');
    if (itemsIdx > -1) {
      console.log('data-items found at:', itemsIdx);
      console.log('data-items:', html.substring(itemsIdx, itemsIdx + 200));
    }
    
    // Check for d2 prefix (embedded subtable)
    console.log('Has d2 prefix:', html.indexOf('data-d2') > -1 || html.indexOf('d2-') > -1);
    
    // Check render_embedded_subtable output
    const embIdx = html.indexOf('id="d2-table"');
    console.log('Has d2-table:', embIdx > -1);
    if (embIdx > -1) {
      console.log(html.substring(embIdx - 50, embIdx + 300));
    }
  });
});
