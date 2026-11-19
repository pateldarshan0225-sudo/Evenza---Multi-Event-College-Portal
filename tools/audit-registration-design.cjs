// Registration presentation only. No successful form is submitted.
const fs=require('node:fs');
(async()=>{
    const target=await(await fetch('http://127.0.0.1:9228/json/new?about:blank',{method:'PUT'})).json();const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let id=0;const pending=new Map(),checks=[],errors=[];
    ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(pending.has(m.id)){pending.get(m.id)(m.result);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);});
    const call=(method,params={})=>new Promise(r=>{const n=++id;pending.set(n,r);ws.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const result=await call('Runtime.evaluate',{expression,returnByValue:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);return result.result.value;};
    await call('Page.enable');await call('Runtime.enable');await call('Network.enable');await call('Network.setCacheDisabled',{cacheDisabled:true});
    for(const page of ['register','register_college','register_university'])for(const width of [320,375,768,1024,1440]){
        await call('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<769});await call('Page.navigate',{url:'http://localhost/Evenza/Frontend/'+page+'.php?evenza_tab_id=audit_registration_0123456789abcdef'});
        for(let i=0;i<60;i++){await new Promise(r=>setTimeout(r,100));if(await evaluate(`document.readyState==='complete'&&location.pathname.endsWith('/${page}.php')&&!!document.querySelector('.registration-role-tabs')`))break;}
        checks.push({page,width,name:'Responsive registration layout',pass:await evaluate(`innerWidth<=${width}+1&&document.documentElement.scrollWidth<=${width}+1`)});
        checks.push({page,width,name:'Three role links and current account type',pass:await evaluate(`document.querySelectorAll('.registration-role-tabs a').length===3&&document.querySelector('.registration-role-tabs [aria-current=page]').getAttribute('href').includes('${page}.php')`)});
        await evaluate(`toggleRegisterPassword()`);
        checks.push({page,width,name:'Password visibility control preserved',pass:await evaluate(`document.getElementById('registerPassword').type==='text'`)});await evaluate(`toggleRegisterPassword()`);
        checks.push({page,width,name:'Invalid registration blocked',pass:await evaluate(`!document.querySelector('.register-card-bespoke form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))`)});
        await evaluate(`document.getElementById('signUpDropdown').click()`);await new Promise(r=>setTimeout(r,100));
        checks.push({page,width,name:'Signup role menu fits viewport',pass:await evaluate(`(()=>{const menu=document.querySelector('[aria-labelledby=signUpDropdown]'),r=menu.getBoundingClientRect();return menu.classList.contains('show')&&r.left>=0&&r.right<=innerWidth&&menu.querySelectorAll('.signup-menu-card').length===3;})()`)});
        if(page==='register'&&width===1440){const shot=await call('Page.captureScreenshot',{format:'png'});fs.writeFileSync('tools/signup-menu-redesign.png',Buffer.from(shot.data,'base64'));}
        await evaluate(`document.getElementById('signUpDropdown').click()`);
        checks.push({page,width,name:'Corrected fields pass client validation without submission',pass:await evaluate(`(()=>{
            const form=document.querySelector('.register-card-bespoke form');
            const values={name:'Interface Example',short_name:'IEX',enrollment_no:'EX-001',email:'interface@example.invalid',phone:'9876543210',gender:'male',semester:'1',password:'Example123',slug:'interface-example'};
            Object.entries(values).forEach(([name,value])=>{const field=form.querySelector('[name='+name+']');if(field){field.value=value;field.dispatchEvent(new Event('input',{bubbles:true}));}});
            for(const kind of ['university','college']){const field=document.getElementById(kind+'Select');if(field){field.value='1';document.getElementById(kind+'Input')?.classList.remove('is-invalid');}}
            let blocked;form.addEventListener('submit',event=>{blocked=event.defaultPrevented;event.preventDefault();},{once:true});
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));return blocked===false;
        })()`)});
        if(width===1440||width===375){await call('Page.navigate',{url:'http://localhost/Evenza/Frontend/'+page+'.php?evenza_tab_id=audit_registration_0123456789abcdef'});await new Promise(r=>setTimeout(r,500));const shot=await call('Page.captureScreenshot',{format:'png'});fs.writeFileSync('tools/'+page+'-redesign-'+width+'.png',Buffer.from(shot.data,'base64'));}
    }
    fs.writeFileSync('tools/registration-design-audit-results.json',JSON.stringify({checks,errors},null,2));console.log(JSON.stringify({checks:checks.length,failures:checks.filter(row=>!row.pass),errors},null,2));await call('Target.closeTarget',{targetId:target.id});ws.close();if(checks.some(row=>!row.pass)||errors.length)process.exitCode=1;
})().catch(error=>{console.error(error);process.exitCode=1;});
