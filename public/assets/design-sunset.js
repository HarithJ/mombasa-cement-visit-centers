'use strict';
// Sunset design: switch the full-screen backdrop to whichever destination card is in the middle of the screen.
const story = document.querySelector('.ss-story');
if (story && 'IntersectionObserver' in window) {
  const layers = story.querySelectorAll('[data-backdrop]');
  const dots = story.querySelectorAll('[data-dot]');
  const activate = id => {
    layers.forEach(layer => layer.classList.toggle('is-active', layer.dataset.backdrop === id));
    dots.forEach(dot => dot.classList.toggle('is-active', dot.dataset.dot === id));
  };
  const observer = new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) activate(entry.target.dataset.destination);
  }), { rootMargin: '-45% 0px -45% 0px' });
  story.querySelectorAll('.ss-card').forEach(card => observer.observe(card));
}
