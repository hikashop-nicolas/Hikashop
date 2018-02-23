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
	var $accepted_currencies = array(
		'AUD','BRL','CAD','EUR','GBP','JPY','USD','NZD','CHF','HKD','SGD','SEK',
		'DKK','PLN','NOK','HUF','CZK','MXN','MYR','PHP','TWD','THB','ILS','TRY',
		'RUB'
	);
	var $multiple = true;
	var $name = 'nochex';
	var $doc_form = 'nochex';
	var $pluginConfig = array(			
		'email' => array('NOCHEX_EMAIL', 'input'),
		'merchant_id' => array('NOCHEX_MERCHANT_ID', 'input'),	
		'test_mode' => array('Nochex Test Mode', 'boolean','0'),
		'hide_mode' => array('Hide Billing', 'boolean','0'),
		'postage_mode' => array('Postage', 'boolean','0'),
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
		
		$merchantID=$this->payment_params->merchant_id;
		
		$notify_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment='.$this->name.'&tmpl=component&lang='.$this->locale . $this->url_itemid;
		$return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$order->order_id . $this->url_itemid;
		$cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$order->order_id . $this->url_itemid;
		
		$billing_fullname = $order->cart->billing_address->address_firstname . ', '. $order->cart->billing_address->address_lastname;
		$billing_address = $order->cart->billing_address->address_street;
		$billing_city = $order->cart->billing_address->address_city;
		$billing_postcode = $order->cart->billing_address->address_post_code;
		
		$customer_phone_number = $order->cart->billing_address->address_telephone;
		$email_address = $this->user->user_email;
		
		$delivery_fullname = $order->cart->shipping_address->address_firstname . ', '. $order->cart->shipping_address->address_lastname;
		$delivery_address = $order->cart->shipping_address->address_street;
		$delivery_city = $order->cart->shipping_address->address_city;
		$delivery_postcode = $order->cart->shipping_address->address_post_code;
		
		$callback_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment='.$this->name.'&tmpl=component&lang='.$this->locale . $this->url_itemid;
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
		
		?>
		
	<script type="text/javascript">			
	(function($){								
	$(document).ready(function(){									
	$("#submit_nochex_payment_form").click();								
	})							
	})(jQuery);			
	</script>
	
	<?php
		
		$paymentForm = "
		<form method=\"post\" action=\"https://secure.nochex.com/default.aspx\" id=\"nochex_payment_form\">
		<input type=\"hidden\" value=\"".$merchantID."\" name=\"merchant_id\" />
		<input type=\"hidden\" value=\"".$order->order_id."\" name=\"order_id\" />
		<input type=\"hidden\" value=\"".number_format($price, 2, '.', '' )."\" name=\"amount\" />
		<input type=\"hidden\" value=\"".number_format($postage, 2, '.', '' )."\" name=\"postage\" />
		<input type=\"hidden\" value=\"".$desc."\" name=\"description\" />
		<input type=\"hidden\" value=\"".$xmlCollection."\" name=\"xml_item_collection\" />
		<input type=\"hidden\" value=\"".$billing_fullname."\" name=\"billing_fullname\" />
		<input type=\"hidden\" value=\"".$billing_address."\" name=\"billing_address\" />
		<input type=\"hidden\" value=\"".$billing_city."\" name=\"billing_city\" />
		<input type=\"hidden\" value=\"".$billing_postcode."\" name=\"billing_postcode\" />
		<input type=\"hidden\" value=\"".$delivery_fullname."\" name=\"delivery_fullname\" />
		<input type=\"hidden\" value=\"".$delivery_address."\" name=\"delivery_address\" />
		<input type=\"hidden\" value=\"".$delivery_city."\" name=\"delivery_city\" />
		<input type=\"hidden\" value=\"".$delivery_postcode."\" name=\"delivery_postcode\" />
		<input type=\"hidden\" value=\"".$customer_phone_number."\" name=\"customer_phone_number\" />
		<input type=\"hidden\" value=\"".$email_address."\" name=\"email_address\" />
		<input type=\"hidden\" value=\"".$success_url."\" name=\"test_success_url\" />
		<input type=\"hidden\" value=\"".$success_url."\" name=\"success_url\" />
		<input type=\"hidden\" value=\"".$cancel_url."\" name=\"cancel_url\" />
		<input type=\"hidden\" value=\"".$callback_url."\" name=\"callback_url\" />
		<input type=\"submit\" id=\"submit_nochex_payment_form\" />
		</form>";
				
		echo $paymentForm;
		
		return false;

	}


	function onPaymentNotification(&$statuses){
		
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
	 	 
	 	$this->modifyOrder($_POST["order_id"], "Confirmed", true, $mailer);
	
	}

}
