<?php

if (!class_exists('RESTOperationService')) {
	include_once $GLOBALS["REDSYS_API_PATH"] . "/Service/RESTService.php";
	//include_once $GLOBALS["REDSYS_API_PATH"]."/Service/Impl/RESTDCCConfirmationService.php";
	//include_once $GLOBALS["REDSYS_API_PATH"]."/Model/message/RESTDCCConfirmationMessage.php";
	include_once $GLOBALS["REDSYS_API_PATH"] . "/Model/element/RESTOperationElement.php";
	include_once $GLOBALS["REDSYS_API_PATH"] . "/Model/message/RESTResponseMessage.php";
	include_once $GLOBALS["REDSYS_API_PATH"] . "/Model/message/RESTInitialRequestMessage.php";
	include_once $GLOBALS["REDSYS_API_PATH"] . "/Utils/RESTSignatureUtils.php";

	class RESTOperationService extends RESTService
	{
		private $request;
		function __construct($signatureKey, $env)
		{
			parent::__construct($signatureKey, $env, RESTConstants::$TRATA);
		}

		public function createRequestMessage($message)
		{
			$this->request = $message;
			$req = new RESTInitialRequestMessage();
			$req->setDatosEntrada($message);

			$signatureUtils = new RESTSignatureUtils();
			$localSignature = $signatureUtils->createMerchantSignature($this->getSignatureKey(), $req->getDatosEntradaB64());

			$req->setSignature($localSignature);

			return $req;
		}

		public function createResponseMessage($trataPeticionResponse, $idLog = null)
		{
			$response = new RESTResponseMessage();
			$varArray = json_decode($trataPeticionResponse, true);

			if (isset($varArray["ERROR"]) || isset($varArray["errorCode"])) {
				escribirLog("ERROR", $idLog, "Se ha producido un error -- Datos recibidos: " . $trataPeticionResponse, null, __METHOD__);
				$response->setResult(RESTConstants::$RESP_LITERAL_KO);
			} else {
				$varArray = json_decode(base64_decode($varArray["Ds_MerchantParameters"]), true);

				$dccElem = isset($varArray[RESTConstants::$RESPONSE_DCC_MARGIN_TAG]);

				if ($dccElem) {
					// 					$dccService=new RESTDCCConfirmationService($this->getSignatureKey(), $this->getEnv());
					// 					$dccResponse=$dccService->unMarshallResponseMessage($trataPeticionResponse);

					// 					$dccConfirmation=new RESTDCCConfirmationMessage();
					// 					$currency="";
					// 					$amount="";
					// 					if($this->request->isDcc()){
					// 						$currency=$dccResponse->getDcc0()->getCurrency();
					// 						$amount=$dccResponse->getDcc0()->getAmount();
					// 					}
					// 					else{
					// 						$currency=$dccResponse->getDcc1()->getCurrency();
					// 						$amount=$dccResponse->getDcc1()->getAmount();
					// 					}

					// 					$dccConfirmation->setCurrencyCode($currency, $amount);
					// 					$dccConfirmation->setMerchant($this->request->getMerchant());
					// 					$dccConfirmation->setTerminal($this->request->getTerminal());
					// 					$dccConfirmation->setOrder($this->request->getOrder());
					// 					$dccConfirmation->setSesion($dccResponse->getSesion());

					// 					$response=$dccService->sendOperation($dccConfirmation);
				} else {
					$response = $this->unMarshallResponseMessage($trataPeticionResponse);
					if (is_null($response->getApiCode())) {
						$paramsB64 = json_decode($trataPeticionResponse, true)["Ds_MerchantParameters"];
						$response->setApiCode($response->getOperation()->getResponseCode());

						if (!$this->checkSignature($paramsB64, $response->getOperation()->getSignature(), $idLog)) {
							$response->setResult(RESTConstants::$RESP_LITERAL_KO);
						} else {
							if ($response->getOperation()->requiresSCA()) {
								$response->setResult(RESTConstants::$RESP_LITERAL_AUT);
							} else {
								$transType = $response->getTransactionType();
								switch ((int)$response->getOperation()->getResponseCode()) {
									case RESTConstants::$AUTHORIZATION_OK:
										$response->setResult(($transType == RESTConstants::$AUTHORIZATION || $transType == RESTConstants::$PREAUTHORIZATION) ? RESTConstants::$RESP_LITERAL_OK : RESTConstants::$RESP_LITERAL_KO);
										break;
									case RESTConstants::$CONFIRMATION_OK:
										$response->setResult(($transType == RESTConstants::$CONFIRMATION || $transType == RESTConstants::$REFUND) ? RESTConstants::$RESP_LITERAL_OK : RESTConstants::$RESP_LITERAL_KO);
										break;
									case RESTConstants::$CANCELLATION_OK:
										$response->setResult($transType == RESTConstants::$CANCELLATION ? RESTConstants::$RESP_LITERAL_OK : RESTConstants::$RESP_LITERAL_KO);
										break;
									default:
										$response->setResult(RESTConstants::$RESP_LITERAL_KO);
								}
							}
						}

					}
					
				}

				escribirLog("DEBUG", $idLog, "Datos recibidos: " . $response->toXml(), null, __METHOD__);

				if ($response->getResult() == RESTConstants::$RESP_LITERAL_OK) {
					escribirLog("INFO ", $idLog, "OK // La operación ha finalizado correctamente", null, __METHOD__);
				} else {
					if ($response->getResult() == RESTConstants::$RESP_LITERAL_AUT) {
                        escribirLog("INFO ", $idLog, "AUT // La operación requiere de autenticación", null, __METHOD__);
					} else {
						escribirLog("ERROR", $idLog, "KO // La operación ha finalizado con errores", null, __METHOD__);
					}
				}
			}
			return $response;
		}

		// public function unMarshallResponseMessage($message){
		// 	$response=new RESTResponseMessage();

		// 	$varArray=json_decode($message,true);

		// 	$operacion=new RESTOperationElement();
		// 	$operacion->parseJson(base64_decode($varArray["Ds_MerchantParameters"]));
		// 	$operacion->setSignature($varArray["Ds_Signature"]);

		// 	$response->setOperation($operacion);

		// 	return $response;
		// }
	}
}
