// The CASTILE buying block in English mode on the Local site: choose a scent and a size,
// see the English labels and the price, add to cart, and see the cart count go up.
// Also checks the Thai /castile/ buying block still shows Thai labels (no English leaking in).
//   node tools/language-switcher/castile-buying.e2e.mjs
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = 'http://amouriq-backup.local';
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PORT = 9452;
const profile = mkdtempSync(join(tmpdir(), 'amq-buy-'));
const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`, '--no-first-run', '--disable-gpu', 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let wsUrl;
for (let i = 0; i < 50 && !wsUrl; i++) {
	try { wsUrl = (await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json()).find((t) => t.type === 'page')?.webSocketDebuggerUrl; } catch {}
	if (!wsUrl) await sleep(200);
}
const ws = new WebSocket(wsUrl);
await new Promise((r) => (ws.onopen = r));
let id = 0, loaded = null;
const pending = new Map();
ws.onmessage = (e) => {
	const m = JSON.parse(e.data);
	if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
	if (m.method === 'Page.loadEventFired' && loaded) { loaded(); loaded = null; }
};
const send = (method, params = {}) => new Promise((res) => { const i = ++id; pending.set(i, res); ws.send(JSON.stringify({ id: i, method, params })); });
const evaluate = async (expr) => (await send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true })).result.result.value;
const failures = [];
const check = (name, ok, detail = '') => { console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${ok ? '' : '  -> ' + detail}`); if (!ok) failures.push(name); };

try {
	await send('Page.enable');
	await send('Network.enable');
	await send('Network.setCacheDisabled', { cacheDisabled: true });
	await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
	const open = async (path) => {
		const done = new Promise((r) => (loaded = r));
		await send('Page.navigate', { url: BASE + path });
		await done;
		await sleep(3500);
	};

	await open('/castile/');
	const thai = await evaluate(`(() => {
		const vis = (e) => e.getBoundingClientRect().width > 0;
		return [...document.querySelectorAll('.amq-buying-block button, .amq-buying-block a')].filter(vis).map((b) => b.textContent.trim());
	})()`);
	check('Thai page keeps its Thai buying block (no English "Add to cart")', thai.some((t) => /[฀-๿]/.test(t)) && !thai.some((t) => /^add to cart$/i.test(t)), JSON.stringify(thai));

	await open('/en/castile/');

	const info = await evaluate(`(() => {
		const q = (s) => [...document.querySelectorAll(s)];
		const vis = (e) => e.getBoundingClientRect().width > 0;
		return {
			scents: q('.amq-bb-scent-btn').filter(vis).map((b) => b.textContent.replace(/\s+/g, ' ').trim()),
			sizes: q('.amq-bb-size-btn').filter(vis).map((b) => b.textContent.replace(/\s+/g, ' ').trim()),
			add: q('.amq-buying-block button, .amq-buying-block a').filter(vis).map((b) => b.textContent.trim()).filter((t) => /cart/i.test(t)),
		};
	})()`);
	check('English scent buttons shown', info.scents.length === 3 && info.scents.every((t) => !/[\u0E00-\u0E7F]/.test(t)), JSON.stringify(info.scents));
	check('English size buttons shown', info.sizes.length >= 2, JSON.stringify(info.sizes));
	check('English "Add to cart" button shown', info.add.some((t) => /^add to cart$/i.test(t)), JSON.stringify(info.add));

	// pick the second scent and the second size, read the price, add to cart
	await evaluate(`[...document.querySelectorAll('.amq-bb-scent-btn')].filter((b) => b.getBoundingClientRect().width > 0)[1].click()`);
	await evaluate(`[...document.querySelectorAll('.amq-bb-size-btn')].filter((b) => b.getBoundingClientRect().width > 0)[1].click()`);
	await sleep(500);
	const price = await evaluate(`(document.querySelector('.amq-buying-block__price') || document.querySelector('[class*=price]') || {}).textContent`);
	check('a price in baht is shown', /฿\s*[\d,]+/.test(price || ''), price);
	await evaluate(`window.__adds = []; const of = window.fetch; window.fetch = function (u, o) { return of.apply(this, arguments).then((r) => { r.clone().json().then((j) => window.__adds.push(j)).catch(() => {}); return r; }); };`);
	await evaluate(`[...document.querySelectorAll('.amq-buying-block button, .amq-buying-block a')].filter((b) => b.getBoundingClientRect().width > 0 && /^add to cart$/i.test(b.textContent.trim()))[0].click()`);
	await sleep(1200);
	const msg = await evaluate(`(() => { const m = document.getElementById('amq-bb-success-message'); return m && !m.hidden ? m.textContent.trim() : null; })()`);
	check('the added-to-cart message is shown in English', !!msg && !/[\u0E00-\u0E7F]/.test(msg), msg);
	const adds = await evaluate(`JSON.stringify(window.__adds)`);
	check('the cart accepted the item (cart count 1 or more)', /"success":true/.test(adds) && /"cart_count":[1-9]/.test(adds), adds);
} finally {
	ws.close();
	chrome.kill();
	await sleep(500);
	try { rmSync(profile, { recursive: true, force: true }); } catch {}
}
console.log(failures.length ? `\n${failures.length} FAILED` : '\nAll passed');
process.exit(failures.length ? 1 : 0);
