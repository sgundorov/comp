const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find all bindLookup occurrences
    let pos = 0;
    let count = 0;
    while ((pos = d.indexOf('bindLookup', pos)) !== -1) {
      count++;
      console.log('bindLookup #' + count + ' at pos ' + pos + ':', d.substring(pos - 30, pos + 100));
      pos += 10;
    }
    console.log('Total bindLookup occurrences:', count);
    
    // Find formModalConfig in page
    const fmc = d.indexOf('lookup_tables');
    console.log('lookup_tables in page:', fmc > -1);
    
    // Check the form-modal-handler IIFE
    const fmi = d.indexOf('var backdrop = document.getElementById(\'formModal\')');
    if (fmi > -1) {
      const chunk = d.substring(fmi, fmi + 2000);
      console.log('form-modal handler (first 500):', chunk.substring(0, 500));
    }
  });
});
