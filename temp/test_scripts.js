const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find all <script> tags and what they contain
    let pos = 0;
    let idx = 0;
    while ((pos = d.indexOf('<script>', pos)) !== -1) {
      const end = d.indexOf('</script>', pos);
      const content = d.substring(pos + 8, end);
      const hasBackdrop = content.indexOf('backdrop') > -1;
      const hasCloseAllPanels = content.indexOf('closeAllPanels') > -1;
      const hasSearchPanel = content.indexOf('SearchPanel.init') > -1;
      const hasIIFE = content.indexOf('var backdrop') > -1;
      console.log('Script #' + idx + ' (pos ' + pos + '): length=' + content.length + 
        (hasIIFE ? ' [FORM-MODAL IIFE]' : '') +
        (hasCloseAllPanels ? ' [table init]' : '') +
        (hasSearchPanel ? ' [SearchPanel]' : ''));
      idx++;
      pos = end + 1;
    }
  });
});
