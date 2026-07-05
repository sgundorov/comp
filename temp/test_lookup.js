const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Status:', res.statusCode, 'Length:', d.length);
    const warnings = d.match(/<b>Warning<\/b>[^<]*/g);
    if (warnings) warnings.forEach(w => console.log('WARNING:', w.substring(0, 200)));
    
    console.log('lookupData:', d.indexOf('lookupData') > -1);
    console.log('clientLookupOptions:', d.indexOf('clientLookupOptions') > -1);
    console.log('map[field]:', d.indexOf('map[field]') > -1);
    
    // Find the getLookupData section
    const idx = d.indexOf('getLookupData');
    if (idx > -1) console.log('getLookupData context:', d.substring(idx, idx + 300));
    else console.log('getLookupData NOT FOUND');
  });
});
