# Browser Test Plan for Conditional Shipping Rules

## Test Addresses
- **US baseline**: New York, NY 10001 (123 Main St)
- **International**: Various countries per rule requirements

## Rules Summary (17 enabled)

1. **43729 - 120V appliances – US only**: product 42759, country != US → block DHL/International, show message
2. **43730 - Australia – $700 max**: AU, subtotal > $700 → show message
3. **23437 - Australia no Algae**: AU, products [42355,42323,42595...] → block
4. **23439 - Brazil – $450 max**: BR, subtotal > $450 → show message
5. **23440 - Brazil – 3 items max**: BR, items > 3 → show message
6. **23442 - Canada – no mushrooms**: CA, category → block
7. **43744 - Countries blocked**: GH|MX|NG|RU|UA → block, show message
8. **23438 - Customers blocked – stars an more**: customer_role=community-4, postcode=07512 → block
9. **23443 - DHL restricted – PT, RU, SP, UKR, US, PLO**: PS|PT|RU|ES|UA|US → block DHL/International
10. **23449 - International max price $600**: country != AU|BR|IL|GB|US, subtotal > $600 → show message
11. **23446 - International max weight 10lb**: country != US, weight > 10lb, tag != 6304 → block
12. **23444 - Israel – $480 max**: IL, subtotal > $480 → show message
13. **23445 - Israel – 14 items max**: IL, items > 14 → show message
14. **23447 - Supplements restricted countries**: NO|PT|ES|SE, category → block
15. **23448 - Thailand restricted products**: TH, products [43559,42769...] → block
16. **23450 - United Kingdom – max $460**: GB, category 3262, subtotal > $460 → show message
17. **23452 - USPS Restricted Countries**: CA|DE|SA → block USPS

## Test Execution Status

- [ ] 43729 - 120V appliances – US only
- [ ] 43730 - Australia – $700 max
- [ ] 23437 - Australia no Algae
- [ ] 23439 - Brazil – $450 max
- [ ] 23440 - Brazil – 3 items max
- [ ] 23442 - Canada – no mushrooms
- [ ] 43744 - Countries blocked
- [ ] 23438 - Customers blocked
- [ ] 23443 - DHL restricted
- [ ] 23449 - International max price $600
- [ ] 23446 - International max weight 10lb
- [ ] 23444 - Israel – $480 max
- [ ] 23445 - Israel – 14 items max
- [ ] 23447 - Supplements restricted countries
- [ ] 23448 - Thailand restricted products
- [ ] 23450 - United Kingdom – max $460
- [ ] 23452 - USPS Restricted Countries

