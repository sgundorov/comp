const http = require('http');
const vm = require('vm');
http.get('http://localhost/comp/group.php?sort=note%3Aasc', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const lines = d.split('\n');
    console.log('Total lines:', lines.length);
    const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
    let match;
    let idx = 0;
    while ((match = scriptRegex.exec(d)) !== null) {
      const code = match[1].trim();
      if (code.length < 10) continue;
      idx++;
      try {
        vm.compileFunction(code);
      } catch (e) {
        console.log('SYNTAX ERROR in script #' + idx + ':', e.message);
      }
    }
    console.log('Checked', idx, 'script blocks');
    // Check for initSgTable
    if (d.includes('initSgTable')) console.log('initSgTable: found');
    else console.log('initSgTable: NOT FOUND');
    if (d.includes('initGroupSgroupTable')) console.log('initGroupSgroupTable: STILL PRESENT');
    else console.log('initGroupSgroupTable: removed');
  });
});
