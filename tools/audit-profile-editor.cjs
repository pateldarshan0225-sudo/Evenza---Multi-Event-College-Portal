// Image selection and preview tests only. Forms are never submitted.
const fs=require('node:fs'),{spawnSync}=require('node:child_process');
(async()=>{
    const target=await(await fetch('http://127.0.0.1:9228/json/new?about:blank',{method:'PUT'})).json();
    const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let id=0,html;const pending=new Map(),checks=[],errors=[];
    ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m.result);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);});
    const call=(method,params={})=>new Promise(r=>{const n=++id;pending.set(n,r);ws.send(JSON.stringify({id:n,method,params}));});
    const evaluate=async expression=>{const result=await call('Runtime.evaluate',{expression,returnByValue:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);return result.result.value;};
    ws.addEventListener('message',async e=>{const m=JSON.parse(e.data);if(m.method==='Fetch.requestPaused')await call('Fetch.fulfillRequest',{requestId:m.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(html).toString('base64')});});
    await call('Page.enable');await call('Runtime.enable');await call('Network.enable');await call('Network.setCacheDisabled',{cacheDisabled:true});await call('DOM.enable');
    for(const role of ['Student','Organizer']){
        const page=role+'/Profile.php';const render=spawnSync('C:/wamp64/bin/php/php8.3.28/php.exe',['-d','xdebug.mode=off','tools/render-audit.php',page],{encoding:'utf8',maxBuffer:32*1024*1024});
        if(render.status!==0||/Warning:|Fatal error|Parse error/.test(render.stdout+render.stderr))throw Error('Read-only render failed');
        html=render.stdout;await call('Fetch.enable',{patterns:[{urlPattern:'*'+page+'*',resourceType:'Document'}]});
        for(const width of [375,1440]){
            await call('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<769});await call('Page.navigate',{url:'http://localhost/Evenza/'+page+'?evenza_tab_id=audit_tab_0123456789abcdef'});
            for(let i=0;i<60;i++){await new Promise(r=>setTimeout(r,100));if(await evaluate(`document.readyState==='complete'&&!!document.getElementById('profile_image')`))break;}
            checks.push({role,width,name:'Editable details and protected admin fields',pass:await evaluate(`['name','email','phone',${role==='Student'?"'enrollment_no','college_id','gender','semester'":"'slug','address','university_id'"}].every(name=>{const field=document.querySelector('#profileForm [name='+name+']');return field&&!field.disabled&&!field.readOnly;})&&!document.querySelector('#profileForm [name=status],#profileForm [name=account_status],#profileForm [name=verification_status]')`)});
            checks.push({role,width,name:'Profile and sidebar images load',pass:await evaluate(`[document.getElementById('profileImagePreview'),document.querySelector('.berun-avatar-pill')].every(image=>image.complete&&image.naturalWidth>0)`)});
            const root=(await call('DOM.getDocument')).root.nodeId;const input=(await call('DOM.querySelector',{nodeId:root,selector:'#profile_image'})).nodeId;
            await call('DOM.setFileInputFiles',{nodeId:input,files:['C:/wamp64/www/Evenza/assets/images/brand/campus-community.jpg']});await new Promise(r=>setTimeout(r,150));
            checks.push({role,width,name:'Valid image preview and upload form',pass:await evaluate(`document.getElementById('profileImagePreview').src.startsWith('blob:')&&document.getElementById('profile_image').validity.valid&&document.getElementById('profileForm').enctype==='multipart/form-data'&&!document.querySelector('#profileForm button[type=submit]').disabled`)});
            await call('DOM.setFileInputFiles',{nodeId:input,files:['C:/wamp64/www/Evenza/assets/images/brand/avatar-placeholder.svg']});
            checks.push({role,width,name:'Unsupported image is blocked',pass:await evaluate(`!document.getElementById('profile_image').validity.valid&&document.getElementById('profileImageError').textContent.length>0`)});
            await evaluate(`document.getElementById('profileForm').reset()`);await new Promise(r=>setTimeout(r,100));
            checks.push({role,width,name:'Reset restores image and file validity',pass:await evaluate(`!document.getElementById('profileImagePreview').src.startsWith('blob:')&&document.getElementById('profile_image').files.length===0&&document.getElementById('profile_image').validity.valid`)});
            checks.push({role,width,name:'No page overflow',pass:await evaluate(`innerWidth<=${width}+1&&document.documentElement.scrollWidth<=${width}+1`)});
            if(width===1440){const image=await call('Page.captureScreenshot',{format:'png'});fs.writeFileSync('tools/'+role.toLowerCase()+'-profile-editor.png',Buffer.from(image.data,'base64'));}
        }
        await call('Fetch.disable');
    }
    fs.writeFileSync('tools/profile-editor-audit-results.json',JSON.stringify({checks,errors},null,2));console.log(JSON.stringify({checks:checks.length,failures:checks.filter(row=>!row.pass),errors},null,2));
    await call('Target.closeTarget',{targetId:target.id});ws.close();if(checks.some(row=>!row.pass)||errors.length)process.exitCode=1;
})().catch(error=>{console.error(error);process.exitCode=1;});
