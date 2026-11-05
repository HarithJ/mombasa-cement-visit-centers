// Photos section: prints are tossed onto the table when it scrolls into view, tilt toward the pointer when
// picked up, and fly from where they lie to the middle of the screen when clicked.
(() => {
  const table = document.querySelector('[data-scatter]');
  const viewer = document.querySelector('[data-print-viewer]');
  if (!table || !viewer) return;
  const prints = [...table.querySelectorAll('[data-print]')];
  const frame = viewer.querySelector('[data-viewer-frame]');
  const viewImg = viewer.querySelector('[data-viewer-img]');
  const caption = viewer.querySelector('[data-viewer-caption]');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const phone = matchMedia('(max-width: 760px)');
  const rand = (a, b) => a + Math.random() * (b - a);
  const paperOf = p => p.querySelector('.print-paper');
  const cssDeg = (p, name) => parseFloat(getComputedStyle(p).getPropertyValue(name)) || 0;
  const restAngle = p => cssDeg(p, '--r');
  const pileOrder = p => cssDeg(p, '--i');
  const REST_SHADOW = '0 1px 1px #0000001c, 0 3px 6px -2px #00000024, 0 16px 30px -14px #00000040';
  const NO_SHADOW = '0 1px 1px #00000000, 0 3px 6px -2px #00000000, 0 16px 30px -14px #00000000';

  // ---------- the drop ----------
  // The whole handful is let go together from above the table, close to the camera. Still bunched, they
  // fall faster and faster, fanning apart and fluttering as paper does, then land almost at once, bottom of
  // the pile first: each flattens on contact, skids a touch and stops. Shadows on the table sharpen as the
  // prints come down, and the ones underneath get knocked a little by those landing on them.
  function throwIn() {
    if (reduced.matches || !table.animate) { table.classList.add('is-thrown', 'is-landed'); return; }
    const vw = innerWidth, vh = innerHeight;
    const P = Math.max(vw, vh) * 1.4;                        // the table's perspective, in px
    table.style.perspective = `${P}px`;
    const T = table.getBoundingClientRect(), O = [T.left + T.width / 2, T.top + T.height / 2];
    const hand = [O[0] + rand(-0.03, 0.03) * T.width, O[1] - T.height * 0.08];  // where the bunch is held, on screen
    const plans = prints.map(p => {
      const box = p.getBoundingClientRect(), C = [box.left + box.width / 2, box.top + box.height / 2];
      const order = pileOrder(p);
      // Bottom of the pile was lowest in the hand, so it starts a little nearer the table.
      const Z = P * (0.5 + order / prints.length * 0.08 + rand(-0.02, 0.02)), k = P / (P - Z);
      // Seen from the camera, the prints start bunched around the hand; on the table they lie spread out.
      const S = [hand[0] + (C[0] - O[0]) * 0.28 + rand(-18, 18), hand[1] + (C[1] - O[1]) * 0.28 + rand(-18, 18)];
      const start = [O[0] + (S[0] - O[0]) / k - C[0], O[1] + (S[1] - O[1]) / k - C[1]];
      // A short skid outward from the middle of the pile, the way it was fanning as it fell.
      const out = [C[0] - O[0], C[1] - O[1]], ol = Math.hypot(out[0], out[1]) || 1, skid = order === prints.length - 1 ? rand(4, 8) : rand(8, 18);
      const contact = [-out[0] / ol * skid, -out[1] / ol * skid];
      return { p, C, w: box.width, r: restAngle(p), start, contact, Z, order,
        spin: rand(25, 70) * (Math.random() < 0.5 ? -1 : 1), flipX: rand(18, 38) * (Math.random() < 0.5 ? -1 : 1), flipY: rand(-22, 22) };
    }).sort((a, b) => a.order - b.order);

    // The shadows sit on the table, separate from the paper, so they can lag behind a print still in the air.
    const shadows = plans.map(({ p }) => {
      const sh = document.createElement('i');
      sh.className = 'print-shadow';
      sh.setAttribute('style', p.getAttribute('style'));
      sh.style.height = `${paperOf(p).offsetHeight}px`;
      p.before(sh);
      return sh;
    });

    table.classList.add('is-thrown');
    const landings = [];
    plans.forEach((plan, n) => {
      const { p, r, start, contact, Z, spin, flipX, flipY } = plan;
      const air = 600 + n * 14 + rand(0, 20), slide = rand(260, 340), duration = air + slide, land = air / duration;
      const timing = { duration, delay: rand(0, 40), fill: 'backwards' };
      const pose = (xy, z, rz, rx, ry) => `translate(-50%, -50%) translate3d(${xy[0].toFixed(1)}px, ${xy[1].toFixed(1)}px, ${z.toFixed(1)}px) rotate(${rz.toFixed(2)}deg) rotateX(${rx.toFixed(1)}deg) rotateY(${ry.toFixed(1)}deg)`;
      const at = f => [mix(start[0], contact[0], f), mix(start[1], contact[1], f)];
      // Gravity: height falls with the square of time; the paper drifts sideways more evenly.
      const fall = f => Z * (1 - f * f);
      const flight = p.animate([
        { transform: pose(start, Z, r + spin, flipX, flipY), filter: 'blur(3px)', opacity: 0, easing: 'linear' },
        { opacity: 1, offset: land * 0.12 },
        { transform: pose(at(0.4), fall(0.4), r + spin * 0.55, -flipX * 0.5, flipY * 0.6), filter: 'blur(1.5px)', offset: land * 0.4, easing: 'linear' },
        { transform: pose(at(0.75), fall(0.75), r + spin * 0.22, flipX * 0.25, -flipY * 0.3), filter: 'blur(.6px)', offset: land * 0.75, easing: 'linear' },
        // Contact: flat on the table.
        { transform: pose(contact, 0, r + spin * 0.04, 0, 0), filter: 'blur(0px)', offset: land, easing: 'cubic-bezier(.1,.7,.25,1)' },
        // Friction brings it to rest.
        { transform: pose([0, 0], 0, r, 0, 0), filter: 'blur(0px)', opacity: 1 },
      ], timing);
      const paperShadow = paperOf(p).animate([
        { boxShadow: NO_SHADOW }, { boxShadow: NO_SHADOW, offset: land * 0.9 }, { boxShadow: REST_SHADOW, offset: land }, { boxShadow: REST_SHADOW },
      ], timing);
      // Light from above and slightly in front: the higher the print, the further and softer its shadow falls.
      const sh = shadows[n], ground = (xy, z, rz, s, blur, o) => ({
        transform: `translate(-50%, -50%) translate(${xy[0].toFixed(1)}px, ${(xy[1] + z * 0.1).toFixed(1)}px) rotate(${rz.toFixed(2)}deg) scale(${s})`,
        filter: `blur(${blur}px)`, opacity: o, easing: 'linear' });
      const shadowAnim = sh.animate([
        ground(start, Z, r + spin, 0.85, 28, 0),
        { ...ground(at(0.4), fall(0.4), r + spin * 0.55, 0.9, 20, 0.07), offset: land * 0.4 },
        { ...ground(at(0.75), fall(0.75), r + spin * 0.22, 0.96, 10, 0.17), offset: land * 0.75 },
        { ...ground(contact, 0, r + spin * 0.04, 1, 4, 0.26), offset: land, easing: 'cubic-bezier(.1,.7,.25,1)' },
        ground([0, 0], 0, r, 1, 4, 0),
      ], timing);
      const done = () => { flight.cancel(); paperShadow.cancel(); shadowAnim.cancel(); sh.remove(); };
      flight.finished.then(done, done);
      landings.push(flight.finished.catch(() => {}));
      // On impact, prints already lying underneath get knocked a few pixels outward.
      setTimeout(() => {
        for (let m = 0; m < n; m++) {
          const under = plans[m], dx = under.C[0] - plan.C[0], dy = under.C[1] - plan.C[1], d = Math.hypot(dx, dy) || 1;
          const reach = (under.w + plan.w) * 0.5;
          if (d > reach) continue;
          const f = (1 - d / reach) * (n === plans.length - 1 ? rand(4, 7) : rand(1.5, 3.5));
          under.p.animate([{ transform: 'translate(0, 0)' }, { transform: `translate(${(dx / d * f).toFixed(1)}px, ${(dy / d * f).toFixed(1)}px) rotate(${rand(-0.6, 0.6).toFixed(2)}deg)` }],
            { duration: 240, easing: 'cubic-bezier(.2,.9,.3,1)', fill: 'forwards', composite: 'add' });
        }
      }, timing.delay + air);
    });
    // Once everything is down, the pencil note beside the pile draws itself in.
    Promise.all(landings).then(() => table.classList.add('is-landed'));
  }
  const mix = (a, b, t) => a + (b - a) * t;
  // Fetch the photographs well before the table is reached, so nothing lands as an empty frame.
  let ready = null;
  const prepare = () => ready || (ready = Promise.race([
    Promise.all(prints.map(p => { const img = p.querySelector('img'); img.loading = 'eager'; return img.decode ? img.decode().catch(() => {}) : null; })),
    new Promise(resolve => setTimeout(resolve, 2500)),
  ]));
  if ('IntersectionObserver' in window) {
    const near = new IntersectionObserver(entries => {
      if (entries.some(e => e.isIntersecting)) { near.disconnect(); prepare(); }
    }, { rootMargin: '120% 0px' });
    near.observe(table);
    const io = new IntersectionObserver(entries => {
      if (entries.some(e => e.isIntersecting)) { io.disconnect(); prepare().then(throwIn); }
    }, { threshold: 0.22 });
    io.observe(table);
  } else table.classList.add('is-thrown', 'is-landed');

  // ---------- picking a print up ----------
  prints.forEach(p => {
    p.addEventListener('pointermove', event => {
      if (event.pointerType !== 'mouse' || reduced.matches) return;
      const box = paperOf(p).getBoundingClientRect();
      const u = Math.min(1, Math.max(0, (event.clientX - box.left) / box.width));
      const v = Math.min(1, Math.max(0, (event.clientY - box.top) / box.height));
      p.style.setProperty('--ty', `${((u - 0.5) * 9).toFixed(2)}deg`);
      p.style.setProperty('--tx', `${((0.5 - v) * 7).toFixed(2)}deg`);
      p.style.setProperty('--px', `${(u * 100).toFixed(1)}%`);
      p.style.setProperty('--py', `${(v * 100).toFixed(1)}%`);
    });
    p.addEventListener('pointerleave', () => { for (const k of ['--tx', '--ty', '--px', '--py']) p.style.removeProperty(k); });
    p.addEventListener('click', event => {
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.button) return;
      event.preventDefault();
      open(prints.indexOf(p));
    });
  });

  // ---------- the viewer ----------
  let current = -1, busy = false;
  function layout(p) {
    const img = p.querySelector('img');
    const aspect = (img.naturalWidth || img.width) / (img.naturalHeight || img.height) || 16 / 9;
    const vw = innerWidth, vh = innerHeight, pad = Math.min(14, Math.max(8, vw * 0.008));
    const maxW = Math.min(vw - (phone.matches ? 24 : 200), 1500), maxH = vh - (phone.matches ? 190 : 150);
    const iw = Math.min(maxW - 2 * pad, (maxH - 2 * pad) * aspect), ih = iw / aspect;
    const w = iw + 2 * pad, h = ih + 2 * pad;
    return { left: (vw - w) / 2, top: (vh - h) / 2 - (phone.matches ? 24 : 12), w, h, aspect };
  }
  function place(p) {
    const L = layout(p);
    Object.assign(frame.style, { left: `${L.left}px`, top: `${L.top}px`, width: `${L.w}px` });
    viewImg.style.aspectRatio = String(L.aspect);
    return L;
  }
  // The transform that puts the viewer frame exactly over a print on the table.
  function overPrint(p, L, lifted) {
    const paper = paperOf(p), box = paper.getBoundingClientRect();
    const r = restAngle(p) + (lifted ? cssDeg(p, '--hr') : 0), s = paper.offsetWidth * (lifted ? 1.045 : 1) / L.w;
    const dx = box.left + box.width / 2 - (L.left + L.w / 2), dy = box.top + box.height / 2 - (L.top + L.h / 2);
    return `translate(${dx.toFixed(1)}px, ${dy.toFixed(1)}px) rotate(${r.toFixed(2)}deg) scale(${s.toFixed(4)})`;
  }
  function show(i) {
    const p = prints[i], thumb = p.querySelector('img');
    current = i;
    viewImg.src = thumb.currentSrc || thumb.src;
    viewImg.alt = thumb.alt;
    caption.textContent = p.dataset.caption || '';
    prints.forEach((q, k) => q.classList.toggle('is-away', k === i));
    // Swap in the full-size photograph once it has loaded.
    const full = new Image();
    full.src = p.dataset.full;
    (full.decode ? full.decode() : Promise.resolve()).then(() => { if (current === i) viewImg.src = full.src; }, () => {});
  }
  function open(i) {
    if (busy || viewer.open) return;
    const p = prints[i];
    const lifted = p.matches(':hover, :focus-visible');
    show(i);
    const L = place(p);
    viewer.showModal();
    requestAnimationFrame(() => viewer.classList.add('is-open'));
    if (reduced.matches) { frame.animate([{ opacity: 0 }, { opacity: 1 }], 200); viewer.classList.add('is-settled'); return; }
    busy = true;
    const anim = frame.animate([{ transform: overPrint(p, L, lifted) }, { transform: 'none' }], { duration: 640, easing: 'cubic-bezier(.2,.8,.2,1)' });
    anim.finished.then(() => { busy = false; viewer.classList.add('is-settled'); }, () => { busy = false; });
  }
  function close() {
    if (busy || !viewer.open) return;
    const p = prints[current];
    viewer.classList.remove('is-settled', 'is-open');
    const finish = () => { p.classList.remove('is-away'); viewer.close(); busy = false; p.focus({ preventScroll: true }); };
    if (reduced.matches) { finish(); return; }
    busy = true;
    const L = layout(p);
    const anim = frame.animate([{ transform: 'none' }, { transform: overPrint(p, L, false) }], { duration: 480, easing: 'cubic-bezier(.45,0,.2,1)', fill: 'forwards' });
    anim.finished.then(() => { finish(); anim.cancel(); }, finish);
  }
  function step(dir) {
    if (busy || !viewer.open) return;
    const next = (current + dir + prints.length) % prints.length;
    if (reduced.matches) { show(next); place(prints[next]); return; }
    busy = true;
    const out = frame.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: `translateX(${-dir * 40}px)` }], { duration: 180, easing: 'ease-in', fill: 'forwards' });
    out.finished.then(() => {
      show(next); place(prints[next]);
      out.cancel();
      const inn = frame.animate([{ opacity: 0, transform: `translateX(${dir * 40}px)` }, { opacity: 1, transform: 'none' }], { duration: 320, easing: 'cubic-bezier(.2,.8,.2,1)' });
      inn.finished.then(() => { busy = false; }, () => { busy = false; });
    }, () => { busy = false; });
  }

  viewer.querySelectorAll('[data-viewer-close]').forEach(el => el.addEventListener('click', close));
  viewer.querySelectorAll('[data-viewer-step]').forEach(el => el.addEventListener('click', () => step(Number(el.dataset.viewerStep))));
  viewer.addEventListener('cancel', event => { event.preventDefault(); close(); });
  viewer.addEventListener('keydown', event => {
    if (event.key === 'ArrowRight') { event.preventDefault(); step(1); }
    if (event.key === 'ArrowLeft') { event.preventDefault(); step(-1); }
  });
  // Keep the page still behind the viewer without hiding the scrollbar (which would shift the table).
  const hold = event => { if (viewer.open) event.preventDefault(); };
  viewer.addEventListener('wheel', hold, { passive: false });
  viewer.addEventListener('touchmove', hold, { passive: false });
  addEventListener('resize', () => { if (viewer.open && !busy) place(prints[current]); });
})();
