(function () {
  "use strict";

  // ─────────────────────────────────────────────
  // CONSTANTS & PALETTE
  // ─────────────────────────────────────────────
  const C = {
    purple:  [162, 64,  255],
    pink:    [255, 102, 216],
    cyan:    [92,  242, 255],
    white:   [255, 255, 255],
  };

  function rgb(c, a = 1)  { return `rgba(${c[0]},${c[1]},${c[2]},${a})`; }
  function lerp(a, b, t)  { return a + (b - a) * t; }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function rand(a, b)     { return Math.random() * (b - a) + a; }
  function randInt(a, b)  { return Math.floor(rand(a, b + 1)); }
  function pick(arr)      { return arr[Math.floor(Math.random() * arr.length)]; }

  // ─────────────────────────────────────────────
  // SHARED CANVAS STATE
  // ─────────────────────────────────────────────
  let canvas, ctx, W, H, DPR;

  function setupCanvas() {
    canvas = document.getElementById("eggParticles");
    if (!canvas) return false;
    ctx    = canvas.getContext("2d");
    DPR    = Math.max(1, window.devicePixelRatio || 1);
    resize();
    window.addEventListener("resize", resize);
    return true;
  }

  function resize() {
    W = window.innerWidth;
    H = window.innerHeight;
    canvas.width  = Math.floor(W * DPR);
    canvas.height = Math.floor(H * DPR);
    canvas.style.width  = W + "px";
    canvas.style.height = H + "px";
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
  }

  // ─────────────────────────────────────────────
  // 1. HYPERSPACE WARP STREAKS
  // ─────────────────────────────────────────────
  const warpStars = [];
  const WARP_COUNT = 220;

  function makeWarpStar() {
    const angle = rand(0, Math.PI * 2);
    const speed = rand(0.6, 2.8);
    return {
      x: W / 2, y: H / 2,
      angle, speed,
      dist: rand(0, Math.min(W, H) * 0.18),
      len:  rand(6, 22),
      col:  pick([C.purple, C.cyan, C.pink, C.white]),
      alpha: rand(0.35, 0.85),
      width: rand(0.5, 1.6),
    };
  }

  function initWarp() {
    warpStars.length = 0;
    for (let i = 0; i < WARP_COUNT; i++) {
      const s = makeWarpStar();
      s.dist = rand(0, Math.min(W, H) * 0.6); // scatter initially
      warpStars.push(s);
    }
  }

  function tickWarp(dt) {
    const cx = W / 2, cy = H / 2;
    for (const s of warpStars) {
      s.dist += s.speed * dt * 0.055;
      const maxDist = Math.hypot(W, H) * 0.62;
      if (s.dist > maxDist) { Object.assign(s, makeWarpStar()); s.dist = 2; }

      const x0 = cx + Math.cos(s.angle) * s.dist;
      const y0 = cy + Math.sin(s.angle) * s.dist;
      const stretch = clamp(s.dist / 90, 0.1, 12);
      const x1 = cx + Math.cos(s.angle) * (s.dist + s.len * stretch);
      const y1 = cy + Math.sin(s.angle) * (s.dist + s.len * stretch);

      const g = ctx.createLinearGradient(x0, y0, x1, y1);
      g.addColorStop(0, rgb(s.col, 0));
      g.addColorStop(1, rgb(s.col, s.alpha * clamp(s.dist / 60, 0, 1)));

      ctx.beginPath();
      ctx.moveTo(x0, y0);
      ctx.lineTo(x1, y1);
      ctx.strokeStyle = g;
      ctx.lineWidth = s.width * clamp(s.dist / 120, 0.3, 1.8);
      ctx.globalAlpha = 1;
      ctx.stroke();
    }
  }

  // ─────────────────────────────────────────────
  // 2. FLOATING PARTICLES
  // ─────────────────────────────────────────────
  const particles = [];
  const PARTICLE_COUNT = 75;

  function makeParticle() {
    return {
      x: rand(0, W), y: rand(0, H),
      vx: rand(-0.12, 0.12), vy: rand(-0.15, 0.15),
      r: rand(1.0, 2.8),
      col: pick([C.purple, C.cyan, C.pink]),
      alpha: rand(0.18, 0.55),
    };
  }

  function initParticles() {
    particles.length = 0;
    for (let i = 0; i < PARTICLE_COUNT; i++) particles.push(makeParticle());
  }

  function tickParticles(dt) {
    ctx.globalCompositeOperation = "lighter";
    for (const p of particles) {
      p.x += p.vx * dt;
      p.y += p.vy * dt;
      if (p.x < -20) p.x = W + 20;
      if (p.x > W + 20) p.x = -20;
      if (p.y < -20) p.y = H + 20;
      if (p.y > H + 20) p.y = -20;

      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = rgb(p.col, p.alpha);
      ctx.fill();
    }
    ctx.globalCompositeOperation = "source-over";
  }

  // ─────────────────────────────────────────────
  // 3. LIGHTNING ARCS
  // ─────────────────────────────────────────────
  const lightningBolts = [];

  function makeLightningPath(x1, y1, x2, y2, splits, depth) {
    if (depth <= 0 || splits <= 0) return [[x1, y1, x2, y2]];
    const segs = [];
    const mx = (x1 + x2) / 2 + rand(-80, 80) * (depth / 4);
    const my = (y1 + y2) / 2 + rand(-80, 80) * (depth / 4);
    segs.push(...makeLightningPath(x1, y1, mx, my, splits - 1, depth - 1));
    segs.push(...makeLightningPath(mx, my, x2, y2, splits - 1, depth - 1));
    if (Math.random() < 0.28) {
      const bx = mx + rand(-120, 120);
      const by = my + rand(-120, 120);
      segs.push(...makeLightningPath(mx, my, bx, by, 1, depth - 2));
    }
    return segs;
  }

  function spawnLightning() {
    const edge = randInt(0, 3);
    let x1, y1;
    if (edge === 0) { x1 = rand(0, W); y1 = 0; }
    else if (edge === 1) { x1 = W; y1 = rand(0, H); }
    else if (edge === 2) { x1 = rand(0, W); y1 = H; }
    else { x1 = 0; y1 = rand(0, H); }

    const x2 = rand(W * 0.2, W * 0.8);
    const y2 = rand(H * 0.2, H * 0.8);
    const col = pick([C.cyan, C.purple, C.pink]);

    lightningBolts.push({
      segs: makeLightningPath(x1, y1, x2, y2, 7, 5),
      col,
      life: 1.0,
      decay: rand(0.032, 0.068),
    });
  }

  let lightningTimer = 0;
  const LIGHTNING_INTERVAL = 1800; // ms

  function tickLightning(dt) {
    lightningTimer += dt;
    if (lightningTimer > LIGHTNING_INTERVAL) {
      lightningTimer = 0;
      spawnLightning();
      if (Math.random() < 0.35) spawnLightning(); // double bolt occasionally
    }

    ctx.globalCompositeOperation = "lighter";
    for (let i = lightningBolts.length - 1; i >= 0; i--) {
      const bolt = lightningBolts[i];
      bolt.life -= bolt.decay * (dt / 16);
      if (bolt.life <= 0) { lightningBolts.splice(i, 1); continue; }

      const alpha = clamp(bolt.life, 0, 1);
      for (const [ax, ay, bx, by] of bolt.segs) {
        // Glow pass
        ctx.beginPath();
        ctx.moveTo(ax, ay); ctx.lineTo(bx, by);
        ctx.strokeStyle = rgb(bolt.col, alpha * 0.18);
        ctx.lineWidth = 6;
        ctx.stroke();
        // Core pass
        ctx.beginPath();
        ctx.moveTo(ax, ay); ctx.lineTo(bx, by);
        ctx.strokeStyle = rgb(C.white, alpha * 0.85);
        ctx.lineWidth = 1;
        ctx.stroke();
      }
    }
    ctx.globalCompositeOperation = "source-over";
  }

  // ─────────────────────────────────────────────
  // 4. SHOCKWAVE PULSES
  // ─────────────────────────────────────────────
  const shockwaves = [];

  function spawnShockwave() {
    shockwaves.push({
      x: rand(W * 0.25, W * 0.75),
      y: rand(H * 0.25, H * 0.75),
      r: 0,
      maxR: rand(180, 380),
      col: pick([C.purple, C.cyan, C.pink]),
      life: 1.0,
      decay: rand(0.008, 0.018),
      width: rand(1.5, 3.5),
    });
  }

  let shockTimer = 0;
  const SHOCK_INTERVAL = 2600;

  function tickShockwaves(dt) {
    shockTimer += dt;
    if (shockTimer > SHOCK_INTERVAL) {
      shockTimer = 0;
      spawnShockwave();
    }

    for (let i = shockwaves.length - 1; i >= 0; i--) {
      const s = shockwaves[i];
      s.life -= s.decay * (dt / 16);
      s.r = (1 - s.life) * s.maxR;
      if (s.life <= 0) { shockwaves.splice(i, 1); continue; }

      const alpha = clamp(s.life * 0.7, 0, 0.7);

      // Outer glow ring
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.strokeStyle = rgb(s.col, alpha * 0.22);
      ctx.lineWidth = s.width * 5;
      ctx.stroke();

      // Sharp ring
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.strokeStyle = rgb(s.col, alpha);
      ctx.lineWidth = s.width;
      ctx.stroke();

      // Inner ring (slightly smaller, offset)
      if (s.r > 18) {
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.r * 0.82, 0, Math.PI * 2);
        ctx.strokeStyle = rgb(C.white, alpha * 0.18);
        ctx.lineWidth = 1;
        ctx.stroke();
      }
    }
  }

  // ─────────────────────────────────────────────
  // 5. MATRIX RAIN
  // ─────────────────────────────────────────────
  const MATRIX_COLS = [];
  const MATRIX_FONT = 13;
  const MATRIX_CHARS = "700アイウエオカキクケコ01サシスセソABCDEFGHIJあいうえおタチツテト∑∆∏Ωλφψ∇∞≈≠±×÷";

  function initMatrix() {
    MATRIX_COLS.length = 0;
    const cols = Math.floor(W / MATRIX_FONT);
    for (let i = 0; i < cols; i++) {
      MATRIX_COLS.push({
        x: i * MATRIX_FONT,
        y: rand(-H, 0),
        speed: rand(0.4, 1.1),
        length: randInt(8, 28),
        chars: Array.from({ length: 28 }, () => pick([...MATRIX_CHARS])),
        col: pick([C.cyan, C.purple, C.pink]),
        alpha: rand(0.04, 0.10),
        mutateTimer: 0,
      });
    }
  }

  function tickMatrix(dt) {
    for (const col of MATRIX_COLS) {
      col.y += col.speed * dt * 0.04;
      col.mutateTimer += dt;
      if (col.mutateTimer > 600) {
        col.mutateTimer = 0;
        const idx = randInt(0, col.chars.length - 1);
        col.chars[idx] = pick([...MATRIX_CHARS]);
      }
      if (col.y - col.length * MATRIX_FONT > H) {
        col.y = -col.length * MATRIX_FONT;
        col.speed = rand(0.4, 1.1);
        col.col = pick([C.cyan, C.purple, C.pink]);
        col.alpha = rand(0.04, 0.10);
      }

      ctx.font = `${MATRIX_FONT}px monospace`;
      for (let j = 0; j < col.length; j++) {
        const cy = col.y + j * MATRIX_FONT;
        if (cy < 0 || cy > H) continue;
        const frac = j / col.length;
        const a = frac < 0.1 ? 0 : frac > 0.85 ? col.alpha * (1 - (frac - 0.85) / 0.15) : col.alpha;
        ctx.fillStyle = j === col.length - 1
          ? rgb(C.white, col.alpha * 1.6)
          : rgb(col.col, a);
        ctx.fillText(col.chars[j % col.chars.length], col.x, cy);
      }
    }
  }

  // ─────────────────────────────────────────────
  // 6. PLASMA ORBIT RINGS
  // ─────────────────────────────────────────────
  const rings = [
    { rx: 260, ry: 42,  tilt: 0.28, speed: 0.00038, phase: 0,            col: C.purple, alpha: 0.28, segments: 90 },
    { rx: 200, ry: 30,  tilt: -0.45, speed: -0.00055, phase: Math.PI/3,  col: C.cyan,   alpha: 0.22, segments: 70 },
    { rx: 320, ry: 55,  tilt: 0.62, speed: 0.00028, phase: Math.PI*0.8,  col: C.pink,   alpha: 0.18, segments: 110 },
    { rx: 150, ry: 22,  tilt: -0.20, speed: 0.00072, phase: Math.PI*1.4, col: C.white,  alpha: 0.12, segments: 50 },
  ];

  function tickRings(now) {
    const cx = W / 2;
    const cy = H / 2;

    for (const ring of rings) {
      const angle = now * ring.speed;
      ctx.globalCompositeOperation = "lighter";

      // Draw ring as a series of arc segments with perspective squish
      for (let i = 0; i < ring.segments; i++) {
        const t0 = (i / ring.segments) * Math.PI * 2 + angle + ring.phase;
        const t1 = ((i + 1) / ring.segments) * Math.PI * 2 + angle + ring.phase;

        const cos0 = Math.cos(t0), sin0 = Math.sin(t0);
        const cos1 = Math.cos(t1), sin1 = Math.sin(t1);

        // Perspective tilt (fake 3D by applying tilt to Y)
        const x0 = cx + ring.rx * cos0;
        const y0 = cy + ring.ry * sin0 * Math.cos(ring.tilt) + ring.rx * cos0 * Math.sin(ring.tilt) * 0.18;
        const x1 = cx + ring.rx * cos1;
        const y1 = cy + ring.ry * sin1 * Math.cos(ring.tilt) + ring.rx * cos1 * Math.sin(ring.tilt) * 0.18;

        // Brightness varies around ring (bright at "front", dim at "back")
        const brightness = clamp((sin0 + 1) / 2, 0.08, 1);

        ctx.beginPath();
        ctx.moveTo(x0, y0);
        ctx.lineTo(x1, y1);
        ctx.strokeStyle = rgb(ring.col, ring.alpha * brightness);
        ctx.lineWidth = brightness * 2.2 + 0.4;
        ctx.stroke();
      }

      ctx.globalCompositeOperation = "source-over";
    }
  }

  // ─────────────────────────────────────────────
  // 7. GLITCH EFFECT (periodic full-canvas RGB split)
  // ─────────────────────────────────────────────
  let glitchState = {
    active: false,
    timer: 0,
    nextGlitch: rand(3000, 7000),
    duration: 0,
    strips: [],
  };

  function makeGlitchStrips() {
    const strips = [];
    const count = randInt(4, 12);
    for (let i = 0; i < count; i++) {
      strips.push({
        y: rand(0, H),
        h: rand(4, H * 0.08),
        dx: rand(-28, 28),
      });
    }
    return strips;
  }

  function tickGlitch(dt, now) {
    glitchState.timer += dt;

    if (!glitchState.active) {
      if (glitchState.timer >= glitchState.nextGlitch) {
        glitchState.active   = true;
        glitchState.timer    = 0;
        glitchState.duration = rand(80, 280);
        glitchState.strips   = makeGlitchStrips();
      }
      return;
    }

    if (glitchState.timer >= glitchState.duration) {
      glitchState.active    = false;
      glitchState.timer     = 0;
      glitchState.nextGlitch = rand(2800, 8000);
      return;
    }

    // Grab current canvas pixels and RGB-shift strips
    try {
      const snap = ctx.getImageData(0, 0, canvas.width, canvas.height);
      for (const strip of glitchState.strips) {
        const sy = Math.floor(strip.y * DPR);
        const sh = Math.floor(strip.h * DPR);
        const sdx = Math.floor(strip.dx * DPR);
        if (sy < 0 || sy + sh > canvas.height) continue;

        // Shift red channel right
        const rShift = sdx;
        // Shift blue channel left
        const bShift = -sdx * 0.6;

        for (let row = sy; row < sy + sh && row < canvas.height; row++) {
          for (let col = 0; col < canvas.width; col++) {
            const i = (row * canvas.width + col) * 4;
            const rCol = clamp(col + rShift, 0, canvas.width - 1);
            const bCol = clamp(col + bShift, 0, canvas.width - 1);
            const ri = (row * canvas.width + rCol) * 4;
            const bi = (row * canvas.width + bCol) * 4;
            snap.data[i]     = snap.data[ri];     // R from shifted pos
            snap.data[i + 2] = snap.data[bi + 2]; // B from shifted pos
          }
        }
      }
      ctx.putImageData(snap, 0, 0);
    } catch {
      // cross-origin or security error — skip silently
    }

    // Draw scanline flicker bands
    ctx.globalAlpha = rand(0.04, 0.14);
    ctx.fillStyle = rgb(C.purple, 1);
    for (const strip of glitchState.strips) {
      ctx.fillRect(0, strip.y, W, strip.h * 0.3);
    }
    ctx.globalAlpha = 1;
  }

  // ─────────────────────────────────────────────
  // 8. TILT SPOTLIGHT (card hover)
  // ─────────────────────────────────────────────
  function initTiltSpotlight() {
    // Tilt is applied to the wrapper (no .card class = no overflow:hidden = no 3D flattening)
    // Mouse events are listened on the card itself for accurate hit detection
    const wrap = document.getElementById("eggTiltWrap");
    const card = document.getElementById("eggCard") || document.querySelector(".egg-card");
    if (!wrap || !card) return;

    let target  = { rx: 0, ry: 0, mx: 50, my: 35 };
    let current = { rx: 0, ry: 0, mx: 50, my: 35 };

    function tick() {
      current.rx = lerp(current.rx, target.rx, 0.08);
      current.ry = lerp(current.ry, target.ry, 0.08);
      current.mx = lerp(current.mx, target.mx, 0.10);
      current.my = lerp(current.my, target.my, 0.10);

      // Apply perspective + tilt to the wrapper — it has no overflow:hidden, so 3D works
      wrap.style.transform =
        `perspective(1000px) rotateX(${current.rx.toFixed(3)}deg) rotateY(${current.ry.toFixed(3)}deg)`;

      // Spotlight follows mouse on the card via CSS custom properties
      card.style.setProperty("--mx", `${current.mx.toFixed(1)}%`);
      card.style.setProperty("--my", `${current.my.toFixed(1)}%`);

      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);

    function onMove(clientX, clientY) {
      const r = card.getBoundingClientRect();
      const x = (clientX - r.left) / r.width;
      const y = (clientY - r.top)  / r.height;
      target.mx = clamp(x * 100, 0, 100);
      target.my = clamp(y * 100, 0, 100);
      const max = 10;
      target.ry =  (x - 0.5) * (max * 2);
      target.rx = -(y - 0.5) * (max * 2);
    }

    function reset() {
      target.rx = 0; target.ry = 0;
      target.mx = 50; target.my = 35;
    }

    card.addEventListener("mousemove", (e) => onMove(e.clientX, e.clientY));
    card.addEventListener("mouseleave", reset);
    card.addEventListener("touchmove", (e) => {
      if (e.touches?.[0]) onMove(e.touches[0].clientX, e.touches[0].clientY);
    }, { passive: true });
    card.addEventListener("touchend", reset);
  }

  // ─────────────────────────────────────────────
  // 9. ENTRANCE ANIMATION
  // ─────────────────────────────────────────────
  function initEntrance() {
    const wrap = document.querySelector(".egg-enter");
    if (wrap) requestAnimationFrame(() => wrap.classList.add("egg-entered"));
  }

  // ─────────────────────────────────────────────
  // 10. TITLE GLITCH (DOM text RGB split via CSS class)
  // ─────────────────────────────────────────────
  function initTitleGlitch() {
    const title = document.querySelector(".egg-title");
    if (!title) return;
    function doGlitch() {
      title.classList.add("egg-title--glitch");
      setTimeout(() => title.classList.remove("egg-title--glitch"), rand(80, 220));
      setTimeout(doGlitch, rand(2500, 7000));
    }
    setTimeout(doGlitch, rand(1200, 3000));
  }

  // ─────────────────────────────────────────────
  // MAIN LOOP
  // ─────────────────────────────────────────────
  let lastTime = null;

  function loop(now) {
    if (lastTime === null) lastTime = now;
    const dt = Math.min(48, now - lastTime);
    lastTime = now;

    ctx.clearRect(0, 0, W, H);

    // Layer order: matrix (bottom) → warp → particles → rings → shockwaves → lightning → glitch (top)
    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = "source-over";

    tickMatrix(dt);
    tickWarp(dt);
    tickParticles(dt);
    tickRings(now);
    tickShockwaves(dt);
    tickLightning(dt);
    tickGlitch(dt, now);


    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = "source-over";

    requestAnimationFrame(loop);
  }

  // ─────────────────────────────────────────────
  // INIT
  // ─────────────────────────────────────────────
  window.addEventListener("DOMContentLoaded", () => {
    initEntrance();

    const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
    if (reduce) return;

    if (!setupCanvas()) return;

    initWarp();
    initParticles();
    initMatrix();
    initTiltSpotlight();
    initTitleGlitch();

    // Kick off with a shockwave immediately + one lightning bolt
    spawnShockwave();
    setTimeout(spawnLightning, 400);

    requestAnimationFrame(loop);
  });

})();
