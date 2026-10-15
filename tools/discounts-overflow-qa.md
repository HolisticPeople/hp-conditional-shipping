# Conditions discounts-table overflow repair

Owner: hp-conditional-shipping. HP-Zen remains the existing admin palette/shell
provider (v1.3 consumer). No shipping-settings, mode or rule mutation is needed.

Base: e3162fd, plugin2.0.14. Candidate:2.0.15. Relevant guide: hp-zen-it at
HP-Codex-Skills0a1dce3482773f478c970b3bb9524adc3880b60d, structural composition
and tablet-first sections; shipping-authority handover preserves business ownership.
The development routes have no separate Conditional Shipping guide; existing
entry/current-state pointers were read. This is a plugin-specific layout repair.

## Root cause and contract

The nine-column nested table contributes its minimum intrinsic width to the
outer form table, expanding the desktop document to1949px at a1440px viewport.
WordPress additionally stacks nested form-table descendants at mobile widths,
turning each discount header into a table-wide block.

Constrain the outer settings table with fixed layout; retain a full-width local
overflow scroller with a named region and keyboard focus. Preserve semantic
column display inside the editor; stack only outer section labels/cells through
1023px. Limit the runtime-mode select to available width. Keep fields/submit
names, current values, selected options and save flow intact.

The wrapper is transparent with no added decoration. Existing page shell and
form card retain their gutter responsibilities; nested data-table decoration is
a functional table exception. On phone the scroller is274px wide with58px total
inset per side. All nine columns are reachable without document scrolling.
No palette/tone change or cross-plugin selectors were added.

## Browser evidence

Authenticated staging candidate injection at834x1194,390x844,1440x1000 and
3840x2160 plus767/768/1023/1024px boundary probes: document width exactly equals
viewport width; last column reachable; native keyboard ArrowRight advances the
scroller; all nine headings present; field values/selections unchanged after
resizing and scrolling. No form submission or fixture creation.

Captures/results: C:/DEV/_qa/shipping-discounts-candidate-*.png and
shipping-discounts-candidate-results.json. Known unrelated staging null-append
page errors are recorded separately; geometry and value-preservation checks pass.
Actual served2.0.15 markup and CSS must be rechecked after deployment.
Physical touch acceptance and production approval remain separate.
