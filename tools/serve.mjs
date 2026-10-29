#!/usr/bin/env node
/**
 * Tiny static server to preview the snapshot like GitHub Pages does:
 *   node serve.mjs <folder> [port]
 * Serves folder/index.html for directories and 404.html for unknown paths.
 */
import http from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve(process.argv[2] || 'dist');
const port = Number(process.argv[3] || 8090);
const types = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.gif': 'image/gif', '.woff2': 'font/woff2', '.xml': 'application/xml', '.txt': 'text/plain', '.ico': 'image/x-icon' };

async function find(p) {
	const full = path.join(root, p);
	if (!full.startsWith(root)) return null;
	for (const candidate of [full, path.join(full, 'index.html')]) {
		try {
			if ((await stat(candidate)).isFile()) return candidate;
		} catch { /* try next */ }
	}
	return null;
}

http.createServer(async (req, res) => {
	const p = decodeURIComponent(new URL(req.url, 'http://x').pathname);
	let file = await find(p);
	let status = 200;
	if (!file) {
		file = await find('/404.html');
		status = 404;
	}
	if (!file) {
		res.writeHead(404).end('Not found');
		return;
	}
	res.writeHead(status, { 'Content-Type': types[path.extname(file)] || 'application/octet-stream' });
	res.end(await readFile(file));
}).listen(port, () => console.log(`http://localhost:${port}  ←  ${root}`));
