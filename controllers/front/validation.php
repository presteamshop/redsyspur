<?php
/**
 * NOTA SOBRE LA LICENCIA DE USO DEL SOFTWARE
 *
 * El uso de este software está sujeto a las Condiciones de uso de software que
 * se incluyen en el paquete en el documento "Aviso Legal.pdf". También puede
 * obtener una copia en la siguiente url:
 * http://www.redsys.es/wps/portal/redsys/publica/areadeserviciosweb/descargaDeDocumentacionYEjecutables
 *
 * Redsys es titular de todos los derechos de propiedad intelectual e industrial
 * del software.
 *
 * Quedan expresamente prohibidas la reproducción, la distribución y la
 * comunicación pública, incluida su modalidad de puesta a disposición con fines
 * distintos a los descritos en las Condiciones de uso.
 *
 * Redsys se reserva la posibilidad de ejercer las acciones legales que le
 * correspondan para hacer valer sus derechos frente a cualquier infracción de
 * los derechos de propiedad intelectual y/o industrial.
 *
 * Redsys Servicios de Procesamiento, S.L., CIF B85955367
 */

if(!class_exists("Redsys_Refund")) {
	require_once('redsys_refund.php');
}

class RedsyspurValidationModuleFrontController extends ModuleFrontController  {
    public function postProcess() {
        try{
            /** Log de Errores **/
            $logLevel  = Configuration::get('REDSYS_LOG');
            $logString = Configuration::get( 'REDSYS_LOG_STRING' );
            $bizum = false;

            if(isset($_COOKIE['nPedSession']))
                setcookie("nPedSession", "", time() - 3600);

            $accesoDesde = "";
            if (!empty($_POST)) {
                $accesoDesde = 'POST';
            } else if (!empty($_GET)) {
                $accesoDesde = 'GET';
            }
            
            if ($accesoDesde === 'POST' || $accesoDesde === 'GET') {
                
                /** Recoger datos de respuesta **/
                $version      = Tools::getValue('Ds_SignatureVersion');
                $datos        = Tools::getValue('Ds_MerchantParameters');
                $firma_remota = Tools::getValue('Ds_Signature');
                
                // Se crea Objeto
                $miObj = new RedsysAPI;
                
                /** Se decodifican los datos enviados y se carga el array de datos **/
                $miObj->decodeMerchantParameters($datos);

                /** Declaramos Log */
                $pedido = $miObj->getParameter('Ds_Order');
                $idLog = generateIdLog($logLevel, $logString, $pedido);

                if ($accesoDesde === 'POST')
                    escribirLog("INFO ", $idLog, "***** VALIDACIÓN DE LA NOTIFICACIÓN  ──  PEDIDO " . $pedido . " *****");
                
                escribirLog("DEBUG", $idLog, "Parámetros de la notificación: " . $datos);
                escribirLog("DEBUG", $idLog, "Firma recibida del SIS       : " . $firma_remota);

                /** Clave y método de pago **/
                if ($miObj->getParameter('Ds_ProcessedPayMethod') == 68) {
                    $bizum = true;
                    $kc = Configuration::get('REDSYS_CLAVE256_BIZUM'); //68 -> Bizum
                    $codigoOrig = Configuration::get('REDSYS_FUC_BIZUM');
                    $metodo = "Redsys - Bizum";
                } else {
                    $kc = Configuration::get('REDSYS_CLAVE256_TARJETA');
                    $codigoOrig = Configuration::get('REDSYS_FUC_TARJETA');
                    $metodo = "Redsys - Tarjeta";
                }

                /** Se calcula la firma **/
                $firma_local = $miObj->createMerchantSignatureNotif($kc,$datos);
                escribirLog("DEBUG", $idLog, "Firma calculada notificación : " . $firma_local);

                $merchantData = b64url_decode($miObj->getParameter('Ds_MerchantData'));
                $merchantData = json_decode( $merchantData );
                
                /** Extraer datos de la notificación **/
                $total     = $miObj->getParameter('Ds_Amount');  
                //$pedido extraido en el Log, arriba.
                $pedidoSecuencial = $merchantData->idCart;
                $codigo    = $miObj->getParameter('Ds_MerchantCode');
                $terminal  = $miObj->getParameter('Ds_Terminal');
                $moneda    = $miObj->getParameter('Ds_Currency');
                $respuesta = $miObj->getParameter('Ds_Response');
                $id_trans  = $miObj->getParameter('Ds_AuthorisationCode');

                $metodoOrder = "N/A";

                if ($respuesta < 101)
                    $metodoOrder = "Autorizada " . $id_trans;    
                else if ($respuesta >= 101)
                    $metodoOrder = "Denegada " . $respuesta;

                if ($accesoDesde === 'POST') {
                    escribirLog("DEBUG", $idLog, "ID del Carrito: " . $pedidoSecuencial);
                    escribirLog("DEBUG", $idLog, "Codigo Comercio FUC: " . $codigo);
                    escribirLog("DEBUG", $idLog, "Terminal: " . $terminal);
                    escribirLog("DEBUG", $idLog, "Moneda: " . $moneda);
                    escribirLog("DEBUG", $idLog, "Codigo de respuesta del SIS: " . $respuesta);
                    escribirLog("DEBUG", $idLog, "Método de Pago: " . $metodo);
                    escribirLog("DEBUG", $idLog, "Información adicional del módulo: " . $merchantData->moduleComent);
                }

                /** Análisis de respuesta del SIS. */
                $erroresSIS = array();
                $errorBackofficeSIS = "";

                include 'erroresSIS.php';

                if (array_key_exists($respuesta, $erroresSIS)) {
                    
                    $errorBackofficeSIS  = $respuesta;
                    $errorBackofficeSIS .= ' - '.$erroresSIS[$respuesta].'.';
                
                } else {

                    $errorBackofficeSIS = "La operación ha finalizado con errores. Consulte el módulo de administración del TPV Virtual.";
                }
                
                escribirLog("DEBUG", $idLog, "Código de Autorización: " . $id_trans);
                $id_trans = str_replace("+", "", $id_trans);
                
                /** VALIDACIONES DE LIBRERÍA **/
                if (checkFirma($firma_local, $firma_remota)
                    && checkImporte($total)
                    && checkPedidoAlfaNum($pedido, Configuration::get('REDSYS_PEDIDO_EXTENDIDO') == 1)
                    && checkFuc($codigo)
                    && checkMoneda($moneda)
                    && checkRespuesta($respuesta)) {
                        if ($accesoDesde === 'POST') {
                            escribirLog("DEBUG", $idLog, "Primera validación POST");
                            
                            /** Creamos los objetos para confirmar el pedido **/
                            $cart = new Cart($pedidoSecuencial);
                            $redsys = new Redsyspur();

                            (empty($cart)) ? ($cartInfo = "El objeto del carrito está vacío") : ($cartInfo = serialize($cart));
                            if(Configuration::get('REDSYS_LOG_CART'))
                                escribirLog("INFO ", $idLog, "POST ─ CARRITO SERIALIZADO: " . $cartInfo);
                            
                            $carrito_valido = true;
                            $cliente = true;
                            $mensajeError = "Errores validando el carrito en POST: ";
                            /** Validamos Objeto carrito **/
                            if ($cart->id_customer == 0) {
                                escribirLog("DEBUG", $idLog, "Excepción validando el carrito. Cliente vacío. Puede no estar logueado, cargamos el guest.");

                                if ($cart->id_guest == 0) {
                                    escribirLog("DEBUG", $idLog, "Error validando el carrito. Cliente vacío y Guest vacío.");
                                    $mensajeError += "Cliente vacío | ";
                                    $carrito_valido = false;
                                }
                                else {
                                    $cliente = false;
                                    escribirLog("DEBUG", $idLog, "Excepción validando el carrito CONTROLADA. Cliente vacío pero GUEST con datos.");
                                    $id_customer = $cart->id_guest;
                                }
                            }
                            else {
                                $id_customer = $cart->id_customer;
                            }
                            
                            if ($cart->id_address_delivery == 0) {
                                escribirLog("DEBUG", $idLog, "Error validando el carrito. Dirección de envío vacía.");
                                $mensajeError += "Dirección de envío vacía | ";
                                $carrito_valido = false;
                            }
                            if ($cart->id_address_invoice == 0){
                                escribirLog("DEBUG", $idLog, "Error validando el carrito. Dirección de facturación vacía.");
                                $mensajeError += "Dirección de facturación vacía | ";
                                $carrito_valido = false;
                            }
                            if (!$redsys->active) {
                                escribirLog("DEBUG", $idLog, "Error. Módulo desactivado.");
                                $mensajeError += "Módulo desactivado | ";
                                $carrito_valido = false;
                            }

                            $totalCarrito = $cart->getOrderTotal(true, Cart::BOTH);
                            if ($total/100 != $totalCarrito) {
                                escribirLog("DEBUG", $idLog, "Error. No coincide el total con el del carrito");
                                $mensajeError += "No coincide el total con el del carrito | ";
                                $carrito_valido = false;
                            }
                            
                            if (!$carrito_valido){
                                escribirLog("INFO ", $idLog, "Ha ocurrido un error al procesar el carrito y el pedido " . $pedidoSecuencial . " (" . $pedido . ") no se ha validado correctamente. Acceda al Portal de Administración del TPV Virtual para comprobar el estado del pago.");
                                escribirLog("DEBUG", $idLog, "Carrito serializado: " . serialize($cart));

                                if ($respuesta < 101) {
                                    /** Lugar peligroso: La validación ha fallado pero la respuesta indica que el pedido ha sido pagado. */
                                    escribirLog("ERROR", $idLog, "ERROR VALIDANDO EL CARRITO, PERO SE HA RECIBIDO RESPUESTA OK POR PARTE DE REDSYS ── Revisar en el Portal de Administración la operación " . $pedido . " ya que es posible que el cliente haya completado el pago correctamente.");
                                    
                                    $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] " . $errorBackofficeSIS);
                                    $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] ATENCIÓN: Se ha producido un error, pero la respuesta recibida de Redsys es OK // 0000. Revise la operación (" . $pedido . ") en el Portal de Administración ya que es posible que el importe haya sido cobrado al cliente.");
                                    $redsys->validateOrder($pedidoSecuencial, _PS_OS_ERROR_, 0, $metodo, "[REDSYS] " . $errorBackofficeSIS);
                                    $redsys->addPaymentInfo($pedidoSecuencial, $pedido, $metodoOrder, $idLog);
                                    
                                    echo "Error validando el carrito ── " . $errorBackofficeSIS;
                                    exit();
                                }

                                $mensajeError += "";

                                $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] " . $errorBackofficeSIS);
                                $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] Ha ocurrido un error al procesar el carrito. Revise el Portal de Administración del TPV Virtual para revisar el estado de la operación.");
                                $redsys->validateOrder($pedidoSecuencial, _PS_OS_CANCELED_, $total/100, $metodo, "[REDSYS] " . $errorBackofficeSIS);
                                $redsys->addPaymentInfo($pedidoSecuencial, $pedido, $metodoOrder, $idLog);

                                escribirLog("DEBUG", $idLog, $mensajeError);
                                escribirLog("INFO ", $idLog, $errorBackofficeSIS);
                                echo "Error validando el carrito ── " . $errorBackofficeSIS;
                                exit();
                            }
                            /** Validamos Objeto cliente **/
                            $customer = $cliente ? new Customer((int)$cart->id_customer) : new Guest((int)$cart->id_guest);

                            if (!$cliente)
                                escribirLog("DEBUG", $idLog, "Cliente serializado: " . serialize($customer));

                            /** Donet **/
                            $address = new Address((int)$cart->id_address_invoice);
                            Context::getContext()->country = new Country((int)$address->id_country);
                            Context::getContext()->language = new Language((int)$cart->id_lang);
                            Context::getContext()->currency = new Currency((int)$cart->id_currency);
                            
                            if (!Validate::isLoadedObject($customer)) {
                                escribirLog("INFO ", $idLog, "Error validando el cliente.");
                                escribirLog("INFO ", $idLog, $errorBackofficeSIS);
                                echo "Error validando al cliente ── " . $errorBackofficeSIS;
                                exit();
                            }
                            
                            // DsResponse
                            $respuesta = (int)$respuesta;
                            
                            if ($respuesta < 101 && checkAutCode($id_trans)) {
                                /** Compra válida **/
                                
                                $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] " . $errorBackofficeSIS);
                                $redsys->validateOrder($cart->id, Configuration::get("REDSYS_ESTADO_PEDIDO"), $total/100, $metodo, "[REDSYS] " . $errorBackofficeSIS, array('transaction_id' => $pedido), (int)$cart->id_currency, false, (property_exists($customer, "secure_key") && !is_null($customer->secure_key)) ? $customer->secure_key : false);
                                Redsys_Refund::SaveOrderId($cart->id, $pedido, $bizum ? 'bizum' : 'redireccion');
                                $redsys->addPaymentInfo($pedidoSecuencial, $pedido, $metodoOrder, $idLog, true);

                                escribirLog("INFO ", $idLog, "El pedido con ID de carrito " . $cart->id . " (" . $pedido . ") es válido y se ha registrado correctamente.");
                                escribirLog("INFO ", $idLog, $errorBackofficeSIS);
                                echo "Pedido validado con éxito ── " . $errorBackofficeSIS;
                                exit();
                                
                            } else {
                                escribirLog("DEBUG", $idLog, "Pedido inválido, L227.");
                                
                                $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] " . $errorBackofficeSIS);
                                $redsys->validateOrder($pedidoSecuencial, _PS_OS_CANCELED_, 0, $metodo, "[REDSYS] " . $errorBackofficeSIS);
                                $redsys->addPaymentInfo($pedidoSecuencial, $pedido, $metodoOrder, $idLog);
                            }

                            echo "El pedido ha finalizado con errores ── " . $errorBackofficeSIS;
                            escribirLog("INFO ", $idLog, "El pedido con ID de carrito " . $pedidoSecuencial . " (" . $pedido . ") ha finalizado con errores.");
                            escribirLog("INFO ", $idLog, $errorBackofficeSIS);
                            exit(); 

                        } else if ($accesoDesde === 'GET') {
                            $respuesta = (int)$respuesta;
                            escribirLog("DEBUG", $idLog, "El cliente ha vuelto desde Redsys. Respuesta: " . $respuesta);
                            if ($respuesta < 101) {
                                /** Compra válida **/
                                Tools::redirect('index.php?controller=order&step=1');
                            } else {
                                Tools::redirect('index.php?controller=order&step=1');
                            }
                        }
                    } else {

                        $cart = new Cart($pedidoSecuencial);
                        $redsys = new Redsyspur();

                        (empty($cart)) ? ($cartInfo = "El objeto del carrito está vacío") : ($cartInfo = serialize($cart));
                            if(Configuration::get('REDSYS_LOG_CART'))
                                escribirLog("INFO ", $idLog, "GET ─ CARRITO SERIALIZADO: " . $cartInfo);
                        
                        $cliente = true;
                        /** Validamos Objeto carrito **/
                        if ($cart->id_customer == 0) {
                            escribirLog("DEBUG", $idLog, "Excepción validando el carrito. Cliente vacío. Puede no estar logueado, cargamos el guest.");

                            if ($cart->id_guest == 0)
                                escribirLog("DEBUG", $idLog, "Error validando el carrito. Cliente vacío y Guest vacío.");
                            else 
                                $id_customer = $cart->id_guest;

                        } else {
                            $id_customer = $cart->id_customer;
                        }

                        if ($accesoDesde === 'POST') {

                            escribirLog("INFO ", $idLog, "Notificación: El pedido con ID de carrito " . $pedidoSecuencial . " es inválido.");
                            escribirLog("ERROR", $idLog, "Error validando el pedido con ID de carrito " . $pedidoSecuencial . " (" . $pedido . "). Resultado de las validaciones [Firma|Respuesta|Moneda|FUC|Pedido|Importe]: [" . checkFirma($firma_local, $firma_remota) . "|" . checkRespuesta($respuesta) . "|" . checkMoneda($moneda) . "|" . checkFuc($codigo) . "|" . checkPedidoAlfaNum($pedido, Configuration::get('REDSYS_PEDIDO_EXTENDIDO') == 1) . "|" . checkImporte($total) . "]" );

                            if ($respuesta < 101) {
                                /** Lugar peligroso: La validación ha fallado pero la respuesta indica que el pedido ha sido pagado. */
                                
                                escribirLog("ERROR", $idLog, "ERROR VALIDANDO EL PEDIDO, PERO SE HA RECIBIDO RESPUESTA OK POR PARTE DE REDSYS ── Revisar en el Portal de Administración la operación " . $pedido . " ya que es posible que el cliente haya completado el pago correctamente.");
                                
                                $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] " . $errorBackofficeSIS);                   
                                $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] ATENCIÓN: Se ha producido un error validando el pedido, pero la respuesta recibida de Redsys es OK // 0000. Revise la operación (" . $pedido . ") en el Portal de Administración ya que es posible que el importe haya sido cobrado al cliente.");                   
                                $redsys->validateOrder($pedidoSecuencial, _PS_OS_ERROR_, 0, $metodo, "[REDSYS] " . $errorBackofficeSIS);
                                $redsys->addPaymentInfo($pedidoSecuencial, $pedido, $metodoOrder, $idLog);

                                exit();
                            }

                            $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] " . $errorBackofficeSIS);                   
                            $redsys->addMessage($id_customer, $pedidoSecuencial, "[REDSYS] Error durante la validación del pedido. Consulte los logs generados en {prestashop}/modules/redsyspur/logs/redsysLog.log para obtener más información.");
                            $redsys->validateOrder($pedidoSecuencial, _PS_OS_CANCELED_, 0, $metodo, "[REDSYS] " . $errorBackofficeSIS);
                            $redsys->addPaymentInfo($pedidoSecuencial, $pedido, $metodoOrder, $idLog);

                            echo "Error en las validaciones ── " . $errorBackofficeSIS;
                            
                        } else if ($accesoDesde === 'GET') {
                            escribirLog("DEBUG", $idLog, "El cliente ha vuelto desde redsys. Respuesta: " . $respuesta);
                            echo "Error en las validaciones ── " . $errorBackofficeSIS;
                        }

                        escribirLog("INFO ", $idLog, $errorBackofficeSIS);
                        exit();
                    }
            }
        }
        catch (Exception $e){

            escribirLog("DEBUG", "0000000000000000000000000error", "Excepcion en la validacion: ".$e->getMessage());
            die("Excepcion en la validacion");
        }
    }
}