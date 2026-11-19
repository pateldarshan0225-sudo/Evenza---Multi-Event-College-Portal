// Read-only filter interaction and layout checks using a dedicated Chrome CDP session.
const fs = require('node:fs');
(async function () {
    const tabs = await (await fetch('http://127.0.0.1:9228/json')).json();
    const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => ws.addEventListener('open', resolve, {once: true}));
    let id = 0;
    const pending = new Map();
    const errors = [];
    ws.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (pending.has(message.id)) { pending.get(message.id)(message.result); pending.delete(message.id); }
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails);
    });
    const call = (method, params = {}) => new Promise(resolve => {
        const n = ++id;
        pending.set(n, resolve);
        ws.send(JSON.stringify({id: n, method, params}));
    });
    const evaluate = async expression => (await call('Runtime.evaluate', {expression, returnByValue: true}))?.result?.value;
    const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
    await call('Page.enable'); await call('Runtime.enable');
    const checks = [];
    for (const width of [320, 375, 768, 1024, 1440]) {
        await call('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: width < 769});
        await call('Page.navigate', {url: 'http://localhost/Evenza/Frontend/colleges.php?evenza_tab_id=audit_tab_public_0123456789abcdef'});
        for (let i = 0; i < 60; i++) {
            await pause(200);
            if (await evaluate(`typeof jQuery!=='undefined'&&!!jQuery('#collegeUniversityFilter').data('select2')`)) break;
        }
        await evaluate(`jQuery('#collegeUniversityFilter').select2('open')`);
        await pause(150);
        checks.push({width, layout: await evaluate(`(()=>{const r=document.querySelector('.select2-dropdown').getBoundingClientRect();return {pageWidth:document.documentElement.scrollWidth,left:r.left,right:r.right,styled:!!document.querySelector('.university-option-icon'),focused:document.activeElement.classList.contains('select2-search__field')};})()`)});
        if (width === 1440) {
            const screenshot = await call('Page.captureScreenshot', {format: 'png'});
            fs.writeFileSync('tools/college-filter-desktop.png', Buffer.from(screenshot.data, 'base64'));
        }
        if (width === 375) {
            const screenshot = await call('Page.captureScreenshot', {format: 'png'});
            fs.writeFileSync('tools/college-filter-mobile.png', Buffer.from(screenshot.data, 'base64'));
        }
    }
    await evaluate(`(()=>{const e=document.querySelector('.select2-search__field');e.value='Cambridge';e.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    await pause(200);
    checks.push({search: await evaluate(`(()=>{const e=[...document.querySelectorAll('.select2-results__option--selectable')];return e.length===1&&e[0].textContent.includes('Cambridge');})()`)});
    await call('Input.dispatchKeyEvent', {type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await call('Input.dispatchKeyEvent', {type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await pause(700);
    checks.push({keyboardSelectionAndLiveFilter: await evaluate(`document.getElementById('collegeUniversityFilter').value==='Cambridge University'&&new URLSearchParams(location.search).get('university')==='Cambridge University'&&document.getElementById('ajaxCollegesContainer').innerText.includes('Cambridge')`)});
    await evaluate(`jQuery('#collegeUniversityFilter').select2('open')`);
    await pause(100);
    await call('Input.dispatchKeyEvent', {type:'keyDown',key:'z',code:'KeyZ',windowsVirtualKeyCode:90});
    await evaluate(`(()=>{const e=document.querySelector('.university-filter-field .select2-search__field');e.value='zzzz-no-university';e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new KeyboardEvent('keyup',{key:'y',bubbles:true}));})()`);
    await pause(400);
    checks.push({emptyState: await evaluate(`document.querySelector('.university-filter-field .select2-results').textContent.includes('No universities found')`), emptyText: await evaluate(`document.querySelector('.university-filter-field .select2-results').textContent`), searchValue: await evaluate(`document.querySelector('.university-filter-field .select2-search__field').value`)});
    console.log(JSON.stringify({checks, errors}, null, 2));
    fs.writeFileSync('tools/college-filter-audit-results.json', JSON.stringify({checks, errors}, null, 2));
    await call('Browser.close');
    ws.close();
})().catch(error => { console.error(error); process.exitCode = 1; });
