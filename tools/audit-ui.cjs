// Read-only browser audit; start headless Chrome with CDP on port 9228.
const fs = require('node:fs');
const {spawnSync} = require('node:child_process');
async function main() {
  const protectedMode = process.argv.includes('--protected');
  const pages = protectedMode ? ['Admin/Dashboard.php','Admin/Colleges.php','Admin/all_events.php','Admin/allregistrations.php','Admin/allstudents.php','Admin/allteams.php','Admin/alluniversity.php','Admin/change_password.php','Admin/event_cat.php','Admin/teamdetails.php','Student/Dashboard.php','Student/MyEvents.php','Student/MyTeams.php','Student/Profile.php','Organizer/Dashboard.php','Organizer/Events.php','Organizer/Registrations.php','Organizer/Students.php','Organizer/Profile.php'] : ['index','about','colleges','events','categories','contact','login','register','register_college','register_university'].map(p=>'Frontend/'+p+'.php');
  const only = process.argv.find(a=>a.startsWith('--page='))?.slice(7);
  if(only) pages.splice(0,pages.length,only);
  const rendered = new Map();
  if(protectedMode) for(const page of pages) {
    const r=spawnSync('C:/wamp64/bin/php/php8.3.28/php.exe',['-d','xdebug.mode=off','tools/render-audit.php',page],{encoding:'utf8',maxBuffer:32*1024*1024});
    if(r.status!==0||/Fatal error|Warning:|Parse error/.test(r.stdout+r.stderr)) console.log('BACKEND ERROR '+page+' '+r.stderr+' '+r.stdout.slice(-800));
    rendered.set('http://localhost/Evenza/'+page+'?evenza_tab_id=audit_tab_0123456789abcdef',r.stdout);
  }
  const tabs = await (await fetch('http://127.0.0.1:9228/json')).json();
  const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise(r => ws.addEventListener('open', r, {once:true}));
  let id=0; const pending=new Map();
  ws.addEventListener('message', e=>{const m=JSON.parse(e.data); if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}});
  const call=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;const timer=setTimeout(()=>{pending.delete(n);reject(Error(method+' timeout'));},20000);pending.set(n,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id:n,method,params}));});
  await call('Page.enable');
  if(protectedMode) {
    ws.addEventListener('message', async e=>{const m=JSON.parse(e.data);if(m.method==='Fetch.requestPaused') {const p=m.params;const html=rendered.get(p.request.url);if(html!==undefined) await call('Fetch.fulfillRequest',{requestId:p.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(html).toString('base64')});else await call('Fetch.continueRequest',{requestId:p.requestId});}});
    await call('Fetch.enable',{patterns:[{urlPattern:'http://localhost/Evenza/*',resourceType:'Document',requestStage:'Request'}]});
  }
  const results=[];
  const widthOption = process.argv.find(a => a.startsWith('--widths='));
  const widths = widthOption ? widthOption.slice(9).split(',').map(Number) : (only ? [320] : [320,375,768,1024,1440]);
  for(const width of widths) {
    await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<769});
    for(const page of pages) {
      await call('Page.navigate',{url:'http://localhost/Evenza/'+page+(page.includes('?')?'&':'?')+'evenza_tab_id='+(protectedMode?'audit_tab_0123456789abcdef':'audit_tab_public_0123456789abcdef')});
      for(let i=0;i<50;i++){await new Promise(r=>setTimeout(r,100));const s=await call('Runtime.evaluate',{expression:'document.readyState'});if(s.result.value==='complete')break;}
      const r=await call('Runtime.evaluate',{returnByValue:true,expression:`JSON.stringify({url:location.pathname,width:innerWidth,scroll:document.documentElement.scrollWidth,title:document.title,errors:/Fatal error|Warning:|Parse error|Database Connection Failed/.test(document.body.innerText),overflow:[...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&(r.right>innerWidth+2||r.left < -2)&&getComputedStyle(e).position!=='absolute'&&!e.closest('.table-responsive,.carousel,.dropdown-menu,.berun-nav-group')}).slice(0,8).map(e=>e.tagName+'.'+e.className)})`});
      if (!r.result.value) { console.log(JSON.stringify(r)); throw Error('Browser evaluation failed'); }
      const row=JSON.parse(r.result.value);row.requestedWidth=width;results.push(row);console.log(JSON.stringify(row));
    }
  }
  fs.writeFileSync(only ? 'tools/page-audit-results.json' : (protectedMode?'tools/portal-audit-results.json':'tools/ui-audit-results.json'),JSON.stringify(results,null,2));
  if(protectedMode) await call('Fetch.disable');
  ws.close();
}
main().catch(e=>{console.error(e);process.exitCode=1;});
