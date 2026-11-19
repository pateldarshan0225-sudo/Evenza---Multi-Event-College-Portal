// Native picker styling and interaction audit; no form submissions or database writes.
const fs=require('node:fs'),{spawnSync}=require('node:child_process');
(async()=>{
    const tab=await(await fetch('http://127.0.0.1:9228/json/new?about:blank',{method:'PUT'})).json();
    const ws=new WebSocket(tab.webSocketDebuggerUrl);await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
    let id=0,html;const pending=new Map(),checks=[];
    ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(pending.has(m.id)){pending.get(m.id)(m.result);pending.delete(m.id);}});
    const call=(method,params={})=>new Promise(resolve=>{const n=++id;pending.set(n,resolve);ws.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async(expression,userGesture=false)=>{const r=await call('Runtime.evaluate',{expression,returnByValue:true,userGesture});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
    ws.addEventListener('message',async event=>{const m=JSON.parse(event.data);if(m.method==='Fetch.requestPaused')await call('Fetch.fulfillRequest',{requestId:m.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(html).toString('base64')});});
    await call('Page.enable');
    for(const [page,selector,modal] of [
        ['Admin/Dashboard.php','#datePresetSelect'],
        ['Organizer/Events.php','#statusFilter'],
        ['Student/Profile.php','select[name="semester"]'],
        ['Frontend/register.php','#gender'],
        ['Organizer/Events.php','#createEventModal select[name="category_id"]',true]
    ]){
        const portal=!page.startsWith('Frontend');
        if(portal){const r=spawnSync('C:/wamp64/bin/php/php8.3.28/php.exe',['-d','xdebug.mode=off','tools/render-audit.php',page],{encoding:'utf8',maxBuffer:32*1024*1024});if(r.status!==0)throw Error('Read-only render failed');html=r.stdout;await call('Fetch.enable',{patterns:[{urlPattern:'*'+page+'*',resourceType:'Document'}]});}
        for(const width of [375,1440]){
            await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<769});await call('Page.navigate',{url:'http://localhost/Evenza/'+page+'?evenza_tab_id=audit_tab_0123456789abcdef'});
            for(let i=0;i<60;i++){await new Promise(resolve=>setTimeout(resolve,100));if(await evaluate('document.readyState === "complete"'))break;}
            if(modal){await evaluate(`document.querySelector('[data-bs-target="#createEventModal"]').click()`);await new Promise(resolve=>setTimeout(resolve,400));}
            await evaluate(`window.testSelect=document.querySelector(${JSON.stringify(selector)});testSelect.scrollIntoView({block:'center'});testSelect.selectedIndex=0;window.selectChangeCount=0;testSelect.addEventListener('change',()=>selectChangeCount++);testSelect.focus();testSelect.showPicker()`,true);
            checks.push({page,width,selector,name:'Styled options open within viewport',pass:await evaluate(`testSelect.matches(':open')&&getComputedStyle(testSelect).appearance==='base-select'&&[...testSelect.options].every(option=>{const r=option.getBoundingClientRect();return r.width>=100&&r.left>=0&&r.right<=innerWidth&&r.height>=38;})`)});
            await call('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowDown',code:'ArrowDown',windowsVirtualKeyCode:40});
            await call('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,text:'\r'});
            await call('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
            checks.push({page,width,selector,name:'Keyboard selection fires one native change',pass:await evaluate(`testSelect.selectedIndex===1&&selectChangeCount===1&&!testSelect.matches(':open')`)});
        }
        if(portal)await call('Fetch.disable');
    }
    fs.writeFileSync('tools/select-options-audit-results.json',JSON.stringify(checks,null,2));console.log(JSON.stringify({checks:checks.length,failures:checks.filter(row=>!row.pass)},null,2));
    await call('Target.closeTarget',{targetId:tab.id});ws.close();if(checks.some(row=>!row.pass))process.exitCode=1;
})().catch(error=>{console.error(error);process.exitCode=1;});
