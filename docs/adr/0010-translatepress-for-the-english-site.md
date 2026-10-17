---
status: accepted
---

# TranslatePress runs the English site, not Polylang

The English copy of the site (Thai default at the original addresses, English under `/en/`) is produced by TranslatePress, which translates the rendered page text. The older `[lang_switcher]` and language-font code assumed Polylang, a plugin that is not installed; that code is moved to TranslatePress's language detection.

**Why not Polylang:** it keeps one copy of every post per language. That would mean duplicating the CASTILE Elementor page (whose stored data has been destroyed twice by the wrong write method) and every variable product, and translating variable products with Polylang needs a paid add-on. TranslatePress leaves Thai posts untouched and stores English text in its own tables.

**Checked on Local (2026-10-17):** with the plugin active, the Thai pages are unchanged, `/en/` answers with `lang="en-US"`, every page declares `hreflang` for both languages, and the CASTILE page's stored Elementor data has the same size and hash before and after activation. The free plugin supports the default language plus one extra, which is all we need (Thai + English); a third language would need the paid version. No licence is installed.

**Consequence:** English text lives in the database (the translation tables), not in code, so it travels with database backups and has to be carried to the live site deliberately.
