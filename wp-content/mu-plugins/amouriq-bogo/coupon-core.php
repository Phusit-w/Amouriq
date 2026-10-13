<?php
/**
 * AMOURIQ BOGO coupon decision (ADR-0009): what saving or copying a coupon-trigger Deal
 * does with its coupon code, on plain data. No WordPress or WooCommerce functions are
 * called here; the caller (amouriq-bogo-admin.php) performs the result.
 */

defined( 'ABSPATH' ) || exit;

/**
 * $code:   the coupon code as WooCommerce formats it (lower case).
 * $lookup: callable( $code ) returning null when no coupon has that code, else
 *          array( 'id' => int, 'status' => 'publish' | 'draft' | 'trash' | ..., 'owner' => string ).
 *          `owner` says who made the coupon: '' (the owner, by hand), 'points' (a points reward
 *          coupon, one per member) or the id of the Deal that created it.
 * $mode:   'save' (the Deal's own code) or 'copy' (a duplicated Deal needs a fresh code).
 * $deal_id: the Deal being saved ('' for a new one); it may link to a coupon it created itself.
 *
 * Returns one of
 *   array( 'action' => 'create', 'code' => code )             make an empty coupon
 *   array( 'action' => 'link', 'code' => code, 'coupon_id' => id )   use the one that exists
 *   array( 'action' => 'refuse', 'message' => Thai text )       do not save
 *   array( 'action' => 'none' )                                 copy of a Deal with no coupon
 */
function amouriq_bogo_core_coupon_decision( $code, $lookup, $mode, $deal_id = '' ) {
	$code = (string) $code;
	if ( '' === $code ) {
		return 'copy' === $mode ? array( 'action' => 'none' ) : array( 'action' => 'refuse', 'message' => 'เลือกให้ทำงานด้วยคูปองแต่ยังไม่ได้ใส่รหัสคูปอง' );
	}
	if ( ! preg_match( '/^[\p{L}\p{N}\p{M}_.\-]{1,60}$/u', $code ) ) {
		return array( 'action' => 'refuse', 'message' => 'รหัสคูปอง "' . $code . '" ใช้ไม่ได้ ใช้ได้เฉพาะตัวอักษร ตัวเลข - _ . ไม่เว้นวรรค และยาวไม่เกิน 60 ตัว' );
	}

	if ( 'copy' === $mode ) {
		for ( $n = 2; ; $n++ ) {
			if ( null === call_user_func( $lookup, $code . '-' . $n ) ) {
				return array( 'action' => 'create', 'code' => $code . '-' . $n );
			}
		}
	}

	$found = call_user_func( $lookup, $code );
	if ( null === $found ) {
		return array( 'action' => 'create', 'code' => $code );
	}
	if ( 'trash' === $found['status'] ) {
		return array( 'action' => 'refuse', 'message' => 'รหัสคูปอง "' . $code . '" อยู่ในถังขยะ กู้คืนหรือลบถาวรที่ การตลาด > คูปอง ก่อน หรือใช้รหัสอื่น' );
	}
	$owner = isset( $found['owner'] ) ? (string) $found['owner'] : '';
	if ( 'points' === $owner ) {
		return array( 'action' => 'refuse', 'message' => 'รหัสคูปอง "' . $code . '" เป็นคูปองของระบบแต้มสะสม (ออกให้สมาชิกแต่ละคน) ใช้เป็นตัวเปิดดีลไม่ได้ ใช้รหัสอื่น' );
	}
	if ( '' !== $owner && $owner !== (string) $deal_id ) {
		return array( 'action' => 'refuse', 'message' => 'รหัสคูปอง "' . $code . '" เป็นคูปองที่ดีลอื่นสร้างไว้ ใช้ซ้ำไม่ได้เพราะแก้วันหมดอายุของดีลหนึ่งจะกระทบอีกดีล ใช้รหัสอื่น' );
	}
	return array( 'action' => 'link', 'code' => $code, 'coupon_id' => (int) $found['id'] );
}
