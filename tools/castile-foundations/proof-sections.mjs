// Ticket 11 record: rebuilds the chart rows, numbered eyebrows, evidence-note card and key-takeaway blocks of the
// CASTILE proof sections (05 moisture, 06 water loss, 07 barrier, 08 memory lock 2, 04 not enough, 09 free alkali, 10 finished soap, 11 proof bridge) to match the Claude Design reference.
//
// Input : current _elementor_data JSON of page 5309, as it stands after ticket 10
// Output: new _elementor_data JSON, written with write-page.php (raw $wpdb->update, never update_post_meta).
// Not idempotent (it adds a container): run it once against the data as it stood after ticket 10.
//
// Usage: node tools/castile-foundations/proof-sections.mjs --page page.json --out new.json
import { readFileSync, writeFileSync } from 'node:fs';
import { INK, BG, SURFACE, ACCENT, RULE, px, box, gap, pct, toolkit } from './elementor-helpers.mjs';

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

// ---- 04 not enough (dark intro to the proof sections) ---------------------------------------------------------------------
{
  get('e01ebd2').settings.flex_gap = gap(0);
  const h2 = get('1ae4342');
  h2.settings.title = 'แต่สำหรับเรา การเป็น “สบู่เหลวแท้” ยังไม่พอ';
  type(h2, { margin: m(0, 18), free: true });
  type(get('5c51b2e'), { fs: 26, fw: 700, lh: 1.5, ls: 0, margin: m(0, 24) });
  const p = get('7f68ed5');
  p.settings.editor = 'AMOURIQ ไม่ได้หยุดอยู่ที่วัตถุดิบ สูตร หรือคำว่า Castile เราเลือกทดสอบ<br><strong style="font-weight:500">ผลิตภัณฑ์สำเร็จรูปบนผิวจริง</strong>';
  type(p, { fs: 16.5, fw: 400, lh: 1.85, ls: 0, margin: m(0, 13.2) });
  type(get('c4f8a7f'), { fs: 26, fw: 400, lh: 1.55, ls: 0, margin: box(31, 0, 0, 0) });
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

// ---- 08 memory lock (dark, three columns) -----------------------------------------------------------------------------
{
  get('b548825').settings.flex_gap = gap(0);
  const h2 = get('49c49a3');
  h2.settings.title = '100% REAL SOAP<sup><a href="#fn-dagger">†</a></sup> ที่คิดถึงสิ่งที่เกิดขึ้นกับผิว<br>หลังล้าง';
  h2.settings._margin = m(0, 36);
  get('3d9e6e8').settings.flex_gap = gap(36);
  for (const [colId, svgId, headId, descId] of [['7e202ea', '217cbf3', 'd2565c5', '5d7e7f4'], ['0b65c83', 'adbb6a8', 'b300d2a', '975b6f9'], ['681a8aa', '43137da', '00e6794', '5cf3133']]) {
    const col = get(colId);
    Object.assign(col.settings, {
      flex_gap: gap(0), padding: box(14, 0, 0, 0),
      border_border: 'solid', border_width: box(1, 0, 0, 0), border_color: '#fbf8f24d',
    });
    const svg = get(svgId), head = get(headId), desc = get(descId);
    delete svg.settings._margin;
    delete head.settings._margin;
    type(head, { fs: 22, fw: 700, lh: 1.55, ls: 0, margin: null });
    type(desc, { fs: 14.5, fw: 400, lh: 1.55, ls: 0, margin: null });
    const header = container({ flex_direction: 'row', flex_align_items: 'center', flex_gap: gap(9), margin: m(0, 6) }, [svg, head]);
    col.elements = [header, desc];
  }
}

// ---- 09 free alkali ---------------------------------------------------------------------------------------------------
{
  const sec = get('5552040');
  sec.settings.flex_gap = gap(0);
  const eyebrow = get('9e97b46');
  eyebrow.settings._margin = m(0, 14);
  const h2 = get('815f34b');
  h2.settings.title = 'มี KOH ในกระบวนการทำสบู่ แต่ผลตรวจ<br>"ไม่พบด่างอิสระ" ในสบู่สำเร็จรูป';
  h2.settings._margin = m(0, 20);
  widthOf(h2, 912);
  const intro = get('0791c27');
  type(intro, { fs: 17, fw: 400, lh: 1.85, color: MUTED, margin: m(0, 0) });
  widthOf(intro, 680);
  intro.settings.editor = intro.settings.editor.replace(' AMOURIQ แต่', ' AMOURIQ<br>แต่').replace(/<strong[^>]*>/g, `<strong style="font-weight:500;color:${INK}">`);

  const big = get('fd11604');
  big.settings.title = 'NOT DETECTED';
  type(big, { fs: 76, fw: 700, lh: 1, ls: -2.28, tt: 'none', color: '#4a5b32', margin: null, free: true });
  const note = get('6c1d7e9');
  type(note, { fs: 14.5, fw: 400, lh: 1.85, ls: 0, color: MUTED, margin: m(18, 0) });
  widthOf(note, 551);
  note.settings.editor = note.settings.editor.replace('โดยมวล) ', 'โดยมวล)<br>').replace(/<strong[^>]*>/g, `<strong style="font-weight:500;color:${INK}">`);
  const card = container({
    flex_direction: 'column', flex_gap: gap(0), padding: box(44, 44, 44, 44), margin: box(48, 0, 48, 0),
    background_background: 'classic', background_color: SURFACE,
    border_border: 'solid', border_width: box(1, 1, 1, 1), border_color: RULE, border_radius: box(8, 8, 8, 8),
  }, [
    heading('FREE ALKALI', { fs: 11, fw: 400, lh: 1.55, ls: 1.54, tt: 'uppercase', color: INK, margin: m(0, 10) }),
    big, note,
  ]);
  sec.elements = sec.elements.flatMap((e) => (e === big ? [card] : e === note ? [] : [e]));

  const quote = get('d668473');
  quote.settings.editor = 'คุณภาพของกระบวนการ ควรมีสิ่งที่ตรวจสอบได้ในผลิตภัณฑ์สำเร็จรูป';
  type(quote, { fs: 17, fw: 400, lh: 1.85, ls: 0, color: MUTED, margin: m(0, 13.2) });
  widthOf(quote, 544);
  Object.assign(quote.settings, { _padding: box(0, 0, 0, 18), _border_border: 'solid', _border_width: box(0, 0, 0, 2), _border_color: ACCENT });
  const foot = get('b987653');
  type(foot, { fs: 12.5, fw: 400, lh: 1.85, ls: 0, color: '#3a322a8c', margin: box(24, 0, 13.2, 0) });
  widthOf(foot, 550);
}

// ---- 10 finished-soap analysis (four cards in one row) -----------------------------------------------------------------
{
  const sec = get('d7c3001');
  sec.settings.flex_gap = gap(0);
  get('e0b583f').settings._margin = m(0, 14);
  const h2 = get('4afef18');
  h2.settings.title = 'เราไม่ได้หยุดตรวจแค่วัตถุดิบ แต่ตรวจสิ่งที่อยู่ในสบู่<br>หลังผลิตเสร็จจริง';
  h2.settings._margin = m(0, 14);
  widthOf(h2, 912);
  type(get('d22f640'), { fs: 22, fw: 500, lh: 1.5, ls: 0, color: MUTED, margin: m(0, 40), free: true });

  const cards = [['eea2af7', 'dc0d24e', '5b07abb', '5c54e43'], ['3819d29', 'f62bc01', 'b89bc3e', '3b720f1'],
    ['6af3969', '512663d', '4d43fc7', '566a26d'], ['f3d0ec2', 'bca3e50', '3725c01', 'f0f986d']]
    .map(([cardId, titleId, tagId, descId]) => {
      const card = get(cardId), title = get(titleId), tag = get(tagId), desc = get(descId);
      Object.assign(card.settings, {
        width: pct(25), flex_gap: gap(8.8), padding: box(17.6, 17.6, 17.6, 17.6),
        background_color: BG, border_border: 'solid', border_width: box(1, 1, 1, 1), border_color: RULE, border_radius: box(8, 8, 8, 8),
      });
      type(tag, { fs: 10, fw: 400, lh: 1.55, ls: 1, tt: 'uppercase', color: '#4a5b32', margin: null });
      type(title, { fs: 17, fw: 700, lh: 1.2, ls: 0, color: INK, margin: null });
      type(desc, { fs: 13, fw: 400, lh: 1.85, ls: 0, color: MUTED, margin: null });
      card.elements = [tag, title, desc];
      return card;
    });
  const row = get('f8698cc');
  Object.assign(row.settings, { flex_gap: gap(14) });
  row.elements = cards;
  const note = get('f52f025');
  note.settings.editor = note.settings.editor.replace('ใน Section 09', 'ด้านบน');
  type(note, { fs: 13, fw: 400, lh: 1.85, ls: 0, color: MUTED, margin: box(24, 0, 13.2, 0) });
  widthOf(note, 598);
  sec.elements = sec.elements.filter((e) => e.id !== 'ab07809');
}

// ---- 11 proof bridge (finished soap → what happens on skin) ----------------------------------------------------------------
{
  const sec = get('0173364');
  sec.settings.flex_gap = gap(0);
  const h2 = get('c997d8e');
  h2.settings.title = 'แต่การตรวจพบสิ่งที่อยู่ในสบู่<br>ยังไม่ใช่คำตอบสุดท้าย';
  h2.settings._margin = m(0, 54);
  widthOf(h2, 960);
  const row = get('4a2fcc6');
  row.settings.flex_gap = gap(28);

  const side = (cardId, eyebrowId, listId, items, color, itemColor, bg) => {
    const card = get(cardId);
    Object.assign(card.settings, {
      content_width: 'full', width: pct(45.4), flex_gap: gap(10), padding: box(32, 32, 32, 32),
      background_color: bg, border_border: 'solid', border_width: box(1, 1, 1, 1), border_color: RULE, border_radius: box(8, 8, 8, 8),
    });
    const eyebrow = get(eyebrowId);
    type(eyebrow, { fs: 11, fw: 400, lh: 1.55, ls: 1.32, tt: 'uppercase', color, margin: m(0, 6) });
    const list = items.map((t) => heading(t, { fs: 20, fw: 700, lh: 1.55, color: itemColor }));
    card.elements = [eyebrow, ...list];
    return card;
  };
  const left = side('17cc6f0', 'cb4d0b7', 'ace8142', ['Squalene', 'Beta-carotene', 'Glycerine<sup><a href="#fn-ddagger">‡</a></sup>', 'Alpha-tocopherol<sup><a href="#fn-ddagger">‡</a></sup>'], '#4a5b32', INK, BG);
  const right = side('0f8b5cc', '7b09715', '1a166c5', ['Moisture ↑', 'Water Loss ↓', 'Barrier-Friendly'], HI, '#41574f', '#f0f8f5');
  void left; void right;
  const arrowCol = get('e920b4d');
  Object.assign(arrowCol.settings, { content_width: 'full', width: px(44), flex_align_items: 'center', flex_justify_content: 'center' });
  type(get('53a8ec9'), { fs: 26, fw: 400, lh: 1.55, ls: 0, color: ACCENT, margin: null });

  const quote = get('15133a6');
  type(quote, { fs: 20, fw: 400, lh: 1.85, ls: 0, color: INK, margin: box(44, 0, 0, 0) });
  widthOf(quote, 680);
  Object.assign(quote.settings, { _padding: box(0, 0, 0, 20), _border_border: 'solid', _border_width: box(0, 0, 0, 2), _border_color: ACCENT });
}

// Elementor gives every nested container 10px padding by default; the design has none.
const zeroPad = (nodes) => nodes.forEach((n) => { if (n.elType === 'container' && n.isInner && !n.settings.padding) n.settings.padding = box(0, 0, 0, 0); zeroPad(n.elements || []); });
zeroPad(page.slice(4, 12));

writeFileSync(arg('out'), JSON.stringify(page));
console.log('ok');
