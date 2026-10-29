/**
 * Respawn intro: a ~30 second presentation that plays once per page view and never loops.
 * Canvas draws particles (boot dust → diamond → RESPAWN → explosion), a warp starfield, a neon grid
 * and the final shockwave; DOM scenes on top show real site data. Every scene is a pure function of
 * the timeline position t, so "skip" and "replay" just move t.
 */
(() => {
	const root = document.getElementById('rsp-intro');
	if (!root) return;
	let data = {};
	try {
		data = JSON.parse(document.getElementById('rsp-intro-data').textContent || '{}');
	} catch { /* empty data still plays */ }

	const canvas = root.querySelector('.intro__canvas');
	const ctx = canvas.getContext('2d');
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const $ = (s) => root.querySelector(s);
	const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
	const ease = (u) => (u < 0.5 ? 4 * u * u * u : 1 - Math.pow(-2 * u + 2, 3) / 2);
	const local = (t, [a, b]) => clamp((t - a) / (b - a));

	// Timeline, seconds.
	const S = {
		boot: [0, 3.4],
		diamond: [3.4, 5.4],
		word: [5.4, 7.3],
		warp: [7.6, 12],
		games: [12, 17.6],
		news: [17.6, 22.6],
		community: [22.6, 26.8],
		stats: [26.8, 29.6],
	};
	const BOOM = 29.6;
	const FINAL = 30.8;
	const END = 31.6;
	const scenes = {
		boot: [0, 3.1],
		logo: [5.9, 7.3],
		warp: [7.6, 12],
		games: [12.1, 17.3],
		news: [17.7, 22.3],
		community: [22.7, 26.5],
		stats: [26.9, 29.4],
	};

	let W = 0, H = 0, DPR = 1, cx = 0, cy = 0;
	let t = 0, prevT = 0, last = 0, running = false, visible = true, flash = 0, finalShown = false;
	let particles = [], stars = [], diamondPts = [], wordPts = [], wordSize = 120;

	/* ---------- Geometry ---------- */

	function resize() {
		const r = root.getBoundingClientRect();
		DPR = Math.min(window.devicePixelRatio || 1, 1.5);
		W = Math.max(320, r.width);
		H = Math.max(420, r.height);
		canvas.width = Math.round(W * DPR);
		canvas.height = Math.round(H * DPR);
		ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
		cx = W / 2;
		cy = H * 0.46;
		diamondPts = makeDiamond();
		wordPts = makeWord('RESPAWN');
		layoutRing();
		ctx.fillStyle = '#0A0B0E';
		ctx.fillRect(0, 0, W, H);
	}

	function makeDiamond() {
		const R = Math.min(W, H) * 0.2;
		const pts = [];
		for (let i = 0; i < 1600; i++) {
			const edge = Math.random() < 0.62;
			let x, y;
			if (edge) {
				const k = Math.random() * 4;
				const side = Math.floor(k);
				const f = k - side;
				const corners = [[0, -R], [R, 0], [0, R], [-R, 0], [0, -R]];
				x = corners[side][0] + (corners[side + 1][0] - corners[side][0]) * f;
				y = corners[side][1] + (corners[side + 1][1] - corners[side][1]) * f;
			} else {
				do {
					x = (Math.random() * 2 - 1) * R;
					y = (Math.random() * 2 - 1) * R;
				} while (Math.abs(x) + Math.abs(y) > R * 0.94);
			}
			pts.push([cx + x, cy + y]);
		}
		return pts;
	}

	function makeWord(text) {
		const size = (wordSize = Math.min(W * 0.14, H * 0.22, 170));
		const off = document.createElement('canvas');
		off.width = Math.ceil(W);
		off.height = Math.ceil(H);
		const o = off.getContext('2d');
		o.fillStyle = '#fff';
		o.textAlign = 'center';
		o.textBaseline = 'middle';
		o.font = `800 ${size}px Unbounded, Onest, sans-serif`;
		o.fillText(text, W / 2, cy);
		const img = o.getImageData(0, 0, off.width, off.height).data;
		const step = Math.max(2, Math.round(size / 48));
		const pts = [];
		for (let y = 0; y < off.height; y += step) {
			for (let x = 0; x < off.width; x += step) {
				if (img[(y * off.width + x) * 4 + 3] > 128) pts.push([x, y]);
			}
		}
		return pts.length ? pts : makeDiamond();
	}

	function seedParticles() {
		const n = Math.round(clamp((W * H) / 460, 700, 2600));
		particles = Array.from({ length: n }, (_, i) => ({
			x: Math.random() * W,
			y: Math.random() * H,
			vx: (Math.random() - 0.5) * 1.2,
			vy: (Math.random() - 0.5) * 1.2,
			s: Math.random() < 0.15 ? 2.2 : 1.3,
			hot: Math.random() < 0.62,
			seed: Math.random() * 1000,
			ember: i < n * 0.32,
			jx: (Math.random() - 0.5) * 1.2,
			jy: (Math.random() - 0.5) * 1.2,
		}));
		stars = Array.from({ length: Math.round(clamp(W / 2.2, 260, 700)) }, newStar);
	}

	function newStar() {
		return { x: (Math.random() * 2 - 1) * W, y: (Math.random() * 2 - 1) * H, z: Math.random() * 0.9 + 0.1, pz: 0, hot: Math.random() < 0.35 };
	}

	/* ---------- Canvas phases ---------- */

	function particleAlpha() {
		if (t < S.boot[1]) return clamp(t / 1.2) * 0.9;
		if (t < S.word[1]) return 1;
		if (t < 8.4) return 1 - clamp((t - 7.3) / 1.1);
		if (t < S.stats[0]) return 0;
		if (t < BOOM) return clamp((t - S.stats[0]) / 1);
		return 1 - clamp((t - BOOM - 0.5) / 1.1);
	}

	function updateParticles(dt) {
		const f = dt * 60;
		const mode = t < S.boot[1] ? 'boot' : t < S.diamond[1] ? 'diamond' : t < S.word[1] ? 'word' : t < S.stats[0] ? 'burst' : t < BOOM ? 'diamond' : t < BOOM + 1.6 ? 'burst' : 'ember';
		const pts = mode === 'word' ? wordPts : diamondPts;
		const k = mode === 'word' ? 0.075 : 0.05;
		particles.forEach((p, i) => {
			if (mode === 'boot') {
				const dx = cx - p.x, dy = cy - p.y;
				p.vx = (p.vx + dy * 0.00025 * f + dx * 0.00012 * f) * Math.pow(0.985, f);
				p.vy = (p.vy - dx * 0.00025 * f + dy * 0.00012 * f) * Math.pow(0.985, f);
			} else if (mode === 'diamond' || mode === 'word') {
				// Spread particles evenly over the target so every letter gets its share.
				const tp = pts[Math.floor((i / particles.length) * pts.length + (i % 3)) % pts.length];
				// During the stats scene the diamond is a wide frame around the numbers, then it implodes before the blast.
				const wide = t >= S.stats[0] && t < BOOM - 0.7 ? 2.6 : 1;
				const tx = cx + (tp[0] - cx) * wide, ty = cy + (tp[1] - cy) * wide;
				p.vx = (p.vx + (tx + p.jx - p.x) * k * f) * Math.pow(0.78, f);
				p.vy = (p.vy + (ty + p.jy - p.y) * k * f) * Math.pow(0.78, f);
			} else if (mode === 'burst') {
				p.vx *= Math.pow(0.965, f);
				p.vy *= Math.pow(0.965, f);
			} else {
				// Embers drifting up after the finale.
				p.vx = Math.sin(t * 0.8 + p.seed) * 0.35;
				p.vy = -(0.35 + (p.seed % 1) * 0.7);
				if (p.y < -10) {
					p.y = H + 10;
					p.x = Math.random() * W;
				}
			}
			p.x += p.vx * f;
			p.y += p.vy * f;
		});
	}

	function explode(power) {
		particles.forEach((p) => {
			const dx = p.x - cx, dy = p.y - cy;
			const d = Math.hypot(dx, dy) || 1;
			const v = power * (0.4 + Math.random());
			p.vx += (dx / d) * v;
			p.vy += (dy / d) * v;
		});
	}

	function drawParticles(alpha) {
		if (alpha <= 0) return;
		const ember = t >= BOOM + 1.6;
		ctx.globalCompositeOperation = 'lighter';
		for (const p of particles) {
			if (ember && !p.ember) continue;
			// Embers glow up after the shockwave, then fade out for good: nothing keeps moving forever.
			const a = ember ? (0.25 + 0.35 * Math.abs(Math.sin(t * 2 + p.seed))) * clamp((t - BOOM - 1.6) / 1.5) * (1 - clamp((t - END - 2) / 6)) : alpha;
			ctx.globalAlpha = a;
			ctx.fillStyle = p.hot ? '#FF6A3A' : '#F3E9E4';
			ctx.fillRect(p.x, p.y, p.s, p.s);
		}
		ctx.globalAlpha = 1;
		ctx.globalCompositeOperation = 'source-over';
	}

	function warpSpeed() {
		if (t < S.warp[0] - 0.4) return 0;
		if (t < 9.6) return 0.15 + ease(local(t, [S.warp[0] - 0.4, 9.6])) * 2.4;
		if (t < 11.2) return 2.55;
		if (t < 12.6) return 2.55 - ease(local(t, [11.2, 12.6])) * 2.4;
		return 0.15;
	}

	function starAlpha() {
		if (t < S.warp[0] - 0.4) return 0;
		if (t < S.stats[0]) return t < 12.6 ? 1 : 0.55;
		return 0.55 * (1 - clamp((t - S.stats[0]) / 1.2));
	}

	function drawStars(dt) {
		const a = starAlpha();
		if (a <= 0) return;
		const sp = warpSpeed();
		const fz = W * 0.42;
		ctx.lineCap = 'round';
		for (const s of stars) {
			s.pz = s.z;
			s.z -= sp * dt * 0.42;
			if (s.z <= 0.02) {
				Object.assign(s, newStar(), { z: 1 });
				s.pz = s.z;
				continue;
			}
			const x1 = cx + (s.x / s.pz) * (fz / W);
			const y1 = cy + (s.y / s.pz) * (fz / W);
			const x2 = cx + (s.x / s.z) * (fz / W);
			const y2 = cy + (s.y / s.z) * (fz / W);
			if (x2 < -50 || x2 > W + 50 || y2 < -50 || y2 > H + 50) {
				Object.assign(s, newStar(), { z: 1 });
				continue;
			}
			ctx.globalAlpha = a * clamp(1.2 - s.z);
			ctx.strokeStyle = s.hot ? '#FF5B2E' : '#DCE3EE';
			ctx.lineWidth = clamp(1.6 - s.z, 0.4, 2.4) * (sp > 1 ? 1.4 : 1);
			ctx.beginPath();
			ctx.moveTo(x1, y1);
			ctx.lineTo(x2, y2);
			ctx.stroke();
		}
		ctx.globalAlpha = 1;
	}

	function drawGrid() {
		const a = clamp((t - S.news[0] + 0.4) / 1.2) * (1 - clamp((t - S.community[0] - 1.5) / 1.5));
		if (a <= 0) return;
		const hz = H * 0.64;
		// Neon sun behind the horizon.
		const r = Math.min(W, H) * 0.24;
		const g = ctx.createLinearGradient(0, hz - r, 0, hz);
		g.addColorStop(0, `rgba(255,176,46,${0.55 * a})`);
		g.addColorStop(1, `rgba(255,62,46,${0.5 * a})`);
		ctx.save();
		ctx.beginPath();
		ctx.rect(0, 0, W, hz);
		ctx.clip();
		ctx.fillStyle = g;
		ctx.beginPath();
		ctx.arc(cx, hz, r, Math.PI, 0);
		ctx.fill();
		ctx.fillStyle = '#0A0B0E';
		for (let i = 0; i < 7; i++) {
			const y = hz - r * 0.08 - i * r * 0.13;
			ctx.fillRect(cx - r, y, r * 2, 2 + (7 - i) * 0.9);
		}
		ctx.restore();
		// Floor.
		ctx.strokeStyle = '#FF5B2E';
		ctx.lineWidth = 1.2;
		for (let i = -14; i <= 14; i++) {
			ctx.globalAlpha = a * 0.55;
			ctx.beginPath();
			ctx.moveTo(cx + i * W * 0.012, hz);
			ctx.lineTo(cx + i * W * 0.14, H);
			ctx.stroke();
		}
		const off = (t * 0.9) % 1;
		for (let j = 0; j < 14; j++) {
			const u = (j + off) / 14;
			const y = hz + (H - hz) * Math.pow(u, 2.1);
			ctx.globalAlpha = a * u * 0.8;
			ctx.beginPath();
			ctx.moveTo(0, y);
			ctx.lineTo(W, y);
			ctx.stroke();
		}
		ctx.globalAlpha = a;
		ctx.fillStyle = 'rgba(255,91,46,.18)';
		ctx.fillRect(0, hz - 1, W, 2);
		ctx.globalAlpha = 1;
	}

	// The particle word "ignites" into solid glowing letters just before it explodes.
	function drawWordGlow() {
		const a = ease(local(t, [6.1, 6.9])) * (1 - local(t, [S.word[1] - 0.12, S.word[1] + 0.05]));
		if (a <= 0) return;
		ctx.save();
		ctx.globalAlpha = a;
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.font = `800 ${wordSize}px Unbounded, Onest, sans-serif`;
		ctx.shadowColor = 'rgba(255,91,46,.95)';
		ctx.shadowBlur = 40;
		const g = ctx.createLinearGradient(cx - wordSize * 3, 0, cx + wordSize * 3, 0);
		g.addColorStop(0, '#FF5B2E');
		g.addColorStop(0.5, '#FFF4EE');
		g.addColorStop(1, '#FF5B2E');
		ctx.fillStyle = g;
		ctx.fillText('RESPAWN', cx, cy);
		ctx.restore();
	}

	function drawShock() {
		const u = (t - BOOM) / 1.8;
		if (u < 0 || u > 1) return;
		ctx.globalCompositeOperation = 'lighter';
		[0, 0.18].forEach((d, n) => {
			const v = clamp((u - d) / (1 - d));
			if (v <= 0) return;
			ctx.globalAlpha = (1 - v) * (n ? 0.5 : 0.9);
			ctx.strokeStyle = n ? '#FFFFFF' : '#FF5B2E';
			ctx.lineWidth = (n ? 2 : 6) * (1 - v) + 1;
			ctx.beginPath();
			ctx.arc(cx, cy, ease(v) * Math.hypot(W, H) * 0.7, 0, Math.PI * 2);
			ctx.stroke();
		});
		ctx.globalAlpha = 1;
		ctx.globalCompositeOperation = 'source-over';
	}

	function drawFlash() {
		if (flash <= 0.01) return;
		ctx.globalCompositeOperation = 'lighter';
		const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, Math.max(W, H) * 0.8);
		g.addColorStop(0, `rgba(255,220,200,${flash})`);
		g.addColorStop(0.4, `rgba(255,91,46,${flash * 0.6})`);
		g.addColorStop(1, 'rgba(255,91,46,0)');
		ctx.fillStyle = g;
		ctx.fillRect(0, 0, W, H);
		ctx.globalCompositeOperation = 'source-over';
	}

	/* ---------- DOM scenes ---------- */

	const sceneEls = Object.fromEntries(Object.keys(scenes).map((k) => [k, $(`.iscene--${k}`)]));
	const progress = $('.intro__progress span');

	// Warp words.
	const wordsBox = $('.warp-words');
	const words = (data.words || []).map((w, i) => {
		const el = document.createElement('span');
		el.className = 'warp-word';
		el.textContent = w;
		el.dataset.i = i;
		wordsBox.appendChild(el);
		return el;
	});

	// Game covers ring.
	const ring = $('.ring');
	const ringLabel = $('.ring-label');
	const games = (data.games || []).slice(0, 12);
	const ringItems = games.map((g) => {
		const el = document.createElement('div');
		el.className = 'ring__item';
		const img = new Image();
		img.src = g.cover;
		img.alt = '';
		img.decoding = 'async';
		el.appendChild(img);
		ring.appendChild(el);
		return el;
	});
	let ringR = 300, ringStep = 30, lastIdx = -1;
	function layoutRing() {
		if (!ringItems.length) return;
		const w = clamp(W * 0.13, 92, 180);
		ringStep = 360 / ringItems.length;
		ringR = Math.max(160, (w * 1.18) / (2 * Math.tan(Math.PI / ringItems.length)));
		ring.style.setProperty('--w', w + 'px');
		ringItems.forEach((el, i) => (el.style.transform = `rotateY(${i * ringStep}deg) translateZ(${ringR}px)`));
	}
	function updateRing() {
		if (!ringItems.length) return;
		const u = local(t, S.games);
		const angle = -(ease(clamp(u * 1.05)) * 300);
		const scale = 0.55 + ease(clamp(u / 0.25)) * 0.45 + clamp((u - 0.88) / 0.12) * 0.6;
		ring.style.transform = `translateZ(${-ringR}px) rotateX(-8deg) rotateY(${angle}deg) scale(${scale})`;
		const idx = ((Math.round(-angle / ringStep) % ringItems.length) + ringItems.length) % ringItems.length;
		if (idx !== lastIdx) {
			lastIdx = idx;
			ringLabel.textContent = games[idx].title;
			ringLabel.classList.remove('is-pop');
			void ringLabel.offsetWidth;
			ringLabel.classList.add('is-pop');
			ringItems.forEach((el, i) => el.classList.toggle('is-front', i === idx));
		}
	}

	// News cards.
	const newsBox = $('.inews');
	(data.news || []).forEach((n, i) => {
		const card = document.createElement('div');
		card.className = 'inews__card';
		card.style.setProperty('--i', i);
		card.innerHTML = `<span class="inews__img"></span><span class="inews__body"><span class="inews__meta"><b></b><i>Respawn AI</i></span><span class="inews__title"></span></span>`;
		if (n.img) card.querySelector('.inews__img').style.backgroundImage = `url("${n.img.replace(/"/g, '%22')}")`;
		card.querySelector('b').textContent = n.cat || 'Новости';
		if (!n.ai) card.querySelector('i').remove();
		card.querySelector('.inews__title').textContent = n.title;
		newsBox.appendChild(card);
	});

	// Forum threads.
	const threadsBox = $('.ithreads');
	(data.threads || []).forEach((title, i) => {
		const li = document.createElement('li');
		li.style.setProperty('--i', i);
		li.innerHTML = '<span class="ivote"><i>▲</i><em>+1</em><i>▼</i></span><span class="ithreads__t"></span>';
		li.querySelector('.ithreads__t').textContent = title;
		threadsBox.appendChild(li);
	});

	const counters = [...root.querySelectorAll('.istat b')];

	function updateDom() {
		for (const [k, [a, b]] of Object.entries(scenes)) {
			sceneEls[k]?.classList.toggle('is-on', t >= a && t < b);
		}
		// Warp words fly from the depth past the viewer.
		words.forEach((el, i) => {
			const u = (t - (S.warp[0] + 0.3 + i * 0.62)) / 1.35;
			if (u <= 0 || u >= 1) {
				el.style.opacity = 0;
				return;
			}
			const sc = 0.12 + Math.pow(u, 2.6) * 6;
			const ang = i * 2.4;
			const dx = Math.cos(ang) * u * W * 0.22;
			const dy = Math.sin(ang) * u * H * 0.18;
			el.style.opacity = Math.min(1, Math.sin(Math.PI * u) * 1.6);
			el.style.transform = `translate(-50%,-50%) translate(${dx}px,${dy}px) scale(${sc})`;
			el.style.filter = u > 0.72 ? `blur(${(u - 0.72) * 18}px)` : 'none';
		});
		if (t >= S.games[0] - 0.3 && t < S.games[1]) updateRing();
		const su = local(t, [S.stats[0] + 0.2, S.stats[0] + 1.9]);
		counters.forEach((c) => (c.textContent = Math.round(Number(c.dataset.to) * ease(su)).toLocaleString('ru-RU')));
		progress.style.transform = `scaleX(${clamp(t / FINAL)})`;
		if (t >= FINAL && !finalShown) {
			finalShown = true;
			root.classList.add('is-final');
		}
	}

	/* ---------- Loop ---------- */

	function frame(now) {
		if (!running) return;
		const dt = Math.min(0.05, (now - (last || now)) / 1000);
		last = now;
		if (visible) {
			prevT = t;
			t += dt;
			if (prevT < S.word[1] && t >= S.word[1]) explode(14);
			if (prevT < BOOM && t >= BOOM) {
				explode(22);
				flash = 1;
			}
			ctx.fillStyle = `rgba(10,11,14,${t > S.warp[0] && t < 12.6 ? 0.28 : 0.4})`;
			ctx.fillRect(0, 0, W, H);
			drawGrid();
			drawStars(dt);
			updateParticles(dt);
			drawParticles(particleAlpha());
			drawWordGlow();
			drawShock();
			drawFlash();
			flash *= Math.pow(0.9, dt * 60);
			updateDom();
			if (t > END + 9) {
				running = false;
				return;
			}
		}
		requestAnimationFrame(frame);
	}

	function start(from = 0) {
		t = from;
		prevT = from;
		last = 0;
		flash = 0;
		finalShown = false;
		lastIdx = -1;
		root.classList.remove('is-final');
		root.classList.add('is-playing');
		seedParticles();
		ctx.fillStyle = '#0A0B0E';
		ctx.fillRect(0, 0, W, H);
		if (!running) {
			running = true;
			requestAnimationFrame(frame);
		}
	}

	$('.intro__skip').addEventListener('click', () => {
		if (t < S.stats[0]) {
			// Jump to the gathering before the finale so the shockwave still plays.
			t = S.stats[0] + 1.2;
			prevT = t;
			particles.forEach((p) => {
				p.x = Math.random() * W;
				p.y = Math.random() * H;
			});
		}
	});
	$('.intro__replay').addEventListener('click', () => start(0));
	$('.intro__go').addEventListener('click', (e) => {
		const target = document.getElementById('explore');
		if (!target) return;
		e.preventDefault();
		target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
	});

	// Pause while the intro is mostly off screen; resume where it stopped.
	if ('IntersectionObserver' in window) {
		new IntersectionObserver((entries) => {
			visible = entries[0].intersectionRatio > 0.25;
			last = 0;
		}, { threshold: [0, 0.25, 0.5] }).observe(root);
	}
	let rt = 0;
	window.addEventListener('resize', () => {
		clearTimeout(rt);
		rt = setTimeout(resize, 150);
	});

	if (reduce) {
		resize();
		root.classList.add('is-final', 'is-static');
		return;
	}
	const fontReady = document.fonts && document.fonts.load ? document.fonts.load('800 100px Unbounded') : Promise.resolve();
	Promise.race([fontReady, new Promise((r) => setTimeout(r, 1500))]).then(() => {
		resize();
		// ?intro_t=12 starts from a given second (handy for checking a scene).
		const qs = new URLSearchParams(location.search);
		const from = parseFloat(qs.get('intro_t'));
		if (qs.has('intro_still')) root.classList.add('intro--still');
		start(Number.isFinite(from) ? clamp(from, 0, END) : 0);
	});
})();
