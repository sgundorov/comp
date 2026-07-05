const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find the "Add" button
    const addBtnIdx = d.indexOf('data-form-open');
    if (addBtnIdx > -1) {
      console.log('Add button:', d.substring(addBtnIdx - 50, addBtnIdx + 100));
    }
    
    // Check if the IIFE has the click handler for data-form-open
    const clickIdx = d.indexOf('data-form-open');
    const allClickHandlers = [];
    let pos = 0;
    while ((pos = d.indexOf('data-form-open', pos)) !== -1) {
      allClickHandlers.push({pos, context: d.substring(Math.max(0, pos - 30), pos + 50)});
      pos += 10;
    }
    console.log('\ndata-form-open occurrences:', allClickHandlers.length);
    allClickHandlers.forEach((h, i) => console.log(i + ':', h.context.replace(/\n/g, ' ').trim()));
    
    // Check for any PHP errors that would break the page
    const errors = d.match(/<b>(Warning|Fatal error|Notice)<\/b>[^<]*/g);
    if (errors) {
      console.log('\nPHP ERRORS:');
      errors.forEach(e => console.log(e.substring(0, 150)));
    } else {
      console.log('\nNo PHP errors');
    }
  });
});
