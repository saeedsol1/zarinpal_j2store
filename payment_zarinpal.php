<?php
/**
 * Created by PhpStorm.
 * User: mohsen
 * Date: 5/8/16
 * Time: 2:33 PM
 */
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');
require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/helpers/j2store.php');

class plgJ2StorePayment_zarinpal extends J2StorePaymentPlugin
{
    /**
     * @var $_element  string  Should always correspond with the plugin's filename,
     *                         forcing it to be unique
     */
    var $_element   = 'payment_zarinpal';
    private $merchantCode = '';
    private $callBackUrl = '';
    private $redirectToZarinpal = '';

    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage( '', JPATH_ADMINISTRATOR );
        $this->merchantCode = trim($this->params->get('merchant_id'));
        $this->callBackUrl = JUri::root().'/index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=payment_zarinpal&paction=callback';
        $this->redirectToZarinpal = 'https://www.zarinpal.com/pg/StartPay/';
    }

    public function _renderForm( $data )
    {
        $vars = new JObject();
        $vars->message = JText::_("J2STORE_ZARINPAL_PAYMENT_MESSAGE");
        $html = $this->_getLayout('form', $vars);
        return $html;
    }

    public function _prePayment($data)
    {
        $vars = new StdClass();
        $vars->display_name = $this->params->get('display_name', '');
        $vars->onbeforepayment_text = JText::_("J2STORE_ZARINPAL_PAYMENT_PREPARATION_MESSAGE");

        $amount = $data['orderpayment_amount'] / 10;
        $amount = (int)$amount;
        $merchantCode = trim($this->params->get('merchant_id'));
        $desc = "پرداخت سفارش شماره " . $data['order_id'] . " به مبلغ "  . $amount . " با زرین پال";
        $email = '';
        $mobile = '';
        $zpRequestContext = array(
            'MerchantID' => $merchantCode,
            'Amount' => $amount,
            'Description' => $desc,
            'Email' => $email,
            'Mobile' => $mobile,
            'CallbackURL' => $this->callBackUrl
        );

        $request = $this->requestZarinpal($zpRequestContext);

        if(is_array($request) and array_key_exists('error', $request)){
            $vars->error = $request['error'];
            $html = $this->_getLayout('prepayment', $vars);
            return $html;
        }

        if($request->Status == 100){
            $authority = $request->Authority;
            $vars->redirectToZP = $this->redirectToZarinpal . $authority;
            $html = $this->_getLayout('prepayment', $vars);
            return $html;
        }

        $vars->error = $this->statusText($request->Status);
        $html = $this->_getLayout('prepayment', $vars);
        return $html;
    }

    public function _postPayment($data)
    {
        $vars = new JObject();
        //get order id
        $orderId = $data['order_id'];
        // get instatnce of j2store table
        F0FTable::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_j2store/tables');
        $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
        $order->load(array('order_id' => $orderId));

        if($order->load(array('order_id' => $orderId))){

            $currency = J2Store::currency();
            $currencyValues= $this->getCurrency($order);
            $orderPaymentAmount = $currency->format($order->order_total, $currencyValues['currency_code'], $currencyValues['currency_value'], false);
            $orderPaymentAmount = (int)($orderPaymentAmount / 10);

            $order->add_history(JText::_('J2STORE_CALLBACK_RESPONSE_RECEIVED'));

            $merchantCode = $this->params->get('merchant_id');

            $app = JFactory::getApplication();
            $authority = $app->input->getString('Authority');

            $zpVerifyContext = array(
                'MerchantID' => $merchantCode,
                'Amount' => (int)$orderPaymentAmount,
                'Authority' => $authority
            );

            $validate = $this->validateZarinpal($zpVerifyContext);

            if(is_array($validate) and array_key_exists('error', $validate)){
                $vars->message = $validate['error'];
                $html = $this->_getLayout('postpayment', $vars);
                // $app->close();
                return $html;
            }

            if($validate->Status == 100){
                $order->payment_complete();
                $order->empty_cart();
                $message = JText::_("J2STORE_ZARINPAL_PAYMENT_SUCCESS") . "\n";
                $message .= JText::_("J2STORE_ZARINPAL_PAYMENT_ZP_REF") . $validate->RefID;
                $vars->message = $message;
                $html = $this->_getLayout('postpayment', $vars);
                // $app->close();
                return $html;
            }

            $message = JText::_("J2STORE_ZARINPAL_PAYMENT_FAILED") . "\n";
            $message .= JText::_("J2STORE_ZARINPAL_PAYMENT_ERROR");
            $message .= $this->statusText($validate->Status) . "\n";
            $message .= JText::_("J2STORE_ZARINPAL_PAYMENT_CONTACT") . "\n";
            $vars->message = $message;
            $html = $this->_getLayout('postpayment', $vars);
            // $app->close();
            return $html;

        }

        $vars->message = JText::_("J2STORE_ZARINPAL_PAYMENT_PAGE_ERROR");
        $html = $this->_getLayout('postpayment', $vars);
        return $html;
    }

    private function requestZarinpal($params = [])
    {
        try{
            $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
        } catch(SoapFault $e){
            return ['error' => $e->getMessage()];
        }
        return $client->PaymentRequest($params);
    }

    private function validateZarinpal($params = [])
    {
        try{
            $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
        } catch(SoapFault $e){
            return ['error' => $e->getMessage()];
        }
        return $client->PaymentVerification($params);
    }

    private function statusText($status)
    {
        return JText::_("J2STORE_ZARINPAL_PAYMENT_STATUS_" . $status );
    }
}