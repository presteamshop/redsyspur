<?php

if(!class_exists('RESTInitialRequestService')){
    include_once $GLOBALS["REDSYS_API_PATH"]."/Service/RESTService.php";
	include_once $GLOBALS["REDSYS_API_PATH"]."/Model/message/RESTInitialRequestMessage.php";
    include_once $GLOBALS["REDSYS_API_PATH"]."/Model/message/RESTResponseMessage.php";
    include_once $GLOBALS["REDSYS_API_PATH"]."/Model/element/RESTOperationElement.php";
    include_once $GLOBALS["REDSYS_API_PATH"]."/Model/RESTRequestInterface.php";
    include_once $GLOBALS["REDSYS_API_PATH"]."/Model/RESTResponseInterface.php";
	include_once $GLOBALS["REDSYS_API_PATH"]."/Utils/RESTSignatureUtils.php";
    include_once $GLOBALS["REDSYS_API_PATH"]."/Constants/RESTConstants.php";

    class RESTInitialRequestService extends RESTService{
		function __construct($signatureKey, $env){
			parent::__construct($signatureKey, $env, RESTConstants::$INICIA);
        }
        
        public function createRequestMessage($message){
			$req=new RESTInitialRequestMessage();
			$req->setDatosEntrada($message);
		
			$tagDE=$message->toJson();
			
			$signatureUtils=new RESTSignatureUtils();
			$localSignature=$signatureUtils->createMerchantSignature($this->getSignatureKey(), $req->getDatosEntradaB64());
			$req->setSignature($localSignature);

			return $req;
        }

        public function createResponseMessage($trataPeticionResponse, $idLog){
			$response = $this->unMarshallResponseMessage($trataPeticionResponse);
            
            if (is_null($response->getApiCode())) {

                $paramsB64=json_decode($trataPeticionResponse,true)["Ds_MerchantParameters"];
                $response->setApiCode($response->getOperation()->getResponseCode());
                
                $transType = $response->getTransactionType();
                if(!$this->checkSignature($paramsB64, $response->getOperation()->getSignature(), $idLog))
                {
                    escribirLog("ERROR", $idLog, "Se ha producido un error -- Datos recibidos: " . $trataPeticionResponse, null, __METHOD__);
                    $response->setResult(RESTConstants::$RESP_LITERAL_KO);
                }
                else{
                    if ($response->getOperation()->getResponseCode() == null && $response->getOperation()->getPsd2() != null && $response->getOperation()->getPsd2() == RESTConstants::$RESPONSE_PSD2_TRUE) {
                        $response->setResult(RESTConstants::$RESP_LITERAL_AUT);
                        escribirLog("INFO ", $idLog, "AUT // La operación requiere de autenticación", null, __METHOD__);
                    }else if ($response->getOperation()->getResponseCode() == null && $response->getOperation()->getPsd2() != null && $response->getOperation()->getPsd2() == RESTConstants::$RESPONSE_PSD2_FALSE) {
                        $response->setResult(RESTConstants::$RESP_LITERAL_OK);
                        escribirLog("INFO ", $idLog, "OK // La operación ha finalizado correctamente", null, __METHOD__);
                    }
                    else{
                        $response->setResult(RESTConstants::$RESP_LITERAL_KO);
                        escribirLog("ERROR", $idLog, "KO // La operación ha finalizado con errores", null, __METHOD__);
                    }
                }
            }

            escribirLog("DEBUG", $idLog, "Datos recibidos: " . $response->toXml(), null, __METHOD__);		
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