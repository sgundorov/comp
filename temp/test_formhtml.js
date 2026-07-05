const http = require('http');
http.get('http://localhost/comp/invo_form.php?mode=new&ajax=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const j = JSON.parse(d);
    const html = j.html;
    
    const formOpen = html.indexOf('<form');
    const formClose = html.indexOf('</form>');
    const formActions = html.indexOf('form-actions');
    const submitBtn = html.indexOf('type="submit"');
    
    console.log('form open:', formOpen);
    console.log('form-actions:', formActions);
    console.log('submit btn:', submitBtn);
    console.log('form close:', formClose);
    console.log('form-actions inside form:', formActions > formOpen && formActions < formClose);
    console.log('submit inside form:', submitBtn > formOpen && submitBtn < formClose);
    
    // Show the form-actions section
    if (formActions > -1) {
      console.log('\nform-actions HTML:');
      console.log(html.substring(formActions - 50, formActions + 500));
    }
  });
});
