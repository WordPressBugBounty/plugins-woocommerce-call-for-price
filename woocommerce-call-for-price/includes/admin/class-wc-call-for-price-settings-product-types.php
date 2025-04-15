<?php // phpcs:ignore
/**
 * WooCommerce Call for Price - Product Types Sections Settings
 *
 * @package CallForPrice
 * @version 3.2.1
 * @since   3.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Call_For_Price_Settings_Product_Types' ) ) :
	/**
	 * Main Alg_WC_Call_For_Price_Settings_Product_Types Class
	 *
	 * @class   Alg_WC_Call_For_Price_Settings_Product_Types
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Call_For_Price_Settings_Product_Types {
		/**
		 * Product Types.
		 *
		 * @var $product_types
		 * @since 3.0.0
		 */
		public $product_types = '';

		/**
		 * Constructor.
		 *
		 * @version 3.1.0
		 * @since   3.0.0
		 */
		public function __construct() {
			add_filter( 'woocommerce_get_sections_alg_call_for_price', array( $this, 'settings_section' ) );
			add_action( 'init', array( &$this, 'add_cfp_desc_product_types' ) );
			add_action( 'woocommerce_admin_field_alg_wc_call_for_price_textarea', array( $this, 'output_custom_textarea' ) );
			add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'unclean_custom_textarea' ), PHP_INT_MAX, 3 );
		}
		/**
		 * Add desc to setting page.
		 */
		public function add_cfp_desc_product_types() {
			$this->product_types = array(
				'simple'   => __( 'Simple Products', 'woocommerce-call-for-price' ),
				'variable' => __( 'Variable Products', 'woocommerce-call-for-price' ),
				'grouped'  => __( 'Grouped Products', 'woocommerce-call-for-price' ),
				'external' => __( 'External Products', 'woocommerce-call-for-price' ),
			);
			foreach ( $this->product_types as $product_type_id => $product_type_desc ) {
				add_filter( 'woocommerce_get_settings_alg_call_for_price_' . $product_type_id, array( $this, 'get_settings' ), PHP_INT_MAX );
			}
		}

		/**
		 * Unclean_custom_textarea.
		 *
		 * @param string $value Value.
		 * @param array  $option Options.
		 * @param string $raw_value Raw Value.
		 * @version 3.1.0
		 * @since   3.1.0
		 */
		public function unclean_custom_textarea( $value, $option, $raw_value ) {
			return ( 'alg_wc_call_for_price_textarea' === $option['type'] ) ? $raw_value : $value;
		}

		/**
		 * Output_custom_textarea.
		 *
		 * @param array $value Array of attributes for creating input field.
		 * @version 3.1.0
		 * @since   3.1.0
		 */
		public function output_custom_textarea( $value ) {
			$option_value      = get_option( $value['id'], $value['default'] );
			$custom_attributes = ( isset( $value['custom_attributes'] ) && is_array( $value['custom_attributes'] ) ) ? $value['custom_attributes'] : array();
			$description       = ' <p class="description">' . $value['desc'] . '</p>';
			$tooltip_html      = ( isset( $value['desc_tip'] ) && '' !== $value['desc_tip'] ) ? '<span class="woocommerce-help-tip" data-tip="' . $value['desc_tip'] . '"></span>' : '';
			?><tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
				<?php echo wp_kses_post( $tooltip_html ); ?>
			</th>
			<td class="forminp forminp-<?php echo esc_attr( $value['type'] ); ?>">
				<?php echo wp_kses_post( $description ); ?>
				<textarea
					name="<?php echo esc_attr( $value['id'] ); ?>"
					id="<?php echo esc_attr( $value['id'] ); ?>"
					style="<?php echo esc_attr( $value['css'] ); ?>"
					class="<?php echo esc_attr( $value['class'] ); ?>"
					placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
					<?php echo implode( ' ', array_map( 'esc_attr', $custom_attributes ) ); ?>
					><?php echo esc_textarea( $option_value ); ?></textarea>
			</td>
		</tr>
			<?php
		}

		/**
		 * Settings_section.
		 *
		 * @param array $sections List of Sections.
		 * @version 3.0.0
		 * @since   3.0.0
		 */
		public function settings_section( $sections ) {
			foreach ( $this->product_types as $product_type_id => $product_type_desc ) {
				$sections[ $product_type_id ] = $product_type_desc;
			}
			return $sections;
		}

		/**
		 * Generate_settings_section.
		 *
		 * @param string $product_type Type of product.
		 * @version 3.2.1
		 * @since   3.0.0
		 */
		public function generate_settings_section( $product_type ) {
			$views = array(
				'single'  => __( 'Single product page', 'woocommerce-call-for-price' ),
				'related' => __( 'Related products', 'woocommerce-call-for-price' ),
				'home'    => __( 'Homepage', 'woocommerce-call-for-price' ),
				'page'    => __( 'Pages (e.g. shortcodes)', 'woocommerce-call-for-price' ),
				'archive' => __( 'Archives', 'woocommerce-call-for-price' ),
			);
			if ( 'variable' === $product_type ) {
				$views['variation'] = __( 'Variations', 'woocommerce-call-for-price' );
			}
			$settings = array(
				array(
					'title' => $this->product_types[ $product_type ],
					'type'  => 'title',
					'id'    => 'alg_wc_call_for_price_' . $product_type . '_options',
					'desc'  => apply_filters(
						'alg_call_for_price',
						sprintf(
							/* translators: %s: Link to pro version */
							__( 'You will need <a target="_blank" href="%s">Call for Price for WooCommerce Pro</a> plugin to change default texts except for single product page texts.', 'woocommerce-call-for-price' ),
							'https://www.tychesoftwares.com/store/premium-plugins/woocommerce-call-for-price-plugin/?utm_source=cfpupgradetopro&utm_medium=link&utm_campaign=CallForPriceLite'
						),
						'settings',
						$product_type,
						'all'
					),
				),
				array(
					'title'   => __( 'Enable/Disable', 'woocommerce-call-for-price' ),
					'desc'    => '<strong>' . __( 'Enable', 'woocommerce-call-for-price' ) . ' - ' . $this->product_types[ $product_type ] . '</strong>',
					'id'      => 'alg_wc_call_for_price_' . $product_type . '_enabled',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
			);
			foreach ( $views as $view => $view_desc ) {
				$settings = array_merge(
					$settings,
					array(
						array(
							'title'   => $view_desc,
							'desc'    => __( 'Enable', 'woocommerce-call-for-price' ),
							'id'      => 'alg_wc_call_for_price_' . $product_type . '_' . $view . '_enabled',
							'default' => 'yes',
							'type'    => 'checkbox',
						),
						array(
							'desc_tip'          => __( 'This sets the html to output on empty price. Leave blank to disable.', 'woocommerce-call-for-price' ),
							'id'                => 'alg_wc_call_for_price_text_' . $product_type . '_' . $view,
							'default'           => '<strong>' . __( 'Call for Price', 'woocommerce-call-for-price' ) . '</strong>',
							'type'              => 'alg_wc_call_for_price_textarea',
							'css'               => 'width:100%',
							'custom_attributes' => 'single' !== $view ? apply_filters( 'alg_call_for_price', array( 'readonly' => 'readonly' ), 'settings', $product_type, $view ) : '',
						),
					)
				);
			}
			$settings = array_merge(
				$settings,
				array(
					array(
						'type' => 'sectionend',
						'id'   => 'alg_wc_call_for_price_' . $product_type . '_options',
					),
				)
			);
			return $settings;
		}

		/**
		 * Get_settings.
		 *
		 * @version 3.0.0
		 * @since   3.0.0
		 */
		public function get_settings() {
			return ( isset( $_GET['section'] ) ) ? $this->generate_settings_section( sanitize_text_field( wp_unslash( $_GET['section'] ) ) ) : array(); // phpcs:ignore
		}
	}

endif;

return new Alg_WC_Call_For_Price_Settings_Product_Types();
