<?php
/**
 * WooCommerce Call for Price - Settings - Per Product
 *
 * @package CallForPrice
 * @version 3.1.1
 * @since   3.1.1
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Call_For_Price_Settings_Per_Product' ) ) :
	/**
	 * Main Alg_WC_Call_For_Price_Settings_Per_Product Class
	 *
	 * @class   Alg_WC_Call_For_Price_Settings_Per_Product
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Call_For_Price_Settings_Per_Product {

		/**
		 * Constructor.
		 *
		 * @version 3.1.1
		 * @version 3.1.1
		 */
		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'add_call_for_price_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_call_for_price_meta_box' ) );
		}

		/**
		 * Add_call_for_price_meta_box.
		 *
		 * @version 3.1.1
		 * @since   3.1.1
		 */
		public function add_call_for_price_meta_box() {
			add_meta_box(
				'alg-wc-call-for-price-meta-box',
				__( 'Call for Price', 'woocommerce-call-for-price' ),
				array( $this, 'create_call_for_price_meta_box' ),
				'product',
				'normal',
				'high'
			);
		}

		/**
		 * Get_meta_box_options.
		 *
		 * @version 3.1.1
		 * @since   3.1.1
		 * @todo    per variation
		 * @todo    views
		 */
		public function get_meta_box_options() {
			return array(
				array(
					'title'   => __( 'Enable', 'woocommerce-call-for-price' ),
					'tooltip' => __( 'This will only enable the label - you still have to set product price to empty, for label to appear on frontend.', 'woocommerce-call-for-price' ),
					'name'    => 'alg_wc_call_for_price_enabled',
					'type'    => 'select',
					'default' => 'no',
					'options' => array(
						'no'  => __( 'No', 'woocommerce-call-for-price' ),
						'yes' => __( 'Yes', 'woocommerce-call-for-price' ),
					),
				),
				array(
					'title'   => __( 'Text (all views)', 'woocommerce-call-for-price' ),
					'name'    => 'alg_wc_call_for_price_text_all_views',
					'type'    => 'textarea',
					'default' => '<strong>' . __( 'Call for Price', 'woocommerce-call-for-price' ) . '</strong>',
					'css'     => 'width:100%;',
				),
			);
		}

		/**
		 * Create_call_for_price_meta_box.
		 *
		 * @param object $post Post Object.
		 * @version 3.1.1
		 * @since   3.1.1
		 */
		public function create_call_for_price_meta_box( $post ) {
			$current_post_id = get_the_ID();
			$html            = '';
			$html           .= '<table class="widefat striped">';
			foreach ( $this->get_meta_box_options() as $option ) {
				if ( ! isset( $option['enabled'] ) || 'yes' === $option['enabled'] ) {
					if ( 'title' === $option['type'] ) {
						$html .= '<tr>';
						$html .= '<th colspan="3" style="text-align:left;font-weight:bold;">' . $option['title'] . '</th>';
						$html .= '</tr>';
					} else {
						$custom_attributes = '';
						$the_post_id       = ( isset( $option['product_id'] ) ) ? $option['product_id'] : $current_post_id;
						$the_meta_name     = ( isset( $option['meta_name'] ) ) ? $option['meta_name'] : '_' . $option['name'];
						if ( get_post_meta( $the_post_id, $the_meta_name ) ) {
							$option_value = get_post_meta( $the_post_id, $the_meta_name, true );
						} else {
							$option_value = ( isset( $option['default'] ) ) ? $option['default'] : '';
						}
						$css          = ( isset( $option['css'] ) ) ? $option['css'] : '';
						$input_ending = '';
						if ( 'select' === $option['type'] ) {
							if ( isset( $option['multiple'] ) ) {
								$custom_attributes = ' multiple';
								$option_name       = $option['name'] . '[]';
								$class             = 'chosen_select';
							} else {
								$option_name = $option['name'];
								$class       = '';
							}
							if ( isset( $option['custom_attributes'] ) ) {
								$custom_attributes .= ' ' . $option['custom_attributes'];
							}
							$options = '';
							foreach ( $option['options'] as $select_option_key => $select_option_value ) {
								$selected = '';
								if ( is_array( $option_value ) ) {
									foreach ( $option_value as $single_option_value ) {
										$selected = selected( $single_option_value, $select_option_key, false );
										if ( '' !== $selected ) {
											break;
										}
									}
								} else {
									$selected = selected( $option_value, $select_option_key, false );
								}
								$options .= '<option value="' . $select_option_key . '" ' . $selected . '>' . $select_option_value . '</option>';
							}
						} elseif ( 'textarea' === $option['type'] ) {
							if ( '' === $css ) {
								$css = 'min-width:300px;';
							}
						} else {
							$input_ending = ' id="' . $option['name'] . '" name="' . $option['name'] . '" value="' . $option_value . '">';
							if ( isset( $option['custom_attributes'] ) ) {
								$input_ending = ' ' . $option['custom_attributes'] . $input_ending;
							}
							if ( isset( $option['placeholder'] ) ) {
								$input_ending = ' placeholder="' . $option['placeholder'] . '"' . $input_ending;
							}
						}
						switch ( $option['type'] ) {
							case 'price':
								$field_html = '<input style="' . $css . '" class="short wc_input_price" type="number" step="0.0001"' . $input_ending;
								break;
							case 'date':
								$field_html = '<input style="' . $css . '" class="input-text" display="date" type="text"' . $input_ending;
								break;
							case 'textarea':
								$field_html = '<textarea style="' . $css . '" id="' . $option['name'] . '" name="' . $option['name'] . '">' .
								$option_value . '</textarea>';
								break;
							case 'select':
								$field_html = '<select' . $custom_attributes . ' style="' . $css . '" id="' . $option['name'] . '" name="' .
								$option_name . '" class="' . $class . '">' . $options . '</select>';
								break;
							default:
								$field_html = '<input style="' . $css . '" class="short" type="' . $option['type'] . '"' . $input_ending;
								break;
						}
						$html         .= '<tr>';
						$maybe_tooltip = ( isset( $option['tooltip'] ) && '' !== $option['tooltip'] ) ? wc_help_tip( $option['tooltip'], true ) : '';
						$html         .= '<th style="text-align:left;width:25%;">' . $option['title'] . $maybe_tooltip . '</th>';
						if ( isset( $option['desc'] ) && '' !== $option['desc'] ) {
							$html .= '<td style="font-style:italic;width:25%;">' . $option['desc'] . '</td>';
						}
						$html .= '<td>' . $field_html . '</td>';
						$html .= '</tr>';
					}
				}
			}
			$html .= '</table>';
			$html .= '<input type="hidden" name="alg_wc_call_for_price_meta_box_save_post" value="alg_wc_call_for_price_meta_box_save_post">';
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Save_call_for_price_meta_box.
		 *
		 * @param int $post_id Post ID.
		 * @version 3.1.1
		 * @since   3.1.1
		 */
		public function save_call_for_price_meta_box( $post_id ) {
			if ( ! isset( $_POST['alg_wc_call_for_price_meta_box_save_post'] ) ) {
				return;
			}
			foreach ( $this->get_meta_box_options() as $option ) {
				if ( 'title' === $option['type'] ) {
					continue;
				}
				if ( ! isset( $option['enabled'] ) || 'yes' === $option['enabled'] ) {
					$option_value = '';
					if ( isset( $option['name'] ) ) {
						$option_value = ( isset( $_POST[ $option['name'] ] ) ? sanitize_text_field( wp_unslash( $_POST[ $option['name'] ] ) ) : $option['default'] );
					}
					$_post_id   = ( isset( $option['product_id'] ) ? $option['product_id'] : $post_id );
					$_meta_name = ( isset( $option['meta_name'] ) ? $option['meta_name'] : '_' . $option['name'] );
					update_post_meta( $_post_id, $_meta_name, $option_value );
				}
			}
		}

	}

endif;

return new Alg_WC_Call_For_Price_Settings_Per_Product();
