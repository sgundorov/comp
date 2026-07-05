const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Check the IIFE for the openFormModal function specifically
    const openIdx = d.indexOf('function openFormModal(');
    const closeIdx = d.indexOf('function closeFormModal(');
    if (openIdx > -1 && closeIdx > -1) {
      const openFunc = d.substring(openIdx, closeIdx);
      // Check that bindForm is called with the form variable
      console.log('bindForm(form) in openFormModal:', openFunc.indexOf('bindForm(form)') > -1);
      console.log('body.innerHTML = data.html:', openFunc.indexOf('body.innerHTML = data.html') > -1);
      console.log('form[data-form-modal]:', openFunc.indexOf('form[data-form-modal]') > -1);
      
      // Check for any potential issues
      console.log('Has fetch:', openFunc.indexOf('fetch(') > -1);
      console.log('Has .then:', openFunc.indexOf('.then(') > -1);
      console.log('Has .catch:', openFunc.indexOf('.catch(') > -1);
    }
    
    // Also check the bindForm function
    const bindFormIdx = d.indexOf('function bindForm(form)');
    if (bindFormIdx > -1) {
      const bindFunc = d.substring(bindFormIdx, bindFormIdx + 800);
      console.log('\nbindForm function:');
      console.log('Has addEventListener submit:', bindFunc.indexOf("addEventListener('submit'") > -1);
      console.log('Has e.preventDefault:', bindFunc.indexOf('e.preventDefault()') > -1);
      console.log('Has fetch:', bindFunc.indexOf('fetch(') > -1);
      console.log('Has FormData:', bindFunc.indexOf('new FormData') > -1);
    }
  });
});
