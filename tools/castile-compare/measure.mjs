// Measures computed layout/typography per top-level section of the design (or a built page) at several viewport
// widths and writes JSON. Used to drive the Elementor values for the CASTILE page.
// Usage: node tools/castile-compare/measure.mjs --target design|local|live --out file.json [--widths 1440,768,390]
import { writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { openBrowser, sleep } from './cdp.mjs';

const arg = (n, d) => { const i = process.argv.indexOf(`--${n}`); return i > -1 ? process.argv[i + 1] : d; };
const target = arg('target', 'design');
const out = arg('out');
const widths = arg('widths', '1440,768,390').split(',').map(Number);
const designFile = arg('design', 'D:/Local/app/AMOURIQ Lavender Soap Sales Page/AMOURIQ Lavender Sales Page.dc.html');
const targets = {
  design: { url: pathToFileURL(designFile).href, sections: 'section[data-screen-label]' },
  local: { url: arg('local', 'http://amouriq-backup.local/castile/'), sections: '[data-elementor-id="5309"] > .e-parent' },
  live: { url: arg('live', 'https://amouriq.co.th/castile/'), sections: '[data-elementor-id="5309"] > .e-parent' },
};
const t = targets[target];

const MEASURE = String.raw`(sel) => {
  const px = (v) => parseFloat(v) || 0;
  const rgb = (c) => { const m = c.match(/^rgba?\((\d+), (\d+), (\d+)(?:, ([\d.]+))?\)$/); if (!m) return null;
    if (m[4] !== undefined && +m[4] < 1) return null; return '#' + [m[1], m[2], m[3]].map((x) => (+x).toString(16).padStart(2, '0')).join(''); };
  return [...document.querySelectorAll(sel)].map((s, i) => {
    const cs = getComputedStyle(s);
    const sr = s.getBoundingClientRect();
    // content wrapper: first descendant (depth <= 3) with a max-width, else none
    let wrap = null;
    const queue = [...s.children].map((e) => [e, 1]);
    while (queue.length) { const [e, d] = queue.shift(); const w = getComputedStyle(e).maxWidth; if (w !== 'none' && w.endsWith('px')) { wrap = e; break; } if (d < 3) queue.push(...[...e.children].map((c) => [c, d + 1])); }
    const texts = [];
    s.querySelectorAll('*').forEach((e) => {
      const own = [...e.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent).join('').trim();
      if (!own) return;
      const c = getComputedStyle(e);
      const r = e.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) return;
      texts.push({ tag: e.tagName.toLowerCase(), text: e.textContent.trim().replace(/\s+/g, ' ').slice(0, 160),
        fs: px(c.fontSize), fw: c.fontWeight, lh: c.lineHeight === 'normal' ? null : px(c.lineHeight), ls: c.letterSpacing === 'normal' ? 0 : px(c.letterSpacing),
        color: rgb(c.color), ta: c.textAlign, tt: c.textTransform, ff: c.fontFamily.split(',')[0].replace(/["']/g, '').trim(),
        maxw: c.maxWidth.endsWith('px') ? px(c.maxWidth) : null, w: Math.round(r.width), x: Math.round(r.left - sr.left), mb: px(c.marginBottom), mt: px(c.marginTop) });
    });
    return { i, label: s.dataset.screenLabel || '', pad: [px(cs.paddingTop), px(cs.paddingRight), px(cs.paddingBottom), px(cs.paddingLeft)],
      bg: cs.backgroundColor, display: cs.display, wrapMax: wrap ? px(getComputedStyle(wrap).maxWidth) : null, height: Math.round(sr.height), texts };
  });
}`;

const b = await openBrowser();
try {
  const result = {};
  for (const w of widths) {
    await b.send('Emulation.setDeviceMetricsOverride', { width: w, height: 900, deviceScaleFactor: 1, mobile: w < 700 });
    await b.send('Page.navigate', { url: t.url });
    await sleep(3500);
    result[w] = await b.evaluate(`(${MEASURE})(${JSON.stringify(t.sections)})`);
    console.log(`${target} @${w}: ${result[w].length} sections`);
  }
  writeFileSync(out, JSON.stringify(result));
} finally { await b.close(); }
