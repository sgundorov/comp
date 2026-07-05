const http = require('http');
const fs = require('fs');
http.get('http://localhost/comp/group.php?sort=note%3Aasc', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    fs.writeFileSync('C:/xa/htdocs/comp/temp/rendered.html', d);
    const lines = d.split('\n');
    console.log('Total lines:', lines.length);
    const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
    let match;
    let idx = 0;
    const vm = require('vm');
    while ((match = scriptRegex.exec(d)) !== null) {
      const code = match[1].trim();
      if (code.length < 10) continue;
      idx++;
      try {
        vm.compileFunction(code);
      } catch (e) {
        console.log('SYNTAX ERROR in script #' + idx + ':', e.message);
        const codeLines = code.split('\n');
        for (let i = 0; i < codeLines.length; i++) {
          if (codeLines[i].includes('_sp') || codeLines[i].includes('SearchPanel') || codeLines[i].includes('searchCond')) {
            console.log('  L' + (i+1) + ': ' + codeLines[i].substring(0, 120));
          }
        }
      }
    }
    console.log('Checked', idx, 'script blocks - all OK');
  });
});
