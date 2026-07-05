const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the local bindLookup function definition
    const funcIdx = d.indexOf('function bindLookup(root, tableName)');
    if (funcIdx > -1) {
      console.log('Local bindLookup function:');
      console.log(d.substring(funcIdx, funcIdx + 300));
    } else {
      console.log('Local bindLookup NOT found!');
    }
    
    console.log('\n---');
    
    // Find the call sites in openFormModal
    const openIdx = d.indexOf('body.innerHTML = data.html');
    if (openIdx > -1) {
      const chunk = d.substring(openIdx, openIdx + 1000);
      const blIdx = chunk.indexOf('bindLookup');
      if (blIdx > -1) {
        console.log('bindLookup call in openFormModal:');
        console.log(chunk.substring(blIdx - 20, blIdx + 150));
      }
    }
  });
});
