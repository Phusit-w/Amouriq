// Matches Elementor heading / text-editor widgets to the text nodes the design measurement found, by normalised text.
// Shared by foundations.mjs (ticket 09) and mobile-round.mjs (ticket 15).
export const strip = (h) => String(h ?? '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;|&amp;|&#?\w+;/g, ' ');
export const norm = (h) => strip(h).toLowerCase().replace(/[^\p{L}\p{M}]+/gu, '');
export const widgetText = (w) => (w.widgetType === 'heading' ? w.settings.title : w.settings.editor);

// texts: the design's measured text nodes for one section (measure.mjs output); returns Map(widget -> index into texts).
export function matchTexts(texts, widgets) {
  const cands = texts.map((t, k) => ({ k, key: norm(t.text), used: false }));
  const result = new Map();
  for (const w of widgets) {
    const key = norm(widgetText(w));
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
  return { result, unused: cands.filter((c) => !c.used).length };
}
