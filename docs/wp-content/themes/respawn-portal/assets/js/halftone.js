/* Fragline halftone wordmark: the slogan on the home screen is printed as a field of dots. Each dot is sized by how much
 * of the letter covers its cell; the dots part around the pointer on springs, warm toward the site's lime as they are
 * pushed off their place, and a click sends a shock ring through the word. Port of the HalftoneWordmarkFooter React
 * component to plain JS (no React build in the theme). The real text stays in the DOM for readers and search. */
(() => {
	const els = document.querySelectorAll('[data-halftone]');
	if (!els.length) return;
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	const hex = (h) => {
		const m = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec((h || '').trim());
		if (!m) return null;
		const s = m[1].length === 3 ? m[1].split('').map((c) => c + c).join('') : m[1];
		return [parseInt(s.slice(0, 2), 16), parseInt(s.slice(2, 4), 16), parseInt(s.slice(4, 6), 16)];
	};
	// Area-true dot radius: tone stays even whatever the dot gap.
	const radiusFor = (cov, gap) => Math.sqrt(Math.min(1, Math.max(0, cov))) * gap * 0.56;
	// One spring step toward home, plus the push from a pointer within radius r.
	const step = (x, y, vx, vy, hx, hy, px, py, r) => {
		const dx = x - px, dy = y - py, d2 = dx * dx + dy * dy;
		if (r > 0 && d2 < r * r) {
			const d = Math.sqrt(d2) + 0.001;
			const f = (1 - d / r) * 2.6;
			vx += (dx / d) * f;
			vy += (dy / d) * f;
		}
		vx = (vx + (hx - x) * 0.05) * 0.84;
		vy = (vy + (hy - y) * 0.05) * 0.84;
		return [x + vx, y + vy, vx, vy];
	};

	els.forEach((el) => {
		const text = (el.textContent || '').trim();
		if (!text) return;
		const cs = getComputedStyle(document.documentElement);
		const ink = hex('#EEF1F5');
		const warm = hex(cs.getPropertyValue('--accent-fill')) || [200, 255, 61];
		const family = '"Manrope", "Onest", system-ui, sans-serif';
		const weight = 800;

		const canvas = document.createElement('canvas');
		canvas.className = 'halftone__canvas';
		canvas.setAttribute('aria-hidden', 'true');
		el.append(canvas);
		const ctx = canvas.getContext('2d');
		if (!ctx) return;

		let w = 0, h = 0, dpr = 1, dots = new Float32Array(0), count = 0;
		let px = -1e5, py = -1e5, raf = 0, visible = true, awake = true;
		let ring = { x: 0, y: 0, t: -1 };

		const layout = () => {
			w = Math.max(1, Math.round(el.clientWidth));
			if (w < 2) return;
			dpr = Math.min(window.devicePixelRatio || 1, 2);
			const gap = w < 520 ? 4 : w < 900 ? 5 : 6;

			// Set the word once on a 1x mask at a size that fills the width.
			const probe = document.createElement('canvas').getContext('2d');
			probe.font = weight + ' 100px ' + family;
			const fit = (w / Math.max(1, probe.measureText(text).width)) * 100;
			probe.font = weight + ' ' + fit + 'px ' + family;
			const box = probe.measureText(text);
			const asc = box.actualBoundingBoxAscent || fit * 0.72;
			const desc = box.actualBoundingBoxDescent || fit * 0.2;
			const pad = gap * 2;
			h = Math.ceil(asc + desc + pad * 2);
			el.style.height = h + 'px';
			canvas.width = Math.round(w * dpr);
			canvas.height = Math.round(h * dpr);

			const mask = document.createElement('canvas');
			mask.width = w;
			mask.height = h;
			const m = mask.getContext('2d', { willReadFrequently: true });
			m.font = weight + ' ' + fit + 'px ' + family;
			m.fillStyle = '#000';
			m.textBaseline = 'alphabetic';
			m.fillText(text, (w - box.width) / 2, pad + asc);
			const img = m.getImageData(0, 0, w, h).data;

			const cols = Math.ceil(w / gap), rows = Math.ceil(h / gap), tmp = [];
			for (let gy = 0; gy < rows; gy++) {
				for (let gx = 0; gx < cols; gx++) {
					let sum = 0;
					for (let sy = 0; sy < 3; sy++) {
						for (let sx = 0; sx < 3; sx++) {
							const x = Math.min(w - 1, Math.floor(gx * gap + ((sx + 0.5) * gap) / 3));
							const y = Math.min(h - 1, Math.floor(gy * gap + ((sy + 0.5) * gap) / 3));
							sum += img[(y * w + x) * 4 + 3];
						}
					}
					const cov = sum / (9 * 255);
					if (cov < 0.06) continue;
					const hx = gx * gap + gap / 2, hy = gy * gap + gap / 2;
					tmp.push(hx, hy, 0, 0, hx, hy, radiusFor(cov, gap));
				}
			}
			dots = new Float32Array(tmp);
			count = tmp.length / 7;
			el.classList.add('has-halftone');
			awake = true;
		};

		const draw = (now) => {
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			ctx.clearRect(0, 0, w, h);
			const r = reduce ? 0 : 80;
			const age = ring.t < 0 ? 1e9 : now - ring.t;
			const ringR = age * 0.9;
			let moving = false;
			for (let i = 0; i < count; i++) {
				const j = i * 7;
				let x = dots[j], y = dots[j + 1], vx = dots[j + 2], vy = dots[j + 3];
				const hx = dots[j + 4], hy = dots[j + 5];
				if (!reduce) {
					if (age < 900) {
						const dx = hx - ring.x, dy = hy - ring.y;
						const d = Math.sqrt(dx * dx + dy * dy) + 0.001;
						const band = 1 - Math.min(1, Math.abs(d - ringR) / 26);
						if (band > 0) {
							const f = band * 3.2 * (1 - age / 900);
							vx += (dx / d) * f;
							vy += (dy / d) * f;
						}
					}
					const s = step(x, y, vx, vy, hx, hy, px, py, r);
					x = s[0]; y = s[1]; vx = s[2]; vy = s[3];
					if (Math.abs(vx) + Math.abs(vy) > 0.02 || Math.abs(x - hx) + Math.abs(y - hy) > 0.05) moving = true;
					else { x = hx; y = hy; vx = 0; vy = 0; }
					dots[j] = x; dots[j + 1] = y; dots[j + 2] = vx; dots[j + 3] = vy;
				}
				const k = Math.min(1, Math.hypot(x - hx, y - hy) / 28);
				ctx.fillStyle = k > 0.02
					? 'rgb(' + Math.round(ink[0] + (warm[0] - ink[0]) * k) + ',' + Math.round(ink[1] + (warm[1] - ink[1]) * k) + ',' + Math.round(ink[2] + (warm[2] - ink[2]) * k) + ')'
					: 'rgb(' + ink[0] + ',' + ink[1] + ',' + ink[2] + ')';
				ctx.beginPath();
				ctx.arc(x, y, dots[j + 6], 0, 6.2832);
				ctx.fill();
			}
			awake = moving || age < 900 || px > -1e4;
		};
		const loop = (now) => {
			raf = 0;
			if (!visible) return;
			draw(now);
			if (awake) raf = requestAnimationFrame(loop);
		};
		const wake = () => {
			awake = true;
			if (!raf && visible) raf = requestAnimationFrame(loop);
		};
		const local = (e) => {
			const b = canvas.getBoundingClientRect();
			return [e.clientX - b.left, e.clientY - b.top];
		};

		el.addEventListener('pointermove', (e) => { const p = local(e); px = p[0]; py = p[1]; wake(); });
		el.addEventListener('pointerleave', () => { px = -1e5; py = -1e5; wake(); });
		el.addEventListener('pointerdown', (e) => {
			if (reduce) return;
			const p = local(e);
			ring = { x: p[0], y: p[1], t: performance.now() };
			wake();
		});
		new ResizeObserver(() => { layout(); draw(performance.now()); wake(); }).observe(el);
		new IntersectionObserver((entries) => { visible = entries[0] ? entries[0].isIntersecting : true; if (visible) wake(); }).observe(el);

		// The face may arrive after first paint: set the word again once it is in.
		const start = () => { layout(); draw(performance.now()); wake(); };
		if (document.fonts && document.fonts.load) {
			document.fonts.load(weight + ' 100px "Manrope"', text).then(start, start);
		} else {
			start();
		}
	});
})();
