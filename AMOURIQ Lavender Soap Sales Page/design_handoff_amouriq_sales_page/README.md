# Handoff: AMOURIQ Organic Olive Castile Soap — Sales Page

## Overview
A long-form, evidence-led sales page for AMOURIQ Organic Olive Castile Soap, built to be embedded as a page inside the existing website **www.amouriq.co.th**. Twenty-four stacked sections carry the argument from the product claim through third-party lab evidence to an interactive buying block, closing with FAQ and regulatory footnotes.

The page sells one product line in **three scents** (Lavender, Rosemary, Rose Geranium) × **three sizes** (100 / 250 / 500 ML), selected interactively in one buying block. Prices are identical across scents.

## About the Design Files
The files in this bundle are **design references created in HTML** — a prototype showing intended look and behavior, **not production code to copy directly**.

The task is to **recreate this design in the target codebase's existing environment** (React, Vue, Next.js, WordPress theme, Shopify section, etc.) using its established patterns, component library and routing. If no environment exists yet, choose the most appropriate framework for the project and implement the design there.

Two specific notes for the reimplementation:

1. **The page has no header and no footer by design.** They were deliberately removed because the host site (amouriq.co.th) already provides them. Mount this as page content inside the site's existing layout/shell. A standalone header exists behind an off-by-default flag (`standaloneChrome`) purely for previewing the page in isolation — do not ship it.
2. **The prototype is written as a single-file component with inline styles.** That is a constraint of the prototyping tool, not a recommendation. Reimplement using the codebase's normal styling approach (CSS modules, Tailwind, styled-components, SCSS partials) and split into components along the section boundaries listed below.

## Fidelity
**High-fidelity (hifi).** Colors, typography, spacing, animation timings and copy are final and should be reproduced faithfully. All copy is production Thai copy — do not paraphrase, re-translate or "improve" it; it has been reviewed for regulatory accuracy (cosmetic claim language, FDA notification numbers, lab-report wording).

Two exceptions, both flagged in place:
- Section 15 and Section 22 imagery are **placeholders** awaiting real photography (see Assets).
- The 100 ML and 250 ML packshots currently reuse the 500 ML bottle image per scent, as an interim stand-in.

---

## Design Tokens

All tokens come from the **AMOURIQ design system** (`_ds/…/styles.css` in this bundle). Read that file as the source of truth; the values below are for quick reference.

### Color — Identity Core (fixed, never retune)
| Name | Token | Hex | Role |
| --- | --- | --- | --- |
| Milk Oat | `--color-bg` | `#fbf8f2` | Page ground — every section starts here |
| Roasted Umber | `--color-text` | `#3a322a` | All body copy and headings |
| Sunbaked Clay | `--color-accent` | `#c1673f` | Primary actions, kickers |
| Olive Grove | `--color-accent-2` | `#4a5b32` | Second voice — full-bleed section grounds, general icons |

### Color — Flex Accent (revisitable)
| Name | Token | Hex | Role |
| --- | --- | --- | --- |
| Sage Mist Teal | `--color-accent-signature` | `#4f6d63` | **Data and stat only** — chart bars, metric icons, stat numerals |
| Web Light | `--color-surface` | `#f5efe4` | Digital surface tint under cards and alternating sections |

Derived: `--color-divider: color-mix(in srgb, #3a322a 16%, transparent)` — the only separator in the design.

Each role also carries a 100–900 OKLCH ramp on one shared lightness scale (e.g. `--color-accent-700` for accent-colored body copy, since accent-on-ground pairs are tuned to 3:1 and are not paragraph-safe).

### Spacing scale
`--space-1: 4.4px` · `--space-2: 8.8px` · `--space-3: 13.2px` · `--space-4: 17.6px` · `--space-6: 26.4px` · `--space-8: 35.2px`

Section padding in this page is fluid, not from the scale: `padding: clamp(48px,7vw,96px) clamp(16px,4vw,48px)`. Content column is `max-width: 1120px; margin: 0 auto`.

### Radius — nothing is a pill
`--radius-sm: 4px` (buttons, tags, inputs) · `--radius-md: 6px` (cards) · `--radius-lg: 8px` (large cards, image frames). **Never exceed 8px.**

### Elevation — flat by design
`--shadow-sm` and `--shadow-md` are `none` **on purpose**. Separation is drawn with `--hairline: 1px solid var(--color-divider)` or with whitespace. `--shadow-lg` exists only for overlays that have left the page (modals). **Do not add box-shadows to in-flow surfaces** — this is the most commonly violated rule in the system.

### Typography
- **Latin: DM Sans** — headings 700, body 400, emphasis 500.
- **Thai: IBM Plex Sans Thai** (weights 300–700), ahead of Prompt in the stack.

```css
--font-body:    "DM Sans", "IBM Plex Sans Thai", Prompt, system-ui, sans-serif;
--font-heading: "DM Sans", "IBM Plex Sans Thai", Prompt, system-ui, sans-serif;
```

**Why this deviates from the design system.** The system specifies Prompt as the only Thai face. Prompt stacks the tone mark directly onto the vowel mark with almost no gap — in words like "ตั้ง" (mai-han-akat with mai-tho above it) this reads as an overlap at body sizes. It is glyph geometry inside the font; no CSS property can loosen it. IBM Plex Sans Thai is loopless and geometric like Prompt but leaves proper room above the base. DM Sans carries no Thai glyphs, so Latin is unaffected and resolves to DM Sans as specified. **Keep this substitution** unless a brand decision replaces it — reverting to Prompt reintroduces the defect.

Thai-specific type overrides applied page-wide (all deviate from the DS defaults, which are tuned for Latin):
```css
h1,h2,h3,h4,h5,h6 { letter-spacing: 0; line-height: 1.5; }  /* DS default: -0.018em / 1.14 */
h1                { line-height: 1.42; }
p, li, summary    { line-height: 1.85; }
sup { font-size: .62em; line-height: 0; vertical-align: super; font-weight: 500; letter-spacing: 0; }
[data-thai-run]   { letter-spacing: normal; }
```

Fluid heading sizes used in-page: h2 `clamp(24px,3.2vw,38px)`, h3 `clamp(17px,2vw,22px)`, stat numerals `clamp(22px,2.6vw,30px)`, hero h1 `clamp(30px,4.6vw,54px)`.

### The Thai letter-spacing problem (must reimplement)
Thai vowel and tone marks are **zero-width combining characters**. Any `letter-spacing` is inserted *after* each mark, dragging the tone mark off its base — so uppercase labels, kickers and buttons with tracking render broken Thai.

The prototype solves this at runtime (`fixThaiTracking()`): walk text nodes, and for any node whose parent has non-zero computed `letter-spacing`, wrap each Thai run (`[\u0E00-\u0E7F]`) in a `<span data-thai-run>` that resets `letter-spacing: normal`. Latin keeps its tracking.

**In a real codebase, do this at authoring time instead** — a small `<ThaiRun>`/`<Th>` wrapper component, or a build-time transform on localized strings. A DOM walk on every render is a prototype workaround, not a pattern to ship. The rule to preserve: **tracked element + Thai text = reset tracking on the Thai run only.**

### Icons
Lucide, **outline only**: `fill: none`, `stroke: currentColor`, `stroke-width: 1.75` (stay 1.5–2), round caps and joins. Sizes 16 / 20 / 28px (`.icon-sm` / `.icon` / `.icon-lg`). No filled variant exists in the system.

Color rule, strictly observed in this page:
- `.icon-signature` (Sage Mist Teal) — **stat and data icons only**
- `.icon-accent-2` (Olive Grove) — general/utility icons
- On dark grounds (Olive Grove, Roasted Umber) icons **inherit Milk Oat from surrounding text** — teal fails contrast there

Icons used: `droplet`, `arrow-down-to-line`, `shield` (the results triad, repeated in Hero and Section 08); `droplets`, `sparkles`, `shower-head` (how-to-use steps); `droplet-off`, `flask-conical-off`, `palette`+slash, `clock`+slash (the "not added" cards — all four share the identical slash path `m2 2 20 20` so "free from" reads without text).

---

## Screens / Views

One continuously scrolling page, 24 sections. Each is a `<section>` carrying `data-screen-label` in the prototype (useful as component names). Section grounds alternate Milk Oat / Web Light, with two full-bleed dark sections as punctuation — **max two background colors plus the dark punctuation**, per the system.

| # | Label | Purpose | Ground | Layout |
| --- | --- | --- | --- | --- |
| 00 | Trust bar | Three proof chips above the fold | Milk Oat | Row, hairline bottom, `white-space: nowrap` |
| 01 | Hero | Product claim + 4:5 lifestyle image + three stat cards | Milk Oat | 2-col `auto-fit minmax(320px,1fr)`, image left |
| 02 | Memory lock | One-line thesis restatement | Web Light | Centered single column |
| 03 | Why real soap | Defines "real soap" chemically | Milk Oat | Text + kicker |
| 04 | Not enough | Why "natural" alone is insufficient | Web Light | Text |
| 05 | Moisture | **Corneometer chart**, 4 rows | Milk Oat | Label / bar / value grid `minmax(96px,1.1fr) 2fr auto` |
| 06 | Water loss | **TEWL chart**, 4 rows + key takeaway + evidence note | Web Light | Same grid as 05 |
| 07 | Barrier | Barrier-friendly conclusion | Milk Oat | Text |
| 08 | Memory lock 2 | The results triad restated with icons | **Olive Grove** | 3-col, Milk Oat text |
| 09 | Free alkali | "NOT DETECTED" hero claim | Milk Oat | Large display numeral treatment |
| 10 | Finished-soap proof | Lab method explanation | Web Light | Text |
| 11 | Proof bridge | Oils → soap transition | Milk Oat | 2-col with directional arrow (→ desktop, ↓ mobile) |
| 12 | Oils to soap | Ingredient origin | Web Light | Card grid |
| 13 | Why we made it | Brand rationale, 3 stat cards (3 ปี / 72 ชม / 10+ ปี) | Milk Oat | Quote + stat row |
| 14 | Not added | Four "free from" cards | Web Light | 4-col `auto-fit`, icon + label + one-line why |
| 15 | The ritual | Lifestyle image + ritual copy | **Olive Grove** panel | 50/50 split, image left, stacks on mobile |
| 16 | Evidence | Certifications, incl. **three Thai FDA notification numbers** | Milk Oat | Card grid, equal-height |
| 17 | Why trust | Trust rationale | Web Light | Text |
| 18 | Customer voice | Testimonial | Milk Oat | Quote block |
| 19 | Buying block | **Interactive scent × size selector** | Web Light | Packshot left, controls right |
| 20 | How to use | Three steps with icons | Web Light | 3-col |
| 21 | FAQ | Accordion, native `<details>` | Milk Oat | Single column, hairline rows |
| 22 | Final close | Lifestyle image + final CTA | **Roasted Umber** panel | 50/50 split, image left |
| 23 | Footnotes | Footnote definitions, regulatory references | Milk Oat | Small type, hairline top |
| — | Sticky cart bar | Mobile-only add-to-cart | Milk Oat | `position: fixed`, bottom |

### Section 19 — Buying block (the only stateful section)

Product data:
```js
scents = [
  { name: 'Lavender',      code: 'LV', note: 'ดอกลาเวนเดอร์ นุ่ม สงบ',    oils: 'Lavender + Lavandin Essential Oils', img: 'assets/packshot-lavender-500.png' },
  { name: 'Rosemary',      code: 'RM', note: 'สมุนไพร สดชื่น ปลอดโปร่ง',  oils: 'Rosemary Essential Oil',             img: 'assets/packshot-rosemary-500.png' },
  { name: 'Rose Geranium', code: 'RG', note: 'ดอกกุหลาบ อบอุ่น กลมกล่อม', oils: 'Rose Geranium Essential Oil',        img: 'assets/packshot-rose-geranium-500.png' }
];

// Prices are identical across scents; the SKU differs only by the scent code.
variants = [
  { label: '100 ML', price: 459,  sku: '0100-1' },
  { label: '250 ML', price: 779,  sku: '0150-1 (รอยืนยัน)' },
  { label: '500 ML', price: 1199, sku: '0500-1' }
];
```

Selecting a scent swaps packshot, product name, essential-oil label and SKU. Selecting a size swaps price, SKU and total. **The 250 ML SKU is unconfirmed** ("รอยืนยัน") — confirm with the client before shipping.

**Interim asset note:** all three packshots are 500 ML bottles, reused for 100 and 250 ML. A note stating this appears under the price table — remove it once real packshots per size exist.

---

## Interactions & Behavior

Overall motion direction: **calm, precise, editorial, measured, evidence-led — never promotional or playful.** No bounce, spring, overshoot, glow, typewriter or letter-by-letter effects anywhere. Every animation runs **once only** and every animation must honor `prefers-reduced-motion: reduce` by rendering the final state immediately.

### 1. Scroll-triggered count-up (Hero stats, Section 13 stats, chart values)
Numerals animate 0 → final value on entering the viewport.
- Easing: **cubic ease-out** — `1 - Math.pow(1 - p, 3)`
- Duration: **1400ms** default; **740ms** for chart row values
- Trigger: IntersectionObserver, threshold **0.4**, `unobserve` on fire (once only)
- Format preserved through the animation: sign prefix (`+` / `−`), decimal places (2 for percentages, 0 for integers), suffix (`%` / `+`)
- Static words around the numeral ("เกือบ", "≥", "ปี", "ชั่วโมง") never move
- Values animated: `+13.37%`, `−10.67%` (Hero); `3`, `72`, `10+` (Section 13); all chart row values

**Implementation caution:** in the prototype the animated text is held in a `Map` and re-applied after every re-render, because React restores the literal template text otherwise. In a real codebase, hold the displayed value in component state instead — cleaner and no DOM patching.

### 2. Scroll-triggered mask reveal (Section 09 "NOT DETECTED", chart headings, key takeaways)
Each revealed line is an `overflow: hidden` mask wrapper containing an inner element that animates:
- `opacity: 0 → 1` and `translateY(20px) → translateY(0)`
- Duration **800ms**, easing **`cubic-bezier(0.22, 1, 0.36, 1)`**
- Trigger: IntersectionObserver, threshold **0.25**, once only
- Stagger in Section 09: eyebrow "FREE ALKALI" at **0ms** → "NOT DETECTED" at **180ms** → supporting evidence line at **510ms**
- The large-display mask needs `padding-bottom: .06em` so descenders aren't clipped

### 3. Chart row animation (Sections 05 and 06)
Both charts use identical motion; the moisture chart is permitted slightly stronger positive emphasis because its increases are statistically significant.
- Eyebrow and headline reveal first (mask reveal, as above)
- Pale track visible first, then the filled bar animates `scaleX(0 → 1)` with `transform-origin: left`
- Bar duration **860ms**; the emphasized row (**+13.37%**, 4-hour result) runs **960ms** (≈100ms longer)
- Stagger between rows: **160ms**, top to bottom
- Row value counts up over **740ms** on the same clock
- After all rows, the key-takeaway line does a whole-line mask reveal, then the supporting statement fades, then the statistical evidence note fades
- **+13.37% carries the strongest hierarchy through darker fill and stronger type — not through dramatic motion.** Only that one row is emphasized (the 8-hour row was deliberately de-emphasized).

### 4. Sticky mobile add-to-cart bar
Appears below **860px** viewport width, after the Hero has scrolled out of view.
- Driven by a media query listener **plus** an IntersectionObserver on `#hero` — **never cached scroll offsets**, which can be measured before layout settles and then go stale if scrolling happens in a container rather than on `window`
- Condition: `!entry.isIntersecting && entry.boundingClientRect.top < 0`
- Sections reserve bottom padding so the bar never covers the footnotes

### 5. FAQ accordion
Native `<details>`/`<summary>`, hairline row separators, default marker removed (`summary::-webkit-details-marker { display: none }`). No JS.

### 6. Footnote links
Every footnote marker (`*`, `**`, `†`, `‡`) is a superscript anchor linking to its definition in Section 23. Targets carry `scroll-margin-top: 96px` so the host site's sticky header doesn't cover them — **retune this value to the real header height.**

### 7. Smooth anchor scrolling
`html { scroll-behavior: smooth }`. In-page anchors: `#results`, `#evidence`, `#faq`, `#buy`.

### Responsive behavior
Single fluid breakpoint family — no fixed widths anywhere. Multi-column sections use `grid-template-columns: repeat(auto-fit, minmax(320px,1fr))` and collapse naturally. The only scripted breakpoint is **859px** for the sticky cart bar. Sections 15 and 22 are 50/50 splits that stack image-above-text on narrow screens. `white-space: nowrap` is applied to trust-bar chips and CTA labels to prevent mid-word Thai breaks.

## State Management

| State | Type | Purpose |
| --- | --- | --- |
| `scent` | index 0–2 | Selected scent → packshot, name, oils label, SKU |
| `size` | index 0–2 | Selected size → price, SKU, total |
| `mobile` | boolean | Viewport < 860px (media query listener) |
| `pastHero` | boolean | Hero scrolled out of view (IntersectionObserver) |

`mobile && pastHero` shows the sticky cart bar. Animation "has run" flags are held outside render state (`WeakSet` / `Map` keyed by element) so re-renders never replay an animation.

No data fetching — all product data is static and inline. Wire `scent` + `size` to the real cart/commerce API on integration; the prototype's add-to-cart is inert.

## Assets

In `assets/` in this bundle:

| File | Used in | Status |
| --- | --- | --- |
| `logo-dark.png` / `logo-light.png` | Standalone header only (off by default) | Design-system asset |
| `lifestyle.png` | Section 01 Hero, 4:5 | **Real** |
| `olive-grove.jpg` | Section 12 | **Real** |
| `packshot-lavender-500.png` | Section 19 | **Real** (500 ML) |
| `packshot-rosemary-500.png` | Section 19 | **Real** (500 ML) |
| `packshot-rose-geranium-500.png` | Section 19 | **Real** (500 ML) |
| `packshot-lavender.png` | superseded | Legacy — can be dropped |
| — | Section 15 "The Ritual" | **PLACEHOLDER** — needs real photo |
| — | Section 22 "Final close" | **PLACEHOLDER** — needs real photo |

Sections 15 and 22 use an `<image-slot>` placeholder element in the prototype. **Replace with real `<img>` elements** on integration — the placeholder component is a prototyping affordance and should not ship.

Required photo dimensions (both crop from center — keep the subject centered with ~15% margin):
- **Hero (Section 01):** 4:5 portrait, **1600 × 2000 px** (minimum 1080 × 1350)
- **Section 15 and Section 22:** square, **1800 × 1800 px**
- JPEG q85 or WebP, ≤400 KB per file

### Logo
The wordmark is an **image asset, never re-typed as text**. Pick by background: `logo-dark.png` on light grounds, `logo-light.png` on dark. Apply `class="logo"` (pins `aspect-ratio: 7500/1111`) or set `height` alone — never both width and height. Never recolor the file or crop the ®. In this page the logo appears only in the off-by-default standalone header; the host site supplies the real one.

### Fonts
Loaded from Google Fonts:
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap">
```
DM Sans comes from the design-system stylesheet. Self-host on integration if the site's performance budget calls for it.

---

## Content Rules (carry these into the implementation)

The copy is not decorative — it is regulated product communication in a claims-sensitive category.

- **Explain, don't sell.** Short declarative sentences, concrete nouns over adjectives. Say what an ingredient does and where it comes from.
- **No urgency, no superlatives, no exclamation marks, no fear-based claims.** Confidence comes from specificity.
- **Never soften or strengthen an evidence claim.** The TEWL evidence note explicitly states the differences did **not** reach statistical significance — that qualification is deliberate and legally material. Do not cut it for brevity or symmetry.
- **The three Thai FDA notification numbers** (`10-1-6800022354`, `10-1-6800023739`, `10-1-6800023133`) must all remain visible in Section 16 and in the footnotes.
- **Dagger footnote markers** (`†`, `‡`) attach to every "100% REAL SOAP" claim and must resolve to a real definition.
- **Audience:** working women 30–50, time-poor, trust verifiable proof over price or hype, read fast rather than skim. Keep blocks short; put proof points where the eye lands early.

## Files

| File | What it is |
| --- | --- |
| `AMOURIQ Lavender Sales Page.dc.html` | The design reference — all 24 sections, all interactions. Open in a browser to see the intended result. |
| `support.js` | Prototyping-tool runtime. **Not part of the design; do not port.** |
| `image-slot.js` | Placeholder-image component for Sections 15/22. **Prototyping affordance; do not port.** |
| `_ds/amouriq-…/styles.css` | **The design system stylesheet — the source of truth for all tokens.** Port these values into the target codebase's theme. |
| `_ds/amouriq-…/_ds_bundle.js` | Design-system component bundle |
| `assets/` | Images and logo files listed above |
| `uploads/` | Original client source material (design brief, packshot originals) |

### Reading the design reference
The HTML file is structured as a template plus a logic class. Ignore the tooling wrapper and read:
- Template markup between `<x-dc>` and `</x-dc>` — the 24 sections, in order, each marked with `data-screen-label`
- `<helmet>` at the top — font loading, base resets, the Thai type overrides
- `class Component extends DCLogic` — product data, state, and the animation implementations (`initCountUps`, `initReveals`, `initSeq`, `fixThaiTracking`)

Template holes are `{{ dotted.path }}` and `<sc-for>` / `<sc-if>` are loop and conditional — translate to the target framework's equivalents.

## Open Items for the Client
1. **250 ML SKU unconfirmed** — currently `0150-1 (รอยืนยัน)`.
2. **Section 15 and Section 22 photography** — placeholders in place.
3. **Per-size packshots** — 100 and 250 ML currently reuse the 500 ML bottle image.
4. **Host header height** — `scroll-margin-top: 96px` on footnote targets is an estimate; retune to the real amouriq.co.th header.
5. **Scent-aware copy** — the buying block is fully scent-aware, but body copy elsewhere still speaks primarily about Lavender. Decide whether this is a Lavender-led page with three purchase options, or should become fully scent-neutral.
