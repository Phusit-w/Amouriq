# castile-compare

Screenshots the CASTILE sales page and the Claude Design reference as one PNG per top-level section (24 sections, `00`–`23`), so design and build can be compared section by section.

```
node tools/castile-compare/compare.mjs --out <dir> [--only design,local,live] [--width 1440]
```

- Output: `<dir>/design/`, `<dir>/local/`, `<dir>/live/`, files named `NN-<label>.png`. Pair them by the `NN` prefix. Put `--out` outside the repo (default is the OS temp folder).
- Needs Node 22+ and Google Chrome. Chrome runs headless with a throwaway profile, never the real one.
- Defaults: design = `D:/Local/app/AMOURIQ Lavender Soap Sales Page/AMOURIQ Lavender Sales Page.dc.html`, local = `http://amouriq-backup.local/castile/`, live = `https://amouriq.co.th/castile/`. Override with `--design`, `--local`, `--live`.
- Counters and bars are forced to their final values. The design resets its count-ups to 0 under `prefers-reduced-motion`, so reduced-motion emulation cannot be used for it.
- Build sections are the top-level containers of Elementor page 5309; design sections are `section[data-screen-label]`. If either count changes, the `NN` pairing shifts, so check the console line `N sections`.
