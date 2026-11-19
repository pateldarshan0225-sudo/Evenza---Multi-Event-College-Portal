// Browser interactions use GET requests only; no form is submitted.
const fs = require('node:fs');
(async () => {
  const tabs = await (await fetch('http://127.0.0.1:9228/json')).json();
  const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise(r => ws.addEventListener('open', r, { once: true }));
  let id = 0; const pending = new Map(); const errors = [];
  ws.addEventListener('message', e => { const m = JSON.parse(e.data); if (pending.has(m.id)) { pending.get(m.id)(m.result); pending.delete(m.id); } if (m.method === 'Runtime.exceptionThrown') errors.push(m.params.exceptionDetails); });
  const call = (method, params = {}) => new Promise(r => { const n = ++id; pending.set(n, r); ws.send(JSON.stringify({ id: n, method, params })); });
  const evaluate = async expression => { const r = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); if (r.exceptionDetails) throw Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text); return r.result.value; };
  await call('Page.enable'); await call('Runtime.enable');
  await call('Emulation.setDeviceMetricsOverride', { width: 320, height: 900, deviceScaleFactor: 1, mobile: true });
  await call('Page.navigate', { url: 'http://localhost/Evenza/Frontend/register_college.php?evenza_tab_id=audit_tab_public_0123456789abcdef' });
  for (let i = 0; i < 80; i++) { await new Promise(r => setTimeout(r, 150)); if (await evaluate(`document.readyState==='complete'&&location.pathname.endsWith('/Frontend/register_college.php')&&typeof toggleRegisterPassword==='function'`)) break; }
  const checks = [];
  await evaluate(`document.querySelector('[data-bs-target="#evenzaMobileNav"]').click()`);
  await new Promise(r => setTimeout(r, 400));
  checks.push({ name: 'Mobile menu opens', pass: await evaluate(`document.getElementById('evenzaMobileNav').classList.contains('show')`) });
  await evaluate(`document.getElementById('signUpDropdown').click()`);
  checks.push({ name: 'Signup dropdown opens within viewport', pass: await evaluate(`(()=>{const e=document.querySelector('[aria-labelledby="signUpDropdown"]');const r=e.getBoundingClientRect();return e.classList.contains('show')&&r.left>=0&&r.right<=innerWidth;})()`) });
  await evaluate(`document.getElementById('signUpDropdown').click();document.querySelector('[data-bs-target="#evenzaMobileNav"]').click()`);
  checks.push({ name: 'Password visibility toggle', pass: await evaluate(`toggleRegisterPassword();document.getElementById('registerPassword').type==='text'`) });
  checks.push({ name: 'Invalid registration is blocked before submission', pass: await evaluate(`!document.getElementById('collegeRegisterForm').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))`) });
  checks.push({ name: 'Phone validation matches Indian normalization', pass: await evaluate(`(()=>{const e=document.getElementById('phone');e.value='+91 98765 43210';const valid=validateField('phone');e.value='1234567890';return valid&&!validateField('phone');})()`) });
  checks.push({ name: 'Editing university clears the stored selection', pass: await evaluate(`(()=>{const item=document.querySelector('#universityDropdown .searchable-select-item');selectUniversity(item.dataset.value,item.textContent.trim());const e=document.getElementById('universityInput');e.value='another university';e.dispatchEvent(new Event('input',{bubbles:true}));return document.getElementById('universitySelect').value==='';})()`) });
  await evaluate(`toggleRegisterPassword()`);
  checks.push({ name: 'Email validation feedback', pass: await evaluate(`document.getElementById('email').value='invalid';validateField('email');document.getElementById('email').classList.contains('is-invalid')`) });
  await evaluate(`document.getElementById('email').value='';document.getElementById('email').classList.remove('is-invalid');document.getElementById('err_email').style.display='none'`);
  await evaluate(`scrollTo(0,0)`); await new Promise(r => setTimeout(r, 400));
  const shot = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
  fs.writeFileSync('tools/register-college-mobile.png', Buffer.from(shot.data, 'base64'));
  await call('Page.navigate', { url: 'http://localhost/Evenza/Frontend/colleges.php?evenza_tab_id=audit_tab_public_0123456789abcdef' }); for(let i=0;i<80;i++){await new Promise(r=>setTimeout(r,100));if(await evaluate(`document.readyState==='complete'&&location.pathname.endsWith('/Frontend/colleges.php')&&!!document.querySelector('input[name=search]')`))break;}
  await evaluate(`(()=>{const e=document.querySelector('input[name="search"]');e.value='no_such_college_audit';e.dispatchEvent(new Event('input',{bubbles:true}));})()`);
  await new Promise(r => setTimeout(r, 900));
  checks.push({ name: 'College live search empty state', pass: await evaluate(`location.search.includes('no_such_college_audit')&&document.getElementById('ajaxCollegesContainer').innerText.toLowerCase().includes('no partner colleges')`) });
  fs.writeFileSync('tools/flow-audit-results.json', JSON.stringify({ checks, errors }, null, 2));
  console.log(JSON.stringify({ checks, errors }, null, 2)); ws.close();
})().catch(e => { console.error(e); process.exitCode = 1; });
