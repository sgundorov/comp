const http = require('http');
const vm = require('vm');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Extract the IIFE
    const fmi = d.indexOf('var backdrop');
    const si = d.lastIndexOf('<script>', fmi);
    const ei = d.indexOf('</script>', fmi);
    const iife = d.substring(si + 8, ei);
    
    // Create a mock DOM environment
    const mock = {
      document: {
        getElementById: () => ({ innerHTML: '', classList: { contains: () => false, add: () => {}, remove: () => {} }, querySelector: () => null, querySelectorAll: () => [], appendChild: () => {}, addEventListener: () => {} }),
        addEventListener: () => {},
        body: { style: {}, overflow: '' }
      },
      window: { FormModalCore: { appendAjax: u => u, bindFormTabTrap: () => {}, focusFirstField: () => {}, setFocusAfterSave: () => {} }, bindLookup: () => {}, __openFormModal: null },
      console: console,
      fetch: () => Promise.resolve({ ok: true, json: () => Promise.resolve({}) }),
      URLSearchParams: URLSearchParams,
      alert: () => {}
    };
    
    try {
      vm.createContext(mock);
      vm.runInContext(iife, mock);
      console.log('IIFE executed WITHOUT error');
      console.log('__openFormModal set:', typeof mock.window.__openFormModal);
    } catch(e) {
      console.log('IIFE runtime ERROR:', e.message);
      console.log('At line:', e.stack.split('\n')[0]);
    }
  });
});
