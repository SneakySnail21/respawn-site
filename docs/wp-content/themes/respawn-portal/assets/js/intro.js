/**
 * Fragline intro v2: "the line has a pulse".
 * A flat monitor line searches for a signal, catches a heartbeat and erupts into the live Steam online of the
 * top games, sums them into one big number, folds into the Fragline mark with a glitch shockwave, then the fresh
 * headlines land in a gamer kill feed and the screen settles on the slogan. The line then lives behind the
 * slogan: it beats every few seconds, bends to the pointer, a click snaps it. About 8.5 s, once per 30 minutes,
 * skippable; phones play it too (weak devices / save-data / reduced motion get the calm panel at once).
 */
(() => {
	const root = document.getElementById('rsp-intro');
	if (!root) return;
	let data = {};
	try { data = JSON.parse(document.getElementById('rsp-intro-data').textContent || '{}'); } catch { /* no data: still plays */ }
	const EN = (document.documentElement.lang || '').toLowerCase().startsWith('en');
	const T = (ru, en) => (EN ? en : ru);
	const LOCALE = EN ? 'en-US' : 'ru-RU';
	const fmt = (n) => Math.round(n).toLocaleString(LOCALE);
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const canvas = root.querySelector('.intro__canvas');
	const ctx = canvas.getContext('2d');
	const stage = root.querySelector('.intro__stage');
	const slogan = root.querySelector('.intro__slogan');
	const scenes = {};
	root.querySelectorAll('.iscene').forEach((s) => {
		const key = [...s.classList].find((c) => c.startsWith('iscene--'));
		if (key) scenes[key.slice(8)] = s;
	});
	const progress = root.querySelector('.intro__progress span');
	const ACCENT = '#C8FF3D';
	const GLOW = 'rgba(200, 255, 61, .55)';
	const TOTAL = 8500;
	const IDLE_AMP = 0.35;

	// ---------- Canvas state ----------
	// The line is N samples of a normalised offset; `cur` eases toward `tgt` every frame.
	const N = 180;
	const cur = new Float32Array(N);
	const tgt = new Float32Array(N);
	let W = 0, H = 0, small = false;
	let reveal = 0, extent = 1, lineAlpha = 1, amp = 0, logoP = 0, logoAlpha = 0, fill = 0, flash = 0, glitch = 0, scan = -1;
	let cy = 0, cyTgt = 0;
	let idle = false, idleT = 0, playing = false, nextBeat = 0;
	const parts = [];
	const rings = [];
	const beats = [];
	const timers = [];

	const ampPx = () => Math.min(H * 0.24, 150);
	// After the intro the line runs through the slogan ("hold the line"), not through the small text.
	const idleY = () => {
		if (!slogan) return H * 0.42;
		const r = root.getBoundingClientRect(), s = slogan.getBoundingClientRect();
		return s.height ? s.top - r.top + s.height * 0.56 : H * 0.42;
	};
	const resize = () => {
		const dpr = Math.min(2, window.devicePixelRatio || 1);
		W = root.clientWidth; H = root.clientHeight; small = W < 700;
		canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		cyTgt = idle ? idleY() : H * 0.5;
		if (!cy) cy = cyTgt;
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
	const wave = (t, a = 0.12) => setTarget((x) => Math.sin(x * Math.PI * 3 + t) * a * Math.sin(x * Math.PI));
	const seriesShape = (vals) => {
		if (!Array.isArray(vals) || vals.length < 2) { wave(0, 0.4); return; }
		const min = Math.min(...vals), max = Math.max(...vals), span = max - min || 1;
		setTarget((x) => {
			const f = x * (vals.length - 1), i = Math.floor(f), k = f - i;
			const v = vals[i] + (vals[Math.min(vals.length - 1, i + 1)] - vals[i]) * k;
			return -((v - min) / span - 0.5) * 1.6 * Math.min(1, Math.sin(x * Math.PI) * 6);
		});
	};

	// One heartbeat (ECG P-QRS-T) as a function of distance from the beat centre, in line widths. Negative = up.
	const G = (d, w) => Math.exp(-(d * d) / (2 * w * w));
	const ecg = (d) => -0.12 * G(d + 0.075, 0.014) + 0.22 * G(d + 0.012, 0.005) - 1.15 * G(d, 0.006) + 0.42 * G(d - 0.014, 0.006) - 0.2 * G(d - 0.07, 0.02);
	const sstep = (a, b, v) => { const t = Math.max(0, Math.min(1, (v - a) / (b - a))); return t * t * (3 - 2 * t); };
	// The wave changes while it travels (p = 0..1 across the line): a sharp heartbeat spike spreads out, rounds
	// into a soft swell, loses height, and leaves a small damped ripple behind it.
	const pulse = (d, p) => {
		const k = 1 + 2.6 * p;
		const sharp = ecg(d / k);
		const swell = -0.75 * G(d, 0.026 * k);
		const m = sstep(0.1, 0.8, p);
		const ripple = d < 0 ? Math.sin(d * 95 / (1 + 1.5 * p)) * Math.exp(d / (0.045 * k)) * 0.16 * Math.min(1, p * 4) : 0;
		return (sharp * (1 - m) + swell * m + ripple) * (1 - 0.55 * p);
	};
	const beat = (speed = 1.15, size = 1) => beats.push({ pos: -0.1, speed, size, hit: false });

	const burst = (x, y, n, power = 4) => {
		for (let i = 0; i < n; i++) {
			const a = Math.random() * Math.PI * 2, s = 1 + Math.random() * power;
			parts.push({ x, y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 1, life: 1, size: Math.random() < 0.3 ? 4 : 2.5 });
		}
	};
	const ring = (x, y, max = 260) => rings.push({ x, y, r: 6, max, life: 1 });
	const shake = (ms = 260, px = 7) => {
		if (reduce) return;
		const t0 = performance.now();
		const step = (t) => {
			const p = (t - t0) / ms;
			if (p >= 1) { canvas.style.transform = ''; stage.style.transform = ''; return; }
			const k = px * (1 - p);
			const tr = `translate(${(Math.random() * 2 - 1) * k}px, ${(Math.random() * 2 - 1) * k}px)`;
			canvas.style.transform = tr; stage.style.transform = tr;
			requestAnimationFrame(step);
		};
		requestAnimationFrame(step);
	};

	// ---------- Drawing ----------
	const ptr = { x: -1, y: 0, on: false, px: 0, py: 0, energy: 0 };
	const draw = (dt) => {
		ctx.clearRect(0, 0, W, H);
		for (let i = 0; i < N; i++) cur[i] += (tgt[i] - cur[i]) * 0.14;
		cy += (cyTgt - cy) * 0.08;
		for (let i = beats.length - 1; i >= 0; i--) {
			const b = beats[i];
			b.pos += b.speed * dt / 1000;
			if (!b.hit && b.pos > 0.5) { b.hit = true; if (idle) { root.classList.remove('is-beat'); void root.offsetWidth; root.classList.add('is-beat'); } }
			if (b.pos > 1.15) beats.splice(i, 1);
		}
		const A = ampPx() * amp;
		const beatA = ampPx() * (idle ? 0.2 : 0.4) * (small ? 0.85 : 1);
		const x0 = W * 0.5 * (1 - extent), x1 = W - x0;
		const n = Math.max(2, Math.floor(N * reveal));
		const X = (i) => x0 + (x1 - x0) * i / (N - 1);
		const Y = (i) => {
			const x = i / (N - 1);
			let v = cur[i] * A;
			const env = Math.min(1, Math.sin(Math.PI * x) * 4);
			for (const b of beats) v += pulse((x - b.pos) * (small ? 0.75 : 1), Math.max(0, Math.min(1, b.pos))) * b.size * beatA * env;
			return cy + v;
		};

		if (lineAlpha > 0.01 && reveal > 0.01 && extent > 0.01) {
			const path = () => { ctx.beginPath(); for (let i = 0; i < n; i++) { const x = X(i), y = Y(i); if (i) ctx.lineTo(x, y); else ctx.moveTo(x, y); } };
			ctx.save();
			ctx.globalAlpha = lineAlpha; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
			// Chart fill under the line while it shows the online curve.
			if (fill > 0.01) {
				path(); ctx.lineTo(X(n - 1), H); ctx.lineTo(X(0), H); ctx.closePath();
				const g = ctx.createLinearGradient(0, cy - A, 0, H);
				g.addColorStop(0, `rgba(200, 255, 61, ${0.16 * fill})`); g.addColorStop(1, 'rgba(200, 255, 61, 0)');
				ctx.fillStyle = g; ctx.fill();
			}
			ctx.strokeStyle = ACCENT; ctx.lineWidth = idle ? 3 : 2.6;
			ctx.shadowColor = GLOW; ctx.shadowBlur = small ? 10 : (idle ? 22 + ptr.energy * 40 : 18);
			path(); ctx.stroke();
			// A bright head that runs ahead of the line while it is drawn.
			if (reveal < 1) {
				const hx = X(n - 1), hy = Y(n - 1);
				ctx.fillStyle = '#fff'; ctx.shadowColor = '#fff'; ctx.shadowBlur = 20;
				ctx.beginPath(); ctx.arc(hx, hy, 4, 0, Math.PI * 2); ctx.fill();
			}
			// Each heartbeat carries a white-hot core.
			for (const b of beats) {
				if (b.pos < 0 || b.pos > 1) continue;
				const ic = Math.round(b.pos * (N - 1));
				const from = Math.max(0, ic - 10), to = Math.min(n - 1, ic + 4);
				if (to <= from) continue;
				ctx.beginPath();
				for (let i = from; i <= to; i++) { const x = X(i), y = Y(i); if (i === from) ctx.moveTo(x, y); else ctx.lineTo(x, y); }
				ctx.globalAlpha = lineAlpha * Math.max(0, 1 - b.pos * 1.25) * 0.9;
				ctx.strokeStyle = '#F4FFD6'; ctx.lineWidth = 3; ctx.shadowColor = '#fff'; ctx.shadowBlur = 16; ctx.stroke();
			}
			ctx.restore();
		}

		// Scanner glow sweeping the dead line at boot.
		if (scan >= 0 && scan <= 1) {
			const sx = x0 + (x1 - x0) * scan;
			const g = ctx.createRadialGradient(sx, cy, 0, sx, cy, 90);
			g.addColorStop(0, 'rgba(200, 255, 61, .35)'); g.addColorStop(1, 'rgba(200, 255, 61, 0)');
			ctx.fillStyle = g; ctx.fillRect(sx - 90, cy - 90, 180, 180);
		}

		if (logoAlpha > 0.01 && logoP > 0) {
			const S = Math.min(W, H) * (small ? 0.36 : 0.3) / 64, ox = W / 2 - 32 * S, oy = cy - 40 * S;
			const strokeLogo = (dx, color, alpha, blur) => {
				ctx.save();
				ctx.globalAlpha = logoAlpha * alpha; ctx.strokeStyle = color; ctx.lineWidth = 7 * S; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
				ctx.shadowColor = GLOW; ctx.shadowBlur = blur;
				LOGO.forEach((poly, pi) => {
					let budget = logoP * logoTotal - (pi ? logoLen[0] : 0);
					if (budget <= 0) return;
					ctx.beginPath();
					ctx.moveTo(ox + dx + poly[0][0] * S, oy + poly[0][1] * S);
					for (let i = 1; i < poly.length && budget > 0; i++) {
						const a = poly[i - 1], b = poly[i], seg = segLen(a, b), k = Math.min(1, budget / seg);
						ctx.lineTo(ox + dx + (a[0] + (b[0] - a[0]) * k) * S, oy + (a[1] + (b[1] - a[1]) * k) * S);
						budget -= seg;
					}
					ctx.stroke();
				});
				ctx.restore();
			};
			if (glitch > 0.02) {
				const off = glitch * 14 * (Math.random() < 0.5 ? -1 : 1);
				strokeLogo(off, '#FFFFFF', 0.35 * glitch, 0);
				strokeLogo(-off * 0.7, '#5E7A12', 0.6 * glitch, 0);
			}
			strokeLogo(0, ACCENT, 1, 26);
		}

		for (let i = rings.length - 1; i >= 0; i--) {
			const r = rings[i];
			r.life -= dt / 650; r.r += (r.max - r.r) * 0.09;
			if (r.life <= 0) { rings.splice(i, 1); continue; }
			ctx.save(); ctx.globalAlpha = r.life * 0.8; ctx.strokeStyle = ACCENT; ctx.lineWidth = 2 + r.life * 3;
			ctx.shadowColor = GLOW; ctx.shadowBlur = 16;
			ctx.beginPath(); ctx.arc(r.x, r.y, r.r, 0, Math.PI * 2); ctx.stroke(); ctx.restore();
		}
		for (let i = parts.length - 1; i >= 0; i--) {
			const p = parts[i];
			p.x += p.vx; p.y += p.vy; p.vy += 0.09; p.vx *= 0.985; p.life -= dt / 800;
			if (p.life <= 0) { parts.splice(i, 1); continue; }
			ctx.globalAlpha = p.life; ctx.fillStyle = p.size > 3 ? '#F4FFD6' : ACCENT; ctx.fillRect(p.x, p.y, p.size, p.size);
		}
		ctx.globalAlpha = 1;
		if (flash > 0.01) {
			ctx.fillStyle = `rgba(214, 255, 120, ${flash * 0.2})`; ctx.fillRect(0, 0, W, H);
			flash *= 0.82;
		}
	};

	// ---------- After the intro: a live string ----------
	// It bends toward the pointer, a fast sweep plucks it, a click snaps it, and it beats every few seconds.
	const pull = () => {
		const A = ampPx() * IDLE_AMP;
		ptr.energy *= 0.94;
		for (let i = 0; i < N; i++) {
			const x = i / (N - 1);
			tgt[i] *= 1 + ptr.energy * 5;
			if (ptr.on) {
				const d = (x * W - ptr.x) / (W * 0.12);
				const k = Math.exp(-d * d);
				const want = Math.max(-2.6, Math.min(2.6, (ptr.y - cy) / A));
				tgt[i] += (want - tgt[i]) * k * 0.9;
			}
		}
	};
	if (!reduce) {
		window.addEventListener('pointermove', (e) => {
			if (!idle || e.pointerType === 'touch') return;
			const r = root.getBoundingClientRect();
			const inside = e.clientY >= r.top && e.clientY <= r.bottom;
			ptr.on = inside;
			if (!inside) return;
			ptr.x = e.clientX - r.left; ptr.y = e.clientY - r.top;
			ptr.energy = Math.min(0.5, ptr.energy + Math.hypot(ptr.x - ptr.px, ptr.y - ptr.py) / 900);
			ptr.px = ptr.x; ptr.py = ptr.y;
		}, { passive: true });
		root.addEventListener('pointerleave', () => { ptr.on = false; });
		root.addEventListener('pointerdown', (e) => {
			if (!idle || e.target.closest('a, button')) return;
			const r = root.getBoundingClientRect();
			const x = e.clientX - r.left;
			const i = Math.round(Math.max(0, Math.min(1, x / W)) * (N - 1));
			const y = cy + (cur[i] || 0) * ampPx() * IDLE_AMP;
			burst(x, y, 26); ring(x, y, 120); ptr.energy = 0.5;
		});
	}
	let last = performance.now();
	const loop = (t) => {
		const dt = Math.min(50, t - last); last = t; idleT += dt / 1000;
		if (idle) {
			wave(idleT * 0.8, 0.3);
			if (!reduce) {
				pull();
				if (idleT > nextBeat) { beat(0.22, 1); nextBeat = idleT + 5 + Math.random() * 2.5; }
			}
		}
		draw(dt);
		requestAnimationFrame(loop);
	};

	// ---------- Timeline helpers ----------
	const at = (ms, fn) => timers.push(setTimeout(fn, ms));
	const clearAll = () => { timers.forEach((t) => { clearTimeout(t); clearInterval(t); }); timers.length = 0; };
	const ease = (p) => 1 - Math.pow(1 - p, 3);
	const tween = (ms, fn) => {
		const t0 = performance.now();
		const step = (t) => {
			const p = Math.min(1, (t - t0) / ms);
			fn(ease(p));
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
	const roll = (el, from, to, ms, suffix = '') => {
		const t0 = performance.now();
		const step = (t) => {
			const p = Math.min(1, (t - t0) / ms);
			el.textContent = fmt(from + (to - from) * ease(p));
			if (suffix) { const s = document.createElement('small'); s.textContent = suffix; el.append(s); }
			if (p < 1 && playing) requestAnimationFrame(step);
		};
		requestAnimationFrame(step);
	};
	// Text "decodes" from noise, left to right. Used for the logo word and the slogan.
	const GLYPHS = '▓▒░/<>_=+#*01FRAGLINE';
	const scramble = (el, text, ms) => {
		if (!el) return;
		if (reduce || !text) { el.textContent = text; return; }
		const t0 = performance.now();
		const step = (t) => {
			const p = Math.min(1, (t - t0) / ms);
			const done = Math.floor(text.length * p);
			let out = '';
			for (let i = 0; i < text.length; i++) {
				const ch = text[i];
				out += i < done || ch === ' ' ? ch : GLYPHS[(Math.random() * GLYPHS.length) | 0];
			}
			el.textContent = out;
			if (p < 1) requestAnimationFrame(step);
		};
		requestAnimationFrame(step);
	};
	const sloganText = slogan ? slogan.textContent : '';

	// ---------- Scenes ----------
	// Live Steam online: three games one by one, then the sum of the top as one big number. Returns its length.
	const pulseScene = () => {
		const sc = scenes.pulse;
		const tag = sc.querySelector('.ipulse__tag'), nameEl = sc.querySelector('.ipulse__name'), numEl = sc.querySelector('.ipulse__num');
		if (!sc.querySelector('.ipulse__live')) {
			const live = document.createElement('span'); live.className = 'ipulse__live'; live.textContent = 'LIVE';
			tag.prepend(live);
		}
		const pop = () => [nameEl, numEl].forEach((el) => { el.classList.remove('is-in'); void el.offsetWidth; el.classList.add('is-in'); });
		const series = (data.series || []).filter((g) => g && Number(g.now) > 0);
		const games = series.slice(0, 3);
		const total = series.reduce((s, g) => s + Number(g.now), 0);
		let prev = 0;
		sc.classList.remove('is-total');
		if (!games.length) {
			at(0, () => { wave(0, 0.5); nameEl.textContent = T('Радар', 'Radar'); numEl.textContent = T('собирает данные', 'collecting data'); pop(); });
			return 2000;
		}
		games.forEach((g, i) => at(i * 850, () => {
			seriesShape(g.vals); beat(0.6, 0.7);
			nameEl.textContent = g.name; pop();
			roll(numEl, prev, Number(g.now), 650); prev = Number(g.now);
		}));
		const tAt = games.length * 850;
		if (series.length > 1) {
			at(tAt, () => {
				sc.classList.add('is-total');
				setTarget((x) => Math.sin(x * Math.PI * 7) * 0.35 * Math.sin(x * Math.PI));
				beat(0.65, 1);
				flash = 0.6; shake(220, 5);
				nameEl.textContent = T(`топ-${series.length} игр Steam прямо сейчас`, `top ${series.length} Steam games right now`); pop();
				roll(numEl, 0, total, 800, T('игроков', 'players'));
			});
			return tAt + 1150;
		}
		return tAt + 200;
	};

	// Fresh headlines land in a kill feed; each one kicks the line. Returns its length.
	const killfeed = () => {
		const sc = scenes.frags;
		const box = sc.querySelector('.ifrags');
		const cnt = sc.querySelector('.ifrags__count b');
		box.innerHTML = ''; cnt.textContent = '0';
		let k = 0;
		const news = (data.news || []).slice(0, 5);
		news.forEach((n, i) => at(i * 330, () => {
			const c = document.createElement('div');
			c.className = 'ifrag';
			const cat = document.createElement('span'); cat.className = 'ifrag__cat'; cat.textContent = n.cat || 'Fragline';
			const x = document.createElement('span'); x.className = 'ifrag__x'; x.setAttribute('aria-hidden', 'true');
			x.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 1v6M12 17v6M1 12h6M17 12h6" stroke="currentColor" stroke-width="2"/></svg>';
			const title = document.createElement('span'); title.className = 'ifrag__title'; title.textContent = n.title;
			c.append(cat, x, title);
			box.prepend(c);
			[...box.children].forEach((el, j) => el.classList.toggle('is-old', j > 0));
			while (box.children.length > 4) box.lastChild.remove();
			beat(0.7, 0.6);
			burst(W * (0.2 + Math.random() * 0.6), cy, 14, 3.5);
			cnt.textContent = '+' + String(++k);
			const wrap = cnt.parentElement;
			wrap.classList.remove('is-hit'); void wrap.offsetWidth; wrap.classList.add('is-hit');
		}));
		return Math.max(1, news.length) * 330 + 750;
	};

	// ---------- Finish / abort / play ----------
	const enterIdle = () => {
		idle = true; amp = IDLE_AMP; extent = 1; reveal = 1; lineAlpha = 0.9; logoAlpha = 0; fill = 0; scan = -1; glitch = 0;
		nextBeat = idleT + 1.4;
		requestAnimationFrame(() => { cyTgt = idleY(); });
		setTimeout(() => { if (idle) cyTgt = idleY(); }, 800);
	};
	const finish = () => {
		if (!playing && root.classList.contains('is-done')) return;
		const wasPlaying = playing;
		playing = false;
		clearAll();
		Object.values(scenes).forEach((s) => s.classList.remove('is-on'));
		progress.style.transform = 'scaleX(1)';
		root.classList.add('is-done');
		if (wasPlaying) flash = 0.9;
		enterIdle();
		scramble(slogan, sloganText, 700);
		try { sessionStorage.setItem('fl-intro', String(Date.now())); } catch { /* storage blocked */ }
	};

	// A tab hidden mid-intro (Yandex Browser preloads and backgrounds pages) must not eat the intro: stop quietly,
	// do not mark it as seen, and play from the start when the tab is really shown.
	const abort = () => {
		playing = false; clearAll();
		Object.values(scenes).forEach((s) => s.classList.remove('is-on'));
		scenes.logo?.classList.remove('is-word');
		progress.style.transform = 'scaleX(0)';
		cur.fill(0); tgt.fill(0); parts.length = 0; rings.length = 0; beats.length = 0;
		reveal = 0; lineAlpha = 0; logoAlpha = 0; fill = 0; scan = -1;
	};

	const play = () => {
		clearAll();
		playing = true; idle = false; parts.length = 0; rings.length = 0; beats.length = 0;
		root.classList.remove('is-done', 'is-quick', 'is-beat');
		scenes.logo?.classList.remove('is-word');
		cur.fill(0); tgt.fill(0);
		reveal = 0; extent = 1; lineAlpha = 1; amp = 1; logoP = 0; logoAlpha = 0; fill = 0; glitch = 0; flash = 0; scan = -1;
		cyTgt = H * 0.5; cy = cyTgt;
		const t0 = performance.now();
		const prog = () => { if (!playing) return; progress.style.transform = `scaleX(${Math.min(1, (performance.now() - t0) / TOTAL)})`; requestAnimationFrame(prog); };
		prog();

		// 1. Dead line, a scanner searches for a signal, then one heartbeat.
		show('boot');
		typeInto(scenes.boot.querySelector('[data-type]'), T('fragline // поиск сигнала…', 'fragline // searching for signal…'), 800);
		tween(700, (p) => { reveal = p; });
		let scanning = true;
		at(250, () => tween(650, (p) => { if (scanning) scan = p; }));
		at(900, () => { scanning = false; scan = -1; });
		at(700, () => { beat(0.7, 1); });

		// 2. The beat reaches the middle: the line erupts into the live online of the top games.
		at(1450, () => {
			flash = 1; shake(300, 8); ring(W / 2, cy, Math.max(W, H) * 0.7); burst(W / 2, cy, 40, 6);
			show('pulse'); tween(500, (p) => { fill = p; });
			const tLogo = Math.min(pulseScene(), 3600);
			// 3. The line folds into the mark: glitch, shockwave, the name decodes.
			at(tLogo, () => {
				flat(); show('logo'); tween(400, (p) => { fill = 1 - p; });
				tween(600, (p) => { extent = 1 - p; lineAlpha = 1 - p; });
			});
			at(tLogo + 350, () => { logoAlpha = 1; tween(750, (p) => { logoP = p; }); });
			at(tLogo + 1100, () => {
				glitch = 1; tween(500, (p) => { glitch = 1 - p; });
				flash = 0.7; shake(240, 6); ring(W / 2, cy - 8, Math.max(W, H) * 0.55);
				scenes.logo.classList.add('is-word');
				scramble(scenes.logo.querySelector('.ilogo__word'), 'Fragline', 520);
			});
			// 4. Fresh headlines land in the kill feed, then a flash wipe into the slogan panel.
			at(tLogo + 1850, () => {
				show('frags');
				tween(400, (p) => { logoAlpha = 1 - p; });
				cur.fill(0); flat(); amp = 0.5; extent = 1; reveal = 1;
				tween(400, (p) => { lineAlpha = p; });
				at(killfeed(), finish);
			});
		});
		at(TOTAL + 2000, finish); // safety net
	};

	const quick = () => {
		root.classList.add('is-done', 'is-quick');
		progress.style.transform = 'scaleX(1)';
		enterIdle();
		scramble(slogan, sloganText, 600);
	};

	root.querySelector('.intro__skip')?.addEventListener('click', finish);
	root.querySelector('.intro__replay')?.addEventListener('click', () => {
		// In reduced-motion mode the visitor asks for the animation explicitly, so it may play.
		if (reduce) root.classList.add('allow-motion');
		play();
	});
	root.querySelector('.intro__go')?.addEventListener('click', (e) => {
		e.preventDefault();
		document.getElementById('explore')?.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
	});
	// Visible = the user can really see the page (not hidden, not a background prerender).
	const visible = () => !document.hidden && !document.prerendering;
	let waiting = false;
	const tryStart = () => {
		if (!waiting || !visible()) return;
		waiting = false;
		play();
	};
	const onHide = () => { if (!visible() && playing) { abort(); waiting = true; } };
	document.addEventListener('visibilitychange', () => { onHide(); tryStart(); });
	document.addEventListener('prerenderingchange', tryStart);
	window.addEventListener('load', () => { if (idle) cyTgt = idleY(); });
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => { if (idle) cyTgt = idleY(); });

	// "Seen" lasts 30 minutes: browsers that restore tabs (Yandex) keep sessionStorage for days.
	let seen = false;
	try { const ts = Number(sessionStorage.getItem('fl-intro')) || 0; seen = ts > 1e12 && Date.now() - ts < 30 * 60 * 1000; } catch { /* storage blocked */ }
	// Weak or metered devices get the calm panel straight away. Phones get the intro too.
	const weak = (() => { try { const c = navigator.connection || {}; return !!c.saveData || /^(slow-2g|2g)$/.test(c.effectiveType || '') || (navigator.deviceMemory || 8) <= 2 || (navigator.hardwareConcurrency || 8) <= 2; } catch { return false; } })();
	if (reduce) {
		// The system asks for less motion: no sequence, no beats, no pointer effects, only a slow calm wave.
		root.classList.add('is-reduced');
		quick();
		requestAnimationFrame(loop);
	} else {
		requestAnimationFrame(loop);
		if (seen || weak) quick();
		else if (visible()) play();
		else waiting = true;
	}
})();
