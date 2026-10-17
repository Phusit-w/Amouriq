// Ticket 14 record: sections 15 (the ritual) and 22 (final close) were skipped by the frame step (two-column grids with no
// content wrapper). They now follow the design: full-bleed halves (photo slot | coloured text panel), the panel padding and
// type from the design, section 15's REAL SOAP / MOISTURE CARE pair as two columns, section 22's CTA as a real button
// linking to #buy. The photo slots stay placeholders by decision (real photography is still pending).
//
// Input : current _elementor_data JSON of page 5309; Output: new _elementor_data JSON, written with write-page.php.
// Not idempotent (it removes/replaces widgets): run once against the data as it stood after ticket 13.
//
// Usage: node tools/castile-foundations/lifestyle-sections.mjs --page page.json --out new.json
import { readFileSync, writeFileSync } from 'node:fs';
import { INK, BG, ACCENT, px, box, gap, pct, toolkit } from './elementor-helpers.mjs';

const arg = (n) => { const i = process.argv.indexOf(`--${n}`); return i > -1 ? process.argv[i + 1] : undefined; };
const page = JSON.parse(readFileSync(arg('page'), 'utf8'));
const { get, find, newId, container, heading, type } = toolkit(page);

const PLACEHOLDER_BG = '#f0ede9';
const m = (t, b) => box(t, 0, b, 0);

// Section frame shared by 15 and 22: no padding, halves side by side (stacked below desktop), text panel centred vertically.
function twoHalves({ outer, row, photo, placeholder, panel, panelBg, minHeight }) {
  const o = get(outer).settings;
  o.content_width = 'full';
  o.padding = box(0, 0, 0, 0);
  o.flex_gap = gap(0);
  o.align_items = 'stretch';
  delete o.background_background; delete o.background_color;
  Object.assign(get(row).settings, { flex_gap: gap(0), align_items: 'stretch', padding: box(0, 0, 0, 0) });

  Object.assign(get(photo).settings, {
    width: pct(50), width_tablet: pct(100), width_mobile: pct(100),
    background_background: 'classic', background_color: PLACEHOLDER_BG,
    padding: box(0, 0, 0, 0), border_radius: box(0, 0, 0, 0), min_height: px(minHeight), min_height_mobile: px(260),
    flex_justify_content: "center", flex_align_items: "center",
  });
  type(get(placeholder), { fs: 13, fw: 400, lh: 1.6, ls: 0, color: '#3a322a99' });

  const p = get(panel).settings;
  Object.assign(p, {
    width: pct(50), width_tablet: pct(100), width_mobile: pct(100),
    background_background: 'classic', background_color: panelBg,
    flex_justify_content: 'center', flex_align_items: 'flex-start', flex_gap: gap(0),
    padding: box(64, 64, 64, 64), padding_mobile: box(28, 28, 28, 28),
  });
}

// ---- 15 the ritual ----------------------------------------------------------------------------------------------------------
{
  twoHalves({ outer: 'f6819a3', row: 'f9d4d3f', photo: 'eb1321e', placeholder: '9a1b53b', panel: 'faf608c', panelBg: '#4a5b32', minHeight: 475 });
  type(get('91660c3'), { fs: 40, fw: 600, lh: 1.25, ls: 0, margin: m(0, 22) });

  const col = (title, note, w) => container({ flex_direction: 'column', flex_gap: gap(0), padding: box(0, 0, 0, 0), width: px(w), width_mobile: pct(100) }, [
    heading(title, { tag: 'div', fs: 15, fw: 700, lh: 1.5, color: BG }),
    heading(note, { tag: 'div', fs: 13.5, fw: 400, lh: 1.5, color: '#fbf8f2d9' }),
  ]);
  const pair = container({ flex_direction: 'row', flex_gap: gap(32, 14), flex_wrap: 'wrap', padding: box(0, 0, 0, 0), margin: m(0, 22) }, [
    col('REAL SOAP', 'สูตรเพื่อผิวโดยเฉพาะ', 120),
    col('MOISTURE CARE', 'ที่คิดถึงสิ่งที่เกิดขึ้นหลังล้าง', 280),
  ]);
  const panel = get('faf608c');
  const i = panel.elements.findIndex((e) => e.id === '7ca4af6');
  panel.elements.splice(i, 1, pair);
  type(get('3652a66'), { fs: 16, fw: 400, lh: 1.6, ls: 0, color: '#fbf8f2e6', margin: null });
}

// ---- 22 final close ---------------------------------------------------------------------------------------------------------
{
  twoHalves({ outer: '4d024d9', row: 'e5672e7', photo: '33f9cd5', placeholder: 'e0a81f9', panel: '8a14590', panelBg: INK, minHeight: 560 });
  const text = '#fbf8f2e6';
  const h2 = get('b4566cc');
  h2.settings.title = 'เพราะการอาบน้ำที่ดี<br>ไม่ควรจบแค่คำว่า <span style="white-space:nowrap">“ผิวสะอาด”</span>';
  type(h2, { fs: 48, fw: 600, lh: 1.25, ls: 0, margin: m(0, 24) });
  type(get('78e66f4'), { fs: 17, fw: 400, lh: 1.7, ls: 0, color: text, margin: m(0, 8) });
  type(get('17b291b'), { fs: 17, fw: 400, lh: 1.7, ls: 0, color: text, margin: m(0, 8) });
  type(get('97e18ec'), { fs: 17, fw: 400, lh: 1.7, ls: 0, color: text, margin: m(0, 26) });
  type(get('a5eb2d8'), { fs: 26, fw: 600, lh: 1.3, ls: 0, margin: m(0, 20) });
  type(get('6525b8f'), { fs: 13.5, fw: 500, lh: 1.6, ls: 0, color: '#fbf8f2d9', margin: m(28, 0) });
  type(get('1195072'), { fs: 13.5, fw: 400, lh: 1.6, ls: 0, color: '#fbf8f2d9', margin: null });

  // CTA: clone the hero button (same orange button style), swap text and anchor.
  const hero = JSON.parse(JSON.stringify(findButton(page, 'เลือกขนาดและสั่งซื้อ')));
  hero.id = newId();
  hero.settings.text = 'เลือกการดูแลที่คิดถึงผิวหลังล้าง';
  hero.settings.link = { url: '#buy', is_external: '', nofollow: '', custom_attributes: '' };
  hero.settings._margin = m(0, 0);
  const panel = get('8a14590');
  const i = panel.elements.findIndex((e) => e.id === 'd9838af');
  panel.elements.splice(i, 1, hero);
}

function findButton(nodes, text) {
  for (const n of nodes) {
    if (n.widgetType === 'button' && n.settings.text === text) return n;
    const f = findButton(n.elements || [], text);
    if (f) return f;
  }
  return null;
}

writeFileSync(arg('out'), JSON.stringify(page));
console.log('written', arg('out'));
