const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find _invoSave in the actual rendered output
    const idx = d.indexOf('function _invoSave');
    if (idx > -1) {
      console.log('_invoSave found at pos', idx);
      const func = d.substring(idx, idx + 2000);
      console.log(func.substring(0, 1500));
    } else {
      console.log('_invoSave NOT FOUND');
    }
    
    // Check where it's attached
    const attachIdx = d.indexOf('_invF.addEventListener');
    if (attachIdx > -1) {
      console.log('\nAttachment:', d.substring(attachIdx, attachIdx + 100));
    }
  });
});
