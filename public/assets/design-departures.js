'use strict';
// Departures design: live Nairobi clock and a split-flap settle animation on the board.
const clock = document.querySelector('#dep-clock');
if (clock) {
  const format = new Intl.DateTimeFormat('en-GB', { timeZone: 'Africa/Nairobi', hour: '2-digit', minute: '2-digit' });
  const tick = () => { clock.textContent = format.format(new Date()); };
  tick();
  setInterval(tick, 15000);
}
if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  document.querySelectorAll('.dep-board .flap').forEach((flap, index) => {
    const target = flap.dataset.char;
    let turns = 6 + (index % 9);
    const flip = () => {
      if (turns-- <= 0) { flap.textContent = target; flap.classList.remove('flipping'); return; }
      flap.textContent = alphabet[Math.floor(Math.random() * alphabet.length)];
      flap.classList.remove('flipping'); void flap.offsetWidth; flap.classList.add('flipping');
      setTimeout(flip, 70);
    };
    setTimeout(flip, 200 + index * 12);
  });
}
