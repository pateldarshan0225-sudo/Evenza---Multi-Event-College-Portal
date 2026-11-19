const {spawnSync}=require('node:child_process');
(async()=>{
 const target=await(await fetch('http://127.0.0.1:9228/json/new?about:blank',{method:'PUT'})).json();const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let id=0;const pending=new Map();ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m.result);pending.delete(m.id)}});const call=(method,params={})=>new Promise(r=>{pending.set(++id,r);ws.send(JSON.stringify({id,method,params}))});await call('Page.enable');
 const checks=[];
 for(const page of ['Organizer/Events.php','Admin/all_events.php']){
 const html=spawnSync('C:/wamp64/bin/php/php8.3.28/php.exe',['-d','xdebug.mode=off','tools/render-audit.php',page],{encoding:'utf8',maxBuffer:20*1024*1024}).stdout;
 await call('Fetch.enable',{patterns:[{urlPattern:'*'+page+'*',resourceType:'Document'}]});
 const listener=async e=>{const m=JSON.parse(e.data);if(m.method==='Fetch.requestPaused')await call('Fetch.fulfillRequest',{requestId:m.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(html).toString('base64')})};ws.addEventListener('message',listener);
 await call('Page.navigate',{url:'http://localhost/Evenza/'+page+'?evenza_tab_id=audit_tab_0123456789abcdef'});await new Promise(r=>setTimeout(r,650));
 const result=await call('Runtime.evaluate',{returnByValue:true,expression:`(()=>{const start=document.querySelector('[name=start_time]'),end=start.form.querySelector('[name=end_time]');const set=(field,time,period)=>{const g=field.previousElementSibling;g.querySelector('input').value=time;g.querySelector('select').value=period;g.querySelector('select').dispatchEvent(new Event('change'));};set(start,'10:00','AM');set(end,'01:30','PM');const a=start.value==='10:00'&&end.value==='13:30';set(start,'12:00','AM');set(end,'12:00','PM');const b=start.value==='00:00'&&end.value==='12:00';set(end,'13:30','PM');const c=!end.previousElementSibling.querySelector('input').checkValidity();return {afternoon:a,noonMidnight:b,invalidTime:c}})()`});checks.push({page,...result.result.value});await call('Fetch.disable');ws.removeEventListener('message',listener);
 }
 console.log(JSON.stringify(checks,null,2));await call('Target.closeTarget',{targetId:target.id});ws.close();if(checks.some(c=>!c.afternoon||!c.noonMidnight||!c.invalidTime))process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1});
