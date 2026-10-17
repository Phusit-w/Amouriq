<?php
/**
 * Plugin Name: AMOURIQ: CASTILE page install
 * Description: One-off admin tool (Tools > CASTILE page) that puts the finished CASTILE sales page content on this
 *              site and can put the previous content back. The content ships as amq-castile/page-data.json (the
 *              Elementor data of the page with slug "castile"); it is DB state, so git does not hold it.
 *
 * Why a raw $wpdb write: _elementor_data of this page has been silently destroyed by update_post_meta() more than
 * once (it logged success and dropped fields), so the value is written byte for byte with $wpdb->update() and read
 * straight back. No wp_slash() on this path: slashing belongs only in front of update_post_meta().
 * The first install keeps the content that was live in the postmeta row _amq_castile_data_backup; roll back restores it.
 * Every install also keeps what it is about to replace in _amq_castile_data_prev (when that differs from the new
 * content), so a repeat install never silently drops Elementor edits made since; "Restore content before last install" puts it back.
 */

defined( 'ABSPATH' ) || exit;

// The page data was built on the local copy of the site; the two image URLs inside it carry that host.
const AMQ_CASTILE_LOCAL_ORIGIN = 'http://amouriq-backup.local';
const AMQ_CASTILE_BACKUP_KEY   = '_amq_castile_data_backup';
const AMQ_CASTILE_PREV_KEY     = '_amq_castile_data_prev';

add_action( 'admin_menu', function () {
	add_management_page( 'CASTILE page', 'CASTILE page', 'manage_options', 'amq-castile-page', 'amq_castile_install_screen' );
} );
add_action( 'admin_post_amq_castile_install', function () {
	amq_castile_install_run( 'install' );
} );
add_action( 'admin_post_amq_castile_rollback', function () {
	amq_castile_install_run( 'rollback' );
} );
add_action( 'admin_post_amq_castile_restore_prev', function () {
	amq_castile_install_run( 'restore_prev' );
} );

function amq_castile_install_page_id() {
	$page = get_page_by_path( 'castile' );
	return $page ? (int) $page->ID : 0;
}

function amq_castile_install_read( $id, $key ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, $key ) );
}

function amq_castile_install_shipped() {
	$file = __DIR__ . '/amq-castile/page-data.json';
	if ( ! is_readable( $file ) ) {
		return null;
	}
	$json = file_get_contents( $file );
	if ( false === $json ) {
		return null;
	}
	$home = untrailingslashit( home_url() );
	$json = str_replace(
		array( str_replace( '/', '\/', AMQ_CASTILE_LOCAL_ORIGIN ), AMQ_CASTILE_LOCAL_ORIGIN ),
		array( str_replace( '/', '\/', $home ), $home ),
		$json
	);
	$data = json_decode( $json, true );
	return ( is_array( $data ) && $data ) ? $json : null;
}

/** Store $value in postmeta row $key (one row per post), read it back; true when it reads back identical. */
function amq_castile_install_keep( $id, $key, $value ) {
	global $wpdb;
	if ( null === amq_castile_install_read( $id, $key ) ) {
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $key, 'meta_value' => $value ) );
	} else {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $value ), array( 'post_id' => $id, 'meta_key' => $key ) );
	}
	wp_cache_delete( $id, 'post_meta' );
	return amq_castile_install_read( $id, $key ) === $value;
}

/** Replace the page content with $json (raw), read it back, drop the caches Elementor and the cache plugin keep. */
function amq_castile_install_write( $id, $json ) {
	global $wpdb;
	if ( null === amq_castile_install_read( $id, '_elementor_data' ) ) {
		$ok = $wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => '_elementor_data', 'meta_value' => $json ) );
	} else {
		$ok = false !== $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $json ), array( 'post_id' => $id, 'meta_key' => '_elementor_data' ) );
	}
	wp_cache_delete( $id, 'post_meta' );
	clean_post_cache( $id );
	if ( ! $ok || amq_castile_install_read( $id, '_elementor_data' ) !== $json ) {
		return false;
	}
	delete_post_meta( $id, '_elementor_css' );
	delete_post_meta( $id, '_elementor_element_cache' );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	if ( class_exists( 'WpFastestCache' ) && method_exists( 'WpFastestCache', 'deleteCache' ) ) {
		( new WpFastestCache() )->deleteCache( true );
	}
	return true;
}

function amq_castile_install_run( $action ) {
	global $wpdb;
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'amq_castile_' . $action );

	$id = amq_castile_install_page_id();
	if ( ! $id ) {
		amq_castile_install_back( 'error', 'No page with the slug "castile" was found.' );
	}

	if ( 'install' === $action ) {
		$json = amq_castile_install_shipped();
		if ( null === $json ) {
			amq_castile_install_back( 'error', 'amq-castile/page-data.json is missing or not valid.' );
		}
		$current = amq_castile_install_read( $id, '_elementor_data' );
		if ( null !== $current && $current !== $json ) {
			$first = null === amq_castile_install_read( $id, AMQ_CASTILE_BACKUP_KEY );
			if ( ( $first && ! amq_castile_install_keep( $id, AMQ_CASTILE_BACKUP_KEY, $current ) )
				|| ! amq_castile_install_keep( $id, AMQ_CASTILE_PREV_KEY, $current ) ) {
				amq_castile_install_back( 'error', 'The current content could not be backed up, so nothing was changed.' );
			}
		}
		if ( ! amq_castile_install_write( $id, $json ) ) {
			if ( null !== $current ) {
				amq_castile_install_write( $id, $current );
			}
			amq_castile_install_back( 'error', 'The content did not read back identical; the previous content was put back.' );
		}
		// The theme's hero/title block must stay off for this page; the live page already has this, so only fill a gap.
		if ( ! get_post_meta( $id, 'blocksy_post_meta_options', true ) ) {
			update_post_meta( $id, 'blocksy_post_meta_options', array(
				'disable_featured_image'  => 'yes',
				'disable_share_box'       => 'yes',
				'has_hero_section'        => 'disabled',
				'content_style_source'    => 'custom',
				'vertical_spacing_source' => 'custom',
				'content_area_spacing'    => 'none',
			) );
		}
		amq_castile_install_back( 'ok', 'CASTILE page content installed.' );
	}

	$key    = 'restore_prev' === $action ? AMQ_CASTILE_PREV_KEY : AMQ_CASTILE_BACKUP_KEY;
	$backup = amq_castile_install_read( $id, $key );
	if ( null === $backup ) {
		amq_castile_install_back( 'error', 'There is no backup to restore (the install was never run here).' );
	}
	$current = amq_castile_install_read( $id, '_elementor_data' );
	if ( null !== $current && $current !== $backup && ! amq_castile_install_keep( $id, AMQ_CASTILE_PREV_KEY, $current ) ) {
		amq_castile_install_back( 'error', 'The current content could not be backed up, so nothing was changed.' );
	}
	if ( ! amq_castile_install_write( $id, $backup ) ) {
		amq_castile_install_back( 'error', 'The previous content did not read back identical.' );
	}
	amq_castile_install_back( 'ok', 'Previous CASTILE page content restored.' );
}

function amq_castile_install_back( $status, $message ) {
	wp_safe_redirect( add_query_arg( array( 'page' => 'amq-castile-page', 'amq_status' => $status, 'amq_msg' => rawurlencode( $message ) ), admin_url( 'tools.php' ) ) );
	exit;
}

function amq_castile_install_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$id      = amq_castile_install_page_id();
	$shipped = amq_castile_install_shipped();
	$current = $id ? amq_castile_install_read( $id, '_elementor_data' ) : null;
	$backup  = $id ? amq_castile_install_read( $id, AMQ_CASTILE_BACKUP_KEY ) : null;
	$prev    = $id ? amq_castile_install_read( $id, AMQ_CASTILE_PREV_KEY ) : null;
	$count   = static function ( $json ) {
		$d = null === $json ? null : json_decode( $json, true );
		return is_array( $d ) ? count( $d ) . ' sections, ' . size_format( strlen( $json ) ) : 'none';
	};
	$post = static function ( $action, $label, $disabled = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:12px">';
		echo '<input type="hidden" name="action" value="amq_castile_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'amq_castile_' . $action );
		submit_button( $label, 'install' === $action ? 'primary' : 'secondary', 'submit', false, $disabled ? array( 'disabled' => 'disabled' ) : array() );
		echo '</form>';
	};

	echo '<div class="wrap"><h1>CASTILE page</h1>';
	if ( isset( $_GET['amq_status'], $_GET['amq_msg'] ) ) {
		printf( '<div class="notice notice-%s"><p>%s</p></div>', 'ok' === $_GET['amq_status'] ? 'success' : 'error', esc_html( rawurldecode( wp_unslash( $_GET['amq_msg'] ) ) ) );
	}
	echo '<table class="widefat striped" style="max-width:640px"><tbody>';
	printf( '<tr><td>Page "castile"</td><td>%s</td></tr>', $id ? 'found (ID ' . (int) $id . ')' : 'NOT FOUND' );
	printf( '<tr><td>Content on this site now</td><td>%s</td></tr>', esc_html( $count( $current ) ) );
	printf( '<tr><td>Content in the install file</td><td>%s</td></tr>', esc_html( $count( $shipped ) ) );
	printf( '<tr><td>Same as the install file</td><td>%s</td></tr>', ( $shipped && $current === $shipped ) ? 'yes' : 'no' );
	printf( '<tr><td>Original content (kept at the first install)</td><td>%s</td></tr>', esc_html( $count( $backup ) ) );
	printf( '<tr><td>Content before the last install</td><td>%s</td></tr>', esc_html( $count( $prev ) ) );
	echo '</tbody></table><p>Install keeps the content that is live now (the original once, and the content before each install), then writes the new content. '
		. 'Roll back puts the original back and drops any Elementor edits made after the install; "Restore content before last install" puts back what the last install replaced.</p>';
	$post( 'install', 'Install page content', ! $id || ! $shipped );
	$post( 'restore_prev', 'Restore content before last install', null === $prev );
	$post( 'rollback', 'Roll back to original content', null === $backup );
	echo '</div>';
}
