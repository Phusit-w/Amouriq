// Downloads the fonts the CASTILE design uses (DM Sans, IBM Plex Sans Thai) from Google Fonts into the theme's
// assets/fonts folder and writes the matching @font-face CSS. Run once; the files and CSS are committed.
// Usage: node tools/castile-foundations/fetch-fonts.mjs --css-out path/to/fonts.css
import { mkdirSync, writeFileSync } from 'node:fs';

const arg = (n) => { const i = process.argv.indexOf(`--${n}`); return i > -1 ? process.argv[i + 1] : undefined; };
const dir = 'wp-content/themes/pcoursewebbs/assets/fonts/';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
const api = 'https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300..700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap';

const css = await (await fetch(api, { headers: { 'User-Agent': UA } })).text();
const blocks = [...css.matchAll(/\/\* ([\w-]+) \*\/\s*@font-face \{([^}]*)\}/g)].map((m) => ({ subset: m[1], body: m[2] }));
const get = (body, k) => (body.match(new RegExp(`${k}:\\s*([^;]+);`)) || [])[1];
mkdirSync(dir, { recursive: true });

const WEIGHTS = [300, 400, 500, 600, 700];
let out = `/* Fonts for the CASTILE sales page. The design sets DM Sans for Latin and IBM Plex Sans Thai for Thai.
 * Elementor typography takes a single family name, so the Thai glyphs of IBM Plex Sans Thai are registered here
 * under the name "DM Sans" (Thai unicode-range only): picking "DM Sans" in Elementor gives the design's pairing.
 * Self-hosted next to the theme assets (ADR-0005). Both fonts are SIL OFL.
 * Every weight is its own set (latin, latin-ext, thai): Chrome only mixes faces per character when their weight
 * descriptors are identical, so one 300-700 range face would shadow the Thai faces. */\n`;
const faces = [];
for (const b of blocks) {
  const family = get(b.body, 'font-family').replace(/'/g, '');
  const weight = get(b.body, 'font-weight');
  const url = get(b.body, 'src').match(/url\(([^)]+)\)/)[1];
  let name;
  if (family === 'DM Sans' && (b.subset === 'latin' || b.subset === 'latin-ext')) name = `dm-sans-${b.subset}.woff2`;
  else if (family === 'IBM Plex Sans Thai' && b.subset === 'thai') name = `ibm-plex-sans-thai-${weight}.woff2`;
  else continue;
  const buf = Buffer.from(await (await fetch(url)).arrayBuffer());
  writeFileSync(dir + name, buf);
  console.log(name, buf.length);
  const range = get(b.body, 'unicode-range');
  const isThai = family !== 'DM Sans';
  for (const w of isThai ? [Number(weight)] : WEIGHTS) {
    faces.push({ w, name, range });
  }
}
faces.sort((a, b) => a.w - b.w);
for (const f of faces) {
  out += `@font-face {
  font-family: "DM Sans";
  font-style: normal;
  font-weight: ${f.w};
  font-display: swap;
  src: url("../fonts/${f.name}") format("woff2");
  unicode-range: ${f.range};
}
`;
}
writeFileSync(arg('css-out'), out);
