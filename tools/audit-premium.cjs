// Presentation checks only. Portal fixtures run in a read-only database transaction.
const fs = require('node:fs');
const {spawnSync} = require('node:child_process');
(async () => {
    const tab = await (await fetch('http://127.0.0.1:9228/json/new?about:blank', {method: 'PUT'})).json();
    const ws = new WebSocket(tab.webSocketDebuggerUrl);
    await new Promise(resolve => ws.addEventListener('open', resolve, {once: true}));
    let id = 0;
    const pending = new Map();
    ws.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (pending.has(message.id)) {
            pending.get(message.id)(message);
            pending.delete(message.id);
        }
    });
    const call = (method, params = {}) => new Promise((resolve, reject) => {
        const next = ++id;
        const timer = setTimeout(() => reject(Error(method + ' timed out')), 15000);
        pending.set(next, message => { clearTimeout(timer); message.error ? reject(Error(JSON.stringify(message.error))) : resolve(message.result); });
        ws.send(JSON.stringify({id: next, method, params}));
    });
    const evaluate = async expression => {
        const result = await call('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
        if (result.exceptionDetails) throw Error(result.exceptionDetails.text);
        return result.result.value;
    };
    const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
    let html;
    ws.addEventListener('message', async event => {
        const message = JSON.parse(event.data);
        if (message.method === 'Fetch.requestPaused') {
            await call('Fetch.fulfillRequest', {requestId: message.params.requestId, responseCode: 200,
                responseHeaders: [{name: 'Content-Type', value: 'text/html; charset=utf-8'}], body: Buffer.from(html).toString('base64')});
        }
    });
    await call('Page.enable');
    const checks = [];
    for (const page of ['Admin/Dashboard.php', 'Organizer/Dashboard.php', 'Student/Dashboard.php', 'Organizer/Events.php']) {
        const rendered = spawnSync('C:/wamp64/bin/php/php8.3.28/php.exe', ['-d', 'xdebug.mode=off', 'tools/render-audit.php', page], {encoding: 'utf8', maxBuffer: 32 * 1024 * 1024});
        if (rendered.status !== 0) throw Error('Could not render ' + page);
        html = rendered.stdout;
        await call('Fetch.enable', {patterns: [{urlPattern: '*' + page + '*', resourceType: 'Document'}]});
        for (const width of [375, 1440]) {
            await call('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: width < 769});
            await call('Page.navigate', {url: 'http://localhost/Evenza/' + page + '?evenza_tab_id=audit_tab_0123456789abcdef'});
            for (let i = 0; i < 60; i++) { await pause(100); if (await evaluate('document.readyState === "complete"')) break; }
            await evaluate('document.fonts.ready.then(() => true)');
            checks.push({page, width, name: 'Local font and visible navigation labels', pass: await evaluate(`document.fonts.check('16px "Evenza Inter"') && [...document.querySelectorAll('.berun-nav-item')].every(a => a.querySelector('.berun-nav-label')?.textContent.trim())`)});
            await evaluate(`document.querySelector('.ev-profile-trigger').focus()`);
            await call('Input.dispatchKeyEvent', {type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: '\r', unmodifiedText: '\r'});
            await call('Input.dispatchKeyEvent', {type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13});
            await pause(200);
            checks.push({page, width, name: 'Keyboard account menu stays inside viewport', pass: await evaluate(`(() => { const menu = document.querySelector('.berun-sidebar-capsule .dropdown-menu'); const r = menu.getBoundingClientRect(); return menu.classList.contains('show') && r.left >= 0 && r.right <= innerWidth; })()`)});
            if (!checks.at(-1).pass) console.log(page, width, await evaluate(`(() => { const b=document.querySelector('.ev-profile-trigger');const m=document.querySelector('.berun-sidebar-capsule .dropdown-menu');const r=m.getBoundingClientRect();return {active:document.activeElement===b,expanded:b.getAttribute('aria-expanded'),shown:m.classList.contains('show'),left:r.left,right:r.right,width:innerWidth};})()`));
            await evaluate(`document.querySelector('.ev-profile-trigger').click()`);
            if (page === 'Organizer/Events.php') {
                await evaluate(`document.querySelector('[data-bs-target="#createEventModal"]').click()`);
                await pause(400);
                checks.push({page, width, name: 'Create event dialog opens within viewport', pass: await evaluate(`(() => { const modal = document.querySelector('#createEventModal'); const r = modal.querySelector('.modal-content').getBoundingClientRect(); return modal.classList.contains('show') && r.left >= 0 && r.right <= innerWidth; })()`)});
                await evaluate(`document.querySelector('#createEventModal [data-bs-dismiss="modal"]').click()`);
            }
        }
        await call('Fetch.disable');
    }
    fs.writeFileSync('tools/premium-audit-results.json', JSON.stringify(checks, null, 2));
    console.log(JSON.stringify({checks: checks.length, failures: checks.filter(check => !check.pass)}, null, 2));
    await call('Target.closeTarget', {targetId: tab.id});
    ws.close();
    if (checks.some(check => !check.pass)) process.exitCode = 1;
})().catch(error => { console.error(error); process.exitCode = 1; });
