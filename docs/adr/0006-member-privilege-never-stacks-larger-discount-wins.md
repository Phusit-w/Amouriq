# Member Privilege never stacks: the larger discount wins, and it is built in our own code

The owner's promotion brief (2026-10-11) gave Member Privilege (5% for any account, Regular Price items only) conflicting stacking rules across its sections: a spend-threshold Deal should "replace" it, product-pair and quantity-tier Deals should give "the higher", and a Coupon should be both "not combinable" and "used instead when higher". Deals and Coupons, meanwhile, already stack with each other (see Stacking in `CONTEXT.md`).

**Decision**: one rule for the whole site. Member Privilege never adds to any other discount; whichever is larger wins. Against a **Deal** the comparison is per cart line, so a Deal's item gets the Deal while other items still get Member Privilege. Against a **Coupon** it is whole-cart. Deals and Coupons keep stacking with each other as before. The "replace always" wording for spend-threshold Deals was dropped: it could leave a customer with less than 5% when the Deal is small.

**Built in our own code**, beside the Deal engine, rather than on the Points & Rewards plugin's membership levels. That plugin cannot compare per line against Deals or against a Coupon, and tying the pricing rules to a plugin we cannot change would make every later rule change a fight with it. If its membership levels are active on the live site they must be switched off before Member Privilege goes live, or customers get both.

**Consequence**: because guest checkout is off and everyone has an account, Member Privilege is effectively a 5% sitewide discount on Regular Price items (minimum item total ฿299). The owner has confirmed this is intended. The ~20 live Discount Rules must be reviewed before it goes live, since they could also discount the same items.
