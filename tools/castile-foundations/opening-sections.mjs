// Ticket 10 record: rebuilds the structure and spacing of the opening sections (00 trust bar, 01 hero, 02 "หลังล้าง",
// 03 Real Soap definition) of the CASTILE page's Elementor data to match the Claude Design reference.
//
// Input : current _elementor_data JSON of page 5309 (wp post meta get 5309 _elementor_data)
// Output: new _elementor_data JSON. Written to the database separately with write-page.php (raw $wpdb->update,
//         never update_post_meta — see README and ADR-0004); this script never touches the database.
// Run once against the data as it stood after ticket 09 (it adds containers, so it is not idempotent).
//
// Usage: node tools/castile-foundations/opening-sections.mjs --page page.json --out new.json
import { readFileSync, writeFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';

const arg = (n) => { const i = process.argv.indexOf(`--${n}`); return i > -1 ? process.argv[i + 1] : undefined; };
const page = JSON.parse(readFileSync(arg('page'), 'utf8'));

const FONT = 'DM Sans';
const INK = '#3a322a';
const BG = '#fbf8f2';
const SURFACE = '#f5efe4';
const ACCENT = '#c1673f';
const RULE = '#3a322a29'; // ink at 16%
const px = (size) => ({ unit: 'px', size, sizes: [] });
const em = (size) => ({ unit: 'em', size, sizes: [] });
const box = (t, r, b, l) => ({ unit: 'px', top: String(t), right: String(r), bottom: String(b), left: String(l), isLinked: false });
const gap = (n, row = n) => ({ unit: 'px', size: n, column: String(n), row: String(row) });

const used = new Set();
const collect = (nodes) => nodes.forEach((n) => { used.add(n.id); collect(n.elements || []); });
collect(page);
const find = (id, nodes = page) => {
  for (const n of nodes) {
    if (n.id === id) return n; const f = find(id, n.elements || []); if (f) return f; }
  return null;
};
const get = (id) => { const e = find(id); if (!e) throw new Error(`element ${id} not found`); return e; };
const newId = () => { let id; do { id = randomBytes(4).toString('hex').slice(0, 7); } while (used.has(id)); used.add(id); return id; };

const container = (settings, elements) => ({ id: newId(), elType: 'container', isInner: true, settings: { content_width: 'full', ...settings }, elements });
const heading = (title, o) => {
  const s = {
    title, header_size: o.tag || 'span',
    typography_typography: 'custom', typography_font_family: FONT, typography_font_weight: String(o.fw),
    typography_font_size: px(o.fs), typography_line_height: em(o.lh ?? 1.55), typography_letter_spacing: px(o.ls ?? 0),
    typography_text_transform: o.tt ?? '', title_color: o.color, align: 'left',
  };
  if (o.margin) s._margin = o.margin;
  if (o.bg) { s._background_background = 'classic'; s._background_color = o.bg; s._padding = o.padding; s._border_radius = o.radius; }
  return { id: newId(), elType: 'widget', isInner: false, settings: s, elements: [], widgetType: 'heading' };
};

// Typography on an existing heading / text-editor widget.
function type(w, { fs, fw, lh, ls, tt, color, margin, free }) {
  const s = w.settings;
  s.typography_typography = 'custom';
  s.typography_font_family = FONT;
  if (fw !== undefined) s.typography_font_weight = String(fw);
  if (fs !== undefined) s.typography_font_size = px(fs);
  if (lh !== undefined) s.typography_line_height = em(lh);
  if (ls !== undefined) s.typography_letter_spacing = px(ls);
  if (tt !== undefined) s.typography_text_transform = tt;
  if (color) { if (w.widgetType === 'heading') s.title_color = color; else s.text_color = color; }
  if (margin) s._margin = margin; else if (margin === null) delete s._margin;
  if (free) { delete s._element_width; delete s._element_custom_width; delete s._flex_align_self; }
}

// The hero button is the template for every button widget on the page.
const buttonTemplate = JSON.parse(JSON.stringify(get('efa0a8a')));
const button = (text, href, s) => {
  const b = JSON.parse(JSON.stringify(buttonTemplate));
  b.id = newId();
  b.settings = { ...b.settings, text, link: { url: href, is_external: '', nofollow: '', custom_attributes: '' },
    typography_typography: 'custom', typography_font_family: FONT, align: 'left', ...s };
  return b;
};

// ---- 00 trust bar ---------------------------------------------------------------------------------------------------
{
  const sec = get('8c9d4fb');
  Object.assign(sec.settings, {
    flex_gap: gap(0),
    border_border: 'solid', border_width: box(0, 0, 1, 0), border_color: RULE,
  });
  const old = get('d14fde3');
  const items = [
    ['100% REAL SOAP<sup><a href="#fn-dagger">†</a></sup>', 500],
    ['DermX Tested', 400], ['Thai FDA Notified', 400], ['Thoughtfully Made for Skin', 400],
  ];
  const row = container({ flex_direction: 'row', flex_justify_content: 'center', flex_wrap: 'wrap', flex_gap: gap(22, 6) },
    items.map(([t, fw]) => heading(t, { fs: 11.5, fw, ls: 0.69, tt: 'uppercase', color: '#3a322ac7' })));
  sec.elements = sec.elements.map((e) => (e === old ? row : e));
}

// ---- 01 hero ----------------------------------------------------------------------------------------------------------
{
  get('e03a679').settings.flex_gap = gap(0);
  Object.assign(get('105f729').settings, { flex_align_items: 'center', flex_gap: gap(56) });
  Object.assign(get('0e0ea3c').settings, {
    background_background: 'classic', background_color: SURFACE, border_radius: box(8, 8, 8, 8), overflow: 'hidden', flex_gap: gap(0),
  });
  Object.assign(get('2b94273').settings, {
    width: { unit: '%', size: 100, sizes: [] },
    height: px(665), height_tablet: px(520), height_mobile: px(380),
    'object-fit': 'cover',
  });
  get('8e217f6').settings.flex_gap = gap(0);
  get('b0a0b16').settings.flex_gap = gap(0);

  const m = (t, b) => box(t, 0, b, 0);
  type(get('46d6b39'), { margin: m(0, 14) });
  // badge + definition text replace the single "100% REAL SOAP — ..." line
  const oldBadge = get('af745c0');
  const badge = container({ flex_direction: 'row', flex_align_items: 'center', flex_gap: gap(10), margin: m(0, 18) }, [
    heading('100% REAL SOAP<sup><a href="#fn-dagger">†</a></sup>', { fs: 11, fw: 500, ls: 0.22, color: '#4a5639', bg: '#f3f8ed', padding: box(3, 10, 3, 10), radius: box(4, 4, 4, 4) }),
    heading('สบู่เหลวแท้ ตามนิยาม มอก. เอส 14-2562', { fs: 12.5, fw: 400, color: INK }),
  ]);
  const col = get('b0a0b16');
  col.elements = col.elements.map((e) => (e === oldBadge ? badge : e));

  type(get('8565c5d'), { free: true, margin: m(0, 18) });
  type(get('c79f12c'), { free: true, margin: m(0, 26) });

  get('362c8a5').settings.flex_gap = gap(12);
  get('362c8a5').settings.margin = m(0, 28);
  const cards = [
    { card: 'a6331ae', label: 'f3dfe2b', value: 'f9b9b55', desc: 'e0ca8e4' },
    { card: '342c371', label: '8d2154f', value: '5fac264', desc: '1cd360a' },
    { card: '1acbcb2', label: 'b4eba24', value: null, desc: 'd306282' },
  ];
  for (const c of cards) {
    Object.assign(get(c.card).settings, {
      background_color: SURFACE, border_border: 'solid', border_width: box(1, 1, 1, 1), border_color: RULE,
      border_radius: box(8, 8, 8, 8), padding: box(13.2, 13.2, 13.2, 13.2), flex_gap: gap(6), _flex_size: 'grow',
    });
    delete get(c.label).settings._margin;
    if (c.value) type(get(c.value), { fs: 28, fw: 700, lh: 1.55, ls: 0, margin: null });
    type(get(c.desc), { fs: 12.5, fw: c.value ? 400 : 500, lh: 1.55, ls: 0, color: INK, margin: c.value ? null : m(4, 0) });
  }
  const d2 = get('1cd360a');
  d2.settings.editor = 'ค่าการสูญเสียน้ำจากผิว (TEWL) หลังใช้ 8 ชั่วโมง<sup><a href="#fn-dblstar">**</a></sup>';
  d2.settings.typography_font_weight = '400';
  get('d306282').settings.editor = 'ผลการทดสอบสรุปว่า "<strong style="font-weight:500">ไม่พบ" ผลทำลายเกราะป้องกันผิว</strong>';
  get('d306282').settings.typography_font_weight = '400';

  get('76ee250').settings.margin = m(0, 0);
  Object.assign(get('efa0a8a').settings, {
    typography_font_weight: '700', typography_font_size: px(15.5),
    border_radius: box(4, 4, 4, 4), text_padding: box(14, 26, 14, 26),
    button_text_color: BG,
  });
}

// ---- 02 "หลังล้าง" ----------------------------------------------------------------------------------------------------
{
  get('b76cbfb').settings.flex_gap = gap(0);
  const h2 = get('178d758');
  h2.settings.title = '"หลังล้าง" สิ่งที่เกิดขึ้นกับผิว<br>สำคัญไม่แพ้ความสะอาด';
  h2.settings._margin = box(0, 0, 48, 0);
  const row = get('4d779f0');
  Object.assign(row.settings, {
    flex_gap: gap(40), padding: box(36, 0, 0, 0),
    border_border: 'solid', border_width: box(1, 0, 0, 0), border_color: RULE,
  });
  for (const [colId, svgId, headId, descId] of [['a939f8a', '293c202', '7997bff', '3f3e6ce'], ['c136268', '621e557', '31d99a1', '98ce7e0'], ['c1d4f19', '9516538', '5d049ae', 'adf2dd0']]) {
    const col = get(colId);
    col.settings.flex_gap = gap(0);
    const svg = get(svgId), head = get(headId), desc = get(descId);
    delete svg.settings._margin;
    delete head.settings._margin;
    const head2 = container({ flex_direction: 'row', flex_align_items: 'center', flex_gap: gap(9), margin: box(0, 0, 8, 0) }, [svg, head]);
    col.elements = [head2, desc];
  }
  const q = get('c1161a9');
  q.settings.editor = 'เพราะผลิตภัณฑ์ทำความสะอาดที่ใช้ทุกวัน ไม่ควรจบหน้าที่เพียงตอนที่ล้างออก';
  Object.assign(q.settings, {
    _margin: box(44, 0, 0, 0), _padding: box(0, 0, 0, 18),
    _border_border: 'solid', _border_width: box(0, 0, 0, 2), _border_color: ACCENT,
  });
}

// ---- 03 Real Soap definition (dark) ------------------------------------------------------------------------------------
{
  const sec = get('6858469');
  sec.settings.flex_gap = gap(0);
  type(get('e2f9edb'), { margin: box(0, 0, 16, 0) });
  const h2 = get('f60a040');
  h2.settings.title = 'สิ่งที่เรียกว่า “สบู่เหลว” ไม่ได้เป็น "สบู่เหลวแท้"<br>เหมือนกันทั้งหมด';
  h2.settings._margin = box(0, 0, 22, 0);
  const b = (t) => `<strong style="font-weight:500">${t}</strong>`;
  const p1 = get('6600a1d');
  p1.settings.editor = `มาตรฐานอุตสาหกรรม ${b('มอก. เอส 14-2562')} แยกคำว่า ${b('“สบู่เหลวแท้”')} และ ${b('“สบู่เหลวผสม”')} ออกจากกันอย่างชัดเจน — สบู่เหลวแท้มีเกลือของกรดไขมันจากน้ำมันหรือไขมันเป็นองค์ประกอบสำคัญ ส่วนสบู่เหลวผสมมีสารลดแรงตึงผิวสังเคราะห์ร่วมด้วย (ข้อ 3.3.1: สบู่เหลวแท้ต้องไม่พบสารลดแรงตึงผิวสังเคราะห์)`;
  type(p1, { fs: 16.5, fw: 400, lh: 1.85, ls: 0, tt: '', color: BG, margin: box(0, 0, 0, 0) });
  Object.assign(p1.settings, { _element_width: 'initial', _element_custom_width: px(660), _flex_align_self: 'flex-start', align: 'left' });
  const rule = container({ min_height: px(1), background_background: 'classic', background_color: '#fbf8f23d', margin: box(52, 0, 52, 0), flex_gap: gap(0) }, []);
  const h3 = get('bc6b40b');
  h3.settings._margin = box(0, 0, 16, 0);
  const p2 = get('23ec737');
  p2.settings._margin = box(0, 0, 13.2, 0);
  p2.settings.editor = p2.settings.editor.replace(/"สบู่เหลวแท้"/, '“สบู่เหลวแท้”');
  const oldBtn = get('6dffece');
  const btn = button('ดูนิยามที่เราอ้างอิง', '#evidence', {
    background_color: '#00000000', button_text_color: BG, hover_color: BG, button_background_hover_color: '#fbf8f21a',
    border_border: 'solid', border_width: box(1, 1, 1, 1), border_color: '#fbf8f273', button_hover_border_color: '#fbf8f2',
    border_radius: box(4, 4, 4, 4), text_padding: box(12, 20, 12, 20),
    typography_font_weight: '700', typography_font_size: px(14), _margin: box(26, 0, 0, 0),
  });
  sec.elements = sec.elements.flatMap((e) => (e.id === 'bc6b40b' ? [rule, e] : e === oldBtn ? [btn] : [e]));
}

// Elementor gives every nested container 10px padding by default; the design has none, so zero it wherever the
// section does not set its own.
const zeroPad = (nodes) => nodes.forEach((n) => { if (n.elType === 'container' && n.isInner && !n.settings.padding) n.settings.padding = box(0, 0, 0, 0); zeroPad(n.elements || []); });
zeroPad(page.slice(0, 4));

writeFileSync(arg('out'), JSON.stringify(page));
console.log('ok');
