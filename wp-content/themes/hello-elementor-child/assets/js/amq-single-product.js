/**
 * amq-single-product.js
 *
 * Behavior for the Single Product page (woocommerce/single-product.php):
 * gallery thumbnail swap, size-box selection (price/variation sync), a
 * quantity stepper, and AJAX add-to-cart. Loaded only on is_product() —
 * see amouriq-single-product.php.
 *
 * The "You might also like" related-products row further down the page is
 * a `.amq-carousel` rendered by amq_render_product_carousel() (same
 * function [amq_bestsellers] uses on Home) and is wired up separately by
 * amq-bestsellers-carousel.js, which is loaded alongside this file whenever
 * that carousel renders.
 */
(function () {
	var ajaxUrl = (window.amqSingleProduct && window.amqSingleProduct.ajaxUrl) || '/wp-admin/admin-ajax.php';

	function initGallery() {
		var mainImage = document.getElementById('amq-pdp-main-image');
		var thumbs = document.querySelectorAll('.amq-pdp__thumb');
		if (!mainImage || !thumbs.length) return;

		thumbs[0].classList.add('is-active');

		thumbs.forEach(function (thumb) {
			thumb.addEventListener('click', function () {
				var fullSrc = thumb.getAttribute('data-full-src');
				if (!fullSrc) return;
				mainImage.src = fullSrc;
				thumbs.forEach(function (t) { t.classList.remove('is-active'); });
				thumb.classList.add('is-active');
			});
		});
	}

	function initSizeBoxes() {
		var boxes = document.querySelectorAll('.amq-size-box');
		var priceEl = document.querySelector('.amq-pdp__price');
		var addBtn = document.querySelector('.amq-pdp__add-to-bag');
		if (!boxes.length) return;

		boxes.forEach(function (box) {
			box.addEventListener('click', function () {
				boxes.forEach(function (b) { b.classList.remove('is-selected'); });
				box.classList.add('is-selected');

				var variationId = box.getAttribute('data-variation-id');
				var priceHtml = box.getAttribute('data-price-html');
				if (priceEl && priceHtml) priceEl.innerHTML = priceHtml;
				if (addBtn && variationId) addBtn.setAttribute('data-variation-id', variationId);
			});
		});
	}

	function initQuantityStepper() {
		var wrap = document.querySelector('.amq-pdp__qty');
		if (!wrap) return;
		var input = wrap.querySelector('.amq-pdp__qty-input');
		if (!input) return;

		wrap.querySelectorAll('.amq-pdp__qty-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var step = parseInt(btn.getAttribute('data-step'), 10) || 0;
				var next = (parseInt(input.value, 10) || 1) + step;
				if (next < 1) next = 1;
				input.value = next;
			});
		});
	}

	function initAccordions() {
		document.querySelectorAll('[data-amq-accordion]').forEach(function (accordion) {
			var toggle = accordion.querySelector('.amq-pdp__accordion-toggle');
			if (!toggle) return;
			toggle.addEventListener('click', function () {
				var isOpen = accordion.classList.toggle('is-open');
				toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			});
		});
	}

	function initAddToBag() {
		var row = document.querySelector('.amq-pdp__purchase-row');
		var btn = document.querySelector('.amq-pdp__add-to-bag');
		var qtyInput = document.querySelector('.amq-pdp__qty-input');
		if (!row || !btn) return;

		var nonce = row.getAttribute('data-nonce') || '';

		btn.addEventListener('click', function () {
			if (btn.disabled) return;
			var productId = btn.getAttribute('data-product-id');
			var variationId = btn.getAttribute('data-variation-id') || '';
			var quantity = (qtyInput && parseInt(qtyInput.value, 10)) || 1;
			var originalText = btn.textContent;

			btn.disabled = true;
			btn.textContent = '...';

			var body = new URLSearchParams();
			body.set('action', 'amq_add_to_cart');
			body.set('nonce', nonce);
			body.set('product_id', productId);
			body.set('variation_id', variationId);
			body.set('quantity', quantity);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			})
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (data && data.success) {
						btn.textContent = 'ADDED';
						btn.classList.add('is-added');
						document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh'));
					} else {
						btn.textContent = originalText;
						window.alert((data && data.data && data.data.message) || 'Could not add this product to your bag.');
					}
				})
				.catch(function () {
					btn.textContent = originalText;
				})
				.finally(function () {
					setTimeout(function () {
						btn.disabled = false;
						btn.textContent = originalText;
						btn.classList.remove('is-added');
					}, 1600);
				});
		});
	}

	initGallery();
	initSizeBoxes();
	initQuantityStepper();
	initAccordions();
	initAddToBag();
})();
