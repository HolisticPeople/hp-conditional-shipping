# hp-conditional-shipping

HolisticPeople WordPress plugin: drop-in replacement for Woo Conditional Shipping Pro (ruleset parity + performance).

## 2.0.10

- Expose a public, fail-soft `hp_cs_calculate_order_shipping_discount( WC_Order $order, string $method_title = '', string $surface = 'hp_checkout' )` so consumer plugins (EAO ShipStation metabox) can estimate the standard front-end shipping subsidy for an existing order without reaching into the front-end class. Backed by new public helpers `hp_cs_discount_rule_matches_surface()` and `hp_cs_order_discount_eligible_amount()`; the storefront surface matcher now delegates to the shared helper so admin and checkout stay in lockstep.

## 2.0.9

- Resync imported wc-shipping-discount rules so enabled, minimum amount, and percentage settings stay aligned with the legacy source during checkout migration.

## 2.0.8

- Applies HP Checkout shipping discounts to raw rate arrays that expose costs as `amount`, `cost`, or `rate`.
- Uses HP Checkout-provided item line totals for shipping subsidy eligibility when available.

## 2.0.7

- Adds HP Checkout shipping-rate filter support so conditional restrictions, notices, and shipping discounts apply to the parallel HP Checkout rate flow.
- Adds an HP Checkout surface option for shipping discount rules while preserving existing classic/funnel/both rule behavior during checkout consolidation.

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
