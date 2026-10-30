(() => {
	const cfg = window.RSP || {};

	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('button[data-fc-vote]');
		if (!btn || btn.disabled) return;
		const row = btn.closest('[data-fc-game]');
		const card = btn.closest('[data-fc]');
		if (!row || !card) return;
		if (!cfg.user) {
			window.location.href = cfg.login || '/wp-login.php';
			return;
		}
		row.querySelectorAll('button[data-fc-vote]').forEach((b) => (b.disabled = true));
		try {
			const res = await fetch(cfg.rest + 'forecast/vote', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify({ appid: Number(row.dataset.fcGame), dir: btn.dataset.fcVote }),
			});
			const data = await res.json();
			if (res.status === 401) { window.location.href = cfg.login || '/wp-login.php'; return; }
			if (!res.ok) throw new Error(data.message || 'Не получилось');
			const total = data.votes.up + data.votes.down;
			const up = total ? Math.round((data.votes.up * 100) / total) : 50;
			row.classList.add('is-voted');
			row.querySelector(`[data-fc-vote="${data.dir}"]`)?.classList.add('is-mine');
			row.querySelector('.fc__bar i').style.width = up + '%';
			row.querySelector('[data-fc-up]').textContent = up + '%';
			row.querySelector('[data-fc-total]').textContent = total;
			const done = card.querySelector('[data-fc-done]');
			if (done && !data.already) done.textContent = data.done;
		} catch (err) {
			row.querySelectorAll('button[data-fc-vote]').forEach((b) => (b.disabled = false));
			row.classList.add('is-error');
			row.title = err.message;
			setTimeout(() => row.classList.remove('is-error'), 1400);
		}
	});
})();
