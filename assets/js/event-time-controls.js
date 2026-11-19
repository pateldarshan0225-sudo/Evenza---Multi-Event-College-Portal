/* Explicit 12-hour controls; original named fields keep the existing 24-hour PHP contract. */
(() => {
  let sequence = 0;
  document.querySelectorAll('input[type="time"][name="start_time"],input[type="time"][name="end_time"]').forEach(canonical => {
    const group = document.createElement('div'); group.className = 'event-time-control input-group';
    const display = document.createElement('input'); display.type = 'text'; display.className = 'form-control';
    display.placeholder = 'hh:mm'; display.inputMode = 'text'; display.required = canonical.required;
    display.pattern = '(0?[1-9]|1[0-2]):[0-5][0-9]'; display.maxLength = 5;
    display.title = 'Enter a time from 1:00 to 12:59, then choose AM or PM.';
    const period = document.createElement('select'); period.className = 'form-select';
    period.setAttribute('aria-label', `${canonical.name === 'start_time' ? 'Start' : 'End'} time AM or PM`);
    ['AM','PM'].forEach(text => { const option = document.createElement('option'); option.value = text; option.textContent = text; period.append(option); });
    const originalId = canonical.id;
    display.id = originalId || `event-time-${++sequence}`;
    if (originalId) canonical.id = originalId + '-canonical';
    const label = canonical.parentElement.querySelector('label');
    if (label) label.htmlFor = display.id;
    display.setAttribute('aria-label', `${canonical.name === 'start_time' ? 'Start' : 'End'} time hours and minutes`);
    const initial = canonical.value.match(/^(\d{2}):(\d{2})/);
    if (initial) {
      const hour = Number(initial[1]); display.value = `${String(hour % 12 || 12).padStart(2,'0')}:${initial[2]}`;
      period.value = hour >= 12 ? 'PM' : 'AM';
    }
    canonical.type = 'hidden'; canonical.before(group); group.append(display, period);
    const sync = () => {
      const value = display.value.trim();
      const valid = /^(0?[1-9]|1[0-2]):([0-5][0-9])$/.exec(value);
      display.setCustomValidity(value && !valid ? 'Enter a valid time, for example 01:30, and choose AM or PM.' : '');
      canonical.value = valid ? `${String(Number(valid[1]) % 12 + (period.value === 'PM' ? 12 : 0)).padStart(2,'0')}:${valid[2]}` : '';
    };
    display.addEventListener('input', sync); period.addEventListener('change', sync);
    display.addEventListener('blur', () => { sync(); if (canonical.value) display.value = display.value.padStart(5,'0'); });
    canonical.form?.addEventListener('submit', event => {
      sync();
      if (!display.checkValidity()) { event.preventDefault(); event.stopImmediatePropagation(); display.reportValidity(); }
    }, {capture:true});
    sync();
  });
})();
