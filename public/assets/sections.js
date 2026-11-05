// Your day: the line down the timeline fills with the scroll, and each step settles in as the line reaches it.
// Getting there: the routes draw themselves once the map is on screen.
(() => {
  addEventListener('load', () => setTimeout(() => document.documentElement.classList.add('is-settled'), 50));
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const steps = [...document.querySelectorAll('[data-day-step]')];
  const list = document.querySelector('[data-day]');
  if (list && steps.length) {
    if (reduced.matches) steps.forEach(step => step.classList.add('is-on'));
    else {
      let queued = false;
      const update = () => {
        queued = false;
        // The reading line sits a little below the middle of the screen.
        const line = innerHeight * 0.62, box = list.getBoundingClientRect();
        list.style.setProperty('--day-p', Math.min(1, Math.max(0, (line - box.top) / box.height)).toFixed(4));
        steps.forEach(step => step.classList.toggle('is-on', step.getBoundingClientRect().top < line));
      };
      const queue = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };
      addEventListener('scroll', queue, { passive: true });
      addEventListener('resize', queue);
      update();
    }
  }

  const map = document.querySelector('[data-map]');
  if (map) {
    if (reduced.matches || !('IntersectionObserver' in window)) map.classList.add('is-drawn');
    else {
      const io = new IntersectionObserver(entries => {
        if (entries.some(e => e.isIntersecting)) { io.disconnect(); map.classList.add('is-drawn'); }
      }, { threshold: 0.35 });
      io.observe(map.querySelector('.map-card') || map);
    }
  }
})();
