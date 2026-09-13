<?php
/**
 * See amouriq-castile-buying-block.php for the registration/enqueue side of
 * this widget. This file is the widget class itself, kept separate per
 * Elementor's own convention (widgets/*.php, one class per file) even though
 * this site otherwise favors one-file-per-feature mu-plugins.
 */

if (!defined('ABSPATH')) exit;

class Amq_Castile_Buying_Block_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'amq-castile-buying-block';
	}

	public function get_title() {
		return __('AMOURIQ CASTILE — Buying Block', 'amouriq');
	}

	public function get_icon() {
		return 'eicon-product-add-to-cart';
	}

	public function get_categories() {
		return ['general'];
	}

	public function get_keywords() {
		return ['castile', 'buying block', 'add to cart', 'woocommerce'];
	}

	/**
	 * No register_controls() override: every field this widget shows is
	 * live WooCommerce data (price/SKU/stock/image) or hardcoded marketing
	 * copy taken verbatim from the design handoff, not a static field an
	 * Elementor editor should be tweaking per-page — see
	 * docs/adr/0001-castile-sales-page-built-in-elementor.md.
	 */

	/**
	 * One deliberate omission versus the design brief's own Section 19 copy:
	 * the brief includes a disclosure line stating the 100/250 ML packshots
	 * reuse the 500 ML bottle image as an interim stand-in. That's true of
	 * the design prototype, but not of this build — every combo below uses
	 * its own real per-variation WooCommerce image (see ticket 06 comments,
	 * ".scratch/castile-sales-page/issues/06-buying-block-and-add-to-cart.md").
	 * Adding that sentence here would be a false statement about this page,
	 * not a faithful copy of the design.
	 */
	protected function render() {
		$scents = amq_castile_buying_block_scents();
		$sizes = amq_castile_buying_block_sizes();

		$combos = [];
		foreach (array_keys($scents) as $scent_key) {
			$combos[$scent_key] = [];
			foreach (array_keys($sizes) as $size_key) {
				$combos[$scent_key][$size_key] = amq_castile_resolve_selection($scent_key, $size_key);
			}
		}

		$default_scent = 'lavender';
		$default_size = '100';
		$default = $combos[$default_scent][$default_size];

		$data = [
			'scents' => $scents,
			'sizes' => $sizes,
			'combos' => $combos,
			'defaultScent' => $default_scent,
			'defaultSize' => $default_size,
			'nonce' => wp_create_nonce('amq_add_to_cart'),
		];
		?>
		<div class="amq-buying-block" id="buy">
			<script type="application/json" class="amq-buying-block__data"><?php echo wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

			<div class="amq-buying-block__image-col">
				<img src="<?php echo esc_url($default ? $default['image_url'] : ''); ?>" alt="<?php echo esc_attr($default ? $default['name'] : ''); ?>" id="amq-bb-image">
			</div>

			<div class="amq-buying-block__info-col">
				<p class="amq-buying-block__brand">AMOURIQ</p>
				<h2 class="amq-buying-block__name" id="amq-bb-name"><?php echo esc_html($default ? $default['name'] : ''); ?></h2>
				<p class="amq-buying-block__tag">100% REAL SOAP†</p>
				<p class="amq-buying-block__triad"><strong>MOISTURE ↑</strong> ผิวชุ่มชื้นเพิ่มขึ้น · <strong>WATER LOSS ↓</strong> การสูญเสียน้ำลดลง · <strong>BARRIER-FRIENDLY</strong> เป็นมิตรต่อเกราะป้องกันผิว</p>
				<p class="amq-buying-block__oils" id="amq-bb-oils"><?php echo esc_html($scents[$default_scent]['oils']); ?></p>

				<div class="amq-buying-block__divider"></div>

				<div class="amq-buying-block__scent">
					<h3 class="amq-buying-block__section-heading">กลิ่น</h3>
					<div class="amq-buying-block__scent-row">
						<?php foreach ($scents as $scent_key => $scent) : ?>
							<button
								type="button"
								class="amq-bb-scent-btn<?php echo $scent_key === $default_scent ? ' is-selected' : ''; ?>"
								data-scent="<?php echo esc_attr($scent_key); ?>"
							>
								<span class="amq-bb-scent-btn__name"><?php echo esc_html($scent['name']); ?></span>
								<span class="amq-bb-scent-btn__note"><?php echo esc_html($scent['note']); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="amq-buying-block__size">
					<h3 class="amq-buying-block__section-heading">ขนาด &amp; ราคา</h3>
					<div class="amq-buying-block__size-row">
						<?php foreach ($sizes as $size_key => $label) :
							// Price is identical across scents (only SKU/image differ by
							// scent) — showing the default scent's combo price per size
							// button is accurate regardless of which scent ends up
							// selected, so this line never needs a JS update. This is a
							// documented catalog convention, not code-enforced: the three
							// scents are three independent WooCommerce products (2278,
							// 2285, 2293 — see amouriq-castile-resolver.php), each with
							// its own price in wp-admin. If a scent is ever repriced
							// on its own, this button would silently show the wrong
							// price for the other two scents' combos.
							$size_price = $combos[$default_scent][$size_key];
							?>
							<button
								type="button"
								class="amq-bb-size-btn<?php echo $size_key === $default_size ? ' is-selected' : ''; ?>"
								data-size="<?php echo esc_attr($size_key); ?>"
							>
								<span class="amq-bb-size-btn__label"><?php echo esc_html($label); ?></span>
								<span class="amq-bb-size-btn__price"><?php echo $size_price ? wp_kses_post($size_price['price_html']) : ''; ?></span>
							</button>
						<?php endforeach; ?>
					</div>
					<p class="amq-buying-block__price-note">ราคาเท่ากันทุกกลิ่นในไซส์เดียวกัน — Lavender / Rosemary / Rose Geranium</p>
					<div class="amq-buying-block__price" id="amq-bb-price"><?php echo $default ? wp_kses_post($default['price_html']) : ''; ?></div>
					<p class="amq-buying-block__sku">SKU: <span id="amq-bb-sku"><?php echo esc_html($default ? $default['sku'] : ''); ?></span></p>
				</div>

				<div class="amq-buying-block__purchase-row">
					<div class="amq-buying-block__qty">
						<span class="amq-buying-block__qty-label">จำนวน</span>
						<div class="amq-buying-block__qty-controls">
							<button type="button" class="amq-buying-block__qty-btn" data-step="-1" aria-label="<?php esc_attr_e('Decrease quantity', 'amouriq'); ?>">&minus;</button>
							<input type="number" class="amq-buying-block__qty-input" id="amq-bb-qty" value="1" min="1" step="1" aria-label="<?php esc_attr_e('Quantity', 'amouriq'); ?>">
							<button type="button" class="amq-buying-block__qty-btn" data-step="1" aria-label="<?php esc_attr_e('Increase quantity', 'amouriq'); ?>">+</button>
						</div>
					</div>
					<div class="amq-buying-block__total">
						<span class="amq-buying-block__total-label">รวม</span>
						<span class="amq-buying-block__total-value" id="amq-bb-total"><?php echo $default ? wp_kses_post(wc_price($default['price'])) : ''; ?></span>
					</div>
					<button type="button" class="amq-buying-block__add-to-cart" id="amq-bb-add-to-cart">เพิ่มลงตะกร้า</button>
				</div>
				<p class="amq-buying-block__oos-message" id="amq-bb-oos-message" hidden>ขนาดนี้หมดชั่วคราว กรุณาเลือกขนาดอื่น</p>
				<p class="amq-buying-block__success-message" id="amq-bb-success-message" hidden>เพิ่มลงตะกร้าแล้ว</p>

				<p class="amq-buying-block__trust-chips">DermX Tested · Thai FDA Notified · Evidence Available</p>

				<?php
				/**
				 * Only "ดูผลการทดสอบ" has a real target on this page
				 * (Section 16, Evidence). The brief's other two secondary
				 * links have no destination anywhere on the site yet: full
				 * ingredients lists live per-product on the Single Product
				 * page (not on this sales page), and shipping info is an
				 * explicitly open item in the design brief itself
				 * ("shipping info ยังไม่มีข้อมูลยืนยันในไฟล์ — คงเป็น
				 * placeholder"). Left as inert "#" rather than pointed at
				 * #faq as a placeholder target — that would silently send
				 * customers to unrelated content instead of doing nothing.
				 */
				?>
				<div class="amq-buying-block__secondary-links">
					<a href="#">ดูส่วนประกอบทั้งหมด</a>
					<a href="#evidence">ดูผลการทดสอบ</a>
					<a href="#">ข้อมูลการจัดส่ง</a>
				</div>
			</div>
		</div>

		<div class="amq-castile-sticky-bar" id="amq-castile-sticky-bar" hidden>
			<img src="<?php echo esc_url($default ? $default['image_url'] : ''); ?>" alt="" id="amq-sticky-image">
			<div class="amq-castile-sticky-bar__info">
				<span id="amq-sticky-name"><?php echo esc_html($default ? $default['name'] : ''); ?></span>
				<span id="amq-sticky-price"><?php echo $default ? wp_kses_post($default['price_html']) : ''; ?></span>
			</div>
			<button type="button" class="amq-castile-sticky-bar__add-to-cart" id="amq-sticky-add-to-cart">เพิ่มลงตะกร้า</button>
		</div>
		<?php
	}
}
