(() => {
	const cfg = window.RSP || {};
	const root = document.documentElement;
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const $ = (s, c = document) => c.querySelector(s);
	const $$ = (s, c = document) => [...c.querySelectorAll(s)];

	// Light / dark theme.
	$$('[data-theme-toggle]').forEach((btn) =>
		btn.addEventListener('click', () => {
			const next = root.dataset.theme === 'light' ? 'dark' : 'light';
			root.dataset.theme = next;
			try { localStorage.setItem('rsp-theme', next); } catch { /* storage blocked */ }
			const meta = $('meta[name="theme-color"]');
			if (meta) meta.content = next === 'light' ? '#F4F5F0' : '#0B0D11';
		})
	);

	// Reveal on scroll: a short fade for the main blocks as they enter the viewport.
	const revealSel = '.lead, .toplist__item, .pulse-card, .review-tile, .feed > .row, .box, .game-card, .thread, .bm-item, .related__list li, .comment, .author-card, .verdict, .postnav a, .empty, .fc-past';
	const targets = $$(revealSel);
	targets.forEach((el) => {
		const sibs = el.parentElement ? [...el.parentElement.children].filter((c) => c.matches(revealSel)) : [el];
		el.style.setProperty('--i', Math.min(sibs.indexOf(el), 5));
		el.setAttribute('data-reveal', '');
	});
	if ('IntersectionObserver' in window && !reduce) {
		const io = new IntersectionObserver((entries) => {
			entries.forEach((e) => {
				if (!e.isIntersecting) return;
				e.target.classList.add('is-in');
				io.unobserve(e.target);
			});
		}, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });
		targets.forEach((el) => io.observe(el));
	} else {
		targets.forEach((el) => el.classList.add('is-in'));
	}

	// Count-up numbers.
	const counters = $$('[data-count]');
	const run = (el) => {
		const end = Number(el.dataset.count) || 0;
		if (reduce || end === 0) { el.textContent = end.toLocaleString('ru-RU'); return; }
		const t0 = performance.now();
		const dur = 1200;
		const tick = (t) => {
			const p = Math.min(1, (t - t0) / dur);
			el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString('ru-RU');
			if (p < 1) requestAnimationFrame(tick);
		};
		requestAnimationFrame(tick);
	};
	if ('IntersectionObserver' in window) {
		const co = new IntersectionObserver((entries) => entries.forEach((e) => {
			if (e.isIntersecting) { run(e.target); co.unobserve(e.target); }
		}), { threshold: 0.6 });
		counters.forEach((c) => co.observe(c));
	} else {
		counters.forEach(run);
	}

	// Reading progress bar, back-to-top.
	const bar = $('.progress');
	const article = $('.article__content');
	const top = $('.to-top');
	let ticking = false;
	const onScroll = () => {
		ticking = false;
		if (bar && article) {
			const r = article.getBoundingClientRect();
			const total = r.height - window.innerHeight * 0.6;
			bar.style.setProperty('--p', Math.max(0, Math.min(1, -r.top / Math.max(total, 1))).toFixed(4));
		}
		top?.classList.toggle('is-on', window.scrollY > 700);
	};
	window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
	onScroll();
	top?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }));

	// Table of contents from article headings.
	const toc = $('[data-toc]');
	if (toc && article) {
		const heads = $$('h2', article);
		if (heads.length >= 3) {
			const list = document.createElement('ol');
			heads.forEach((h, n) => {
				h.id = h.id || 'section-' + (n + 1);
				const li = document.createElement('li');
				const a = document.createElement('a');
				a.href = '#' + h.id;
				a.textContent = h.textContent;
				li.append(a);
				list.append(li);
			});
			toc.append(list);
			toc.hidden = false;
			if ('IntersectionObserver' in window) {
				const links = $$('a', list);
				const so = new IntersectionObserver((entries) => entries.forEach((e) => {
					if (!e.isIntersecting) return;
					links.forEach((l) => l.classList.toggle('is-active', l.hash === '#' + e.target.id));
				}), { rootMargin: '-20% 0px -70% 0px' });
				heads.forEach((h) => so.observe(h));
			}
		}
	}

	// Live search suggestions and the "/" shortcut.
	const input = $('#site-search input[type="search"], #site-search input[name="s"]');
	const live = $('#search-live');
	const searchBtn = $('[data-toggle-search]');
	document.addEventListener('keydown', (e) => {
		if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
		if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName) || document.activeElement?.isContentEditable) return;
		e.preventDefault();
		if (searchBtn?.getAttribute('aria-expanded') !== 'true') searchBtn?.click(); else input?.focus();
	});
	if (input && live && cfg.wp) {
		let timer = 0;
		let ctl = null;
		input.addEventListener('input', () => {
			clearTimeout(timer);
			const q = input.value.trim();
			if (q.length < 2) { live.textContent = ''; return; }
			timer = setTimeout(async () => {
				ctl?.abort();
				ctl = new AbortController();
				try {
					const res = await fetch(cfg.wp + 'search?per_page=6&type=post&search=' + encodeURIComponent(q), { signal: ctl.signal });
					const items = await res.json();
					live.textContent = '';
					if (!Array.isArray(items) || !items.length) {
						const p = document.createElement('p');
						p.textContent = 'Ничего не нашлось. Попробуйте другое слово.';
						live.append(p);
						return;
					}
					items.forEach((it) => {
						const a = document.createElement('a');
						a.href = it.url;
						const t = document.createElement('div');
						t.innerHTML = it.title; // REST returns escaped title markup
						a.append(t.textContent);
						live.append(a);
					});
				} catch { /* aborted or offline */ }
			}, 220);
		});
	}

	// Small JSON POST helper for the site's REST endpoints.
	const post = async (path, body) => {
		const res = await fetch(cfg.rest + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify(body),
		});
		const data = await res.json();
		if (!res.ok) throw new Error(data.message || 'Не получилось');
		return data;
	};
	const flashError = (el, err) => {
		el.title = err.message;
		el.classList.add('is-error');
		setTimeout(() => el.classList.remove('is-error'), 1400);
	};

	document.addEventListener('click', async (e) => {
		// Bookmarks.
		const bm = e.target.closest('button[data-bookmark-id]');
		if (bm && !bm.disabled) {
			bm.disabled = true;
			try {
				const d = await post('bookmark', { id: Number(bm.dataset.bookmarkId) });
				bm.classList.toggle('is-saved', d.saved);
				bm.setAttribute('aria-pressed', String(d.saved));
				bm.title = d.saved ? 'Убрать из закладок' : 'Сохранить в закладки';
				const label = bm.querySelector('.bookmark__label');
				if (label) label.textContent = d.saved ? 'В закладках' : 'В закладки';
				bm.classList.add('is-pop');
				setTimeout(() => bm.classList.remove('is-pop'), 450);
			} catch (err) { flashError(bm, err); } finally { bm.disabled = false; }
			return;
		}

		// Category subscriptions.
		const sub = e.target.closest('button[data-subscribe]');
		if (sub && !sub.disabled) {
			sub.disabled = true;
			try {
				const d = await post('subscribe', { term: Number(sub.dataset.subscribe) });
				sub.classList.toggle('is-on', d.subscribed);
				sub.setAttribute('aria-pressed', String(d.subscribed));
				sub.querySelector('.subscribe__label').textContent = d.subscribed ? 'Вы подписаны' : 'Подписаться';
				sub.querySelector('.subscribe__count').textContent = d.count;
			} catch (err) { flashError(sub, err); } finally { sub.disabled = false; }
			return;
		}

		// Game rating 1–10; clicking your current score clears it.
		const star = e.target.closest('.rating__scale button[data-score]');
		if (star) {
			const box = star.closest('[data-rate-id]');
			const current = box.querySelector('.rating__scale [aria-pressed="true"]');
			const score = current === star ? 0 : Number(star.dataset.score);
			try {
				const d = await post('rate', { id: Number(box.dataset.rateId), score });
				box.querySelectorAll('.rating__scale button').forEach((b) => {
					const n = Number(b.dataset.score);
					b.classList.toggle('is-on', n <= d.mine);
					b.setAttribute('aria-pressed', String(n === d.mine));
				});
				box.querySelector('.rating__avg').textContent = d.count ? String(d.avg).replace('.', ',') : '—';
				box.querySelector('.rating__count').textContent = d.count ? 'оценок игроков: ' + d.count : 'оценок пока нет';
				box.querySelector('.rating__hint').textContent = d.mine ? 'Ваша оценка: ' + d.mine + '. Нажмите ещё раз, чтобы убрать.' : 'Поставьте свою оценку от 1 до 10.';
				box.classList.add('is-pop');
				setTimeout(() => box.classList.remove('is-pop'), 500);
			} catch (err) { flashError(star, err); }
			return;
		}

		// Thread votes.
		const vb = e.target.closest('.vote button[data-dir]');
		if (vb && !vb.disabled) {
			const box = vb.closest('[data-vote-id]');
			const mine = Number(box.dataset.mine);
			const dir = Number(vb.dataset.dir) === mine ? 0 : Number(vb.dataset.dir);
			vb.disabled = true;
			try {
				const d = await post('vote', { id: Number(box.dataset.voteId), dir });
				box.dataset.mine = d.mine;
				box.querySelector('.vote__score').textContent = d.score;
				box.querySelector('.vote__up').classList.toggle('is-on', d.mine === 1);
				box.querySelector('.vote__down').classList.toggle('is-on', d.mine === -1);
			} catch (err) { flashError(box, err); } finally { vb.disabled = false; }
		}
	});

	document.addEventListener('change', async (e) => {
		const box = e.target.closest('input[data-sub-email]');
		if (!box) return;
		try { await post('subscribe-email', { on: box.checked }); } catch { box.checked = !box.checked; }
	});

	// Heart pop on like.
	document.addEventListener('click', (e) => {
		const btn = e.target.closest('button[data-like-id]');
		if (!btn) return;
		btn.classList.add('is-pop');
		setTimeout(() => btn.classList.remove('is-pop'), 450);
	});
})();
