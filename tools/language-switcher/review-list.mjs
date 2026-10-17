// Prints the owner's review table for a translations file, so the readable list is
// generated from the JSON instead of being kept in step by hand.
//   node tools/language-switcher/review-list.mjs <file.json> >> <list.md>
// Rows are numbered from 1 in file order; the owner answers with those numbers.
import { readFileSync } from 'node:fs';

const file = process.argv[2];
if (!file) { console.error('usage: review-list.mjs <file.json>'); process.exit(2); }
const cell = (s) => s.replace(/\|/g, '\\|').replace(/\r?\n/g, ' <br> ');
const rows = JSON.parse(readFileSync(file, 'utf8'));
console.log('| # | Thai (as stored) | English draft |');
console.log('|---|---|---|');
rows.forEach((r, i) => console.log(`| ${i + 1} | ${cell(r.th)} | ${cell(r.en)} |`));
