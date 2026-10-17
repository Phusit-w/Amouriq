// Ticket 11 record: rebuilds the chart rows, numbered eyebrows, evidence-note card and key-takeaway blocks of the
// CASTILE proof sections (05 moisture, 06 water loss, 07 barrier) to match the Claude Design reference.
//
// Input : current _elementor_data JSON of page 5309, as it stands after ticket 10
// Output: new _elementor_data JSON, written with write-page.php (raw $wpdb->update, never update_post_meta).
// Not idempotent (it adds a container): run it once against the data as it stood after ticket 10.
//
// Usage: node tools/castile-foundations/proof-sections.mjs --page page.json --out new.json
import { readFileSync, writeFileSync } from 'node:fs';
import { INK, BG, ACCENT, RULE, px, box, gap, pct, toolkit } from './elementor-helpers.mjs';

const arg = (n) => { const i = process.argv.indexOf(`--${n}`); return i > -1 ? process.argv[i + 1] : undefined; };
const page = JSON.parse(readFileSync(arg('page'), 'utf8'));
const { get, container, heading, type, widthOf } = toolkit(page);

const MUTED = '#3a322aa6';
const PALE = '#c4dcd4';
const HI = '#4f6d63';
const m = (t, b) => box(t, 0, b, 0);

// The design's table-style chart: header line, then one hairline-divided row per period with a label, a thin bar
// on a track, and a large right-aligned value. `rows` lists, per row, the element ids (label widget, track, fill,
// value widget, the row container) and the bar length as a percentage of the track.
function chart({ header, list, track, fills }) {
  const head = get(header);
  head.settings.editor = head.settings.editor
    .replace(/padding-bottom:8px/, `padding-bottom:8px;border-bottom:1px solid rgba(58,50,42,.16)`);
  delete head.settings._margin;

  const rowsBox = get(list);
  Object.assign(rowsBox.settings, { flex_gap: gap(0), margin: m(0, 0) });
  rowsBox.elements.forEach((row, i) => {
    const [labelCol, barCol, valueCol] = row.elements;
    Object.assign(row.settings, {
      flex_gap: gap(20), flex_align_items: 'center', padding: box(14, 0, 14, 0),
      border_border: 'solid', border_width: box(0, 0, 1, 0), border_color: RULE,
    });
    // Elementor only honours a container's Width control when its content width is "full".
    labelCol.settings.content_width = barCol.settings.content_width = valueCol.settings.content_width = 'full';
    labelCol.settings.width = pct(31.3);
    barCol.settings.width = pct(56);
    valueCol.settings.width = pct(8.7);
    valueCol.settings.flex_align_items = 'flex-end';
    for (const c of [labelCol, barCol, valueCol]) c.settings.padding = box(0, 0, 0, 0);

    const label = labelCol.elements[0];
    type(label, { fs: 13.5, fw: 400, lh: 1.55, ls: 0, color: INK, margin: null });

    const trackEl = barCol.elements[0];
    const fill = trackEl.elements[0];
    const f = fills[i];
    Object.assign(trackEl.settings, { content_width: 'full', background_color: track, border_radius: box(2, 2, 2, 2), min_height: px(10), padding: box(0, 0, 0, 0) });
    Object.assign(fill.settings, {
      content_width: 'full', width: pct(f.pct), background_color: f.hi ? HI : PALE, border_radius: box(2, 2, 2, 2), min_height: px(10), padding: box(0, 0, 0, 0),
    });

    const value = valueCol.elements[0];
    type(value, { fs: 22, fw: 700, lh: 1.55, ls: 0, color: f.hi ? HI : INK, margin: null });
    value.settings.align = 'right';
  });
}

// ---- 05 moisture ------------------------------------------------------------------------------------------------------
{
  get('3e22e94').settings.flex_gap = gap(0);
  const eyebrow = get('5c08b70');
  eyebrow.settings.title = 'SKIN RESULT 01 — MOISTURE ↑';
  eyebrow.settings._margin = m(0, 14);
  const h2 = get('1922e0e');
  h2.settings.title = 'ผิวชุ่มชื้น “เพิ่มขึ้น” — วัดด้วยเครื่อง Corneometer<sup>®</sup>';
  h2.settings._margin = m(0, 21);
  chart({
    header: 'af705ce', list: '33ee6c1', track: '#f0f8f5',
    fills: [{ pct: 55 }, { pct: 63 }, { pct: 100, hi: true }, { pct: 69 }],
  });
  const h3 = get('cb54ad0');
  h3.settings._margin = box(44, 0, 14, 0);
  const p1 = get('3edd4bb');
  type(p1, { fs: 15, lh: 1.85, margin: m(0, 13.2) });
  const p2 = get('f32d4ba');
  type(p2, { fs: 13.5, lh: 1.85, color: MUTED, margin: m(10, 13.2) });
  const quote = get('db5ebff');
  quote.settings.editor = 'สบู่ที่ดีสำหรับ AMOURIQ จึงไม่ควรจบหน้าที่แค่ตอนที่ล้างออก';
  type(quote, { fs: 17, lh: 1.85, color: MUTED, margin: box(26, 0, 0, 0) });
  Object.assign(quote.settings, {
    _padding: box(0, 0, 0, 18), _border_border: 'solid', _border_width: box(0, 0, 0, 2), _border_color: ACCENT,
  });
}

// ---- 06 water loss ----------------------------------------------------------------------------------------------------
{
  get('f3bc824').settings.flex_gap = gap(0);
  const eyebrow = get('dc3e2f1');
  eyebrow.settings.title = 'SKIN RESULT 02 — WATER LOSS ↓';
  eyebrow.settings._margin = m(0, 14);
  const h2 = get('a10e2a5');
  h2.settings.title = 'ผิวสูญเสียน้ำน้อยลง (TEWL) — วัดด้วย<br>เครื่อง Tewameter<sup>®</sup>';
  h2.settings._margin = m(0, 21);
  chart({
    header: '213a6d2', list: 'e484aa4', track: '#3a322a14',
    fills: [{ pct: 8 }, { pct: 50 }, { pct: 34 }, { pct: 100, hi: true }],
  });
  const kicker = get('38a1a5e');
  type(kicker, { fs: 11.5, fw: 500, lh: 1.55, ls: 1.61, tt: 'uppercase', color: ACCENT, margin: box(44, 0, 12, 0) });
  const h3 = get('a594814');
  h3.settings.title = 'หลัง 8 ชั่วโมง การสูญเสียน้ำจากผิว (TEWL)<br>ต่ำกว่าก่อนใช้ 10.67%';
  type(h3, { fs: 34, fw: 700, lh: 1.5, ls: 0, color: HI, margin: m(0, 20) });
  widthOf(h3, 816);

  const oldNote = get('95f42f0');
  const noteText = JSON.parse(JSON.stringify(oldNote));
  noteText.settings.editor = 'ค่า TEWL หลัง 1, 4 และ 8 ชั่วโมงต่ำกว่าก่อนใช้ อย่างไรก็ตาม ความแตกต่างเมื่อเทียบกับก่อนใช้ไม่ถึงนัยสำคัญทางสถิติในการศึกษานี้ และรายงานสรุปว่าไม่พบผลที่แสดงว่าผลิตภัณฑ์ทำลายเกราะป้องกันผิว';
  type(noteText, { fs: 14, fw: 400, lh: 1.85, ls: 0, tt: '', color: MUTED, margin: box(0, 0, 0, 0) });
  noteText.settings.align = 'left';
  delete noteText.settings._element_width; delete noteText.settings._element_custom_width; delete noteText.settings._flex_align_self;
  const card = container({
    flex_direction: 'column', flex_gap: gap(8.8), padding: box(17.6, 17.6, 17.6, 17.6), width: px(660),
    background_background: 'classic', background_color: BG,
    border_border: 'solid', border_width: box(1, 1, 1, 1), border_color: RULE, border_radius: box(8, 8, 8, 8),
  }, [
    heading('EVIDENCE NOTE', { fs: 10, fw: 400, lh: 1.55, ls: 1, tt: 'uppercase', color: ACCENT }),
    noteText,
  ]);
  const sec = get('f3bc824');
  sec.elements = sec.elements.map((e) => (e === oldNote ? card : e));
}

// ---- 07 barrier -------------------------------------------------------------------------------------------------------
{
  get('423342a').settings.flex_gap = gap(0);
  const eyebrow = get('c26d192');
  eyebrow.settings.title = 'SKIN RESULT 03 — BARRIER-FRIENDLY';
  eyebrow.settings._margin = m(0, 14);
  const h2 = get('d411e14');
  type(h2, { fs: 42, lh: 1.5, margin: m(0, 24), free: true });
  for (const id of ['70a452c', 'e1513af']) {
    const p = get(id);
    type(p, { fs: 17, fw: 400, lh: 1.85, color: MUTED, margin: m(0, 13.2) });
    widthOf(p, 680);
    // the bold terms keep the full ink colour while the paragraph is dimmed
    p.settings.editor = p.settings.editor.replace(/<strong[^>]*>/g, `<strong style="font-weight:500;color:${INK}">`);
  }
  const p2 = get('e1513af');
  p2.settings.editor = p2.settings.editor.replace(/"Repair\/Restore\/Rebuild Skin Barrier"/, '“Repair / Restore / Rebuild Skin Barrier”');
}

// Elementor gives every nested container 10px padding by default; the design has none.
const zeroPad = (nodes) => nodes.forEach((n) => { if (n.elType === 'container' && n.isInner && !n.settings.padding) n.settings.padding = box(0, 0, 0, 0); zeroPad(n.elements || []); });
zeroPad(page.slice(5, 8));

writeFileSync(arg('out'), JSON.stringify(page));
console.log('ok');
