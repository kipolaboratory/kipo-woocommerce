<?php
if ( ! session_id() ) {
	session_start();
}

class kipopayGatewayClass {

    const SERVER_URL = 'https://backend.kipopay.com:8091/V1.0/processors/json/';

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
	 *
	 * @return bool
	 */
	function sendOrder(
		$order = null,
		$invoice_date = null,
		$amount = null,
		$merchant_key,
		$redirect_address
	) {

		// Get cURL resource
		$curl = curl_init();

		// Set some options - we are passing in a useragent too here
		curl_setopt_array( $curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL            => self::SERVER_URL,
			CURLOPT_POST           => 1,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_POSTFIELDS     => json_encode( [
				'Command' => [
					'Sign' => 'KPG@KPG/Initiate',
				],
				'OrderAt' => date( "Ymdhis" ),
				'OrderID' => '100000',
				'Profile' => [
					'HID' => $merchant_key,
					'SID' => 'fbd08763-9d75-4185-bde8-ccef825e1b65',
				],
				'Session' => [
					'' => ''
				],
				'Version' => [
					'AID' => 'kipo1-alpha',
				],
				'RawData' => [
					'MerchantKy'  => $merchant_key,
					'Amount'      => $amount,
					'BackwardUrl' => $redirect_address
				]
			] ),
			CURLOPT_HTTPHEADER     => [
				'Accept:application/json',
				'IP:127.0.0.1',
				'OS:web',
				'SC:false',
				'SK:.',
			],
			CURLOPT_USERAGENT      => 'kipopay-woocommerce-agent',
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => 0,
		] );

		/**
		 * Send curl request to server
		 */
		$response = curl_exec( $curl );

		/**
		 * Check if there is error
		 */
		if ( curl_error( $curl ) ) {
			$error_message = curl_error( $curl );
		}

		// Close request to clear up some resources
		curl_close( $curl );
		if ( ! isset( $error_message ) OR empty( $error_message ) ) {
			$response     = json_decode( $response );
			$shopping_key = $response->RawData->ShoppingKy;
			if ( ! empty( $shopping_key ) ) {

				/**
				 * Set order details to session
				 */
				$_SESSION['kipo-woocommerce'] = [
					'order_id' => $order->get_id(),
					'sk'       => $shopping_key,
					'amount'   => $amount
				];

				$order->add_order_note( 'کاربر وارد مرحله پرداخت شد، کد خرید:' . $shopping_key );

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
                <p>خطایی در اتصال به درگاه بوجود آمده است، لطفا دقایقی دیگر امتحان فرمایید.</p>
                <p><?= $error_message ?></p>
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
	function verifyOrder( $merchant_key, $shopping_key ) {
		// Get cURL resource
		$curl = curl_init();

		// Set some options - we are passing in a useragent too here
		curl_setopt_array( $curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL            => self::SERVER_URL,
			CURLOPT_POST           => 1,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_POSTFIELDS     => json_encode( [
				'Command' => [
					'Sign' => 'KPG@KPG/Inquery',
				],
				'OrderAt' => date( "Ymdhis" ),
				'OrderID' => '100000',
				'Profile' => [
					'HID' => $merchant_key,
					'SID' => 'fbd08763-9d75-4185-bde8-ccef825e1b65',
				],
				'Session' => [
					'' => ''
				],
				'Version' => [
					'AID' => 'kipo1-alpha',
				],
				'RawData' => [
					'ShoppingKy' => $shopping_key,
				]
			] ),
			CURLOPT_HTTPHEADER     => [
				'Accept:application/json',
				'IP:127.0.0.1',
				'OS:web',
				'SC:false',
				'SK:.',
			],
			CURLOPT_USERAGENT      => 'kipopay-woocommerce-agent',
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => 0,
		] );

		/**
		 * Send curl request to server
		 */
		$response = curl_exec( $curl );

		/**
		 * Check if there is error
		 */
		if ( curl_error( $curl ) ) {
			$error_message = curl_error( $curl );
		}
		// Close request to clear up some resources
		curl_close( $curl );

		if ( ! isset( $error_message ) OR empty( $error_message ) ) {
			$response = json_decode( $response );
			if ( isset( $response->RawData->ReferingID ) AND ! empty( $response->RawData->ReferingID ) ) {
				return [
					'status'       => true,
					'reference_id' => $response->RawData->ReferingID
				];
			}

			return [ 'status' => false ];
		}
	}

	/**
	 * Return message string from message code id
	 *
	 * @param $code_id
	 *
	 * @return mixed
	 */
	public static function kipoMessage( $code_id ) {
		$message = [
			1   => 'پرداخت با موفقیت انجام شد.',
			-1 => 'پرداخت با مشکل مواجه شده است.',
			-2 => 'پرداخت توسط کاربر لغو شده است.',
		];

		return $message[ $code_id ];
	}
}
