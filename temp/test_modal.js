const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('lookup.js loaded:', d.indexOf('lookup.js') > -1);
    console.log('form-modal-core.js:', d.indexOf('form-modal-core.js') > -1);
    console.log('JSON.parse(el.getAttribute:', d.indexOf('JSON.parse(el.getAttribute') > -1);
    
    // Find the form-modal script block and check lookup binding
    const idx = d.indexOf('function openFormModal');
    if (idx > -1) {
      const chunk = d.substring(idx, idx + 800);
      const lookupIdx = chunk.indexOf('bindLookup');
      if (lookupIdx > -1) {
        console.log('bindLookup in openFormModal:', chunk.substring(lookupIdx - 20, lookupIdx + 120));
      } else {
        console.log('bindLookup NOT found in openFormModal');
      }
    }
    
    // Check for PHP errors
    const warnings = d.match(/<b>(Warning|Fatal error|Parse error)<\/b>/g);
    if (warnings) console.log('PHP errors:', warnings.length, warnings);
    else console.log('No PHP errors');
    
    // Check form modal backdrop exists
    console.log('formModal backdrop:', d.indexOf('id="formModal"') > -1);
    console.log('formModalBody:', d.indexOf('id="formModalBody"') > -1);
  });
});
