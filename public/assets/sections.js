// Your day: a pencilled trail draws itself across the prints as you scroll (or swipe), and each step settles in as
// the trail reaches it.
// Getting there: the routes draw themselves once the map is on screen.
(() => {
  addEventListener('load', () => setTimeout(() => document.documentElement.classList.add('is-settled'), 50));
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  // ---------- Your day ----------
  const day = document.querySelector('[data-day]');
  const row = day?.querySelector('.day-row');
  const steps = day ? [...day.querySelectorAll('[data-day-step]')] : [];
  if (day && row && steps.length) {
    const svg = day.querySelector('[data-day-trail]');
    const dots = day.querySelector('[data-day-trail-dots]');
    const reveal = day.querySelector('[data-day-trail-reveal]');
    let marks = [], reached = 0;

    // The trail, through points just above each print and through the gaps between them: it dips into some gaps,
    // rises over the middle of the row, runs under "(optional)" and trails off past the last print.
    const lift = [[-14, -14, 12], [-14, -14, 4], [-18, -22, -25], [-22, -14, 18], [-12, 14, null]];
    function build() {
      // Where each print sits in the row's layout (offsets ignore the tilt and the settling-in motion).
      const prints = steps.map(step => {
        const f = step.querySelector('[data-day-print]');
        return { l: f.offsetLeft, t: f.offsetTop, r: f.offsetLeft + f.offsetWidth, w: f.offsetWidth };
      });
      const pts = [], at = [];
      prints.forEach((p, i) => {
        const [over, out, gap] = lift[i];
        at.push(pts.length);
        pts.push([p.l + p.w * (i ? 0.1 : 0.45), p.t + over], [p.r - p.w * 0.12, p.t + out]);
        if (gap !== null) { const q = prints[i + 1]; pts.push([(p.r + q.l) / 2, (p.t + q.t) / 2 + gap]); }
        else pts.push([p.r - p.w * 0.04, p.t - 2], [p.r + Math.min(30, p.w * 0.12), p.t - 46]);
      });
      // Catmull-Rom through the points, as cubic Béziers.
      let d = `M${pts[0][0].toFixed(1)} ${pts[0][1].toFixed(1)}`;
      const lengths = [0];
      const probe = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      svg.append(probe);
      for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2;
        const c1 = [p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6], c2 = [p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6];
        d += ` C${c1[0].toFixed(1)} ${c1[1].toFixed(1)}, ${c2[0].toFixed(1)} ${c2[1].toFixed(1)}, ${p2[0].toFixed(1)} ${p2[1].toFixed(1)}`;
        probe.setAttribute('d', d); lengths.push(probe.getTotalLength());
      }
      probe.remove();
      const total = lengths.at(-1) || 1;
      marks = at.map(i => lengths[i] / total);       // how far along the trail each print begins
      const list = day.querySelector('[data-day-steps]');
      svg.setAttribute('width', list.scrollWidth); svg.setAttribute('height', list.offsetHeight);
      dots.setAttribute('d', d); reveal.setAttribute('d', d);
    }

    // How much of the trail is drawn: the row coming up the screen; on a strip, also how far along it you've swiped.
    // It never undraws.
    let queued = false;
    function update() {
      queued = false;
      const r = row.getBoundingClientRect(), vh = innerHeight;
      if (r.top < vh * 0.85) day.classList.add('is-in');
      let p = Math.min(1, Math.max(0, (vh * 0.85 - r.top) / (vh * 0.5)));
      if (row.scrollWidth > row.clientWidth + 4) {
        p = Math.min(p, (row.scrollLeft + row.clientWidth * 0.8) / row.scrollWidth + 0.04);
        day.classList.toggle('is-swiped', row.scrollLeft + row.clientWidth >= row.scrollWidth - 24);
      }
      reached = Math.max(reached, p);
      svg.style.setProperty('--trail', (1 - reached).toFixed(4));
      steps.forEach((step, i) => step.classList.toggle('is-on', reached > 0 && reached + 0.02 >= marks[i]));
    }
    const queue = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };

    // With reduced motion, everything is simply there: the whole trail, every step.
    if (reduced.matches) { reached = 1; day.classList.add('is-in'); }
    const draw = () => { build(); update(); };
    draw();
    document.fonts?.ready.then(draw);
    addEventListener('resize', () => requestAnimationFrame(draw));
    if (!reduced.matches) {
      addEventListener('scroll', queue, { passive: true });
      row.addEventListener('scroll', queue, { passive: true });
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

  // The ticket slides in and gets stamped once it is properly in view.
  const pass = document.querySelector('[data-pass]');
  if (pass) {
    if (reduced.matches || !('IntersectionObserver' in window)) pass.classList.add('is-in');
    else {
      const io = new IntersectionObserver(entries => {
        if (entries.some(e => e.isIntersecting)) { io.disconnect(); pass.classList.add('is-in'); }
      }, { threshold: 0.6 });
      io.observe(pass);
    }
  }
})();
