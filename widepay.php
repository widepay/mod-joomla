<?php

if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 * @version $Id: /widepay.php,v 1.4 2013/05/20
 *
 * a special type of 'cash on delivey': *
 * @author Gabriel P
 * @co-author Max Milbers, Valérie Isaksen ( original plugin )
 * @version $Id: /home/components/com_virtuemart 5122 2011-12-18 22:24:49Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

if (!class_exists('shopFunctions'))
    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');

if (!class_exists('WidePay')) {
    require('widepay/widepay.php');
}

class plgVmPaymentWidepay extends vmPSPlugin
{

    // instance of class
    public static $_this = false;

    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * @author Valérie Isaksen
     */
    protected function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment WidePay Table');
    }

    /**
     * Fields to create the payment table
     * @return string SQL Fileds
     */
    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'bigint(15) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'payment_currency' => 'char(3) ',
            'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
            'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
            'tax_id' => 'smallint(11) DEFAULT NULL'
        );

        return $SQLfields;
    }

    /**
     * @param $name
     * @param $id
     * @param $data
     * @return bool
     */
    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    function getPluginParams()
    {
        $db = JFactory::getDbo();
        $sql = "select virtuemart_paymentmethod_id from #__virtuemart_paymentmethods where payment_element = 'widepay'";
        $db->setQuery($sql);
        $id = (int)$db->loadResult();
        return $this->getVmPluginMethod($id);
    }

    /**
     *
     *
     * @author Valérie Isaksen
     */
    function plgVmConfirmedOrder($cart, $order)
    {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;

        $html = "";

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        $this->getPaymentCurrency($method);
        // END printing out HTML Form code (Payment Extra Info)
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

        $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_name'] = $this->renderPluginName($method);
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = (!empty($method->cost_per_transaction) ? $method->cost_per_transaction : 0);
        $dbValues['cost_percent_total'] = (!empty($method->cost_percent_total) ? $method->cost_percent_total : 0);
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);

        $html = $this->retornaHtmlPagamento($order, $method);

        JFactory::getApplication()->enqueueMessage(utf8_encode(
            "Seu pedido foi realizado com sucesso. Informe o seu cpf para gerar sua fatura."
        ));

        $novo_status = $method->status_aguardando;
        return $this->processConfirmedOrderPaymentResponse(1, $cart, $order, $html, $dbValues['payment_name'], $novo_status);

    }

    function retornaHtmlPagamento($order, $method)
    {
        $lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);

        if (isset($order["details"]["ST"])) {
            $endereco = "ST";
        } else {
            $endereco = "BT";
        }


        $items = array();
        $i = 1;
        $items[$i]['descricao'] = 'Fatura Emitida';
        $items[$i]['valor'] = number_format($order['details'][$endereco]->order_total, 2, '.', '');
        $items[$i]['quantidade'] = 1;
        $i++;
        $frete = $order["details"][$endereco]->order_shipment;
        if (isset($frete) && $frete > 0) {
            $items[$i]['descricao'] = 'Frete';
            $items[$i]['valor'] = number_format($frete, 2, '.', '');
            $items[$i]['quantidade'] = 1;
            $i++;
        }
        if (isset($order["details"][$endereco]->coupon_discount) && $order["details"][$endereco]->coupon_discount != 0) {
            $items[$i]['descricao'] = 'Desconto';
            $items[$i]['valor'] = number_format($order["details"]['BT']->coupon_discount, 2, '.', '');
            $items[$i]['quantidade'] = 1;
            $i++;
        }


        //////------

        $cod_estado = (!empty($order["details"][$endereco]->virtuemart_state_id) ? $order["details"][$endereco]->virtuemart_state_id : $order["details"][$endereco]->virtuemart_state_id);
        $estado = ShopFunctions::getStateByID($cod_estado, "state_2_code");

        $invoiceDuedate = new DateTime(date('Y-m-d'));
        $invoiceDuedate->modify('+' . intval($method->WIDE_PAY_VALIDADE) . ' day');
        $invoiceDuedate = $invoiceDuedate->format('Y-m-d');


        $widepayData = array(
            'forma' => $this->widepay_get_formatted_way(trim($method->WIDE_PAY_WAY)),
            'referencia' => $order["details"][$endereco]->order_number,
            'notificacao' => JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&notificationTask=pluginnotification&order_number=' . $order['details']['BT']->order_number,
            'vencimento' => $invoiceDuedate,
            'cliente' => $order["details"][$endereco]->first_name . ' ' . $order["details"][$endereco]->last_name,
            'email' => $order["details"][$endereco]->email,
            'enviar' => 'E-mail',
            'endereco' => array(
                'rua' => $order["details"][$endereco]->address_1,
                'complemento' => '', JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&notificationTask=pluginnotification&order_number=' . $order['details']['BT']->order_number,
                'cep' => preg_replace('/\D/', '', $order["details"][$endereco]->zip),
                'estado' => $estado,
                'cidade' => $order["details"][$endereco]->city
            ),
            'itens' => $items,
            'boleto' => array(
                'gerar' => 'Nao',
                'desconto' => 0,
                'multa' => doubleval($method->WIDE_PAY_FINE),
                'juros' => doubleval($method->WIDE_PAY_INTEREST)
            )
        );

        $url = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&notificationTask=pluginnotification&order_number=' . $order['details']['BT']->order_number . '&acao=adicionar';
        $form = '';
        $form .= '<form name="payment_form" action="' . $url . '" method="post">' . PHP_EOL;
        $form .= '<input type="hidden" name="acao" value="adicionar">' . PHP_EOL;
        $form .= '<input type="hidden" name="dados" value="' . urlencode(serialize($widepayData)) . '">' . PHP_EOL;
        $form .= '<label for="cpf_cnpj">CPF ou CNPJ:' . PHP_EOL;
        $form .= '<input id="cpf_cnpj" type="text" name="cpf_cnpj" value="">' . PHP_EOL;
        $form .= '<input class="bb-button bb-button-submit" type="submit" value="Pagar com Wide Pay" id="payment_button"></button>' . PHP_EOL;
        $form .= '</form>' . PHP_EOL . PHP_EOL;

        return $form;
    }

    private function widepay_get_formatted_way($way)
    {
        $key_value = array(
            'cartao' => 'Cartão',
            'boleto' => 'Boleto',
            'boleto_cartao' => 'Cartão,Boleto',

        );
        return $key_value[$way];
    }

    /**
     * Display stored payment data for an order
     *
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
    {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return null; // Another method was selected, do nothing
        }

        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
            . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);
        if (!($paymentTable = $db->loadObject())) {
            vmWarn(500, $q . " " . $db->getErrorMsg());
            return '';
        }
        $this->getPaymentCurrency($paymentTable);

        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        $html .= '</table>' . "\n";
        return $html;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }


    function setCartPrices(VirtueMartCart $cart, &$cart_prices, $method, $progressive = true)
    {
        if ($method->modo_calculo_desconto == '2') {
            return parent::setCartPrices($cart, $cart_prices, $method, false);
        } else {
            return parent::setCartPrices($cart, $cart_prices, $method, true);
        }
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @param $cart_prices : cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     * @author: Valerie Isaksen
     *
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {

        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
        $method->min_amount = (!empty($method->min_amount) ? $method->min_amount : 0);
        $method->max_amount = (!empty($method->max_amount) ? $method->max_amount : 0);

        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount and $amount <= $method->max_amount
            or
            ($method->min_amount <= $amount and ($method->max_amount == 0)));
        if (!$amount_cond) {
            return false;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id']))
            $address['virtuemart_country_id'] = 0;
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            return true;
        }

        return false;
    }

    /*
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @param VirtueMartCart $cart : the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     * @author Valerie Isaksen
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $orderModel = VmModel::getModel('orders');
        $orderDetails = $orderModel->getOrder($virtuemart_order_id);
        if (!($method = $this->getVmPluginMethod($orderDetails['details']['BT']->virtuemart_paymentmethod_id))) {
            return false;
        }

        $view = JRequest::getVar('view');
        // somente retorna se estiver como transa��o pendente
        if ($method->status_aguardando == $orderDetails['details']['BT']->order_status and $view == 'orders' and $orderDetails['details']['BT']->virtuemart_paymentmethod_id == $virtuemart_paymentmethod_id) {
            JFactory::getApplication()->enqueueMessage(utf8_encode(
                    "O pagamento deste pedido consta como Pendente de pagamento ainda. Clicando no bot&atilde; logo abaixo, voc&ecirc; ser&aacute; redirecionado para o site do WidePay, onde efetuar&aacute; o pagamento da sua compra.")
            );
            $html = $this->retornaHtmlPagamento($orderDetails, $method);
            echo $html;
        }

        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers
     *
     * public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
     * return null;
     * }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    //Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk
     *
     * public function plgVmOnUpdateOrderPayment(&$_formData)
     * {
     * return null;
     * }
     */

    /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @param $return_context : it was given and sent in the payment form. The notification should return it back.
     * Used to know which cart should be emptied, in case it is still in the session.
     * @param int $virtuemart_order_id : payment  order id
     * @param char $new_status : new_status for this order id.
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     * @author Oscar van Eijk
     *
     * public function plgVmOnUpdateOrderLine(  $_formData) {
     * return null;
     * }
     *
     * /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @author Oscar van Eijk
     *
     * public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
     * return null;
     * }
     *
     * /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @author Oscar van Eijk
     *
     * public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
     * return null;
     * }
     *
     * /**
     * This event is fired when the  method notifies you when an event occurs that affects the order.
     * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
     * such as refunds, disputes, and chargebacks.
     *
     * NOTE for Plugin developers:
     *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
     *
     * @author Valerie Isaksen
     *
     *
     * public function plgVmOnPaymentNotification() {
     * return null;
     * }
     */
    function plgVmOnPaymentNotification()
    {

        header("Status: 200 OK");
        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        $widepay_data = $_REQUEST;
        $this->logInfo('widepay_data ' . implode('   ', $widepay_data), 'message');

        $qps = 'SELECT `virtuemart_paymentmethod_id` FROM `#__virtuemart_paymentmethods` WHERE `payment_element`="widepay" ';
        $dbps = JFactory::getDBO();
        $dbps->setQuery($qps);
        $psmethod_id = $dbps->loadResult();

        $psmethod = $this->getVmPluginMethod($psmethod_id);
        if (!$this->selectedThisElement($psmethod->payment_element)) {
            return false;
        }


        if (isset($_GET['acao']) && $_GET['acao'] == 'adicionar') {
            $widepayData = unserialize(urldecode($_POST['dados']));
            $cpf_cnpj = $_POST['cpf_cnpj'];
            list($widepayCpf, $widepayCnpj, $widepayPessoa) = $this->getFiscal($cpf_cnpj);
            $widepayData['cpf'] = $widepayCpf;
            $widepayData['cnpj'] = $widepayCnpj;
            $widepayData['pessoa'] = $widepayPessoa;
            $wide_pay = new WidePay(intval($psmethod->WIDE_PAY_WALLET_ID), trim($psmethod->WIDE_PAY_WALLET_TOKEN));
            $response = $wide_pay->api('recebimentos/cobrancas/adicionar', $widepayData);

            if (!$response->sucesso) {
                $validacao = '';

                if ($response->erro) {
                    echo 'Wide Pay: Erro (' . $response->erro . ')' . '<br>';
                }

                if (isset($response->validacao)) {
                    foreach ($response->validacao as $item) {
                        $validacao .= '- ' . strtoupper($item['id']) . ': ' . $item['erro'] . '<br>';
                    }
                    echo 'Wide Pay: Erro de validação (' . $validacao . ')';
                }

            } else {
                echo "Redirecionando... " . $response->link;
                echo "<script>
                        window.location.href = \"" . $response->link . "\"
                     </script>";
            }
            return;
        }


        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["notificacao"])) {
            ob_clean();
            header("Status: 200 OK");

            $wide_pay = new WidePay(intval($psmethod->WIDE_PAY_WALLET_ID), trim($psmethod->WIDE_PAY_WALLET_TOKEN));
            $notificacao = $wide_pay->api('recebimentos/cobrancas/notificacao', array(
                'id' => $_POST["notificacao"] // ID da notificação recebido do Wide Pay via POST
            ));
            if ($notificacao->sucesso) {
                $transactionID = $notificacao->cobranca['id'];
                $status = $notificacao->cobranca['status'];
                if ($status == 'Baixado' || $status == 'Recebido' || $status == 'Recebido manualmente') {


                    $order_number = $notificacao->cobranca['referencia'];
                    $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);

                    if (!$virtuemart_order_id) {
                        return;
                    }
                    $payment = $this->getDataByOrderId($virtuemart_order_id);
                    if (!$payment) {
                        $this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
                        return null;
                    }

                    $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
                    if (!$this->selectedThisElement($method->payment_element)) {
                        return false;
                    }

                    $this->logInfo('Notification: widepay_data ' . implode(' | ', $widepay_data), 'message');


                    $order = array();

                    $new_status = $method->status_paga;
                    $order['order_status'] = $new_status;
                    $order['customer_notified'] = 1;
                    $desc_status = "Pago";

                    $order['comments'] = 'O status do seu pedido ' . $order_number . ' no WidePay foi atualizado: ' . $desc_status;


                    $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
                    $dbValues['payment_name'] = $this->renderPluginName($method);
                    $dbValues['order_number'] = $order['details']['BT']->order_number;
                    $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
                    $dbValues['cost_per_transaction'] = (!empty($method->cost_per_transaction) ? $method->cost_per_transaction : 0);
                    $dbValues['cost_percent_total'] = (!empty($method->cost_percent_total) ? $method->cost_percent_total : 0);
                    $dbValues['payment_currency'] = $currency_code_3;
                    $dbValues['payment_order_total'] = $notificacao->cobranca['recebido'];
                    $dbValues['tax_id'] = $method->tax_id;
                    $this->storePSPluginInternalData($dbValues);

                    $this->logInfo('plgVmOnPaymentNotification return new_status:' . $new_status, 'message');

                    if ($virtuemart_order_id) {
                        // send the email only if payment has been accepted
                        if (!class_exists('VirtueMartModelOrders'))
                            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
                        $modelOrder = new VirtueMartModelOrders();
                        $orderitems = $modelOrder->getOrder($virtuemart_order_id);
                        $nb_history = count($orderitems['history']);

                        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
                        if ($nb_history == 1) {
                            if (!class_exists('shopFunctionsF'))
                                require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
                            shopFunctionsF::sentOrderConfirmedEmail($orderitems);
                            $this->logInfo('Notification, sentOrderConfirmedEmail ' . $order_number . ' ' . $new_status, 'message');
                        }
                    }
                }


            } else {
                echo $notificacao->erro; // Erro
            }
        }



    }

    private function getFiscal($cpf_cnpj)
    {
        $cpf_cnpj = preg_replace('/\D/', '', $cpf_cnpj);
        // [CPF, CNPJ, FISICA/JURIDICA]
        if (strlen($cpf_cnpj) == 11) {
            return array($cpf_cnpj, '', 'Física');
        } else {
            return array('', $cpf_cnpj, 'Jurídica');
        }
    }

    /**
     * plgVmOnPaymentResponseReceived
     * This event is fired when the  method returns to the shop after the transaction
     *
     *  the method itself should send in the URL the parameters needed
     * NOTE for Plugin developers:
     *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
     *
     * @param int $virtuemart_order_id : should return the virtuemart_order_id
     * @param text $html : the html to display
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     * @author Valerie Isaksen
     *
     *
     * function plgVmOnPaymentResponseReceived(, &$virtuemart_order_id, &$html) {
     * return null;
     * }
     */
    // retorno da transacao para o pedido espec�fico
    function plgVmOnPaymentResponseReceived(&$html)
    {
        //We delete the old stuff
        // get the correct cart / session
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return true;
    }

}

// No closing tag
