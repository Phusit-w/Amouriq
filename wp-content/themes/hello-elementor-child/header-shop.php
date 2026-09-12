<?php
/**
 * Header for WooCommerce archive/shop templates (get_header('shop')).
 *
 * The Shop page is rendered through WooCommerce's archive-product.php, not
 * page.php, so it never sees Home's Elementor-authored #amq-header. This is
 * a static, hand-coded equivalent (same nav items/logo/icons/lang-switcher
 * as #amq-header-static on Contact) so /shop/ has a working header without
 * needing a sitewide HFE system (see memo/learning.md).
 *
 * @package HelloElementorChild
 */

if (!defined('ABSPATH')) exit;

$viewport_content = apply_filters('hello_elementor_viewport_content', 'width=device-width, initial-scale=1');
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="<?php echo esc_attr($viewport_content); ?>">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header id="amq-header-static" class="amq-shop-header">
	<a href="<?php echo esc_url(home_url('/')); ?>" class="amq-shop-header__logo">AMOURIQ</a>

	<nav class="amq-shop-header__nav amq-header-nav">
		<a href="/shop/">SHOP</a>
		<a href="/blog/">BLOG</a>
		<a href="/about/">ABOUT</a>
		<a href="/contact/">CONTACT</a>
	</nav>

	<div class="amq-shop-header__actions">
		<button type="button" class="amq-header-icon amq-search-toggle" aria-label="Search" aria-expanded="false">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7.2"></circle><line x1="21" y1="21" x2="16.4" y2="16.4"></line></svg>
		</button>
		<?php echo do_shortcode('[lang_switcher]'); ?>
		<a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="amq-header-icon" aria-label="My account">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.2"></circle><circle cx="12" cy="10.3" r="2.9"></circle><path d="M6.8 18.2c1.1-2.4 2.9-3.7 5.2-3.7s4.1 1.3 5.2 3.7"></path></svg>
		</a>
		<a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="amq-header-icon" aria-label="Cart">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 8h13l-1.1 11.3a1.6 1.6 0 0 1-1.6 1.4H8.2a1.6 1.6 0 0 1-1.6-1.4L5.5 8Z"></path><path d="M9 8V6.6a3 3 0 0 1 6 0V8"></path></svg>
		</a>
	</div>

	<div class="amq-search-panel">
		<form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="amq-search-panel__form">
			<span class="amq-search-panel__icon">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7.2"></circle><line x1="21" y1="21" x2="16.4" y2="16.4"></line></svg>
			</span>
			<input type="search" name="s" placeholder="Search" autocomplete="off">
			<button type="button" class="amq-search-panel__close" aria-label="Close search">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="5" x2="19" y2="19"></line><line x1="19" y1="5" x2="5" y2="19"></line></svg>
			</button>
		</form>
	</div>
</header>
