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

class RedsyspurProcessPaymentModuleFrontController extends ModuleFrontController {
	public function initContent() {
		if (session_status () != PHP_SESSION_ACTIVE)
			session_start ();
		
		if (! $this->module->active || ! isset ( $_POST ["idCart"] ) || ! isset ( $_POST ["idOper"] )) {
			header ( $_SERVER ['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400 );
			die ( $_SERVER ['SERVER_PROTOCOL'] . ' 400 Bad Request: disabled payment module or incomplete request' );
		}
		
		if (! isset ( $_POST ["save"] )) {
			$_POST ["save"] = false;
		}
		
		$this->businessLogic ();
	}
	private function businessLogic() {
		// Para un nuevo pedido, creamos un nuevo idLog

		$redsys = new redsyspur();
		
		$idLog = generateIdLog( Configuration::get( 'REDSYS_LOG' ), Configuration::get( 'REDSYS_LOG_STRING'),  $_POST ["merchant_order"] );
		
		$response = array (
				"redir" => true,
				"url" => $this->module->_endpoint_paymentko 
		);
		
		$params = $this->createParameters ( $_POST ["merchant_order"], $_POST ["idCart"], $_POST ["idOper"], $_POST ["save"] );
		$ThreeDSParams = $_POST["valores3DS"];

		$cart = new Cart ( $params ["idCart"] );
		$customer = new Customer ( $cart->id_customer );
		//iniciapeticion, con setOperId y to eso.
		$resultInit = $this->performRequestInit ( $params, $idLog );

		if ($resultInit->getResult () == RESTConstants::$RESP_LITERAL_KO) {

			$resultCode = $resultInit->getResult ();
			$apiCode = $resultInit->getApiCode ();
			$authCode = $resultInit->getAuthCode ();
			$respuestaSIS = $redsys->checkRespuestaSIS($apiCode, $authCode);

			$redsys->validateOrder($params ["idCart"], _PS_OS_CANCELED_, $params ["amount"]/100, "Redsys - Tarjeta", null);
			$redsys->addPaymentInfo($params ["idCart"], $params ["merchant_order"], $respuestaSIS[1], $idLog);

			escribirLog("INFO ", $idLog, $respuestaSIS[0]);

		}

		$result = $this->performRequest ( $params, $cart, $resultInit, $ThreeDSParams, $idLog );
		$ThreeDSInfo = $result->protocolVersionAnalysis();

		$resultCode = $result->getResult ();
		$apiCode = $result->getApiCode ();
		$authCode = $result->getAuthCode ();

		$respuestaSIS = $redsys->checkRespuestaSIS($apiCode, $authCode);
		
		if ($resultCode == RESTConstants::$RESP_LITERAL_OK) {
			
			$response ["redir"] = true;
			$response ["url"] = $redsys->validateCart ( $cart, $params ["merchant_order"], $params ["merchant_order"], $customer, $params ["amount"], $params ["idCurrency"], $result->getOperation ()->getMerchantIdentifier (), $result->getOperation ()->getCardNumber (), $result->getOperation()->getCardBrand(), $result->getOperation()->getCardType(), $authCode, $respuestaSIS[1], $idLog);
			
			escribirLog("INFO ", $idLog, "Orden validada", null, __METHOD__);
			escribirLog("INFO ", $idLog, $respuestaSIS[0]);
		} else {
			if ($resultCode == RESTConstants::$RESP_LITERAL_AUT) {
				escribirLog("DEBUG", $idLog, "AUT // La operación requiere de autenticación", null, __METHOD__);


				$version = explode( '.', $ThreeDSInfo);
				switch ($version[0]) {

					case "1":
						escribirLog("DEBUG", $idLog, "Versión de 3DSecure: " . $ThreeDSInfo, null, __METHOD__);
						$_SESSION ["REDSYS_pareq"] = $result->getPAReqParameter ();
						$_SESSION ["REDSYS_urlacs"] = $result->getAcsURLParameter ();
						$_SESSION ["REDSYS_md"] = $result->getMDParameter ();
						
						$response ["redir"] = false;
						$response ["url"] = Redsyspur::createEndpointParams($this->module->_endpoint_securepayment, $result->getOperation (), $params ["idCart"], null, $idLog);
						escribirLog("DEBUG", $idLog, "URL con parámetros: " . $response ["url"], null, __METHOD__);
					break;
	
					case "2":
						escribirLog("DEBUG", $idLog, "Versión de 3DSecure: " . $ThreeDSInfo, null, __METHOD__);
						$_SESSION ["REDSYS_urlacs"] = $result->getAcsURLParameter ();
						$_SESSION ["REDSYS_creq"] = $result->getCreqParameter ();
						
						$response ["redir"] = false;
						$response ["url"] = $this->module->_endpoint_securepaymentv2;
					break;

					default:
						escribirLog("ERROR", $idLog, "Error en la evaluación de la versión de 3DSecure", null, __METHOD__);
						$response ["redir"] = true;
						$response ["url"] = $this->module->_endpoint_paymentko;
					break;					
				}
				
				
			} else { //FLUJO KO
				if (Configuration::get ( 'REDSYS_URLTPV_INSITE' ) == "1")
					$redsys->setRedsysCookie($_POST ["idCart"]);

				escribirLog("DEBUG", $idLog, "KO", null, __METHOD__);

				$redsys->validateOrder($params ["idCart"], _PS_OS_CANCELED_, $params ["amount"]/100, "Redsys - Tarjeta", null);
				$redsys->addPaymentInfo($params ["idCart"], $params ["merchant_order"], $respuestaSIS[1], $idLog);

				escribirLog("INFO ", $idLog, $respuestaSIS[0]);
			}
		}
		
		die ( Tools::jsonEncode ( $response ) );
	}
	private function performRequest($params, $cart, $resultInit, $ThreeDSParams, $idLog = NULL) {
		
		$request = new RESTOperationMessage ();
		$request->setAmount ( ( int ) number_format ( $params ["amount"], $params ["decimals"], '', '' ) );
		$request->setCurrency ( $params ["currency"] );
		$request->setMerchant ( Configuration::get ( 'REDSYS_FUC_TARJETA_INSITE' ) );
		$request->setTerminal ( Configuration::get ( 'REDSYS_TERMINAL_TARJETA_INSITE' ) );
		$request->setOrder ( $params ["merchant_order"] );
		$request->setOperID ( $params ["idOper"] );
		$request->setTransactionType ( 0 );

		$decoded3DS = json_decode($ThreeDSParams);

		$protocolVersion = $resultInit -> protocolVersionAnalysis();
		$browserAcceptHeader = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json";
		$browserUserAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36";
		$browserJavaEnable = $decoded3DS->browserJavaEnabled;
		$browserJavaScriptEnabled = $decoded3DS->browserJavascriptEnabled;
		$browserLanguage = $decoded3DS->browserLanguage;
		$browserColorDepth = $decoded3DS->browserColorDepth;
		$browserScreenHeight = $decoded3DS->browserScreenHeight;
		$browserScreenWidth = $decoded3DS->browserScreenWidth;
		$browserTZ = $decoded3DS->browserTZ;
		$threeDSCompInd = $decoded3DS->browserUserAgent;

		$threeDSServerTransID = $resultInit -> getThreeDSServerTransID();

		if (Configuration::get ( 'REDSYS_ACTIVAR_3DS' ) == '1') {

			$version = explode( '.', $protocolVersion);
			
			if ($version[0] == "1") {

				escribirLog("DEBUG", $idLog, "Versión de 3DSecure: " . $protocolVersion, null, __METHOD__);
				$request -> setEMV3DSParamsV1();
	
			} else {
				
				escribirLog("DEBUG", $idLog, "Versión de 3DSecure: " . $protocolVersion, null, __METHOD__);
				$notificationURL = Redsyspur::createEndpointParams($this->module->_endpoint_securepaymentv2, $request, $params ["idCart"], $protocolVersion, $idLog);
				escribirLog("DEBUG", $idLog, "URL con parámetros: " . $notificationURL, null, __METHOD__);
				$request -> setEMV3DSParamsV2($protocolVersion, $browserAcceptHeader, $browserUserAgent, $browserJavaEnable, $browserJavaScriptEnabled, $browserLanguage, $browserColorDepth, $browserScreenHeight, $browserScreenWidth, $browserTZ, $threeDSServerTransID, $notificationURL, $threeDSCompInd);
	
			}

		} else {

			escribirLog("DEBUG", $idLog, "Usando DirectPayment", null, __METHOD__);
			$request->useDirectPayment ();
		}
		
		$request->addParameter ( "DS_MERCHANT_TITULAR", $params ["customer"] );
		$request->addParameter ( "DS_MERCHANT_PRODUCTDESCRIPTION", $params ["products"] );
		$request->addParameter ( "DS_MERCHANT_MODULE", "PR-PURv" . $this->module->version );

		if (isset ( $params ["requestRef"] ) && $params ["requestRef"] == "true" && Configuration::get ( 'REDSYS_REFERENCIA' ) == '1' && ! $cart->isGuestCartByCartId ( $params ["idCart"] )) {

			escribirLog("DEBUG", $idLog, "Guardando referencia", null, __METHOD__);
			$request->createReference ();
		}
			
		escribirLog("DEBUG", $idLog, "Enviando operación, firmada con clave " . substr(Tools::getValue ( 'REDSYS_CLAVE256_TARJETA_INSITE' ), 0, 3) . "*", null, __METHOD__);
		$service = new RESTOperationService ( Configuration::get ( 'REDSYS_CLAVE256_TARJETA_INSITE' ), Configuration::get ( 'REDSYS_URLTPV_INSITE' ) );
		$result = $service->sendOperation ( $request, $idLog );
		
		return $result;
	}
	private function createParameters($merchant_order, $idCart, $idOper, $save) {
		$params = array ();
		
		$cart = new Cart ( $idCart );
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
		
		$params ["merchant_order"] = $merchant_order;
		$params ["idCart"] = $idCart;
		$params ["idOper"] = $idOper;
		$params ["amount"] = $total_price;
		$params ["currency"] = $currency->iso_code_num;
		$params ["idCurrency"] = $currency->id;
		$params ["decimals"] = $decimals;
		$params ["products"] = preg_replace ( "/[^ a-zA-Z0-9-]+/", "", $productsDesc );
		$params ["customer"] = preg_replace ( "/[^ a-zA-Z0-9-]+/", "", $cust_name );
		$params ["requestRef"] = $save;
		
		return $params;
	}
	private function performRequestInit($params, $idLog) {

		escribirLog("DEBUG", $idLog, "Iniciando petición...", null, __METHOD__);

		$request = new RESTInitialRequestMessage();

		$request->setAmount ( ( int ) number_format ( $params ["amount"], $params ["decimals"], '', '' ) );
		$request->setCurrency ( $params ["currency"] );
		$request->setMerchant ( Configuration::get ( 'REDSYS_FUC_TARJETA_INSITE' ) );
		$request->setTerminal ( Configuration::get ( 'REDSYS_TERMINAL_TARJETA_INSITE' ) );
		$request->setOrder ( $params ["merchant_order"] );
		$request->setOperID ( $params ["idOper"] );
		$request->setTransactionType ( 0 );
		$request->demandCardData();
		
		$service = new RESTInitialRequestService ( Configuration::get ( 'REDSYS_CLAVE256_TARJETA_INSITE' ), Configuration::get ( 'REDSYS_URLTPV_INSITE' ) );
		$result = $service->sendOperation ( $request, $idLog );
		
		return $result;
	}
}
