<?php
if (!session_id()) {
    session_start();
}
add_action('wp_loaded', 'init_kipopay_payment');

/**
 * Require KipoPay gateway system
 */
require_once __DIR__ . '/includes/kipopayGatewayClass.php';

function init_kipopay_payment()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Check kipo response is exist or not and render showMessage
     */
    if (isset($_GET['kipo_message']) AND isset($_GET['kipo_class'])) {
        add_action('the_content', 'WC_full_kipopay::showMessage');
    }

    /**
     * Declare Class and extends that from payment gateway of WooCommerce
     *
     * Class WC_full_kipopay
     */
    class WC_full_kipopay extends WC_Payment_Gateway
    {
        public function __construct()
        {

            $this->id = 'kipopay_payment';
            $this->method_title = 'درگاه پرداخت کیپو';
            $this->method_description = 'پرداخت‌ها را از طریق درگاه پرداخت کیپو انجام دهید';
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchant_key = $this->settings['merchant_key'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];

            $this->msg['kipo_message'] = 0;
            $this->msg['kipo_class'] = '';
            $this->msg['transaction_code'] = 0;

            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_kipo_response'));

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                // Compatibilization plugin for different versions.
                add_action('woocommerce_update_options_payment_gateways_kipopay_payment', array(
                    &$this,
                    'process_admin_options'
                ));
            } else {
                add_action('woocommerce_update_options_payment_kipopay_payment', array(
                    &$this,
                    'process_admin_options'
                ));
            }

            add_action('woocommerce_receipt_kipopay_payment', array(&$this, 'receipt_page'));
        }

        /**
         * Declaring admin page fields.
         */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => [
                    'title' => 'فعال سازی/غیر فعال سازی :',
                    'type' => 'checkbox',
                    'label' => 'فعال سازی درگاه پرداخت کیپو',
                    'description' => 'برای امکان پرداخت کاربران از طریق این درگاه باید تیک فعال سازی زده شده باشد .',
                    'default' => 'no'
                ],
                'merchant_key' => array(
                    'title' => 'شماره مرچنت :',
                    'type' => 'text',
                    'description' => 'شماره تلفن‌همراه/شماره مرچنت فروشگاه',
                    'default' => ''
                ),
                'title' => array(
                    'title' => 'عنوان درگاه :',
                    'type' => 'text',
                    'description' => 'این عنوان در سایت برای کاربر نمایش داده می شود .',
                    'default' => 'درگاه پرداخت کیپو'
                ),
                'description' => array(
                    'title' => 'توضیحات درگاه :',
                    'type' => 'textarea',
                    'description' => 'این توضیحات در سایت، بعد از انتخاب درگاه توسط کاربر نمایش داده می شود .',
                    'default' => 'پرداخت وجه از طریق درگاه پرداخت کیپو توسط تمام کارت های عضو شتاب .'
                ),
                'redirect_page_id' => array(
                    'title' => 'آدرس بازگشت',
                    'type' => 'select',
                    'options' => $this->get_pages('صفحه مورد نظر را انتخاب نمایید'),
                    'description' => "صفحه‌ای که در صورت پرداخت موفق نشان داده می‌شود را نشان دهید."
                ),
            );
        }

        /**
         * Render Admin options
         */
        public function admin_options()
        {
            echo '<div class="kipopay-woocommerce">';
            echo '<h3>درگاه پرداخت کیپو</h3>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
            echo '</div>';
        }


        /**
         * Process_payment Function.
         * system call this function on ajax of checkout page to redirect
         * user to receipt_page
         **/
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order',
                    $order->id, add_query_arg('key', $order->order_key, $this->get_return_url($this->order)))
            );
        }

        /**
         * This function initial when user set order
         * and select payment method
         *
         * Receipt page.
         **/
        function receipt_page($order_id)
        {
            global $woocommerce;

            /**
             * Retrieve order from woocommerce database
             */
            $order = new WC_Order($order_id);

            /**
             * Create callback url
             */
            $callback = ($this->redirect_page_id == "" || $this->redirect_page_id == 0)
                ? get_site_url() . "/"
                : get_permalink($this->redirect_page_id);

            $callback = add_query_arg('wc-api', get_class($this), $callback);

            $merchant_key = $this->merchant_key;
            $order_total = round($order->order_total);

            /**
             * Retrieve currency of woocommerce and check if is iranRial
             * multiplied by 10
             */
            if (get_woocommerce_currency() == "IRT") {
                $order_total = $order_total * 10;
            }

            $kipo_gateway = new kipopayGatewayClass();
            date_default_timezone_set('Asia/Tehran');
            $kipo_gateway->SendOrder($order, date("Y/m/d H:i:s"), $order_total, $merchant_key, $callback);
        }

        /**
         * Check payment status with kipo server after return to website
         * This function called when user cancel or complete payment
         **/
        function check_kipo_response()
        {
            global $woocommerce;

            $session = $_SESSION;
            /**
             * Check if order sets
             */
            if (isset($session['kipo-woocommerce']) AND !empty($session['kipo-woocommerce'])) {
                $kipo_gateway = new kipopayGatewayClass();
                $kipo_session = $_SESSION['kipo-woocommerce'];
                $order_id = $kipo_session['order_id'];
                $order = new WC_Order($order_id);
                $order_total = round($order->order_total);

                /**
                 * Retrieve currency of woocommerce and check if is iranRial
                 * multiplied by 10
                 */
                if ( get_woocommerce_currency() == "IRT" ) {
                    $order_total = $order_total * 10;
                }

                /**
                 * Store WooCommerce session details
                 */
                $_SESSION['order'][$order_id] = ['id' => $order_id, 'status' => 'wc-pending'];

                $payment_check = $kipo_gateway->verifyOrder($this->merchant_key, $kipo_session['shopping_key']);

                if ($payment_check['status']) {
                    if ($payment_check['payment_amount'] == $order_total) {
                        $this->msg['kipo_class'] = 'success';
                        $this->msg['kipo_message'] = 1;
                        $this->msg['transaction_code'] = $payment_check['referent_code'];
                        $_SESSION['order'][$order_id]['status'] = 'wc-processing';

                        $order->payment_complete();
                        $order->add_order_note('پرداخت موفق، کد پیگیری پرداخت: ' . $payment_check['referent_code']);
                        $woocommerce->cart->empty_cart();
                    } else {
                        /**
                         * Payment error on order total
                         */
                        $this->msg['kipo_class'] = 'error';
                        $this->msg['kipo_message'] = -10;
                        $this->msg['transaction_code'] = 0;
                    }
                } else {
                    /**
                     * Payment canceled by user
                     */
                    $this->msg['kipo_class'] = 'error';
                    $this->msg['kipo_message'] = -2;
                    $this->msg['transaction_code'] = 0;
                }

            } else {
                $this->msg['kipo_class'] = 'error';
                $this->msg['kipo_message'] = -1;
                $this->msg['transaction_code'] = 0;
            }

            /**
             * Unset sessions after payment process complete
             */
            unset($_SESSION['kipo-woocommerce']);

            /**
             * Create redirect url to recipe page
             */
            if ($this->redirect_page_id > 0) {
                $redirect_url =
                    ($this->redirect_page_id == "" || $this->redirect_page_id == 0)
                        ? get_site_url() . "/"
                        : get_permalink($this->redirect_page_id);
            } else {
                $redirect_url = [
                    'result' => 'success',
                    'redirect' => add_query_arg('order',
                        $order->id, add_query_arg('key', $order->order_key, $this->get_return_url($this->order)))
                ];
            }

            /**
             * Add message, alert class and transaction_code
             * To url parameters
             */
            $redirect_url = add_query_arg(
                [
                    'kipo_message' => urlencode($this->msg['kipo_message']),
                    'kipo_class' => $this->msg['kipo_class'],
                    'transaction_code' => $this->msg['transaction_code']
                ]
                , $redirect_url);
            wp_redirect($redirect_url);
            exit;
        }

        /**
         * After user came back from kipo system
         * system check parameters of url and render new content
         * to show error message to user
         *
         * @param $content
         *
         * @return string
         */
        public static function showMessage($content)
        {
            /**
             * Check kipo message from response code
             */
            $kipo_message = kipopayGatewayClass::getErrorMessage($_GET['kipo_message']);

            /**
             * Get parameters from URL params
             */
            $kipo_class = $_GET['kipo_class'];
            $transaction_code = (isset($_GET['transaction_code'])) ? $_GET['transaction_code'] : 0;

            if ($kipo_class === 'success') {
                $return = '<div class="kipopay-woocommerce box ' . $kipo_class . '-box">';
                $return .= '<p><strong>' . $kipo_message . '</strong></p>';
                $return .= '<p class="kipopay-woocommerce-reference-code">کد پیگیری پرداخت شما <strong>' . $transaction_code . '</strong> می‌باشد. لطفا این کد را جهت پیگیری‌های بعدی ذخیره نمایید</p>';
                $return .= '</div>';
                $return .= $content;
            } else {
                $return = '<div class="kipopay-woocommerce box ' . $kipo_class . '-box">';
                $return .= '<p><strong>' . $kipo_message . '</strong></p>';
                $return .= '<p>در صورت بروز هرگونه مشکل با واحد پشتیبانی سایت تماس حاصل فرمایید</p>';
                $return .= '</div>';
                $return .= $content;
            }

            return $return;

        }

        /**
         * Get all Wordpress pages
         *
         * @param bool $title
         * @param bool $indent
         *
         * @return array
         */
        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) {
                $page_list[] = $title;
            }
            foreach ($wp_pages as $page) {
                $prefix = '';
                /**
                 * show indented child pages?
                 */
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }

                /**
                 * add to page list array array
                 */
                $page_list[$page->ID] = $prefix . $page->post_title;
            }

            return $page_list;
        }
    }

    /**
     * Add the Gateway to WooCommerce.
     **/
    function woocommerce_add_kipopay_gateway($methods)
    {
        $methods[] = 'WC_full_kipopay';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_kipopay_gateway');
}