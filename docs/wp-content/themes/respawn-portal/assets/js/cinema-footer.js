/* Cinematic curtain footer: scroll-scrubbed reveal of the giant word, heading and links, plus magnetic pills.
 * Vanilla port of the GSAP ScrollTrigger version; runs only while the footer is near the screen. */
(() => {
	const wrap = document.querySelector('[data-cinema-footer]');
	if (!wrap) return;
	const giant = wrap.querySelector('[data-cf-giant]');
	const title = wrap.querySelector('[data-cf-title]');
	const links = wrap.querySelector('[data-cf-links]');
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const clamp = (v) => Math.min(1, Math.max(0, v));
	const ease = (t) => 1 - Math.pow(1 - t, 3);

	const topBtn = wrap.querySelector('[data-cf-top]');
	if (topBtn) topBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }));
	if (reduce) return;

	const cur = { g: 0, t: 0, l: 0 };
	let raf = 0;
	const targets = () => {
		const vh = window.innerHeight;
		const top = wrap.getBoundingClientRect().top;
		const g = clamp((vh * 0.8 - top) / (vh * 0.8));
		const p2 = clamp((vh * 0.4 - top) / (vh * 0.4));
		return { g, t: clamp(p2 * 1.4), l: clamp(p2 * 1.4 - 0.4), top, vh };
	};
	const paint = () => {
		const g = ease(cur.g), t = ease(cur.t), l = ease(cur.l);
		giant.style.opacity = g;
		giant.style.transform = 'translate(-50%, ' + ((1 - g) * 10) + 'vh) scale(' + (0.8 + 0.2 * g) + ')';
		title.style.opacity = t;
		title.style.transform = 'translateY(' + ((1 - t) * 50) + 'px)';
		links.style.opacity = l;
		links.style.transform = 'translateY(' + ((1 - l) * 50) + 'px)';
	};
	const tick = () => {
		raf = 0;
		const tg = targets();
		document.body.classList.toggle('has-cinema-in', tg.top < tg.vh * 0.5);
		if (tg.top > tg.vh) return;
		cur.g += (tg.g - cur.g) * 0.12;
		cur.t += (tg.t - cur.t) * 0.12;
		cur.l += (tg.l - cur.l) * 0.12;
		paint();
		if (Math.abs(tg.g - cur.g) + Math.abs(tg.t - cur.t) + Math.abs(tg.l - cur.l) > 0.002) raf = requestAnimationFrame(tick);
	};
	const wake = () => { if (!raf) raf = requestAnimationFrame(tick); };
	window.addEventListener('scroll', wake, { passive: true });
	window.addEventListener('resize', wake, { passive: true });
	wake();

	// Magnetic pills: lean toward the pointer, spring back on leave.
	if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
	wrap.querySelectorAll('[data-magnetic]').forEach((el) => {
		el.addEventListener('mousemove', (e) => {
			const r = el.getBoundingClientRect();
			const x = e.clientX - r.left - r.width / 2;
			const y = e.clientY - r.top - r.height / 2;
			el.style.transition = 'transform .25s ease-out, border-color .3s, color .3s, background .3s';
			el.style.transform = 'translate(' + x * 0.3 + 'px,' + y * 0.3 + 'px) rotateX(' + (-y * 0.12) + 'deg) rotateY(' + (x * 0.12) + 'deg) scale(1.05)';
		});
		el.addEventListener('mouseleave', () => {
			el.style.transition = 'transform .9s cubic-bezier(.2,1.4,.4,1), border-color .3s, color .3s, background .3s';
			el.style.transform = '';
		});
	});
})();
