const fs = require('fs');
const html = fs.readFileSync('C:/xa/htdocs/comp/temp/rendered.html', 'utf8');
const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
let match;
let idx = 0;
while ((match = scriptRegex.exec(html)) !== null) {
  const code = match[1].trim();
  if (code.length < 10) continue;
  idx++;
  try {
    new Function(code);
  } catch (e) {
    console.log('=== SYNTAX ERROR in script block #' + idx + ' ===');
    console.log('Error:', e.message);
    const lines = code.split('\n');
    const lineNum = parseInt((e.message.match(/line (\d+)/) || [])[1] || '0');
    if (lineNum > 0) {
      for (let i = Math.max(0, lineNum - 3); i < Math.min(lines.length, lineNum + 3); i++) {
        console.log((i + 1) + (i + 1 === lineNum ? ' >>> ' : '     ') + lines[i]);
      }
    }
    console.log('Block starts at HTML char offset:', match.index);
    console.log('Block length:', code.length, 'lines:', lines.length);
    console.log('First 80 chars:', code.substring(0, 80));
    console.log('Last 80 chars:', code.substring(code.length - 80));
  }
}
if (idx === 0) console.log('No script blocks found');
else console.log('Checked', idx, 'script blocks');
