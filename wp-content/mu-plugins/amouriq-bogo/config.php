<?php
/**
 * AMOURIQ BOGO / bundle deal definitions.
 *
 * Edit this array to add, change, or remove deals — no other file needs to
 * change. The engine that reads this lives in
 * ../amouriq-bogo-deals.php.
 *
 * Every deal is one associative array with these keys:
 *
 *   'type'     (required) one of:
 *       'same_product'           buy X of a product, get Y of THE SAME product
 *                                 at a discount (100% = free).
 *       'product_pair'           buy product A, get product B at a discount.
 *       'category_cheapest_free' buy N items from one category; the cheapest
 *                                 item within each group of N is discounted.
 *
 *   'trigger'  (required) 'auto' (applies automatically once the cart
 *              qualifies) or 'coupon' (only applies while a specific coupon
 *              code is also applied to the cart).
 *   'coupon_code' required when trigger is 'coupon' — a normal WooCommerce
 *              coupon must still exist with this exact code (Marketing >
 *              Coupons). Its own discount/free-shipping settings, if any,
 *              still apply as usual on top of this — this array only adds
 *              the BOGO-style discount, it does not create the coupon itself.
 *   'label'    optional — text shown as the discount line in cart/checkout.
 *
 * Type-specific keys:
 *
 *   same_product:
 *     'product_id'            simple product ID (or a specific variation ID)
 *     'buy_qty'                units that must be bought at full price
 *     'get_qty'                units discounted per group
 *     'get_discount_percent'   0-100, 100 = free (default 100)
 *
 *   product_pair:
 *     'buy_product_id'         triggering product
 *     'get_product_id'         product that receives the discount
 *     'buy_qty'                units of buy_product per trigger (default 1)
 *     'get_qty'                units of get_product discounted per trigger (default 1)
 *     'get_discount_percent'   0-100, 100 = free (default 100)
 *
 *   category_cheapest_free:
 *     'category'               product category slug or ID
 *     'buy_qty'                group size — e.g. 3 means "for every 3 items
 *                               from this category in the cart, the cheapest
 *                               of that group of 3 is discounted" (buy_qty
 *                               already counts the discounted item itself,
 *                               it is not an extra item on top)
 *     'get_discount_percent'   0-100, 100 = free (default 100)
 *
 * Prices used are each product's normal WooCommerce price (as shown on the
 * product page); the discount is applied as one combined negative line at
 * checkout ("Deal discount"), it does not change the price shown per item.
 *
 * Every deal below is commented out — copy one, edit the IDs, and uncomment
 * it to switch a deal on.
 */

return array(

	// Example: buy 2, get 1 of the same product free — always on.
	// array(
	// 	'type'                 => 'same_product',
	// 	'label'                => 'ซื้อ 2 แถม 1',
	// 	'product_id'           => 123,
	// 	'buy_qty'              => 2,
	// 	'get_qty'              => 1,
	// 	'get_discount_percent' => 100,
	// 	'trigger'              => 'auto',
	// ),

	// Example: buy product 10, get product 20 half price — only with coupon PAIRDEAL.
	// array(
	// 	'type'                 => 'product_pair',
	// 	'label'                => 'ซื้อคู่สินค้าลดครึ่งราคา',
	// 	'buy_product_id'       => 10,
	// 	'get_product_id'       => 20,
	// 	'get_discount_percent' => 50,
	// 	'trigger'              => 'coupon',
	// 	'coupon_code'          => 'PAIRDEAL',
	// ),

	// Deal 1: buy any 3 Castile soap items, cheapest one free — always on.
	array(
		'type'                 => 'category_cheapest_free',
		'label'                => 'ครบ 3 ชิ้น หมวดสบู่ ลดชิ้นถูกสุดฟรี',
		'category'             => 'castile-soap',
		'buy_qty'              => 3,
		'get_discount_percent' => 100,
		'trigger'              => 'auto',
	),

);
