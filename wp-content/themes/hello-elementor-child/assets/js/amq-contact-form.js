/**
 * amq-contact-form.js
 *
 * AJAX submit handler for [amq_contact_form] (see
 * wp-content/mu-plugins/amouriq-contact-form.php). Posts to admin-ajax.php,
 * shows an inline success/error message, and resets the form on success.
 */
(function () {
	'use strict';

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form.classList || !form.classList.contains('amq-contact-form')) return;

		e.preventDefault();

		var submit = form.querySelector('.amq-contact-form__submit');
		var notice = form.querySelector('.amq-contact-form__notice');
		var ajaxUrl = form.getAttribute('data-ajax-url');
		var nonce = form.getAttribute('data-nonce');

		var data = new FormData(form);
		data.append('action', 'amq_contact_form');
		data.append('nonce', nonce);

		submit.disabled = true;
		notice.removeAttribute('data-state');
		notice.textContent = '';

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		})
			.then(function (res) { return res.json(); })
			.then(function (res) {
				submit.disabled = false;
				if (res && res.success) {
					notice.setAttribute('data-state', 'success');
					notice.textContent = res.data.message;
					form.reset();
				} else {
					notice.setAttribute('data-state', 'error');
					notice.textContent = (res && res.data && res.data.message) || 'Something went wrong. Please try again.';
				}
			})
			.catch(function () {
				submit.disabled = false;
				notice.setAttribute('data-state', 'error');
				notice.textContent = 'Something went wrong. Please try again.';
			});
	});
})();
