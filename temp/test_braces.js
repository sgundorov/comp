const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const fmi = d.indexOf('var backdrop');
    const si = d.lastIndexOf('<script>', fmi);
    const ei = d.indexOf('</script>', fmi);
    const iife = d.substring(si + 8, ei);
    
    let b = 0;
    for (let i = 0; i < iife.length; i++) {
      if (iife[i] === '{') b++;
      if (iife[i] === '}') b--;
      if (b < 0) {
        // Found extra closing brace
        const before = iife.substring(Math.max(0, i - 100), i + 1);
        const after = iife.substring(i + 1, i + 101);
        console.log('Extra } at offset', i);
        console.log('Before:', before.slice(-80));
        console.log('After:', after.slice(0, 80));
        break;
      }
    }
    console.log('Final brace count:', b);
  });
});
