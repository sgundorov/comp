const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the full form-modal IIFE
    const fmi = d.indexOf('var backdrop = document.getElementById(\'formModal\')');
    const scriptEnd = d.indexOf('</script>', fmi);
    const iife = d.substring(fmi, scriptEnd);
    
    // Check for syntax issues - look for unbalanced braces
    let braces = 0;
    for (let i = 0; i < iife.length; i++) {
      if (iife[i] === '{') braces++;
      if (iife[i] === '}') braces--;
    }
    console.log('Brace balance:', braces, braces === 0 ? 'OK' : 'MISMATCH!');
    
    // Check the form action JS
    const actionIdx = iife.indexOf('form.getAttribute');
    if (actionIdx > -1) {
      console.log('form action line:', iife.substring(actionIdx, actionIdx + 100));
    }
    
    // Check the fetch URL
    const fetchIdx = iife.indexOf('fetch(');
    if (fetchIdx > -1) {
      console.log('fetch call:', iife.substring(fetchIdx, fetchIdx + 150));
    }
    
    // Check location.href redirect
    const locIdx = iife.indexOf('location.href');
    if (locIdx > -1) {
      console.log('redirect:', iife.substring(locIdx, locIdx + 100));
    }
    
    // Check for any PHP warnings embedded in the JS
    const warnIdx = iife.indexOf('Warning');
    if (warnIdx > -1) {
      console.log('WARNING in IIFE:', iife.substring(warnIdx - 50, warnIdx + 100));
    }
  });
});
