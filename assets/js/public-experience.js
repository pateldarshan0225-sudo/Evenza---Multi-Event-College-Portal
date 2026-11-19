/* Progressive enhancement: server-rendered content remains visible without JS. */
(() => {
  'use strict';
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const nav = document.querySelector('.evenza-navbar');
  const content = document.querySelector('main, body > section');
  if (content) { content.id = 'public-content'; content.tabIndex = -1; }
  document.querySelectorAll('label:not([for])').forEach(label => {
    const field = label.parentElement.querySelector('input:not([type=hidden]),select,textarea');
    if (field) { if (!field.id) field.id = `public-field-${Array.from(document.querySelectorAll('input,select,textarea')).indexOf(field)}`; label.htmlFor = field.id; }
  });
  document.querySelectorAll('.evenza-nav-link.active').forEach(link => link.setAttribute('aria-current', 'page'));
  let queued = false;
  const updateNav = () => { nav?.classList.toggle('is-scrolled', scrollY > 16); queued = false; };
  addEventListener('scroll', () => { if (!queued) { queued = true; requestAnimationFrame(updateNav); } }, {passive:true});
  updateNav();
  const search = document.querySelector('.hero-search-box input');
  search?.setAttribute('aria-label', 'Search college events');
  document.querySelector('footer input[type="email"]')?.setAttribute('aria-label', 'Email address for event updates');
  if (reduced.matches || !('IntersectionObserver' in window)) return;
  document.body.classList.add('public-motion-ready');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(({target, isIntersecting}) => {
      if (!isIntersecting) return;
      target.classList.remove('is-pending');
      target.classList.add('in-view');
      observer.unobserve(target);
    });
  }, {threshold:.08});
  document.querySelectorAll('main section, body > section').forEach(section => {
    observer.observe(section);
    const items = section.querySelectorAll('h2, .value-card-box, .cat-luxury-card, .event-card-bespoke, .journey-card, .college-card-bespoke, .category-card-bespoke, .pillar-card-bespoke, .about-card-luxury, .contact-info-card-luxury, .ez-highlight-card');
    items.forEach((item, index) => {
      item.classList.add('home-reveal', 'is-pending');
      item.style.setProperty('--reveal-delay', `${Math.min(index % 3 * 80, 160)}ms`);
      observer.observe(item);
    });
  });
  const counter = document.querySelector('[data-count]');
  if (counter) {
    const value = Number(counter.dataset.count);
    const start = performance.now();
    const tick = now => {
      const progress = Math.min((now - start) / 1000, 1);
      counter.textContent = Math.round(value * (1 - Math.pow(1 - progress, 3))).toLocaleString('en-IN');
      if (progress < 1) requestAnimationFrame(tick);
    };
    if (Number.isFinite(value)) requestAnimationFrame(tick);
  }
  // Switching motion preference immediately makes all content visible.
  reduced.addEventListener('change', () => {
    if (reduced.matches) {
      observer.disconnect();
      document.querySelectorAll('.is-pending').forEach(el => el.classList.remove('is-pending'));
    }
  });
})();
