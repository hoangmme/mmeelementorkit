<?php
/**
 * Template functions for themes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mme_kit_area' ) ) {
	/**
	 * Print an extra Elementor area, e.g. mme_kit_area( 'bottom' ) after the_content().
	 *
	 * @param string   $name    Area name from MMe Kit → Areas.
	 * @param int|null $post_id Defaults to the current post.
	 * @return void
	 */
	function mme_kit_area( $name = 'bottom', $post_id = null ) {
		$post_id = $post_id ? (int) $post_id : get_the_ID();
		echo \Ems_Elements_Share\Areas\Ems_Areas_Manager::get_instance()->render( $post_id, sanitize_key( $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Elementor output.
	}
}
