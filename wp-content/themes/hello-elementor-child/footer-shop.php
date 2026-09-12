<?php
/**
 * Footer for WooCommerce archive/shop templates (get_footer('shop')).
 *
 * Static equivalent of the footer baked into Home's Elementor data — see
 * header-shop.php for why this can't just reuse _elementor_data on the
 * Shop page itself. Newsletter band form uses .amq-newsletter-band__*
 * (amq-woocommerce.css) so its styling stays in one place with the
 * Elementor pages' equivalent section.
 *
 * @package HelloElementorChild
 */

if (!defined('ABSPATH')) exit;
?>
<?php if (!function_exists('is_product') || !is_product()) : ?>
<div class="amq-newsletter-band">
	<div class="amq-newsletter-band__inner">
		<h3 class="amq-newsletter-band__heading">Get Promotions &amp; News Before Anyone Else</h3>
		<p class="amq-newsletter-band__subtext1">New customers get &#3647;200 off</p>
		<p class="amq-newsletter-band__subtext2">On orders over &#3647;1,000 &mdash; use code <strong>WELCOME200</strong></p>
		<form class="amq-newsletter-band__form" onsubmit="return false;">
			<input type="email" class="amq-newsletter-band__input" placeholder="Email (name@example.com)" required>
			<button type="submit" class="amq-newsletter-band__submit" aria-label="Subscribe">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
			</button>
		</form>
	</div>
</div>
<?php endif; ?>
<footer id="amq-footer-static" class="amq-shop-footer">
	<div class="amq-shop-footer__grid">
		<div class="amq-shop-footer__col">
			<a href="<?php echo esc_url(home_url('/')); ?>" class="amq-shop-footer__logo">AMOURIQ</a>
		</div>
		<div class="amq-shop-footer__col">
			<h4 class="amq-shop-footer__label">HELP</h4>
			<a href="#">Shipping</a>
			<a href="#">Returns &amp; Exchanges</a>
			<a href="#">Privacy Policy</a>
			<a href="#">Loyalty Points</a>
			<a href="/contact/">Contact Us</a>
		</div>
		<div class="amq-shop-footer__col">
			<h4 class="amq-shop-footer__label">ABOUT US</h4>
			<a href="#">About AMOURIQ</a>
			<a href="#">About Our Castile Soap</a>
		</div>
		<div class="amq-shop-footer__col">
			<h4 class="amq-shop-footer__label">CONNECT</h4>
			<div class="amq-shop-footer__social">
				<a href="#" aria-label="Facebook">f</a>
				<a href="#" aria-label="YouTube">▶</a>
				<a href="#" aria-label="LINE">L</a>
				<a href="#" aria-label="Instagram">IG</a>
			</div>
		</div>
	</div>
	<div class="amq-shop-footer__bottom">
		<span>&copy; <?php echo esc_html(date('Y')); ?> AMOURIQ. All rights reserved.</span>
		<div class="amq-shop-footer__dbd-wrap">
			<a href="https://dbdregistered.dbd.go.th/api/public/shopinfo?param=C2043A9CACFF878503CAF4E3E62273ADF79C62B96A2167F952D7D318BDEF8389" target="_blank" rel="noopener">
				<img src="https://dbdregistered.dbd.go.th/api/public/banner?param=C2043A9CACFF878503CAF4E3E62273ADF79C62B96A2167F952D7D318BDEF8389" alt="DBD Registered" class="amq-shop-footer__dbd">
			</a>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>

</body>
</html>
