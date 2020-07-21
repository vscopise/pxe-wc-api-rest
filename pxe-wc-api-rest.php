<?php

/**
 * Plugin Name:       PXE WC Api Rest
 * Plugin URI:        
 * Description:       Extends the Woocommerce Rest Api
 * Version:           1.0.0
 * Author:            Pixie
 * Author URI:        http://www.pixie.com.uy/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pxe-extend-api-rest
 * Domain Path:       /languages/
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!class_exists('PXE_WC_Api_Rest')) :
    class PXE_WC_Api_Rest
    {

        protected static $instance = NULL;

        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self;
            }
            return self::$instance;
        }

        /**
         * __construct
         *
         * @return void
         */
        public function __construct()
        {
            //add_action( 'woocommerce_rest_check_permissions', __CLASS__ . '::pxe_wc_rest_check_permissions' );    
            add_action('rest_api_init', __CLASS__ . '::extend_product_endpoint');
            add_action('rest_api_init', __CLASS__ . '::create_order_endpoint');
            add_action('rest_api_init', __CLASS__ . '::get_product_variation_endpoint');
            add_filter('woocommerce_taxonomy_args_product_cat', __CLASS__ . '::extend_product_term');
            add_filter('woocommerce_taxonomy_args_product_tag', __CLASS__ . '::extend_product_term');
        }

        /**
         * Register the route for get the product variation
         *
         * @return void
         */
        public static function get_product_variation_endpoint()
        {
            register_rest_route(
                'wp/v2',
                '/product/(?P<id>\d+)/variation/(?P<variation>\d+)',
                array(
                    'methods' => 'GET',
                    'callback' => __CLASS__ . '::get_product_variation',
                ),
                true
            );
        }

        /**
         * Return Product Variation via API
         *
         * @param  mixed $request
         * @return WP_REST_Response|WP_Error Product Variation.
         */
        public static function get_product_variation($request)
        {
            $variation_id = $request['variation'];

            $variation = wc_get_product($variation_id);

            return $variation->get_data();
        }

        /**
         * Perform a Creation of a new Order in the WC Backend
         *
         * @return void
         */
        public static function create_order_endpoint()
        {
            register_rest_route('pxe-wc/v1', '/create-order/', array(
                'methods' => 'POST',
                'callback' => __CLASS__ . '::create_order',
            ));
        }

        /**
         * create_order
         *
         * @return void
         */
        public static function create_order($request)
        {
            $adress_data = $request['address'];
            $line_items = $request['line_items'];

            $address = array(
                'first_name' => $adress_data['first_name'],
                'last_name'  => $adress_data['last_name'],
                'company'    => $adress_data['company'],
                'email'      => $adress_data['email'],
                'phone'      => $adress_data['phone'],
                'address_1'  => $adress_data['address_1'],
                'address_2'  => $adress_data['address_2'],
                'city'       => $adress_data['city'],
                'state'      => $adress_data['state'],
                'postcode'   => $adress_data['postcode'],
                'country'    => $adress_data['country']
            );
            $order = wc_create_order();

            foreach ($line_items as $item) {
                $product_item = wc_get_product($item['productId']);
                $order->add_product(
                    $product_item,
                    $item['quantity']
                );
            }

            // Set addresses
            $order->set_address($address, 'billing');
            $order->set_address($address, 'shipping');

            // Set payment gateway
            $payment_gateways = WC()->payment_gateways->payment_gateways();
            $order->set_payment_method($payment_gateways['bacs']);

            // Calculate totals
            $order->calculate_totals();
            $order->update_status('completed', 'Order created dynamically - ', TRUE);
            //$order->update_status('Completed', 'Order created dynamically - ', TRUE);

            return true;
        }

        /**
         * pxe_wc_rest_check_permissions
         *
         * @return void
         */
        /* public static function pxe_wc_rest_check_permissions() {
            return true;
        } */

        /**
         * Includes extra information in the Product endpoint
         *
         * @return void
         */
        public static function extend_product_endpoint()
        {
            // Product meta fields to include
            $fields = array(
                'product_image_gallery',
                'regular_price',
                'sale_price_dates_from',
                'sale_price_dates_to',
                'sale_price',
                'sku',
            );

            foreach ($fields as $field) {
                register_rest_field('product', $field, array(
                    'get_callback' => function ($object, $field) {
                        return get_post_meta($object['id'], '_' . $field, true);
                    }
                ));
            }

            // Product Categories
            register_rest_field('product', 'product_cat', array(
                'get_callback' => function ($object) {
                    $terms = get_the_terms($object['id'], 'product_cat');
                    foreach ($terms as $term) {
                        $product_categories[] = $term->term_id;
                    }
                    return $product_categories;
                }
            ));

            // Product Tags
            register_rest_field('product', 'product_tag', array(
                'get_callback' => function ($object) {
                    $terms = get_the_terms($object['id'], 'product_tag');

                    if (!$terms) return array();
                    
                    foreach ($terms as $term) {
                        $product_tags[] = $term->term_id;
                    }
                    return $product_tags;
                }
            ));

            // Product Price
            register_rest_field('product', 'price', array(
                'get_callback' => function ($object) {
                    $product = wc_get_product($object['id']);

                    return $product->get_price();
                }
            ));

            // Featured Product
            register_rest_field('product', 'featured', array(
                'get_callback' => function ($object) {
                    $product = wc_get_product($object['id']);

                    return $product->get_featured();
                }
            ));

            // Rating Count of Product
            register_rest_field('product', 'rating_count', array(
                'get_callback' => function ($object) {
                    $product = wc_get_product($object['id']);

                    return $product->get_rating_counts();
                }
            ));

            // Average Rating of Product
            register_rest_field('product', 'average_rating', array(
                'get_callback' => function ($object) {
                    $product = wc_get_product($object['id']);

                    return $product->get_average_rating();
                }
            ));

            // Product Type
            register_rest_field('product', 'product_type', array(
                'get_callback' => function ($object) {
                    $product = wc_get_product($object['id']);

                    return $product->get_type();
                }
            ));

            // Product Variations
            register_rest_field('product', 'variations', array(
                'get_callback' => function ($object) {
                    $product = wc_get_product($object['id']);

                    if ($product->is_type('variable')) {
                        $variations = $product->get_available_variations();
                        return  wp_list_pluck($variations, 'variation_id');
                    } else {
                        return [];
                    }
                }
            ));

            // Product Attributes
            register_rest_field('product', 'attributes', array(
                'get_callback' => function ($object) {
                    $product = wc_get_product($object['id']);
                    $product_attributes = $product->get_attributes();
                    return $product_attributes;
                }
            ));
        }

        /**
         * extend_product_term
         *
         * @param  mixed $args
         * @return void
         */
        public static function extend_product_term($args)
        {
            $args['show_in_rest'] = true;
            return $args;
        }
    }

    $PXE_WC_Api_Rest = new PXE_WC_Api_Rest;

endif;
