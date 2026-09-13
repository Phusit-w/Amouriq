/**
 * amq-pdp-customizer-controls.js
 *
 * Runs inside the Customizer's controls frame (admin side). Backs
 * AMQ_Length_Control (amouriq-single-product.php) — used for every
 * font_size AND stepper control (font size, line-height, letter/word
 * spacing, margin/padding per side): each renders a visible number input +
 * unit <select>, plus one hidden input that is the actual field WordPress
 * core's Customizer JS keeps in sync with the setting (via the
 * `data-customize-setting-link` attribute output by `$this->link()` in that
 * class's render_content()). This script just recombines the two visible
 * inputs into "<number><unit>" (or bare "<number>" when the unit is the
 * empty/unitless option, e.g. line-height) and writes/triggers the hidden
 * input whenever either one changes — core handles propagating that into
 * the actual `wp.customize` setting from there.
 */
(function ($) {
	$(document).on('input change', '.amq-length-control__number, .amq-length-control__unit', function () {
		var $row = $(this).closest('.amq-length-control__row');
		var $number = $row.find('.amq-length-control__number');
		var $unit = $row.find('.amq-length-control__unit');
		var num = $number.val();
		if (num === '' || isNaN(num)) return;

		var combined = parseFloat(num) + $unit.val();
		$row.find('.amq-length-control__combined').val(combined).trigger('change');
	});
})(jQuery);
