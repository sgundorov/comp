const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Get the full IIFE click handler
    const clickIdx = d.indexOf("document.addEventListener('click'");
    const clickEnd = d.indexOf('}, true);', clickIdx) + 10;
    const clickHandler = d.substring(clickIdx, clickEnd);
    
    // Check if any part could intercept submit button clicks
    console.log('=== Click handler (capture phase) ===');
    
    // Check for stopImmediatePropagation calls
    const stopCount = (clickHandler.match(/stopImmediatePropagation/g) || []).length;
    console.log('stopImmediatePropagation calls:', stopCount);
    
    // Check all the conditions that could stop propagation
    const conditions = [
      'a.btn-secondary',
      'a[data-lookup-add]',
      'a[href*="invo_form.php"]',
      'button[data-form-open]',
      'button[onclick*="invo_form.php"]'
    ];
    conditions.forEach(c => {
      console.log('  Checks for:', c, '— would NOT match submit button');
    });
    
    // Check the FULL form actions
    console.log('\n=== form-actions HTML ===');
    const faIdx = d.indexOf('form-actions');
    if (faIdx > -1) {
      console.log(d.substring(faIdx - 20, faIdx + 300));
    }
    
    // Check if the form-actions is INSIDE the form tag
    const formIdx = d.indexOf('<form');
    const formCloseIdx = d.indexOf('</form>');
    const formActionsIdx = d.indexOf('form-actions', formIdx);
    console.log('\nform tag start:', formIdx);
    console.log('form-actions:', formActionsIdx);
    console.log('form close:', formCloseIdx);
    console.log('form-actions inside form:', formActionsIdx > formIdx && formActionsIdx < formCloseIdx);
  });
});
