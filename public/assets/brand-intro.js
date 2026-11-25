'use strict';
// Shows the brand film, with sound, on every page load before the page can be used.
// Every visitor watches it to the end (a reload starts it again); it then fades into the page.
// It only lets the visitor in early if the film fails to play.
// Loaded synchronously in <head> so the page never flashes before the film covers it.
(() => {
  const root = document.documentElement;
  root.classList.add('brand-intro-active');
  document.addEventListener('DOMContentLoaded', () => {
    const intro = document.querySelector('#brand-intro');
    if (!intro) { root.classList.remove('brand-intro-active'); return; }
    const video = intro.querySelector('video');
    const play = intro.querySelector('.brand-intro-play');
    const bar = intro.querySelector('.brand-intro-bar span');
    const behind = [...document.body.children].filter(element => element !== intro);
    let finished = false;
    const finish = fade => {
      if (finished) return;
      finished = true;
      clearInterval(watchdog);
      behind.forEach(element => { element.inert = false; });
      root.classList.remove('brand-intro-active');
      const remove = () => { video.pause(); video.removeAttribute('src'); video.load(); intro.remove(); };
      if (!fade) { remove(); return; }
      // Fade the film out to reveal the page underneath, then remove it.
      intro.classList.add('is-leaving');
      setTimeout(remove, matchMedia('(prefers-reduced-motion: reduce)').matches ? 250 : 1100);
    };
    const start = () => video.play().then(() => { play.hidden = true; }, () => { play.hidden = false; play.focus(); });
    behind.forEach(element => { element.inert = true; });
    intro.hidden = false;
    // Download straight away, so the film is ready (or fails fast) even while waiting for a tap to play.
    video.preload = 'auto';
    video.src = video.dataset.src;
    video.addEventListener('ended', () => finish(true));
    // Never trap a visitor behind a film that cannot load.
    video.addEventListener('error', () => finish(false));
    video.addEventListener('timeupdate', () => {
      if (video.duration) bar.style.width = `${Math.min(100, video.currentTime / video.duration * 100)}%`;
    });
    // The film cannot be paused: resume whenever the browser pauses it (for example after switching tabs).
    video.addEventListener('pause', () => { if (!finished && !video.ended && play.hidden && document.visibilityState === 'visible') start(); });
    document.addEventListener('visibilitychange', () => { if (!finished && play.hidden && document.visibilityState === 'visible' && video.paused) start(); });
    // The film plays with sound. Browsers often refuse to start video with sound before the visitor
    // has interacted with the site; then the film waits for a tap on "Play the film", which plays it with sound.
    play.addEventListener('click', start);
    // If playback makes no progress for 20 seconds (a stalled download), let the visitor in.
    let last = -1, stalled = 0;
    const watchdog = setInterval(() => {
      if (!play.hidden || document.visibilityState !== 'visible') { stalled = 0; return; }
      stalled = video.currentTime === last ? stalled + 1 : 0;
      last = video.currentTime;
      if (stalled >= 20) finish(false);
    }, 1000);
    start();
  });
})();
