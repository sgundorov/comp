const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the old broken bindLookup(el, "client") context
    const oldPattern = 'bindLookup(el, "client")';
    const pos = d.indexOf(oldPattern);
    if (pos > -1) {
      console.log('OLD broken call found at pos:', pos);
      // Get surrounding context - go back to find the enclosing function/script
      const start = Math.max(0, pos - 500);
      console.log('Context before:', d.substring(start, pos).slice(-300));
      console.log('Context after:', d.substring(pos, pos + 300));
    } else {
      console.log('OLD pattern not found');
    }
  });
});
