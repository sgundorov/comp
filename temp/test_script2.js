const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Check script #2
    const scriptStart = 70048;
    const content = d.substring(scriptStart, scriptStart + 200);
    console.log('Script #2 start:', content);
    console.log('\nScript #2 end:');
    const end = d.indexOf('</script>', scriptStart);
    console.log(d.substring(end - 200, end + 10));
  });
});
