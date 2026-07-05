const http = require('http');
const vm = require('vm');
function check(url, label) {
  return new Promise((resolve) => {
    http.get(url, res => {
      let d = '';
      res.on('data', c => d += c);
      res.on('end', () => {
        // Check if it's JSON (AJAX response)
        try {
          const json = JSON.parse(d);
          if (json.html) {
            d = json.html;
            console.log(label + ': AJAX response, checking html...');
          }
        } catch(e) {}
        const lines = d.split('\n');
        const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
        let match; let idx = 0; let errors = [];
        while ((match = scriptRegex.exec(d)) !== null) {
          const code = match[1].trim();
          if (code.length < 10) continue;
          idx++;
          try { vm.compileFunction(code); } catch (e) { errors.push('#' + idx + ': ' + e.message); }
        }
        console.log(label + ': ' + lines.length + ' lines, ' + idx + ' scripts, errors: ' + (errors.length || 'none'));
        if (errors.length) errors.forEach(e => console.log('  ' + e));
        // Check for initSgTable
        if (d.includes('initSgTable')) console.log('  initSgTable: found');
        if (d.includes('initGroupSgroupTable')) console.log('  initGroupSgroupTable: STILL PRESENT');
        resolve();
      });
    });
  });
}
Promise.all([
  check('http://localhost/comp/group.php?sort=note%3Aasc', 'group.php'),
  check('http://localhost/comp/group_form.php?mode=edit&id=1&ajax=1', 'group_form.php')
]);
