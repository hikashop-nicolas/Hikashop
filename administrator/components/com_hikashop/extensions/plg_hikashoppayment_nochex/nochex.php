<?php
/**
 * @package	HikaShop for Joomla!
 * @version	3.2.1
 * @author	hikashop.com
 * @copyright	(C) 2010-2017 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?>

<?php
class plgHikashoppaymentNochex extends hikashopPaymentPlugin
{
	var $accepted_currencies = array('GBP');
	var $multiple = true;
	var $name = 'nochex';
	var $doc_form = 'nochex';
	var $pluginConfig = array(			
		'email' => array('Email Address', 'input'),
		'merchant_id' => array('Nochex Merchant ID / Email Address', 'input'),	
		'test_mode' => array('Nochex Test Mode', 'boolean','0'),
		'hide_mode' => array('Hide Billing', 'boolean','0'),
		'postage_mode' => array('Postage', 'boolean','0'),
		'callback_mode' => array('Callback', 'boolean','0'),
		'xmlCollect' => array('Detail Product Information', 'boolean','0')
	);
	
	function __construct(&$subject, $config) {
		parent::__construct($subject, $config);
	}

	function onBeforeOrderCreate(&$order,&$do){
		if(parent::onBeforeOrderCreate($order, $do) === true)
			return true;

		if(empty($this->payment_params->email)) {
			$this->app->enqueueMessage('Please check your Nochex plugin configuration');
			$do = false;
		}
	}

	function onAfterOrderConfirm(&$order, &$methods, $method_id) {
		parent::onAfterOrderConfirm($order, $methods, $method_id);
		
		if($this->payment_params->test_mode == 1){
			$testMode = "100";		
		}else{
			$testMode = "";		
		}
		
		if($this->payment_params->hide_mode == 1){
			$hideMode = true;		
		}else{
			$hideMode = false;		
		}
		
		$merchantID = $this->payment_params->merchant_id;
		
		$billing_fullname = $order->cart->billing_address->address_firstname . ', '. $order->cart->billing_address->address_lastname;
		$billing_address = $order->cart->billing_address->address_street;
		$billing_city = $order->cart->billing_address->address_city;
		$billing_country = $order->cart->billing_address->address_country->zone_name;
		$billing_postcode = $order->cart->billing_address->address_post_code;
		
		$customer_phone_number = $order->cart->billing_address->address_telephone;
		$email_address = $this->user->user_email;
		
		$delivery_fullname = $order->cart->shipping_address->address_firstname . ', '. $order->cart->shipping_address->address_lastname;
		$delivery_address = $order->cart->shipping_address->address_street;
		$delivery_city = $order->cart->shipping_address->address_city;
		$delivery_country = $order->cart->shipping_address->address_country->zone_name;
		$delivery_postcode = $order->cart->shipping_address->address_post_code;
		
		$callback_url = HIKASHOP_LIVE.'index.php?notif_payment='.$this->name.'&option=com_hikashop&task=notify&ctrl=checkout&tmpl=component&lang='.$this->locale . $this->url_itemid; 

		$success_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id.$this->url_itemid;
		$cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$order->order_id.$this->url_itemid;
		
		if($this->payment_params->postage_mode == 1){		
			$price = round($order->cart->full_total->prices[0]->price_value_without_shipping_with_tax,(int)$this->currency->currency_locale['int_frac_digits'],2);		
			$postage = round($order->cart->full_total->prices[0]->price_value_with_tax - $order->cart->full_total->prices[0]->price_value_without_shipping_with_tax,(int)$this->currency->currency_locale['int_frac_digits'],2);
		}else{		
			$price = round($order->cart->full_total->prices[0]->price_value_with_tax,(int)$this->currency->currency_locale['int_frac_digits'],2);		
			$postage = 0.00;		
		}
		
		if(strpos($price,'.')){
			$price =rtrim(rtrim($price, '0'), '.');
		}
				
		$desc = "";
		$xmlCollection = "<items>";
		
		foreach($order->cart->products as $product) {		
			$desc .= "" . $product->order_product_name . " - " . number_format($product->order_product_price, 2, '.', '' ) ." X ". $product->order_product_quantity;
			$xmlCollection .= "<item><id></id><name>" . $product->order_product_name . "</name><description>" . $product->order_product_name . "</description><quantity>". $product->order_product_quantity ."</quantity><price>" . number_format($product->order_product_price, 2, '.', '' ) ."</price></item>";		
		}
		
		$xmlCollection .= "</items>";
		
		if($this->payment_params->xmlCollect == 1){
			$desc = "Order Created: ".$order->order_id; 	
		}else{
			$xmlCollection = "";		
		}
		
		if($this->payment_params->callback_mode == 1){
			$callback_enabled = "ENABLED";
		}else{
			$callback_enabled = "DISABLED";
		}
		
		$vars = array( 
			"merchant_id" => $merchantID,
			"order_id" => $order->order_id,
			"amount" => number_format($price, 2, '.', '' ),
			"postage" => number_format($postage, 2, '.', '' ),
			"description" => $desc,
			"xml_item_collection" => $xmlCollection,
			"billing_fullname" => $billing_fullname,
			"billing_address" => $billing_address,
			"billing_city" => $billing_city,
			"billing_country" => $billing_country,
			"billing_postcode" => $billing_postcode,
			"delivery_fullname" => $delivery_fullname,
			"delivery_address" => $delivery_address,
			"delivery_city" => $delivery_city,
			"delivery_country" => $delivery_country,
			"delivery_postcode" => $delivery_postcode,
			"customer_phone_number" => $customer_phone_number,
			"email_address" => $email_address,
			"hide_billing_details" => $hideMode,
			"test_transaction" => $testMode,
			"test_success_url" => $success_url,
			"success_url" => $success_url,
			"cancel_url" => $cancel_url,
			"callback_url" => $callback_url,
			"optional_2" => $callback_enabled
		);
		
		$this->vars = $vars;
				
		return $this->showPage('end');
	}

	function onPaymentNotification(&$statuses){
		
	if(isset($_POST["optional_2"]) == "ENABLED"){
	
		
	$dbOrder = $this->getOrder($_POST["order_id"]);

	// Get the POST information from Nochex server
	$postvars = http_build_query($_POST);

	// Set parameters for the email 
	$url = "https://secure.nochex.com/callback/callback.aspx"; 

	// Curl code to post variables back
	$ch = curl_init(); // Initialise the curl tranfer
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($ch, CURLOPT_POST, true);
	curl_setopt ($ch, CURLOPT_POSTFIELDS, $postvars); // Set POST fields 
	curl_setopt ($ch, CURLOPT_SSLVERSION, 6); // set openSSL version variable to CURL_SSLVERSION_TLSv1
	$output = curl_exec($ch); // Post back
	curl_close($ch);

	// Put the variables in a printable format for the email
	$debug = "IP -> " . $_SERVER['REMOTE_ADDR'] ."\r\n\r\nPOST DATA:\r\n"; 
	foreach($_POST as $Index => $Value) 

	$debug .= "$Index -> $Value\r\n"; 
	$debug .= "\r\nRESPONSE:\r\n$output";
 
	//If statement
	if (!strstr($output, "AUTHORISED")) {  // searches response to see if AUTHORISED is present if it isn’t a failure message is displayed
		$msg = "Callback was not AUTHORISED.";//\r\n\r\n$debug";  // displays debug message 
	} else { 
		$msg = "Callback was AUTHORISED.";//\r\n\r\n$debug"; // if AUTHORISED was found in the response then it was successful 
	}
	
	 	$mailer = JFactory::getMailer();
		$config =& hikashop_config();
		$sender = array(
				$config->get('from_email')
		);
		$mailer->setSender($sender);
		$mailer->addRecipient(explode(',',$config->get('payment_notification_email')));
		
		$mailer->setSubject("Callback was " . $output);
		$body = $msg;
		$mailer->setBody($body);
		$mailer->Send();
		
	 	$history = new stdClass();
		$history->notified = 0;
		$history->data = 'Nochex Transaction ID: '.$_POST['transaction_id'] . ', ' . $msg;
	 	 
		 
		 
		 
		 
	 	$this->modifyOrder($_POST["order_id"], "Confirmed", $history, $mailer);
	
	}else{
		
	$dbOrder = $this->getOrder($_POST["order_id"]);

	// Get the POST information from Nochex server
	$postvars = http_build_query($_POST);

	// Set parameters for the email
	$url = "https://www.nochex.com/apcnet/apc.aspx";

	// Curl code to post variables back
	$ch = curl_init(); // Initialise the curl tranfer
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($ch, CURLOPT_POST, true);
	curl_setopt ($ch, CURLOPT_POSTFIELDS, $postvars); // Set POST fields 
	curl_setopt ($ch, CURLOPT_SSLVERSION, 6); // set openSSL version variable to CURL_SSLVERSION_TLSv1
	$output = curl_exec($ch); // Post back
	curl_close($ch);

	// Put the variables in a printable format for the email
	$debug = "IP -> " . $_SERVER['REMOTE_ADDR'] ."\r\n\r\nPOST DATA:\r\n"; 
	foreach($_POST as $Index => $Value) 

	$debug .= "$Index -> $Value\r\n"; 
	$debug .= "\r\nRESPONSE:\r\n$output";
 
	//If statement
	if (!strstr($output, "AUTHORISED")) {  // searches response to see if AUTHORISED is present if it isn’t a failure message is displayed
		$msg = "APC was not AUTHORISED.";//\r\n\r\n$debug";  // displays debug message 
	} else { 
		$msg = "APC was AUTHORISED.";//\r\n\r\n$debug"; // if AUTHORISED was found in the response then it was successful 
	}
	
	 	$mailer = JFactory::getMailer();
		$config =& hikashop_config();
		$sender = array(
				$config->get('from_email')
		);
		$mailer->setSender($sender);
		$mailer->addRecipient(explode(',',$config->get('payment_notification_email')));
		
		$mailer->setSubject("Callback was " . $output);
		$body = $msg;
		$mailer->setBody($body);
		$mailer->Send();
		
	 	$history = new stdClass();
		$history->notified = 0;
		$history->data = 'Nochex Transaction ID: '.$_POST['transaction_id'] . ', ' . $msg;
	 	 
	 	$this->modifyOrder($_POST["order_id"], "Confirmed", $history, $mailer);
	
	}
	
	}

}
