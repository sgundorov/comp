const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the extra_open code in the form-modal-handler
    const idx = d.indexOf('tabContainer = body.querySelector');
    if (idx > -1) {
      console.log('extra_open found at:', idx);
      console.log(d.substring(idx - 50, idx + 400));
    } else {
      console.log('extra_open NOT found');
    }
    
    // Check if the form-modal IIFE has any issues - find FormModalCore.init
    const fmcIdx = d.indexOf('FormModalCore');
    if (fmcIdx > -1) {
      console.log('\nFormModalCore at:', fmcIdx);
      console.log(d.substring(fmcIdx, fmcIdx + 100));
    }
  });
});
