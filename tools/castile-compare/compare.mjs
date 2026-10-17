// Screenshots the CASTILE sales page (live, local) and the Claude Design reference,
// one PNG per top-level section, so design and build can be laid side by side.
// Usage: node tools/castile-compare/compare.mjs [--out DIR] [--only design,local,live]
//        [--width 1440] [--local URL] [--live URL] [--design FILE]
// Needs Node 22+ (global WebSocket/fetch) and Google Chrome. Uses a throwaway Chrome profile.
import { mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { openBrowser, sleep } from './cdp.mjs';

const arg = (name, dflt) => {
  const i = process.argv.indexOf(`--${name}`);
  return i > -1 ? process.argv[i + 1] : dflt;
};
const out = arg('out', join(tmpdir(), 'castile-compare'));
const width = Number(arg('width', 1440));
const only = arg('only', 'design,local,live').split(',');
const chrome = arg('chrome', undefined);
const designFile = arg('design', 'D:/Local/app/AMOURIQ Lavender Soap Sales Page/AMOURIQ Lavender Sales Page.dc.html');

const targets = {
  design: { url: pathToFileURL(designFile).href, sections: 'section[data-screen-label]' },
  local: { url: arg('local', 'http://amouriq-backup.local/castile/'), sections: '[data-elementor-id="5309"] > .e-parent' },
  live: { url: arg('live', 'https://amouriq.co.th/castile/'), sections: '[data-elementor-id="5309"] > .e-parent' },
};

const { send, evaluate, close } = await openBrowser({ chrome });

try {
  // Capturing beyond the viewport re-fires scroll triggers, which would start count-up tweens mid-capture.
  // Make every requestAnimationFrame callback see a time far in the future so tweens jump to their final value.
  await send('Page.addScriptToEvaluateOnNewDocument', {
    source: 'window.requestAnimationFrame = (cb) => setTimeout(() => cb(performance.now() + 1e7), 0);',
  });
  for (const name of only) {
    const t = targets[name];
    if (!t) throw new Error(`unknown target ${name}`);
    const url = t.url + (t.url.startsWith('http') ? `${t.url.includes('?') ? '&' : '?'}nc=${Date.now()}` : '');
    await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url });
    await sleep(3000);
    // Scroll through the page in small steps (the design's chart sequencer only fires for rows near the viewport centre,
    // and it ignores prefers-reduced-motion by resetting to 0), then let the tweens finish.
    const total = await evaluate('document.documentElement.scrollHeight');
    for (let y = 0; y < total; y += 200) { await evaluate(`window.scrollTo(0, ${y})`); await sleep(150); }
    await sleep(2500);
    // Headless rAF can leave a tween part-way; write the final text of every [data-countup] and unscale bars, twice
    // (a tween still in flight could overwrite the first pass).
    const finalize = `document.querySelectorAll('[data-countup]').forEach((el) => {
      const d = el.dataset.decimals === undefined ? 2 : parseInt(el.dataset.decimals, 10);
      el.textContent = (el.dataset.prefix || '') + parseFloat(el.dataset.countup).toFixed(d) + (el.dataset.suffix || '');
    }); document.querySelectorAll('[data-seq-bar]').forEach((b) => { b.style.transform = 'scaleX(1)'; });`;
    await evaluate(finalize); await sleep(400); await evaluate(finalize); await sleep(400); await evaluate(finalize);
    await evaluate(`(() => { const st = document.createElement('style');
      st.textContent = '*, *::before, *::after { transition: none !important; animation: none !important; } #cmplz-cookiebanner-container, .cmplz-cookiebanner { display: none !important; }';
      document.head.appendChild(st); })()`);
    await evaluate('window.scrollTo(0, 0)');
    await sleep(500);
    const rects = await evaluate(`(() => [...document.querySelectorAll(${JSON.stringify(t.sections)})].map((e, i) => {
      const r = e.getBoundingClientRect();
      const cls = (e.getAttribute('class') || '').split(/\\s+/).find((c) => /^amq-s\\d/.test(c)) || '';
      return { i, y: r.top + scrollY, h: r.height, label: (e.dataset.screenLabel || cls).replace(/^\\d+\\s*/, '').replace(/^amq-s\\d+-/, '') };
    }))()`);
    const dir = join(out, name);
    mkdirSync(dir, { recursive: true });
    for (const r of rects) {
      if (r.h < 1) continue;
      const shot = await send('Page.captureScreenshot', {
        format: 'png', captureBeyondViewport: true,
        clip: { x: 0, y: r.y, width, height: Math.ceil(r.h), scale: 1 },
      });
      const slug = String(r.label).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      writeFileSync(join(dir, `${String(r.i).padStart(2, '0')}${slug ? '-' + slug : ''}.png`), Buffer.from(shot.data, 'base64'));
    }
    console.log(`${name}: ${rects.length} sections -> ${dir}`);
  }
} finally {
  await close();
}
