// Ticket 09 record: derives the page-wide frame (content width, section padding, alignment, typography) of the
// CASTILE page's Elementor data from measurements of the Claude Design reference.
//
// Input : the current _elementor_data JSON of page 5309 (wp post meta get 5309 _elementor_data)
//         + measure.mjs output for the design (--target design, widths 1440,768,390)
// Output: new _elementor_data JSON + a report of what was and wasn't matched.
// The JSON is written to the database separately (raw $wpdb->update, never update_post_meta — see the memory
// note and ADR-0004); this script never touches the database.
//
// Usage: node tools/castile-foundations/foundations.mjs --page page.json --design design-measure.json --out new.json [--report report.txt]
import { readFileSync, writeFileSync } from 'node:fs';

const arg = (n) => { const i = process.argv.indexOf(`--${n}`); return i > -1 ? process.argv[i + 1] : undefined; };
const page = JSON.parse(readFileSync(arg('page'), 'utf8'));
const design = JSON.parse(readFileSync(arg('design'), 'utf8'));
const VP = { desktop: '1440', tablet: '768', mobile: '390' };
const FONT = 'DM Sans';
// Sections whose own element is a two-column grid with no content wrapper (design sections 15 and 22): left to ticket 12.
const SKIP_FRAME = new Set([15, 22]);

const strip = (h) => String(h ?? '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;|&amp;|&#?\w+;/g, ' ');
const norm = (h) => strip(h).toLowerCase().replace(/[^\p{L}\p{M}]+/gu, '');
const px = (size) => ({ unit: 'px', size, sizes: [] });
const round = (n, d = 2) => Math.round(n * 10 ** d) / 10 ** d;
const report = [];
const log = (m) => report.push(m);

// --- text matching -------------------------------------------------------------------------------------------------
function matchWidgets(sectionIdx, widgets) {
  const per = {};
  for (const [name, vp] of Object.entries(VP)) per[name] = design[vp][sectionIdx];
  const cands = per.desktop.texts.map((t, k) => ({ k, key: norm(t.text), used: false }));
  const result = new Map();
  for (const w of widgets) {
    const key = norm(w.widgetType === 'heading' ? w.settings.title : w.settings.editor);
    if (key.length < 2) continue;
    let best = null;
    for (const c of cands) {
      if (c.used || c.key.length < 2) continue;
      let score = -1;
      if (c.key === key) score = 3;
      else if (c.key.startsWith(key.slice(0, 24)) && key.length >= 8) score = 2;
      else if (key.startsWith(c.key.slice(0, 24)) && c.key.length >= 8) score = 2;
      else if (c.key.includes(key) && key.length >= 6) score = 1;
      if (score < 0) continue;
      const diff = Math.abs(c.key.length - key.length);
      if (!best || score > best.score || (score === best.score && diff < best.diff)) best = { c, score, diff };
    }
    if (best) { best.c.used = true; result.set(w, best.c.k); }
  }
  return { result, per, unusedDesign: cands.filter((c) => !c.used).length };
}

// --- apply ---------------------------------------------------------------------------------------------------------
const alignOf = (ta) => (ta === 'center' ? 'center' : ta === 'right' || ta === 'end' ? 'right' : 'left');

function styleWidget(w, secIdx, per, k, wrapLeft) {
  const s = w.settings;
  const t = Object.fromEntries(Object.entries(VP).map(([n]) => [n, per[n].texts[k] ?? per.desktop.texts[k]]));
  const d = t.desktop;
  s.typography_typography = 'custom';
  s.typography_font_family = FONT;
  s.typography_font_weight = String(d.fw);
  s.typography_font_size = px(round(d.fs, 1));
  for (const n of ['tablet', 'mobile']) {
    if (Math.abs(t[n].fs - d.fs) > 0.4) s[`typography_font_size_${n}`] = px(round(t[n].fs, 1));
    else delete s[`typography_font_size_${n}`];
  }
  if (d.lh) s.typography_line_height = { unit: 'em', size: round(d.lh / d.fs, 2), sizes: [] };
  s.typography_letter_spacing = { unit: 'px', size: round(d.ls, 2), sizes: [] };
  s.typography_text_transform = d.tt === 'none' ? '' : d.tt;
  s.align = alignOf(d.ta);
  // text colour as the design resolves it (opaque colours only; opacity-dimmed text keeps its widget colour)
  if (d.color) {
    if (w.widgetType === 'heading') s.title_color = d.color; else s.text_color = d.color;
  }
  // Only elements with an explicit max-width get a fixed width and a position in the column; everything else keeps
  // the container's stretch so it fills the column like block text does in the design.
  const wrapW = per.desktop.wrapMax;
  if (d.maxw && wrapW && d.maxw < wrapW - 1) {
    s._element_width = 'initial';
    s._element_custom_width = { unit: 'px', size: Math.round(d.maxw), sizes: [] };
    s._flex_align_self = d.x - wrapLeft > 4 && alignOf(d.ta) === 'center' ? 'center' : 'flex-start';
  } else {
    delete s._element_width; delete s._element_custom_width; delete s._flex_align_self;
  }
}

function walkWidgets(el, out = []) {
  if (el.elType === 'widget' && (el.widgetType === 'heading' || el.widgetType === 'text-editor')) out.push(el);
  (el.elements || []).forEach((c) => walkWidgets(c, out));
  return out;
}

page.forEach((sec, i) => {
  const dm = design[VP.desktop][i];
  const widgets = walkWidgets(sec);
  const { result, per, unusedDesign } = matchWidgets(i, widgets);
  const wrapLeft = Math.max(dm.pad[3], (1440 - (dm.wrapMax || 1120)) / 2);

  if (!SKIP_FRAME.has(i) && dm.wrapMax) {
    const s = sec.settings;
    s.content_width = 'boxed';
    s.boxed_width = { unit: 'px', size: dm.wrapMax, sizes: [] };
    s.flex_direction = 'column';
    s.align_items = 'stretch';
    const pad = (n) => { const p = per[n].pad; return { unit: 'px', top: String(round(p[0], 1)), right: String(round(p[1], 1)), bottom: String(round(p[2], 1)), left: String(round(p[3], 1)), isLinked: false }; };
    s.padding = pad('desktop');
    s.padding_tablet = pad('tablet');
    s.padding_mobile = pad('mobile');
  }
  for (const [w, k] of result) styleWidget(w, i, per, k, wrapLeft);
  const withText = widgets.filter((w) => norm(w.widgetType === 'heading' ? w.settings.title : w.settings.editor).length >= 2);
  const unmatched = withText.filter((w) => !result.has(w));
  log(`section ${String(i).padStart(2, '0')} ${dm.label}: ${result.size}/${withText.length} text widgets matched, ${unmatched.length} unmatched, ${unusedDesign} design text nodes unused${SKIP_FRAME.has(i) ? ' (frame skipped: grid section)' : ''}`);
  for (const w of unmatched) log(`    unmatched ${w.id} ${w.widgetType}: ${strip(w.widgetType === 'heading' ? w.settings.title : w.settings.editor).trim().slice(0, 70)}`);
});

writeFileSync(arg('out'), JSON.stringify(page));
if (arg('report')) writeFileSync(arg('report'), report.join('\n'));
console.log(report.filter((l) => !l.startsWith('    ')).join('\n'));
