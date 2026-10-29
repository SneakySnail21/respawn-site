(() => {
	const cfg = window.RSP || {};

	// Mobile navigation and search panel.
	const toggle = (button, target, cls) => {
		const open = button.getAttribute('aria-expanded') !== 'true';
		button.setAttribute('aria-expanded', String(open));
		if (cls) {
			document.body.classList.toggle(cls, open);
		} else {
			target.hidden = !open;
			if (open) target.querySelector('input')?.focus();
		}
	};
	const navBtn = document.querySelector('[data-toggle-nav]');
	navBtn?.addEventListener('click', () => toggle(navBtn, null, 'nav-open'));
	const searchBtn = document.querySelector('[data-toggle-search]');
	const searchPanel = document.getElementById('site-search');
	searchBtn?.addEventListener('click', () => toggle(searchBtn, searchPanel));

	// Close the profile menu when clicking elsewhere or pressing Escape.
	document.addEventListener('click', (e) => {
		document.querySelectorAll('details.usermenu[open]').forEach((d) => {
			if (!d.contains(e.target)) d.open = false;
		});
	});
	document.addEventListener('keydown', (e) => {
		if (e.key !== 'Escape') return;
		document.querySelectorAll('details.usermenu[open]').forEach((d) => (d.open = false));
		if (searchPanel && !searchPanel.hidden) toggle(searchBtn, searchPanel);
	});

	// Likes on posts and comments.
	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('button[data-like-id]');
		if (!btn || btn.disabled) return;
		btn.disabled = true;
		try {
			const res = await fetch(cfg.rest + 'like', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify({ type: btn.dataset.likeType, id: Number(btn.dataset.likeId) }),
			});
			const data = await res.json();
			if (!res.ok) throw new Error(data.message || 'Не получилось');
			btn.classList.toggle('is-liked', data.liked);
			btn.setAttribute('aria-pressed', String(data.liked));
			btn.querySelector('.like__count').textContent = data.count;
		} catch (err) {
			btn.title = err.message;
			btn.classList.add('is-error');
			setTimeout(() => btn.classList.remove('is-error'), 1200);
		} finally {
			btn.disabled = false;
		}
	});

	// Copy article link.
	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('[data-copy]');
		if (!btn) return;
		try {
			await navigator.clipboard.writeText(btn.dataset.copy);
			const text = btn.textContent;
			btn.textContent = btn.dataset.copied || 'Ссылка скопирована';
			setTimeout(() => (btn.textContent = text), 1500);
		} catch {
			window.prompt(btn.dataset.copied ? 'Номер для перевода' : 'Ссылка на материал', btn.dataset.copy);
		}
	});

	// Count one view per article per browser session.
	if (cfg.postId && cfg.rest) {
		const key = 'rsp-viewed-' + cfg.postId;
		try {
			if (!sessionStorage.getItem(key)) {
				sessionStorage.setItem(key, '1');
				fetch(cfg.rest + 'view/' + cfg.postId, { method: 'POST', keepalive: true });
			}
		} catch {
			/* storage blocked: skip counting */
		}
	}
})();
