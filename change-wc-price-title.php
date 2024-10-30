<?php 
/**
 * Plugin Name:          Change Price Title for WooCommerce
 * Plugin URI:           https://kartechify.com/product/change-woocommerce-price-title/
 * Description:          This plugin allows you to change the WooCommerce Price Title. E.g From: $100/- Only. You can completely change the price title or set caption to price. Also, you can hide price titles on the WooCommerce Product page or on all WooCommerce pages.
 * Version:              2.6
 * Author:               Kartik Parmar
 * Author URI:           https://www.kartechify.com
 * Text Domain:          change-wc-price-title
 * Domain Path:          /i18n/languages/
 * Requires PHP:         7.3
 * Tested up to:         6.6.2
 * WC requires at least: 3.0.0
 * WC tested up to:      9.3.3
 * Requires Plugins:     woocommerce
 *
 * @package Change_WooCommerce_Price_Title
 */

if ( ! function_exists( 'cwpt_fs' ) ) {

	/**
	 * Create a helper function for easy SDK access.
	 */
	function cwpt_fs() {
		global $cwpt_fs;

		if ( ! isset( $cwpt_fs ) ) {
			// Include Freemius SDK.
			require_once __DIR__ . '/freemius/start.php';

			$cwpt_fs = fs_dynamic_init(
				array(
					'id'             => '5909',
					'slug'           => 'change-wc-price-title',
					'type'           => 'plugin',
					'public_key'     => 'pk_0b2743f102b17335928ef84f4726c',
					'is_premium'     => false,
					'has_addons'     => false,
					'has_paid_plans' => false,
					'menu'           => array(
						'slug'           => 'woocommerce_price_title',
						'override_exact' => true,
						'account'        => false,
						'contact'        => false,
						'parent'         => array(
							'slug' => 'woocommerce',
						),
					),
				)
			);
		}

		return $cwpt_fs;
	}

	// Init Freemius.
	cwpt_fs();
	// Signal that SDK was initiated.
	do_action( 'cwpt_fs_loaded' );
	/**
	 * Settings page.
	 */
	function cwpt_fs_settings_url() {
		return admin_url( 'admin.php?page=woocommerce_price_title' );
	}

	cwpt_fs()->add_filter( 'connect_url', 'cwpt_fs_settings_url' );
	cwpt_fs()->add_filter( 'after_skip_url', 'cwpt_fs_settings_url' );
	cwpt_fs()->add_filter( 'after_connect_url', 'cwpt_fs_settings_url' );
	cwpt_fs()->add_filter( 'after_pending_connect_url', 'cwpt_fs_settings_url' );
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( 'CWPT_Price' ) ) {

	/**
	 * CWPT_price class
	 */
	class CWPT_Price { // phpcs:ignore

		/**
		 * CWPT_price Constructor
		 *
		 * @since 1.0
		 */
		public function __construct() {

			add_action( 'admin_init', array( &$this, 'cwpt_check_compatibility' ) );

			add_action( 'woocommerce_product_options_advanced', array( &$this, 'cwpt_adding_set_price_title_field' ), 10, 1 );
			add_action( 'woocommerce_process_product_meta', array( &$this, 'cwpt_woocommerce_process_product_meta_simple' ), 10, 1 );
			add_filter( 'woocommerce_get_price_html', array( &$this, 'cwpt_change_woocommerce_price_title' ), 99, 2 );

			register_activation_hook( __FILE__, array( &$this, 'cwpt_price_activate' ) ); // Initialize settings.
			add_action( 'init', array( &$this, 'cwpt_price_update_po_file' ) );// Language Translation.
			add_action( 'admin_menu', array( &$this, 'cwpt_admin_menu' ) ); // WordPress Administration Menu.

			add_action( 'admin_init', array( $this, 'cwpt_add_settings_fields' ) );
			add_action( 'admin_init', array( $this, 'cwpt_register_settings' ) );
			add_action( 'admin_init', array( $this, 'cwpt_add_settings_section' ) );

			// Including styles and scripts.
			add_action( 'wp_enqueue_scripts', array( &$this, 'cwpt_add_scripts' ) );
			// Settings link on plugins page.
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'cwpt_plugin_settings_link' ) );

			add_action( 'before_woocommerce_init', array( &$this, 'cwpt_custom_order_tables_compatibility' ), 999 );
		}

		/**
		 * HPOS Compatibility.
		 *
		 * @since 2.2
		 */
		public function cwpt_custom_order_tables_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'change-wc-price-title/change-wc-price-title.php', true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', 'change-wc-price-title/change-wc-price-title.php', true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', 'change-wc-price-title/change-wc-price-title.php', true );
			}
		}

		/**
		 * Including script.
		 *
		 * @since 1.0
		 */
		public function cwpt_add_scripts() {

			global $post;

			if ( is_null( $post ) ) {
				return;
			}

			$post_id = $post->ID;

			if ( 'product' === get_post_type( $post_id ) ) {
				$cwpt_enable_multiplier = get_option( 'cwpt_enable_multiplier', '' );
				if ( '1' !== $cwpt_enable_multiplier ) {
					return;
				}

				$ajax_url        = get_admin_url() . 'admin-ajax.php';
				$_product        = wc_get_product( $post_id );
				$price           = $_product->get_price();
				$product_type    = $_product->get_type();
				$cur_symbol      = get_woocommerce_currency_symbol();
				$variation_price = array();

				if ( 'variable' === $product_type ) {

					foreach ( $_product->get_available_variations() as $variation ) {
						// Variation ID.
						$variation_id  = $variation['variation_id'];
						$active_price  = floatval( $variation['display_price'] ); // Active price.
						$regular_price = floatval( $variation['display_regular_price'] ); // Regular Price.

						if ( $active_price != $regular_price ) {
							$sale_price                       = $active_price; // Sale Price.
							$variation_price[ $variation_id ] = $sale_price;
						} else {
							$variation_price[ $variation_id ] = $regular_price;
						}
					}

					$price = $variation_price;
				}

				$cwpt_plugin_version_number = get_option( 'change_woocommerce_price_title_db_version' );
				$cwpt_enable_multiplier     = get_option( 'cwpt_enable_multiplier' );

				wp_enqueue_script( 'jquery' );

				wp_deregister_script( 'jqueryui' );

				wp_register_script(
					'cwpt-price-title',
					plugin_dir_url( __FILE__ ) . 'assets/js/cwpt-price-title.js',
					'',
					$cwpt_plugin_version_number,
					false
				);

				wp_localize_script(
					'cwpt-price-title',
					'cwpt_settings_params',
					array(
						'ajax_url'      => $ajax_url,
						'post_id'       => $post_id,
						'title_color'   => __( 'red', 'change-wc-price-title' ),
						'product_price' => $price,
						'wc_currency'   => $cur_symbol,
						'product_type'  => $product_type,
						'multiplier'    => $cwpt_enable_multiplier,
					)
				);

				wp_enqueue_script( 'cwpt-price-title' );
			}
		}

		/**
		 * Adding submenu under WooCommerce menu.
		 */
		public function cwpt_admin_menu() {
			add_submenu_page(
				'woocommerce',
				__( 'Price Title ', 'change-wc-price-title' ),
				__( 'WooCommerce Price Title', 'change-wc-price-title' ),
				'manage_woocommerce', // phpcs:ignore
				'woocommerce_price_title',
				array( &$this, 'cwpt_menu_page' )
			);
		}

		/**
		 * Callback action for submenu.
		 */
		public function cwpt_menu_page() {
			global $wpdb;

			// Check the user capabilities.
			if ( ! current_user_can( 'manage_woocommerce' ) ) { //phpcs:ignore
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'change-wc-price-title' ) );
			}
			settings_errors();
			?>
				<div class="wrap">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'cwpt_settings_group' );
						do_settings_sections( 'cwpt_settings_page' );
						submit_button();
						?>
					</form>
				</div>
				<?php
		}

		/**
		 * Callback for showing page heading on Global settings.
		 */
		public function cwpt_add_settings_section() {
			add_settings_section(
				'cwpt_settings_section',
				__( 'WooCommerce Price Title Settings', 'change-wc-price-title' ),
				array( $this, 'cwpt_section_callback' ),
				'cwpt_settings_page'
			);
		}

		/**
		 * Callback showing page description on Global settings.
		 */
		public function cwpt_section_callback() {
			echo '<p>' . esc_html__( 'Configure the price title settings for WooCommerce Product Price.', 'change-wc-price-title' ) . '</p>';
		}

		/**
		 * Callback for adding the fields on Global settings.
		 */
		public function cwpt_add_settings_fields() {
			add_settings_field(
				'cwpt_woocommerce_price_title',
				__( 'Change Price Title For All Products', 'change-wc-price-title' ),
				array( $this, 'cwpt_price_title_callback' ),
				'cwpt_settings_page',
				'cwpt_settings_section'
			);

			add_settings_field(
				'cwpt_woocommerce_hide_price_title',
				__( 'Hide Price Title', 'change-wc-price-title' ),
				array( $this, 'cwpt_hide_price_title_callback' ),
				'cwpt_settings_page',
				'cwpt_settings_section'
			);

			add_settings_field(
				'cwpt_apply_on_all_products',
				__( 'Apply Above Options On All WooCommerce Pages', 'change-wc-price-title' ),
				array( $this, 'cwpt_apply_on_all_products_callback' ),
				'cwpt_settings_page',
				'cwpt_settings_section'
			);

			add_settings_field(
				'cwpt_enable_multiplier',
				__( 'Enable to show price by multiplying with quantity', 'change-wc-price-title' ),
				array( $this, 'cwpt_enable_multiplier_callback' ),
				'cwpt_settings_page',
				'cwpt_settings_section'
			);
		}

		/**
		 * Callback function for Set price title for all products option on Global settings.
		 */
		public function cwpt_price_title_callback() {
			$value = get_option( 'cwpt_woocommerce_price_title' );
			echo '<input type="text" id="cwpt_woocommerce_price_title" name="cwpt_woocommerce_price_title" value="' . esc_attr( $value ) . '" class="regular-text" />';
			echo '<p class="description"><i>' . esc_html__( 'Here you can set price title for all your products. Also you can use PRICE shortcode as per your requirement. E.g From: PRICE Only/-', 'change-wc-price-title' ) . '</i></p>';
		}

		/**
		 * Callback function for Hide price title for all WooCommerce products option on Global settings.
		 */
		public function cwpt_hide_price_title_callback() {
			$value = get_option( 'cwpt_woocommerce_hide_price_title' );
			echo '<p class="description"><input type="checkbox" id="cwpt_woocommerce_hide_price_title" name="cwpt_woocommerce_hide_price_title" value="1" ' . checked( 1, $value, false ) . ' />';
			echo '<i>' . esc_html__( 'You can hide price title for all WooCommerce products.', 'change-wc-price-title' ) . '</i></p>';
		}

		/**
		 * Callback function for Apply the above settings to all WooCommerce pages option on Global settings.
		 */
		public function cwpt_apply_on_all_products_callback() {
			$value = get_option( 'cwpt_apply_on_all_products' );
			echo '<p class="description"><input type="checkbox" id="cwpt_apply_on_all_products" name="cwpt_apply_on_all_products" value="1" ' . checked( 1, $value, false ) . ' />';
			echo '<i>' . esc_html__( 'Enable this if you wish to apply above setting on all WooCommerce Pages.', 'change-wc-price-title' ) . '</i></p>';
		}

		/**
		 * Callback function for Show price multiplied by quantity option on Global settings.
		 */
		public function cwpt_enable_multiplier_callback() {
			$value = get_option( 'cwpt_enable_multiplier' );
			echo '<p class="description"><input type="checkbox" id="cwpt_enable_multiplier" name="cwpt_enable_multiplier" value="1" ' . checked( 1, $value, false ) . ' />';
			echo '<i>' . esc_html__( 'Enable this if you wish to show price as per the multiply by quantity.', 'change-wc-price-title' ) . '</i></p>';
		}

		/**
		 * Callback function registering all options on Global settings.
		 */
		public function cwpt_register_settings() {
			register_setting( 'cwpt_settings_group', 'cwpt_woocommerce_price_title' );
			register_setting( 'cwpt_settings_group', 'cwpt_woocommerce_hide_price_title' );
			register_setting( 'cwpt_settings_group', 'cwpt_apply_on_all_products' );
			register_setting( 'cwpt_settings_group', 'cwpt_enable_multiplier' );
		}

		/**
		 * Ensure that the plugin is deactivated when WooCommerce is deactivated.
		 *
		 * @since 1.0
		 */
		public static function cwpt_check_compatibility() {

			if ( ! self::cwpt_check_woo_installed() ) {

				if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					deactivate_plugins( plugin_basename( __FILE__ ) );

					add_action( 'admin_notices', array( 'CWPT_Price', 'cwpt_disabled_notice' ) );
					if ( isset( $_GET['activate'] ) ) { // phpcs:ignore
						unset( $_GET['activate'] );
					}
				}
			}
		}

		/**
		 * Check if WooCommerce is active.
		 */
		public static function cwpt_check_woo_installed() {

			if ( class_exists( 'WooCommerce' ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Display a notice in the admin Plugins page if this plugin is
		 * activated while WooCommerce is deactivated.
		 */
		public static function cwpt_disabled_notice() {

			$class   = 'notice notice-error';
			$message = __( 'Change Price Title for WooCommerce requires WooCommerce installed and activate.', 'change-wc-price-title' );

			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); // phpcs:ignore
		}

		/**
		 * Version Saving
		 *
		 * @since 1.0
		 */
		public function cwpt_price_activate() {
			// Activation code here.
			update_option( 'change_woocommerce_price_title_db_version', '2.6' );
		}

		/**
		 * Language Translation
		 *
		 * @since 1.0
		 */
		public function cwpt_price_update_po_file() {

			$domain = 'change-wc-price-title';
			$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
			$loaded = load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '-' . $locale . '.mo' );
			if ( $loaded ) {
				return $loaded;
			} else {
				load_plugin_textdomain( $domain, false, basename( __DIR__ ) . '/i18n/languages/' );
			}
		}

		/**
		 * Adding fields in the Advanced tab of Product data meta box.
		 *
		 * @since 1.0
		 */
		public function cwpt_adding_set_price_title_field() {

			global $post;

			$product_obj = wc_get_product( $post->ID );

			// Set Price Title Input in Product data metabox.
			woocommerce_wp_text_input(
				array(
					'id'          => '_cwpt_price_title',
					'label'       => __( 'Set price title', 'change-wc-price-title' ),
					'desc_tip'    => true,
					'description' => __( 'Here you can set the text for WooCommerce Price title as per your requirement. Use PRICE shortcode to include original price in title. E.g From PRICE', 'change-wc-price-title' ),
				)
			);

			// Hide Title Checkbox in Product data meta box.
			woocommerce_wp_checkbox(
				array(
					'id'          => '_cwpt_hide_price',
					'label'       => __( 'Hide Price?', 'change-wc-price-title' ),
					'desc_tip'    => true,
					'description' => __( 'Hide Price WooCommerce Product Page', 'change-wc-price-title' ),
				)
			);

			// Applicable on all WooCommerce Pages checkbox in Product data metabox.
			woocommerce_wp_checkbox(
				array(
					'id'          => '_cwpt_apply_on_all_wc_pages',
					'label'       => __( 'Applicable on all WooCommerce Pages', 'change-wc-price-title' ),
					'desc_tip'    => true,
					'description' => __( 'Enable to apply the set and hide price option to all WooCommerce pages.', 'change-wc-price-title' ),
				)
			);
		}

		/**
		 * Saving options in database.
		 *
		 * @param int $product_id Product ID.
		 * @since 1.0
		 */
		public function cwpt_woocommerce_process_product_meta_simple( $product_id ) {

			$cwpt_hide_price            = 'no';
			$cwpt_apply_on_all_wc_pages = 'no';

			if ( isset( $_POST['_cwpt_price_title'] ) ) { // phpcs:ignore
				update_post_meta( $product_id, '_cwpt_price_title', sanitize_text_field( wp_unslash( $_POST['_cwpt_price_title'] ) ) ); // phpcs:ignore
			}

			if ( isset( $_POST['_cwpt_hide_price'] ) && 'yes' === $_POST['_cwpt_hide_price'] ) { // phpcs:ignore
				$cwpt_hide_price = 'yes';
			}
			update_post_meta( $product_id, '_cwpt_hide_price', $cwpt_hide_price );

			if ( isset( $_POST['_cwpt_apply_on_all_wc_pages'] ) && 'yes' === $_POST['_cwpt_apply_on_all_wc_pages'] ) { // phpcs:ignore
				$cwpt_apply_on_all_wc_pages = 'yes';
			}
			update_post_meta( $product_id, '_cwpt_apply_on_all_wc_pages', $cwpt_apply_on_all_wc_pages );
		}

		/**
		 * Applying selected options on WooCommerce price title.
		 *
		 * @param string $price Price.
		 * @param obj    $product_obj Price.
		 *
		 * @since 1.0
		 */
		public function cwpt_change_woocommerce_price_title( $price, $product_obj ) {

			// Getting product id from the product object.
			$product_id = $product_obj->get_id();

			// Getting option for applicable on all WooCommerce Pages.
			$cwpt_apply_on_all_wc_pages_value = get_post_meta( $product_id, '_cwpt_apply_on_all_wc_pages', true );
			$cwpt_apply_on_all_wc_pages       = ( isset( $cwpt_apply_on_all_wc_pages_value ) && '' !== $cwpt_apply_on_all_wc_pages_value ) ? $cwpt_apply_on_all_wc_pages_value : 'no';

			// Getting value of Apply on all wc pages from Global Level.
			$cwpt_apply_on_all_products_value = get_option( 'cwpt_apply_on_all_products' );
			if ( ! is_product() && ( '' === $cwpt_apply_on_all_wc_pages || 'no' === $cwpt_apply_on_all_wc_pages ) ) {
				if ( '1' !== $cwpt_apply_on_all_products_value ) {
					return $price;
				}
			}

			$original_price = $price;

			// Getting product id from the product object.
			$product_id = $product_obj->get_id();

			// Getting value of WooCommerce Hide Price from Global Level.
			$global_hide_price = get_option( 'cwpt_woocommerce_hide_price_title' );

			// If Hide Price is enabled then hide all product's prices from WooCommerce Product Page.
			if ( '1' === $global_hide_price ) {
				$price = '';
				return $price;
			}

			// Getting option for hide price at product level.
			$product_hide_price = get_post_meta( $product_id, '_cwpt_hide_price', true );

			// If Hide Price is enabled then hide all product's prices from WooCommerce Product Page.
			if ( 'yes' === $product_hide_price ) {
				$price = '';
				return $price;
			}

			// Getting Price title at Product Level.
			$cwpt_price = get_post_meta( $product_id, '_cwpt_price_title', true );

			// Getting Price title at Global Level.
			$global_level_set_title = get_option( 'cwpt_woocommerce_price_title' );

			// Setting $price to the text as per the set text in Set price title field at global level.
			if ( isset( $global_level_set_title ) && '' !== $global_level_set_title ) {

				if ( strpos( $global_level_set_title, 'PRICE' ) !== false ) {
					$price = str_replace( 'PRICE', $original_price, $global_level_set_title );
				} else {
					$price = $global_level_set_title;
				}
			}

			// Setting $price to the text as per the set text in Set price title field at product level.
			if ( isset( $cwpt_price ) && '' !== $cwpt_price ) {
				if ( strpos( $cwpt_price, 'PRICE' ) !== false ) {
					$price = str_replace( 'PRICE', $original_price, $cwpt_price );
				} else {
					$price = $cwpt_price;
				}
			}

			return $price;
		}

		/**
		 * Settings link on Plugins page
		 *
		 * @param array $links Exisiting Links present on Plugins information section.
		 *
		 * @return array Modified array containing the settings link added
		 *
		 * @since 1.4
		 */
		public function cwpt_plugin_settings_link( $links ) {

			$settings_text            = __( 'Settings', 'change-wc-price-title' );
			$setting_link['settings'] = '<a href="' . esc_url( get_admin_url( null, 'admin.php?page=woocommerce_price_title' ) ) . '">' . $settings_text . '</a>';
			$links                    = $setting_link + $links;
			return $links;
		}
	}
	$cwpt_price = new CWPT_Price();
}
