<?php
/**
 * The shipping label as a PDF file (the Download button). Uses the dompdf library that the
 * "Print Invoices, Packing Slips, Labels" plugin ships with, so no new library is installed, and
 * the Sarabun font in ./fonts (SIL Open Font License) because dompdf's own fonts have no Thai letters.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The PDF bytes of a label document (from amouriq_label_document()) on `$w_mm` x `$h_mm` paper,
 * or a WP_Error when the library is missing.
 */
function amouriq_label_pdf_bytes( $html, $w_mm, $h_mm ) {
	$autoload = WP_PLUGIN_DIR . '/print-invoices-packing-slip-labels-for-woocommerce/includes/vendor/autoload.php';
	if ( ! file_exists( $autoload ) ) {
		return new WP_Error( 'amouriq_label_pdf', 'ไม่พบไลบรารีสร้าง PDF (ปลั๊กอิน Print Invoices ต้องเปิดใช้อยู่)' );
	}
	require_once $autoload;

	// dompdf keeps its font files and cache in one writable folder.
	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'amouriq-label-fonts';
	wp_mkdir_p( $dir );
	foreach ( array( 'Sarabun-Regular.ttf', 'Sarabun-Bold.ttf' ) as $file ) {
		if ( ! file_exists( $dir . '/' . $file ) ) {
			copy( __DIR__ . '/fonts/' . $file, $dir . '/' . $file );
		}
	}

	$options = new \Wtpklistpdf\Dompdf\Options();
	$options->set( 'isHtml5ParserEnabled', true );
	$options->set( 'isRemoteEnabled', true );
	$options->set( 'defaultFont', 'sarabun' );
	$options->set( 'fontDir', $dir );
	$options->set( 'fontCache', $dir );
	$options->set( 'chroot', array( $dir ) ); // dompdf only reads font files inside this folder

	$pdf = new \Wtpklistpdf\Dompdf\Dompdf( $options );
	$fm  = $pdf->getFontMetrics();
	$fm->registerFont( array( 'family' => 'sarabun', 'style' => 'normal', 'weight' => 'normal' ), $dir . '/Sarabun-Regular.ttf' );
	$fm->registerFont( array( 'family' => 'sarabun', 'style' => 'normal', 'weight' => 'bold' ), $dir . '/Sarabun-Bold.ttf' );

	$mm_to_pt = 72 / 25.4;
	$pdf->setPaper( array( 0, 0, $w_mm * $mm_to_pt, $h_mm * $mm_to_pt ) );
	$pdf->loadHtml( $html );
	$pdf->render();
	return $pdf->output();
}
