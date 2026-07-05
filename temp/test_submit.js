const http = require('http');
http.get('http://localhost/comp/invo_form.php?mode=edit&id=1&ajax=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const j = JSON.parse(d);
    console.log('ok:', j.ok);
    const html = j.html;
    
    // Check form action
    const actionMatch = html.match(/action="([^"]*)"/);
    console.log('form action:', actionMatch ? actionMatch[1] : 'NONE');
    
    // Check hidden fields
    const modeMatch = html.match(/name="mode"\s+value="([^"]*)"/);
    const idMatch = html.match(/name="id"\s+value="([^"]*)"/);
    console.log('mode:', modeMatch ? modeMatch[1] : 'NONE');
    console.log('id:', idMatch ? idMatch[1] : 'NONE');
    
    // Check lookup elements
    const lookups = html.match(/data-lookup="([^"]*)"/g);
    console.log('lookup elements:', lookups);
    
    // Check lookup data-countries
    const dc = html.match(/data-countries='([^']*)'/);
    if (dc) {
      const data = JSON.parse(dc[1]);
      console.log('client data count:', data.length);
    }
    
    // Check save button
    console.log('Has submit btn:', html.indexOf('type="submit"') > -1);
    console.log('Has data-form-modal:', html.indexOf('data-form-modal') > -1);
    
    // Check if hidden client_id has value
    const cidMatch = html.match(/name="client_id"[^>]*value="([^"]*)"/);
    console.log('client_id value:', cidMatch ? cidMatch[1] : 'NONE');
    const sidMatch = html.match(/name="store_id"[^>]*value="([^"]*)"/);
    console.log('store_id value:', sidMatch ? sidMatch[1] : 'NONE');
    const soMatch = html.match(/name="sotr_id"[^>]*value="([^"]*)"/);
    console.log('sotr_id value:', soMatch ? soMatch[1] : 'NONE');
  });
});
