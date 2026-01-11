# Browser Test Results - Conditional Shipping Rules

## Test Date
2025-01-28

## Test Address
- **US Baseline**: New York, NY 10001 (123 Main St)
- **International**: Various per rule requirements

## Results

| Rule ID | Title | Status | Notes |
|---------|-------|--------|-------|
| 43729 | 120V appliances – US only | Pending | |
| 43730 | Australia – $700 max | Pending | |
| 23437 | Australia no Algae | Pending | |
| 23439 | Brazil – $450 max | ⚠️ CONFLICT | Cannot test independently - Rule 23440 (Brazil 3 items max) blocks first when shipping to Brazil with 4 items. Rule 23439 requires cart > $450 to trigger. Would need cart < 3 items AND > $450 to test, which is not feasible with current cart setup. |
| 23440 | Brazil – 3 items max | ✅ PASS | BR address (São Paulo, SP, 01310-100): With 4 items, error message displays correctly "Brazil restricts the import of supplement items. Please limit your cart to 3 items or fewer...", 0 shipping methods (expected: blocked). Rule works correctly. |
| 23442 | Canada – no mushrooms | Pending | |
| 43744 | Countries blocked | ✅ PASS | MX address (Ciudad de México, 01000): Error message displays correctly "Unfortunately, we are unable to ship to the selected country at this time. Please choose a different destination.", 0 shipping methods (expected: blocked) |
| 23438 | Customers blocked | ⏭️ SKIP | Requires customer_role condition - skipped per user request |
| 23443 | DHL restricted | ✅ PASS | US address (NY, 10001): DHL methods blocked correctly (hasDHL: false), UPS methods still available. Rule works as expected. |
| 23449 | International max price $600 | Pending | |
| 23446 | International max weight 10lb | Pending | |
| 23444 | Israel – $480 max | Pending | |
| 23445 | Israel – 14 items max | Pending | |
| 23447 | Supplements restricted countries | ✅ PASS | NO address (Oslo, 0160): Error message displays correctly "The chosen destination country restricts the import of supplements. Please remove supplements from your cart, select a different shipping destination, or contact us for assistance.", 0 shipping methods (expected: blocked). Rule works correctly. |
| 23448 | Thailand restricted products | Pending | |
| 23450 | United Kingdom – max $460 | ✅ PARTIAL | GB address (London, SW1A 1AA): With $116 cart (under $460 threshold), no $460 error message appears (expected: should not block). Rule works correctly for non-blocking case. Full blocking test would require cart > $460. |
| 23452 | USPS Restricted Countries | ✅ PASS | CA address (Toronto, ON, M5V1E3): USPS methods blocked correctly (hasUSPS: false), UPS methods still available. Rule works as expected. |
