<?php

if (!defined('EVENT_ESPRESSO_VERSION')) {
    exit('NO direct script access allowed');
}

/**
 * ----------------------------------------------
 *
 * Class  EEG_Paystack
 *
 * @package			Event Espresso
 * @author			Oluwafemi Fagbemi<fems.david@hotmail.com>
 * @version		 	1.1.0
 *
 * ----------------------------------------------
 */
class EEG_Paystack extends EE_Offsite_Gateway {

    /**
     * Merchant API Username.
     *  @var string
     */
    protected $_api_username;

    /**
     * Merchant API Password.
     *  @var string
     */
    protected $_api_password;

    /**
     * API Private Key.
     *  @var string
     */
    protected $_api_privatekey;

    /**
     * Request Shipping address on Paystack checkout page.
     *  @var string
     */
    protected $_request_shipping_addr;

    /**
     * Business/personal logo.
     *  @var string
     */
    protected $_image_url;

    /**
     * gateway URL variable
     *
     * @var string
     */
    protected $_base_gateway_url = '';

    /**
     * EEG_Paypal_Express constructor.
     */
    public function __construct() {
        $this->_currencies_supported = array(
            'USD',
            'AUD',
            'BRL',
            'CAD',
            'CZK',
            'DKK',
            'EUR',
            'HKD',
            'HUF',
            'ILS',
            'JPY',
            'MYR',
            'MXN',
            'NOK',
            'NZD',
            'PHP',
            'PLN',
            'GBP',
            'RUB',
            'SGD',
            'SEK',
            'CHF',
            'TWD',
            'THB',
            'TRY',
            'NGN'
        );


        //Wait for the user to return before processing
        $this->_uses_separate_IPN_request = false;
        parent::__construct();
    }

    /**
     * Sets the gateway URL variable based on whether debug mode is enabled or not.

     *
     * @param array $settings_array
     */
    public function set_settings($settings_array) {
        parent::set_settings($settings_array);
        // Redirect URL.
    }

    /**
     * @param EEI_Payment $payment
     * @param array       $billing_info
     * @param string      $return_url
     * @param string      $notify_url
     * @param string      $cancel_url
     * @return \EE_Payment|\EEI_Payment
     * @throws \EE_Error
     */
    public function set_redirection_info($payment, $billing_info = array(), $return_url = NULL, $notify_url = NULL, $cancel_url = NULL) {

        //echo "<PRE>".print_r($billing_info,1);exit;
        if (!$payment instanceof EEI_Payment) {
            $payment->set_gateway_response(__('Error. No associated payment was found.', 'event_espresso'));
            $payment->set_status($this->_pay_model->failed_status());
            return $payment;
        }
        $transaction = $payment->transaction();
        if (!$transaction instanceof EEI_Transaction) {
            $payment->set_gateway_response(__('Could not process this payment because it has no associated transaction.', 'event_espresso'));
            $payment->set_status($this->_pay_model->failed_status());
            return $payment;
        }
        $order_description = substr($this->_format_order_description($payment), 0, 127);
        $primary_registration = $transaction->primary_registration();
        $primary_attendee = $primary_registration instanceof EE_Registration ? $primary_registration->attendee() : false;


        $locale = explode('-', get_bloginfo('language'));

        //prep init request
        //generate a unique transaction/reference id
        $ref = str_replace(".", "", uniqid("E", true));
        $amountInKobo = $payment->amount() * 100;
        $payment->set_txn_id_chq_nmbr($ref);
        $token_request_dtls = array(
            'email' => $primary_attendee->email(),
            'amount' => $amountInKobo,
            'currency' => $payment->currency_code(),
            'callback_url' => $return_url,
            'reference' => $ref,
            'metadata' => array(
                "cancel_action" => "$cancel_url",
                "custom_fields" => array(
                    array(
                        "display_name" => "First Name",
                        "variable_name" => "fname",
                        "value" => $primary_attendee->fname()
                    ),
                    array(
                        "display_name" => "Last Name",
                        "variable_name" => "lname",
                        "value" => $primary_attendee->lname()
                    ),
                    array(
                        "display_name" => "Description",
                        "variable_name" => "desc",
                        "value" => $order_description
                    )
                )
            )
        );



        //add this params to update_info object! Thanks
        $token_request_dtls = apply_filters(
                'FHEE__EEG_Paystack__set_redirection_info__arguments', $token_request_dtls, $this
        );
        // Initialize Transaction
        $trans_init_response = $this->_initializeTransaction($token_request_dtls, 'Transaction Initialisation', $payment);
        $response_args = ( isset($trans_init_response) && is_object($trans_init_response) ) ? $trans_init_response : new stdClass();
        if ($response_args->status) {
            $payment->set_details(((array) $response_args));
            $payment->set_redirect_url($response_args->data->authorization_url);
        } else {
            if (isset($response_args->message)) {
                $payment->set_gateway_response($response_args->message);
            } else {
                $payment->set_gateway_response(__('Error occurred while trying to process the payment.', 'event_espresso'));
            }
            $payment->set_details(((array) $response_args));
            $payment->set_status($this->_pay_model->failed_status());
        }

        return $payment;
    }

    /**

     *  @param array $update_info {
     * 	  @type string $gateway_txn_id
     * 	  @type string status an EEMI_Payment status
     *  }
     *  @param EEI_Transaction $transaction
     *  @return EEI_Payment
     */
    public function handle_payment_update($update_info, $transaction) {

        $transRef = $update_info["trxref"];

        $payment = $transaction instanceof EEI_Transaction ? $transaction->last_payment() : null;


        $this->log(array('Return from Authorization' => $update_info), $payment);

        //transaction ref coming from payment gateway
        if ($payment instanceof EEI_Payment) {
            //$this->log(array('Return from Authorization' => $update_info), $payment);
            $transaction = $payment->transaction();
            if (!$transaction instanceof EEI_Transaction) {
                $payment->set_gateway_response(__('Could not process this payment because it has no associated transaction.', 'event_espresso'));
                $payment->set_status($this->_pay_model->failed_status());
                return $payment;
            }
            $primary_registrant = $transaction->primary_registration();
            $payment_details = $payment->details();
            // Check if we still have the token.
            if (!isset($update_info["trxref"]) || empty($update_info["trxref"])) {
                $payment->set_status($this->_pay_model->failed_status());
                return $payment;
            }

            $cdetails_request_dtls = array(
                'trxref' => $transRef
            );



            // Verify this transaction- confirm it is coming from paystack.
            $verify_request_response = $this->verifyTransaction($cdetails_request_dtls, 'Verify Transaction', $payment);


            $cdetails_rstatus = ( isset($verify_request_response) && is_object($verify_request_response) ) ? $verify_request_response : FALSE;


            if ($cdetails_rstatus !== FALSE) {
                if ($cdetails_rstatus->status) {
                    // All is well, payment approved.
                    //One more check
                    //confirm user paid the exact amount
                    if ($this->_money->compare_floats($payment->amount(), ((float) ($cdetails_rstatus->data->amount / 100)), '!=')) {
                        $payment->set_status($this->_pay_model->declined_status());
                        return $payment;
                    }
                    $primary_registration_code = $primary_registrant instanceof EE_Registration ? $primary_registrant->reg_code() : '';
                    $payment->set_extra_accntng($primary_registration_code);
                    $payment->set_amount(isset($cdetails_rstatus->data->amount) ? ($cdetails_rstatus->data->amount / 100) : 0);
                    $payment->set_txn_id_chq_nmbr($cdetails_rstatus->data->reference);
                    $payment->set_details(((array) $cdetails_rstatus));
                    $payment->set_gateway_response($cdetails_rstatus->data->gateway_response);
                    $payment->set_status($this->_pay_model->approved_status());
                } else {
                    if (isset($cdetails_rstatus->status)) {
                        if (isset($cdetails_rstatus->data->gateway_response)) {
                            $payment->set_gateway_response($cdetails_rstatus->data->gateway_response);
                        } else {
                            $payment->set_gateway_response($cdetails_rstatus->message);
                        }
                        $payment->set_status($this->_pay_model->declined_status());
                    } else {
                        $payment->set_status($this->_pay_model->failed_status());
                        $payment->set_gateway_response(__('Error occurred while trying to Capture the funds.', 'event_espresso'));
                    }
                    $payment->set_details(((array) $cdetails_rstatus));
                }
            } else {

                $payment->set_gateway_response(__('Error occurred while trying to get payment Details.', 'event_espresso'));
                $payment->set_details(((array) $cdetails_rstatus));
                $payment->set_status($this->_pay_model->failed_status());
            }
        } else {
            $payment->set_gateway_response(__('Error occurred while trying to process the payment.', 'event_espresso'));
            $payment->set_status($this->_pay_model->failed_status());
        }
        return $payment;
    }

    /**
     *  Initialize Transaction
     *
     * 	@param array        $request_params
     * 	@param string       $request_text
     *  @param EEI_Payment  $payment
     * 	@return mixed
     */
    public function _initializeTransaction($request_params, $request_text, $payment) {

        $secretKey = $this->_api_privatekey;
        $param = array(
            "method" => "POST",
            "body" => $request_params,
            "headers" => array("Authorization" => "Bearer $secretKey", "content-type" => "application/json")
        );

        $this->_log_clean_request($request_params, $payment, $request_text . ' Request');
        $response = wp_remote_post("https://api.paystack.co/transaction/initialize", $param);

        $this->log(array($request_text . ' Initialization Response from Paystack' => $response), $payment);
        if (is_wp_error($response)) {
            $param = new stdClass();
            $param->status = false;
            $param->message = $response->errors["http_request_failed"][0];
            return $param;
        } else if (isset($response["body"])) {
            return json_decode($response["body"]);
        } else {
            $param = new stdClass();
            $param->status = false;
            $param->message = "Something went wrong. Please try again later.";
            return $param;
        }
    }

    /**
     *  Check and verify Transaction.
     *
     * 	@param mixed        $tranx
     * 	@return array
     */
    public function verifyTransaction($request_params, $request_text, $payment) {
        $this->_log_clean_request($request_params, $payment, $request_text . ' Request');
        $p = array("body" => $request_params, "method" => "GET");
        $secretKey = $this->_api_privatekey;
        $param = array(
            "headers" => array("Authorization" => "Bearer $secretKey")
        );

        $response = wp_remote_get("https://api.paystack.co/transaction/verify/" . $request_params["trxref"], $param);


        $this->log(array($request_text . ' Response' => $response), $payment);
        if (is_wp_error($response)) {
            $param = new stdClass();
            $param->status = false;
            $param->message = $response->errors["http_request_failed"][0];
            return $param;
        } else if (isset($response["body"])) {
            return json_decode($response["body"]);
        } else {
            $param = new stdClass();
            $param->status = false;
            $param->message = "Something went wrong. Please try again later.";
            return $param;
        }
    }

    /**
     *  Log a "Cleared" request.
     *
     * @param array $request
     * @param EEI_Payment  $payment
     * @param string  		$info
     * @return void
     */
    private function _log_clean_request($request, $payment, $info) {
        $cleaned_request_data = $request;
        unset($cleaned_request_data['PWD'], $cleaned_request_data['USER'], $cleaned_request_data['SIGNATURE']);
        $this->log(array($info => $cleaned_request_data), $payment);
    }

    /**
     *  Get error from the response data.
     *
     *  @param array	$data_array
     *  @return array
     */
    private function _get_errors($data_array) {
        $errors = array();
        $n = 0;
        while (isset($data_array["L_ERRORCODE{$n}"])) {
            $l_error_code = isset($data_array["L_ERRORCODE{$n}"]) ? $data_array["L_ERRORCODE{$n}"] : '';
            $l_severity_code = isset($data_array["L_SEVERITYCODE{$n}"]) ? $data_array["L_SEVERITYCODE{$n}"] : '';
            $l_short_message = isset($data_array["L_SHORTMESSAGE{$n}"]) ? $data_array["L_SHORTMESSAGE{$n}"] : '';
            $l_long_message = isset($data_array["L_LONGMESSAGE{$n}"]) ? $data_array["L_LONGMESSAGE{$n}"] : '';

            if ($n === 0) {
                $errors = array(
                    'L_ERRORCODE' => $l_error_code,
                    'L_SHORTMESSAGE' => $l_short_message,
                    'L_LONGMESSAGE' => $l_long_message,
                    'L_SEVERITYCODE' => $l_severity_code
                );
            } else {
                $errors['L_ERRORCODE'] .= ', ' . $l_error_code;
                $errors['L_SHORTMESSAGE'] .= ', ' . $l_short_message;
                $errors['L_LONGMESSAGE'] .= ', ' . $l_long_message;
                $errors['L_SEVERITYCODE'] .= ', ' . $l_severity_code;
            }

            $n++;
        }


        return $errors;
    }

}

// End of file EEG_Paystack.gateway.php

