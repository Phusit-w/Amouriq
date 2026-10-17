// Product pages in English mode on the Local site: choose a Size, see the price in baht,
// add to cart, then the English cart and checkout still list the item with baht prices.
// Raw CDP over Node's global WebSocket: no npm install. Needs the ticket-05 translations loaded.
//   node tools/language-switcher/products-buying.e2e.mjs
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = 'http://amouriq-backup.local';
const PRODUCTS = ['/en/product/castor-oil/', '/en/product/olive-castile-soap-lavender/'];
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PORT = 9453;
const THAI = /[\u0E00-\u0E3E\u0E40-\u0E7F]/;
const BAHT = /฿\s*[\d,]+/;
const profile = mkdtempSync(join(tmpdir(), 'amq-prod-'));
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
const waitLoad = async (action) => {
	const done = new Promise((r) => (loaded = r));
	await action();
	await done;
	await sleep(2500);
};
const open = (path) => waitLoad(() => send('Page.navigate', { url: BASE + path }));

try {
	await send('Page.enable');
	await send('Network.enable');
	await send('Network.setCacheDisabled', { cacheDisabled: true });
	await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });

	const added = [];
	for (const path of PRODUCTS) {
		await open(path);
		const name = await evaluate(`document.querySelector('h1.product_title')?.textContent.trim()`);
		const sizes = await evaluate(`[...document.querySelectorAll('form.variations_form li.variable-item')].map((li) => li.dataset.value)`);
		check(`${path}: Size choices shown`, sizes?.length >= 2, JSON.stringify(sizes));
		// pick the last Size (largest), wait for the variation price, then add to cart
		await evaluate(`[...document.querySelectorAll('form.variations_form li.variable-item')].at(-1).click()`);
		await sleep(800);
		const v = await evaluate(`(() => {
			const price = document.querySelector('.woocommerce-variation-price .price')?.textContent.trim() || '';
			const btn = document.querySelector('form.variations_form .single_add_to_cart_button');
			return { price, button: btn?.textContent.trim(), enabled: !!btn && !btn.classList.contains('disabled') };
		})()`);
		check(`${path}: variation price in baht`, BAHT.test(v.price), v.price);
		check(`${path}: English "Add to cart" enabled`, v.enabled && /^add to cart$/i.test(v.button) && !THAI.test(v.button), JSON.stringify(v));
		// Blocksy adds to the cart over AJAX (no page load): wait for the header cart count to go up
		const before = await evaluate(`+document.querySelector('.ct-dynamic-count-cart')?.dataset.count || 0`);
		await evaluate(`document.querySelector('form.variations_form .single_add_to_cart_button').click()`);
		let after = before;
		for (let i = 0; i < 40 && after <= before; i++) {
			await sleep(250);
			after = await evaluate(`+document.querySelector('.ct-dynamic-count-cart')?.dataset.count || 0`);
		}
		check(`${path}: cart count goes up`, after > before, `${before} -> ${after}`);
		added.push(name);
	}

	for (const page of ['/en/cart-page/', '/en/checkout/']) {
		await open(page);
		// the product page title shows "–" where the checkout shows "-"
		const dash = (s) => s.replace(/[–—]/g, '-');
		const text = await evaluate(`document.querySelector('main, #main, .site-main, body').innerText`);
		const missing = added.filter((n) => !dash(text).includes(dash(n)));
		check(`${page}: lists both added products`, !missing.length, 'missing ' + JSON.stringify(missing));
		check(`${page}: prices in baht`, BAHT.test(text), text.slice(0, 200));
	}
} finally {
	ws.close();
	chrome.kill();
	await sleep(500);
	try { rmSync(profile, { recursive: true, force: true }); } catch {}
}
console.log(failures.length ? `\n${failures.length} FAILED` : '\nAll passed');
process.exit(failures.length ? 1 : 0);
