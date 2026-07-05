const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const extraIdx = d.indexOf('var tabContainer = body.querySelector');
    // Show 300 chars before and 300 after
    console.log('=== 300 chars BEFORE extra_open ===');
    console.log(d.substring(extraIdx - 300, extraIdx));
    console.log('=== extra_open starts ===');
    console.log(d.substring(extraIdx, extraIdx + 400));
  });
});
