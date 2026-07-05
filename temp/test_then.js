const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const fmi = d.indexOf('var backdrop');
    const si = d.lastIndexOf('<script>', fmi);
    const ei = d.indexOf('</script>', fmi);
    const iife = d.substring(si + 8, ei);
    
    // Find the openFormModal function
    const ofmStart = iife.indexOf('function openFormModal(');
    if (ofmStart === -1) { console.log('openFormModal NOT FOUND'); return; }
    
    // Find the .then callback
    const thenStart = iife.indexOf('.then(function (data) {', ofmStart);
    const thenEnd = iife.indexOf('})', thenStart + 200);
    const thenBlock = iife.substring(thenStart, thenEnd);
    
    console.log('=== .then callback ===');
    console.log(thenBlock);
  });
});
