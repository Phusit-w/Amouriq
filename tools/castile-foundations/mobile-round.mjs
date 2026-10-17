// Ticket 15 record: gives every heading / text-editor widget the design's tablet and mobile type (font size, line
// height, alignment), matched by text like foundations.mjs. Desktop values are never touched.
//
// Input : current _elementor_data JSON of page 5309 + measure.mjs output for the design (widths 1440,768,390)
// Output: new _elementor_data JSON (written with write-page.php), report of unmatched widgets.
// Idempotent: it only sets or removes the *_tablet / *_mobile typography keys and adds mobile padding to inner containers.
// Containers that already have a mobile padding (sections 15 and 22, from lifestyle-sections.mjs) are left alone.
//
// Usage: node tools/castile-foundations/mobile-round.mjs --page page.json --design design-measure.json --out new.json [--report r.txt]
import { readFileSync, writeFileSync } from 'node:fs';
import { matchTexts, widgetText, strip } from './text-match.mjs';

const arg = (n) => { const i = process.argv.indexOf(`--${n}`); return i > -1 ? process.argv[i + 1] : undefined; };
const page = JSON.parse(readFileSync(arg('page'), 'utf8'));
const design = JSON.parse(readFileSync(arg('design'), 'utf8'));
const VIEWPORTS = { tablet: '768', mobile: '390' };
const round = (n, d = 2) => Math.round(n * 10 ** d) / 10 ** d;
const alignOf = (ta) => (ta === 'center' ? 'center' : ta === 'right' || ta === 'end' ? 'right' : 'left');
const report = [];

function widgetsOf(el, out = []) {
  if (el.elType === 'widget' && (el.widgetType === 'heading' || el.widgetType === 'text-editor')) out.push(el);
  (el.elements || []).forEach((c) => widgetsOf(c, out));
  return out;
}

// Card and panel padding: the design shrinks clamp(24px,4vw,44px) and clamp(20px,3vw,30px) to 24px and 20px on a phone.
const shrink = (v) => (v >= 40 ? 24 : v >= 28 ? 20 : v);
function cardPadding(el) {
  const p = el.elType === 'container' && el.isInner && !el.settings.padding_mobile ? el.settings.padding : null;
  if (p && [p.top, p.right, p.bottom, p.left].some((v) => Number(v) >= 28)) {
    el.settings.padding_mobile = { ...p, ...Object.fromEntries(['top', 'right', 'bottom', 'left'].map((k) => [k, String(shrink(Number(p[k])))])) };
  }
  (el.elements || []).forEach(cardPadding);
}
page.forEach(cardPadding);

// Chart rows (label | bar | value, sections 05 and 06): Elementor gives a child container 100% width on a phone unless
// it has its own mobile width, so the three cells stacked. They keep one row, as in the design.
const CHART_CELLS = { 31.3: 26, 56: 48, 8.7: 18 };
function chartRows(el) {
  const kids = el.elements || [];
  if (el.elType === 'container' && el.settings.flex_direction === 'row' && kids.length === 3 && kids.every((k) => k.elType === 'container' && CHART_CELLS[k.settings.width?.size])) {
    el.settings.flex_gap_mobile = { unit: 'px', size: 12, column: '12', row: '12' };
    // the bar fill inside the track cell has a percentage width of its own, which also falls back to 100% on a phone
    kids[1].elements?.[0]?.elements?.forEach((fill) => { if (fill.settings.width) fill.settings.width_mobile = fill.settings.width; });
    kids.forEach((k) => { k.settings.width_mobile = { unit: '%', size: CHART_CELLS[k.settings.width.size], sizes: [] }; });
  }
  kids.forEach(chartRows);
}
page.forEach(chartRows);

// The photo slots of sections 15 and 22 keep the design's 320px minimum height on a phone.
for (const id of ['eb1321e', '33f9cd5']) {
  const find = (nodes) => nodes.reduce((f, n) => f || (n.id === id ? n : find(n.elements || [])), null);
  const slot = find(page);
  if (slot) slot.settings.min_height_mobile = { unit: 'px', size: 320, sizes: [] };
}

page.forEach((sec, i) => {
  const widgets = widgetsOf(sec);
  const desktop = design['1440'][i];
  const { result } = matchTexts(desktop.texts, widgets);
  for (const [w, k] of result) {
    const s = w.settings;
    const d = desktop.texts[k];
    const buildSize = s.typography_font_size?.size ?? d.fs;
    const buildLh = s.typography_line_height?.size;
    for (const [device, vp] of Object.entries(VIEWPORTS)) {
      const t = design[vp][i].texts[k] ?? d;
      const set = (key, value) => { if (value === undefined) delete s[`${key}_${device}`]; else s[`${key}_${device}`] = value; };
      set('typography_font_size', Math.abs(t.fs - buildSize) > 0.4 ? { unit: 'px', size: round(t.fs, 1), sizes: [] } : undefined);
      const ratio = t.lh ? round(t.lh / t.fs) : null;
      set('typography_line_height', ratio && buildLh && Math.abs(ratio - buildLh) > 0.03 ? { unit: 'em', size: ratio, sizes: [] } : undefined);
      set('align', alignOf(t.ta) !== alignOf(d.ta) ? alignOf(t.ta) : undefined);
    }
  }
  const unmatched = widgets.filter((w) => !result.has(w) && strip(widgetText(w)).trim().length > 1);
  // Widgets the text match cannot reach (count-up numbers the design holds as 0, split stat spans, mixed-markup
  // paragraphs): take the size from the design nodes of this section that share the widget's desktop size, when they all
  // shrink to the same phone size.
  for (const w of widgets.filter((x) => !result.has(x) && strip(widgetText(x)).trim().length > 0)) {
    const size = w.settings.typography_font_size?.size;
    if (!size) continue;
    for (const [device, vp] of Object.entries(VIEWPORTS)) {
      const sizes = new Set(desktop.texts.map((t, k) => (Math.abs(t.fs - size) < 0.5 ? round(design[vp][i].texts[k].fs, 1) : null)).filter((v) => v !== null));
      const [only] = sizes;
      if (sizes.size === 1 && Math.abs(only - size) > 0.4) w.settings[`typography_font_size_${device}`] = { unit: 'px', size: only, sizes: [] };
    }
  }
  report.push(`section ${String(i).padStart(2, '0')} ${desktop.label}: ${result.size} matched, ${unmatched.length} unmatched`);
  for (const w of unmatched) report.push(`    unmatched ${w.id} ${w.widgetType}: ${strip(widgetText(w)).trim().slice(0, 70)}`);
});

writeFileSync(arg('out'), JSON.stringify(page));
if (arg('report')) writeFileSync(arg('report'), report.join('\n'));
console.log(report.filter((l) => !l.startsWith('    ')).join('\n'));
