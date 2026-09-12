<?php
/**
 * Minimal stand-in for WC_Product / WC_Product_Variable, exposing only the
 * methods amq_castile_resolve_selection() calls. The $variations shape
 * mirrors WooCommerce's own get_available_variations() output, trimmed to
 * the keys the resolver reads.
 */
final class FakeWcProduct {
	/** @var int */
	private $id;
	/** @var string */
	private $name;
	/** @var int|null */
	private $image_id;
	/** @var array[] */
	private $variations;

	public function __construct($id, $name, $image_id, array $variations) {
		$this->id = $id;
		$this->name = $name;
		$this->image_id = $image_id;
		$this->variations = $variations;
	}

	public function is_type($type) {
		return $type === 'variable';
	}

	public function get_id() {
		return $this->id;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_image_id() {
		return $this->image_id;
	}

	public function get_available_variations() {
		return $this->variations;
	}
}
