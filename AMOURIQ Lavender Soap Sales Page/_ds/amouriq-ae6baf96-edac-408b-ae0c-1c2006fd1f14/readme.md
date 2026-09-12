# AMOURIQ design system

AMOURIQ makes organic olive Castile soap and natural oils. The system that carries it is **calm, warm and expert** — the voice of a knowledgeable friend, not a salesperson. Warm cream grounds, olive and clay accents, one sans family working across English and Thai, a flat minimal frame with hairlines instead of shadow, and outline icons at a thin, even stroke.

**Tone:** explain, don't sell. Short declarative sentences; concrete nouns over adjectives. Say what an ingredient does and where it comes from. No urgency, no superlatives, no exclamation marks, no fear-based claims. Confidence comes from specificity — "7 botanical oils, sulfate-free" beats "the best soap ever."

**Who this is for:** working women 30–50, mid-to-high income, time-poor but unwilling to compromise on self-care — they trust verifiable proof (certifications, licenses, ingredient origin) over price or hype, and read fast rather than skim. Use this only as a tie-breaker when copy density or layout is genuinely open: keep blocks short and put the proof points where the eye lands early.

## How to use this

- Link the one stylesheet from every page — `<link rel="stylesheet" href="styles.css">` (adjust the relative path) — and take every color, font, spacing, radius and shadow from its variables (`var(--color-*)`, `var(--font-*)`, `var(--space-*)`, `var(--radius-*)`, `var(--icon-stroke)`, `var(--hairline)`). Never hard-code a hex, a font name or a px value the tokens already carry.
- Build with the classes in the table below rather than inventing parallel ones; the component pages are plain HTML — view source and copy the markup.
- `templates/` holds starting points a consuming project can copy whole.
- `theme.json` is the machine-readable record of these decisions. Change the look at the top of `styles.css`, then keep `theme.json` and this guide in step.

## Logo

The wordmark is an **image asset, never re-typed** — `assets/logo-dark.png` and `assets/logo-light.png` are the same wordmark in two colors. Pick by the background behind it:

| Background | File |
| --- | --- |
| Light — Milk Oat, Web Light, any light surface or tint | `assets/logo-dark.png` (Roasted Umber) |
| Dark — Olive Grove, Sunbaked Clay, any dark surface | `assets/logo-light.png` (Milk Oat) |

Place it with `class="logo"` (or set `height` alone) — the class pins `aspect-ratio: 7500 / 1111`, so proportions, letterspacing and the ® mark can never be distorted. Never recolor the file, set both width and height, crop the ®, or substitute live type for the mark.

## Color — Identity Core vs Flex Accent

The palette has two tiers, and they are governed differently.

**Identity Core — never changes.** These four carry brand recognition across packaging, print and web. Treat the hexes as fixed.

| Name | Token | Hex | Role |
| --- | --- | --- | --- |
| Milk Oat | `--color-bg` | `#fbf8f2` | The ground — every page starts here |
| Roasted Umber | `--color-text` | `#3a322a` | All body copy and headings |
| Sunbaked Clay | `--color-accent` | `#c1673f` | The lead accent: primary actions, kickers |
| Olive Grove | `--color-accent-2` | `#4a5b32` | The genuine second voice, not a highlight |

**Flex Accent — revisitable every 1–2 years.** These support the core and may be retuned as the brand evolves, without touching the four above.

| Name | Token | Hex | Role |
| --- | --- | --- | --- |
| Sage Mist Teal | `--color-accent-signature` | `#4f6d63` | Stat and data icons, secondary digital CTAs (`.icon-signature`, `.btn-signature`, `.tag-signature`) |
| Web Light | `--color-surface` | `#f5efe4` | Digital-only surface tint under cards, inputs and dialogs |

Every role also carries a 100–900 tonal ramp generated in OKLCH on one shared lightness scale, so the same step of any ramp has the same visual weight: light steps (100–300) for tinted fills and hovers, 500 as the base, dark steps (700–900) for text on tints and pressed states. Accent-on-ground pairs are tuned to at least 3:1 — fine for icons, large text and chrome, not for paragraphs; use `--color-accent-700` for accent-colored body copy. Full ramps and usage: `foundations/color.html`.

## Type

One English family, varied by weight — **DM Sans**: headings at 700 (`--font-heading` / `--font-heading-weight`), body at 400 with 500 for emphasis (`--font-body-weight` / `--font-body-weight-medium`). **Thai is Prompt** at any weight; it sits second in both stacks, so Thai glyphs resolve to it automatically, and `--font-heading-th` / `--font-body-th` (or `lang="th"` / `.th`) name it directly. No third face, ever. Scale and specimens: `foundations/type.html`.

## Space and frame — minimal and flat

Small corners, no lift, generous editorial whitespace. Radii run 4–8px (`--radius-sm/md/lg`) and nothing is a pill. Surfaces separate with a 1px `var(--hairline)` or, better, with air from the `--space-*` scale — `--shadow-sm` and `--shadow-md` are `none` on purpose, and `--shadow-lg` exists only for overlays that have left the page (the dialog). Layouts stay left-aligned and asymmetric: flush-left headings, content hugging the left edge, whitespace on the right. Details: `foundations/layout.html`.

## Icons

Lucide, **outline only** — `fill: none`, `stroke: currentColor` at `var(--icon-stroke)` (1.75px; stay within 1.5–2), round caps and joins to echo the small corners. Inline the SVG with `class="icon"` (`.icon-sm` 16px, `.icon-lg` 28px). Icons inherit the text color; `.icon-accent` / `.icon-accent-2` carry emphasis and `.icon-signature` marks stat and data. No filled or solid variant exists. See `foundations/icons.html`.

## Photography

Warm, approachable **minimalist lifestyle editorial**: natural window light with its direction visible, crisp focus, medium saturation and contrast, warm grading that comes from what is *in frame* — linen, olive branches, clay, wood — never from a filter. Lived-in and uncluttered staging. Never clinical/lab, never vintage-spa, never glamour-lit. Full brief and the "never" list: `foundations/image.html`.

## Components

| Class | What it is | Shown in |
| --- | --- | --- |
| `.btn` with `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-signature`, `.btn-icon`, `.btn-block` | Actions — the primary is a solid clay fill | components/buttons.html |
| `.tag` with `.tag-accent`, `.tag-accent-2`, `.tag-signature`, `.tag-neutral`, `.tag-outline` | Small labels tinted from the ramps | components/buttons.html |
| `.field` + `label`, `.input`, `.radio` + `.dot`, `.seg` + `.seg-opt` | Form fields and choices on native elements — no script | components/forms.html |
| `.card` with `.card-kicker`, `.card-title`, `.card-body`, `.card-meta`; `.elev-sm/md/lg` | Hairline-edged content cards; separation utilities | components/cards.html |
| `.nav` + `.nav-brand` | The header bar | components/navigation.html |
| `.table` | Data tables with themed header and row rules | components/table.html |
| `.dialog-backdrop` + `.dialog` (+ `.dialog-title/-body/-actions`) | A modal — the one place a shadow is allowed | components/dialog.html |
| `.icon` (+ `-sm`, `-lg`, `-accent`, `-accent-2`, `-signature`) | Outline icon sizing and color | foundations/icons.html |
| `.washed` | The image wrapper every content photograph goes through | foundations/image.html |

Interaction states are built in and themed: hovers and pressed states step one past the base on the accent ramp, keyboard focus is `2px solid var(--color-accent)` on `:focus-visible`, `::selection` is an accent tint, disabled drops to 45% opacity. Don't restyle them per page, and never leave a browser-default focus ring.

## Do

- Keep the Identity Core hexes exactly as they are; retune only the Flex Accent pair, and only deliberately.
- Separate surfaces with `var(--hairline)` or whitespace; keep components flat.
- Use Olive Grove as a real second voice, not a highlight.
- Let Sage Mist Teal mark data — stats, charts, metric icons.
- Write like a knowledgeable friend: specific, calm, useful.

## Don't

- Do not round components past `--radius-lg` (8px), and do not make anything a pill.
- Do not add box-shadows to in-flow surfaces; lift belongs to overlays only.
- Do not use filled/solid icons or strokes outside 1.5–2px.
- Do not introduce a third typeface; DM Sans is the only Latin voice, Prompt the only Thai one.
- Do not desaturate the palette into greys — warmth is the point.
- Do not shoot or select clinical, spa-nostalgic or glamour-lit imagery, and do not filter photographs to fake the palette.
- Do not write sales copy: no urgency, superlatives, exclamation marks or fear claims.
- Do not re-type the wordmark as text, recolor the logo files, or use the dark logo on a dark ground (or the light one on a light ground).

## Files

- `styles.css` — the only stylesheet: token sheet (`:root` variables, ramps, base type) plus the component layer.
- `theme.json` — the machine-readable record of the theme's parameters; `theme.html` renders it.
- `thumbnail.html` — the project cover (brand mark + swatches).
- `foundations/` — `color.html`, `type.html`, `layout.html` (spacing & elevation), `icons.html`, `image.html`.
- `components/` — `buttons.html`, `forms.html`, `cards.html`, `navigation.html`, `table.html`, `dialog.html`.
- `templates/landing/` and `templates/deck/` — starting points a consuming project copies whole.
- `assets/logo-dark.png`, `assets/logo-light.png` — the wordmark, one file per background band.
- `assets/photo.png` — the reference photograph the imagery page treats.
