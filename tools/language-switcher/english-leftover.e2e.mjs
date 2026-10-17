// Lists Thai text still showing on English pages of the Local site (acceptance for the
// translation tickets 03-05). Visible text nodes plus placeholder, title, aria-label and
// button values. Not checked: the page <title>, the meta description and image alt text:
// TranslatePress's free version does not translate those (they need its SEO Pack add-on).
//   node tools/language-switcher/english-leftover.e2e.mjs /en/ /en/castile/ ...
// Exit code 1 when any Thai text is left. The baht sign is not counted as Thai text.
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = 'http://amouriq-backup.local';
const paths = process.argv.slice(2);
if (!paths.length) { console.error('usage: english-leftover.e2e.mjs /en/ [more paths]'); process.exit(2); }
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PORT = 9450;
const profile = mkdtempSync(join(tmpdir(), 'amq-left-'));
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

const collect = `(() => {
	const THAI = /[\\u0E00-\\u0E3E\\u0E40-\\u0E7F]/;
	const found = new Set();
	const walker = document.createTreeWalker(document.documentElement, NodeFilter.SHOW_TEXT);
	for (let n; (n = walker.nextNode());) {
		const p = n.parentElement;
		if (!p || /^(SCRIPT|STYLE|NOSCRIPT|TEMPLATE|TITLE)$/.test(p.tagName)) continue;
		if (THAI.test(n.nodeValue)) found.add(n.nodeValue.trim());
	}
	document.querySelectorAll('[placeholder],[title],[aria-label],input[type=submit],input[type=button]').forEach((e) => {
		for (const a of ['placeholder', 'title', 'aria-label', 'value']) {
			const v = e.getAttribute(a);
			if (v && THAI.test(v)) found.add(v.trim());
		}
	});
	return [...found];
})()`;

let total = 0;
try {
	await send('Page.enable');
	await send('Network.enable');
	await send('Network.setCacheDisabled', { cacheDisabled: true });
	for (const [label, width, mobile] of [['desktop', 1440, false], ['mobile', 390, true]]) {
		await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile });
		for (const path of paths) {
			const done = new Promise((r) => (loaded = r));
			await send('Page.navigate', { url: BASE + path });
			await done;
			await sleep(2500);
			const left = (await send('Runtime.evaluate', { expression: collect, returnByValue: true })).result.result.value;
			total += left.length;
			console.log(`${left.length ? 'FAIL' : 'PASS'}  ${label} ${path}: ${left.length} Thai string(s) left`);
			left.forEach((t) => console.log('        ' + t.replace(/\s+/g, ' ').slice(0, 110)));
		}
	}
} finally {
	ws.close();
	chrome.kill();
	await sleep(500);
	try { rmSync(profile, { recursive: true, force: true }); } catch {}
}
process.exit(total ? 1 : 0);
