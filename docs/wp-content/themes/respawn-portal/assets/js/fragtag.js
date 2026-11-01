/* FragTag: copy the tag, rename it, choose interests, ask to be told when a platform arrives. */
(() => {
	const T = window.rspT || ((s) => s);
	const cfg = window.RSP || {};

	document.addEventListener('click', (e) => {
		const chip = e.target.closest('[data-copy]');
		if (!chip) return;
		const text = chip.getAttribute('data-copy');
		const done = () => {
			chip.classList.add('is-copied');
			const span = chip.querySelector('span');
			const old = span.textContent;
			span.textContent = T('Скопировано');
			setTimeout(() => { span.textContent = old; chip.classList.remove('is-copied'); }, 1400);
		};
		if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, () => {});
		else { const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (err) {} ta.remove(); }
	});

	const root = document.querySelector('[data-fragtag]');
	if (!root) return;
	const base = root.dataset.rest;
	const post = (path, body) => fetch(base + path, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
		body: JSON.stringify(body),
	}).then((r) => r.json().then((j) => ({ ok: r.ok, data: j })));

	// rename
	const form = root.querySelector('[data-ft-rename]');
	if (form) {
		form.addEventListener('submit', (e) => {
			e.preventDefault();
			const msg = form.querySelector('[data-ft-msg]');
			msg.className = 'ft__msg';
			post('rename', { name: form.name.value }).then((r) => {
				if (r.ok && r.data.ok) {
					msg.textContent = T('Сохранено') + ': ' + r.data.tag;
					msg.classList.add('is-ok');
					setTimeout(() => location.reload(), 900);
				} else {
					msg.textContent = T((r.data && r.data.message) || 'Не получилось');
					msg.classList.add('is-err');
				}
			}).catch(() => { msg.textContent = T('Не получилось'); msg.classList.add('is-err'); });
		});
	}

	// platforms: "tell me when ready"
	root.querySelectorAll('[data-ft-wait]').forEach((btn) => btn.addEventListener('click', () => {
		post('wait', { provider: btn.dataset.ftWait }).then((r) => {
			if (!r.ok) return;
			btn.classList.toggle('is-on', !!r.data.on);
			btn.setAttribute('aria-pressed', r.data.on ? 'true' : 'false');
			btn.textContent = r.data.on ? T('Ждёте') : T('Скоро');
		});
	}));

	// interests
	const save = root.querySelector('[data-ft-save]');
	if (save) {
		save.addEventListener('click', () => {
			const out = {};
			root.querySelectorAll('[data-ft-group]').forEach((g) => {
				out[g.dataset.ftGroup] = [...g.querySelectorAll('input:checked')].map((i) => i.value);
			});
			const msg = root.querySelector('[data-ft-msg-int]');
			msg.className = 'ft__msg';
			post('interests', out).then((r) => {
				msg.textContent = r.ok ? T('Сохранено') : T('Не получилось');
				msg.classList.add(r.ok ? 'is-ok' : 'is-err');
				if (r.ok) setTimeout(() => location.reload(), 700);
			}).catch(() => { msg.textContent = T('Не получилось'); msg.classList.add('is-err'); });
		});
	}
})();
