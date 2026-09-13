/**
 * amq-bestsellers-carousel.js
 *
 * Behavior for the [amq_bestsellers] "Our Most Loved" product carousel:
 * arrow scroll, size-dropdown price swap, AJAX add-to-cart, and a
 * decorative (non-persisted) bookmark toggle.
 *
 * Loaded conditionally by wp-content/mu-plugins/amouriq-bestsellers-carousel.php
 * only when [amq_bestsellers] renders — see amq_bestsellers_assets_needed().
 */
(function () {
	var scriptTag = document.getElementById('amq-bestsellers-carousel-js');
	var ajaxUrl = scriptTag ? scriptTag.getAttribute('data-ajax-url') : '/wp-admin/admin-ajax.php';

	function initCarouselScroll(el) {
		var viewport = el.querySelector('.amq-carousel__viewport');
		var track = el.querySelector('.amq-carousel__track');
		var prev = el.querySelector('.amq-carousel__arrow--prev');
		var next = el.querySelector('.amq-carousel__arrow--next');
		if (!viewport || !track) return;

		function step() {
			var slide = track.querySelector('.amq-carousel__slide');
			if (!slide) return viewport.clientWidth;
			var style = getComputedStyle(track);
			var gap = parseFloat(style.columnGap || style.gap || 0) || 0;
			return slide.getBoundingClientRect().width + gap;
		}
		function updateArrows() {
			if (!prev || !next) return;
			var max = viewport.scrollWidth - viewport.clientWidth - 1;
			prev.disabled = viewport.scrollLeft <= 0;
			next.disabled = viewport.scrollLeft >= max;
		}
		if (prev) prev.addEventListener('click', function () { viewport.scrollBy({ left: -step(), behavior: 'smooth' }); });
		if (next) next.addEventListener('click', function () { viewport.scrollBy({ left: step(), behavior: 'smooth' }); });
		if (prev || next) {
			viewport.addEventListener('scroll', updateArrows, { passive: true });
			window.addEventListener('resize', updateArrows);
			window.addEventListener('load', updateArrows);
			updateArrows();
		}
	}

	// Badge text and ADD TO BAG label are shared across every card, sourced
	// from two hidden Elementor widgets (Text Editor / Button) placed next
	// to the [amq_bestsellers] shortcode on the page — see the PHP/CSS for
	// why (Elementor free has no per-loop-item widget).
	function readTemplateText() {
		var badgeEl = document.querySelector('.amq-bestsellers-badge-template');
		var ctaEl = document.querySelector('.amq-bestsellers-cta-template');
		var badgeText = badgeEl ? badgeEl.textContent.trim() : '';
		var ctaText = '';
		if (ctaEl) {
			var ctaLabel = ctaEl.querySelector('.elementor-button-text');
			ctaText = (ctaLabel ? ctaLabel.textContent : ctaEl.textContent).trim();
		}
		return { badgeText: badgeText, ctaText: ctaText };
	}

	function applyTemplateText(el, tpl) {
		if (tpl.badgeText) {
			el.querySelectorAll('.amq-product-card__badge').forEach(function (badge) {
				badge.textContent = tpl.badgeText;
			});
		}
		if (tpl.ctaText) {
			el.querySelectorAll('.amq-product-card__add-to-bag').forEach(function (btn) {
				btn.textContent = tpl.ctaText;
			});
		}
	}

	function initSizeSelect(card) {
		var select = card.querySelector('.amq-product-card__size-select');
		var priceEl = card.querySelector('.amq-product-card__price');
		var addBtn = card.querySelector('.amq-product-card__add-to-bag');
		if (!select || !priceEl) return;

		var variations = {};
		try { variations = JSON.parse(select.getAttribute('data-variations') || '{}'); } catch (e) {}

		select.addEventListener('change', function () {
			var variationId = select.value;
			if (variations[variationId]) {
				priceEl.innerHTML = variations[variationId];
			}
			if (addBtn) addBtn.setAttribute('data-variation-id', variationId);
		});
	}

	function initAddToBag(card, nonce) {
		var btn = card.querySelector('.amq-product-card__add-to-bag');
		if (!btn) return;

		btn.addEventListener('click', function () {
			if (btn.disabled) return;
			var productId = btn.getAttribute('data-product-id');
			var variationId = btn.getAttribute('data-variation-id') || '';
			var originalText = btn.textContent;

			btn.disabled = true;
			btn.textContent = '...';

			var body = new URLSearchParams();
			body.set('action', 'amq_add_to_cart');
			body.set('nonce', nonce);
			body.set('product_id', productId);
			body.set('variation_id', variationId);

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

	function initBookmark(card) {
		var btn = card.querySelector('.amq-product-card__bookmark');
		if (!btn) return;
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var pressed = btn.getAttribute('aria-pressed') === 'true';
			btn.setAttribute('aria-pressed', pressed ? 'false' : 'true');
		});
	}

	var templateText = readTemplateText();

	document.querySelectorAll('[data-amq-carousel]').forEach(function (el) {
		initCarouselScroll(el);
		applyTemplateText(el, templateText);
		var nonce = el.getAttribute('data-nonce') || '';
		el.querySelectorAll('.amq-product-card').forEach(function (card) {
			initSizeSelect(card);
			initAddToBag(card, nonce);
			initBookmark(card);
		});
	});
})();
