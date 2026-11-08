// Galana hero: the onion flower photograph rendered in WebGL with a gentle sway, white fluff that
// drifts off the flower under the pointer, and a paper wasp whose take-off follows the scroll.
// Everything is placed in "photo space": the onion photograph scaled to 2000 units wide.
(() => {
  const hero = document.querySelector('[data-hero]');
  if (!hero) return;
  const stage = hero.querySelector('[data-hero-stage]');
  const canvas = hero.querySelector('[data-hero-canvas]');
  const still = hero.querySelector('[data-hero-still]');
  const copy = hero.querySelector('.hero-copy');
  const header = document.querySelector('[data-site-header]');
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');

  // Photo-space landmarks, measured from the original photograph.
  const PHOTO = [2000, 1333.22];
  const FLOWER = [902, 742];
  const FLOWER_R = 462;
  const STEM_PIVOT = [1650, 1900];
  const WASP_RECT = [888.8, 206.39, 1148.47, 419.44];
  // The original pixels of every part of the plate that was rebuilt, shown until the wasp moves.
  const REST_RECT = [877.83, 195.41, 1155.79, 445.07];
  const WASP_SIZE = [WASP_RECT[2] - WASP_RECT[0], WASP_RECT[3] - WASP_RECT[1]];
  const WASP_HINGE_LOCAL = [0.3564, 0.5703];
  const WASP_HINGE = [WASP_RECT[0] + WASP_HINGE_LOCAL[0] * WASP_SIZE[0], WASP_RECT[1] + WASP_HINGE_LOCAL[1] * WASP_SIZE[1]];
  const BASE = [0.086, 0.188, 0.239]; // --c-dark, #16303d
  // The blurred background to the flower's right, as the wasp sees it from far off.
  const HAZE = [0.17, 0.18, 0.22];

  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const smoothstep = (a, b, v) => { const t = clamp((v - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); };
  const mix = (a, b, t) => a + (b - a) * t;
  const wobble = (t, s) => Math.sin(t * 1.7 + s) * 0.5 + Math.sin(t * 2.9 + s * 2.1) * 0.3 + Math.sin(t * 5.3 + s * 3.7) * 0.2;

  let view = { w: 1, h: 1, dpr: 1 };
  let place = layout(1, 1);

  function layout(w, h) {
    const portrait = w < 760 || w / h < 0.9;
    let R, cx, cy;
    if (portrait) { R = Math.min(0.42 * w, 0.22 * h); cx = 0.6 * w; cy = Math.max(0.32 * h, R + 118); }
    else { R = Math.min(0.36 * h, 0.26 * w); cx = 0.69 * w; cy = 0.56 * h; }
    return { portrait, cx, cy, s: R / FLOWER_R };
  }

  function placeStill() {
    const { cx, cy, s } = place;
    still.style.width = `${PHOTO[0] * s}px`;
    still.style.left = `${cx - FLOWER[0] * s}px`;
    still.style.top = `${cy - FLOWER[1] * s}px`;
    still.classList.add('is-placed');
  }

  // ---------- scroll ----------
  // The hero scrolls away like any other content. Side by side with the copy, the scene inside it drifts up
  // at half speed, so the flower stays in view long enough for the wasp's whole flight. On a phone the copy
  // sits under the flower and would slide over it, so there the scene scrolls normally and the flight is
  // played over a shorter stretch of the scroll instead.
  const flightRun = () => place.portrait ? 0.2 : 0.4;
  const sceneLag = () => place.portrait ? 0 : 0.5;
  let lastShift = 0; // how far the scene has been moved down to lag the page, in px
  const scrolled = () => clamp(-hero.getBoundingClientRect().top, 0, stage.offsetHeight);
  const readProgress = () => clamp(scrolled() / (stage.offsetHeight * flightRun()), 0, 1);
  function updateHeader() {
    if (!header) return;
    // Solid as soon as the next section reaches the header.
    const next = hero.nextElementSibling || hero;
    header.classList.toggle('is-solid', next.getBoundingClientRect().top <= header.offsetHeight);
    // Dark glass once the dark booking band (and the footer after it) reaches the middle of the header.
    header.classList.toggle('is-dark', !!dark && dark.getBoundingClientRect().top <= header.offsetHeight / 2);
  }
  const dark = document.querySelector('.book');
  addEventListener('scroll', updateHeader, { passive: true });
  updateHeader();

  function measure() {
    view = { w: stage.clientWidth, h: stage.clientHeight, dpr: Math.min(devicePixelRatio || 1, 2) };
    place = layout(view.w, view.h);
    place.cy0 = place.cy;
    place.cy += lastShift;
    placeStill();
  }
  measure();

  // Without WebGL, or with reduced motion, the still photograph stands in for the scene.
  let gl = null;
  const stillMode = () => addEventListener('resize', measure);
  if (reducedMotion.matches) { stillMode(); return; }
  gl = canvas.getContext('webgl2', { alpha: false, antialias: false, premultipliedAlpha: true, powerPreference: 'high-performance' });
  if (!gl) { stillMode(); return; }

  // ---------- shaders ----------
  const COMMON = `#version 300 es
precision highp float;
uniform vec2 uView; uniform float uDpr; uniform vec2 uCenter; uniform float uScale;
uniform vec3 uBase; uniform float uPortrait; uniform float uTime;
const vec2 F = vec2(${FLOWER[0].toFixed(1)}, ${FLOWER[1].toFixed(1)});
vec2 cssPx() { return vec2(gl_FragCoord.x, uView.y * uDpr - gl_FragCoord.y) / uDpr; }
float hash(vec2 p) { p = fract(p * vec2(443.897, 441.423)); p += dot(p, p.yx + 19.19); return fract((p.x + p.y) * p.x); }
vec3 grade(vec3 c) {
  c = pow(max(c, 0.0), vec3(1.07));
  float l = dot(c, vec3(0.2126, 0.7152, 0.0722));
  return mix(vec3(l), c, 0.9) * vec3(0.985, 1.0, 1.025);
}
vec3 post(vec3 c, vec2 px, float shade) {
  vec2 n = px / uView;
  float side = mix(smoothstep(0.52, 0.0, n.x) * 0.84, smoothstep(0.42, 0.9, n.y) * 0.92, uPortrait);
  c = mix(c, uBase, side * shade);
  vec2 vc = (n - mix(vec2(0.66, 0.5), vec2(0.6, 0.32), uPortrait)) * vec2(uView.x / uView.y, 1.0);
  c = mix(uBase * 0.6, c, mix(0.55, 1.0, smoothstep(1.35, 0.25, length(vc))));
  return c + (hash(gl_FragCoord.xy + fract(uTime * 7.0) * 91.0) - 0.5) * 0.026;
}
`;
  const FULLSCREEN_VS = `#version 300 es
in vec2 aPos; void main() { gl_Position = vec4(aPos, 0.0, 1.0); }`;

  const PLATE_FS = COMMON + `
uniform sampler2D uPlate; uniform sampler2D uRest;
uniform float uTheta; uniform vec2 uPivot; uniform vec2 uParFg; uniform vec2 uParBg;
uniform float uRestMix; uniform vec4 uRestRect;
const vec2 PS = vec2(${PHOTO[0].toFixed(1)}, ${PHOTO[1].toFixed(2)});
out vec4 outColor;
vec2 rot(vec2 v, float a) { float c = cos(a), s = sin(a); return vec2(c * v.x - s * v.y, s * v.x + c * v.y); }
float foreground(vec2 q) {
  float wf = 1.0 - smoothstep(480.0, 720.0, length(q - F));
  vec2 a = vec2(1080.0, 1020.0), b = vec2(1760.0, 1800.0), pa = q - a, ba = b - a;
  float h = clamp(dot(pa, ba) / dot(ba, ba), 0.0, 1.0);
  return max(wf, 1.0 - smoothstep(80.0, 220.0, length(pa - ba * h)));
}
void main() {
  vec2 px = cssPx();
  vec2 q = (px - uCenter) / uScale + F;
  float w = foreground(q);
  vec2 qf = rot(q - uPivot - uParFg / uScale, -uTheta) + uPivot;
  vec2 src = mix(q - uParBg / uScale, qf, w);
  vec2 uv = src / PS;
  float outside = max(max(-uv.x, uv.x - 1.0), max(-uv.y, uv.y - 1.0));
  vec3 col = texture(uPlate, clamp(uv, vec2(0.0006), vec2(0.9994)), clamp(outside * 30.0, 0.0, 7.0)).rgb;
  col = mix(col, uBase * 1.1, smoothstep(0.0, 0.12, outside));
  vec2 r = (src - uRestRect.xy) / (uRestRect.zw - uRestRect.xy);
  // Sampled outside any branch so mip selection stays valid along the patch edges.
  vec4 rc = texture(uRest, clamp(r, 0.0, 1.0));
  float inside = step(0.0, r.x) * step(r.x, 1.0) * step(0.0, r.y) * step(r.y, 1.0);
  col = mix(col, rc.rgb, rc.a * uRestMix * inside);
  outColor = vec4(post(grade(col), px, 1.0), 1.0);
}`;

  const SPRITE_VS = `#version 300 es
in vec2 aPos;
uniform vec2 uView; uniform vec2 uCenter; uniform float uScale;
uniform vec2 uHinge; uniform vec2 uSize; uniform vec2 uPivotL; uniform float uRot; uniform vec2 uSquash; uniform vec2 uOffset;
const vec2 F = vec2(${FLOWER[0].toFixed(1)}, ${FLOWER[1].toFixed(1)});
const float PAD = 0.1;
out vec2 vUv;
void main() {
  vec2 corner = mix(vec2(-PAD), vec2(1.0 + PAD), aPos * 0.5 + 0.5);
  vec2 l = (corner - uPivotL) * uSize * uSquash;
  float c = cos(uRot), s = sin(uRot);
  vec2 p = uHinge + vec2(c * l.x - s * l.y, s * l.x + c * l.y);
  vec2 px = (p - F) * uScale + uCenter + uOffset;
  vec2 clip = px / uView * 2.0 - 1.0;
  gl_Position = vec4(clip.x, -clip.y, 0.0, 1.0);
  vUv = corner;
}`;
  const SPRITE_FS = COMMON + `
uniform sampler2D uTex; uniform float uBlur; uniform float uAlpha; uniform vec2 uTexel; uniform float uShade;
uniform float uHaze; uniform vec3 uHazeColor;
in vec2 vUv; out vec4 outColor;
const vec2 DISC[12] = vec2[12](vec2(-0.326,-0.406), vec2(-0.840,-0.074), vec2(-0.696,0.457), vec2(-0.203,0.621),
  vec2(0.962,-0.195), vec2(0.473,-0.480), vec2(0.519,0.767), vec2(0.185,-0.893), vec2(0.507,0.064),
  vec2(0.896,0.412), vec2(-0.322,-0.933), vec2(-0.792,-0.598));
vec4 tap(vec2 uv, float bias) {
  vec4 c = texture(uTex, uv, bias);
  c.a *= step(0.0, uv.x) * step(uv.x, 1.0) * step(0.0, uv.y) * step(uv.y, 1.0);
  return vec4(c.rgb * c.a, c.a);
}
void main() {
  vec4 acc;
  if (uBlur > 0.05) {
    float bias = log2(1.0 + uBlur) * 0.7;
    acc = tap(vUv, bias);
    for (int i = 0; i < 12; i++) acc += tap(vUv + DISC[i] * uTexel * uBlur, bias);
    acc /= 13.0;
  } else acc = tap(vUv, 0.0);
  if (acc.a < 0.002) discard;
  vec3 rgb = post(grade(acc.rgb / acc.a), cssPx(), uShade);
  // Far off, it takes on the colour of the out-of-focus background it is flying into.
  rgb = mix(rgb, uHazeColor, uHaze);
  float a = acc.a * uAlpha;
  outColor = vec4(rgb * a, a);
}`;

  // ---------- fur atlas ----------
  // Each cell is one tuft drawn hair by hair: tapered, slightly curling strands, so a drifting particle
  // reads as a loose bit of down rather than a line. Round puffs, directional locks and sparse wisps.
  const FUR_COLS = 4, FUR_ROWS = 2, FUR_CELL = 192;
  function furAtlas() {
    const cvs = document.createElement('canvas');
    cvs.width = FUR_COLS * FUR_CELL; cvs.height = FUR_ROWS * FUR_CELL;
    const g = cvs.getContext('2d');
    let seed = 7;
    const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
    const kinds = ['puff', 'lock', 'puff', 'wisp', 'lock', 'puff', 'wisp', 'lock'];
    kinds.forEach((kind, i) => {
      const ox = (i % FUR_COLS) * FUR_CELL + FUR_CELL / 2, oy = Math.floor(i / FUR_COLS) * FUR_CELL + FUR_CELL / 2;
      const C = FUR_CELL, maxR = C * 0.44;
      g.save(); g.beginPath(); g.rect(ox - C / 2, oy - C / 2, C, C); g.clip();
      // A faint scatter of light through the whole tuft, and a denser root.
      const halo = g.createRadialGradient(ox, oy, 0, ox, oy, maxR * (kind === 'wisp' ? 0.9 : 0.7));
      halo.addColorStop(0, `rgba(255,255,255,${kind === 'wisp' ? 0.1 : 0.24})`); halo.addColorStop(0.5, `rgba(255,255,255,${kind === 'wisp' ? 0.05 : 0.1})`); halo.addColorStop(1, 'rgba(255,255,255,0)');
      g.fillStyle = halo; g.fillRect(ox - C / 2, oy - C / 2, C, C);
      const hairs = kind === 'puff' ? 260 : kind === 'lock' ? 200 : 110;
      const heading = rnd() * Math.PI * 2;
      for (let h = 0; h < hairs; h++) {
        let x, y, dir, len;
        if (kind === 'lock') {
          // Hairs share a root and fan out the same way, like a lock pulled from a coat.
          const spread = (rnd() - 0.5) * 1.5 * (0.4 + rnd() * 0.6);
          dir = heading + spread;
          const across = (rnd() - 0.5) * C * 0.22, back = maxR * (0.5 + rnd() * 0.2);
          x = ox - Math.cos(heading) * back - Math.sin(heading) * across;
          y = oy - Math.sin(heading) * back + Math.cos(heading) * across;
          len = maxR * (0.9 + rnd() * 0.75) * (1 - Math.abs(spread) * 0.3);
        } else {
          // Hairs start anywhere in the body of the tuft and cross one another, not one starburst.
          const a = rnd() * Math.PI * 2, r0 = Math.sqrt(rnd()) * maxR * (kind === 'wisp' ? 0.45 : 0.4);
          x = ox + Math.cos(a) * r0; y = oy + Math.sin(a) * r0;
          dir = a + (rnd() - 0.5) * (kind === 'wisp' ? 2.4 : 2.0);
          len = maxR * (kind === 'wisp' ? 0.7 + rnd() * 0.6 : 0.55 + rnd() * 0.6);
        }
        const steps = 18, step = len / steps;
        let curl = (rnd() - 0.5) * (kind === 'wisp' ? 0.3 : 0.2);
        const wave = 0.05 + rnd() * 0.09, wavePhase = rnd() * 6.28, waveRate = 0.6 + rnd() * 0.8;
        const pts = [[x, y]];
        for (let s = 0; s < steps; s++) {
          curl += (rnd() - 0.5) * 0.05; dir += curl + wave * Math.sin(s * waveRate + wavePhase);
          x += Math.cos(dir) * step; y += Math.sin(dir) * step;
          const dx = x - ox, dy = y - oy, d = Math.hypot(dx, dy);
          if (d > maxR) { dir += Math.PI * 0.5 * Math.sign(curl || 1); x = ox + dx / d * maxR; y = oy + dy / d * maxR; }
          pts.push([x, y]);
        }
        // Tapered ribbon: full width at the root, a hair's breadth at the tip.
        const w0 = (kind === 'wisp' ? 0.9 : 1.1) + rnd() * 1.3;
        const left = [], right = [];
        pts.forEach((p, k) => {
          const q = pts[Math.min(k + 1, pts.length - 1)], r = pts[Math.max(k - 1, 0)];
          let tx = q[0] - r[0], ty = q[1] - r[1]; const tl = Math.hypot(tx, ty) || 1; tx /= tl; ty /= tl;
          const w = w0 * 0.5 * (1 - k / pts.length * 0.8);
          left.push([p[0] - ty * w, p[1] + tx * w]); right.push([p[0] + ty * w, p[1] - tx * w]);
        });
        const end = pts[pts.length - 1], alpha = 0.12 + rnd() * 0.26;
        const grad = g.createLinearGradient(pts[0][0], pts[0][1], end[0], end[1]);
        grad.addColorStop(0, `rgba(255,255,255,${alpha * (kind === 'lock' ? 0.35 : 0.6)})`);
        grad.addColorStop(0.25, `rgba(255,255,255,${alpha})`);
        grad.addColorStop(0.7, `rgba(255,255,255,${alpha * 0.6})`);
        grad.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = grad; g.beginPath();
        left.forEach((p, k) => k ? g.lineTo(p[0], p[1]) : g.moveTo(p[0], p[1]));
        for (let k = right.length - 1; k >= 0; k--) g.lineTo(right[k][0], right[k][1]);
        g.closePath(); g.fill();
      }
      g.restore();
    });
    // Alpha carries the coverage; red carries how dense the hair is there, for the shader's sheen.
    const img = g.getImageData(0, 0, cvs.width, cvs.height), d = img.data;
    for (let k = 0; k < d.length; k += 4) { const a = d[k + 3] / 255; d[k] = d[k + 1] = d[k + 2] = Math.round(255 * Math.min(1, a * 1.6)); }
    return img; // ImageData uploads unpremultiplied, so the density channel survives in thin hairs.
  }

  const FLUFF_VS = `#version 300 es
in vec2 aPos; in vec2 aLocal; in vec4 aLook;
uniform vec2 uView;
out vec2 vL; out vec4 vLook;
void main() {
  vec2 clip = aPos / uView * 2.0 - 1.0;
  gl_Position = vec4(clip.x, -clip.y, 0.0, 1.0);
  vL = aLocal; vLook = aLook;
}`;
  // A tuft of fur from a pre-drawn atlas of hairs; out-of-focus tufts read from blurrier mip levels.
  const FLUFF_FS = `#version 300 es
precision highp float;
uniform sampler2D uFur;
in vec2 vL; in vec4 vLook; out vec4 outColor;
void main() {
  float alpha = vLook.x, idx = vLook.y, soft = vLook.z, sheen = vLook.w;
  vec2 cell = vec2(mod(idx, ${FUR_COLS}.0), floor(idx / ${FUR_COLS}.0));
  vec2 uv = (cell + clamp(vL * 0.5 + 0.5, 0.0, 1.0)) / vec2(${FUR_COLS}.0, ${FUR_ROWS}.0);
  vec4 fur = texture(uFur, uv, soft * 2.4);
  float a = fur.a * alpha;
  // Hairs catch the light most where they are densest; thin ends stay a cooler, dimmer grey.
  vec3 tint = mix(vec3(0.86, 0.88, 0.9), vec3(1.0, 0.985, 0.955), clamp(fur.r * (0.8 + sheen), 0.0, 1.0));
  outColor = vec4(tint * a, a);
}`;

  function program(vs, fs) {
    const make = (type, src) => {
      const sh = gl.createShader(type); gl.shaderSource(sh, src); gl.compileShader(sh);
      if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(sh) || 'shader');
      return sh;
    };
    const prog = gl.createProgram();
    gl.attachShader(prog, make(gl.VERTEX_SHADER, vs)); gl.attachShader(prog, make(gl.FRAGMENT_SHADER, fs));
    gl.bindAttribLocation(prog, 0, 'aPos'); gl.bindAttribLocation(prog, 1, 'aLocal'); gl.bindAttribLocation(prog, 2, 'aLook');
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(prog) || 'link');
    const u = {};
    for (let i = 0, n = gl.getProgramParameter(prog, gl.ACTIVE_UNIFORMS); i < n; i++) {
      const name = gl.getActiveUniform(prog, i).name.replace(/\[0\]$/, '');
      u[name] = gl.getUniformLocation(prog, name);
    }
    return { prog, u };
  }

  let plateProg, spriteProg, fluffProg;
  try {
    plateProg = program(FULLSCREEN_VS, PLATE_FS);
    spriteProg = program(SPRITE_VS, SPRITE_FS);
    fluffProg = program(FLUFF_VS, FLUFF_FS);
  } catch (error) { console.warn('Hero scene unavailable:', error); stillMode(); return; }

  const quadVao = gl.createVertexArray();
  gl.bindVertexArray(quadVao);
  const quadBuf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, quadBuf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
  gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
  const spriteVao = gl.createVertexArray();
  gl.bindVertexArray(spriteVao);
  const spriteBuf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, spriteBuf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
  gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);

  const MAX_FLUFF = 320;
  const STRIDE = 8;
  const fluffData = new Float32Array(MAX_FLUFF * 6 * STRIDE);
  const fluffVao = gl.createVertexArray();
  gl.bindVertexArray(fluffVao);
  const fluffBuf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, fluffBuf);
  gl.bufferData(gl.ARRAY_BUFFER, fluffData.byteLength, gl.DYNAMIC_DRAW);
  gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, STRIDE * 4, 0);
  gl.enableVertexAttribArray(1); gl.vertexAttribPointer(1, 2, gl.FLOAT, false, STRIDE * 4, 8);
  gl.enableVertexAttribArray(2); gl.vertexAttribPointer(2, 4, gl.FLOAT, false, STRIDE * 4, 16);
  gl.bindVertexArray(null);

  const loadImage = src => new Promise((resolve, reject) => {
    const img = new Image(); img.decoding = 'async';
    img.onload = () => resolve(img); img.onerror = () => reject(new Error(`Could not load ${src}`)); img.src = src;
  });
  function texture(img) {
    const tex = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, tex);
    gl.pixelStorei(gl.UNPACK_PREMULTIPLY_ALPHA_WEBGL, false);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img);
    gl.generateMipmap(gl.TEXTURE_2D);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    return { tex, w: img.naturalWidth, h: img.naturalHeight };
  }

  // ---------- interaction state ----------
  const pointer = { x: -1e4, y: -1e4, nx: 0, ny: 0, sx: 0, sy: 0, inside: false, has: false };
  let sway = 0, swayVel = 0;
  let pendingFluff = 0;
  const fluff = [];
  let time = 0, last = 0, running = false, frameId = 0, smoothP = readProgress(), lastP = smoothP;

  const parallaxFg = () => [pointer.sx * 9, pointer.sy * 6];
  const parallaxBg = () => [pointer.sx * -4, pointer.sy * -3];
  const rotate = (v, a) => [Math.cos(a) * v[0] - Math.sin(a) * v[1], Math.sin(a) * v[0] + Math.cos(a) * v[1]];
  // Where a photo-space point on the flower ends up after sway and parallax (still in photo space).
  function onFlower(pt, theta) {
    const r = rotate([pt[0] - STEM_PIVOT[0], pt[1] - STEM_PIVOT[1]], theta);
    const par = parallaxFg();
    return [r[0] + STEM_PIVOT[0] + par[0] / place.s, r[1] + STEM_PIVOT[1] + par[1] / place.s];
  }
  const toScreen = pt => [(pt[0] - FLOWER[0]) * place.s + place.cx, (pt[1] - FLOWER[1]) * place.s + place.cy];
  const flowerScreen = theta => toScreen(onFlower(FLOWER, theta));

  function spawn(x, y, vx, vy, z) {
    if (fluff.length >= MAX_FLUFF) fluff.shift();
    const near = Math.max(z, 0);
    fluff.push({
      x, y, z, vx, vy,
      rot: Math.random() * Math.PI * 2, spin: (Math.random() - 0.5) * 0.9,
      // Tumbling about an axis in the picture plane: the tuft narrows as it turns edge-on.
      tumble: Math.random() * Math.PI * 2, tumbleSpeed: (0.4 + Math.random() * 1.0) * (Math.random() < 0.5 ? -1 : 1),
      life: 0, max: 4.5 + Math.random() * 5,
      size: (22 + Math.random() * 26) * (1 + z * 0.7) * clamp(place.s / 0.7, 0.6, 1.2),
      kind: Math.floor(Math.random() * FUR_COLS * FUR_ROWS),
      alpha: (0.6 + Math.random() * 0.35) / (1 + near * 1.1),
      soft: near,
      seed: Math.random() * 100,
    });
  }
  // Release fibres from the visible surface of the flower head near a screen point.
  function release(px, py, count, push = [0, 0]) {
    const [fx, fy] = flowerScreen(sway);
    const R = FLOWER_R * place.s;
    for (let i = 0; i < count; i++) {
      let dx = (px - fx) / R + (Math.random() - 0.5) * 0.16;
      let dy = (py - fy) / R + (Math.random() - 0.5) * 0.16;
      const d = Math.hypot(dx, dy);
      if (d > 0.98) { dx *= 0.98 / d; dy *= 0.98 / d; }
      const nz = Math.sqrt(Math.max(0, 1 - dx * dx - dy * dy));
      const speed = 35 + Math.random() * 95;
      const z = clamp(nz * 0.5 + (Math.random() - 0.45) * 0.9, -0.6, 1);
      spawn(fx + dx * R, fy + dy * R, dx * speed + push[0] * 0.22 + (Math.random() - 0.5) * 30, dy * speed - 12 + push[1] * 0.22 + (Math.random() - 0.5) * 30, z);
    }
  }
  function insideFlower(x, y) {
    const [fx, fy] = flowerScreen(sway);
    return Math.hypot(x - fx, y - fy) < FLOWER_R * place.s * 1.04;
  }
  function track(clientX, clientY) {
    const rect = stage.getBoundingClientRect();
    const x = clientX - rect.left, y = clientY - rect.top;
    if (pointer.has) {
      const dx = x - pointer.x, dy = y - pointer.y;
      const travel = Math.hypot(dx, dy);
      if (insideFlower(x, y) && travel > 0.5 && travel < 200) {
        // Brushing the flower: a small push along the stem's swing, and a few fibres let go.
        swayVel += clamp((dx * 0.84 - dy * 0.54) * 0.00011, -0.006, 0.006);
        pendingFluff += travel * 0.03;
        const n = Math.floor(pendingFluff);
        if (n > 0) { pendingFluff -= n; release(x, y, Math.min(n, 4), [dx * 30, dy * 30]); }
      }
    }
    pointer.x = x; pointer.y = y; pointer.has = true;
    pointer.nx = clamp((x / view.w) * 2 - 1, -1, 1);
    pointer.ny = clamp((y / view.h) * 2 - 1, -1, 1);
    pointer.inside = insideFlower(x, y);
  }
  stage.addEventListener('pointermove', event => track(event.clientX, event.clientY), { passive: true });
  stage.addEventListener('pointerleave', () => { pointer.has = false; pointer.inside = false; pointer.nx = 0; pointer.ny = 0; });
  stage.addEventListener('touchmove', event => { const t = event.touches[0]; if (t) track(t.clientX, t.clientY); }, { passive: true });
  stage.addEventListener('touchend', () => { pointer.has = false; }, { passive: true });
  stage.addEventListener('wheel', event => {
    if (!pointer.inside) return;
    const amount = Math.min(Math.abs(event.deltaY), 120);
    swayVel += Math.sign(event.deltaY) * amount * 0.000035;
    pendingFluff += amount * 0.035;
    const n = Math.floor(pendingFluff);
    if (n > 0) { pendingFluff -= n; release(pointer.x, pointer.y, Math.min(n, 5), [0, -Math.sign(event.deltaY) * 60]); }
  }, { passive: true });

  // ---------- the wasp's flight, as a function of scroll progress ----------
  // After lifting off it swoops: out toward the camera over the top-right of the flower (bigger, and a little
  // soft for being nearer than the focus), round past the flower's right edge back at its own size, and only
  // then away into the background, shrinking, softening and fading into the haze. It is never small while it
  // is over the flower, so it never looks pasted in front of something it should be behind.
  // Waypoints are in photo space; blur is in screen px at the desktop scale.
  const SWOOP = [
    { at: [1190, 380], scale: 1.4, blur: 1.5, haze: 0, rot: -0.1, yaw: 1, when: 0.22 },
    { at: [1345, 540], scale: 1.05, blur: 0.3, haze: 0, rot: 0.25, yaw: 0.9, when: 0.45 },
    { at: [1440, 610], scale: 0.55, blur: 2.5, haze: 0.25, rot: 0.15, yaw: 0.8, when: 0.72 },
    { at: [1490, 600], scale: 0.3, blur: 4, haze: 0.45, rot: 0.1, yaw: 0.75, when: 1 },
  ];
  const catmull = (a, b, c, d, t) => 0.5 * (2 * b + (c - a) * t + (2 * a - 5 * b + 4 * c - d) * t * t + (3 * b - a - 3 * c + d) * t * t * t);
  function waspPose(p, t) {
    const wings = smoothstep(0.02, 0.1, p);
    const lift = smoothstep(0.08, 0.26, p);
    const depart = smoothstep(0.24, 0.95, p);
    const attach = 1 - smoothstep(0.1, 0.2, p);
    const hover = [-18 * lift, (place.portrait ? -40 : -60) * lift];
    const tremble = wings * (0.25 + lift * 0.9);
    const jitter = [wobble(t * 3.1, 1.3) * 2.6 * tremble, wobble(t * 2.6, 4.1) * 3.4 * tremble];
    // The path starts wherever the hover has it, so lifting off and swooping are one movement.
    const pts = [{ at: [WASP_HINGE[0] + hover[0], WASP_HINGE[1] + hover[1]], scale: 1, blur: 0, haze: 0, rot: 0, yaw: 1, when: 0 }, ...SWOOP];
    let i = 0;
    while (i < pts.length - 2 && depart > pts[i + 1].when) i++;
    const f = clamp((depart - pts[i].when) / (pts[i + 1].when - pts[i].when), 0, 1);
    const P = k => pts[clamp(k, 0, pts.length - 1)];
    const along = key => mix(P(i)[key], P(i + 1)[key], f * f * (3 - 2 * f));
    const at = [0, 1].map(c => catmull(P(i - 1).at[c], P(i).at[c], P(i + 1).at[c], P(i + 2).at[c], f));
    const scale = along('scale');
    return {
      wings, lift, depart, attach,
      // Nearer than the flower it shifts more with the pointer than the flower does; far off, about as little as the background.
      z: 0.15 * lift + clamp((scale - 1) * 1.6, -0.8, 0.5),
      offset: [at[0] - WASP_HINGE[0] + jitter[0], at[1] - WASP_HINGE[1] + jitter[1]],
      rot: 0.3 * lift + along('rot') + wobble(t * 2.2, 7.7) * 0.035 * tremble,
      // Never beyond 1.3: the sprites hold about three texels per photo unit, so even on a 2x screen it stays sharp.
      scale,
      yaw: along('yaw'),
      blurPx: along('blur') * place.s / 0.7,
      haze: along('haze'),
      alpha: 1 - smoothstep(0.88, 1, depart),
    };
  }

  // ---------- frame ----------
  let plateTex, restTex, bodyTex, wingTex, furTex;

  function resize() {
    measure();
    canvas.width = Math.round(view.w * view.dpr);
    canvas.height = Math.round(view.h * view.dpr);
  }

  function setCommon(u, theta) {
    gl.uniform2f(u.uView, view.w, view.h);
    if (u.uDpr) gl.uniform1f(u.uDpr, view.dpr);
    if (u.uCenter) gl.uniform2f(u.uCenter, place.cx, place.cy);
    if (u.uScale) gl.uniform1f(u.uScale, place.s);
    if (u.uBase) gl.uniform3f(u.uBase, BASE[0], BASE[1], BASE[2]);
    if (u.uPortrait) gl.uniform1f(u.uPortrait, place.portrait ? 1 : 0);
    if (u.uTime) gl.uniform1f(u.uTime, time);
  }

  function drawSprite(tex, hinge, rot, squash, offset, blur, alpha, shade) {
    const u = spriteProg.u;
    gl.bindTexture(gl.TEXTURE_2D, tex.tex);
    gl.uniform2f(u.uHinge, hinge[0], hinge[1]);
    gl.uniform2f(u.uSize, WASP_SIZE[0], WASP_SIZE[1]);
    gl.uniform2f(u.uPivotL, WASP_HINGE_LOCAL[0], WASP_HINGE_LOCAL[1]);
    gl.uniform1f(u.uRot, rot);
    gl.uniform2f(u.uSquash, squash[0], squash[1]);
    gl.uniform2f(u.uOffset, offset[0], offset[1]);
    gl.uniform1f(u.uBlur, blur);
    gl.uniform1f(u.uAlpha, alpha);
    gl.uniform2f(u.uTexel, 1 / tex.w, 1 / tex.h);
    gl.uniform1f(u.uShade, shade);
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
  }

  function frame(now) {
    frameId = requestAnimationFrame(frame);
    const dt = last ? clamp((now - last) / 1000, 0, 0.05) : 1 / 60;
    last = now; time += dt;

    const target = readProgress();
    smoothP += (target - smoothP) * (1 - Math.exp(-dt * 7));
    if (Math.abs(target - smoothP) < 0.0004) smoothP = target;
    const p = smoothP;
    // The scene lags the page: shift everything drawn, loose fibres included, by the difference.
    const shift = scrolled() * sceneLag(), dShift = shift - lastShift;
    if (dShift) { place.cy = place.cy0 + shift; for (const f of fluff) f.y += dShift; lastShift = shift; }
    pointer.sx += (pointer.nx - pointer.sx) * (1 - Math.exp(-dt * 3));
    pointer.sy += (pointer.ny - pointer.sy) * (1 - Math.exp(-dt * 3));
    copy.style.transform = `translate3d(${(pointer.sx * -5).toFixed(2)}px, ${(pointer.sy * -3).toFixed(2)}px, 0)`;

    // The stem behaves like a lightly damped spring; a breeze keeps it from ever being perfectly still.
    const w0 = Math.PI * 2 * 0.85, zeta = 0.2;
    swayVel += (-w0 * w0 * sway - 2 * zeta * w0 * swayVel) * dt;
    sway = clamp(sway + swayVel * dt, -0.02, 0.02);
    const theta = sway + 0.0021 * Math.sin(time * 0.63) + 0.0011 * Math.sin(time * 1.37 + 1.2);

    const pose = waspPose(p, time);
    // Wing downdraft: as the wasp lifts away, a few fibres shake loose beneath it.
    const dp = p - lastP; lastP = p;
    if (pose.wings > 0.2 && pose.depart < 0.2 && Math.abs(dp) > 0.0004 && Math.random() < 0.3) {
      const base = toScreen(onFlower([WASP_HINGE[0] - 20, WASP_HINGE[1] + 60], theta));
      release(base[0] + (Math.random() - 0.5) * 60, base[1], 1, [0, 40]);
    }
    // Rarely, with nothing touching it, a single fibre drifts off the crown on its own.
    if (Math.random() < dt * 0.35) {
      const a = -Math.PI * (0.15 + Math.random() * 0.7);
      const [fx, fy] = flowerScreen(theta); const R = FLOWER_R * place.s;
      release(fx + Math.cos(a) * R * 0.95, fy + Math.sin(a) * R * 0.95, 1);
    }

    gl.viewport(0, 0, canvas.width, canvas.height);
    gl.disable(gl.BLEND);
    gl.useProgram(plateProg.prog);
    const pu = plateProg.u;
    setCommon(pu, theta);
    const fg = parallaxFg(), bg = parallaxBg();
    gl.uniform1f(pu.uTheta, theta);
    gl.uniform2f(pu.uPivot, STEM_PIVOT[0], STEM_PIVOT[1]);
    gl.uniform2f(pu.uParFg, fg[0], fg[1]);
    gl.uniform2f(pu.uParBg, bg[0], bg[1]);
    const resting = p < 0.03;
    gl.uniform1f(pu.uRestMix, resting ? 1 : 0);
    gl.uniform4f(pu.uRestRect, REST_RECT[0], REST_RECT[1], REST_RECT[2], REST_RECT[3]);
    gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, plateTex.tex); gl.uniform1i(pu.uPlate, 0);
    gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, restTex.tex); gl.uniform1i(pu.uRest, 1);
    gl.bindVertexArray(quadVao);
    gl.drawArrays(gl.TRIANGLES, 0, 3);

    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);

    if (!resting && pose.alpha > 0.002) {
      gl.useProgram(spriteProg.prog);
      const su = spriteProg.u;
      setCommon(su, theta);
      gl.activeTexture(gl.TEXTURE0); gl.uniform1i(su.uTex, 0);
      gl.bindVertexArray(spriteVao);
      const seated = onFlower(WASP_HINGE, theta);
      const free = [WASP_HINGE[0], WASP_HINGE[1]];
      const hinge = [mix(free[0], seated[0], pose.attach) + pose.offset[0], mix(free[1], seated[1], pose.attach) + pose.offset[1]];
      const drift = [pointer.sx * (9 + 16 * pose.z) * (1 - pose.attach), pointer.sy * (6 + 12 * pose.z) * (1 - pose.attach)];
      const rot = theta * pose.attach + pose.rot;
      const squash = [pose.scale * pose.yaw, pose.scale];
      const shade = 1 - pose.depart * 0.25;
      // Depth of field: blur is asked for in screen px, the shader wants sprite texels.
      const blur = pose.blurPx * bodyTex.w / WASP_SIZE[0] / (place.s * pose.scale);
      gl.uniform1f(su.uHaze, pose.haze);
      gl.uniform3f(su.uHazeColor, HAZE[0], HAZE[1], HAZE[2]);
      drawSprite(bodyTex, hinge, rot, squash, drift, blur, pose.alpha, shade);
      // Folded wing at rest; once the wings beat, a translucent fan of strokes stands in for the blur a camera sees.
      if (pose.wings < 0.999) drawSprite(wingTex, hinge, rot + pose.wings * -0.2, squash, drift, blur, pose.alpha * (1 - pose.wings), shade);
      if (pose.wings > 0.001) {
        const strokes = 8;
        const lo = mix(-0.16, -1.2, pose.lift), hi = mix(0.1, 0.34, pose.lift);
        for (let i = 0; i < strokes; i++) {
          const f = (i + Math.random()) / strokes;
          const a = mix(lo, hi, f) * pose.wings;
          const len = 0.8 + 0.2 * Math.cos((f - 0.5) * Math.PI * 1.4);
          drawSprite(wingTex, hinge, rot + a, [squash[0] * len, squash[1] * mix(1, 0.9, f)], drift, blur + 2, pose.alpha * pose.wings * (1.5 / strokes), shade);
        }
      }
    }

    // Fluff
    let count = 0;
    const drag = Math.exp(-dt * 1.6);
    for (let i = fluff.length - 1; i >= 0; i--) {
      const f = fluff[i];
      f.life += dt;
      if (f.life > f.max || f.y < -80 || f.x < -80 || f.x > view.w + 80) { fluff.splice(i, 1); continue; }
      const nx = Math.sin(f.y * 0.011 + time * 0.35 + f.seed) + 0.6 * Math.sin((f.x + f.y) * 0.006 - time * 0.23);
      const ny = Math.cos(f.x * 0.009 - time * 0.3 + f.seed) + 0.6 * Math.cos((f.x - f.y) * 0.005 + time * 0.19);
      f.vx = (f.vx + (nx * 16 + 3) * dt) * drag;
      f.vy = (f.vy + (ny * 11 - 8) * dt) * drag;
      f.x += f.vx * dt; f.y += f.vy * dt;
      f.rot += (f.spin + nx * 0.2) * dt;
      f.tumble += (f.tumbleSpeed + ny * 0.3) * dt;
    }
    for (const f of fluff) {
      if (count >= MAX_FLUFF) break;
      const fade = smoothstep(0, 0.3, f.life) * (1 - smoothstep(f.max * 0.55, f.max, f.life));
      const a = f.alpha * fade;
      if (a < 0.003) continue;
      const cx = f.x + pointer.sx * (7 + 15 * f.z), cy = f.y + pointer.sy * (5 + 10 * f.z);
      const face = Math.abs(Math.cos(f.tumble));
      const hx = f.size * 0.5 * (0.5 + 0.5 * face), hy = f.size * 0.5;
      const c = Math.cos(f.rot), s = Math.sin(f.rot);
      const sheen = 0.2 * face;
      let o = count * 6 * STRIDE;
      for (const [lx, ly] of [[-1, -1], [1, -1], [-1, 1], [-1, 1], [1, -1], [1, 1]]) {
        const ox = lx * hx, oy = ly * hy;
        fluffData[o++] = cx + c * ox - s * oy; fluffData[o++] = cy + s * ox + c * oy;
        fluffData[o++] = lx; fluffData[o++] = ly;
        fluffData[o++] = a * (0.85 + 0.15 * face); fluffData[o++] = f.kind; fluffData[o++] = f.soft; fluffData[o++] = sheen;
      }
      count++;
    }
    if (count) {
      gl.useProgram(fluffProg.prog);
      gl.uniform2f(fluffProg.u.uView, view.w, view.h);
      gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, furTex.tex); gl.uniform1i(fluffProg.u.uFur, 0);
      gl.bindVertexArray(fluffVao);
      gl.bindBuffer(gl.ARRAY_BUFFER, fluffBuf);
      gl.bufferSubData(gl.ARRAY_BUFFER, 0, fluffData, 0, count * 6 * STRIDE);
      gl.drawArrays(gl.TRIANGLES, 0, count * 6);
    }
    gl.bindVertexArray(null);
  }

  function start() { if (!running && plateTex) { running = true; last = 0; frameId = requestAnimationFrame(frame); } }
  function stop() { running = false; cancelAnimationFrame(frameId); }
  let visible = true;
  new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; visible && !document.hidden ? start() : stop(); }).observe(hero);
  document.addEventListener('visibilitychange', () => { visible && !document.hidden ? start() : stop(); });
  addEventListener('resize', resize);
  canvas.addEventListener('webglcontextlost', event => { event.preventDefault(); stop(); plateTex = null; canvas.classList.remove('is-ready'); stage.classList.remove('is-3d'); stillMode(); });
  reducedMotion.addEventListener('change', () => location.reload());

  resize();
  const big = PHOTO[0] * place.s * view.dpr > 1500;
  Promise.all([
    loadImage(big ? 'assets/hero/onion-plate-2400.webp' : 'assets/hero/onion-plate-1400.webp'),
    loadImage(big ? 'assets/hero/onion-rest-2400.webp' : 'assets/hero/onion-rest-1400.webp'),
    loadImage('assets/hero/wasp-body.webp'),
    loadImage('assets/hero/wasp-wing.webp'),
  ]).then(([plate, rest, body, wing]) => {
    plateTex = texture(plate); restTex = texture(rest); bodyTex = texture(body); wingTex = texture(wing);
    furTex = texture(furAtlas());
    stage.classList.add('is-3d');
    start();
    requestAnimationFrame(() => canvas.classList.add('is-ready'));
  }).catch(error => { console.warn('Hero scene unavailable:', error); stillMode(); });
})();
