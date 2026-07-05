const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const fmi = d.indexOf('var backdrop');
    const si = d.lastIndexOf('<script>', fmi);
    const ei = d.indexOf('</script>', fmi);
    const iife = d.substring(si + 8, ei);
    
    try {
      new Function(iife);
      console.log('IIFE syntax: VALID');
    } catch(e) {
      console.log('IIFE syntax ERROR:', e.message);
    }
    
    let b = 0;
    for (const c of iife) { if (c === '{') b++; if (c === '}') b--; }
    console.log('Braces:', b === 0 ? 'balanced' : 'MISMATCH ' + b);
    
    console.log('Has EmbeddedSubTable.create:', iife.indexOf('EmbeddedSubTable.create') > -1);
    console.log('Has __d2Table:', iife.indexOf('__d2Table') > -1);
    console.log('Has tabContainer:', iife.indexOf('tabContainer') > -1);
  });
});
