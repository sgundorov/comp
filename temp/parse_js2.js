const fs = require('fs');
const vm = require('vm');
const html = fs.readFileSync('C:/xa/htdocs/comp/temp/rendered.html', 'utf8');
const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
let match;
let idx = 0;
while ((match = scriptRegex.exec(html)) !== null) {
  const code = match[1].trim();
  if (code.length < 10) continue;
  idx++;
  try {
    vm.compileFunction(code);
  } catch (e) {
    console.log('=== SYNTAX ERROR in script block #' + idx + ' ===');
    console.log('Error:', e.message);
    const lines = code.split('\n');
    console.log('Total lines:', lines.length);
    console.log('Last 10 lines:');
    for (let i = Math.max(0, lines.length - 10); i < lines.length; i++) {
      console.log((i + 1) + ': ' + lines[i]);
    }
    console.log('First 20 lines:');
    for (let i = 0; i < Math.min(20, lines.length); i++) {
      console.log((i + 1) + ': ' + lines[i]);
    }
  }
}
console.log('Checked', idx, 'script blocks');
