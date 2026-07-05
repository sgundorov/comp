const http = require('http');
const fs = require('fs');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    fs.writeFileSync('C:\\xa\\htdocs\\comp\\temp\\invo_dump2.html', d);
    console.log('Size:', d.length);
    
    // Check for warnings
    const warnings = d.match(/Warning[^<]*/g);
    if (warnings) {
      console.log('WARNINGS FOUND:');
      warnings.forEach(w => console.log(' -', w.trim().substring(0, 100)));
    } else {
      console.log('No PHP warnings');
    }
    
    // Check pagination
    const pag = d.match(/<div class="pagination">[\s\S]*?<\/div>/);
    if (pag) console.log('Pagination HTML (200 chars):', pag[0].substring(0, 200));
    
    // Check columns format
    const spCol = d.match(/SearchPanel\.init\(\{[\s\S]*?columns:\s*(\[|\{)/);
    console.log('SearchPanel columns:', spCol ? spCol[1] : 'NOT FOUND');
  });
});
