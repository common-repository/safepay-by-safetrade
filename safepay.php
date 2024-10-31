<?php
/*
  Plugin Name: SafePay by SafeTrade
  Description: SafeTrade escrow service payment option. You get your money back if the product is damaged or incorrect.
  Version: 1.0
  Author: SafeTrade NG
  Author URI: https://safetrade.ng
  
  Copyright: (c) 2017 Five:59 LTD
  
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-SafePay
 * @author    SafeTrade
 * @category  Admin
 * @copyright Copyright: (c) 2017 Five:59 LTD
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */ 



defined('ABSPATH') or exit;
// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}
/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + safepay gateway
 */
function wc_safepay_add_to_gateways($gateways) {
                    $curr = get_woocommerce_currency();
                    $key = get_option('apiKey');
                    if ($curr == "NGN") {
                        $gateways[] = 'WC_Gateway_Safepay';
                    }
                    return $gateways;
                }

            
            add_filter('woocommerce_payment_gateways', 'wc_safepay_add_to_gateways');
/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_safepay_gateway_plugin_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=safepay_gateway') . '">' . __('Configure', 'wc-gateway-safepay') . '</a>'
    );
    return array_merge($plugin_links, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_safepay_gateway_plugin_links');
/**
 * SafePay Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class                                            WC_Gateway_SafePay
 * @extends		WC_Payment_Gateway
 * @version		0.1
 * @package		WooCommerce/Classes/Payment
 * @author 		SafeTarde
 */
add_action('plugins_loaded', 'wc_safepay_gateway_init', 11);







    


add_action( 'wp_footer', 'wc_safepay_reload', 999 );
function wc_safepay_reload() {
    if ( is_checkout() ) :
    ?>
    <script>
        jQuery( function($){
            // Checking that the variable "woocommerce_params" is defined to continue               
            if ( 'undefined' === typeof woocommerce_params )
                return false;

            $('form.checkout').change('input[name="payment_method"]', function(){
                $(this).trigger( 'update' );
            });
        });
    </script>
    <?php
    endif;
}


function wc_safepay_gateway_init() {

    class WC_Gateway_Safepay extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->id = 'safepay_gateway';
            $this->icon = apply_filters('woocommerce_safepay_icon', WP_PLUGIN_URL.'/assets/pay_logo.png');
            $this->has_fields = false;
            $this->method_title = __('SafePay', 'wc-gateway-safepay');
            $this->method_description = __('<img src="'.WP_PLUGIN_URL.'/assets/st.png" style="max-width:25%"> <br>SafeTrade escrow service payment option ', 'wc-gateway-safepay');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = "SafePay";
            $this->description = "SafeTrade escrow service payment option. You get your money back instantly if the product is damaged or incorrect.";
            $this->instructions = "Thank you for using SafePay by SafeTrade";
            $this->apikey = $this->get_option('apiKey');
            $this->apiSecret = $this->get_option('apiSecret');
            $this->business_id = $this->get_option('business_id');
            $this->marketplace = $this->get_option('marketplace');
            $this->enabled = $this->get_option('enabled');
            $this->currency = get_woocommerce_currency();
            
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));


            // Customer Emails
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);


            add_action( 'wc_safepay_check_safepay', array( $this, 'wc_safepay_check_response') );
            
            
   
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {
            
            $curr = get_woocommerce_currency();
            if ($curr !== "NGN") {

                function wc_safepay_error_notice() {
                    ?>
                    <div class="error notice">
                        <p><?php _e('WooCommerce currency must be in NGN (₦) for SafePay to work properly', 'wc-gateway-safepay'); ?></p>
                    </div>
                    <?php
                }

                add_action('admin_notices', 'wc_safepay_error_notice');
            } else {
                $this->form_fields = apply_filters('wc_safepay_form_fields', array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'wc-gateway-safepay'),
                        'type' => 'checkbox',
                        'label' => __("Enable SafePay Payment Method", 'wc-gateway-safepay'),
                        'default' => ''
                    ),
                    'business_id' => array(
                        'title' => __('SafeTrade Business ID', 'wc-gateway-safepay'),
                        'type' => 'text',
                        'description' => __('Business ID from SafeTrade', 'wc-gateway-safepay'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'apiKey' => array(
                        'title' => __('API Key', 'wc-gateway-safepay'),
                        'type' => 'text',
                        'description' => __('ApiKey from SafeTrade', 'wc-gateway-safepay'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'apiSecret' => array(
                        'title' => __('API Secret', 'wc-gateway-safepay'),
                        'type' => 'text',
                        'description' => __("ApiSecret from SafeTrade ", 'wc-gateway-safepay'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'marketplace' => array(
                        'title' => __('Marketplace', 'wc-gateway-safepay'),
                        'type' => 'select',
                        'description' => __("What type of amrket place do you run?", 'wc-gateway-safepay'),
                        'default' => '',
                        'options' => array(
                            'N/A' => __('I sell my own products', 'woocommerce'),
                            'WCMP' => __('I have a market place using WCMP', 'woocommerce'),
                        ),
                        'desc_tip' => true,
                    ),
                        ));
            }
        }

        /**
         * Output for the order received page.
         */ 
        public function thankyou_page() {

            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false) {

            if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('pending-payment')) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        }

        /**
         * Get the return url (thank you page).
         *
         * @param WC_Order $order
         * @return string
         */
        public function wc_safepay_get_return_url($order = null) {
            if ($order) {
                $return_url = $order->get_checkout_order_received_url();
            } else {
                $return_url = wc_get_endpoint_url('order-received', '', wc_get_page_permalink('checkout'));
            }
            if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
                $return_url = str_replace('http:', 'https:', $return_url);
            }
            return apply_filters('woocommerce_wc_safepay_get_return_url', $return_url, $order);
        }

        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {



            $date = date('Y-m-d H:i:s');



            $return_array = array();

            $return_array['order_id'] = $order_id;
            $return_array['buyer'] = array();
            $return_array['item'] = array();
            $return_array['business_id'] = $this->business_id;
            $return_array['apiKey'] = $this->apikey;
            $return_array['date'] = $date;
            $return_array['marketplace'] = $this->marketplace;

            $order = new WC_Order($order_id);
        
            $order_data = $order->get_data();

            $return_array['shipping'] = $order_data['shipping_total'];
            $return_array['discount'] = $order_data['discount_total'];
            $sub_total = $order->get_subtotal();
            $safe_fee = wc_safepay_calculate_fee($sub_total);
            $total_fee = ($sub_total + $return_array['shipping'] - $return_array['discount']);
            $return_array['total'] = $total_fee;
            $return_array['transaction_fee'] = $safe_fee;
            $user_id = $order->get_user_id();
            $customer = get_userdata($user_id);


            $row_array1['fullname'] = $order_data['billing']['first_name'] . " " . $order_data['billing']['last_name'];
            $row_array1['address'] = $order_data['billing']['address_1'] . " " . $order_data['billing']['address_2'] . " " . $order_data['billing']['city'] . " " . $order_data['billing']['country'];
            $row_array1['email'] = $order_data['billing']['email'];
            $row_array1['phone'] = $order_data['billing']['phone'];
            $order_shipping_total = $order_data['shipping_total'];
            array_push($return_array['buyer'], $row_array1);

            $order_items = $order->get_items();

            foreach ($order_items as $key => $item) {
                $row_array3['item_name'] = $item['name'];
                $row_array3['item_amount'] = $order->get_item_meta($key, '_line_total', true);
                $row_array3['item_quantity'] = $order->get_item_meta($key, '_qty', true);
                $product_id = $item['product_id'];

                $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail');
                $row_array3['item_image'] = $image[0];

                $terms = get_the_terms($product_id, 'product_cat');

                foreach ($terms as $term) {
                    // Categories by slug
                    $row_array3['item_description'] = $term->slug;
                }

                if ($this->marketplace == "WCMP") {
                    $vendor = get_wcmp_product_vendors($product_id);
                    $seller = get_userdata($vendor->id);

                    $row_array3['seller'] = array(
                        "fullname" => $vendor->user_data->display_name,
                        "address" => $seller->get_billing_address_1() . " " . $seller->get_billing_address_2(),
                        "email" => $seller->user_email,
                        "phone" => $seller->get_billing_phone(),
                    );
                }

                array_push($return_array['item'], $row_array3);
            }



            $data = http_build_query($return_array);

          
$args = array(
    'body' => $data,
    'timeout' => '5',
    'redirection' => '5',
    'headers' => array('X-API-SECRET' => "$this->apiSecret"), 
);
 
$response = wp_remote_post( 'https://safetrade.ng/v1/api/transactions/', $args );
            
           $response_body[] = json_decode(wp_remote_retrieve_body($response), true);
        
        
            $ids = $response_body[0]['id'];
            $b_user_id = $response_body[0]['user_id'];




            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('on-hold', __('Awaiting SafePay payment', 'wc-gateway-safepay'));

            //$order->add_order_note(sprintf("Order completed with Transaction Id of '%s'", $this->transactionId));
            // Reduce stock levels 
            $order->reduce_order_stock();
        
            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => "https://safetrade.ng/u/continue_pay?tnx_id=$ids&u=$b_user_id&b=$this->business_id"
            );
        }
        
        
        public function wc_safepay_check_response() {
	global $woocommerce;
	 
	if( isset( $_GET['safepay59'] ) ) 
            {
     
		$safepay = sanitize_text_field($_GET['safepay59']);
		$order_id = sanitize_text_field($_GET['order_id']);
		if( $order_id == 0 || $order_id == '' ) {
			return;
		}
		$order = new WC_Order( $order_id );
                
                if( $safepay == 'true' ) {
			
                    // Payment complete
	  		$order->payment_complete();
                        
                        // Remove cart
	  		$woocommerce->cart->empty_cart();
                        
                        // Set status as completed
                        $order->update_status('completed', __('Order complete', 'wc-gateway-safepay'));
                    
                        $r_url = $this->wc_safepay_get_return_url($order);
                        
                        wp_redirect($r_url);
                        exit;
                }
            }
        }
        

    }

    // end \WC_Gateway_Offline class
}

         //calculate SafeTrade's fee
  function wc_safepay_calculate_fee($item_amount)
    {
      $options = new WC_Gateway_Safepay();
    $key = $options->apikey;
    $secret = $options->apiSecret;
    $b_id = $options->business_id;
       
//check if API details are active

$args = array(
    'headers' => array('X-API-SECRET' => "$secret"), 
);
 
$response = wp_remote_get( "https://safetrade.ng/v1/api/business/?apiKey=$key&business_id=$b_id", $args );
            
           $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
    
                foreach($response_body as $item)
    {
                    $min =  $item['min_charge'];
                    $p1 =  $item['p1'];
                    $p2 =  $item['p2'];
                    $p3 =  $item['p3'];
                    $p4 =  $item['p4'];
    }   
    
         if($item_amount < 2500)
    {
        $fee = $min;
    }elseif($item_amount >= 2500 && $item_amount <= 249999)
    {
        $percentage = $p1;
        
        $fee = $item_amount * ($percentage/100);
        
        $fee = $fee + $min;
        
        $fee = floor($fee);
        
        if($fee > 5000)
        {
            $fee = 5000;
        }
        
    }elseif($item_amount >= 25000 && $item_amount <= 999999)
    {
        $percentage = $p2;
        
        $fee = $item_amount * ($percentage/100);
        
        $fee = $fee + $min;
        
        $fee = floor($fee);
        
        if($fee > 15000)
        {
            $fee = 15000;
        }
        
    }elseif($item_amount >= 1000000 && $item_amount <= 1999999)
    {
        $percentage = $p3;
        
        $fee = $item_amount * ($percentage/100);
        
        $fee = $fee + $min;
        
        $fee = floor($fee);
        
        if($fee > 22800)
        {
            $fee = 22800;
        }
        
    }elseif($item_amount >= 2000000)
    {
        $percentage = $p4;
        
        $fee = $item_amount * ($percentage/100);
        
        $fee = $fee + $min;
        
        $fee = floor($fee);
        
        if($fee > 50000)
        {
            $fee = 50000;
        }
        
    }
    
    return $fee;
    }

//Add woocommerce charge
function wc_safepay_safetrade_fee( $cart_obj ) {

    $cart_total = 0;
    
    $options = new WC_Gateway_Safepay();
    $key = $options->apikey;
    $secret = $options->apiSecret;
    $b_id = $options->business_id;
       
//check if API details are active
    
    $args = array(
    'headers' => array('X-API-SECRET' => "$secret"), 
);
        
$response = wp_remote_get( "https://safetrade.ng/v1/api/business/?apiKey=$key&business_id=$b_id", $args );
            
           $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
                foreach($response_body as $item)
    {
                    $payer =  $item['who_pays'];
    }     
    
    if($payer == "Buyer")
    {
    foreach( WC()->cart->get_cart() as $item ){ 
        $cart_total += $item["line_total"];
    }
    
    $fee = wc_safepay_calculate_fee($cart_total);
    }elseif($payer == "Both")
    {
        foreach( WC()->cart->get_cart() as $item ){ 
        $cart_total += $item["line_total"];
    }
    
    $d_fee = wc_safepay_calculate_fee($cart_total);
    $fee = $d_fee/2;
    }
    $chosen_gateway = WC()->session->chosen_payment_method;
    
    
    if('safepay_gateway' == $chosen_gateway)
        $cart_obj->add_fee( __("SafePay's Fee"), $fee, true ); // Tax enabled for the fee
}
add_action( 'woocommerce_cart_calculate_fees','wc_safepay_safetrade_fee', 10, 1 );
    



// Check for return from SafeTrade
add_action( 'init', 'wc_safepay_check_for_safepay' );

function wc_safepay_check_for_safepay() {
	if( isset($_GET['safepay59'])) {
	  // Start the gateways
		WC()->payment_gateways();
		do_action( 'wc_safepay_check_safepay' );
	}
	
}
        

