'use strict';
// Shows the brand film on every page load, before the page can be used.
// A first-time visitor watches it to the end and then closes it; a visitor who has watched it before
// (remembered in this browser) can close it straight away. It never closes by itself unless it fails to play.
// Loaded synchronously in <head> so the page never flashes before the film covers it.
(() => {
  const watchedKey = 'nyumba-brand-intro-watched';
  const root = document.documentElement;
  root.classList.add('brand-intro-active');
  document.addEventListener('DOMContentLoaded', () => {
    const intro = document.querySelector('#brand-intro');
    if (!intro) { root.classList.remove('brand-intro-active'); return; }
    const video = intro.querySelector('video');
    const sound = intro.querySelector('.brand-intro-sound');
    const play = intro.querySelector('.brand-intro-play');
    const bar = intro.querySelector('.brand-intro-bar span');
    const time = intro.querySelector('.brand-intro-time');
    const close = intro.querySelector('.brand-intro-close');
    const behind = [...document.body.children].filter(element => element !== intro);
    let finished = false;
    const allowClose = () => { close.hidden = false; };
    const finish = () => {
      if (finished) return;
      finished = true;
      clearInterval(watchdog);
      video.pause(); video.removeAttribute('src'); video.load();
      intro.remove();
      behind.forEach(element => { element.inert = false; });
      root.classList.remove('brand-intro-active');
    };
    const start = () => video.play().then(() => { play.hidden = true; }, () => { play.hidden = false; play.focus(); });
    behind.forEach(element => { element.inert = true; });
    intro.hidden = false;
    // Download straight away, so the film is ready (or fails fast) even while waiting for a tap to play.
    video.preload = 'auto';
    video.src = video.dataset.src;
    let returning = false;
    try { returning = localStorage.getItem(watchedKey) === '1'; } catch {}
    if (returning) allowClose();
    video.addEventListener('ended', () => {
      clearInterval(watchdog);
      try { localStorage.setItem(watchedKey, '1'); } catch {}
      allowClose();
      close.focus();
    });
    close.addEventListener('click', finish);
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !close.hidden) finish(); });
    // Never trap a visitor behind a film that cannot load.
    video.addEventListener('error', finish);
    video.addEventListener('timeupdate', () => {
      if (!video.duration) return;
      bar.style.width = `${Math.min(100, video.currentTime / video.duration * 100)}%`;
      const left = Math.max(0, Math.ceil(video.duration - video.currentTime));
      time.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
    });
    // The film cannot be paused: resume whenever the browser pauses it (for example after switching tabs).
    video.addEventListener('pause', () => { if (!finished && !video.ended && play.hidden && document.visibilityState === 'visible') start(); });
    document.addEventListener('visibilitychange', () => { if (!finished && play.hidden && document.visibilityState === 'visible' && video.paused) start(); });
    // The film starts muted, because browsers only autoplay muted video on a first visit; the visitor can unmute it.
    // If a browser refuses even that (for example iPhone Low Power Mode), the film waits for a tap on "Play the film".
    sound.addEventListener('click', () => {
      video.muted = !video.muted;
      sound.setAttribute('aria-pressed', String(video.muted));
      sound.textContent = video.muted ? 'Unmute' : 'Mute';
    });
    play.addEventListener('click', start);
    // If playback makes no progress for 20 seconds (a stalled download), let the visitor in.
    let last = -1, stalled = 0;
    const watchdog = setInterval(() => {
      if (!play.hidden || document.visibilityState !== 'visible') { stalled = 0; return; }
      stalled = video.currentTime === last ? stalled + 1 : 0;
      last = video.currentTime;
      if (stalled >= 20) finish();
    }, 1000);
    start();
    sound.focus();
  });
})();
