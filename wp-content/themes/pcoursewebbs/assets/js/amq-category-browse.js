/**
 * amq-category-browse.js
 *
 * Arrow click / scroll behavior for the [amq_browse_categories]
 * "Browse by category" carousel.
 *
 * Migrated from wp-content/mu-plugins/amouriq-category-browse.php.
 * Loaded conditionally by that mu-plugin only when [amq_browse_categories]
 * renders — see amq_browse_categories_assets_needed().
 */
(function(){
function initCarousel(el){
	var viewport = el.querySelector(".amq-cat-carousel__viewport");
	var track = el.querySelector(".amq-cat-carousel__track");
	var prev = el.querySelector(".amq-carousel__arrow--prev");
	var next = el.querySelector(".amq-carousel__arrow--next");
	if (!viewport || !track || !prev || !next) return;

	function step(){
		var tile = track.querySelector(".amq-cat-tile");
		if (!tile) return viewport.clientWidth;
		var style = getComputedStyle(track);
		var gap = parseFloat(style.columnGap || style.gap || 0) || 0;
		return tile.getBoundingClientRect().width + gap;
	}
	function updateArrows(){
		var max = viewport.scrollWidth - viewport.clientWidth - 1;
		prev.disabled = viewport.scrollLeft <= 0;
		next.disabled = viewport.scrollLeft >= max;
	}
	prev.addEventListener("click", function(){ viewport.scrollBy({left: -step(), behavior:"smooth"}); });
	next.addEventListener("click", function(){ viewport.scrollBy({left: step(), behavior:"smooth"}); });
	viewport.addEventListener("scroll", updateArrows, {passive:true});
	window.addEventListener("resize", updateArrows);
	updateArrows();
}
document.querySelectorAll("[data-amq-cat-carousel]").forEach(initCarousel);
})();
