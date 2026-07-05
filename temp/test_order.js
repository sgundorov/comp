const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Check the order: render_form_modal_script IIFE vs extra_open
    const fmi = d.indexOf('var backdrop');
    const extraIdx = d.indexOf('function _invoSave');
    const bindFormIdx = d.indexOf('function bindForm(form)');
    
    console.log('form-modal backdrop at:', fmi);
    console.log('bindForm at:', bindFormIdx);
    console.log('_invoSave at:', extraIdx);
    console.log('\nbindForm before _invoSave:', bindFormIdx < extraIdx);
    
    // Check where _invoSave is called (where the listener is added)
    const invfIdx = d.indexOf('_invF.addEventListener("submit", _invoSave, true)');
    console.log('_invF.addEventListener at:', invfIdx);
    console.log('This is AFTER bindForm call:', invfIdx > bindFormIdx);
    
    // The key question: does _invoSave run in capture phase? Yes, true = capture
    // Does bindForm also run? It runs in bubbling phase (no capture arg)
    // So _invoSave (capture) fires FIRST, calls stopImmediatePropagation
    // This blocks ALL other submit handlers on the form, including bindForm's
    
    // BUT: does the form have _invF? Let me check
    console.log('\n_invF selector:', d.indexOf('var _invF = body.querySelector') > -1);
  });
});
