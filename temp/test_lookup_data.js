const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    // Find getLookupData and extract the full map
    const idx = d.indexOf('getLookupData: function');
    if (idx > -1) {
      // Find the map object
      const mapIdx = d.indexOf('var map =', idx);
      if (mapIdx > -1) {
        // Extract until the closing }; of var map
        let depth = 0;
        let start = d.indexOf('{', mapIdx);
        let end = start;
        for (let i = start; i < d.length; i++) {
          if (d[i] === '{') depth++;
          if (d[i] === '}') depth--;
          if (depth === 0) { end = i + 1; break; }
        }
        const mapStr = d.substring(start, end);
        const map = JSON.parse(mapStr);
        console.log('Lookup keys:', Object.keys(map));
        console.log('Sotr count:', map.sotr ? map.sotr.length : 'MISSING');
        if (map.sotr) {
          map.sotr.forEach(s => console.log('  ', s.id, JSON.stringify(s.name)));
        }
        console.log('Store count:', map.store ? map.store.length : 'MISSING');
      }
    }
  });
});
