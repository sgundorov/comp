const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Extract the extra_open code
    const extraIdx = d.indexOf('tab-header');
    if (extraIdx > -1) {
      // Find the surrounding script context
      const before = d.substring(Math.max(0, extraIdx - 300), extraIdx);
      const after = d.substring(extraIdx, extraIdx + 600);
      console.log('extra_open context:');
      console.log(before.substring(before.length - 150));
      console.log('---');
      console.log(after.substring(0, 400));
    }
  });
});
