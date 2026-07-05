const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('frm.addEventListener submit:', d.indexOf('frm.addEventListener("submit"') > -1);
    console.log('closeFormModal():', d.indexOf('closeFormModal()') > -1);
    console.log('location.href invo.php:', d.indexOf('location.href = "invo.php"') > -1);
    console.log('No errors:', d.indexOf('Warning') === -1);
    
    // Verify discount has no %
    console.log('No % in discount:', d.indexOf("$raw . '%'") === -1);
    
    // Verify sotr has CONCAT
    console.log('sotr CONCAT:', d.indexOf("CONCAT(COALESCE(last_name") > -1);
  });
});
