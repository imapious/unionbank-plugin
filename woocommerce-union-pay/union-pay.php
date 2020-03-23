<?php
/*
 * Plugin Name:       Union Bank Payment Gateway
 * Plugin URI:        https://github.com
 * Description:       A local payment gateway using Union Bank
 * Author:            Andrew Gragasin
 * Author URI:        https://github.com/imapious
 * Requires at least: 4.0
 * Tested up to:      4.6
 * Text Domain:       woocommerce-union-pay
 * Domain Path:       languages
 * Network:           false
 * GitHub Plugin URI: https://github.com
 *
 * WooCommerce Payment Gateway Boilerlate is distributed under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * either version 2 of the License, or any later version.
 *
 * WooCommerce Payment Gateway Boilerlate is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WooCommerce Payment Gateway Boilerlate. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// My Custom CSS & JS
function ubp_scripts() {
    wp_enqueue_style( 'style', plugins_url('\assets\css\custom.css', __FILE__) );
}
add_action( 'wp_enqueue_scripts', 'ubp_scripts' );

date_default_timezone_set('asia/manila');


/**
 * Custom Payment Gateway.
 *
 * Provides a Custom Payment Gateway, mainly for testing purposes.
 */
add_action('plugins_loaded', 'init_ubp_gateway_class');
function init_ubp_gateway_class() {

    class WC_Union_Pay extends WC_Payment_Gateway
    {

        public $domain;
        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->domain = 'ubp_payment';

            $plugin_dir = plugin_dir_url(__FILE__);

            $this->id                 = 'ubp';
            $this->icon               = apply_filters('woocommerce_ubp_gateway_icon', $plugin_dir.'\assets\unionbank400x400.jpg');
            $this->has_fields         = false;
		    $this->order_button_text = __( 'Proceed to UnionBank', 'woocommerce' );
            $this->method_title       = __( 'Union Bank Payment Gateway', $this->domain );
            $this->method_description = __( 'Allows payments with ubp gateway.', $this->domain );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        		= $this->get_option( 'title' );
            $this->description  		= $this->get_option( 'description' );
            $this->instructions 		= $this->get_option( 'instructions', $this->description );
            $this->order_status 		= $this->get_option( 'order_status', 'completed' );
            $this->client_id            = $this->get_option( 'client_id' );
            $this->client_secret        = $this->get_option( 'client_secret' );
            $this->parter_id            = $this->get_option( 'parter_id' );
            // $this->account_number       = $this->get_option( 'account_number' );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) ); // save settings in Admin
            add_action( 'woocommerce_thankyou_ubp', array( $this, 'thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Custom Payment', $this->domain ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
                    'default'     => __( 'Union Pay', $this->domain ),
                    'desc_tip'    => true,
                ),
                'client_id' => array(
                    'title'       => __( 'Client ID', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This is Client ID', $this->domain ),
                    'default'     => __( 'Client ID', $this->domain ),
                    'desc_tip'    => true,
                ),
                'client_secret' => array(
                    'title'       => __( 'Client Secret', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This is Client Secret', $this->domain ),
                    'default'     => __( 'Client Secret', $this->domain ),
                    'desc_tip'    => true,
                ),
                'parter_id' => array(
                    'title'       => __( 'Partner Id', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This is Partner Id', $this->domain ),
                    'default'     => __( 'Partner Id', $this->domain ),
                    'desc_tip'    => true,
                ),
                'redirect_uri' => array(
                    'title'       => __( 'Redirect URI', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This is Redirect Uri', $this->domain ),
                    'default'     => __( 'Redirect Uri', $this->domain ),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Order Status', $this->domain ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
                    'default'     => 'wc-completed',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __( 'Description', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the ubper will see on your checkout.', $this->domain ),
                    'default'     => __('Payment Information', $this->domain),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions )
                echo wpautop( wptexturize( $this->instructions ) );
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( $this->instructions && ! $sent_to_admin && 'ubp' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

        public function payment_fields() { ?>

            <a href="https://api-uat.unionbankph.com/partners/sb/convergent/v1/oauth2/authorize?response_type=code&client_id=<?php echo $this->get_option( 'client_id' ) ?>&redirect_uri=<?php echo $this->get_option( 'redirect_uri' ) ?>&scope=payments&type=single&partnerId=<?php echo $this->get_option( 'partner_id' ) ?>" class="unionbank-btn">UNIONBANK LOGIN</a>

        <?php

        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        // public function process_payment( $order_id ) {

        //     $order = wc_get_order( $order_id );

        //     $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

        //     // Set order status
        //     $order->update_status( $status, __( 'Checkout with ubp payment. ', $this->domain ) );

        //     // Reduce stock levels
        //     $order->reduce_order_stock();

        //     // Remove cart
        //     WC()->cart->empty_cart();

        //     // Return thankyou redirect
        //     return array(
        //         'result'    => 'success',
        //         'redirect'  => $this->get_return_url( $order )
        //     );
        // }
    }
}

add_filter( 'woocommerce_payment_gateways', 'add_ubp_gateway_class' );
function add_ubp_gateway_class( $methods ) {
    $methods[] = 'WC_Union_Pay';
    return $methods;
}

add_action('woocommerce_after_checkout_validation', 'process_ubp_payment', 10, 2);
function process_ubp_payment($fields, $errors) {

    if($_POST['payment_method'] != 'ubp') {
        $errors->add('validation', 'test');
        wc_add_notice( __( 'Login your Union Bank Account', $this->domain ), 'error' );
    }

    if(isset($_GET['code'])) {

        if($_POST['submitted'] == true) {

            $curl = curl_init();

            $code = esc_sql($_GET['code']);

            curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api-uat.unionbankph.com/partners/sb/convergent/v1/oauth2/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=authorization_code&client_id=$this->client_id&code=$code&redirect_uri=$this->redirect_uri",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/x-www-form-urlencoded"
            ),
            ));

            $result = curl_exec($curl);
            $err = curl_error($curl);

            if($err) {
                echo 'Curl Error: ' . $err;
            } else {

                $response = json_decode($result, true);
                $token = $response['access_token'];

                $curl = curl_init();

                $data->senderRefId = $order->get_id;
                $data->tranRequestDate = date(c);
                $data->amount = array(
                    "currency" => "PHP",
                    "value" => $order->get_formatted_order_total()
                );
                $data->remarks = $this->order_status;
                $data->particulars = $order->get_items();
                $data->info = array(
                    array(
                        "index" => 1,
                        "name" => "Payor",
                        "value" => $order->get_billing_first_name() . " " . $order->get_billing_last_name()
                    ),
                    array(
                        "index" => 2,
                        "name" => "InvoiceNo",
                        "value" => $order->get_transaction_id()
                    )
                );

                $request = json_encode($data);

                curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api-uat.unionbankph.com/partners/sb/merchants/v4/payments/single",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $request,
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/json",
                    "x-ibm-client-id:". $this->get_option['client_id'],
                    "x-ibm-client-secret:". $this->get_option['client_secret'],
                    "Authorization: Bearer $token",
                    "x-partner-id: ". $this->get_option['partner_id']
                    ),
                ));

                $response = curl_exec($curl);
                $err = curl_error($curl);

                if ($err) {
                    echo "cURL Error #:" . $err;
                } else {
                    echo $response;
                }
                curl_close($curl);
            }

        } else {
            $errors->add('validation', 'test');
            wc_add_notice( __( 'Login your Union Bank Account', $this->domain ), 'error' );
        }
    }
}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'ubp_payment_update_order_meta' );
function ubp_payment_update_order_meta( $order_id ) {

    if($_POST['payment_method'] != 'ubp') {
        return;
    }

    update_post_meta( $order_id, 'source_account', $_POST['source_account'] );
}

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'ubp_checkout_field_display_admin_order_meta', 10, 1 );
function ubp_checkout_field_display_admin_order_meta($order) {
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'ubp') {
        return;
    }

    // $source_account = get_post_meta( $order->id, 'source_account', true );

    // echo '<p><strong>'.__( 'Account Number' ).':</strong> ' . $order->get_billing_email() . '</p>';
}
