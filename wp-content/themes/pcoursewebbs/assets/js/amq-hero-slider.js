/**
 * amq-hero-slider.js
 *
 * Prev/next navigation for the [amq_ingredient_slider] hero/ingredient banner.
 *
 * Migrated from wp-content/mu-plugins/amouriq-hero-slider.php.
 * Loaded conditionally by that mu-plugin only when [amq_ingredient_slider]
 * renders — see amq_hero_slider_assets_needed().
 */
(function(){
function initSlider(el){
	var track = el.querySelector(".amq-hero-slider__track");
	var slides = el.querySelectorAll(".amq-hero-slide");
	var prev = el.querySelector(".amq-hero-slider__arrow--prev");
	var next = el.querySelector(".amq-hero-slider__arrow--next");
	if (!track || !slides.length) return;
	var count = slides.length;
	var index = 0;

	function show(i){
		index = ((i % count) + count) % count;
		track.style.transform = "translateX(-" + (index * 100) + "%)";
	}
	if (prev) prev.addEventListener("click", function(){ show(index - 1); });
	if (next) next.addEventListener("click", function(){ show(index + 1); });
}
document.querySelectorAll("[data-amq-hero-slider]").forEach(initSlider);
})();
