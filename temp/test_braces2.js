const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const fmi = d.indexOf('var backdrop');
    const si = d.lastIndexOf('<script>', fmi);
    const ei = d.indexOf('</script>', fmi);
    const iife = d.substring(si + 8, ei);
    console.log('IIFE length:', iife.length);
    
    // Count braces by line
    const lines = iife.split('\n');
    let b = 0;
    for (let i = 0; i < lines.length; i++) {
      for (const c of lines[i]) {
        if (c === '{') b++;
        if (c === '}') b--;
      }
      if (b < 0) {
        console.log('EXTRA } at line', i + 1, ':', lines[i].trim());
        console.log('Prev line:', lines[i-1] ? lines[i-1].trim() : '');
        break;
      }
    }
    console.log('Final brace count:', b);
  });
});
