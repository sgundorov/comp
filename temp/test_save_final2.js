const http = require('http');
const boundary = '----Boundary' + Date.now();
function addF(n,v){return '--'+boundary+'\r\nContent-Disposition: form-data; name="'+n+'"\r\n\r\n'+v+'\r\n';}
let b='';
b+=addF('mode','edit');b+=addF('id','129');b+=addF('number','102');b+=addF('date','2026-06-21');
b+=addF('time','12:00');b+=addF('client_id','3062');b+=addF('state','Черновик');
b+=addF('store_id','5');b+=addF('payment_type','Наличные');b+=addF('discount','5');
b+=addF('sum_discount','0');b+=addF('sum','0');b+=addF('sum_nds','0');
b+=addF('sum_plat','0');b+=addF('date_plat','');b+=addF('sotr_id','2');
b+=addF('pos','0');b+=addF('note','test save');b+=addF('cli_name','');
b+=addF('store_name','');b+=addF('sotr_name','');b+='--'+boundary+'--\r\n';

const req=http.request({hostname:'localhost',path:'/comp/invo_form.php',method:'POST',
  headers:{'Content-Type':'multipart/form-data; boundary='+boundary,'Content-Length':Buffer.byteLength(b),'X-Requested-With':'XMLHttpRequest'}
},res=>{let d='';res.on('data',c=>d+=c);res.on('end',()=>{
  try{const j=JSON.parse(d);console.log('ok:',j.ok,'mode:',j.mode,'id:',j.id);if(j.error)console.log('ERROR:',j.error);}
  catch(e){console.log('Not JSON:',d.substring(0,500));}
});});
req.write(b);req.end();
