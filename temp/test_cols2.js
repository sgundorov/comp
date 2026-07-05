const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Status:', res.statusCode);
    const w = d.match(/<b>Warning<\/b>/g);
    console.log('Warnings:', w ? w.length : 0);
    
    // Extract SearchPanel columns
    const match = d.match(/SearchPanel\.init\(\{[\s\S]*?columns:\s*(\[[\s\S]*?\])/);
    if (match) {
      const cols = JSON.parse(match[1]);
      console.log('SearchPanel columns:', cols.map(c => c.key).join(', '));
    }
    
    // Check inline edit fields
    const inlineMatch = d.match(/InlineEdit\.init\(\{[\s\S]*?fields:\s*(\{[\s\S]*?\})/);
    if (inlineMatch) {
      const fields = JSON.parse(inlineMatch[1]);
      console.log('InlineEdit fields:', Object.keys(fields).join(', '));
    }
  });
});
