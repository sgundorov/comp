const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the openFormModal .then callback and check order
    const thenIdx = d.indexOf('.then(function (data) {');
    const nextThen = d.indexOf('.then(function (data) {', thenIdx + 1);
    
    if (thenIdx > -1) {
      const block = d.substring(thenIdx, thenIdx + 2000);
      // Check order: bindForm, lookup binding, stashed=null
      const bindFormIdx = block.indexOf('bindForm(form)');
      const lookupIdx = block.indexOf('data-lookup');
      const stashedIdx = block.indexOf('stashed = null');
      const extraOpenIdx = block.indexOf('tabContainer');
      
      console.log('Order in .then callback:');
      console.log('  bindForm:', bindFormIdx > -1 ? 'pos ' + bindFormIdx : 'NOT FOUND');
      console.log('  lookup binding:', lookupIdx > -1 ? 'pos ' + lookupIdx : 'NOT FOUND');
      console.log('  stashed=null:', stashedIdx > -1 ? 'pos ' + stashedIdx : 'NOT FOUND');
      console.log('  tabContainer:', extraOpenIdx > -1 ? 'pos ' + extraOpenIdx : 'NOT FOUND');
      
      if (bindFormIdx > -1 && stashedIdx > -1) {
        console.log('\nstashed=null AFTER bindForm:', stashedIdx > bindFormIdx ? 'YES (correct)' : 'NO (wrong!)');
      }
    }
  });
});
