/**
 * CASTILE sales page, Section 19 (Buying Block) — ticket 06.
 *
 * State (scent/size/qty) lives in this file; all 9 scent×size combinations'
 * live WooCommerce data (price/SKU/stock/image) is embedded server-side as
 * JSON by Amq_Castile_Buying_Block_Widget::render() — swapping scent/size
 * is a pure client-side lookup, no request, matching the design brief's own
 * "คลิกแล้วราคาเปลี่ยนทันที ไม่ต้องโหลดหน้าใหม่" requirement. Only Add to
 * Cart makes a request, reusing the site's existing `amq_add_to_cart` AJAX
 * action (see amouriq-bestsellers-carousel.php) — same endpoint the Single
 * Product page and the bestsellers carousel already use, so cart behavior
 * (nonce, response shape, wc_fragment_refresh cart-count update) is
 * identical everywhere on the site, not a second parallel implementation.
 */
(function () {
	'use strict';

	var dataEl = document.querySelector('.amq-buying-block__data');
	if (!dataEl) return;

	var data = JSON.parse(dataEl.textContent);
	var ajaxUrl = (window.amqCastileBuyingBlock && window.amqCastileBuyingBlock.ajaxUrl) || '/wp-admin/admin-ajax.php';

	var state = { scent: data.defaultScent, size: data.defaultSize };

	var el = {
		image: document.getElementById('amq-bb-image'),
		name: document.getElementById('amq-bb-name'),
		oils: document.getElementById('amq-bb-oils'),
		price: document.getElementById('amq-bb-price'),
		sku: document.getElementById('amq-bb-sku'),
		qtyInput: document.getElementById('amq-bb-qty'),
		total: document.getElementById('amq-bb-total'),
		addBtn: document.getElementById('amq-bb-add-to-cart'),
		oosMessage: document.getElementById('amq-bb-oos-message'),
		successMessage: document.getElementById('amq-bb-success-message'),
		scentBtns: document.querySelectorAll('.amq-bb-scent-btn'),
		sizeBtns: document.querySelectorAll('.amq-bb-size-btn'),
		stickyBar: document.getElementById('amq-castile-sticky-bar'),
		stickyImage: document.getElementById('amq-sticky-image'),
		stickyName: document.getElementById('amq-sticky-name'),
		stickyPrice: document.getElementById('amq-sticky-price'),
		stickyAddBtn: document.getElementById('amq-sticky-add-to-cart'),
	};

	function currentCombo() {
		return data.combos[state.scent] && data.combos[state.scent][state.size];
	}

	// Matches the ฿-symbol, 2-decimal style wc_price() already renders for
	// el.price/el.stickyPrice (combo.price_html), so "รวม" (Total) reads
	// consistently with the unit price shown just above it, not a
	// differently-formatted number.
	function formatTotal(amount) {
		return '฿' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function updateTotal() {
		var combo = currentCombo();
		if (!el.total || !combo) return;
		var qty = (el.qtyInput && parseInt(el.qtyInput.value, 10)) || 1;
		el.total.textContent = formatTotal(combo.price * qty);
	}

	function render() {
		var combo = currentCombo();
		var inStock = !!(combo && combo.in_stock);

		if (combo) {
			if (el.image) el.image.src = combo.image_url;
			if (el.name) el.name.textContent = combo.name;
			if (el.oils) el.oils.textContent = data.scents[state.scent].oils;
			if (el.price) el.price.innerHTML = combo.price_html;
			if (el.sku) el.sku.textContent = combo.sku;

			if (el.stickyImage) el.stickyImage.src = combo.image_url;
			if (el.stickyName) el.stickyName.textContent = combo.name;
			if (el.stickyPrice) el.stickyPrice.innerHTML = combo.price_html;
		}

		if (el.addBtn) el.addBtn.disabled = !inStock;
		if (el.stickyAddBtn) el.stickyAddBtn.disabled = !inStock;
		if (el.oosMessage) el.oosMessage.hidden = inStock;

		updateTotal();
	}

	function selectScent(scent) {
		state.scent = scent;
		el.scentBtns.forEach(function (btn) {
			btn.classList.toggle('is-selected', btn.getAttribute('data-scent') === scent);
		});
		render();
	}

	function selectSize(size) {
		state.size = size;
		el.sizeBtns.forEach(function (btn) {
			btn.classList.toggle('is-selected', btn.getAttribute('data-size') === size);
		});
		render();
	}

	function addToCart(btn) {
		var combo = currentCombo();
		if (!combo || !combo.in_stock || btn.disabled) return;

		var quantity = (el.qtyInput && parseInt(el.qtyInput.value, 10)) || 1;
		var originalText = btn.textContent;

		btn.disabled = true;
		btn.textContent = '...';

		var body = new URLSearchParams();
		body.set('action', 'amq_add_to_cart');
		body.set('nonce', data.nonce);
		body.set('product_id', combo.product_id);
		body.set('variation_id', combo.variation_id);
		body.set('quantity', quantity);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		})
			.then(function (res) { return res.json(); })
			.then(function (res) {
				if (res && res.success) {
					if (el.successMessage) {
						el.successMessage.hidden = false;
						setTimeout(function () { el.successMessage.hidden = true; }, 2500);
					}
					document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh'));
				} else {
					window.alert((res && res.data && res.data.message) || 'ไม่สามารถเพิ่มสินค้าลงตะกร้าได้');
				}
			})
			.catch(function () {
				window.alert('ไม่สามารถเพิ่มสินค้าลงตะกร้าได้');
			})
			.finally(function () {
				setTimeout(function () {
					btn.disabled = !combo.in_stock;
					btn.textContent = originalText;
				}, 1200);
			});
	}

	function initQuantityStepper() {
		if (!el.qtyInput) return;
		document.querySelectorAll('.amq-buying-block__qty-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var step = parseInt(btn.getAttribute('data-step'), 10) || 0;
				var next = (parseInt(el.qtyInput.value, 10) || 1) + step;
				if (next < 1) next = 1;
				el.qtyInput.value = next;
				updateTotal();
			});
		});
		el.qtyInput.addEventListener('input', updateTotal);
	}

	/**
	 * Sticky mobile cart bar (README, "Interactions & Behavior" #4): appears
	 * below ~860px viewport width, after #hero has scrolled out of view.
	 * Driven by a media query listener plus an IntersectionObserver — never
	 * a cached scroll offset, per that same spec. Ticket 03's Hero section
	 * container has no HTML id (its Advanced-tab "CSS ID" field was never
	 * set), so this targets Elementor's own always-rendered
	 * ".elementor-element-<id>" class instead — same ADR-0003 approach the
	 * rest of this page's supplementary CSS/JS already uses, rather than
	 * retrofitting a real #hero id onto already-committed ticket 03 content.
	 */
	function initStickyBar() {
		if (!el.stickyBar) return;

		var heroEl = document.querySelector('.elementor-element-e03a679');
		var mobileMql = window.matchMedia('(max-width: 860px)');
		var pastHero = false;

		function updateVisibility() {
			var visible = mobileMql.matches && pastHero;
			el.stickyBar.hidden = !visible;
			document.body.classList.toggle('amq-castile-sticky-bar-visible', visible);
		}

		if (heroEl && typeof IntersectionObserver !== 'undefined') {
			var observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					pastHero = !entry.isIntersecting && entry.boundingClientRect.top < 0;
					updateVisibility();
				});
			}, { threshold: 0 });
			observer.observe(heroEl);
		}

		if (typeof mobileMql.addEventListener === 'function') {
			mobileMql.addEventListener('change', updateVisibility);
		} else if (typeof mobileMql.addListener === 'function') {
			mobileMql.addListener(updateVisibility);
		}

		updateVisibility();
	}

	el.scentBtns.forEach(function (btn) {
		btn.addEventListener('click', function () { selectScent(btn.getAttribute('data-scent')); });
	});
	el.sizeBtns.forEach(function (btn) {
		btn.addEventListener('click', function () { selectSize(btn.getAttribute('data-size')); });
	});
	if (el.addBtn) el.addBtn.addEventListener('click', function () { addToCart(el.addBtn); });
	if (el.stickyAddBtn) el.stickyAddBtn.addEventListener('click', function () { addToCart(el.stickyAddBtn); });

	initQuantityStepper();
	initStickyBar();
	render();
})();
