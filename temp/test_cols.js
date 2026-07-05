const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Check if columns is array (starts with [) or object (starts with {)
    const spMatch = d.match(/SearchPanel\.init\(\{[\s\S]*?columns:\s*(\[|\{)/);
    const sortMatch = d.match(/SortPanel\.init\(\{[\s\S]*?columns:\s*(\[|\{)/);
    console.log('SearchPanel columns type:', spMatch ? spMatch[1] : 'NOT FOUND');
    console.log('SortPanel columns type:', sortMatch ? sortMatch[1] : 'NOT FOUND');
    
    // Also check the full columns value
    const colMatch = d.match(/columns:\s*(\[[\s\S]*?\])\s*,\s*closeAllPanels/);
    if (colMatch) {
      console.log('SearchPanel columns (first 200):', colMatch[1].substring(0, 200));
    }
  });
});
