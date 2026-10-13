'use strict';
// Horizon design: story-style destination hero and the sideways-scrolling destination journey.
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');

const story = document.querySelector('.hz-story');
if (story) {
  const slides = [...story.querySelectorAll('.hz-slide')];
  const bars = [...story.querySelectorAll('.hz-bar')];
  let current = 0;
  const show = index => {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, position) => {
      const active = position === current;
      slide.classList.toggle('is-active', active);
      slide.inert = !active;
      if (active) slide.removeAttribute('aria-hidden'); else slide.setAttribute('aria-hidden', 'true');
    });
    bars.forEach((bar, position) => {
      bar.classList.toggle('is-done', position < current);
      bar.classList.remove('is-active');
      void bar.offsetWidth; // restart the progress fill
      bar.classList.toggle('is-active', position === current);
      bar.setAttribute('aria-current', String(position === current));
    });
  };
  bars.forEach((bar, position) => {
    bar.addEventListener('click', () => show(position));
    // The active bar's fill animation is the timer; when it completes, move on.
    bar.querySelector('span').addEventListener('animationend', () => { if (position === current) show(current + 1); });
  });
  story.querySelector('.hz-step-prev').addEventListener('click', () => show(current - 1));
  story.querySelector('.hz-step-next').addEventListener('click', () => show(current + 1));
  // Stop advancing while the booking form or photo gallery is open.
  const pauseWhen = document.querySelectorAll('#booking-dialog, #photo-lightbox');
  const syncPause = () => story.classList.toggle('is-paused', [...pauseWhen].some(dialog => dialog.open) || document.hidden);
  pauseWhen.forEach(dialog => new MutationObserver(syncPause).observe(dialog, { attributes: true, attributeFilter: ['open'] }));
  document.addEventListener('visibilitychange', syncPause);
  show(0);
}

const pin = document.querySelector('.hz-pin');
if (pin) {
  const track = pin.querySelector('.hz-track');
  const progress = pin.querySelector('.hz-progress span');
  const roomy = matchMedia('(min-width: 901px) and (min-height: 640px)');
  const pinned = () => roomy.matches && !reducedMotion.matches;
  const distance = () => Math.max(0, track.scrollWidth - innerWidth);
  const update = () => {
    if (!pin.classList.contains('is-pinned')) return;
    const travelled = Math.min(Math.max(-pin.getBoundingClientRect().top, 0), distance());
    track.style.transform = `translate3d(${-travelled}px, 0, 0)`;
    progress.style.transform = `scaleX(${distance() ? travelled / distance() : 0})`;
  };
  const layout = () => {
    pin.classList.toggle('is-pinned', pinned());
    if (!pinned()) { pin.style.height = ''; track.style.transform = ''; return; }
    pin.style.height = `${distance() + innerHeight}px`;
    update();
  };
  // Keep keyboard focus visible: scroll the page to wherever the focused card sits on the track.
  track.addEventListener('focusin', event => {
    if (!pin.classList.contains('is-pinned')) return;
    const card = event.target.closest('.hz-track > *');
    if (!card) return;
    const target = pin.getBoundingClientRect().top + scrollY + Math.min(Math.max(card.offsetLeft - 64, 0), distance());
    scrollTo({ top: target, behavior: 'instant' });
  });
  addEventListener('scroll', update, { passive: true });
  addEventListener('resize', layout);
  roomy.addEventListener('change', layout);
  reducedMotion.addEventListener('change', layout);
  addEventListener('load', layout);
  layout();
}
