Status: ready-for-agent

# Spec: Thai / English Language switcher

Language note: owner-facing text and the Thai site stay Thai. Vocabulary follows `CONTEXT.md` (Language switcher, Castile Soap, Scent, Size). Tickets: `issues/01` to `issues/07`.

## Problem Statement

The shop's customers can only read the site in Thai. The owner wants a header button that changes the site from Thai (TH) to English (EN), so that English readers can browse the brand and its products.

Today there is no multilingual system on the live site at all (`/en/` returns 404, and pages declare only Thai). A small "EN / TH" shortcode exists in the code, but it depends on the Polylang plugin, which the live site does not run, and the only header that calls it belongs to the redesign theme that is not active yet. The live header is built by the active theme's header builder and shows nothing.

## Solution

Install a translation system, give the site an English copy of its market pages and products under `/en/`, and show the **Language switcher** in the live header (desktop and mobile). Thai stays the default language at the original addresses, so existing links and search rankings do not change. English text is drafted by Claude and reviewed by the owner before it goes live. The switcher stays hidden on the live site until the owner has reviewed the English text and switches it on.

## User Stories

1. As an English-speaking visitor, I want a visible EN control in the header, so that I can read the site in a language I understand
2. As an English-speaking visitor on a phone, I want the same control in the mobile menu, so that I do not need a desktop to switch
3. As a visitor reading English, I want a TH control, so that I can go back to Thai
4. As a visitor on a translated page, I want EN to open that same page in English, so that I do not lose my place
5. As a visitor on a page with no English version, I want EN to take me to the English home page, so that I never land on an error
6. As a Thai customer, I want the site to open in Thai every time, even if my browser is set to English, so that nothing changes for me
7. As a Thai customer with a saved link or bookmark, I want the original address to keep working, so that my links do not break
8. As the owner, I want the English text reviewed by me before customers see it, so that the brand voice and product claims are correct
9. As the owner, I want the switcher hidden on the live site until I switch it on, so that half-translated pages are never shown
10. As the owner, I want to install the system on the live site myself from a prepared kit with rollback copies, so that I stay in control of changes to the live site
11. As an English-speaking visitor, I want the home page, menu and footer fully in English, so that I can find my way around
12. As an English-speaking visitor, I want the Castile Soap sales page fully in English, so that I understand the product
13. As an English-speaking visitor, I want product names, descriptions, Scent and Size labels in English, so that I can choose what to buy
14. As an English-speaking visitor, I want to add a Size to the cart from the English page with prices in baht, so that ordering works
15. As a Thai customer, I want the Thai pages to look exactly as they do today, fonts included, so that adding English changes nothing for me
16. As the owner, I want the Thai Castile page content left untouched by the install, so that the sales page is not damaged again
17. As the owner, I want search engines to be told which page is the Thai and which the English version, so that each language appears for the right searchers
18. As the owner, I want cart, checkout and Points to keep working in Thai exactly as today, so that revenue is not at risk during this project
19. As the owner, I want the choice of translation system written down with the reasons, so that nobody later swaps it blindly

## Implementation Decisions

- **Language rule:** Thai is the default language at the original addresses; English lives under `/en/`. The site always opens in Thai and changes language only when the customer clicks the switcher, never from the browser's language.
- **Switcher behaviour:** shows the current language as plain text and the other as a link to the same page in that language. Under TranslatePress every page has an `/en/` address (text not yet translated shows in Thai), so there is no "page without a translation" case. The existing shortcode look is kept; only its source of "current language" and "same page in the other language" changes.
- **Placement:** the existing shortcode is placed in the active theme's header builder, in the desktop header and the mobile menu. Nothing is moved to the redesign theme.
- **Font:** unchanged. The live Thai pages load the English font set today (the old language-detection code asked a plugin that is not installed), so every page, Thai and English, keeps loading that set. Owner's decision, 2026-10-17.
- **Translation system:** direction is TranslatePress (text-level translation of rendered pages; works with Elementor and WooCommerce without duplicating pages; free tier covers one extra language). Polylang is the fallback if the check fails. The check and the ADR are ticket 01. The decision is hard to reverse, surprising to a later reader (the old code assumed Polylang) and a real trade-off, so it gets an ADR.
- **CASTILE page:** its stored Elementor data must never be written with the normal post-meta function (it destroyed the content twice). Every change that touches it is verified from a fresh process.
- **Scope of phase one:** the home page, header menu, footer, the CASTILE sales page, all products (all variable: three Scents by three Sizes) and category pages. Cart, checkout, Points, messages written by our own plugins (Deals, Member Privilege, Payment fee), order emails and the shipping label are out of this phase.
- **Customers and money:** prices stay in baht, orders ship within Thailand only. No currency switch, no international address fields.
- **Text authorship:** Claude drafts all English text; the owner reviews every line before it goes live.
- **Live rollout:** the owner installs and uploads by hand from a kit with rollback copies. The switcher ships hidden behind an off switch, and is turned on in the last ticket.

## Testing Decisions

- A good test here looks at the page a customer actually gets: it loads a page, clicks the switcher, and checks the result. It does not check how the plugin stores translations.
- **One seam: the rendered Local site in a real headless Chrome**, driven with raw CDP, the same technique used for earlier tests (hover, click, mobile width). It covers the switcher, the font, fallback, the Thai-first rule, CASTILE integrity and add to cart in both languages. The cart-and-checkout flow is only smoke-tested for not breaking.
- There are no PHP unit tests for this feature: the existing PHP test suite covers Deal and Points logic, which this does not touch. If the switcher's link-choice logic grows beyond a few lines, it may get a small unit test at that point.
- Live checks are read-only: fetch pages and check Thai pages are unchanged, `/en/` answers, alternate-language links are present, and the switcher's visibility matches its switch.

## Out of Scope

- Translating cart, checkout, My Account, Points, order emails, the shipping label, and text written by our own plugins
- Any other language, a currency switch, international shipping or address formats
- Automatic or machine translation shown to customers without owner review
- Guessing the language from the browser
- Moving the live site to the redesign theme (a separate decision, ADR-0002)

## Further Notes

- Correction found in ticket 01: the local plugins folder is not empty. TranslatePress is already there with its tables and settings (Thai default, English under `/en/`), switched off, with 4,228 Thai strings found and only 67 translated. WP-CLI runs on Local when pointed at the database over TCP port 10005 with the `mysqli` extension enabled (see ticket 01 for details). The translations are database content, so ADR-0010 notes they must be carried to live deliberately.
- Findings from the live site on 2026-10-17: `/en/` returns 404; pages declare Thai only; no switcher in the header.
- `CONTEXT.md` already defines **Language switcher**.
