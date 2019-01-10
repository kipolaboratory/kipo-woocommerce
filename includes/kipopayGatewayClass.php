<?php
if (!session_id()) {
    session_start();
}

class kipopayGatewayClass
{

    const SERVER_URL = 'http://webgate.kipopay.com/';

    const API_GENERATE_TOKEN = 'api/v1/token/generate';
    const API_VERIFY_PAYMENT = 'api/v1/payment/verify';

    /**
     * Contain error code explanation
     *
     * @var array
     */
    const ERROR_MESSAGE = [
        -1 => '.خطایی در داده‌های ارسالی وجود دارد،‌ لطفا اطلاعات را بررسی کنید و دوباره ارسال نمایید. (درخواست پرداخت)',
        -2 => 'خطایی در تحلیل داده‌های در سرور کیپو بوجود آمده است، دقایقی دیگر امتحان فرمایید.',
        -3 => 'امکان برقراری ارتباط با سرور کیپو میسر نمی‌باشد.',
        -4 => 'خطایی در داده‌های ارسالی وجود دارد،‌ لطفا اطلاعات را بررسی کنید و دوباره ارسال نمایید. (بررسی تایید پرداخت).',
        -5 => 'پرداخت توسط کاربر لغو شده یا با مشکل مواجه شده است.',
        -6 => 'شماره تماس فروشنده مورد نظر مورد تایید نمی‌باشد.',
        -7 => 'حداقل مبلغ پرداخت 1,000 ریال می‌باشد.',
        -8 => 'حداکثر مبلغ پرداخت 30,0000,000 ریال می‌باشد.',
        -9 => 'شناسه پرداخت ارسالی مورد تایید نمی‌باشد.',
        -10 => 'مبلغ پرداختی با مبلغ فاکتور تطابق ندارد.'
    ];

    /**
     * SEND ORDER TO KipoPay Gateway
     * Return False if there is a error,
     * redirect to gateway if everything OK.
     *
     * @param WC_Order $order
     * @param null $invoice_date date()
     * @param null $amount int/Rial
     * @param $merchant_key
     * @param $redirect_address
     */
    function sendOrder(
        $order = null,
        $invoice_date = null,
        $amount = null,
        $merchant_key,
        $redirect_address
    )
    {

        /**
         * Call self CURL request function
         */
        $curl = self::sendCurlRequest([
            'merchant_mobile' => $merchant_key,
            'payment_amount' => $amount,
            'callback_url' => $redirect_address
        ], self::API_GENERATE_TOKEN);

        if (!isset($curl['error_message']) OR empty($curl['error_message'])) {
            $response = json_decode($curl['response']);
            $shopping_key = $response->payment_token;
            if (!empty($shopping_key)) {

                /**
                 * Set order details to session
                 */
                $_SESSION['kipo-woocommerce'] = [
                    'order_id' => $order->get_id(),
                    'shopping_key' => $shopping_key,
                    'amount' => $amount
                ];

                $order->add_order_note('کاربر وارد مرحله پرداخت شد، کد خرید:' . $shopping_key);

                ?>
                <div id="wait-to-send" style='margin:0 auto; width: 600px; text-align: center;'>درحال انتقال به
                    درگاه<br>لطفا
                    منتظر بمانید .
                </div>
                <form id="kipopay-gateway" method="post" Action='http://webgate.kipopay.com/'
                      style='display: none;'>
                    <input type="hidden" id="sk" name="sk" value="<?= $shopping_key ?>"/>
                </form>
                <script language='javascript'>document.forms['kipopay-gateway'].submit();</script>
                <?php
            } else {
                ?>
                <div class="kipopay-woocommerce-gateway kipopay-woocommerce-gateway-error">
                    <p>خطایی در اتصال به درگاه بوجود آمده است، لطفا دقایقی دیگر امتحان فرمایید.</p>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="kipopay-woocommerce-gateway kipopay-woocommerce-gateway-error">
                <p><?= $curl['error_message'] ?></p>
            </div>
            <?php
        }
    }

    /**
     * Verify Order by Kipopay order verification system
     *
     * @param $merchant_key
     * @param $shopping_key
     *
     * @return array
     */
    function verifyOrder($merchant_key, $shopping_key)
    {

        /**
         * Call self CURL request function
         */
        $curl = self::sendCurlRequest([
            'payment_token' => $shopping_key,
        ], self::API_VERIFY_PAYMENT);

        /**
         * Check error code and error message
         * if there is no error, send back referent_code to user
         */
        if (!isset($curl['error_message']) OR empty($curl['error_message'])) {
            $response = json_decode($curl['response']);
            if (isset($response->referent_code) AND !empty($response->referent_code)) {
                return [
                    'status' => true,
                    'referent_code' => $response->referent_code,
                    'payment_amount' => $response->payment_amount
                ];
            }

            return ['status' => false];
        }
    }

    /**
     * Create an Object from CURL library
     * and send request to Kipo server
     *
     * @param $post_fields
     * @param $url_sign
     *
     * @return array
     */
    public static function sendCurlRequest($post_fields, $url_sign)
    {
        $error_message = null;
        $error_code = null;
        // Get cURL resource
        $curl = curl_init();

        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => self::SERVER_URL . $url_sign,
            CURLOPT_POST => 1,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POSTFIELDS => json_encode($post_fields),
            CURLOPT_HTTPHEADER => [
                'Accept:application/json',
                'Content-Type:application/json',
            ],
            CURLOPT_USERAGENT => 'kipopay-woocommerce-agent',
        ]);

        /**
         * Send curl request to server
         */
        $response = curl_exec($curl);
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        /**
         * Check if there is error
         */
        if (curl_error($curl)) {
            $error_code = curl_errno($curl);
            $error_message = curl_error($curl);
        }

        /**
         * Retrieve field error
         */
        if ($response_code === 422) {
            $field_error = array_pop(json_decode($response));
            $error_code = $field_error->message;
            $error_message = self::getErrorMessage($field_error->message);
        }

        // Close request to clear up some resources
        curl_close($curl);

        return [
            'response' => $response,
            'error_code' => $error_code,
            'error_message' => $error_message
        ];
    }

    /**
     * Retrieve error message
     *
     * @param $error_code
     *
     * @return mixed|null
     */
    public static function getErrorMessage($error_code)
    {
        $return_error = null;
        if (is_numeric($error_code)) {
            $return_error = self::ERROR_MESSAGE[$error_code];
        }

        return $return_error;
    }
}
