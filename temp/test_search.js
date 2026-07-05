const http = require('http');
http.get('http://localhost/comp/invo.php?q=test&sf=1&cols=number,date,client,note&cond=contains', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Status:', res.statusCode, 'Length:', d.length);
    
    // Check pagination
    const pagMatch = d.match(/<div class="pagination">[\s\S]*?<\/div>/);
    if (pagMatch) console.log('Pagination:', pagMatch[0].substring(0, 300));
    
    // Check filter banner
    const filterMatch = d.match(/<div[^>]*class="mode-banner"[^>]*>[\s\S]*?<\/div>/);
    if (filterMatch) console.log('Filter banner:', filterMatch[0].substring(0, 300));
    else console.log('No filter banner');
    
    // Check search input value
    const qMatch = d.match(/name="q"[^>]*value="([^"]*)"/);
    console.log('Search value:', qMatch ? qMatch[1] : 'NOT FOUND');
    
    // Check row count
    const rows = d.match(/data-row-id/g);
    console.log('Rows:', rows ? rows.length : 0);
    
    // Check toolbar
    const tbMatch = d.match(/data-search="([^"]*)"/);
    console.log('Toolbar search attr:', tbMatch ? tbMatch[1] : 'NOT FOUND');
    
    // Check warnings
    const warnings = d.match(/<b>Warning<\/b>[^<]*/g);
    if (warnings) {
      console.log('WARNINGS:');
      warnings.forEach(w => console.log(' -', w.substring(0, 120)));
    } else {
      console.log('No warnings');
    }
  });
});
