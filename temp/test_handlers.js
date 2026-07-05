const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find ALL script blocks that contain "var backdrop"
    let pos = 0;
    let count = 0;
    while ((pos = d.indexOf('var backdrop = document.getElementById', pos)) !== -1) {
      count++;
      console.log('=== backdrop #' + count + ' at pos ' + pos + ' ===');
      // Find the nearest <script> before
      const scriptStart = d.lastIndexOf('<script>', pos);
      const beforeScript = d.substring(Math.max(0, scriptStart - 200), scriptStart);
      console.log('Context before <script>:', beforeScript.substring(beforeScript.length - 150));
      pos += 10;
    }
    console.log('Total backdrop handlers:', count);
  });
});
