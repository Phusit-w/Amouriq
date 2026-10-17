// Real-browser check of the Language switcher on the Local site (ticket 02).
// Raw Chrome DevTools Protocol over Node's global WebSocket: no npm install.
//   node tools/language-switcher/switcher.e2e.mjs [baseUrl]
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = (process.argv[2] || 'http://amouriq-backup.local').replace(/\/$/, '');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PORT = 9444;
const profile = mkdtempSync(join(tmpdir(), 'amq-lang-'));
const chrome = spawn(CHROME, [
	'--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`,
	'--no-first-run', '--disable-gpu', 'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function pageSocket() {
	for (let i = 0; i < 50; i++) {
		try {
			const list = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
			const page = list.find((t) => t.type === 'page');
			if (page) return page.webSocketDebuggerUrl;
		} catch {}
		await sleep(200);
	}
	throw new Error('Chrome did not start');
}

const ws = new WebSocket(await pageSocket());
await new Promise((r) => (ws.onopen = r));
let id = 0;
const pending = new Map();
let loaded = null;
ws.onmessage = (e) => {
	const m = JSON.parse(e.data);
	if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
	if (m.method === 'Page.loadEventFired' && loaded) { loaded(); loaded = null; }
};
const send = (method, params = {}) => new Promise((res) => {
	const i = ++id; pending.set(i, res); ws.send(JSON.stringify({ id: i, method, params }));
});
const evaluate = async (expr) => (await send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true })).result.result.value;
async function go(path) {
	const done = new Promise((r) => (loaded = r));
	await send('Page.navigate', { url: BASE + path });
	await done;
	await sleep(400);
}
async function viewport(width, mobile) {
	await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile });
}

async function clickSwitcherLink() {
	const done = new Promise((r) => (loaded = r));
	await evaluate(`[...document.querySelectorAll('.amq-lang-switch a')].find((a) => a.getBoundingClientRect().width > 0).click()`);
	await done;
	await sleep(400);
}

const failures = [];
const check = (name, ok, detail = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${ok ? '' : '  -> ' + detail}`);
	if (!ok) failures.push(name);
};

// What a customer sees of the switcher on the current page.
const readSwitcher = () => evaluate(`(() => {
	const all = [...document.querySelectorAll('.amq-lang-switch')];
	const box = all.find((b) => b.getBoundingClientRect().width > 0) || all[0];
	if (!box) return null;
	const r = box.getBoundingClientRect();
	const link = box.querySelector('a');
	return {
		plain: [...box.querySelectorAll('span')].map((s) => s.textContent.trim()).filter((t) => t === 'EN' || t === 'TH'),
		linkText: link ? link.textContent.trim() : null,
		linkPath: link ? new URL(link.href).pathname : null,
		visible: r.width > 0 && r.height > 0 && getComputedStyle(box).visibility !== 'hidden',
		inViewport: r.left >= 0 && r.right <= innerWidth,
		overflowX: document.documentElement.scrollWidth > innerWidth,
		lang: document.documentElement.lang,
		fontSheet: (document.getElementById('amq-lang-font-css') || {}).href || '',
		fontGoogle: (document.getElementById('amq-lang-font-google-css') || {}).href || '',
		path: location.pathname,
	};
})()`);

try {
	await send('Page.enable');
	await send('Network.enable');
	await send('Network.setCacheDisabled', { cacheDisabled: true });

	// Desktop, Thai home
	await viewport(1440, false);
	await go('/');
	let s = await readSwitcher();
	check('desktop: switcher is in the header on the Thai home page', !!s && s.visible, JSON.stringify(s));
	check('desktop: Thai page shows TH as plain text and EN as the link', s && s.plain.includes('TH') && s.linkText === 'EN', JSON.stringify(s));
	check('desktop: EN link goes to /en/', s && s.linkPath === '/en/', JSON.stringify(s));
	check('desktop: Thai page loads the Thai font (Prompt)', s && s.fontSheet.includes('amq-lang-font-th.css') && s.fontGoogle.includes('Prompt'), s && s.fontSheet);

	// Click EN
	await clickSwitcherLink();
	s = await readSwitcher();
	check('desktop: clicking EN lands on /en/ in English', s && s.path === '/en/' && /^en/i.test(s.lang), JSON.stringify(s));
	check('desktop: English page shows EN as plain text and TH as the link', s && s.plain.includes('EN') && s.linkText === 'TH', JSON.stringify(s));
	check('desktop: English page loads the English font (Plus Jakarta Sans)', s && s.fontSheet.includes('amq-lang-font-en.css') && s.fontGoogle.includes('Plus+Jakarta+Sans'), s && s.fontSheet);

	// Click TH goes back to the same Thai page
	await clickSwitcherLink();
	s = await readSwitcher();
	check('desktop: clicking TH returns to the Thai home page', s && s.path === '/' && s.lang === 'th', JSON.stringify(s));

	// Another page keeps its place (CASTILE)
	await go('/castile/');
	s = await readSwitcher();
	check('CASTILE: EN link keeps the same page (/en/castile/)', s && s.linkPath === '/en/castile/', JSON.stringify(s));
	await go('/en/castile/');
	s = await readSwitcher();
	check('CASTILE (English): TH link goes back to /castile/', s && s.linkPath === '/castile/' && /^en/i.test(s.lang), JSON.stringify(s));

	// A browser set to English still opens in Thai
	await send('Emulation.setUserAgentOverride', { userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130 Safari/537.36', acceptLanguage: 'en-US,en;q=0.9' });
	await go('/');
	s = await readSwitcher();
	check('English browser: a fresh visit still opens in Thai', s && s.path === '/' && s.lang === 'th', JSON.stringify(s));

	// Having visited English earlier in this same browser profile, the home page still opens in Thai
	await go('/en/');
	await go('/');
	s = await readSwitcher();
	check('revisit: after browsing /en/, opening / again shows Thai (no remembered language)', s && s.path === '/' && s.lang === 'th', JSON.stringify(s));

	// Mobile
	await viewport(390, true);
	await go('/');
	s = await readSwitcher();
	check('mobile: switcher is visible and inside the screen', s && s.visible && s.inViewport, JSON.stringify(s));
	check('mobile: no sideways scrolling is introduced', s && !s.overflowX, JSON.stringify(s));
	await go('/en/');
	s = await readSwitcher();
	check('mobile (English): switcher visible, TH link to /', s && s.visible && s.inViewport && s.linkPath === '/', JSON.stringify(s));
} finally {
	ws.close();
	chrome.kill();
	await sleep(500);
	try { rmSync(profile, { recursive: true, force: true }); } catch {}
}

console.log(failures.length ? `\n${failures.length} FAILED` : '\nAll passed');
process.exit(failures.length ? 1 : 0);
