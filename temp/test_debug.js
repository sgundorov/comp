const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Check PHP errors
    const errors = d.match(/<b>(Warning|Fatal error|Parse error|Notice)<\/b>[^<]*/g);
    if (errors) { console.log('PHP ERRORS:'); errors.forEach(e => console.log(e.substring(0, 150))); }
    else console.log('No PHP errors');
    
    // Check _invoSave is defined
    console.log('_invoSave:', d.indexOf('function _invoSave') > -1);
    
    // Extract and validate the IIFE
    const fmi = d.indexOf('var backdrop');
    const si = d.lastIndexOf('<script>', fmi);
    const ei = d.indexOf('</script>', fmi);
    const iife = d.substring(si + 8, ei);
    try { new Function(iife); console.log('IIFE syntax: VALID'); } catch(e) { console.log('IIFE syntax ERROR:', e.message); }
    
    // Check braces
    let b = 0;
    for (const c of iife) { if (c === '{') b++; if (c === '}') b--; }
    console.log('Braces:', b === 0 ? 'balanced' : 'MISMATCH ' + b);
    
    // Check the save handler code quality
    const saveIdx = iife.indexOf('function _invoSave');
    if (saveIdx > -1) {
      const saveFunc = iife.substring(saveIdx, saveIdx + 1200);
      console.log('\n_invoSave function (first 500 chars):');
      console.log(saveFunc.substring(0, 500));
    }
  });
});
