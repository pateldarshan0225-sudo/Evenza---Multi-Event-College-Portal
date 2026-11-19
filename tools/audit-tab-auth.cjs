// Real HTTP navigation in an isolated browser context with disposable PHP sessions.
const fs=require('node:fs'),{spawnSync}=require('node:child_process');
const php='C:/wamp64/bin/php/php8.3.28/php.exe';
function connect(url){return new Promise(resolve=>{const ws=new WebSocket(url),pending=new Map();let id=0;ws.addEventListener('open',()=>resolve({ws,call:(method,params={})=>new Promise((resolve,reject)=>{const n=++id,timer=setTimeout(()=>reject(Error(method+' timeout')),20000);pending.set(n,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id:n,method,params}));})}));ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}});});}
(async()=>{
    const fixtureResult=spawnSync(php,['-d','xdebug.mode=off','tools/auth-test-fixture.php'],{encoding:'utf8'});
    if(fixtureResult.status!==0)throw Error(fixtureResult.stderr);
    const fixture=JSON.parse(fixtureResult.stdout),checks=[],pages={};let browser,context;
    const base='http://localhost/Evenza/',routes={admin:'Admin/Dashboard.php',organizer:'Organizer/Dashboard.php',student:'Student/Dashboard.php'};
    try{
        const version=await(await fetch('http://127.0.0.1:9228/json/version')).json();browser=await connect(version.webSocketDebuggerUrl);
        context=(await browser.call('Target.createBrowserContext')).browserContextId;
        for(const role of ['organizer','admin','student']){
            const target=await browser.call('Target.createTarget',{url:'about:blank',browserContextId:context});
            const list=await(await fetch('http://127.0.0.1:9228/json')).json();const page=await connect(list.find(t=>t.id===target.targetId).webSocketDebuggerUrl);pages[role]=page;
            await page.call('Page.enable');await page.call('Network.enable');
            await page.call('Network.setCookie',{name:'PHPSESSID',value:fixture.session,url:base});
        }
        const evaluate=async(page,expression)=>{const r=await page.call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
        const wait=async(page,pathname)=>{for(let i=0;i<100;i++){await new Promise(r=>setTimeout(r,100));if(await evaluate(page,`document.readyState==='complete'&&location.pathname.endsWith(${JSON.stringify(pathname)})&&typeof getEvenzaTabId==='function'`))return;}const state=await evaluate(page,`JSON.stringify({path:location.pathname,title:document.title,helper:typeof getEvenzaTabId,phpError:/Warning:|Fatal error/.test(document.body.innerText),scripts:[...document.scripts].map(s=>new URL(s.src||location.href).pathname)})`);throw Error('Navigation failed: '+pathname+' '+state);};
        const navigate=async(role,url,path=routes[role])=>{await pages[role].call('Page.navigate',{url});await wait(pages[role],path);};
        for(const role of Object.keys(pages))await navigate(role,base+routes[role]+'?evenza_tab_id='+fixture.tabs[role]);
        for(let round=0;round<2;round++)for(const role of ['admin','student','organizer']){
            const page=pages[role],other=role==='admin'?'student':'admin';
            await page.call('Network.setCookie',{name:'evenza_tab_id',value:fixture.tabs[other],url:base});
            await page.call('Page.bringToFront');await page.call('Page.reload',{ignoreCache:true});await wait(page,routes[role]);
            checks.push({name:role+' refresh ignores another tab cookie, round '+round,pass:await evaluate(page,`getEvenzaTabId()===${JSON.stringify(fixture.tabs[role])}&&new URL(location.href).searchParams.get('evenza_tab_id')===${JSON.stringify(fixture.tabs[role])}`)});
        }
        await navigate('organizer',base+routes.organizer);
        checks.push({name:'Old organizer URL recovers its sessionStorage context',pass:await evaluate(pages.organizer,`location.pathname.endsWith('Organizer/Dashboard.php')&&getEvenzaTabId()===${JSON.stringify(fixture.tabs.organizer)}`)});
        await navigate('organizer',base+routes.admin+'?evenza_tab_id='+fixture.tabs.organizer);
        checks.push({name:'Organizer cannot become admin through a role mismatch redirect',pass:await evaluate(pages.organizer,`location.pathname.endsWith('Organizer/Dashboard.php')`)});
        checks.push({name:'Links and forms carry the organizer context',pass:await evaluate(pages.organizer,`[...document.querySelectorAll('a[href]')].filter(a=>a.getAttribute('href').includes('.php')).every(a=>new URL(a.href).searchParams.get('evenza_tab_id')===getEvenzaTabId())`)});
        await evaluate(pages.organizer,`const form=document.createElement('form');form.action='../Frontend/events.php';form.method='get';document.body.appendChild(form);form.submit()`);
        await wait(pages.organizer,'Frontend/events.php');
        checks.push({name:'Programmatic GET form submission preserves account',pass:await evaluate(pages.organizer,`new URL(location.href).searchParams.get('evenza_tab_id')===${JSON.stringify(fixture.tabs.organizer)}&&document.querySelector('.evenza-auth-actions').textContent.includes('Audit Organizer')`)});
        const response=await fetch(base+routes.organizer,{redirect:'manual',headers:{Cookie:'PHPSESSID='+fixture.session+'; evenza_tab_id='+fixture.tabs.admin}});
        checks.push({name:'Cookie alone cannot choose an account',pass:response.status===302&&response.headers.get('location').includes('login.php')});
        await navigate('organizer',base+'Organizer/Logout.php?evenza_tab_id='+fixture.tabs.organizer,'Frontend/login.php');
        for(const role of ['admin','student']){await pages[role].call('Page.reload',{ignoreCache:true});await wait(pages[role],routes[role]);checks.push({name:'Organizer logout leaves '+role+' signed in',pass:true});}
        await navigate('organizer',base+routes.organizer+'?evenza_tab_id='+fixture.tabs.organizer,'Frontend/login.php');
        checks.push({name:'Logged-out organizer stays on login instead of admin dashboard',pass:true});
        fs.writeFileSync('tools/tab-auth-audit-results.json',JSON.stringify(checks,null,2));console.log(JSON.stringify({checks:checks.length,failures:checks.filter(c=>!c.pass)},null,2));
        if(checks.some(c=>!c.pass))process.exitCode=1;
    }finally{
        if(context)await browser.call('Target.disposeBrowserContext',{browserContextId:context});
        Object.values(pages).forEach(page=>page.ws.close());browser?.ws.close();
        spawnSync(php,['-d','xdebug.mode=off','tools/auth-test-fixture.php','destroy',fixture.session]);
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
