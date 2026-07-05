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
      // Find the error location
      const lineMatch = e.message.match(/position (\d+)/);
      if (lineMatch) {
        const pos = parseInt(lineMatch[1]);
        console.log('Near:', iife.substring(Math.max(0, pos - 80), pos + 80));
      }
    }
    
    let b = 0;
    for (const c of iife) { if (c === '{') b++; if (c === '}') b--; }
    console.log('Braces:', b === 0 ? 'balanced' : 'MISMATCH ' + b);
  });
});
