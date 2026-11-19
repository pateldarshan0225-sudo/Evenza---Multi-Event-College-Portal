const fs = require('node:fs');
(async () => {
    const tabs = await (await fetch('http://127.0.0.1:9228/json')).json();
    const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
    await new Promise(r => ws.addEventListener('open', r, {once:true}));
    let id = 0; const pending = new Map();
    ws.addEventListener('message', e => { const m = JSON.parse(e.data); if (pending.has(m.id)) { pending.get(m.id)(m.result); pending.delete(m.id); } });
    const call = (method, params = {}) => new Promise(r => { const n = ++id; pending.set(n, r); ws.send(JSON.stringify({id:n,method,params})); });
    const evaluate = async expression => (await call('Runtime.evaluate', {expression, returnByValue:true}))?.result?.value;
    const pause = ms => new Promise(r => setTimeout(r, ms));
    await call('Page.enable');
    const checks = [];
    for (const width of [320,375,768,1024,1440]) {
        await call('Emulation.setDeviceMetricsOverride', {width,height:1000,deviceScaleFactor:1,mobile:width<769});
        await call('Page.navigate', {url:'http://localhost/Evenza/Frontend/events.php?evenza_tab_id=audit_tab_public_0123456789abcdef'});
        for (let i=0;i<60;i++) { await pause(150); if(await evaluate(`typeof jQuery!=='undefined'&&!!jQuery('#eventFormatFilter').data('select2')`)) break; }
        for (const field of ['eventCategoryFilter','eventFormatFilter']) {
            await evaluate(`jQuery('#${field}').select2('open')`);
            await pause(100);
            const layout = await evaluate(`(()=>{const r=document.querySelector('.select2-dropdown').getBoundingClientRect();return {scroll:document.documentElement.scrollWidth,left:r.left,right:r.right,styled:!!document.querySelector('.university-option-icon')};})()`);
            checks.push({width,field,pass:layout.scroll<=width&&layout.left>=0&&layout.right<=width&&layout.styled});
            if (field==='eventFormatFilter') {
                checks.push({width,name:'All format options remain readable',pass:await evaluate(`(()=>{const items=[...document.querySelectorAll('.select2-results__option--selectable')];return items.length===3&&items.every(e=>e.getBoundingClientRect().height<=64)&&[...document.querySelectorAll('.university-option-text')].every(e=>e.scrollWidth<=e.clientWidth);})()`)});
            }
            if (width===1440&&field==='eventFormatFilter') {
                const shot=await call('Page.captureScreenshot',{format:'png'});
                fs.writeFileSync('tools/event-filters-desktop.png',Buffer.from(shot.data,'base64'));
            }
            await evaluate(`jQuery('#${field}').select2('close')`);
        }
    }
    await evaluate(`jQuery('#eventCategoryFilter').select2('open');(()=>{const s=document.querySelector('.select2-search__field');s.value='zzzz-no-category';s.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    await pause(200);
    checks.push({name:'Category search empty state',pass:await evaluate(`document.querySelector('.select2-results').textContent.includes('No categories found')`)});
    await evaluate(`jQuery('#eventCategoryFilter').select2('close');jQuery('#eventFormatFilter').select2('open')`);
    await call('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowDown',code:'ArrowDown',windowsVirtualKeyCode:40});
    await call('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await call('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await pause(700);
    checks.push({name:'Keyboard format selection updates live results',pass:await evaluate(`new URLSearchParams(location.search).get('format')==='solo'&&document.getElementById('eventFormatFilter').value==='solo'`)});
    console.log(JSON.stringify(checks,null,2));
    fs.writeFileSync('tools/event-filter-audit-results.json',JSON.stringify(checks,null,2));
    ws.close();
})().catch(e=>{console.error(e);process.exitCode=1;});
