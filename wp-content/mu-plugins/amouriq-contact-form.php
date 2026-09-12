<?php
/**
 * [amq_contact_form] — minimal AJAX contact form (First/Last name, Email,
 * Phone, Message) used on the Contact page. Free Elementor has no Form
 * widget, so this is built in the child theme per the project convention
 * and dropped in via a native Shortcode widget.
 *
 * Submits over AJAX to admin-ajax.php, emails wp_mail() to the site contact
 * address, and shows an inline success/error message without a page reload.
 */

if (!defined('ABSPATH')) exit;

define('AMQ_CONTACT_FORM_TO', 'service@amouriq.com');

add_shortcode('amq_contact_form', function ($atts) {
	amq_contact_form_assets_needed();

	$ajax_url = admin_url('admin-ajax.php');
	$nonce    = wp_create_nonce('amq_contact_form');

	ob_start();
	?>
	<form class="amq-contact-form" data-ajax-url="<?php echo esc_url($ajax_url); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" novalidate>
		<div class="amq-contact-form__row">
			<div class="amq-contact-form__field">
				<label for="amq-cf-first-name"><?php esc_html_e('First Name:', 'amouriq'); ?></label>
				<input type="text" id="amq-cf-first-name" name="first_name" required>
			</div>
			<div class="amq-contact-form__field">
				<label for="amq-cf-last-name"><?php esc_html_e('Last Name:', 'amouriq'); ?></label>
				<input type="text" id="amq-cf-last-name" name="last_name" required>
			</div>
		</div>
		<div class="amq-contact-form__row">
			<div class="amq-contact-form__field">
				<label for="amq-cf-email"><?php esc_html_e('Email:', 'amouriq'); ?></label>
				<input type="email" id="amq-cf-email" name="email" required>
			</div>
			<div class="amq-contact-form__field">
				<label for="amq-cf-phone"><?php esc_html_e('Phone:', 'amouriq'); ?></label>
				<input type="tel" id="amq-cf-phone" name="phone">
			</div>
		</div>
		<div class="amq-contact-form__field amq-contact-form__field--full">
			<label for="amq-cf-message"><?php esc_html_e('Message:', 'amouriq'); ?></label>
			<textarea id="amq-cf-message" name="message" rows="6" required></textarea>
		</div>
		<div class="amq-contact-form__honeypot" aria-hidden="true">
			<label for="amq-cf-website"><?php esc_html_e('Website', 'amouriq'); ?></label>
			<input type="text" id="amq-cf-website" name="website" tabindex="-1" autocomplete="off">
		</div>
		<button type="submit" class="amq-contact-form__submit"><?php esc_html_e('Send', 'amouriq'); ?></button>
		<p class="amq-contact-form__notice" role="status" aria-live="polite"></p>
	</form>
	<?php
	return ob_get_clean();
});

function amq_contact_form_handle_submit() {
	check_ajax_referer('amq_contact_form', 'nonce');

	// Honeypot: bots fill hidden fields, humans never see them.
	if (!empty($_POST['website'])) {
		wp_send_json_success(['message' => __('Thank you — your message has been sent.', 'amouriq')]);
	}

	$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
	$last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
	$email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
	$phone      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
	$message    = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

	if ($first_name === '' || $last_name === '' || $message === '' || !is_email($email)) {
		wp_send_json_error(['message' => __('Please fill in your name, a valid email, and a message.', 'amouriq')], 400);
	}

	$subject = sprintf(__('New contact form message from %s', 'amouriq'), trim($first_name . ' ' . $last_name));

	$body_lines = [
		sprintf(__('Name: %s', 'amouriq'), trim($first_name . ' ' . $last_name)),
		sprintf(__('Email: %s', 'amouriq'), $email),
		sprintf(__('Phone: %s', 'amouriq'), $phone !== '' ? $phone : '-'),
		'',
		$message,
	];

	$headers = ['Content-Type: text/plain; charset=UTF-8'];
	if ($email) {
		$headers[] = 'Reply-To: ' . trim($first_name . ' ' . $last_name) . ' <' . $email . '>';
	}

	$sent = wp_mail(AMQ_CONTACT_FORM_TO, $subject, implode("\n", $body_lines), $headers);

	if (!$sent) {
		wp_send_json_error(['message' => __('Sorry, something went wrong sending your message. Please try again.', 'amouriq')], 500);
	}

	wp_send_json_success(['message' => __('Thank you — your message has been sent.', 'amouriq')]);
}
add_action('wp_ajax_amq_contact_form', 'amq_contact_form_handle_submit');
add_action('wp_ajax_nopriv_amq_contact_form', 'amq_contact_form_handle_submit');

function amq_contact_form_assets_needed() {
	static $done = false;
	if ($done) return;
	$done = true;

	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/css/amq-contact-form.css';
		if (!file_exists($path)) return;
		printf(
			'<link rel="stylesheet" id="amq-contact-form-css" href="%s" media="all" />',
			esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-contact-form.css?ver=' . filemtime($path))
		);
	}, 100);

	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/js/amq-contact-form.js';
		if (!file_exists($path)) return;
		printf(
			'<script id="amq-contact-form-js" src="%s"></script>',
			esc_url(get_stylesheet_directory_uri() . '/assets/js/amq-contact-form.js?ver=' . filemtime($path))
		);
	});
}
