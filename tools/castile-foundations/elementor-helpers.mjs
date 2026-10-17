// Shared helpers for the scripts that rewrite page 5309's Elementor data (tickets 10, 11, ...): value builders for
// Elementor's setting shapes, element lookup by id, and constructors/styling for containers and headings.
// `toolkit(page)` binds them to one parsed _elementor_data array.
import { randomBytes } from 'node:crypto';

export const FONT = 'DM Sans';
export const INK = '#3a322a';
export const BG = '#fbf8f2';
export const SURFACE = '#f5efe4';
export const ACCENT = '#c1673f';
export const RULE = '#3a322a29'; // ink at 16%
export const px = (size) => ({ unit: 'px', size, sizes: [] });
export const em = (size) => ({ unit: 'em', size, sizes: [] });
export const box = (t, r, b, l) => ({ unit: 'px', top: String(t), right: String(r), bottom: String(b), left: String(l), isLinked: false });
export const gap = (n, row = n) => ({ unit: 'px', size: n, column: String(n), row: String(row) });
export const pct = (size) => ({ unit: '%', size, sizes: [] });

export function toolkit(page) {
  const used = new Set();
  const collect = (nodes) => nodes.forEach((n) => { used.add(n.id); collect(n.elements || []); });
  collect(page);
  const find = (id, nodes = page) => {
    for (const n of nodes) {
      if (n.id === id) return n;
      const f = find(id, n.elements || []);
      if (f) return f;
    }
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

  // Fixed-width, left-anchored widget (the design's explicit max-widths).
  const widthOf = (w, size) => Object.assign(w.settings, { _element_width: 'initial', _element_custom_width: px(size), _flex_align_self: 'flex-start' });

  return { get, find, newId, container, heading, type, widthOf };
}
