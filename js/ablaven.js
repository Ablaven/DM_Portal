(function () {
  function qs(sel) {
    return document.querySelector(sel);
  }

  // Entrance animation
  window.addEventListener("DOMContentLoaded", () => {
    const wrap = qs(".egg-enter");
    if (wrap) {
      // next frame
      requestAnimationFrame(() => wrap.classList.add("egg-entered"));
    }

    const reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (!reduce) {
      initParticles();
      initTiltSpotlight();
    }
  });

  function initTiltSpotlight() {
    const card = document.getElementById("eggCard") || qs(".egg-card");
    if (!card) return;

    let raf = 0;
    let target = { rx: 0, ry: 0, mx: 50, my: 35 };
    let current = { rx: 0, ry: 0, mx: 50, my: 35 };

    function animate() {
      raf = 0;
      // smooth lerp
      const lerp = (a, b, t) => a + (b - a) * t;
      current.rx = lerp(current.rx, target.rx, 0.10);
      current.ry = lerp(current.ry, target.ry, 0.10);
      current.mx = lerp(current.mx, target.mx, 0.12);
      current.my = lerp(current.my, target.my, 0.12);

      card.style.transform = `perspective(900px) rotateX(${current.rx.toFixed(2)}deg) rotateY(${current.ry.toFixed(2)}deg)`;
      card.style.setProperty("--mx", `${current.mx.toFixed(1)}%`);
      card.style.setProperty("--my", `${current.my.toFixed(1)}%`);

      if (Math.abs(current.rx - target.rx) > 0.01 || Math.abs(current.ry - target.ry) > 0.01) {
        raf = requestAnimationFrame(animate);
      }
    }

    function request() {
      if (!raf) raf = requestAnimationFrame(animate);
    }

    function onMove(clientX, clientY) {
      const r = card.getBoundingClientRect();
      const x = (clientX - r.left) / r.width; // 0..1
      const y = (clientY - r.top) / r.height; // 0..1

      // spotlight position
      target.mx = clamp(x * 100, 0, 100);
      target.my = clamp(y * 100, 0, 100);

      // gentle tilt
      const max = 7.5;
      target.ry = (x - 0.5) * (max * 2);
      target.rx = -(y - 0.5) * (max * 2);

      request();
    }

    function reset() {
      target = { rx: 0, ry: 0, mx: 50, my: 35 };
      request();
    }

    card.addEventListener("mousemove", (e) => onMove(e.clientX, e.clientY));
    card.addEventListener("mouseleave", reset);

    // Touch support (simple)
    card.addEventListener(
      "touchmove",
      (e) => {
        if (!e.touches || !e.touches[0]) return;
        onMove(e.touches[0].clientX, e.touches[0].clientY);
      },
      { passive: true }
    );
    card.addEventListener("touchend", reset);

    // start in a nice default
    reset();

    function clamp(v, a, b) {
      return Math.max(a, Math.min(b, v));
    }
  }

  function initParticles() {
    const canvas = document.getElementById("eggParticles");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    let w = 0;
    let h = 0;
    const dpr = Math.max(1, window.devicePixelRatio || 1);

    function resize() {
      w = Math.floor(window.innerWidth);
      h = Math.floor(window.innerHeight);
      canvas.width = Math.floor(w * dpr);
      canvas.height = Math.floor(h * dpr);
      canvas.style.width = w + "px";
      canvas.style.height = h + "px";
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    resize();
    window.addEventListener("resize", resize);

    const colors = [
      "rgba(162,64,255,0.65)",
      "rgba(255,102,216,0.55)",
      "rgba(92,242,255,0.45)",
    ];

    const count = Math.min(90, Math.max(40, Math.floor((w * h) / 26000)));
    const pts = Array.from({ length: count }, () => makeParticle());

    function makeParticle() {
      const r = rand(0.8, 2.4);
      return {
        x: rand(0, w),
        y: rand(0, h),
        vx: rand(-0.18, 0.18),
        vy: rand(-0.22, 0.22),
        r,
        c: colors[Math.floor(rand(0, colors.length))],
        a: rand(0.18, 0.55),
      };
    }

    let last = performance.now();
    function tick(now) {
      const dt = Math.min(32, now - last);
      last = now;

      ctx.clearRect(0, 0, w, h);

      // soft glow background
      ctx.globalCompositeOperation = "lighter";

      for (const p of pts) {
        p.x += p.vx * dt;
        p.y += p.vy * dt;

        if (p.x < -20) p.x = w + 20;
        if (p.x > w + 20) p.x = -20;
        if (p.y < -20) p.y = h + 20;
        if (p.y > h + 20) p.y = -20;

        ctx.beginPath();
        ctx.fillStyle = p.c;
        ctx.globalAlpha = p.a;
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      }

      // faint connections (keep it subtle so it feels premium, not noisy)
      ctx.globalAlpha = 0.07;
      ctx.strokeStyle = "rgba(162,64,255,0.42)";
      for (let i = 0; i < pts.length; i++) {
        for (let j = i + 1; j < pts.length; j++) {
          const a = pts[i];
          const b = pts[j];
          const dx = a.x - b.x;
          const dy = a.y - b.y;
          const d2 = dx * dx + dy * dy;
          if (d2 < 115 * 115) {
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
          }
        }
      }

      ctx.globalAlpha = 1;
      ctx.globalCompositeOperation = "source-over";

      requestAnimationFrame(tick);
    }

    requestAnimationFrame(tick);

    function rand(min, max) {
      return Math.random() * (max - min) + min;
    }
  }
})();
