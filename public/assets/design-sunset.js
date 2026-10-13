'use strict';
// Sunset design: the sun sets as you scroll the hero, the menu becomes a frosted bar once you scroll,
// and the full-screen backdrop follows the story, cycling through each destination's photos.
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
const motionPaused = () => reducedMotion.matches || document.querySelector('#motion-toggle')?.getAttribute('aria-pressed') === 'true';

const hero = document.querySelector('.ss-hero');
const header = document.querySelector('.header');
if (hero && header) {
  // The header overlaps the hero; keep that overlap equal to its real height on every screen size.
  const fitHeader = () => { header.style.marginBottom = `-${header.offsetHeight}px`; };
  fitHeader();
  addEventListener('resize', fitHeader);
  let ticking = false;
  const update = () => {
    ticking = false;
    const height = hero.offsetHeight;
    const scrolled = Math.min(Math.max(-hero.getBoundingClientRect().top, 0), height);
    header.classList.toggle('is-solid', scrollY > 40);
    if (!reducedMotion.matches) hero.style.setProperty('--set', (scrolled / height).toFixed(3));
  };
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
  update();
}

const story = document.querySelector('.ss-story');
if (story && 'IntersectionObserver' in window) {
  const layers = [...story.querySelectorAll('[data-backdrop]')];
  const dots = story.querySelectorAll('[data-dot]');
  let active = 'intro';
  let timer = null;
  const cycle = layer => {
    clearInterval(timer);
    const photos = JSON.parse(layer.dataset.photos || '[]');
    if (photos.length < 2) return;
    let index = 1;
    timer = setInterval(() => {
      if (motionPaused() || document.hidden) return;
      index = (index + 1) % photos.length;
      const next = new Image();
      next.src = photos[index];
      next.alt = '';
      next.decode().then(() => {
        if (layer.dataset.backdrop !== active) return;
        next.className = 'is-entering';
        layer.append(next);
        requestAnimationFrame(() => next.classList.remove('is-entering'));
        setTimeout(() => { [...layer.querySelectorAll('img')].slice(0, -1).forEach(old => old.remove()); }, 1400);
      }).catch(() => {});
    }, 5000);
  };
  const activate = id => {
    if (id === active) return;
    active = id;
    layers.forEach(layer => layer.classList.toggle('is-active', layer.dataset.backdrop === id));
    dots.forEach(dot => dot.classList.toggle('is-active', dot.dataset.dot === id));
    const layer = layers.find(item => item.dataset.backdrop === id);
    if (layer) cycle(layer); else clearInterval(timer);
  };
  const observer = new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) activate(entry.target.dataset.story);
  }), { rootMargin: '-45% 0px -45% 0px' });
  story.querySelectorAll('[data-story]').forEach(item => observer.observe(item));
}
