/**
 * Shop archive sidebar — "Filter by Category" / "Filter by attribute"
 * dropdown+arrow-button forms navigate to the selected term's own archive
 * URL on submit (option value = the URL itself). See
 * template-parts/shop-sidebar.php.
 */
document.querySelectorAll('[data-amq-nav-select]').forEach(function (form) {
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var url = form.querySelector('select').value;
		if (url) {
			window.location.href = url;
		}
	});
});
