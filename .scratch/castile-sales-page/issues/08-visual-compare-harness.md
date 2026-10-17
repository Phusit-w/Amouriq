# 08: Visual compare harness (design vs live page)

**What to build:** One repeatable command that screenshots the CASTILE page (local and live amouriq.co.th) and the Claude Design reference (`AMOURIQ Lavender Sales Page.dc.html`), and cuts each into per-section images so design and build can be laid side by side. Animations are disabled so counters and bars show their final values. This becomes the acceptance yardstick for every later ticket in this round.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] One command produces, for each of design / local / live, a set of per-section crops at desktop width
- [x] Count-up numbers and bar fills show final values in all three (design included)
- [x] Uses a throwaway Chrome profile, never the user's real one
- [x] Output lands in a scratch location, not committed to the repo
- [x] Short usage note recorded so later tickets can reuse it

## Comments

- Built as `tools/castile-compare/compare.mjs` (raw CDP, Node global WebSocket, no dependencies), usage in the README beside it. Run for design, local and live: 24 sections each.
- Two traps found while making counters final: the design's chart sequencer only fires for rows near the viewport centre (so the script scrolls in 200px steps), and it resets count-ups to 0 under `prefers-reduced-motion` (so reduced-motion emulation gives zeros). Capturing beyond the viewport also re-fires scroll triggers mid-capture, so `requestAnimationFrame` is patched to finish tweens instantly and transitions are switched off. Checked on section 06: final values +0.81 / −5.34 / −3.60 / −10.67%.
- The earlier ad-hoc approach (a tall `--screenshot` window cropped with System.Drawing) is replaced by this.
