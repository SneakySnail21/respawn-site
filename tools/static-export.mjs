#!/usr/bin/env node
/**
 * Static snapshot of the Respawn site for GitHub Pages and other static hosts.
 *
 *   node static-export.mjs --public=https://USER.github.io/REPO [--site=https://respawn.1c-freelancer.ru] [--out=dist] [--main=URL]
 *
 * Crawls the live site, saves every public page as folder/index.html, downloads CSS, JS, fonts and images,
 * rewrites every address to --public, removes API links and marks the page "is-static" (theme CSS hides
 * the forms that need PHP: login, likes, bookmarks, ratings, comments). --main is where readers can go
 * for the full interactive version.
 */
import { mkdir, writeFile, rm, readFile, readdir, stat } from 'node:fs/promises';
import path from 'node:path';

const args = Object.fromEntries(process.argv.slice(2).map((a) => {
	const m = a.match(/^--([^=]+)(?:=(.*))?$/);
	return m ? [m[1], m[2] ?? true] : [a, true];
}));
const SITE = String(args.site || 'https://respawn.1c-freelancer.ru').replace(/\/+$/, '');
const PUBLIC = String(args.public || '').replace(/\/+$/, '');
const MAIN = String(args.main || SITE);
const OUT = path.resolve(String(args.out || 'dist'));
if (!PUBLIC) {
	console.error('Укажите адрес публикации: --public=https://USER.github.io/REPO');
	process.exit(1);
}
const ORIGIN = new URL(SITE).origin;
const HOST = new URL(SITE).host;

const SKIP_PATH = /^\/(wp-admin|wp-login|wp-json|xmlrpc|login|register|write|bookmarks|my-feed|feed|comments\/feed|cart|checkout)(\/|\.|$)|\/feed\/?$|\/embed\/?$|\/trackback\/?$/i;
const ASSET_EXT = /\.(css|js|mjs|json|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif|ico|mp4|webm|pdf|xml|txt|map)$/i;

const seenPages = new Set();
const queue = [];
const assets = new Set();
const saved = [];
const failures = [];

const rel = (urlPath) => decodeURIComponent(urlPath).replace(/^\/+/, '');

async function save(file, data) {
	const target = path.join(OUT, file);
	await mkdir(path.dirname(target), { recursive: true });
	await writeFile(target, data);
	saved.push(file);
}

async function get(url, as = 'text') {
	for (let attempt = 1; attempt <= 3; attempt++) {
		try {
			const res = await fetch(url, { redirect: 'follow', headers: { 'User-Agent': 'RespawnStaticExport/1.0' } });
			const buf = as === 'text' ? await res.text() : Buffer.from(await res.arrayBuffer());
			return { status: res.status, body: buf, url: res.url, type: res.headers.get('content-type') || '' };
		} catch (e) {
			if (attempt === 3) throw e;
			await new Promise((r) => setTimeout(r, 400 * attempt));
		}
	}
}

function pageUrl(href, base) {
	try {
		const u = new URL(href, base);
		if (u.origin !== ORIGIN) return null;
		return u;
	} catch {
		return null;
	}
}

function pageFile(u) {
	let p = decodeURIComponent(u.pathname);
	if (!p.endsWith('/')) p += '/';
	return path.posix.join(p, 'index.html').replace(/^\/+/, '');
}

function enqueue(u) {
	if (!u || u.search || SKIP_PATH.test(u.pathname) || ASSET_EXT.test(u.pathname)) return;
	u.hash = '';
	const key = u.pathname.endsWith('/') ? u.pathname : u.pathname + '/';
	if (seenPages.has(key)) return;
	seenPages.add(key);
	queue.push(ORIGIN + key);
}

function collectLinks(html, base) {
	for (const m of html.matchAll(/<a\s[^>]*href=["']([^"'#]+)["']/gi)) enqueue(pageUrl(m[1].replace(/&amp;/g, '&'), base));
	for (const m of html.matchAll(/<link[^>]+rel=["']next["'][^>]*href=["']([^"']+)["']/gi)) enqueue(pageUrl(m[1], base));
}

function collectAssets(text, base, { css = false } = {}) {
	const found = [];
	if (!css) {
		for (const m of text.matchAll(/(?:src|href|data-src|data-lazy-src|poster|content)=["']([^"']+)["']/gi)) found.push(m[1]);
		for (const m of text.matchAll(/srcset=["']([^"']+)["']/gi)) m[1].split(',').forEach((s) => found.push(s.trim().split(/\s+/)[0]));
		for (const m of text.matchAll(/url\(\s*['"]?([^'")]+)['"]?\s*\)/gi)) found.push(m[1]);
	} else {
		for (const m of text.matchAll(/url\(\s*['"]?([^'")]+)['"]?\s*\)/gi)) found.push(m[1]);
		for (const m of text.matchAll(/@import\s+['"]([^'"]+)['"]/gi)) found.push(m[1]);
	}
	for (const raw of found) {
		if (!raw || raw.startsWith('data:') || raw.startsWith('#')) continue;
		const u = pageUrl(raw.replace(/&amp;/g, '&'), base);
		if (u && (ASSET_EXT.test(u.pathname) || /\/wp-(content|includes)\//.test(u.pathname)) && !/\.(php)$/i.test(u.pathname)) assets.add(u.origin + u.pathname);
	}
}

async function fetchAsset(url) {
	const u = new URL(url);
	const file = rel(u.pathname);
	if (saved.includes(file)) return;
	try {
		const res = await get(url, 'binary');
		if (res.status !== 200) {
			failures.push(`${res.status} ${url}`);
			return;
		}
		await save(file, res.body);
		if (/\.css$/i.test(u.pathname)) {
			const css = res.body.toString('utf8');
			const before = assets.size;
			collectAssets(css, url, { css: true });
			if (assets.size !== before) await drain();
		}
	} catch (e) {
		failures.push(`${e.message} ${url}`);
	}
}

let draining = false;
const done = new Set();
async function drain() {
	if (draining) return;
	draining = true;
	try {
		while (true) {
			const todo = [...assets].filter((a) => !done.has(a));
			if (!todo.length) break;
			todo.forEach((a) => done.add(a));
			await pool(todo, 8, fetchAsset);
		}
	} finally {
		draining = false;
	}
}

async function pool(items, size, fn) {
	let i = 0;
	await Promise.all(Array.from({ length: Math.min(size, items.length) }, async () => {
		while (i < items.length) await fn(items[i++]);
	}));
}

const escOrigin = (s) => s
	.split(ORIGIN).join(PUBLIC)
	.split(ORIGIN.replace('https:', 'http:')).join(PUBLIC)
	.split(ORIGIN.replace(/\//g, '\\/')).join(PUBLIC.replace(/\//g, '\\/'))
	.split(`//${HOST}`).join(PUBLIC.replace(/^https?:/, ''));

// The copy lives on another address and has no consent banner: drop analytics and ad tags (Google Site Kit, gtag, tag manager).
function stripTracking(html) {
	return html
		.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, (tag) => (/googletagmanager|google-analytics|gtag\(|googlesitekit|google-site-kit|_googlesitekit/i.test(tag) ? '' : tag))
		.replace(/<link[^>]+(?:googletagmanager|google-analytics|google-site-kit|googlesitekit)[^>]*>\s*/gi, '')
		.replace(/<meta[^>]+google-site-verification[^>]*>\s*/gi, '')
		.replace(/<meta[^>]+name=["']generator["'][^>]*>\s*/gi, '')
		.replace(/<link[^>]+type=["']application\/(?:json|json\+oembed|xml\+oembed)["'][^>]*>\s*/gi, '');
}

function transform(html) {
	let out = stripTracking(html)
		.replace(/<link[^>]+rel=["']https:\/\/api\.w\.org\/["'][^>]*>\s*/gi, '')
		.replace(/<link[^>]+(?:oembed|wlwmanifest|EditURI|pingback|shortlink)[^>]*>\s*/gi, '')
		.replace(/<link[^>]+rel=["']alternate["'][^>]+type=["']application\/(?:rss|atom)\+xml["'][^>]*>\s*/gi, '')
		.replace(/<script[^>]*id=["']rsp-main-js-extra["'][^>]*>[\s\S]*?<\/script>/i, '<script>window.RSP={static:true};</script>')
		.replace(/<script[^>]*id=["']rsp-features-js-extra["'][^>]*>[\s\S]*?<\/script>/i, '');
	out = escOrigin(out);
	// Pages that need PHP (login, writing, bookmarks) live only on the main site.
	out = out.replace(new RegExp(`${PUBLIC.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/(login|register|write|bookmarks|my-feed)/`, 'g'), `${MAIN}/$1/`);
	out = out.replace(/<body([^>]*?)class="([^"]*)"/i, '<body$1class="$2 is-static"');
	const banner = `<div class="static-note" role="note">Это статичная копия Respawn для чтения. Регистрация, форум, оценки и закладки работают на <a href="${MAIN}" rel="nofollow">основном сайте</a>.</div>`;
	return out.replace(/(<body[^>]*>)/i, `$1${banner}`);
}

async function crawlPage(url) {
	const res = await get(url);
	if (res.status !== 200 || !/html/.test(res.type)) {
		if (res.status !== 200) failures.push(`${res.status} ${url}`);
		return;
	}
	const final = new URL(res.url);
	if (final.origin !== ORIGIN) return;
	const clean = stripTracking(res.body);
	collectLinks(clean, res.url);
	collectAssets(clean, res.url);
	await save(pageFile(final), transform(clean));
}

async function readSitemaps() {
	const urls = [];
	const index = await get(`${SITE}/sitemap_index.xml`);
	if (index.status !== 200) return urls;
	const files = [...index.body.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
	await save('sitemap_index.xml', escOrigin(index.body.replace(/<\?xml-stylesheet[^>]*\?>\s*/g, '')));
	for (const f of files) {
		const r = await get(f);
		if (r.status !== 200) continue;
		const name = rel(new URL(f).pathname);
		await save(name, escOrigin(r.body.replace(/<\?xml-stylesheet[^>]*\?>\s*/g, '')));
		for (const m of r.body.matchAll(/<loc>([^<]+)<\/loc>/g)) if (!/\.(jpe?g|png|webp|gif)$/i.test(m[1])) urls.push(m[1]);
	}
	return urls;
}

async function walk(dir) {
	const out = [];
	for (const e of await readdir(dir, { withFileTypes: true })) {
		const p = path.join(dir, e.name);
		if (e.isDirectory()) out.push(...await walk(p));
		else out.push(p);
	}
	return out;
}

async function verifyLinks() {
	const missing = new Map();
	for (const file of (await walk(OUT)).filter((f) => f.endsWith('.html'))) {
		const html = await readFile(file, 'utf8');
		for (const m of html.matchAll(/(?:href|src)=["']([^"'#?]+)/g)) {
			const href = m[1];
			if (!href.startsWith(PUBLIC + '/')) continue;
			const p = decodeURIComponent(href.slice(PUBLIC.length));
			const targets = p.endsWith('/') ? [path.join(OUT, p, 'index.html')] : [path.join(OUT, p), path.join(OUT, p, 'index.html')];
			let ok = false;
			for (const t of targets) {
				try { await stat(t); ok = true; break; } catch { /* next */ }
			}
			if (!ok && !SKIP_PATH.test(p)) missing.set(p, (missing.get(p) || 0) + 1);
		}
	}
	return missing;
}

console.log(`Сайт: ${SITE}\nПубликация: ${PUBLIC}\nПапка: ${OUT}`);
await rm(OUT, { recursive: true, force: true });
await mkdir(OUT, { recursive: true });

enqueue(new URL('/', ORIGIN));
enqueue(new URL('/games/', ORIGIN));
enqueue(new URL('/forums/', ORIGIN));
for (const u of await readSitemaps()) enqueue(pageUrl(u, ORIGIN));
console.log(`В очереди после карты сайта: ${queue.length}`);

let index = 0;
const crawler = async () => {
	while (index < queue.length) {
		const url = queue[index++];
		try {
			await crawlPage(url);
		} catch (e) {
			failures.push(`${e.message} ${url}`);
		}
	}
};
// Pages found while crawling extend the queue, so keep workers alive until it stops growing.
for (let round = 0; round < 50; round++) {
	const before = queue.length;
	await Promise.all(Array.from({ length: 4 }, crawler));
	if (queue.length === before && index >= queue.length) break;
}
await drain();

// 404 page, robots, jekyll opt-out.
const nf = await get(`${SITE}/this-page-does-not-exist-respawn/`);
if (nf.status === 404 && /html/.test(nf.type)) {
	collectAssets(stripTracking(nf.body), nf.url);
	await save('404.html', transform(nf.body));
	await drain();
}
await save('robots.txt', `User-agent: *\nAllow: /\n\nSitemap: ${PUBLIC}/sitemap_index.xml\n`);
await save('.nojekyll', '');

const pages = saved.filter((f) => f.endsWith('.html')).length;
console.log(`Страниц: ${pages}, всего файлов: ${saved.length}`);
const missing = await verifyLinks();
if (missing.size) {
	console.log(`Ссылки без файла (${missing.size}):`);
	for (const [p, n] of [...missing].slice(0, 20)) console.log(`  ${p}  (×${n})`);
}
if (failures.length) {
	console.log(`Ошибки загрузки (${failures.length}):`);
	failures.slice(0, 20).forEach((f) => console.log('  ' + f));
}
