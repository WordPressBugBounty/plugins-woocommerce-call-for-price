<?php
/**
 * WooCommerce Call for Price - Settings
 *
 * @package CallForPrice
 * @version 3.2.0
 * @since   2.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Settings_Call_For_Price' ) ) :
	/**
	 * Main Alg_WC_Settings_Call_For_Price Class
	 *
	 * @class   Alg_WC_Settings_Call_For_Price
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Settings_Call_For_Price extends WC_Settings_Page {

		/**
		 * Constructor.
		 *
		 * @version 3.0.0
		 */
		public function __construct() {
			$this->id    = 'alg_call_for_price';
			$this->label = __( 'Call for Price', 'woocommerce-call-for-price' );
			parent::__construct();
		}

		/**
		 * Get_settings.
		 *
		 * @version 3.2.0
		 */
		public function get_settings() {
			global $current_section;
			$settings = apply_filters( 'woocommerce_get_settings_' . $this->id . '_' . $current_section, array() );
			return array_merge(
				$settings,
				array(
					array(
						'title' => __( 'Reset Settings', 'woocommerce-call-for-price' ),
						'type'  => 'title',
						'id'    => $this->id . '_' . $current_section . '_reset_options',
					),
					array(
						'title'   => __( 'Reset section settings', 'woocommerce-call-for-price' ),
						'desc'    => '<strong>' . __( 'Reset', 'woocommerce-call-for-price' ) . '</strong>',
						'id'      => $this->id . '_' . $current_section . '_reset',
						'default' => 'no',
						'type'    => 'checkbox',
					),
					array(
						'title'   => __( 'Reset Usage Tracking', 'woocommerce-call-for-price' ),
						'desc'    => __( 'This will reset your usage tracking settings, causing it to show the opt-in banner again and not sending any data.', 'woocommerce-call-for-price' ),
						'id'      => $this->id . '_' . $current_section . '_reset_usage_tracking',
						'default' => 'no',
						'type'    => 'checkbox',
					),
					array(
						'type' => 'sectionend',
						'id'   => $this->id . '_' . $current_section . '_reset_options',
					),
				)
			);
		}

		/**
		 * Maybe_reset_settings.
		 *
		 * @version 3.1.0
		 * @since   3.1.0
		 */
		public function maybe_reset_settings() {
			global $current_section;
			if ( 'yes' === get_option( $this->id . '_' . $current_section . '_reset', 'no' ) ) {
				foreach ( $this->get_settings() as $value ) {
					if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
						delete_option( $value['id'] );
						$autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
						add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
					}
				}
			}
			if ( 'yes' === get_option( $this->id . '_' . $current_section . '_reset_usage_tracking', '' ) ) {
				delete_option( 'cfp_lite_allow_tracking' );
				delete_option( $this->id . '_' . $current_section . '_reset_usage_tracking' );
				Tyche_Plugin_Tracking::reset_tracker_setting( 'cfp_lite' );
			}
		}

		/**
		 * Save settings.
		 *
		 * @version 3.1.0
		 * @since   3.1.0
		 */
		public function save() {
			parent::save();
			$this->maybe_reset_settings();
		}

		/**
		 * Output.
		 *
		 * @version 3.1.0
		 * @since   3.1.0
		 */
		public function output() {
			parent::output();
			if ( '' !== get_option( 'alg_wc_call_for_price_version', '' ) ) {
				echo '<p style="font-style:italic;float:right;">';
				echo sprintf(
					/* translators: %s: search term */
					esc_html__( 'Call for Price for WooCommerce - version %s', 'woocommerce-call-for-price' ),
					esc_html( get_option( 'alg_wc_call_for_price_version', '' ) )
				);
				echo '</p>';
			}
		}

	}

endif;

return new Alg_WC_Settings_Call_For_Price();
