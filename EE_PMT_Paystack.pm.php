<?php

if (!defined('EVENT_ESPRESSO_VERSION')) {
    exit('NO direct script access allowed');
}

/**
 * ----------------------------------------------
 *
 * Class  EE_PMT_Paystack
 *
 * @package			Event Espresso
 * @author			Oluwafemi Fagbemi<fems.david@hotmail.com>
 * @version		 	1.1.0
 *
 * ----------------------------------------------
 */
class EE_PMT_Paystack extends EE_PMT_Base {

    /**
     * Class constructor.
     */
    public function __construct($pm_instance = NULL) {
        require_once( $this->file_folder() . 'EEG_Paystack.gateway.php' );
        $this->_gateway = new EEG_Paystack();

        $this->_requires_https = true;
        $this->_pretty_name = __('Paystack', 'event_espresso');
        $this->_template_path = $this->file_folder() . 'templates' . DS;
        $this->_default_description = __('After clicking \'Finalize Registration\', you will be forwarded to Paystack website to make your payment.', 'event_espresso');
        $this->_default_button_url = $this->file_url() . 'lib' . DS . 'paystack-logo.png';

        parent::__construct($pm_instance);
    }

    /**
     * Adds the help tab.
     *
     * @see EE_PMT_Base::help_tabs_config()
     * @return array
     */
    public function help_tabs_config() {
        return array(
            $this->get_help_tab_name() => array(
                'title' => __('Paystack Settings', 'event_espresso'),
                'filename' => 'payment_methods_overview_paystack_standard'
            )
        );
    }

    /**
     * Gets the form for all the settings related to this payment method type.
     *
     * @return EE_Payment_Method_Form
     */
    public function generate_new_settings_form() {
        EE_Registry::instance()->load_helper('Template');
        $form = new EE_Payment_Method_Form(
                array(
            'extra_meta_inputs' => array(
                'api_privatekey' => new EE_Text_Input(
                        array(
                    'html_label_text' => sprintf(__('API Private Key %s', 'event_espresso'), $this->get_help_tab_link()),
                    'required' => true)
                )
            )
                )
        );

        //die();
        return $form;
    }

    /**
     * 	Creates a billing form for this payment method type.
     *
     * 	@param \EE_Transaction $transaction
     * 	@return \EE_Billing_Info_Form
     */
    public function generate_new_billing_form(EE_Transaction $transaction = null) {


        $allowed_types = $this->_pm_instance->get_extra_meta('credit_card_types', TRUE, array());
        $billing_form = new EE_Billing_Attendee_Info_Form(
                $this->_pm_instance, array(
                )
        );
        return false;
    }

}
