const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find ALL occurrences of _invF.addEventListener
    let pos = 0;
    let count = 0;
    while ((pos = d.indexOf('_invF.addEventListener', pos)) !== -1) {
      count++;
      // Find the function this is inside
      const before = d.substring(Math.max(0, pos - 500), pos);
      // Find the nearest function declaration
      const funcMatches = before.match(/function\s+(\w+)/g);
      const lastFunc = funcMatches ? funcMatches[funcMatches.length - 1] : 'unknown';
      console.log('Occurrence #' + count + ' at pos', pos, '- inside function:', lastFunc);
      console.log('  Context:', d.substring(pos - 50, pos + 80).replace(/\n/g, ' ').trim());
      pos += 10;
    }
    console.log('Total occurrences:', count);
  });
});
