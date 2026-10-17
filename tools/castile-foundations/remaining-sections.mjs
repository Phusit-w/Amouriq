// Ticket 12 record: brings the sections between the proof bridge and the footnotes (12 oils to soap, 13 why we made it,
// 14 not added, 16 evidence, 17 why trust, 18 customer voice, 20 how to use, 21 FAQ, 23 footnotes) to the Claude Design reference.
// Sections 15 and 22 (lifestyle photo slots) and the buying block (19, ticket 13) are not touched here.
//
// Input : current _elementor_data JSON of page 5309, as it stands after ticket 11
// Output: new _elementor_data JSON, written with write-page.php (raw $wpdb->update, never update_post_meta).
// Not idempotent (it moves/removes containers): run it once against the data as it stood after ticket 11.
//
// Usage: node tools/castile-foundations/remaining-sections.mjs --page page.json --out new.json
import { readFileSync, writeFileSync } from 'node:fs';
import { INK, BG, ACCENT, RULE, px, box, gap, pct, toolkit } from './elementor-helpers.mjs';

const arg = (n) => { const i = process.argv.indexOf(`--${n}`); return i > -1 ? process.argv[i + 1] : undefined; };
const page = JSON.parse(readFileSync(arg('page'), 'utf8'));
const { get, container, heading, type, widthOf } = toolkit(page);

const MUTED = '#3a322aa6';
const FAINT = '#3a322a8c';
const GREEN = '#4a5b32';
const m = (t, b) => box(t, 0, b, 0);
const bar = (w, pad) => Object.assign(w.settings, {
  _padding: box(0, 0, 0, pad), _border_border: 'solid', _border_width: box(0, 0, 0, 2), _border_color: ACCENT,
});
const card = (extra = {}) => ({
  background_color: BG, border_border: 'solid', border_width: box(1, 1, 1, 1), border_color: RULE, border_radius: box(8, 8, 8, 8), ...extra,
});

// ---- 12 oils to soap ------------------------------------------------------------------------------------------------------
{
  get('b041630').settings.flex_gap = gap(0);
  const h2 = get('449ac69');
  h2.settings.title = 'เริ่มจากน้ำมันที่คัดสรร แล้วตรวจสิ่งที่เรา<br>ทำออกมาจริง';
  h2.settings._margin = m(0, 48);
  get('ad33dba').settings._margin = m(0, 24);
  for (const [cardId, eyebrowId, bodyId] of [['22d7f94', '8c5dd04', '01c1287'], ['63e65e3', 'd2c76aa', '4af31cd'], ['da95193', '642e622', 'd5af40c'], ['c64ff4e', '434fce4', '4581fbe']]) {
    const c = get(cardId);
    Object.assign(c.settings, { flex_gap: gap(0), padding: box(30, 30, 30, 30), align_items: 'stretch' });
    if (cardId !== 'c64ff4e') Object.assign(c.settings, card());
    else Object.assign(c.settings, card({ background_color: '#f0f8f5' }));
    get(eyebrowId).settings._margin = m(0, 10);
    type(get(bodyId), { fs: 17.5, fw: 500, lh: 1.55, ls: 0, margin: null });
    if (bodyId === '01c1287') get(bodyId).settings.editor = get(bodyId).settings.editor.replace('Palm/Palm Kernel Oil', 'Red Palm / Palm Kernel Oil').replace('Lavender/Lavandin', 'Lavender / Lavandin');
  }
  for (const id of ['a1fc448', '87f9166', '4c44680']) {
    type(get(id), { fs: 18, fw: 400, lh: 1.3, ls: 0, color: ACCENT, margin: box(14, 0, 14, 27) });
  }
}

// ---- 13 why we made it (stat row) -------------------------------------------------------------------------------------------
{
  const sec = get('211ee5c');
  sec.settings.flex_gap = gap(0);
  get('6a94c3a').settings._margin = m(0, 22);
  const intro = get('785a7a7');
  intro.settings.editor = 'เราไม่ได้ต้องการสร้างสบู่ที่พยายามทำทุกอย่าง แต่ต้องการพัฒนา Castile Soap สำหรับการดูแลผิวโดยเฉพาะ';
  type(intro, { fs: 20, fw: 400, lh: 1.85, ls: 0, margin: m(0, 52) });
  bar(intro, 20);

  const row = get('8a1b37b');
  Object.assign(row.settings, { flex_gap: gap(32), justify_content: 'flex-start', align_items: 'stretch', margin: m(0, 52) });
  const cols = [['c57cdfc', '770fdca', ['3373487'], 'c323cd3'], ['70fa8c5', '3d3c568', ['8a78040'], 'a8dd37d'], ['9ebd12e', '20ce949', ['af3588e'], '514b916']];
  for (const [colId, valueRowId, strays, labelId] of cols) {
    const col = get(colId);
    Object.assign(col.settings, {
      width: pct(31.2), width_tablet: pct(100), width_mobile: pct(100), flex_gap: gap(0), align_items: 'flex-start', padding: box(14, 0, 0, 0),
      border_border: 'solid', border_width: box(1, 0, 0, 0), border_color: RULE,
    });
    col.elements = col.elements.filter((e) => !strays.includes(e.id));
    const valueRow = get(valueRowId);
    Object.assign(valueRow.settings, { flex_gap: gap(8), justify_content: 'flex-start', align_items: 'baseline' });
    for (const w of valueRow.elements) type(w, { fs: 30, fw: 700, lh: 1.55, ls: 0, color: GREEN, margin: null });
    const label = get(labelId);
    if (labelId === '514b916') label.settings.editor = 'Natural & Essential Oil Expertise';
    type(label, { fs: 12, fw: 400, lh: 1.55, ls: 1.2, tt: 'uppercase', color: FAINT, margin: box(6, 0, 0, 0) });
    label.settings.align = 'left';
  }
  type(get('4694159'), { fs: 18, fw: 400, lh: 1.85, ls: 0, color: MUTED, margin: null });
  widthOf(get('4694159'), 576);
}

// ---- 14 not added -----------------------------------------------------------------------------------------------------------
{
  const sec = get('1587d25');
  sec.settings.flex_gap = gap(0);
  get('17f999d').settings._margin = m(0, 42);
  const row = get('fbbdefb');
  Object.assign(row.settings, { flex_gap: gap(13), margin: m(0, 40) });
  const cards = [['f9b1a89', '4284729', 'b4ab332', 'eb31bae', 'f8537af'], ['6e1cf63', '4019ee3', '039a16e', 'a2a7c64', '7bbc001'],
    ['fced10f', '49b45e5', '8710423', '15db51f', '2933e39'], ['c5ce918', '8b2ab93', '80e2d12', 'da4db9a', '425e85b']]
    .map(([, cardId, iconId, titleId, descId]) => {
      const c = get(cardId);
      Object.assign(c.settings, card({ width: pct(24.025), width_tablet: pct(100), width_mobile: pct(100), padding: box(20, 20, 20, 20), flex_gap: gap(12) }));
      get(iconId).settings._margin = m(0, 0);
      type(get(titleId), { fs: 15.5, fw: 700, lh: 1.35, ls: 0, color: INK, margin: null });
      type(get(descId), { fs: 13, fw: 400, lh: 1.7, ls: 0, color: MUTED, margin: null });
      return c;
    });
  row.elements = cards;

  const p = get('28d026e');
  p.settings.editor = 'กลิ่นลาเวนเดอร์มาจากน้ำมันหอมระเหยสองตัวตามสูตรจดแจ้ง — Lavandula Angustifolia Oil ให้กลิ่นดอกลาเวนเดอร์ที่นุ่มและกลม และ Lavandula Hybrida Oil ให้ปลายกลิ่นสดแบบสมุนไพร ไม่มีน้ำหอมสังเคราะห์เพิ่ม';
  type(p, { fs: 17, fw: 400, lh: 1.85, ls: 0, color: MUTED, margin: null, free: true });
  p.settings.align = 'left';
  widthOf(p, 646);
  const q = get('03c590a');
  type(q, { fs: 20, fw: 400, lh: 1.85, ls: 0, margin: box(26, 0, 26, 0) });
  widthOf(q, 600);
  bar(q, 20);
  const note = get('a485bbb');
  type(note, { fs: 13, fw: 400, lh: 1.85, ls: 0, color: MUTED, margin: null });
  note.settings.editor = note.settings.editor.replace('หมายเหตุ:', '<strong style="font-weight:500;color:#3a322a">หมายเหตุ:</strong>');
}

// ---- 16 evidence (five cards in one wrapping row) --------------------------------------------------------------------------
{
  const sec = get('20c58a8');
  sec.settings.flex_gap = gap(0);
  get('c850e1f').settings._margin = m(0, 14);
  const h2 = get('733bd3e');
  h2.settings.title = 'สิ่งที่เราบอกว่าดี ควรมีสิ่งที่ให้คุณเชื่อถือได้';
  h2.settings._margin = m(0, 48);
  const row = get('19fdb9f');
  Object.assign(row.settings, { flex_wrap: 'wrap', flex_gap: gap(14) });
  const cards = [['b778f7e', 'a986c30', '7d3b504'], ['e094b47', '2712f15', 'f2361d1'], ['1035088', '29ddea3', '56b2927'], ['209b985', 'a36a67c', 'b44f7b3'], ['aa0a531', '2f54927', '01eac86']]
    .map(([cardId, titleId, descId], i) => {
      const c = get(cardId), title = get(titleId), desc = get(descId);
      Object.assign(c.settings, {
        width: pct(24.06), width_tablet: pct(48.5), width_mobile: pct(100), flex_gap: gap(8), padding: box(17.6, 17.6, 17.6, 17.6),
        background_color: '#ffffff14', border_border: 'solid', border_width: box(1, 1, 1, 1), border_color: '#fbf8f238', border_radius: box(8, 8, 8, 8),
      });
      type(title, { fs: 17, fw: 700, lh: 1.3, ls: 0, color: BG, margin: null });
      type(desc, { fs: 13, fw: 400, lh: 1.85, ls: 0, color: '#fbf8f2d1', margin: null });
      if (i === 4) desc.settings.editor = '<p style="margin:0 0 14px">เลขที่ใบรับแจ้ง 3 รายการ</p><p style="margin:0">10-1-6800022354<br>10-1-6800023739<br>10-1-6800023133</p>';
      c.elements = [heading(`0${i + 1}`, { fs: 11, fw: 400, lh: 1.55, ls: 0.5, color: '#cddcbb' }), title, desc];
      return c;
    });
  row.elements = cards;
  sec.elements = sec.elements.filter((e) => e.id !== '512d68f');
}

// ---- 17 why trust -----------------------------------------------------------------------------------------------------------
{
  get('36db640').settings.flex_gap = gap(0);
  get('d1bb356').settings._margin = m(0, 22);
  const intro = get('0c186c9');
  type(intro, { fs: 18, fw: 400, lh: 1.85, ls: 0, color: MUTED, margin: m(0, 28) });
  widthOf(intro, 640);
  const q = get('d0ebc85');
  type(q, { fs: 20, fw: 400, lh: 1.85, ls: 0, margin: null });
  widthOf(q, 640);
  bar(q, 20);
}

// ---- 18 customer voice -------------------------------------------------------------------------------------------------------
{
  const sec = get('1a1d964');
  sec.settings.flex_gap = gap(0);
  const h2 = get('9420a30');
  h2.settings.title = 'มาตรฐานบอกว่าเราเป็นอะไร เครื่องมือบอกว่าเกิดอะไรกับผิว<br>คนใช้จริงบอกว่า "รู้สึกอย่างไร"';
  h2.settings._margin = m(0, 48);
  const row = get('1ed8cd5');
  Object.assign(row.settings, { flex_gap: gap(14), margin: m(0, 24) });
  const attrs = ['รีวิวลูกค้าจริง Shopee · 30 mL Lavender · พฤษภาคม 2024',
    'รีวิวลูกค้าจริง Shopee · 30 mL Rosemary (สูตรเนื้อสบู่พื้นฐานเดียวกับ Lavender) · เมษายน 2026',
    'รีวิวลูกค้าจริง Shopee · Rose Geranium / Rosemary / Lavender · สิงหาคม 2024'];
  row.elements = [['9b7be7f', '19fd006', 'd5d7a21', 'fc2d34a'], ['0357056', 'acd4f9b', 'f632f26', '39a45ff'], ['127d1b3', '560edf7', '4da99f1', '3434202']]
    .map(([cardId, labelId, quoteId, attrId], i) => {
      const c = get(cardId), label = get(labelId), quote = get(quoteId), attr = get(attrId);
      Object.assign(c.settings, card({ width: pct(32.5), width_tablet: pct(100), width_mobile: pct(100), flex_gap: gap(10), padding: box(18, 18, 18, 18) }));
      type(label, { fs: 11, fw: 400, lh: 1.55, ls: 0.6, color: ACCENT, margin: null });
      quote.settings.editor = quote.settings.editor.replace('★★★★★ ', '');
      type(quote, { fs: 14, fw: 400, lh: 1.88, ls: 0, color: MUTED, margin: null });
      Object.assign(quote.settings, { _flex_size: 'custom', _flex_grow: 1 });
      attr.settings.editor = attrs[i];
      type(attr, { fs: 11, fw: 400, lh: 1.55, ls: 0, color: FAINT, margin: box(10, 0, 0, 0) });
      c.elements = [label, heading('★★★★★', { fs: 14, fw: 400, lh: 1.55, ls: 1, color: ACCENT }), quote, attr];
      return c;
    });
  const total = get('84cd86c'), note = get('74ea6d3');
  type(total, { fs: 22, fw: 700, lh: 1.5, ls: 0, margin: null });
  note.settings.editor = '(ตรวจสอบตัวเลขล่าสุดก่อนเผยแพร่จริง — คะแนน/จำนวนรีวิวเปลี่ยนทุกวัน)';
  type(note, { fs: 12.5, fw: 400, lh: 1.55, ls: 0, color: FAINT, margin: null });
  note.settings.align = 'left';
  const total2 = container({ flex_direction: 'row', flex_wrap: 'wrap', flex_align_items: 'baseline', flex_gap: gap(14) }, [total, note]);
  sec.elements = sec.elements.flatMap((e) => (e === total ? [total2] : e === note ? [] : [e]));
}

// ---- 20 how to use ----------------------------------------------------------------------------------------------------------
{
  get('aa8ffde').settings.flex_gap = gap(0);
  get('c38fcdf').settings._margin = m(0, 44);
  Object.assign(get('0de74cb').settings, { flex_gap: gap(32), align_items: 'stretch', margin: m(0, 36) });
  for (const [colId, iconId, titleId, descId] of [['508c4cf', 'ffbc31c', 'c347216', '7742f2a'], ['f701b9e', 'b26bac4', 'b425d2b', 'fbe8117'], ['28ed599', 'cf78551', '76074ed', 'eb03e10']]) {
    Object.assign(get(colId).settings, {
      width: pct(31.2), width_tablet: pct(100), width_mobile: pct(100), flex_gap: gap(0), align_items: 'flex-start', padding: box(14, 0, 0, 0),
      border_border: 'solid', border_width: box(1, 0, 0, 0), border_color: RULE,
    });
    get(iconId).settings._margin = m(0, 12);
    type(get(titleId), { fs: 21, fw: 700, lh: 1.55, ls: 0, margin: m(0, 6) });
    type(get(descId), { fs: 14.5, fw: 400, lh: 1.7, ls: 0, color: MUTED, margin: null });
  }
  type(get('c49dea4'), { fs: 12.5, fw: 400, lh: 1.85, ls: 0, color: FAINT, margin: null });
}

// ---- 21 FAQ (row padding and weight live in amq-castile.css) -----------------------------------------------------------------
{
  const sec = get('b6d9988');
  sec.settings.flex_gap = gap(0);
  get('1d85d6c').settings._margin = m(0, 40);
}

// ---- 23 footnotes ------------------------------------------------------------------------------------------------------------
{
  const sec = get('bf58e9b');
  sec.settings.flex_gap = gap(0);
  const rule = get('ec05fb5');
  const head = get('af103fc');
  head.settings.title = 'หมายเหตุและข้อมูลอ้างอิง';
  type(head, { fs: 14, fw: 500, lh: 1.55, ls: 0, color: MUTED, margin: m(0, 12) });
  head.settings.align = 'left';
  widthOf(head, 690);
  Object.assign(rule.settings, { width: px(690), margin: m(0, 26), flex_gap: gap(0), padding: box(0, 0, 0, 0) });
  for (const id of ['fd081dd', '2bc9b24', '7d115e2', 'ade5e48', '31dd285', '1fa6d53']) {
    const w = get(id);
    type(w, { fs: 12.5, fw: 400, lh: 1.5, ls: 0, color: MUTED, margin: m(0, 12) });
    widthOf(w, 690);
  }
  sec.elements = [head, rule, ...sec.elements.filter((e) => e !== head && e !== rule)];
}

// Elementor gives every nested container 10px padding by default; the design has none.
const zeroPad = (nodes) => nodes.forEach((n) => { if (n.elType === 'container' && n.isInner && !n.settings.padding) n.settings.padding = box(0, 0, 0, 0); zeroPad(n.elements || []); });
zeroPad(page.slice(12, 19));
zeroPad(page.slice(20));

writeFileSync(arg('out'), JSON.stringify(page));
console.log('ok');
