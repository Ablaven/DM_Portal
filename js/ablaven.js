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
    orange:  [255, 165, 0],
    yellow:  [255, 240, 0],
  };

  function rgb(c, a = 1)  { return `rgba(${c[0]},${c[1]},${c[2]},${a})`; }
  function lerp(a, b, t)  { return a + (b - a) * t; }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function rand(a, b)     { return Math.random() * (b - a) + a; }
  function randInt(a, b)  { return Math.floor(rand(a, b + 1)); }
  function pick(arr)      { return arr[Math.floor(Math.random() * arr.length)]; }

  // ─────────────────────────────────────────────
  // GOD MODE & ACHIEVEMENTS STATE
  // ─────────────────────────────────────────────
  let godMode = false;
  let achievements = {
    entered: true,
    clicked: false,
    konami: false,
    watcher: false,
  };
  let achievementUI = null;
  let watchTimer = 0;

  // ─────────────────────────────────────────────
  // SHARED CANVAS STATE
  // ─────────────────────────────────────────────
  let canvas, ctx, W, H, DPR;
  let resizeListenerAdded = false;

  function setupCanvas() {
    canvas = document.getElementById("eggParticles");
    if (!canvas) return false;
    ctx    = canvas.getContext("2d");
    DPR    = Math.max(1, window.devicePixelRatio || 1);
    resize();
    if (!resizeListenerAdded) {
      window.addEventListener("resize", resize);
      resizeListenerAdded = true;
    }
    return true;
  }

  function resize() {
    const oldW = W;
    W = window.innerWidth;
    H = window.innerHeight;
    canvas.width  = Math.floor(W * DPR);
    canvas.height = Math.floor(H * DPR);
    canvas.style.width  = W + "px";
    canvas.style.height = H + "px";
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    
    if (Math.abs((oldW || W) - W) > 100) {
      initMatrix();
    }
  }

  // ─────────────────────────────────────────────
  // NEW: CLICK PARTICLE EXPLOSION
  // ─────────────────────────────────────────────
  const explosions = [];

  function spawnExplosion(x, y) {
    if (!achievements.clicked) {
      achievements.clicked = true;
      showAchievement("Fireworks Master", "Create your first explosion");
    }
    
    const particleCount = godMode ? 120 : 60;
    const explosion = {
      x, y,
      particles: [],
      life: 1.0,
    };
    
    for (let i = 0; i < particleCount; i++) {
      const angle = rand(0, Math.PI * 2);
      const speed = rand(godMode ? 4 : 2, godMode ? 10 : 6);
      explosion.particles.push({
        x: 0, y: 0,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        r: rand(1, 3),
        col: pick([C.purple, C.cyan, C.pink, C.orange, C.yellow]),
        alpha: rand(0.7, 1.0),
        decay: rand(0.015, 0.025),
      });
    }
    explosions.push(explosion);
  }

  function tickExplosions(dt) {
    ctx.globalCompositeOperation = "lighter";
    for (let i = explosions.length - 1; i >= 0; i--) {
      const exp = explosions[i];
      exp.life -= 0.012 * (dt / 16);
      
      if (exp.life <= 0) {
        explosions.splice(i, 1);
        continue;
      }
      
      for (const p of exp.particles) {
        p.x += p.vx * (dt / 16);
        p.y += p.vy * (dt / 16);
        p.vy += 0.15 * (dt / 16); // gravity
        p.vx *= 0.98;
        p.vy *= 0.98;
        p.alpha -= p.decay * (dt / 16);
        
        if (p.alpha > 0) {
          ctx.beginPath();
          ctx.arc(exp.x + p.x, exp.y + p.y, p.r, 0, Math.PI * 2);
          ctx.fillStyle = rgb(p.col, p.alpha * exp.life);
          ctx.fill();
          
          // Trail effect
          if (godMode) {
            ctx.beginPath();
            ctx.moveTo(exp.x + p.x, exp.y + p.y);
            ctx.lineTo(exp.x + p.x - p.vx * 2, exp.y + p.y - p.vy * 2);
            ctx.strokeStyle = rgb(p.col, p.alpha * exp.life * 0.3);
            ctx.lineWidth = p.r * 0.5;
            ctx.stroke();
          }
        }
      }
    }
    ctx.globalCompositeOperation = "source-over";
  }

  // ─────────────────────────────────────────────
  // NEW: 3D WIREFRAME CUBE
  // ─────────────────────────────────────────────
  const cubeVertices = [
    [-1,-1,-1], [1,-1,-1], [1,1,-1], [-1,1,-1], // back face
    [-1,-1,1],  [1,-1,1],  [1,1,1],  [-1,1,1],  // front face
  ];
  const cubeEdges = [
    [0,1],[1,2],[2,3],[3,0], // back
    [4,5],[5,6],[6,7],[7,4], // front
    [0,4],[1,5],[2,6],[3,7], // connecting
  ];
  
  let cubeRotation = { x: 0, y: 0, z: 0 };

  function project3D(vertex, rotX, rotY, rotZ, scale, offsetX, offsetY) {
    let [x, y, z] = vertex;
    
    // Rotate X
    let cosX = Math.cos(rotX), sinX = Math.sin(rotX);
    let y1 = y * cosX - z * sinX;
    let z1 = y * sinX + z * cosX;
    y = y1; z = z1;
    
    // Rotate Y
    let cosY = Math.cos(rotY), sinY = Math.sin(rotY);
    let x1 = x * cosY + z * sinY;
    z1 = -x * sinY + z * cosY;
    x = x1; z = z1;
    
    // Rotate Z
    let cosZ = Math.cos(rotZ), sinZ = Math.sin(rotZ);
    x1 = x * cosZ - y * sinZ;
    y1 = x * sinZ + y * cosZ;
    x = x1; y = y1;
    
    // Perspective projection
    const fov = 4;
    const depth = 1 / (fov + z);
    return {
      x: offsetX + x * scale * depth,
      y: offsetY + y * scale * depth,
      depth: z,
    };
  }

  function tickCube(now) {
    cubeRotation.x = now * 0.0008;
    cubeRotation.y = now * 0.0012;
    cubeRotation.z = now * 0.0006;
    
    const scale = godMode ? 180 : 120;
    const cx = W * 0.85;
    const cy = H * 0.15;
    
    // Project vertices
    const projected = cubeVertices.map(v => 
      project3D(v, cubeRotation.x, cubeRotation.y, cubeRotation.z, scale, cx, cy)
    );
    
    // Draw edges
    ctx.globalCompositeOperation = "lighter";
    for (const [i, j] of cubeEdges) {
      const p1 = projected[i];
      const p2 = projected[j];
      const avgDepth = (p1.depth + p2.depth) / 2;
      const alpha = clamp((avgDepth + 2) / 4, 0.15, 0.9);
      
      ctx.beginPath();
      ctx.moveTo(p1.x, p1.y);
      ctx.lineTo(p2.x, p2.y);
      
      const grad = ctx.createLinearGradient(p1.x, p1.y, p2.x, p2.y);
      grad.addColorStop(0, rgb(C.cyan, alpha));
      grad.addColorStop(0.5, rgb(C.purple, alpha));
      grad.addColorStop(1, rgb(C.pink, alpha));
      
      ctx.strokeStyle = grad;
      ctx.lineWidth = godMode ? 2.5 : 1.5;
      ctx.stroke();
    }
    
    // Draw vertices as glowing dots
    for (const p of projected) {
      const alpha = clamp((p.depth + 2) / 4, 0.2, 1.0);
      ctx.beginPath();
      ctx.arc(p.x, p.y, godMode ? 4 : 2.5, 0, Math.PI * 2);
      ctx.fillStyle = rgb(C.white, alpha);
      ctx.fill();
      
      // Glow
      ctx.beginPath();
      ctx.arc(p.x, p.y, godMode ? 8 : 5, 0, Math.PI * 2);
      ctx.fillStyle = rgb(C.cyan, alpha * 0.2);
      ctx.fill();
    }
    
    // "ABLAVEN" text on cube
    ctx.save();
    ctx.translate(cx, cy - scale * 0.6);
    ctx.rotate(cubeRotation.y);
    ctx.font = `${godMode ? 18 : 14}px monospace`;
    ctx.textAlign = "center";
    ctx.fillStyle = rgb(C.white, 0.85);
    ctx.fillText("ABLAVEN", 0, 0);
    ctx.restore();
    
    ctx.globalCompositeOperation = "source-over";
  }

  // ─────────────────────────────────────────────
  // NEW: SYNTHWAVE SUN & GRID HORIZON
  // ─────────────────────────────────────────────
  let gridOffset = 0;

  function tickSynthwave(dt, now) {
    const horizonY = H * 0.70;
    const sunCenterY = horizonY - 80;
    const sunCenterX = W / 2;
    
    // Draw sun
    const sunSize = godMode ? 140 : 100;
    const sunGrad = ctx.createRadialGradient(sunCenterX, sunCenterY, 0, sunCenterX, sunCenterY, sunSize);
    sunGrad.addColorStop(0, rgb(C.yellow, 0.9));
    sunGrad.addColorStop(0.4, rgb(C.orange, 0.7));
    sunGrad.addColorStop(0.7, rgb(C.pink, 0.5));
    sunGrad.addColorStop(1, rgb(C.purple, 0));
    
    ctx.globalCompositeOperation = "lighter";
    ctx.beginPath();
    ctx.arc(sunCenterX, sunCenterY, sunSize, 0, Math.PI * 2);
    ctx.fillStyle = sunGrad;
    ctx.fill();
    
    // Sun stripes
    const stripeCount = 8;
    const stripeSpacing = sunSize * 2 / stripeCount;
    for (let i = 0; i < stripeCount; i++) {
      const y = sunCenterY - sunSize + i * stripeSpacing;
      ctx.fillStyle = rgb(C.purple, 0.6);
      ctx.fillRect(sunCenterX - sunSize, y, sunSize * 2, stripeSpacing * 0.3);
    }
    
    // Grid
    gridOffset += dt * 0.15;
    const gridSize = 50;
    if (gridOffset > gridSize) gridOffset -= gridSize;
    
    ctx.save();
    ctx.globalAlpha = 0.35;
    ctx.strokeStyle = rgb(C.cyan, 1);
    ctx.lineWidth = 1.5;
    
    // Perspective grid
    for (let i = -10; i < 20; i++) {
      const z = i * gridSize + gridOffset;
      if (z < 10) continue;
      
      const scale = 300 / z;
      const y = horizonY + (H - horizonY) * (1 - scale);
      const width = W * scale;
      
      if (y > H) continue;
      
      // Horizontal lines
      ctx.beginPath();
      ctx.moveTo(W/2 - width/2, y);
      ctx.lineTo(W/2 + width/2, y);
      ctx.stroke();
    }
    
    // Vertical lines
    const vLineCount = 15;
    for (let i = -vLineCount; i <= vLineCount; i++) {
      if (i === 0) continue;
      const xOffset = (W / vLineCount) * i;
      
      ctx.beginPath();
      ctx.moveTo(W/2 + xOffset, horizonY);
      
      for (let z = 50; z < 1000; z += 50) {
        const scale = 300 / z;
        const y = horizonY + (H - horizonY) * (1 - scale);
        const x = W/2 + xOffset * scale;
        ctx.lineTo(x, y);
      }
      ctx.stroke();
    }
    
    ctx.restore();
    ctx.globalCompositeOperation = "source-over";
  }

  // ─────────────────────────────────────────────
  // 1. HYPERSPACE WARP STREAKS
  // ─────────────────────────────────────────────
  const warpStars = [];
  const WARP_COUNT = 220;

  function makeWarpStar() {
    const angle = rand(0, Math.PI * 2);
    const speed = rand(0.6, 2.8) * (godMode ? 2 : 1);
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
    for (let i = 0; i < (godMode ? WARP_COUNT * 2 : WARP_COUNT); i++) {
      const s = makeWarpStar();
      s.dist = rand(0, Math.min(W, H) * 0.6);
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
      segs: makeLightningPath(x1, y1, x2, y2, godMode ? 10 : 7, godMode ? 7 : 5),
      col,
      life: 1.0,
      decay: rand(0.032, 0.068),
    });
  }

  let lightningTimer = 0;
  const LIGHTNING_INTERVAL = 1800;

  function tickLightning(dt) {
    lightningTimer += dt;
    const interval = godMode ? LIGHTNING_INTERVAL / 3 : LIGHTNING_INTERVAL;
    if (lightningTimer > interval) {
      lightningTimer = 0;
      spawnLightning();
      if (Math.random() < 0.35 || godMode) spawnLightning();
    }

    ctx.globalCompositeOperation = "lighter";
    for (let i = lightningBolts.length - 1; i >= 0; i--) {
      const bolt = lightningBolts[i];
      bolt.life -= bolt.decay * (dt / 16);
      if (bolt.life <= 0) { lightningBolts.splice(i, 1); continue; }

      const alpha = clamp(bolt.life, 0, 1);
      for (const [ax, ay, bx, by] of bolt.segs) {
        ctx.beginPath();
        ctx.moveTo(ax, ay); ctx.lineTo(bx, by);
        ctx.strokeStyle = rgb(bolt.col, alpha * 0.18);
        ctx.lineWidth = 6;
        ctx.stroke();
        
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
      maxR: rand(180, 380) * (godMode ? 1.5 : 1),
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
    const interval = godMode ? SHOCK_INTERVAL / 2 : SHOCK_INTERVAL;
    if (shockTimer > interval) {
      shockTimer = 0;
      spawnShockwave();
    }

    for (let i = shockwaves.length - 1; i >= 0; i--) {
      const s = shockwaves[i];
      s.life -= s.decay * (dt / 16);
      s.r = (1 - s.life) * s.maxR;
      if (s.life <= 0) { shockwaves.splice(i, 1); continue; }

      const alpha = clamp(s.life * 0.7, 0, 0.7);

      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.strokeStyle = rgb(s.col, alpha * 0.22);
      ctx.lineWidth = s.width * 5;
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.strokeStyle = rgb(s.col, alpha);
      ctx.lineWidth = s.width;
      ctx.stroke();

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
      col.y += col.speed * dt * 0.04 * (godMode ? 2 : 1);
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
      const angle = now * ring.speed * (godMode ? 2 : 1);
      ctx.globalCompositeOperation = "lighter";

      for (let i = 0; i < ring.segments; i++) {
        const t0 = (i / ring.segments) * Math.PI * 2 + angle + ring.phase;
        const t1 = ((i + 1) / ring.segments) * Math.PI * 2 + angle + ring.phase;

        const cos0 = Math.cos(t0), sin0 = Math.sin(t0);
        const cos1 = Math.cos(t1), sin1 = Math.sin(t1);

        const x0 = cx + ring.rx * cos0;
        const y0 = cy + ring.ry * sin0 * Math.cos(ring.tilt) + ring.rx * cos0 * Math.sin(ring.tilt) * 0.18;
        const x1 = cx + ring.rx * cos1;
        const y1 = cy + ring.ry * sin1 * Math.cos(ring.tilt) + ring.rx * cos1 * Math.sin(ring.tilt) * 0.18;

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
  // 7. GLITCH EFFECT
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
      const nextInterval = godMode ? 1500 : 4000;
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
      glitchState.nextGlitch = rand(godMode ? 1000 : 2800, godMode ? 3000 : 8000);
      return;
    }

    try {
      const snap = ctx.getImageData(0, 0, canvas.width, canvas.height);
      for (const strip of glitchState.strips) {
        const sy = Math.floor(strip.y * DPR);
        const sh = Math.floor(strip.h * DPR);
        const sdx = Math.floor(strip.dx * DPR);
        if (sy < 0 || sy + sh > canvas.height) continue;

        const rShift = sdx;
        const bShift = -sdx * 0.6;

        for (let row = sy; row < sy + sh && row < canvas.height; row++) {
          for (let col = 0; col < canvas.width; col++) {
            const i = (row * canvas.width + col) * 4;
            const rCol = clamp(col + rShift, 0, canvas.width - 1);
            const bCol = clamp(col + bShift, 0, canvas.width - 1);
            const ri = (row * canvas.width + rCol) * 4;
            const bi = (row * canvas.width + bCol) * 4;
            snap.data[i]     = snap.data[ri];
            snap.data[i + 2] = snap.data[bi + 2];
          }
        }
      }
      ctx.putImageData(snap, 0, 0);
    } catch {
      // cross-origin or security error
    }

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

      wrap.style.transform =
        `perspective(1000px) rotateX(${current.rx.toFixed(3)}deg) rotateY(${current.ry.toFixed(3)}deg)`;

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
  // 10. TITLE GLITCH
  // ─────────────────────────────────────────────
  function initTitleGlitch() {
    const title = document.querySelector(".egg-title");
    if (!title) return;
    function doGlitch() {
      title.classList.add("egg-title--glitch");
      setTimeout(() => title.classList.remove("egg-title--glitch"), rand(80, 220));
      setTimeout(doGlitch, rand(godMode ? 1200 : 2500, godMode ? 4000 : 7000));
    }
    setTimeout(doGlitch, rand(1200, 3000));
  }

  // ─────────────────────────────────────────────
  // NEW: KONAMI CODE LISTENER
  // ─────────────────────────────────────────────
  function initKonamiCode() {
    const sequence = ["ArrowUp", "ArrowUp", "ArrowDown", "ArrowDown", "ArrowLeft", "ArrowRight", "ArrowLeft", "ArrowRight", "b", "a"];
    let progress = 0;
    let lastKeyTime = 0;

    document.addEventListener("keydown", (e) => {
      const now = Date.now();
      if (now - lastKeyTime > 1000) progress = 0;
      lastKeyTime = now;

      if (e.key.toLowerCase() === sequence[progress].toLowerCase() || e.key === sequence[progress]) {
        progress++;
        if (progress === sequence.length) {
          progress = 0;
          activateGodMode();
        }
      } else {
        progress = 0;
      }
    });
  }

  function activateGodMode() {
    if (godMode) return;
    godMode = true;
    achievements.konami = true;
    showAchievement("GOD MODE ACTIVATED", "Konami Code unlocked! All effects doubled!");
    
    // Reinitialize effects with god mode
    initWarp();
    
    // Visual feedback
    for (let i = 0; i < 5; i++) {
      setTimeout(() => spawnShockwave(), i * 200);
      setTimeout(() => spawnLightning(), i * 150);
    }
  }

  // ─────────────────────────────────────────────
  // NEW: ACHIEVEMENT SYSTEM
  // ─────────────────────────────────────────────
  function showAchievement(title, desc) {
    const container = document.getElementById("achievementContainer");
    if (!container) return;

    const el = document.createElement("div");
    el.className = "achievement-toast";
    el.innerHTML = `
      <div class="achievement-icon">🏆</div>
      <div class="achievement-content">
        <div class="achievement-title">${title}</div>
        <div class="achievement-desc">${desc}</div>
      </div>
    `;

    container.appendChild(el);
    
    requestAnimationFrame(() => el.classList.add("achievement-show"));

    setTimeout(() => {
      el.classList.remove("achievement-show");
      setTimeout(() => el.remove(), 300);
    }, 4000);
    
    // Play sound if enabled
    playSound("achievement");
  }

  // ─────────────────────────────────────────────
  // NEW: CLICK EXPLOSION HANDLER
  // ─────────────────────────────────────────────
  function initClickExplosions() {
    canvas.addEventListener("click", (e) => {
      const rect = canvas.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      spawnExplosion(x, y);
      playSound("explosion");
    });
  }

  // ─────────────────────────────────────────────
  // NEW: SIMPLE SOUND SYSTEM (beeps via Web Audio API)
  // ─────────────────────────────────────────────
  let audioContext = null;
  let soundEnabled = true;

  function initSound() {
    try {
      audioContext = new (window.AudioContext || window.webkitAudioContext)();
    } catch {
      soundEnabled = false;
    }
  }

  function playSound(type) {
    if (!soundEnabled || !audioContext) return;
    
    const osc = audioContext.createOscillator();
    const gain = audioContext.createGain();
    
    osc.connect(gain);
    gain.connect(audioContext.destination);
    
    if (type === "achievement") {
      osc.frequency.setValueAtTime(800, audioContext.currentTime);
      osc.frequency.exponentialRampToValueAtTime(1200, audioContext.currentTime + 0.1);
      gain.gain.setValueAtTime(0.15, audioContext.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
      osc.start();
      osc.stop(audioContext.currentTime + 0.3);
    } else if (type === "explosion") {
      osc.type = "sawtooth";
      osc.frequency.setValueAtTime(150, audioContext.currentTime);
      osc.frequency.exponentialRampToValueAtTime(50, audioContext.currentTime + 0.15);
      gain.gain.setValueAtTime(0.08, audioContext.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.15);
      osc.start();
      osc.stop(audioContext.currentTime + 0.15);
    }
  }

  function toggleSound() {
    soundEnabled = !soundEnabled;
    const btn = document.getElementById("soundToggle");
    if (btn) btn.textContent = soundEnabled ? "🔊 Sound ON" : "🔇 Sound OFF";
  }

  // ─────────────────────────────────────────────
  // NEW: WATCH TIME ACHIEVEMENT
  // ─────────────────────────────────────────────
  function checkWatchAchievement(dt) {
    if (achievements.watcher) return;
    watchTimer += dt;
    if (watchTimer > 30000) { // 30 seconds
      achievements.watcher = true;
      showAchievement("Dedicated Watcher", "Stayed for 30 seconds!");
    }
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

    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = "source-over";

    tickSynthwave(dt, now);
    tickMatrix(dt);
    tickWarp(dt);
    tickParticles(dt);
    tickCube(now);
    tickRings(now);
    tickShockwaves(dt);
    tickLightning(dt);
    tickExplosions(dt);
    tickGlitch(dt, now);
    
    checkWatchAchievement(dt);

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
    initKonamiCode();
    initClickExplosions();
    initSound();

    spawnShockwave();
    setTimeout(spawnLightning, 400);

    requestAnimationFrame(loop);
    
    // Sound toggle button
    const soundBtn = document.getElementById("soundToggle");
    if (soundBtn) {
      soundBtn.addEventListener("click", toggleSound);
    }
  });

})();