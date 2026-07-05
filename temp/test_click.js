const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the click handler that processes data-form-open
    const idx = d.indexOf("button[data-form-open]");
    if (idx > -1) {
      console.log('Found data-form-open handler:');
      console.log(d.substring(idx - 200, idx + 200));
    }
    
    // Check the Add button HTML
    const addIdx = d.indexOf('data-form-open="invo_form');
    if (addIdx > -1) {
      console.log('\nAdd button:');
      console.log(d.substring(addIdx - 50, addIdx + 100));
    }
    
    // Most importantly - check if the form-modal-handler IIFE runs at all
    // by looking for window.__openFormModal at the end
    const openIdx = d.indexOf('window.__openFormModal');
    if (openIdx > -1) {
      console.log('\nwindow.__openFormModal defined at pos:', openIdx);
    }
  });
});
