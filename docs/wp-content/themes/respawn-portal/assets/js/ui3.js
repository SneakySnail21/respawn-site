/* Fragline UI 3: header fold, rubric bar, phone tab bar + sections sheet, forecast "show all", review arrows,
   sticky table of contents next to articles. Only loaded when body has class ui3 (inc/ui3.php). */
(() => {
	const body = document.body;
	if (!body.classList.contains('ui3')) return;
	const $ = (s, r = document) => r.querySelector(s);
	const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
	const en = (document.documentElement.lang || '').toLowerCase().startsWith('en');
	const t = (ru, eng) => (en ? eng : ru);
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const arrow = (d) => `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${d === 'next' ? 'M9 6l6 6-6 6' : 'M15 6l-6 6 6 6'}"/></svg>`;

	// Header: the rubric bar folds while reading down and comes back on the way up.
	const header = $('.site-header');
	let lastY = window.scrollY;
	let ticking = false;
	const onScroll = () => {
		ticking = false;
		const y = window.scrollY;
		if (!header || body.classList.contains('nav-open')) { lastY = y; return; }
		if (y < 120) header.classList.remove('is-folded');
		else if (y > lastY + 6) header.classList.add('is-folded');
		else if (y < lastY - 6) header.classList.remove('is-folded');
		lastY = y;
	};
	window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });

	// Rubric bar: active item in view, arrows on wide screens.
	const bar = $('.rubricbar');
	const track = $('[data-rubricbar]');
	if (bar && track) {
		const active = $('.rubricbar__item.is-active', track);
		if (active) track.scrollLeft = Math.max(0, active.offsetLeft - track.clientWidth / 2 + active.offsetWidth / 2);
		const inner = $('.rubricbar__inner', bar);
		['prev', 'next'].forEach((d) => {
			const b = document.createElement('button');
			b.type = 'button';
			b.className = 'rubricbar__arrow rubricbar__arrow--' + d;
			b.setAttribute('aria-label', d === 'next' ? t('Ещё рубрики', 'More sections') : t('Назад', 'Back'));
			b.innerHTML = arrow(d);
			b.addEventListener('click', () => track.scrollBy({ left: (d === 'next' ? 1 : -1) * track.clientWidth * 0.7, behavior: reduce ? 'auto' : 'smooth' }));
			inner.append(b);
		});
		const upd = () => {
			bar.classList.toggle('can-prev', track.scrollLeft > 4);
			bar.classList.toggle('can-next', track.scrollLeft + track.clientWidth < track.scrollWidth - 4);
		};
		track.addEventListener('scroll', upd, { passive: true });
		window.addEventListener('resize', upd);
		upd();

		// Phones: no sideways swiping. One button shows the current section; tapping it opens every section as a wrapped list.
		const cur = active ? active.firstChild.textContent.trim() : t('Вся лента', 'All stories');
		const tog = document.createElement('button');
		tog.type = 'button';
		tog.className = 'rubricbar__toggle';
		tog.setAttribute('aria-expanded', 'false');
		tog.innerHTML = `<span class="rubricbar__label">${t('Рубрики', 'Sections')}</span><span class="rubricbar__cur"></span><svg class="rubricbar__chev" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>`;
		$('.rubricbar__cur', tog).textContent = cur;
		inner.prepend(tog);
		const setOpen = (on) => {
			bar.classList.toggle('is-open', on);
			tog.setAttribute('aria-expanded', String(on));
		};
		tog.addEventListener('click', () => setOpen(!bar.classList.contains('is-open')));
		document.addEventListener('click', (e) => { if (!bar.contains(e.target)) setOpen(false); });
		document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
		window.addEventListener('scroll', () => { if (header && header.classList.contains('is-folded')) setOpen(false); }, { passive: true });
	}

	// Phone tab bar: the "Sections" sheet.
	const sheet = $('#fl3-sheet');
	const sheetBtn = $('[data-tabbar-sections]');
	if (sheet && sheetBtn) {
		const open = () => {
			sheet.hidden = false;
			body.classList.add('sheet-open');
			sheetBtn.setAttribute('aria-expanded', 'true');
			requestAnimationFrame(() => requestAnimationFrame(() => sheet.classList.add('is-open')));
			$('.sheet__panel', sheet).focus?.();
		};
		const close = () => {
			sheet.classList.remove('is-open');
			body.classList.remove('sheet-open');
			sheetBtn.setAttribute('aria-expanded', 'false');
			setTimeout(() => { if (!sheet.classList.contains('is-open')) sheet.hidden = true; }, reduce ? 0 : 320);
		};
		$('.sheet__panel', sheet).tabIndex = -1;
		sheetBtn.addEventListener('click', () => (sheet.hidden ? open() : close()));
		$$('[data-sheet-close]', sheet).forEach((el) => el.addEventListener('click', close));
		document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !sheet.hidden) close(); });
		// Swipe down on the grip/head closes it.
		let y0 = null;
		const panel = $('.sheet__panel', sheet);
		panel.addEventListener('touchstart', (e) => { y0 = panel.scrollTop <= 0 ? e.touches[0].clientY : null; }, { passive: true });
		panel.addEventListener('touchend', (e) => { if (y0 !== null && e.changedTouches[0].clientY - y0 > 70) close(); y0 = null; }, { passive: true });
	}

	// Home forecast: three games visible, the rest behind one button (voting is unchanged).
	$$('.pulse .fc--compact').forEach((fc) => {
		const rows = $$('.fc__row', fc);
		if (rows.length <= 3) return;
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'btn btn--ghost fc3-more';
		btn.innerHTML = t('Показать все игры раунда', 'Show every game this round') + ' <b>+' + (rows.length - 3) + '</b>';
		btn.addEventListener('click', () => { fc.classList.add('is-all'); btn.remove(); });
		$('.fc__list', fc).after(btn);
	});

	// Reviews strip: arrows when it scrolls sideways.
	$$('.reviews-strip').forEach((sec) => {
		const tr = $('.reviews-strip__track', sec);
		const head = $('.section-head', sec);
		if (!tr || !head) return;
		const nav = document.createElement('div');
		nav.className = 'strip-nav';
		const mk = (d) => {
			const b = document.createElement('button');
			b.type = 'button';
			b.setAttribute('aria-label', d === 'next' ? t('Дальше', 'Next') : t('Назад', 'Back'));
			b.innerHTML = arrow(d);
			b.addEventListener('click', () => tr.scrollBy({ left: (d === 'next' ? 1 : -1) * tr.clientWidth * 0.8, behavior: reduce ? 'auto' : 'smooth' }));
			nav.append(b);
			return b;
		};
		const prev = mk('prev');
		const next = mk('next');
		head.insertBefore(nav, head.lastElementChild);
		const upd = () => {
			const scrollable = tr.scrollWidth > tr.clientWidth + 4;
			nav.hidden = !scrollable;
			prev.disabled = tr.scrollLeft <= 4;
			next.disabled = tr.scrollLeft + tr.clientWidth >= tr.scrollWidth - 4;
		};
		tr.addEventListener('scroll', upd, { passive: true });
		window.addEventListener('resize', upd);
		upd();
	});

	// Article: sticky table of contents in the side column, with reading progress.
	const buildRail = () => {
		const toc = $('.article [data-toc]');
		const list = toc && $('ol', toc);
		const side = $('.sidebar');
		const content = $('.article__content');
		if (!list || !side || !content || $('.toc-rail')) return !!$('.toc-rail');
		const rail = document.createElement('section');
		rail.className = 'box toc-rail';
		rail.setAttribute('aria-label', t('Содержание статьи', 'Article contents'));
		rail.innerHTML = `<h2 class="box__title">${t('В этой статье', 'In this article')}</h2>`;
		const ol = list.cloneNode(true);
		rail.append(ol);
		const prog = document.createElement('div');
		prog.className = 'toc-rail__progress';
		prog.innerHTML = `<span data-rail-pct>${t('Прочитано', 'Read')}: 0%</span><span class="toc-rail__bar"><i></i></span>`;
		rail.append(prog);
		side.append(rail);
		body.classList.add('has-toc-rail');

		const links = $$('a', ol);
		const heads = links.map((a) => document.getElementById(decodeURIComponent(a.hash.slice(1)))).filter(Boolean);
		const pct = $('[data-rail-pct]', prog);
		const barI = $('.toc-rail__bar i', prog);
		let raf = false;
		const upd = () => {
			raf = false;
			const off = (header ? header.getBoundingClientRect().bottom : 60) + 24;
			let cur = -1;
			heads.forEach((h, i) => { if (h.getBoundingClientRect().top <= off) cur = i; });
			links.forEach((a, i) => a.classList.toggle('is-active', i === cur));
			const r = content.getBoundingClientRect();
			const total = r.height - window.innerHeight * 0.6;
			const p = Math.min(1, Math.max(0, (off - r.top) / Math.max(1, total)));
			barI.style.setProperty('--p', p.toFixed(3));
			pct.textContent = `${t('Прочитано', 'Read')}: ${Math.round(p * 100)}%`;
		};
		window.addEventListener('scroll', () => { if (!raf) { raf = true; requestAnimationFrame(upd); } }, { passive: true });
		window.addEventListener('resize', upd);
		upd();
		return true;
	};
	if (body.classList.contains('single-post')) {
		let tries = 0;
		const tick = () => { if (!buildRail() && ++tries < 20) setTimeout(tick, 150); };
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tick); else tick();
	}
})();

/* Personal feed: tabs ("For you" / "All latest") and the one-click opt-in. */
(() => {
	const body = document.body;
	if (!body.classList.contains('ui3')) return;
	const $ = (s, r = document) => r.querySelector(s);
	const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
	const en = (document.documentElement.lang || '').toLowerCase().startsWith('en');
	const KEY = 'fl3-feed-tab';

	const box = $('[data-feed-tabs]');
	if (box) {
		const set = (name, save) => {
			$$('[data-feed-tab]', box).forEach((b) => {
				const on = b.dataset.feedTab === name;
				b.classList.toggle('is-active', on);
				b.setAttribute('aria-selected', String(on));
			});
			$$('[data-feed-panel]', box).forEach((p) => { p.hidden = p.dataset.feedPanel !== name; });
			if (save) { try { localStorage.setItem(KEY, name); } catch (e) { /* storage blocked */ } }
		};
		box.addEventListener('click', (e) => {
			const b = e.target.closest('[data-feed-tab]');
			if (b) set(b.dataset.feedTab, true);
		});
		let saved = null;
		try { saved = localStorage.getItem(KEY); } catch (e) { /* ignore */ }
		if (saved === 'all') set('all', false);
	}

	const inv = $('[data-feed-invite]');
	if (inv) {
		const msg = $('[data-feed-msg]', inv);
		$('[data-feed-optin]', inv)?.addEventListener('click', async (e) => {
			const btn = e.currentTarget;
			const cfg = window.RSP || {};
			btn.disabled = true;
			msg.textContent = en ? 'Saving…' : 'Сохраняем…';
			try {
				const res = await fetch(cfg.rest + 'fragtag/recs', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body: JSON.stringify({ on: true }),
				});
				const j = await res.json();
				if (!res.ok || j.ok === false) throw new Error(j.message || '');
				location.reload();
			} catch (err) {
				btn.disabled = false;
				msg.textContent = err.message || (en ? 'Could not save, try again later.' : 'Не удалось сохранить, попробуйте позже.');
			}
		});
	}
})();
