# hp-conditional-shipping

HolisticPeople WordPress plugin: drop-in replacement for Woo Conditional Shipping Pro (ruleset parity + performance).

## 0.4.0

- Add audit/enforce/disabled runtime mode for safe staging activation.
- Add owned shipping-discount rules that can replace wc-shipping-discount configuration.
- Apply conditional shipping rules to HP funnel shipping quote responses through the HP-Funnels filter hook.
- Import legacy wc-shipping-discount rules defensively without assuming optional deduct flags exist.

## 0.3.1

- Prefer package destination data over session customer shipping fields during
  checkout rate filtering so country/postcode/city rules evaluate against the
  current address being quoted.














