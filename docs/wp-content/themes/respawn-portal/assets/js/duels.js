/* Duel voting: one click, the bars fill in with the live result. */
(() => {
	const rest = window.RSP && window.RSP.rest;
	if (!rest) return;

	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('[data-duel-vote]');
		if (!btn) return;
		const card = btn.closest('[data-duel]');
		if (!card || card.classList.contains('is-voted')) return;
		const side = btn.dataset.duelVote;
		card.classList.add('is-busy');
		try {
			const res = await fetch(rest + 'duel/' + card.dataset.duel + '/vote', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ side }),
			});
			const data = await res.json();
			if (!data.votes) throw new Error('no votes');
			const a = data.votes.a;
			const b = data.votes.b;
			const total = a + b;
			const pa = total ? Math.round((a * 100) / total) : 50;
			const pct = { a: pa, b: 100 - pa };
			['a', 'b'].forEach((s) => {
				const el = card.querySelector('.duel__side--' + s);
				if (!el) return;
				el.disabled = true;
				el.querySelector('.duel__bar i').style.width = pct[s] + '%';
				el.querySelector('.duel__pct').textContent = pct[s] + '%';
			});
			btn.classList.add('is-mine');
			const count = card.querySelector('.duel__count b');
			if (count) count.textContent = total;
			card.classList.add('is-voted');
		} catch (err) {
			/* leave the card as it was; the visitor can try again */
		} finally {
			card.classList.remove('is-busy');
		}
	});
})();
