/* Admin overview: all figures are derived from read-only source records. */
'use strict';
const overview = window.masterDataset;
const el = id => document.getElementById(id);
const text = value => String(value ?? '');
const escapeHtml = value => text(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const money = value => Number(value || 0).toLocaleString('en-IN', {style:'currency', currency:'INR'});
const dateOf = value => value ? new Date(text(value).replace(' ', 'T')) : new Date(NaN);
const dayKey = date => [date.getFullYear(), String(date.getMonth()+1).padStart(2,'0'), String(date.getDate()).padStart(2,'0')].join('-');
const normalized = value => text(value).toLowerCase();
let chartLine, chartDonut, chartBar, chartHBar, chartPie;
let linePeriod = 'all', revenueMode = 'monthly';
let currentRegs = [], currentPayments = [];
let currentSort = {table:null, col:null, asc:true};

function inPeriod(value, period = el('datePresetSelect').value) {
    if (period === 'all') return true;
    const date = dateOf(value), start = new Date(), end = new Date();
    start.setHours(0,0,0,0); start.setDate(start.getDate() - Number(period) + 1);
    end.setHours(23,59,59,999);
    return date >= start && date <= end;
}

function initCharts() {
    Chart.defaults.font.family = "'Evenza Inter', system-ui, sans-serif";
    Chart.defaults.color = '#697461';
    const emptyState = {id:'overviewEmptyState', afterDraw(chart) {
        if (chart.data.datasets.some(set=>set.data.some(value=>Number(value)>0))) return;
        const {ctx, chartArea} = chart; if (!chartArea) return;
        ctx.save(); ctx.fillStyle='#697461'; ctx.font='12px system-ui'; ctx.textAlign='center';
        ctx.fillText('No data for selected filters', (chartArea.left+chartArea.right)/2, (chartArea.top+chartArea.bottom)/2); ctx.restore();
    }};
    const make = (id, type, labels, color) => new Chart(el(id), {
        type, plugins:[emptyState],
        data:{labels, datasets:[{label:id === 'barChart' ? 'Revenue (INR)' : 'Registrations', data:[], backgroundColor:color, borderColor:type === 'line' ? '#4f46e5' : '#fff', borderWidth:2, tension:.25}]},
        options:{responsive:true, maintainAspectRatio:false, animation:false, plugins:{legend:{display:false}},
            ...(type === 'doughnut' ? {cutout:'72%'} : {}),
            ...(['bar','line'].includes(type) ? {scales:{y:{beginAtZero:true, ticks:{precision:0}}, x:{grid:{display:false}}}} : {}),
            ...(id === 'hBarChart' ? {indexAxis:'y'} : {})}
    });
    chartLine = make('lineChart','line',[], '#4f46e51a');
    chartDonut = make('donutChart','doughnut',['Used','Unused'],['#198754','#e5e7eb']);
    chartBar = make('barChart','bar',[],'#198754');
    chartHBar = make('hBarChart','bar',[],['#4f46e5','#198754','#0284c7','#9333ea','#d97706']);
    chartPie = make('pieChart','pie',['Team','Solo'],['#4f46e5','#0284c7']);
}

function updateChart(chart, labels, values) {
    chart.data.labels = labels; chart.data.datasets[0].data = values; chart.update();
}

function updateTimeline() {
    const counts = new Map();
    const rows = currentRegs.filter(row => inPeriod(row.registered_at, linePeriod));
    rows.forEach(row => { const date = dateOf(row.registered_at); if (!isNaN(date)) { const key = dayKey(date); counts.set(key,(counts.get(key)||0)+1); } });
    if (linePeriod !== 'all') {
        const date = new Date(); date.setHours(0,0,0,0); date.setDate(date.getDate()-Number(linePeriod)+1);
        for (let i=0; i<Number(linePeriod); i++, date.setDate(date.getDate()+1)) { const key=dayKey(date); if (!counts.has(key)) counts.set(key,0); }
    }
    const keys = [...counts.keys()].sort();
    updateChart(chartLine, keys, keys.map(key=>counts.get(key)));
}

function updateRevenue() {
    const totals = new Map();
    currentPayments.filter(row=>normalized(row.payment_status)==='paid').forEach(row=>{
        const date=dateOf(row.payment_date); if (isNaN(date)) return;
        const key=revenueMode==='quarterly' ? `${date.getFullYear()} Q${Math.floor(date.getMonth()/3)+1}` : dayKey(date).slice(0,7);
        totals.set(key,(totals.get(key)||0)+Number(row.amount));
    });
    const keys=[...totals.keys()].sort();
    updateChart(chartBar,keys,keys.map(key=>totals.get(key)));
}

function applyFilters() {
    const searches=[el('liveSearchInput').value, el('filterSearch').value].map(v=>v.trim().toLowerCase()).filter(Boolean);
    const event=el('filterEvent').value, college=el('filterCollege').value, status=el('filterStatus').value;
    const scoped=searches.length>0 || event!=='all' || college!=='all' || status!=='all';
    const matches = row => searches.every(query=>[row.registration_id,row.participant_name,row.event_title,row.college_name].some(value=>normalized(value).includes(query))) && (event==='all'||row.event_title===event) && (college==='all'||row.college_name===college) && (status==='all'||normalized(row.status)===status);
    const scopeRegs=overview.registrations.filter(matches);
    const scopeIds=new Set(scopeRegs.map(row=>String(row.registration_id)));
    currentRegs=scopeRegs.filter(row=>inPeriod(row.registered_at)).sort((a,b)=>text(b.registered_at).localeCompare(text(a.registered_at))||Number(b.registration_id)-Number(a.registration_id));
    currentPayments=overview.payments.filter(row=>(!scoped||scopeIds.has(String(row.registration_id)))&&inPeriod(row.payment_date));
    const passes=overview.passes.filter(row=>(!scoped||scopeIds.has(String(row.registration_id)))&&inPeriod(row.issued_at));
    const used=passes.filter(row=>Number(row.is_used)===1).length;
    const ids=new Set(currentRegs.map(row=>String(row.student_id)).filter(id=>id!=='null'));
    const teamIds=new Set(currentRegs.map(row=>String(row.team_id)));
    overview.members.filter(row=>teamIds.has(String(row.team_id))).forEach(row=>ids.add(String(row.student_id)));
    const eventIds=new Set(currentRegs.map(row=>String(row.event_id)));
    const students=overview.students.filter(row=>scoped ? ids.has(String(row.student_id)) : inPeriod(row.created_at));
    const events=overview.events.filter(row=>scoped ? eventIds.has(String(row.event_id)) : inPeriod(row.created_at));
    const collegeIds=new Set(events.map(row=>String(row.college_id)).concat(students.map(row=>String(row.college_id))));
    const colleges=overview.colleges.filter(row=>row.status==='active' && (scoped ? collegeIds.has(String(row.college_id)) : inPeriod(row.created_at)));
    const revenue=currentPayments.filter(row=>normalized(row.payment_status)==='paid').reduce((sum,row)=>sum+Number(row.amount),0);
    const cancelled=currentRegs.filter(row=>normalized(row.status)==='cancelled').length;
    const kpis={students:students.length, events:events.length, regs:currentRegs.length, rev:revenue, colleges:colleges.length, issued:passes.length, used, cancelled};
    const allRevenue=overview.payments.filter(row=>normalized(row.payment_status)==='paid').reduce((sum,row)=>sum+Number(row.amount),0);
    const totals={students:overview.students.length,events:overview.events.length,regs:overview.registrations.length,rev:allRevenue,colleges:overview.colleges.filter(row=>row.status==='active').length,issued:overview.passes.length,used:overview.passes.filter(row=>Number(row.is_used)===1).length,cancelled:overview.registrations.filter(row=>normalized(row.status)==='cancelled').length};
    Object.entries(kpis).forEach(([key,value])=>{el(`kpi-${key}-val`).textContent=key==='rev'?money(value):value.toLocaleString('en-IN');const bar=el(`kpi-${key}-bar`);bar.style.width=(totals[key]?Math.min(100,value/totals[key]*100):0)+'%';bar.title='Share of all-time total';});
    const usedPct=passes.length?used/passes.length*100:0;
    el('donut-used-pct').textContent=usedPct.toFixed(1)+'%';
    el('donut-unused-pct').textContent=(passes.length?100-usedPct:0).toFixed(1)+'%';
    updateChart(chartDonut,['Used','Unused'],[used,passes.length-used]);
    const teams=currentRegs.filter(row=>row.registration_type==='team').length, solos=currentRegs.filter(row=>row.registration_type==='solo').length;
    updateChart(chartPie,['Team','Solo'],[teams,solos]);
    el('team-share').textContent=(teams+solos?teams/(teams+solos)*100:0).toFixed(1)+'%';
    el('solo-share').textContent=(teams+solos?solos/(teams+solos)*100:0).toFixed(1)+'%';
    const ranked=new Map();currentRegs.forEach(row=>{const key=String(row.event_id); const entry=ranked.get(key)||{title:row.event_title,count:0};entry.count++;ranked.set(key,entry);});
    const top=[...ranked.values()].sort((a,b)=>b.count-a.count||a.title.localeCompare(b.title)).slice(0,5);
    updateChart(chartHBar,top.map(row=>row.title),top.map(row=>row.count));
    el('insight-event').textContent=top[0]?.title||'No registrations';
    el('insight-event-count').textContent=(top[0]?.count||0)+' registrations';
    el('insight-colleges').textContent=colleges.length+' Partner Colleges';
    const approved=currentRegs.filter(row=>normalized(row.status)==='approved').length;
    el('insight-status').textContent=approved+' Approved / '+currentRegs.filter(row=>normalized(row.status)==='pending').length+' Pending';
    el('insight-approval').textContent=(currentRegs.length?approved/currentRegs.length*100:0).toFixed(1)+'% Approval Rate';
    el('activeFilterBadge').style.display=scoped||el('datePresetSelect').value!=='all'?'inline-flex':'none';
    renderTables(); updateTimeline(); updateRevenue();
}

function changeLineChartPeriod(period, button) {
    linePeriod=period;
    button.parentElement.querySelectorAll('.chart-tab-btn').forEach(item=>item.classList.toggle('active',item===button));
    updateTimeline();
}
function toggleRevenueView(mode, button) {
    revenueMode=mode;
    button.parentElement.querySelectorAll('.chart-tab-btn').forEach(item=>item.classList.toggle('active',item===button));
    updateRevenue();
}
function handleDatePresetChange() { linePeriod=el('datePresetSelect').value; document.querySelectorAll('#lineChartTabs .chart-tab-btn').forEach(button=>button.classList.toggle('active',button.dataset.period===linePeriod)); applyFilters(); }
function resetFilters() {
    ['liveSearchInput','filterSearch'].forEach(id=>el(id).value='');
    ['filterEvent','filterCollege','filterStatus','datePresetSelect'].forEach(id=>el(id).value='all');
    currentSort={table:null,col:null,asc:true}; handleDatePresetChange();
}
function badge(status) { const value=normalized(status); return `<span class="status-badge ${['approved','paid','success'].includes(value)?'badge-success':value==='pending'?'badge-warning':'badge-danger'}">${escapeHtml(status)}</span>`; }
function renderTables() {
    let regs=currentRegs.slice(0,5), payments=[...currentPayments];
    if (currentSort.table) {
        const isReg=currentSort.table==='regTable', keys=isReg?['participant_name','event_title','status','registered_at']:['payment_id','amount','payment_status','event_title'];
        const list=isReg?regs:payments, key=keys[currentSort.col];
        list.sort((a,b)=>(key==='amount'?Number(a[key])-Number(b[key]):text(a[key]).localeCompare(text(b[key])))*(currentSort.asc?1:-1));
    }
    el('regCountBadge').textContent=regs.length+' of '+currentRegs.length;
    el('payCountBadge').textContent=payments.length+' Records';
    el('regTableBody').innerHTML=regs.slice(0,5).map(row=>`<tr data-registration="${Number(row.registration_id)}" tabindex="0" role="button" aria-label="View registration ${Number(row.registration_id)}"><td class="ps-4"><strong>${escapeHtml(row.participant_name)}</strong><br><small class="text-muted">REG#${Number(row.registration_id)} · ${escapeHtml(row.registration_type)}</small></td><td>${escapeHtml(row.event_title)}<br><small class="text-muted">${escapeHtml(row.college_name)} &middot; ${escapeHtml(row.event_status || "")} &middot; ${escapeHtml(row.pass_status || "")}</small></td><td>${badge(row.status)}</td><td class="pe-4 text-muted small">${escapeHtml(text(row.registered_at).slice(0,10))}</td></tr>`).join('')||'<tr><td colspan="4" class="text-center text-muted py-4">No matching registrations</td></tr>';
    el('payTableBody').innerHTML=payments.map(row=>`<tr data-payment="${Number(row.payment_id)}" tabindex="0" role="button" aria-label="View payment ${Number(row.payment_id)}"><td class="ps-4 small">${escapeHtml(row.transaction_id||row.payment_id)}</td><td class="small">${money(row.amount)}</td><td>${badge(row.payment_status)}</td><td class="pe-4 small">${escapeHtml(row.event_title)}</td></tr>`).join('')||'<tr><td colspan="4" class="text-center text-muted py-4">No transactions found</td></tr>';
}
function sortTable(table,col) { currentSort={table,col,asc:currentSort.table===table&&currentSort.col===col?!currentSort.asc:true};renderTables(); }
function showDetails(title, fields) {
    el('modalTitle').textContent=title;
    el('modalBody').innerHTML='<dl class="row g-3">'+Object.entries(fields).map(([key,value])=>`<dt class="col-sm-4 text-muted">${escapeHtml(key)}</dt><dd class="col-sm-8 text-break">${escapeHtml(value||'Not provided')}</dd>`).join('')+'</dl>';
    el('detailModal').classList.add('show');
    el('detailModal').querySelector('button').focus();
}
function showRegDetails(id) {const row=overview.registrations.find(item=>Number(item.registration_id)===Number(id));if(row)showDetails('Registration #'+row.registration_id,{Participant:row.participant_name,Event:row.event_title,College:row.college_name,Status:row.status,Format:row.registration_type,Registered:row.registered_at,Email:row.email,Phone:row.phone});}
function showPaymentReceipt(id) {const row=overview.payments.find(item=>Number(item.payment_id)===Number(id));if(row)showDetails('Payment #'+row.payment_id,{Amount:money(row.amount),Status:row.payment_status,Transaction:row.transaction_id,Student:row.student_name,Event:row.event_title,Method:row.payment_method,Date:row.payment_date});}
function closeModal() {el('detailModal').classList.remove('show');}
function exportCSVData() {
    const fields=['registration_id','participant_name','event_title','college_name','status','registered_at'];
    const csv=[fields,...currentRegs.map(row=>fields.map(key=>row[key]))].map(row=>row.map(value=>'"'+text(value).replace(/^[=+@-]/,"'$&").replace(/"/g,'""')+'"').join(',')).join('\r\n');
    const url=URL.createObjectURL(new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8;'})), link=document.createElement('a');
    link.href=url;link.download='evenza-overview-registrations.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
}
document.addEventListener('DOMContentLoaded',()=>{
    initCharts();applyFilters();
    const activate=event=>{if(event.type==='keydown'&&!['Enter',' '].includes(event.key))return;const row=event.target.closest('[data-registration],[data-payment]');if(!row)return;event.preventDefault();if(row.dataset.registration)showRegDetails(row.dataset.registration);else showPaymentReceipt(row.dataset.payment);};
    document.addEventListener('click',activate);document.addEventListener('keydown',activate);
    document.addEventListener('keydown',event=>{if(event.key==='Escape')closeModal();});
});
