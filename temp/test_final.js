const http = require('http');
const vm = require('vm');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const fmi = d.indexOf('var backdrop');
    const si = d.lastIndexOf('<script>', fmi);
    const ei = d.indexOf('</script>', fmi);
    const iife = d.substring(si + 8, ei);
    
    let b = 0;
    for (const c of iife) {
      if (c === '{') b++;
      if (c === '}') b--;
    }
    console.log('Brace count:', b, b === 0 ? 'OK' : 'MISMATCH');
    
    // Also count single quotes to check for syntax issues
    let sq = 0;
    let dq = 0;
    let inStr = false;
    let strChar = '';
    for (let i = 0; i < iife.length; i++) {
      const c = iife[i];
      if (!inStr && (c === '"' || c === "'")) {
        inStr = true;
        strChar = c;
      } else if (inStr && c === strChar && iife[i-1] !== '\\') {
        inStr = false;
      }
    }
    
    // Try to parse with Node
    try {
      new Function(iife);
      console.log('Syntax: VALID');
    } catch(e) {
      console.log('Syntax ERROR:', e.message);
    }
  });
});
