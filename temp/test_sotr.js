const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find sotr lookup data
    const sotrIdx = d.indexOf('sotrLookupOptions');
    if (sotrIdx > -1) {
      console.log('sotrLookupOptions at:', sotrIdx);
      const chunk = d.substring(sotrIdx, sotrIdx + 500);
      console.log(chunk.substring(0, 300));
    } else {
      console.log('sotrLookupOptions NOT FOUND');
      // Search for sotr-related data
      const idx = d.indexOf('"sotr"');
      if (idx > -1) console.log('Found "sotr" at:', idx, d.substring(idx - 20, idx + 100));
    }
    
    // Check inlineEdit getLookupData
    const ilIdx = d.indexOf('getLookupData: function');
    if (ilIdx > -1) {
      console.log('\ngetLookupData:', d.substring(ilIdx, ilIdx + 400));
    }
  });
});
