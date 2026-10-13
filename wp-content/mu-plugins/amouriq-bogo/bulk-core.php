<?php
/**
 * AMOURIQ BOGO bulk actions on the Deals list, on plain data (no WordPress calls).
 * The admin screen (amouriq-bogo-admin.php) posts the ticked Deal ids and one action.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Apply `$action` ('enable', 'disable' or 'delete') to the Deals whose id is in `$ids`.
 * Deals are matched by id, never by position, so a stale list in another browser tab
 * cannot act on the wrong Deal. Returns array( 'deals' => the new list, 'changed' => how many
 * Deals really changed ). A Deal already in the asked state, an unknown id or an unknown
 * action changes nothing. `$now` ('Y-m-d H:i:s') stamps `updated_at` on a Deal that changed.
 */
function amouriq_bogo_core_bulk( $deals, $ids, $action, $now ) {
	$changed = 0;
	$out     = array();
	foreach ( $deals as $deal ) {
		$picked = isset( $deal['id'] ) && in_array( $deal['id'], $ids, true );
		if ( ! $picked || ! in_array( $action, array( 'enable', 'disable', 'delete' ), true ) ) {
			$out[] = $deal;
			continue;
		}
		if ( 'delete' === $action ) {
			$changed++;
			continue;
		}
		$is_on = ! isset( $deal['enabled'] ) || $deal['enabled'];
		if ( $is_on !== ( 'enable' === $action ) ) {
			$deal['enabled']    = 'enable' === $action;
			$deal['updated_at'] = $now;
			$changed++;
		}
		$out[] = $deal;
	}
	return array( 'deals' => $out, 'changed' => $changed );
}
