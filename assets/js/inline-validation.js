/* Shared inline validation; native constraints and server-side rules are preserved. */
(() => {
  'use strict';
  if (window.evenzaInlineValidation) return;
  window.evenzaInlineValidation = true;
  let sequence = 0;
  const controls = form => [...form.elements].filter(field => field.matches?.('input,select,textarea') && field.willValidate);
  const labelFor = field => {
    const label = field.labels?.[0]?.textContent || field.getAttribute('aria-label') || field.placeholder || field.name?.replaceAll('_',' ') || 'This field';
    return label.replace(/\*/g,'').replace(/\s+/g,' ').trim();
  };
  const messageFor = field => {
    const name = labelFor(field), v = field.validity;
    if (v.valueMissing) return `${name} is required.`;
    if (v.typeMismatch) return field.type === 'email' ? 'Please enter a valid email address.' : `Please enter a valid ${name.toLowerCase()}.`;
    if (v.tooShort) return `${name} must contain at least ${field.minLength} characters.`;
    if (v.tooLong) return `${name} must contain no more than ${field.maxLength} characters.`;
    if (v.patternMismatch) return field.title || `Please enter a valid ${name.toLowerCase()}.`;
    if (v.rangeUnderflow) return `${name} must be at least ${field.min}.`;
    if (v.rangeOverflow) return `${name} must be no more than ${field.max}.`;
    if (v.stepMismatch || v.badInput) return `Please enter a valid ${name.toLowerCase()}.`;
    return field.validationMessage || `Please check ${name.toLowerCase()}.`;
  };
  const errorFor = field => {
    if (field.dataset.inlineError) return document.getElementById(field.dataset.inlineError);
    const group = field.closest('.register-input-group,.form-group,.mb-3,.mb-4') || field.parentElement;
    let error = group.querySelector('.invalid-feedback,.invalid-feedback-bespoke');
    if (!error || [...group.querySelectorAll('input:not([type=hidden]),select,textarea')].length > 1) error = null;
    if (!error) {
      error = document.createElement('div');
      const wrapper = field.closest('.input-group,.position-relative');
      (wrapper && field.form?.contains(wrapper) ? wrapper : field).after(error);
    }
    error.id ||= `ev-field-error-${++sequence}`;
    error.classList.add('ev-field-error'); error.setAttribute('aria-live','polite');
    field.dataset.inlineError = error.id;
    const described = new Set((field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
    described.add(error.id); field.setAttribute('aria-describedby',[...described].join(' '));
    return error;
  };
  const show = field => {
    const valid = field.validity.valid;
    if (valid && !field.dataset.inlineError) return true;
    const error = errorFor(field);
    field.classList.toggle('is-invalid',!valid);
    field.setAttribute('aria-invalid',String(!valid));
    error.textContent = valid ? '' : messageFor(field);
    error.style.display = valid ? 'none' : 'flex';
    return valid;
  };
  const summary = (form, invalid) => {
    let notice = form.querySelector('.ev-validation-summary,.registration-client-error');
    if (notice) { notice.classList.add('ev-validation-summary'); notice.classList.toggle('d-none',!invalid); }
    if (!notice && invalid) {
      notice = document.createElement('div'); notice.className = 'ev-validation-summary'; notice.setAttribute('role','alert');
      notice.textContent = 'Please complete or correct the highlighted fields.'; form.prepend(notice);
    }
    if (notice) notice.hidden = !invalid;
  };
  const validate = form => {
    form.noValidate = true; form.dataset.inlineAttempted = 'true';
    for (const kind of ['university','college']) {
      const hidden = form.querySelector(`#${kind}Select[type=hidden]`);
      const display = form.querySelector(`#${kind}Input`);
      if (hidden && display) {
        display.setCustomValidity(hidden.value ? '' : `Please select your ${kind}.`);
        display.dataset.institutionConstraint = kind;
      }
    }
    const invalid = controls(form).filter(field => !show(field));
    summary(form,invalid.length > 0);
    if (invalid.length) { invalid[0].focus(); return false; }
    return true;
  };
  const prepare = root => {
    if (root.matches?.('form')) root.noValidate = true;
    root.querySelectorAll?.('form').forEach(form => { form.noValidate = true; });
  };
  prepare(document);
  new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => { if (node.nodeType === 1) prepare(node); }))).observe(document.documentElement,{childList:true,subtree:true});
  document.addEventListener('invalid', event => { event.preventDefault(); show(event.target); },true);
  document.addEventListener('submit',event => {
    if (event.target instanceof HTMLFormElement && !validate(event.target)) { event.preventDefault(); event.stopImmediatePropagation(); }
  },true);
  ['input','change','focusout'].forEach(type => document.addEventListener(type,event => {
    const field = event.target;
    if (!field.matches?.('input,select,textarea') || !field.form || !field.willValidate) return;
    if (field.dataset.institutionConstraint) {
      const hidden = field.form.querySelector(`#${field.dataset.institutionConstraint}Select`);
      field.setCustomValidity(hidden?.value ? '' : `Please select your ${field.dataset.institutionConstraint}.`);
    }
    if (field.dataset.inlineError || field.form.dataset.inlineAttempted) {
      show(field);
      if (controls(field.form).every(control => control.validity.valid)) summary(field.form,false);
    }
  }));
  document.addEventListener('click', () => {
    document.querySelectorAll('[data-institution-constraint]').forEach(field => {
      const hidden = field.form?.querySelector(`#${field.dataset.institutionConstraint}Select`);
      if (hidden?.value) { field.setCustomValidity(''); show(field); }
    });
  });
  const submit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function() { if (validate(this)) return submit.call(this); };
})();
