const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Get the full <script> block containing the IIFE
    const fmi = d.indexOf('var backdrop');
    const scriptStart = d.lastIndexOf('<script>', fmi);
    const scriptEnd = d.indexOf('</script>', fmi);
    const script = d.substring(scriptStart + 8, scriptEnd);
    
    // Count braces line by line
    const lines = script.split('\n');
    let b = 0;
    let firstNeg = -1;
    for (let i = 0; i < lines.length; i++) {
      for (const c of lines[i]) {
        if (c === '{') b++;
        if (c === '}') b--;
      }
      if (firstNeg === -1 && b < 0) {
        firstNeg = i;
        console.log('First negative at line', i + 1, 'count:', b, ':', lines[i].trim().substring(0, 80));
      }
    }
    console.log('Final brace count:', b);
    console.log('Total lines:', lines.length);
    
    // Also check if there are any PHP warnings embedded
    if (script.indexOf('Warning') > -1) {
      console.log('WARNING found in IIFE!');
    }
    if (script.indexOf('Notice') > -1) {
      console.log('Notice found in IIFE!');
    }
  });
});
