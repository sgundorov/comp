const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Check the form-modal IIFE for the extra_open code placement
    const extraIdx = d.indexOf('var tabContainer = body.querySelector');
    if (extraIdx > -1) {
      // Find which function this is inside
      const before = d.substring(Math.max(0, extraIdx - 500), extraIdx);
      const lastFunc = before.lastIndexOf('function ');
      const lastBrace = before.lastIndexOf('{');
      console.log('extra_open is inside function starting at offset:', lastFunc);
      console.log('Context:', before.slice(-200));
    }
    
    // Check if the IIFE wraps properly - find the closing })();
    const iifeEnd = d.indexOf('})();', extraIdx);
    if (iifeEnd > -1) {
      console.log('\nIIFE end at:', iifeEnd);
      console.log('Between extra_open and IIFE end:');
      const between = d.substring(extraIdx, iifeEnd + 5);
      // Count braces
      let b = 0;
      for (const c of between) {
        if (c === '{') b++;
        if (c === '}') b--;
      }
      console.log('Brace count in extra_open section:', b);
    }
  });
});
