# hp-conditional-shipping

HolisticPeople WordPress plugin: drop-in replacement for Woo Conditional Shipping Pro (ruleset parity + performance).

## 2.0.3

- Opts the conditional shipping rules settings screen into the shared HP-Zen admin runtime.

## 2.0.2

- Restore shipping discounts as a percentage of eligible order total applied to shipping, capped at zero.
- Show an explicit `$0.00` amount for paid carrier methods reduced to zero by discount rules.

## 2.0.1

- Fix shipping discount rules so the percentage discount is applied to each shipping rate cost, not the cart product subtotal.

## 2.0.0

- Major production promotion for the PHP 8.5 baseline, staged conditional-shipping runtime hardening, and HP-owned checkout compatibility.

## 0.4.0

- Add audit/enforce/disabled runtime mode for safe staging activation.
- Add owned shipping-discount rules that can replace wc-shipping-discount configuration.
- Apply conditional shipping rules to HP funnel shipping quote responses through the HP-Funnels filter hook.
- Import legacy wc-shipping-discount rules defensively without assuming optional deduct flags exist.

## 0.3.1

- Prefer package destination data over session customer shipping fields during
  checkout rate filtering so country/postcode/city rules evaluate against the
  current address being quoted.

## Runtime Requirements

- PHP 8.5+
