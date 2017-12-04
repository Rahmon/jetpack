<?php
/**
 * Jetpack_WooCommerce_Analytics_Universal
 *
 * @package Jetpack
 * @author Automattic
 */

/**
 * Bail if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Jetpack_WooCommerce_Analytics_Universal
 * Filters and Actions added to Store pages to perform analytics
 */
class Jetpack_WooCommerce_Analytics_Universal {
	/**
	 * Jetpack_WooCommerce_Analytics_Universal constructor.
	 */
	public function __construct() {

		// loading s.js
		add_action( 'wp_head', array( $this, 'wp_head' ), 999999 );

		// single product page view
		add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );

		// add to cart on single product page
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_cart' ) );

		// add to carts from list views (search, store etc.)
		add_action( 'wp_footer', array( $this, 'loop_add_to_cart' ) );


		add_action( 'woocommerce_after_cart', array( $this, 'remove_from_cart' ) );
		add_action( 'woocommerce_after_mini_cart', array( $this, 'remove_from_cart' ) );
		add_action( 'wcct_before_cart_widget', array( $this, 'remove_from_cart' ) );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'remove_from_cart_attributes' ), 10, 2 );

		// cart checkout
		add_action( 'woocommerce_after_checkout_form', array( $this, 'checkout_process' ) );

		// order confirmed
		add_action( 'woocommerce_thankyou', array( $this, 'order_process' ), 10, 1 );
		add_action( 'woocommerce_after_cart', array( $this, 'remove_from_cart_via_quantity' ), 10, 1 );
	}

	/**
	 * Place script to call s.js, Store Analytics
	 */
	public function wp_head() {

		// If we're in the admin_area, return without inserting code.
		if ( is_admin() ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		wc_enqueue_js( "
			window._wca = window._wca || [];
		" );

		$async_code = "<script async src='https://stats.wp.com/s.js'></script>";

		echo "$async_code\r\n";
	}

	/**
	 * On a product page, add a click event listener to "Add to Cart" button click
	 */
	public function add_to_cart() {

		if ( ! is_single() ) {
			return;
		}

		$blogid = Jetpack::get_option( 'id' );
		global $product;

		$selector = '.single_add_to_cart_button';

		wc_enqueue_js(
			"jQuery( '" . esc_js( $selector ) . "' ).click( function() {
				_wca.push( {
					'_en': 'woocommerceanalytics_add_to_cart',
					'blog_id': " . esc_js( $blogid ) . ",
					'pi': '" . esc_js( $product->get_id() ) . "',
					'pn' : '" . esc_js( $product->get_title() ) . "',
					'pq': jQuery( 'input.qty' ).val() ? jQuery( 'input.qty' ).val() : '1',
				} );
			} );"
		);
	}

	/**
	 * On product lists or other non-product pages, add an event listener to "Add to Cart" button click
	 */
	public function loop_add_to_cart() {

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$minimum_woocommerce_active = class_exists( 'WooCommerce' ) && version_compare( WC_VERSION, '3.0', '>=' );
		if ( ! $minimum_woocommerce_active ) {
			return;
		}
		$blogid   = Jetpack::get_option( 'id' );
		$selector = '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)';

		wc_enqueue_js(
			"jQuery( '" . esc_js( $selector ) . "' ).click( function() {
				var productID = jQuery( this ).data( 'product_id' );
				var productDetails = {
					'id': productID,
					'quantity': jQuery( this ).data( 'quantity' ),
				};
				_wca.push( {
					'_en': 'woocommerceanalytics_product_view',
					'blog_id': '" . esc_js( $blogid ) . "',
					'pi': productDetails.id,
				} );
				_wca.push( {
					'_en': 'woocommerceanalytics_add_to_cart',
					'blog_id': " . esc_js( $blogid ) . ",
					'pi': productDetails.id,
					'pq': productDetails.quantity,
				} );
			} );"
		);
	}

	/**
	 * On the cart page, add an event listener for removal of product click
	 */
	public function remove_from_cart() {

		// We listen at div.woocommerce because the cart 'form' contents get forcibly
		// updated and subsequent removals from cart would then not have this click
		// handler attached.
		$blogid = Jetpack::get_option( 'id' );
		wc_enqueue_js(
			"jQuery( 'div.woocommerce' ).on( 'click', 'a.remove', function() {
				var productID = jQuery( this ).data( 'product_id' );
				var quantity = jQuery( this ).parent().parent().find( '.qty' ).val()
				var productDetails = {
					'id': productID,
					'quantity': quantity ? quantity : '1',
				};
				_wca.push( {
					'_en': 'woocommerceanalytics_remove_from_cart',
					'blog_id': '" . esc_js( $blogid ) . "',
					'pi': productDetails.id,
					'pq': productDetails.quantity,
				} );
			} );"
		);
	}

	/**
	 * Adds the product ID to the remove product link (for use by remove_from_cart above) if not present
	 *
	 * @param string $url url.
	 * @param string $key key.
	 * @return mixed.
	 */
	public function remove_from_cart_attributes( $url, $key ) {
		if ( false !== strpos( $url, 'data-product_id' ) ) {
			return $url;
		}

		$item    = WC()->cart->get_cart_item( $key );
		$product = $item['data'];

		$new_attributes = sprintf(
			'href="%s" data-product_id="%s" data-product_sku="%s"',
			esc_attr( $url ),
			esc_attr( $product->get_id() ),
			esc_attr( $product->get_sku() )
		);
		$url            = str_replace( 'href=', $new_attributes );
		return $url;
	}

	/**
	 * Track a product page view
	 */
	public function product_detail() {

		global $product;
		$blogid = Jetpack::get_option( 'id' );

		$item_details = array(
			'id'       => $product->get_id(),
			'name'     => $product->get_title(),
			'category' => Jetpack_WooCommerce_Analytics_Utils::get_product_categories_concatenated( $product ),
			'price'    => $product->get_price(),
		);
		wc_enqueue_js(
			"_wca.push( {
				'_en': 'woocommerceanalytics_product_view',
				'blog_id': '" . esc_js( $blogid ) . "',
				'pi': '" . esc_js( $item_details['id'] ) . "',
				'pn': '" . esc_js( $item_details['name'] ) . "',
				'pc': '" . esc_js( $item_details['category'] ) . "',
				'pp': '" . esc_js( $item_details['price'] ) . "'
			} );"
		);
	}

	/**
	 * On the Checkout page, trigger an event for each product in the cart
	 */
	public function checkout_process() {

		$universal_commands = array();
		$cart               = WC()->cart->get_cart();
		$blogid             = Jetpack::get_option( 'id' );

		foreach ( $cart as $cart_item_key => $cart_item ) {
			/**
			* This filter is already documented in woocommerce/templates/cart/cart.php
			*/
			$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			$item_details = array(
				'id'       => $product->get_id(),
				'name'     => $product->get_title(),
				'category' => Jetpack_WooCommerce_Analytics_Utils::get_product_categories_concatenated( $product ),
				'price'    => $product->get_price(),
				'quantity' => $cart_item['quantity'],
			);

			array_push(
				$universal_commands,
				"_wca.push( {
					'_en': 'woocommerceanalytics_product_checkout',
					'blog_id': '" . esc_js( $blogid ) . "',
					'pi': '" . esc_js( $item_details['id'] ) . "',
					'pn': '" . esc_js( $item_details['name'] ) . "',
					'pc': '" . esc_js( $item_details['category'] ) . "',
					'pq': '" . esc_js( $item_details['quantity'] ) . "',
				} );"
			);
		}

		wc_enqueue_js( implode( "\r\n", $universal_commands ) );
	}

	/**
	 * After the checkout process, fire an event for each item in the order
	 *
	 * @param string $order_id Order Id.
	 */
	public function order_process( $order_id ) {
		$order              = wc_get_order( $order_id );
		$universal_commands = array();
		$blogid             = Jetpack::get_option( 'id' );

		// loop through products in the order and queue a purchase event.
		foreach ( $order->get_items() as $order_item_id => $order_item ) {
			$product = $order->get_product_from_item( $order_item );

			$item_details = array(
				'id'       => $product->get_id(),
				'name'     => $product->get_title(),
				'category' => Jetpack_WooCommerce_Analytics_Utils::get_product_categories_concatenated( $product ),
				'price'    => $product->get_price(),
				'order'    => $order->get_order_number(),
			);

			array_push(
				$universal_commands,
				"_wca.push( {
					'_en': 'woocommerceanalytics_product_purchase',
					'blog_id': '" . esc_js( $blogid ) . "',
					'pi': '" . esc_js( $item_details['id'] ) . "',
					'pn': '" . esc_js( $item_details['name'] ) . "',
					'pc': '" . esc_js( $item_details['category'] ) . "',
					'pp': '" . esc_js( $item_details['price'] ) . "',
					'oi': '" . esc_js( $item_details['order'] ) . "',
				} );"
			);
		}

		wc_enqueue_js( implode( "\r\n", $universal_commands ) );
	}

	/**
	 * Listen for clicks on the "Update Cart" button to know if an item has been removed by
	 * updating its quantity to zero
	 */
	public function remove_from_cart_via_quantity() {
		$blogid = Jetpack::get_option( 'id' );

		wc_enqueue_js( "
			jQuery( 'input[name=update_cart]' ).on( 'click', function() {
				var cartItems = jQuery( '.cart_item' );
				cartItems.each( function( item ) {
					var qty = jQuery( this ).find( 'input.qty' );
					if ( qty && qty.val() === '0' ) {
						var productID = jQuery( this ).find( '.product-remove a' ).data( 'product_id' );
						_wca.push( {
							'_en': 'woocommerceanalytics_remove_from_cart',
							'blog_id': '" . esc_js( $blogid ) . "',
							'pi': productID,
						} );
					}
				} );
			} );
		" );
	}

}
