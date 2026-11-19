// Read-only organizer rendering and browser interactions. Never submits an update.
const fs=require('node:fs'),{spawnSync}=require('node:child_process');
(async()=>{
    const target=await(await fetch('http://127.0.0.1:9228/json/new?about:blank',{method:'PUT'})).json();
    const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
    let id=0,html;const pending=new Map(),checks=[],errors=[];
    ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);});
    const call=(method,params={})=>new Promise((resolve,reject)=>{const n=++id,timer=setTimeout(()=>reject(Error(method+' timeout')),15000);pending.set(n,m=>{clearTimeout(timer);m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result);});ws.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
    ws.addEventListener('message',async event=>{const m=JSON.parse(event.data);if(m.method==='Fetch.requestPaused')await call('Fetch.fulfillRequest',{requestId:m.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(html).toString('base64')});});
    await call('Page.enable');await call('Runtime.enable');
    for(const name of ['Dashboard','Registrations','Profile','Events','Students']){
        const page='Organizer/'+name+'.php';
        const rendered=spawnSync('C:/wamp64/bin/php/php8.3.28/php.exe',['-d','xdebug.mode=off','tools/render-audit.php',page],{encoding:'utf8',maxBuffer:32*1024*1024});
        if(rendered.status!==0||/Warning:|Fatal error|Parse error/.test(rendered.stdout+rendered.stderr))throw Error('Render failed: '+page);
        html=rendered.stdout;await call('Fetch.enable',{patterns:[{urlPattern:'*'+page+'*',resourceType:'Document'}]});
        for(const width of [375,1440]){
            await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<769});
            await call('Page.navigate',{url:'http://localhost/Evenza/'+page+'?evenza_tab_id=audit_tab_0123456789abcdef'});
            for(let i=0;i<60;i++){await new Promise(r=>setTimeout(r,100));if(await evaluate(`document.readyState==='complete'&&location.pathname.endsWith(${JSON.stringify(page)})`))break;}
            checks.push({page,width,name:'Page stays within viewport',pass:await evaluate(`innerWidth<=${width}+1&&document.documentElement.scrollWidth<=${width}+1`)});
            if(name==='Registrations'){
                checks.push({page,width,name:'Every registration stays in main table; modals are outside tables',pass:await evaluate(`document.querySelectorAll('.reg-table-row').length===document.querySelectorAll('#regLogTable > tbody > .reg-table-row').length&&document.querySelectorAll('.reg-table-row').length===document.querySelectorAll('[id^=rosterModal]').length&&document.querySelectorAll('table .modal').length===0`)});
                const ids=await evaluate(`[...document.querySelectorAll('[id^=rosterModal]')].map(m=>m.id)`);
                for(const id of ids){await evaluate(`document.querySelector('[data-bs-target="#${id}"]').click()`);await new Promise(r=>setTimeout(r,350));checks.push({page,width,name:'Roster '+id+' opens as dialog',pass:await evaluate(`(()=>{const modal=document.getElementById('${id}'),r=modal.querySelector('.modal-content').getBoundingClientRect();return modal.classList.contains('show')&&r.width>0&&r.left>=0&&r.right<=innerWidth;})()`)});await evaluate(`document.getElementById('${id}').querySelector('[data-bs-dismiss=modal]').click()`);await new Promise(r=>setTimeout(r,350));}
                checks.push({page,width,name:'Registration status options use canonical values',pass:await evaluate(`[...document.querySelectorAll('#regLogTable select[name=status] option')].every(option=>['approved','pending','cancelled'].includes(option.value))`)});
                await evaluate(`document.getElementById('regSearchInput').value='__no_matching_record__';document.getElementById('regSearchInput').dispatchEvent(new Event('input'))`);
                checks.push({page,width,name:'Registration filter empty state',pass:await evaluate(`!document.querySelector('.reg-table-row:not(.d-none)')&&!document.querySelector('.organizer-filter-empty').classList.contains('d-none')`)});
            }
            if(name==='Profile'){
                checks.push({page,width,name:'Unchanged profile button disabled',pass:await evaluate(`document.querySelector('#profileForm button[type=submit]').disabled`)});
                checks.push({page,width,name:'Editing and reverting profile toggles save',pass:await evaluate(`(()=>{const input=document.querySelector('#profileForm input[name=phone]'),original=input.value,button=document.querySelector('#profileForm button[type=submit]');input.value='9876543210';input.dispatchEvent(new Event('input',{bubbles:true}));const enabled=!button.disabled;input.value=original;input.dispatchEvent(new Event('input',{bubbles:true}));return enabled&&button.disabled;})()`)});
                await evaluate(`document.getElementById('pills-security-tab').click()`);
                checks.push({page,width,name:'Password toggle works',pass:await evaluate(`document.querySelector('[data-target=new_password]').click();document.getElementById('new_password').type==='text'`)});
                checks.push({page,width,name:'Password mismatch clears when new password is corrected',pass:await evaluate(`(()=>{const n=document.getElementById('new_password'),c=document.getElementById('confirm_password');n.value='Example123';c.value='Different123';c.dispatchEvent(new Event('input'));const mismatch=!c.validity.valid;n.value=c.value;n.dispatchEvent(new Event('input'));return mismatch&&c.validity.valid;})()`)});
            }
            if(name==='Events'){
                await evaluate(`document.getElementById('eventSearchInput').value='__no_matching_event__';document.getElementById('eventSearchInput').dispatchEvent(new Event('input'))`);
                checks.push({page,width,name:'Events grid and table show matching empty states',pass:await evaluate(`document.querySelectorAll('.organizer-filter-empty:not(.d-none)').length===2&&!document.querySelector('.event-item-card:not(.d-none)')&&!document.querySelector('.event-table-row:not(.d-none)')`)});
                await evaluate(`document.getElementById('viewTableBtn').click()`);
                checks.push({page,width,name:'Table view switch works',pass:await evaluate(`!document.getElementById('eventsTableContainer').classList.contains('d-none')&&document.getElementById('eventsGridContainer').classList.contains('d-none')`)});
            }
            if(name==='Students'){
                await evaluate(`document.getElementById('studentSearchInput').value='__no_matching_student__';document.getElementById('studentSearchInput').dispatchEvent(new Event('input'))`);
                checks.push({page,width,name:'Student filter empty state',pass:await evaluate(`!document.querySelector('.student-table-row:not(.d-none)')&&!document.querySelector('.organizer-filter-empty').classList.contains('d-none')`)});
            }
        }
        await call('Fetch.disable');
    }
    fs.writeFileSync('tools/organizer-audit-results.json',JSON.stringify({checks,errors},null,2));console.log(JSON.stringify({checks:checks.length,failures:checks.filter(row=>!row.pass),errors},null,2));
    await call('Target.closeTarget',{targetId:target.id});ws.close();if(checks.some(row=>!row.pass)||errors.length)process.exitCode=1;
})().catch(error=>{console.error(error);process.exitCode=1;});
