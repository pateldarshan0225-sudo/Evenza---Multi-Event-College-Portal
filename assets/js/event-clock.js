/* Refresh server-derived lifecycle state at its boundary without discarding edited forms. */
(() => {
  const script = document.querySelector('script[data-refresh-after]');
  const delay = Number(script?.dataset.refreshAfter);
  if (!Number.isFinite(delay) || delay <= 0) return;
  const deadline = performance.now() + delay * 1000;
  let edited = false;
  document.addEventListener('input', event => { if (event.target.closest('form')) edited = true; }, {capture:true});
  const check = () => {
    const remaining = deadline - performance.now();
    if (remaining > 0) { setTimeout(check, Math.min(remaining, 2147483000)); return; }
    if (edited || document.querySelector('.modal.show') || document.hidden) { setTimeout(check, 15000); return; }
    location.reload();
  };
  setTimeout(check, Math.min(delay * 1000, 2147483000));
})();
