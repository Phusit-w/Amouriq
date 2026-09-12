/**
 * amq-header-hover.js
 *
 * Toggles .is-scrolled on #amq-header past the scroll threshold.
 *
 * Migrated from wp-content/mu-plugins/amouriq-header-hover.php.
 * Loaded sitewide (unconditional) by that mu-plugin — see the wp_footer
 * hook in amouriq-header-hover.php.
 */
(function(){
var header = document.getElementById("amq-header");
if (!header) return;
var threshold = 40;
function onScroll(){
	if (window.scrollY > threshold) { header.classList.add("is-scrolled"); }
	else { header.classList.remove("is-scrolled"); }
}
window.addEventListener("scroll", onScroll, {passive:true});
onScroll();
})();
