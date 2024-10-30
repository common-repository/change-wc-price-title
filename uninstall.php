<?php
/**
 * Change Price Title for WooCommerce
 *
 * Uninstalling Change Price Title for WooCommerce Plugin deletes options.
 *
 * @author      Kartik Parmar
 * @category    Core
 * @package     Change_WooCommerce_Price_Title/Uninstall
 * @version     1.4
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Delete the data for the WordPress Multisite.
 */
if ( is_multisite() ) {

	$cwpt_blog_list = get_sites();

	foreach ( $cwpt_blog_list as $cwpt_blog_list_key => $cwpt_blog_list_value ) {
		$cwpt_blog_id = $cwpt_blog_list_value->blog_id;
		delete_blog_option( $cwpt_blog_id, 'cwpt_woocommerce_price_title' );
		delete_blog_option( $cwpt_blog_id, 'cwpt_woocommerce_hide_price_title' );
		delete_blog_option( $cwpt_blog_id, 'cwpt_apply_on_all_products' );
		delete_blog_option( $cwpt_blog_id, 'cwpt_enable_multiplier' );
		delete_blog_option( $cwpt_blog_id, 'change_woocommerce_price_title_db_version' );
	}
} else {
	// deleting all the seting added at product level.
	delete_post_meta_by_key( '_cwpt_price_title' );
	delete_post_meta_by_key( '_cwpt_hide_price' );

	// deleteing options added from global level.
	delete_option( 'cwpt_woocommerce_price_title' );
	delete_option( 'cwpt_woocommerce_hide_price_title' );
	delete_option( 'cwpt_apply_on_all_products' );
	delete_option( 'cwpt_enable_multiplier' );
	delete_option( 'change_woocommerce_price_title_db_version' );
}

// Clear any cached data that has been removed.
wp_cache_flush();
