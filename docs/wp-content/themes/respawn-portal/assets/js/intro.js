/**
 * Fragline intro: one line on a canvas. It is drawn across the screen, reshapes into the live Steam pulse of
 * the top games, collapses into the Fragline mark, then catches the fresh headlines as "frags" and settles
 * into a calm idle wave behind the slogan panel. About ten seconds, once per browser session, skippable.
 */
(() => {
	const root = document.getElementById('rsp-intro');
	if (!root) return;
	let data = {};
	try { data = JSON.parse(document.getElementById('rsp-intro-data').textContent || '{}'); } catch { /* no data: still plays */ }
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const canvas = root.querySelector('.intro__canvas');
	const ctx = canvas.getContext('2d');
	const scenes = {};
	root.querySelectorAll('.iscene').forEach((s) => {
		const key = [...s.classList].find((c) => c.startsWith('iscene--'));
		if (key) scenes[key.slice(8)] = s;
	});
	const progress = root.querySelector('.intro__progress span');
	const ACCENT = '#C8FF3D';
	const TOTAL = 10400;

	// Canvas state: the line is N samples of a normalised offset; `cur` eases toward `tgt` every frame.
	const N = 160;
	const cur = new Float32Array(N);
	const tgt = new Float32Array(N);
	let W = 0, H = 0;
	let reveal = 0, extent = 1, lineAlpha = 1, amp = 0, logoP = 0, logoAlpha = 0;
	let idle = false, idleT = 0, playing = false;
	const parts = [];
	const timers = [];

	const resize = () => {
		const dpr = Math.min(2, window.devicePixelRatio || 1);
		W = root.clientWidth; H = root.clientHeight;
		canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
	};
	resize();
	window.addEventListener('resize', resize);

	// The mark in a 64-unit box: the F stem with its top bar, and the pulse that is its middle arm.
	const LOGO = [[[19, 51], [19, 13], [46, 13]], [[19, 33], [26, 33], [31, 23], [37, 43], [42, 33], [49, 33]]];
	const segLen = (a, b) => Math.hypot(b[0] - a[0], b[1] - a[1]);
	const logoLen = LOGO.map((p) => p.slice(1).reduce((l, pt, i) => l + segLen(p[i], pt), 0));
	const logoTotal = logoLen[0] + logoLen[1];

	const setTarget = (fn) => { for (let i = 0; i < N; i++) tgt[i] = fn(i / (N - 1)); };
	const flat = () => setTarget(() => 0);
	const wave = (t) => setTarget((x) => Math.sin(x * Math.PI * 3 + t) * 0.12 * Math.sin(x * Math.PI));
	const seriesShape = (vals) => {
		if (!Array.isArray(vals) || vals.length < 2) { wave(0); return; }
		const min = Math.min(...vals), max = Math.max(...vals), span = max - min || 1;
		setTarget((x) => {
			const f = x * (vals.length - 1), i = Math.floor(f), k = f - i;
			const v = vals[i] + (vals[Math.min(vals.length - 1, i + 1)] - vals[i]) * k;
			return -((v - min) / span - 0.5) * 1.6;
		});
	};
	const burst = (x, y, n) => {
		for (let i = 0; i < n; i++) {
			const a = Math.random() * Math.PI * 2, s = 1.5 + Math.random() * 4;
			parts.push({ x, y, vx: Math.cos(a) * s, vy: Math.sin(a) * s, life: 1 });
		}
	};

	const draw = (dt) => {
		ctx.clearRect(0, 0, W, H);
		for (let i = 0; i < N; i++) cur[i] += (tgt[i] - cur[i]) * 0.14;
		const cy = H * 0.46, A = Math.min(H * 0.24, 150) * amp;
		const x0 = W * 0.5 * (1 - extent), x1 = W - x0;
		const n = Math.max(2, Math.floor(N * reveal));
		if (lineAlpha > 0.01 && reveal > 0.01 && extent > 0.01) {
			const path = () => {
				ctx.beginPath();
				for (let i = 0; i < n; i++) {
					const x = x0 + (x1 - x0) * i / (N - 1), y = cy + cur[i] * A;
					i ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
				}
			};
			ctx.save();
			ctx.globalAlpha = lineAlpha; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
			ctx.strokeStyle = ACCENT; ctx.lineWidth = 2.5;
			ctx.shadowColor = 'rgba(200, 255, 61, .55)'; ctx.shadowBlur = 18;
			path(); ctx.stroke();
			if (reveal < 1) {
				const hx = x0 + (x1 - x0) * (n - 1) / (N - 1), hy = cy + cur[n - 1] * A;
				ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(hx, hy, 4, 0, Math.PI * 2); ctx.fill();
			}
			ctx.restore();
		}
		if (logoAlpha > 0.01 && logoP > 0) {
			const S = Math.min(W, H) * 0.3 / 64, ox = W / 2 - 32 * S, oy = cy - 40 * S;
			ctx.save();
			ctx.globalAlpha = logoAlpha; ctx.strokeStyle = ACCENT; ctx.lineWidth = 7 * S; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
			ctx.shadowColor = 'rgba(200, 255, 61, .5)'; ctx.shadowBlur = 26;
			LOGO.forEach((poly, pi) => {
				let budget = logoP * logoTotal - (pi ? logoLen[0] : 0);
				if (budget <= 0) return;
				ctx.beginPath();
				ctx.moveTo(ox + poly[0][0] * S, oy + poly[0][1] * S);
				for (let i = 1; i < poly.length && budget > 0; i++) {
					const a = poly[i - 1], b = poly[i], seg = segLen(a, b), k = Math.min(1, budget / seg);
					ctx.lineTo(ox + (a[0] + (b[0] - a[0]) * k) * S, oy + (a[1] + (b[1] - a[1]) * k) * S);
					budget -= seg;
				}
				ctx.stroke();
			});
			ctx.restore();
		}
		for (let i = parts.length - 1; i >= 0; i--) {
			const p = parts[i];
			p.x += p.vx; p.y += p.vy; p.vy += 0.08; p.life -= dt / 700;
			if (p.life <= 0) { parts.splice(i, 1); continue; }
			ctx.globalAlpha = p.life; ctx.fillStyle = ACCENT; ctx.fillRect(p.x, p.y, 3, 3);
		}
		ctx.globalAlpha = 1;
	};
	let last = performance.now();
	const loop = (t) => {
		const dt = Math.min(50, t - last); last = t; idleT += dt / 1000;
		if (idle) wave(idleT * 0.8);
		draw(dt);
		requestAnimationFrame(loop);
	};

	// Timeline helpers.
	const at = (ms, fn) => timers.push(setTimeout(fn, ms));
	const clearAll = () => { timers.forEach(clearTimeout); timers.length = 0; };
	const tween = (ms, fn) => {
		const t0 = performance.now();
		const step = (t) => {
			const p = Math.min(1, (t - t0) / ms);
			fn(1 - Math.pow(1 - p, 3));
			if (p < 1 && playing) requestAnimationFrame(step);
		};
		requestAnimationFrame(step);
	};
	const show = (k) => { Object.values(scenes).forEach((s) => s.classList.remove('is-on')); scenes[k]?.classList.add('is-on'); };
	const typeInto = (el, text, ms) => {
		el.textContent = '';
		let i = 0;
		const iv = setInterval(() => { el.textContent = text.slice(0, ++i); if (i >= text.length) clearInterval(iv); }, ms / Math.max(1, text.length));
		timers.push(iv);
	};
	const countUp = (el, end, ms) => {
		const t0 = performance.now();
		const step = (t) => {
			const p = Math.min(1, (t - t0) / ms);
			el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString('ru-RU');
			if (p < 1 && playing) requestAnimationFrame(step);
		};
		requestAnimationFrame(step);
	};

	const runFrags = () => {
		const box = scenes.frags.querySelector('.ifrags');
		const cnt = scenes.frags.querySelector('.ifrags__count b');
		box.innerHTML = ''; cnt.textContent = '0';
		let k = 0;
		(data.news || []).slice(0, 5).forEach((n, i) => at(i * 430, () => {
			const c = document.createElement('div');
			c.className = 'ifrag';
			const cat = document.createElement('span'); cat.className = 'ifrag__cat'; cat.textContent = n.cat || 'Fragline';
			const title = document.createElement('span'); title.className = 'ifrag__title'; title.textContent = n.title;
			c.append(cat, title);
			box.append(c);
			at(480, () => { burst(W / 2, H * 0.46, 16); cnt.textContent = String(++k); c.classList.add('is-hit'); });
			at(1700, () => c.remove());
		}));
	};

	const finish = () => {
		if (!playing && root.classList.contains('is-done')) return;
		playing = false;
		clearAll();
		Object.values(scenes).forEach((s) => s.classList.remove('is-on'));
		root.classList.add('is-done');
		idle = true; amp = 0.35; extent = 1; reveal = 1; lineAlpha = 0.5; logoAlpha = 0;
		try { sessionStorage.setItem('fl-intro', '1'); } catch { /* storage blocked */ }
	};

	const play = () => {
		clearAll();
		playing = true; idle = false; parts.length = 0;
		root.classList.remove('is-done', 'is-quick');
		cur.fill(0); tgt.fill(0);
		reveal = 0; extent = 1; lineAlpha = 1; amp = 0; logoP = 0; logoAlpha = 0;
		const t0 = performance.now();
		const prog = () => { if (!playing) return; progress.style.transform = `scaleX(${Math.min(1, (performance.now() - t0) / TOTAL)})`; requestAnimationFrame(prog); };
		prog();

		// 1. The line is drawn.
		show('boot');
		typeInto(scenes.boot.querySelector('[data-type]'), data.boot || 'fragline // линия инициализирована', 900);
		tween(1400, (p) => { reveal = p; });

		// 2. Live pulse of the top games.
		const games = (data.series || []).slice(0, 3);
		const nameEl = scenes.pulse.querySelector('.ipulse__name'), numEl = scenes.pulse.querySelector('.ipulse__num');
		at(1500, () => { show('pulse'); amp = 1; });
		if (games.length) {
			games.forEach((g, i) => at(1500 + i * 1050, () => {
				seriesShape(g.vals);
				nameEl.textContent = g.name; numEl.textContent = Number(g.now || 0).toLocaleString('ru-RU');
				[nameEl, numEl].forEach((el) => { el.classList.remove('is-in'); void el.offsetWidth; el.classList.add('is-in'); });
			}));
		} else {
			at(1500, () => { wave(0); nameEl.textContent = 'Радар'; numEl.textContent = 'собирает данные'; nameEl.classList.add('is-in'); numEl.classList.add('is-in'); });
		}

		// 3. The line folds into the mark, the name appears.
		at(4700, () => { flat(); show('logo'); tween(700, (p) => { extent = 1 - p; lineAlpha = 1 - p; }); });
		at(5100, () => { logoAlpha = 1; tween(1000, (p) => { logoP = p; }); });
		at(5900, () => {
			const word = scenes.logo.querySelector('.ilogo__word');
			word.innerHTML = '';
			'Fragline'.split('').forEach((ch, i) => { const s = document.createElement('span'); s.textContent = ch; s.style.setProperty('--d', (i * 60) + 'ms'); word.append(s); });
			scenes.logo.classList.add('is-word');
		});

		// 4. Fresh headlines hit the line.
		at(6900, () => {
			show('frags');
			tween(500, (p) => { logoAlpha = 1 - p; });
			cur.fill(0); flat(); amp = 0.25; extent = 1; reveal = 1;
			tween(500, (p) => { lineAlpha = p; });
			runFrags();
		});

		// 5. Numbers, then the panel.
		at(9100, () => { show('stats'); amp = 0.08; scenes.stats.querySelectorAll('[data-to]').forEach((el) => countUp(el, Number(el.dataset.to) || 0, 900)); });
		at(TOTAL, finish);
	};

	const quick = () => {
		root.classList.add('is-done', 'is-quick');
		idle = true; amp = 0.35; extent = 1; reveal = 1; lineAlpha = 0.5; logoAlpha = 0;
	};

	root.querySelector('.intro__skip')?.addEventListener('click', finish);
	root.querySelector('.intro__replay')?.addEventListener('click', () => { scenes.logo?.classList.remove('is-word'); play(); });
	root.querySelector('.intro__go')?.addEventListener('click', (e) => {
		e.preventDefault();
		document.getElementById('explore')?.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
	});
	document.addEventListener('visibilitychange', () => { if (document.hidden && playing) finish(); });

	let seen = false;
	try { seen = !!sessionStorage.getItem('fl-intro'); } catch { /* storage blocked */ }
	if (reduce) {
		quick(); idle = false; wave(0); cur.set(tgt); draw(0);
	} else {
		requestAnimationFrame(loop);
		if (seen) quick(); else play();
	}
})();
