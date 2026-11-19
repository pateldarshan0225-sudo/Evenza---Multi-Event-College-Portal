// Read-only fixture rendering; browser interactions never submit a form.
const fs = require('node:fs');
const {spawnSync} = require('node:child_process');
(async()=>{
    const rendered=spawnSync('C:/wamp64/bin/php/php8.3.28/php.exe',['-d','xdebug.mode=off','tools/render-audit.php','Admin/Dashboard.php'],{encoding:'utf8',maxBuffer:32*1024*1024});
    if(rendered.status!==0 || /Fatal error|Warning:|Parse error/.test(rendered.stdout+rendered.stderr)) throw Error('Read-only overview rendering failed');
    const tab=await(await fetch('http://127.0.0.1:9228/json/new?about:blank',{method:'PUT'})).json();
    const ws=new WebSocket(tab.webSocketDebuggerUrl);await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
    let id=0;const pending=new Map(),errors=[];
    ws.addEventListener('message',event=>{const message=JSON.parse(event.data);if(pending.has(message.id)){pending.get(message.id)(message);pending.delete(message.id);}if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.text);});
    const call=(method,params={})=>new Promise((resolve,reject)=>{const next=++id;const timer=setTimeout(()=>reject(Error(method+' timed out')),15000);pending.set(next,message=>{clearTimeout(timer);message.error?reject(Error(JSON.stringify(message.error))):resolve(message.result);});ws.send(JSON.stringify({id:next,method,params}));});
    const evaluate=async expression=>{const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
    ws.addEventListener('message',async event=>{const message=JSON.parse(event.data);if(message.method==='Fetch.requestPaused')await call('Fetch.fulfillRequest',{requestId:message.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(rendered.stdout).toString('base64')});});
    await call('Page.enable');await call('Runtime.enable');await call('Fetch.enable',{patterns:[{urlPattern:'*Admin/Dashboard.php*',resourceType:'Document'}]});
    await call('Page.navigate',{url:'http://localhost/Evenza/Admin/Dashboard.php?evenza_tab_id=audit_tab_0123456789abcdef'});
    for(let i=0;i<60;i++){await new Promise(resolve=>setTimeout(resolve,100));if(await evaluate('document.readyState === "complete"'))break;}
    const checks=await evaluate(`(()=>{
        const checks=[], check=(name,pass)=>checks.push({name,pass:Boolean(pass)});
        const sum=values=>values.reduce((total,value)=>total+Number(value),0);
        check('Recent Registrations heading', [...document.querySelectorAll('h3')].some(node=>node.textContent==='Recent Registrations'));
        const expected=overview.registrations.slice().sort((a,b)=>String(b.registered_at).localeCompare(String(a.registered_at))||Number(b.registration_id)-Number(a.registration_id)).slice(0,5).map(row=>Number(row.registration_id));
        check('Only latest five rows shown', JSON.stringify([...document.querySelectorAll('#regTableBody [data-registration]')].map(row=>Number(row.dataset.registration)))===JSON.stringify(expected));
        check('Timeline totals equal registrations', sum(chartLine.data.datasets[0].data)===overview.registrations.length);
        const paid=overview.payments.filter(row=>row.payment_status==='paid').reduce((total,row)=>total+Number(row.amount),0);
        check('Monthly revenue equals actual paid transactions', Math.abs(sum(chartBar.data.datasets[0].data)-paid)<.001);
        check('Pass counts equal source records', Number(el('kpi-issued-val').textContent.replace(/,/g,''))===overview.passes.length && chartDonut.data.datasets[0].data[0]===overview.passes.filter(row=>Number(row.is_used)===1).length);
        const button=[...document.querySelectorAll('.chart-tab-btn')].find(node=>node.textContent==='Quarterly');toggleRevenueView('quarterly',button);
        check('Quarterly revenue preserves actual total', Math.abs(sum(chartBar.data.datasets[0].data)-paid)<.001);
        el('filterStatus').value='pending';applyFilters();sortTable('regTable',0);
        check('Sorting keeps active status filter and five-row limit', currentRegs.every(row=>row.status==='pending') && document.querySelectorAll('#regTableBody [data-registration]').length<=5 && [...document.querySelectorAll('#regTableBody [data-registration]')].every(row=>overview.registrations.find(record=>String(record.registration_id)===row.dataset.registration).status==='pending'));
        resetFilters();el('datePresetSelect').value='7';handleDatePresetChange();
        const start=new Date();start.setHours(0,0,0,0);start.setDate(start.getDate()-6);const end=new Date();end.setHours(23,59,59,999);
        check('Date preset filters actual registration dates', currentRegs.length===overview.registrations.filter(row=>{const date=new Date(row.registered_at.replace(' ','T'));return date>=start&&date<=end;}).length);
        resetFilters();el('filterSearch').value='__no_matching_overview_record__';applyFilters();
        check('Empty search produces zero counts and empty charts', currentRegs.length===0 && currentPayments.length===0 && ['students','events','regs','issued','used','cancelled'].every(key=>el('kpi-'+key+'-val').textContent==='0') && sum(chartLine.data.datasets[0].data)===0 && sum(chartDonut.data.datasets[0].data)===0 && chartHBar.data.labels.length===0);
        check('Empty search displays an honest empty state',el('regTableBody').textContent.includes('No matching registrations') && el('donut-used-pct').textContent==='0.0%');
        resetFilters();
        if(currentRegs.length){showRegDetails(currentRegs[0].registration_id);check('Actual registration details open',el('detailModal').classList.contains('show')&&el('modalBody').textContent.includes(currentRegs[0].participant_name));closeModal();}
        check('Reset restores actual totals', Number(el('kpi-regs-val').textContent.replace(/,/g,''))===overview.registrations.length);
        const snapshot={...overview};Object.keys(overview).forEach(key=>overview[key]=[]);resetFilters();
        check('Empty source records never produce demo data',currentRegs.length===0 && currentPayments.length===0 && chartLine.data.datasets[0].data.length===0 && chartBar.data.datasets[0].data.length===0 && el('kpi-rev-val').textContent===money(0) && el('donut-unused-pct').textContent==='0.0%');
        Object.assign(overview,snapshot);resetFilters();
        const safeRow={...overview.registrations[0],registration_id:987654321,participant_name:'<img src=x onerror=alert(1)>',registered_at:'2099-01-01 00:00:00'};
        overview.registrations=[safeRow];resetFilters();
        check('Participant text is rendered without executing markup',el('regTableBody').textContent.includes(safeRow.participant_name) && !el('regTableBody').querySelector('img'));
        Object.assign(overview,snapshot);resetFilters();
        return checks;
    })()`);
    for(const width of [320,375,768,1024,1440,1920]){
        await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<769});
        await new Promise(resolve=>setTimeout(resolve,150));
        checks.push({name:'Overview layout '+width,pass:await evaluate(`innerWidth<=${width}+1 && document.documentElement.scrollWidth<=${width}+1`)});
    }
    fs.writeFileSync('tools/admin-overview-audit-results.json',JSON.stringify({checks,errors},null,2));
    console.log(JSON.stringify({checks:checks.length,failures:checks.filter(check=>!check.pass),errors},null,2));
    await call('Target.closeTarget',{targetId:tab.id});ws.close();
    if(checks.some(check=>!check.pass)||errors.length)process.exitCode=1;
})().catch(error=>{console.error(error);process.exitCode=1;});
