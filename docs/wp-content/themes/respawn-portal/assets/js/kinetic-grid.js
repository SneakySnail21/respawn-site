/* Fragline kinetic grid: a fixed canvas behind the whole site. The grid is static; the lines and nodes
 * near the pointer light up in the site's green. Port of the KineticGrid React
 * component to plain JS (the theme has no React build). Redraws only while something moves, pauses in background tabs. */
(() => {
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const small = window.matchMedia('(max-width: 640px)').matches;

	const CELL = small ? 44 : 55;
	const INFLUENCE = small ? 230 : 380;
	const LERP = 0.16;
	const NODE_R = 1.6;
	const NODE_R_ON = 3;

	const canvas = document.createElement('canvas');
	canvas.className = 'kgrid';
	canvas.setAttribute('aria-hidden', 'true');
	document.body.prepend(canvas);
	const ctx = canvas.getContext('2d');
	if (!ctx) return;

	let W = 0, H = 0, dpr = 1, raf = 0, awake = true;
	const mouse = { x: -9999, y: -9999 };
	const target = { x: -9999, y: -9999 };
	let theme = null;

	const parse = (v, fallback) => {
		const m = /#([0-9a-f]{6})/i.exec(v || '') || /#([0-9a-f]{3})\b/i.exec(v || '');
		if (!m) return fallback;
		let h = m[1];
		if (h.length === 3) h = h.split('').map((c) => c + c).join('');
		return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
	};
	const readTheme = () => {
		const light = document.documentElement.dataset.theme === 'light';
		const cs = getComputedStyle(document.documentElement);
		const accent = parse(cs.getPropertyValue('--accent'), light ? [85, 130, 10] : [200, 255, 61]);
		theme = {
			base: light ? [17, 20, 25] : [255, 255, 255],
			lineA: light ? 0.09 : 0.11,
			nodeA: light ? 0.16 : 0.2,
			active: accent,
			lineOn: light ? 0.85 : 0.9,
		};
		awake = true;
		wake();
	};

	const lerp = (a, b, t) => a + (b - a) * t;
	const rgba = (c, a) => 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a.toFixed(3) + ')';
	const mix = (c1, a1, c2, a2, t) => 'rgba(' + Math.round(lerp(c1[0], c2[0], t)) + ',' + Math.round(lerp(c1[1], c2[1], t)) + ',' + Math.round(lerp(c1[2], c2[2], t)) + ',' + lerp(a1, a2, t).toFixed(3) + ')';

	const resize = () => {
		W = window.innerWidth;
		H = window.innerHeight;
		dpr = Math.min(window.devicePixelRatio || 1, small ? 1.5 : 2);
		canvas.width = Math.round(W * dpr);
		canvas.height = Math.round(H * dpr);
		awake = true;
		wake();
	};

	// Grid points stay put; only the highlight strength (0..1) near the pointer is computed.
	const warp = (gx, gy, col, row, cols, rows) => {
		const edge = 1.5;
		const pinC = Math.min(col / edge, (cols - 1 - col) / edge, 1);
		const pinR = Math.min(row / edge, (rows - 1 - row) / edge, 1);
		const pin = pinC * pinC * pinR * pinR;
		const dist = Math.hypot(gx - mouse.x, gy - mouse.y);
		return [gx, gy, Math.max(0, 1 - dist / INFLUENCE) * pin];
	};

	const draw = (now) => {
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		ctx.clearRect(0, 0, W, H);

		const cols = Math.max(2, Math.ceil(W / CELL)) + 1;
		const rows = Math.max(2, Math.ceil(H / CELL)) + 1;
		const cw = W / (cols - 1);
		const ch = H / (rows - 1);
		const px = new Float32Array(cols * rows);
		const py = new Float32Array(cols * rows);
		const pr = new Float32Array(cols * rows);
		for (let row = 0; row < rows; row++) {
			for (let col = 0; col < cols; col++) {
				const k = row * cols + col;
				const p = warp(col * cw, row * ch, col, row, cols, rows, now);
				px[k] = p[0]; py[k] = p[1]; pr[k] = p[2];
			}
		}

		// Calm segments are batched into one path; only the lit ones are drawn one by one.
		const lit = [];
		ctx.lineCap = 'butt';
		ctx.lineWidth = 0.8;
		ctx.strokeStyle = rgba(theme.base, theme.lineA);
		ctx.beginPath();
		const seg = (a, b) => {
			const avg = (pr[a] + pr[b]) / 2;
			if (avg < 0.002) {
				ctx.moveTo(px[a], py[a]);
				ctx.lineTo(px[b], py[b]);
			} else {
				lit.push(a, b, avg);
			}
		};
		for (let row = 0; row < rows; row++) for (let col = 0; col < cols - 1; col++) seg(row * cols + col, row * cols + col + 1);
		for (let col = 0; col < cols; col++) for (let row = 0; row < rows - 1; row++) seg(row * cols + col, (row + 1) * cols + col);
		ctx.stroke();
		for (let i = 0; i < lit.length; i += 3) {
			const t = lit[i + 2] * lit[i + 2] * (3 - 2 * lit[i + 2]);
			ctx.beginPath();
			ctx.moveTo(px[lit[i]], py[lit[i]]);
			ctx.lineTo(px[lit[i + 1]], py[lit[i + 1]]);
			ctx.strokeStyle = mix(theme.base, theme.lineA, theme.active, theme.lineOn, t);
			ctx.lineWidth = lerp(0.8, 2.2, t);
			ctx.stroke();
		}

		// Nodes: calm ones in one fill, lit ones with a soft glow.
		ctx.fillStyle = rgba(theme.base, theme.nodeA);
		ctx.beginPath();
		const glow = [];
		for (let k = 0; k < cols * rows; k++) {
			if (pr[k] < 0.002) {
				ctx.moveTo(px[k] + NODE_R, py[k]);
				ctx.arc(px[k], py[k], NODE_R, 0, 6.2832);
			} else {
				glow.push(k);
			}
		}
		ctx.fill();
		for (const k of glow) {
			const t = pr[k] * pr[k] * (3 - 2 * pr[k]);
			const r = lerp(NODE_R, NODE_R_ON, t);
			if (t > 0.3) {
				const gr = r + lerp(0, 6, (t - 0.3) / 0.7);
				const g = ctx.createRadialGradient(px[k], py[k], r * 0.5, px[k], py[k], gr);
				g.addColorStop(0, rgba(theme.active, t * 0.32));
				g.addColorStop(1, rgba(theme.active, 0));
				ctx.beginPath();
				ctx.arc(px[k], py[k], gr, 0, 6.2832);
				ctx.fillStyle = g;
				ctx.fill();
			}
			ctx.beginPath();
			ctx.arc(px[k], py[k], r, 0, 6.2832);
			ctx.fillStyle = mix(theme.base, theme.nodeA, theme.active, 1, t);
			ctx.fill();
		}

	};

	const loop = (now) => {
		raf = 0;
		if (document.hidden) return;
		mouse.x = lerp(mouse.x, target.x, LERP);
		mouse.y = lerp(mouse.y, target.y, LERP);
		draw(now);
		const settled = Math.abs(mouse.x - target.x) < 0.5 && Math.abs(mouse.y - target.y) < 0.5;
		awake = !settled;
		if (awake) raf = requestAnimationFrame(loop);
	};
	function wake() {
		if (!theme) return;
		if (!raf && !document.hidden) raf = requestAnimationFrame(loop);
	}

	readTheme();
	resize();
	window.addEventListener('resize', resize, { passive: true });
	document.addEventListener('visibilitychange', () => { if (!document.hidden) wake(); });
	new MutationObserver(readTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

	if (reduce) return;

	window.addEventListener('pointermove', (e) => {
		if (target.x === -9999) { mouse.x = e.clientX; mouse.y = e.clientY; }
		target.x = e.clientX;
		target.y = e.clientY;
		wake();
	}, { passive: true });
	document.documentElement.addEventListener('pointerleave', () => { target.x = -9999; target.y = -9999; mouse.x = -9999; mouse.y = -9999; wake(); });
})();
