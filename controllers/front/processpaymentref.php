<?php

/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2017 PrestaShop SA
 *  @version  Release: $Revision: 13573 $
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

/**
 *
 * @since 1.5.0
 */
require_once dirname ( __FILE__ ) . '/../../ApiRedsysREST/initRedsysApi.php';

class RedsyspurProcessPaymentRefModuleFrontController extends ModuleFrontController {
	public function initContent() {
		if (session_status () != PHP_SESSION_ACTIVE)
			session_start ();
		
		if (! $this->module->active || ! isset ( $_POST ["idCart"] )) {
			header ( $_SERVER ['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400 );
			die ( $_SERVER ['SERVER_PROTOCOL'] . ' 400 Bad Request: disabled payment module or incomplete request' );
		}
		$cart = new Cart ( ltrim ( $_POST ["idCart"], "0" ) );
		
		$this->businessLogic ( $cart );
	}
	private function businessLogic($cart) {

		$redsys = new redsyspur();

		// Para un nuevo pedido, creamos un nuevo idLog
		$idLog = generateIdLog( Configuration::get( 'REDSYS_LOG' ), Configuration::get( 'REDSYS_LOG_STRING'),  $_POST ["merchant_order"] );
		escribirLog("INFO ", $idLog, "Procesando orden usando pago por referencia", null, __METHOD__);
		
		$response = array (
				"redir" => true,
				"url" => $this->module->_endpoint_paymentko 
		);
		
		$params = $this->createParameters ( $_POST ["idCart"], $redsys->getCustomerRef ( $cart->id_customer ) [0] );
		$result = $this->performRequest ( $params, $cart, $idLog );

		$resultCode = $result->getResult ();
		$apiCode = $result->getApiCode ();
		$authCode = $result->getAuthCode ();

		$respuestaSIS = $redsys->checkRespuestaSIS($apiCode, $authCode);

		$customer = new Customer ( $cart->id_customer );
		if ($resultCode == RESTConstants::$RESP_LITERAL_OK) {

			$response ["redir"] = true;
			$response ["url"] = $redsys->validateCart ( $cart, $_POST ["merchant_order"], $params ["idCart"], $customer, $params ["amount"], $params ["idCurrency"], $result->getOperation ()->getMerchantIdentifier (), $result->getOperation ()->getCardNumber (), $result->getOperation()->getCardBrand(), $result->getOperation()->getCardType(), $authCode, $respuestaSIS[1], $idLog);
			
			escribirLog("INFO ", $idLog, "Orden validada", null, __METHOD__);
			escribirLog("INFO ", $idLog, $respuestaSIS[0]);

		} else {
			if ($resultCode == RESTConstants::$RESP_LITERAL_AUT) {
				escribirLog("DEBUG", $idLog, "AUT // La operación requiere de autenticación", null, __METHOD__);

				$_SESSION ["REDSYS_oper"] = serialize ( $result->getOperation () );
				$_SESSION ["REDSYS_pareq"] = $result->getOperation ()->getPaRequest ();
				$_SESSION ["REDSYS_urlacs"] = $result->getOperation ()->getAcsUrl ();
				$_SESSION ["REDSYS_md"] = $result->getOperation ()->getAutSession ();

				$response ["redir"] = false;
				$response ["url"] = $this->module->_endpoint_securepayment;
			} else { //FLUJO KO
				if (Configuration::get ( 'REDSYS_URLTPV_INSITE' ) == "1")
					$redsys->setRedsysCookie($_POST ["idCart"]);

				$redsys->validateOrder($params ["idCart"], _PS_OS_CANCELED_, $params ["amount"]/100, "Redsys - Tarjeta", null);
				$redsys->addPaymentInfo($params ["idCart"], $params ["merchant_order"], $respuestaSIS[1], $idLog);

				escribirLog("INFO ", $idLog, $respuestaSIS[0]);
			}
		}
		
		die ( Tools::jsonEncode ( $response ) );
	}
	private function performRequest($params, $cart, $idLog) {
		$request = new RESTOperationMessage ();
		$request->setAmount ( ( int ) number_format ( $params ["amount"], $params ["decimals"], '', '' ) );
		$request->setCurrency ( $params ["currency"] );
		$request->setMerchant ( Configuration::get ( 'REDSYS_FUC_TARJETA_INSITE' ) );
		$request->setTerminal ( Configuration::get ( 'REDSYS_TERMINAL_TARJETA_INSITE' ) );
		$request->setOrder ( $params ["idCart"] );
		$request->useReference ( $params ["reference"] );
		$request->setTransactionType ( 0 );
		$request->useDirectPayment ();
		$request->addParameter ( "DS_MERCHANT_TITULAR", $params ["customer"] );
		// $request->addParameter ( "DS_MERCHANT_CLIENTIP", $_SERVER['REMOTE_ADDR'] );
		$request->addParameter ( "DS_MERCHANT_PRODUCTDESCRIPTION", $params ["products"] );
		$request->addParameter ( "DS_MERCHANT_MODULE", "PR-PURv" . $this->module->version );
		
		$service = new RESTOperationService ( Configuration::get ( 'REDSYS_CLAVE256_TARJETA_INSITE' ), Configuration::get ( 'REDSYS_URLTPV_INSITE' ) );
		$result = $service->sendOperation ( $request, $idLog );
		
		return $result;
	}
	private function createParameters($idCart, $reference) {
		$params = array ();
		
		$cart = new Cart ( ltrim ( $idCart, "0" ) );
		$customer = new Customer ( $cart->id_customer );
		
		// Calculate Amount
		$currency = new Currency ( $cart->id_currency );
		$currency_decimals = is_array ( $currency ) ? ( int ) $currency ['decimals'] : ( int ) $currency->decimals;
		$cart_details = $cart->getSummaryDetails ( null, true );
		$decimals = $currency_decimals * _PS_PRICE_DISPLAY_PRECISION_;
		$shipping = $cart_details ['total_shipping_tax_exc'];
		$subtotal = $cart_details ['total_price_without_tax'] - $cart_details ['total_shipping_tax_exc'];
		$tax = $cart_details ['total_tax'];
		$total_price = Tools::ps_round ( $shipping + $subtotal + $tax, $decimals );
		
		// Product Description
		$products = $cart->getProducts ();
		$productsDesc = '';
		foreach ( $products as $product )
			$productsDesc .= $product ['quantity'] . ' ' . Tools::truncate ( $product ['name'], 50 ) . ' - ';
		$productsDesc = substr ( $productsDesc, 0, strlen ( $productsDesc ) - 3 );
		
		$cust_name="";
		if($customer != null) {
			$cust_name = $customer->firstname . " " . $customer->lastname ;
		}
		
		$params ["idCart"] = str_pad ( $idCart, 12, "0", STR_PAD_LEFT );
		$params ["reference"] = $reference;
		$params ["amount"] = $total_price;
		$params ["currency"] = $currency->iso_code_num;
		$params ["idCurrency"] = $currency->id;
		$params ["decimals"] = $decimals;
		$params ["products"] = preg_replace ( "/[^ a-zA-Z0-9-]+/", "", $productsDesc );
		$params ["customer"] = preg_replace ( "/[^ a-zA-Z0-9-]+/", "", $cust_name );
		
		return $params;
	}
}
