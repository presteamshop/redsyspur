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
if (! defined ( '_PS_VERSION_' )) {
	exit ();
}
if (! (function_exists ( "escribirLog" ) or function_exists("generateIdLog") or function_exists("checkFuc"))) {
	require_once ('apiRedsys/redsysLibrary.php');
}
if (! class_exists ( "RedsysAPI" )) {
	require_once ('apiRedsys/apiRedsysFinal.php');
}
if(!class_exists("Redsys_Refund")) {
	require_once('redsys_refund.php');
}

require_once ('ApiRedsysREST/Constants/RESTConstants.php');

if (! defined ( '_CAN_LOAD_FILES_' ))
	exit ();
	
class Redsyspur extends PaymentModule {
	
	private $_html = '';
	private $_postErrors = array ();
	private $_dbRefTable = _DB_PREFIX_."redsys_references";
	public $_endpoint_paymentko;
	public $_endpoint_securepayment;
	public $_endpoint_securepaymentv2;
	public $_endpoint_processpayment;
	public $_endpoint_processpaymentref;
	
	
	public function __construct() {
		
		$this->name = 'redsyspur';		
		$this->author = 'Redsys Servicios de Procesamiento S.L.';		
		$this->tab = 'payments_gateways';
		$this->version = '1.0.2';
		$this->moduleComent = "Pasarela Unificada de Redsys para Prestashop";
		$this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => '1.7.99',
        ];

		$this->is_eu_compatible = 1;
		$this->bootstrap = true;

		$this->titlePago   = $this->l('Tarjeta de crédito o débito');
		$this->titlePagoC  = $this->l('Pagar con tarjeta de crédito');
		$this->titlePagoD  = $this->l('Pagar con tarjeta de débito');

		$this->urlEntornoSandbox = 'https://sis-t.redsys.es:25443/sis/realizarPago/utf-8';
		$this->urlEntornoProduccion = 'https://sis.redsys.es/sis/realizarPago/utf-8';

		$this->urlModalSandbox = 'https://sis-t.redsys.es:25443/sis/redsys-modal/js/redsys-modal.js';
		$this->urlModalProduccion = 'https://sis.redsys.es/sis/redsys-modal/js/redsys-modal.js';

		parent::__construct();

		$this->_endpoint_paymentko = $this->context->link->getModuleLink ( $this->name, 'paymentko' );
		$this->_endpoint_securepayment = $this->context->link->getModuleLink ( $this->name, 'securepayment' );
		$this->_endpoint_securepaymentv2 = $this->context->link->getModuleLink ( $this->name, 'securepaymentv2' );
		$this->_endpoint_processpayment = $this->context->link->getModuleLink ( $this->name, 'processpayment' );
		$this->_endpoint_processpaymentref = $this->context->link->getModuleLink ( $this->name, 'processpaymentref' );

		$this->displayName = $this->l('Pasarela Unificada de Redsys para Prestashop');
		$this->description = $this->l('Acepta pagos con tarjeta o con Bizum utilizando los servicios de Redsys.');

        $this->confirmUninstall = $this->l('¿Está seguro que desea desinstalar el módulo? Una vez eliminado no podrá aceptar pagos con tarjeta utilizando la pasarela de Redsys.');
		
		//aa
		
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		
		// Array config con los datos de config.
		$config = Configuration::getMultiple ( array (
				'REDSYS_ACTIVAR_TARJETA',
				'REDSYS_ACTIVAR_TARJETA_MODAL',
				'REDSYS_ACTIVAR_BIZUM',
				'REDSYS_ACTIVAR_TARJETA_INSITE',
				'REDSYS_URLTPV_REDIR',
				'REDSYS_URLTPV_INSITE',
				'REDSYS_URLTPV_BIZUM',
				'REDSYS_NOMBRE',
				'REDSYS_FUC_TARJETA',
				'REDSYS_TERMINAL_TARJETA',
				'REDSYS_CLAVE256_TARJETA',
				'REDSYS_FUC_BIZUM',
				'REDSYS_TERMINAL_BIZUM',
				'REDSYS_CLAVE256_BIZUM',
				'REDSYS_FUC_TARJETA_INSITE',
				'REDSYS_TERMINAL_TARJETA_INSITE',
				'REDSYS_CLAVE256_TARJETA_INSITE',
				'REDSYS_MANTENER_CARRITO',
				'REDSYS_LOG',
				'REDSYS_LOG_CART',
				'REDSYS_MENSAJES_BACKOFFICE',
				'REDSYS_LOG_STRING',
				'REDSYS_NUMERO_PEDIDO',
				'REDSYS_PEDIDO_EXTENDIDO',
				'REDSYS_IDIOMAS_ESTADO',
				'REDSYS_ESTADO_PEDIDO',
				'REDSYS_REFERENCIA',
				'REDSYS_TEXT_BTN',
				'REDSYS_STYLE_BTN',
				'REDSYS_STYLE_BODY',
				'REDSYS_STYLE_FORM',
				'REDSYS_STYLE_TEXT',
				'REDSYS_ACTIVAR_3DS',
				'REDSYS_MONEDA',
				'REDSYS_URLOK',
				'REDSYS_URLKO'
		) );
		
		// Establecer propiedades nediante los datos de config.
		$this->env = $config ['REDSYS_URLTPV_REDIR'];
		switch ($this->env) {
			case 0 : // Pruebas / Sandbox / sis-t
				$this->urlTPVredir = $this->urlEntornoSandbox;
				$this->urlModal = $this->urlModalSandbox;
				$this->environmentModal = 'test';
				break;
			case 1 : // Real
				$this->urlTPVredir = $this->urlEntornoProduccion;
				$this->urlModal = $this->urlModalProduccion;
				$this->environmentModal = 'prod';
				break;
		}

		$this->env = $config ['REDSYS_URLTPV_BIZUM'];
		switch ($this->env) {
			case 0 : // Pruebas / Sandbox / sis-t
				$this->urlTPVbizum = $this->urlEntornoSandbox;
				break;
			case 1 : // Real / Produccion / sis
				$this->urlTPVbizum = $this->urlEntornoProduccion;
				break;
		}



		if (isset ( $config ['REDSYS_ACTIVAR_TARJETA'] ))
			$this->REDSYS_ACTIVAR_TARJETA = $config ['REDSYS_ACTIVAR_TARJETA'];
		if (isset ( $config ['REDSYS_ACTIVAR_TARJETA_MODAL'] ))
			$this->REDSYS_ACTIVAR_TARJETA = $config ['REDSYS_ACTIVAR_TARJETA_MODAL'];
		if (isset ( $config ['REDSYS_ACTIVAR_BIZUM'] ))
			$this->REDSYS_ACTIVAR_BIZUM = $config ['REDSYS_ACTIVAR_BIZUM'];
		if (isset ( $config ['REDSYS_ACTIVAR_TARJETA_INSITE'] ))
			$this->REDSYS_ACTIVAR_TARJETA_INSITE = $config ['REDSYS_ACTIVAR_TARJETA_INSITE'];
		if (isset ( $config ['REDSYS_NOMBRE'] ))
			$this->REDSYS_NOMBRE = $config ['REDSYS_NOMBRE'];
		if (isset ( $config ['REDSYS_FUC_TARJETA'] ))
			$this->REDSYS_FUC_TARJETA = $config ['REDSYS_FUC_TARJETA'];
		if (isset ( $config ['REDSYS_TERMINAL_TARJETA'] ))
			$this->REDSYS_TERMINAL_TARJETA = $config ['REDSYS_TERMINAL_TARJETA'];
		if (isset ( $config ['REDSYS_CLAVE256_TARJETA'] ))
			$this->REDSYS_CLAVE256_TARJETA = $config ['REDSYS_CLAVE256_TARJETA'];
		if (isset ( $config ['REDSYS_FUC_BIZUM'] ))
			$this->REDSYS_FUC_BIZUM = $config ['REDSYS_FUC_BIZUM'];
		if (isset ( $config ['REDSYS_TERMINAL_BIZUM'] ))
			$this->REDSYS_TERMINAL_BIZUM = $config ['REDSYS_TERMINAL_BIZUM'];
		if (isset ( $config ['REDSYS_CLAVE256_BIZUM'] ))
			$this->REDSYS_CLAVE256_BIZUM = $config ['REDSYS_CLAVE256_BIZUM'];
		if (isset ( $config ['REDSYS_FUC_TARJETA_INSITE'] ))
			$this->REDSYS_FUC_TARJETA_INSITE = $config ['REDSYS_FUC_TARJETA_INSITE'];
		if (isset ( $config ['REDSYS_TERMINAL_TARJETA_INSITE'] ))
			$this->REDSYS_TERMINAL_TARJETA_INSITE = $config ['REDSYS_TERMINAL_TARJETA_INSITE'];
		if (isset ( $config ['REDSYS_CLAVE256_TARJETA_INSITE'] ))
			$this->REDSYS_CLAVE256_TARJETA_INSITE = $config ['REDSYS_CLAVE256_TARJETA_INSITE'];
		if (isset ( $config ['REDSYS_MANTENER_CARRITO'] ))
			$this->REDSYS_MANTENER_CARRITO = $config ['REDSYS_MANTENER_CARRITO'];
		if (isset ( $config ['REDSYS_LOG'] ))
			$this->REDSYS_LOG = $config ['REDSYS_LOG'];
		if (isset ( $config ['REDSYS_LOG_CART'] ))
			$this->REDSYS_LOG_CART = $config ['REDSYS_LOG_CART'];
		if (isset ( $config ['REDSYS_MENSAJES_BACKOFFICE'] ))
			$this->REDSYS_MENSAJES_BACKOFFICE = $config ['REDSYS_MENSAJES_BACKOFFICE'];
		if (isset ( $config ['REDSYS_IDIOMAS_ESTADO'] ))
			$this->REDSYS_IDIOMAS_ESTADO = $config ['REDSYS_IDIOMAS_ESTADO'];
		if (isset($config['REDSYS_ESTADO_PEDIDO']))
			$this->REDSYS_ESTADO_PEDIDO = $config['REDSYS_ESTADO_PEDIDO'];
		if (isset($config['REDSYS_NUMERO_PEDIDO']))
			$this->REDSYS_NUMERO_PEDIDO = $config['REDSYS_NUMERO_PEDIDO'];
		if (isset($config['REDSYS_PEDIDO_EXTENDIDO']))
			$this->REDSYS_PEDIDO_EXTENDIDO = $config['REDSYS_PEDIDO_EXTENDIDO'];
		if (isset($config['REDSYS_REFERENCIA']))
			$this->REDSYS_REFERENCIA = $config['REDSYS_REFERENCIA'];
		if (isset($config['REDSYS_TEXT_BTN']))
			$this->REDSYS_TEXT_BTN = $config['REDSYS_TEXT_BTN'];
		if (isset($config['REDSYS_STYLE_BTN']))
			$this->REDSYS_STYLE_BTN = $config['REDSYS_STYLE_BTN'];
		if (isset($config['REDSYS_STYLE_BODY']))
			$this->REDSYS_STYLE_BODY = $config['REDSYS_STYLE_BODY'];
		if (isset($config['REDSYS_STYLE_FORM']))
			$this->REDSYS_STYLE_FORM = $config['REDSYS_STYLE_FORM'];
		if (isset($config['REDSYS_STYLE_TEXT']))
			$this->REDSYS_STYLE_TEXT = $config['REDSYS_STYLE_TEXT'];
		if (isset($config['REDSYS_ACTIVAR_3DS']))
			$this->REDSYS_ACTIVAR_3DS = $config['REDSYS_ACTIVAR_3DS'];
		if (isset($config['REDSYS_MONEDA']))
			$this->REDSYS_MONEDA = $config['REDSYS_MONEDA'];
		if (isset($config['URLOK']))
			$this->URLOK = $config['URLOK'];
		if (isset($config['URLKO']))
			$this->URLKO = $config['URLKO'];
		
		$this->page = basename ( __FILE__, '.php' );
		
		// Mostrar aviso si faltan datos de config.
		if (! isset ( $this->REDSYS_URLTPV_REDIR ) 
				|| ! isset ( $this->REDSYS_URLTPV_INSITE )
				|| ! isset ( $this->REDSYS_URLTPV_BIZUM )
				|| ! isset ( $this->REDSYS_ACTIVAR_TARJETA )
				|| ! isset ( $this->REDSYS_ACTIVAR_BIZUM )
				|| ! isset ( $this->REDSYS_ACTIVAR_TARJETA_INSITE )
				|| ! isset ( $this->REDSYS_NOMBRE )
				|| ! isset ( $this->REDSYS_FUC_TARJETA ) 
				|| ! isset ( $this->REDSYS_TERMINAL_TARJETA ) 
				|| ! isset ( $this->REDSYS_CLAVE256_TARJETA )
				|| ! isset ( $this->REDSYS_FUC_BIZUM ) 
				|| ! isset ( $this->REDSYS_TERMINAL_BIZUM ) 
				|| ! isset ( $this->REDSYS_CLAVE256_BIZUM )  
				|| ! isset ( $this->REDSYS_FUC_TARJETA_INSITE ) 
				|| ! isset ( $this->REDSYS_TERMINAL_TARJETA_INSITE ) 
				|| ! isset ( $this->REDSYS_CLAVE256_TARJETA_INSITE )
				|| ! isset ( $this->REDSYS_MANTENER_CARRITO ) 
				|| ! isset ( $this->REDSYS_LOG ) 
				|| ! isset ( $this->REDSYS_LOG_CART )
				|| ! isset ( $this->REDSYS_MENSAJES_BACKOFFICE ) 
				|| ! isset ( $this->REDSYS_IDIOMAS_ESTADO )
				|| ! isset ( $this->REDSYS_ESTADO_PEDIDO)
				|| ! isset ( $this->REDSYS_NUMERO_PEDIDO)
				|| ! isset ( $this->REDSYS_PEDIDO_EXTENDIDO)
				|| ! isset ( $this->REDSYS_REFERENCIA)
				|| ! isset ( $this->REDSYS_TEXT_BTN)
				|| ! isset ( $this->REDSYS_STYLE_BTN)
				|| ! isset ( $this->REDSYS_STYLE_BODY)
				|| ! isset ( $this->REDSYS_STYLE_FORM)
				|| ! isset ( $this->REDSYS_STYLE_TEXT)
				|| ! isset ( $this->REDSYS_ACTIVAR_3DS)
				|| ! isset ( $this->REDSYS_MONEDA) )
			
			$this->warning = $this->l ( 'Faltan datos por configurar en el módulo de Redsys.' );
	}
	
	
	public function install() {
		if (! parent::install () 
				|| ! Configuration::updateValue ( 'REDSYS_URLTPV_REDIR', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_URLTPV_INSITE', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_URLTPV_BIZUM', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_ACTIVAR_TARJETA', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_ACTIVAR_TARJETA_MODAL', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_ACTIVAR_BIZUM', 0 )  
				|| ! Configuration::updateValue ( 'REDSYS_ACTIVAR_TARJETA_INSITE', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_NOMBRE', '' )
				|| ! Configuration::updateValue ( 'REDSYS_FUC_TARJETA', '' )
				|| ! Configuration::updateValue ( 'REDSYS_TERMINAL_TARJETA', '' ) 
				|| ! Configuration::updateValue ( 'REDSYS_CLAVE256_TARJETA', '' )
				|| ! Configuration::updateValue ( 'REDSYS_FUC_BIZUM', '' )
				|| ! Configuration::updateValue ( 'REDSYS_TERMINAL_BIZUM', '' ) 
				|| ! Configuration::updateValue ( 'REDSYS_CLAVE256_BIZUM', '' )
				|| ! Configuration::updateValue ( 'REDSYS_FUC_TARJETA_INSITE', '' )
				|| ! Configuration::updateValue ( 'REDSYS_TERMINAL_TARJETA_INSITE', '' ) 
				|| ! Configuration::updateValue ( 'REDSYS_CLAVE256_TARJETA_INSITE', '' )
				|| ! Configuration::updateValue ( 'REDSYS_MANTENER_CARRITO', 0 ) 
				|| ! Configuration::updateValue ( 'REDSYS_LOG', 1 ) 
				|| ! Configuration::updateValue ( 'REDSYS_LOG_CART', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_MENSAJES_BACKOFFICE', 0 ) 
				|| ! Configuration::updateValue ( 'REDSYS_LOG_STRING', str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') )
				|| ! Configuration::updateValue ( 'REDSYS_IDIOMAS_ESTADO', 0 ) 
				|| ! Configuration::updateValue ( 'REDSYS_ESTADO_PEDIDO', '2' )
				|| ! Configuration::updateValue ( 'REDSYS_NUMERO_PEDIDO', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_PEDIDO_EXTENDIDO', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_REFERENCIA', 0 )
				|| ! Configuration::updateValue ( 'REDSYS_TEXT_BTN', 'REALIZAR PAGO' )
				|| ! Configuration::updateValue ( 'REDSYS_STYLE_BTN', 'background-color:orange;color:black;' )
				|| ! Configuration::updateValue ( 'REDSYS_STYLE_BODY', 'color:black' )
				|| ! Configuration::updateValue ( 'REDSYS_STYLE_FORM', 'color:grey;' )
				|| ! Configuration::updateValue ( 'REDSYS_STYLE_TEXT', ';' )
				|| ! Configuration::updateValue ( 'REDSYS_ACTIVAR_3DS', 1)
				|| ! Configuration::updateValue ( 'REDSYS_MONEDA', '')
				|| ! Configuration::updateValue ( 'REDSYS_URLOK', '')
				|| ! Configuration::updateValue ( 'REDSYS_URLKO', '')
				|| ! $this->registerHook ( 'paymentReturn' ) 
				|| ! $this->registerHook ( 'actionProductCancel' ) 
				|| ( _PS_VERSION_ >= 1.7 ? ! $this->registerHook ( 'paymentOptions' ) : ! $this->registerHook ( 'payment' ))
				) {
			return false;
			
			if ((_PS_VERSION_ > '1.5') && (!$this->registerHook('displayPaymentEU'))) {
				return false;
			}
		}
		$this->createRefTable();
		$this->tratarJSON();
		return true;
	}
	
	/*
	 * Tratamos el JSON es_addons_modules.json para que addons 
	 * TPV REDSYS Pago tarjeta no pise nuestra versión 
	 */
	private function tratarJSON(){
		$fileName = "../app/cache/prod/es_addons_modules.json";
		if(file_exists($fileName) &&  _PS_VERSION_ >= 1.7){
			$data = file_get_contents($fileName);
			$jsonDecode = json_decode($data, true);
				
			if ( $jsonDecode[redsys] != null && $jsonDecode[redsys][name] != null){
				$jsonDecode[redsys][name]="ps_redsys";
				$newJsonString = json_encode($jsonDecode);
				file_put_contents($fileName, $newJsonString);
			}
		}
	}
	
	
	public function uninstall() {
		// Valores a quitar si desinstalamos
		if (!Configuration::deleteByName('REDSYS_URLTPV_REDIR')
			|| !Configuration::deleteByName('REDSYS_URLTPV_INSITE')
			|| !Configuration::deleteByName('REDSYS_URLTPV_BIZUM')
			|| !Configuration::deleteByName('REDSYS_ACTIVAR_TARJETA')
			|| !Configuration::deleteByName('REDSYS_ACTIVAR_TARJETA_MODAL')
			|| !Configuration::deleteByName('REDSYS_ACTIVAR_BIZUM')
			|| !Configuration::deleteByName('REDSYS_ACTIVAR_TARJETA_INSITE')
			|| !Configuration::deleteByName('REDSYS_NOMBRE')
			|| !Configuration::deleteByName('REDSYS_FUC_TARJETA')
			|| !Configuration::deleteByName('REDSYS_TERMINAL_TARJETA')
			|| !Configuration::deleteByName('REDSYS_CLAVE256_TARJETA')
			|| !Configuration::deleteByName('REDSYS_FUC_BIZUM')
			|| !Configuration::deleteByName('REDSYS_TERMINAL_BIZUM')
			|| !Configuration::deleteByName('REDSYS_CLAVE256_BIZUM')
			|| !Configuration::deleteByName('REDSYS_FUC_TARJETA_INSITE')
			|| !Configuration::deleteByName('REDSYS_TERMINAL_TARJETA_INSITE')
			|| !Configuration::deleteByName('REDSYS_CLAVE256_TARJETA_INSITE')
			|| !Configuration::deleteByName('REDSYS_MANTENER_CARRITO')
			|| !Configuration::deleteByName('REDSYS_LOG')
			|| !Configuration::deleteByName('REDSYS_LOG_CART')
			|| !Configuration::deleteByName('REDSYS_MENSAJES_BACKOFFICE')
			|| !Configuration::deleteByName('REDSYS_LOG_STRING')
			|| !Configuration::deleteByName('REDSYS_IDIOMAS_ESTADO')
			|| !Configuration::deleteByName('REDSYS_ESTADO_PEDIDO')
			|| !Configuration::deleteByName('REDSYS_NUMERO_PEDIDO')
			|| !Configuration::deleteByName('REDSYS_PEDIDO_EXTENDIDO')
			|| !Configuration::deleteByName('REDSYS_REFERENCIA')
			|| !Configuration::deleteByName('REDSYS_TEXT_BTN')
			|| !Configuration::deleteByName('REDSYS_STYLE_BTN')
			|| !Configuration::deleteByName('REDSYS_STYLE_BODY')
			|| !Configuration::deleteByName('REDSYS_STYLE_FORM')
			|| !Configuration::deleteByName('REDSYS_STYLE_TEXT')
			|| !Configuration::deleteByName('REDSYS_ACTIVAR_3DS')
			|| !Configuration::deleteByName('REDSYS_MONEDA')
			|| !Configuration::deleteByName('REDSYS_URLOK')
			|| !Configuration::deleteByName('REDSYS_URLKO')
			|| !parent::uninstall())
			return false;

		$this->dropRefTable();
		return true;
	}
	
	private function _postProcess() {
		// Actualizar la config. en la BBDD
		if (Tools::isSubmit ( 'btnSubmit' )) {
			if (empty(Tools::getValue ( 'REDSYS_NOMBRE' )))
				return false;

			if (
				Tools::getValue ( 'REDSYS_ACTIVAR_TARJETA' ) == 1 && (
					empty(Tools::getValue ( 'REDSYS_FUC_TARJETA' )) ||
					empty(Tools::getValue ( 'REDSYS_TERMINAL_TARJETA' )) ||
					empty(Tools::getValue ( 'REDSYS_CLAVE256_TARJETA' ))
				)
			) {
				return false;
			}

			if (
				Tools::getValue ( 'REDSYS_ACTIVAR_BIZUM' ) == 1 && (
					empty(Tools::getValue ( 'REDSYS_FUC_BIZUM' )) ||
					empty(Tools::getValue ( 'REDSYS_TERMINAL_BIZUM' )) ||
					empty(Tools::getValue ( 'REDSYS_CLAVE256_BIZUM' ))
				)
			) {
				return false;
			}

			if (
				Tools::getValue ( 'REDSYS_ACTIVAR_TARJETA_INSITE' ) == 1 && (
					empty(Tools::getValue ( 'REDSYS_FUC_TARJETA_INSITE' )) ||
					empty(Tools::getValue ( 'REDSYS_TERMINAL_TARJETA_INSITE' )) ||
					empty(Tools::getValue ( 'REDSYS_CLAVE256_TARJETA_INSITE' ))
				)
			) {
				return false;
			}

			Configuration::updateValue ( 'REDSYS_URLTPV_REDIR', Tools::getValue ( 'REDSYS_URLTPV_REDIR' ) );
			Configuration::updateValue ( 'REDSYS_URLTPV_INSITE', Tools::getValue ( 'REDSYS_URLTPV_INSITE' ) );
			Configuration::updateValue ( 'REDSYS_URLTPV_BIZUM', Tools::getValue ( 'REDSYS_URLTPV_BIZUM' ) );
			Configuration::updateValue ( 'REDSYS_ACTIVAR_TARJETA', Tools::getValue ( 'REDSYS_ACTIVAR_TARJETA' ) );
			Configuration::updateValue ( 'REDSYS_ACTIVAR_TARJETA_MODAL', Tools::getValue ( 'REDSYS_ACTIVAR_TARJETA_MODAL' ) );
			Configuration::updateValue ( 'REDSYS_ACTIVAR_BIZUM', Tools::getValue ( 'REDSYS_ACTIVAR_BIZUM' ) );
			Configuration::updateValue ( 'REDSYS_ACTIVAR_TARJETA_INSITE', Tools::getValue ( 'REDSYS_ACTIVAR_TARJETA_INSITE' ) );
			Configuration::updateValue ( 'REDSYS_NOMBRE', Tools::getValue ( 'REDSYS_NOMBRE' ) );
			Configuration::updateValue ( 'REDSYS_FUC_TARJETA', Tools::getValue ( 'REDSYS_FUC_TARJETA' ) );
			Configuration::updateValue ( 'REDSYS_TERMINAL_TARJETA', Tools::getValue ( 'REDSYS_TERMINAL_TARJETA' ) );
			Configuration::updateValue ( 'REDSYS_CLAVE256_TARJETA', Tools::getValue ( 'REDSYS_CLAVE256_TARJETA' ) );
			Configuration::updateValue ( 'REDSYS_FUC_BIZUM', Tools::getValue ( 'REDSYS_FUC_BIZUM' ) );
			Configuration::updateValue ( 'REDSYS_TERMINAL_BIZUM', Tools::getValue ( 'REDSYS_TERMINAL_BIZUM' ) );
			Configuration::updateValue ( 'REDSYS_CLAVE256_BIZUM', Tools::getValue ( 'REDSYS_CLAVE256_BIZUM' ) );
			Configuration::updateValue ( 'REDSYS_FUC_TARJETA_INSITE', Tools::getValue ( 'REDSYS_FUC_TARJETA_INSITE' ) );
			Configuration::updateValue ( 'REDSYS_TERMINAL_TARJETA_INSITE', Tools::getValue ( 'REDSYS_TERMINAL_TARJETA_INSITE' ) );
			Configuration::updateValue ( 'REDSYS_CLAVE256_TARJETA_INSITE', Tools::getValue ( 'REDSYS_CLAVE256_TARJETA_INSITE' ) );
			Configuration::updateValue ( 'REDSYS_MANTENER_CARRITO', Tools::getValue ( 'REDSYS_MANTENER_CARRITO' ) );
			Configuration::updateValue ( 'REDSYS_LOG', Tools::getValue ( 'REDSYS_LOG' ) );
			Configuration::updateValue ( 'REDSYS_LOG_CART', Tools::getValue ( 'REDSYS_LOG_CART' ) );
			Configuration::updateValue ( 'REDSYS_MENSAJES_BACKOFFICE', Tools::getValue ( 'REDSYS_MENSAJES_BACKOFFICE' ) );
			Configuration::updateValue ( 'REDSYS_IDIOMAS_ESTADO', Tools::getValue ( 'REDSYS_IDIOMAS_ESTADO' ) );
			Configuration::updateValue ( 'REDSYS_ESTADO_PEDIDO', Tools::getValue ( 'REDSYS_ESTADO_PEDIDO' ) );
			Configuration::updateValue ( 'REDSYS_NUMERO_PEDIDO', Tools::getValue ( 'REDSYS_NUMERO_PEDIDO' ) );
			Configuration::updateValue ( 'REDSYS_PEDIDO_EXTENDIDO', Tools::getValue ( 'REDSYS_PEDIDO_EXTENDIDO' ) );
			Configuration::updateValue ( 'REDSYS_REFERENCIA', Tools::getValue ( 'REDSYS_REFERENCIA' ) );
			Configuration::updateValue ( 'REDSYS_TEXT_BTN', Tools::getValue( 'REDSYS_TEXT_BTN' ) );
			Configuration::updateValue ( 'REDSYS_STYLE_BTN', Tools::getValue ( 'REDSYS_STYLE_BTN' ) );
			Configuration::updateValue ( 'REDSYS_STYLE_BODY', Tools::getValue ( 'REDSYS_STYLE_BODY' ) );
			Configuration::updateValue ( 'REDSYS_STYLE_FORM', Tools::getValue ( 'REDSYS_STYLE_FORM' ) );
			Configuration::updateValue ( 'REDSYS_STYLE_TEXT', Tools::getValue ( 'REDSYS_STYLE_TEXT' ) );
			Configuration::updateValue ( 'REDSYS_ACTIVAR_3DS', Tools::getValue ( 'REDSYS_ACTIVAR_3DS' ) );
			Configuration::updateValue ( 'REDSYS_MONEDA', Tools::getValue ( 'REDSYS_MONEDA' ) );
			Configuration::updateValue ( 'REDSYS_URLOK', Tools::getValue ( 'REDSYS_URLOK' ) );
			Configuration::updateValue ( 'REDSYS_URLKO', Tools::getValue ( 'REDSYS_URLKO' ) );

			$logLevel = Tools::getValue ( 'REDSYS_LOG' );

			escribirLog("INFO ", "00000000000000000configUpdated", 
				"Configuración del módulo actualizada en Base de Datos. Modificado por: [" . $this->context->employee->id . "] " . $this->context->employee->firstname . " " . $this->context->employee->lastname . ".", $logLevel);

			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_URLTPV_REDIR:           		" . Tools::getValue ( 'REDSYS_URLTPV_REDIR' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_URLTPV_INSITE:          		" . Tools::getValue ( 'REDSYS_URLTPV_INSITE' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_URLTPV_BIZUM:           		" . Tools::getValue ( 'REDSYS_URLTPV_BIZUM' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_ACTIVAR_TARJETA:      		" . Tools::getValue ( 'REDSYS_ACTIVAR_TARJETA' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_ACTIVAR_TARJETA_MODAL:      	" . Tools::getValue ( 'REDSYS_ACTIVAR_TARJETA_MODAL' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_ACTIVAR_BIZUM:        		" . Tools::getValue ( 'REDSYS_ACTIVAR_BIZUM' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_ACTIVAR_TARJETA_INSITE:      " . Tools::getValue ( 'REDSYS_ACTIVAR_TARJETA_INSITE' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_NOMBRE:               		" . Tools::getValue ( 'REDSYS_NOMBRE' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_FUC_TARJETA:          		" . Tools::getValue ( 'REDSYS_FUC_TARJETA' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_TERMINAL_TARJETA:     		" . Tools::getValue ( 'REDSYS_TERMINAL_TARJETA' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_CLAVE256_TARJETA:     		" . substr(Tools::getValue ( 'REDSYS_CLAVE256_TARJETA' ), 0, 3) . "*", $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_FUC_BIZUM:            		" . Tools::getValue ( 'REDSYS_FUC_BIZUM' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_TERMINAL_BIZUM:       		" . Tools::getValue ( 'REDSYS_TERMINAL_BIZUM' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_CLAVE256_BIZUM:       		" . substr(Tools::getValue ( 'REDSYS_CLAVE256_BIZUM' ), 0, 3) . "*", $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_FUC_TARJETA_INSITE:          " . Tools::getValue ( 'REDSYS_FUC_TARJETA_INSITE' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_TERMINAL_TARJETA_INSITE:     " . Tools::getValue ( 'REDSYS_TERMINAL_TARJETA_INSITE' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_CLAVE256_TARJETA_INSITE:     " . substr(Tools::getValue ( 'REDSYS_CLAVE256_TARJETA_INSITE' ), 0, 3) . "*", $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_MANTENER_CARRITO:     		" . Tools::getValue ( 'REDSYS_MANTENER_CARRITO' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_LOG:                  		" . Tools::getValue ( 'REDSYS_LOG' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_LOG_CART:              		" . Tools::getValue ( 'REDSYS_LOG_CART' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_MENSAJES_BACKOFFICE:  		" . Tools::getValue ( 'REDSYS_MENSAJES_BACKOFFICE' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_IDIOMAS_ESTADO:       		" . Tools::getValue ( 'REDSYS_IDIOMAS_ESTADO' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_ESTADO_PEDIDO:        		" . Tools::getValue ( 'REDSYS_ESTADO_PEDIDO' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_PEDIDO_EXTENDIDO:      		" . Tools::getValue ( 'REDSYS_PEDIDO_EXTENDIDO' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_NUMERO_PEDIDO:        		" . Tools::getValue ( 'REDSYS_NUMERO_PEDIDO' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_REFERENCIA:           		" . Tools::getValue ( 'REDSYS_REFERENCIA' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_TEXT_BTN:                	" . Tools::getValue ( 'REDSYS_TEXT_BTN' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_STYLE_BTN:                	" . Tools::getValue ( 'REDSYS_STYLE_BTN' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_STYLE_BODY:                	" . Tools::getValue ( 'REDSYS_STYLE_BODY' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_STYLE_FORM:                	" . Tools::getValue ( 'REDSYS_STYLE_FORM' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_STYLE_TEXT:                	" . Tools::getValue ( 'REDSYS_STYLE_TEXT' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_ACTIVAR_3DS:          		" . Tools::getValue ( 'REDSYS_ACTIVAR_3DS' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_MONEDA:                      " . Tools::getValue ( 'REDSYS_MONEDA' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_URLOK:                		" . Tools::getValue ( 'REDSYS_URLOK' ), $logLevel);
			escribirLog("DEBUG", "00000000000000000configUpdated", "REDSYS_URLKO:                		" . Tools::getValue ( 'REDSYS_URLKO' ), $logLevel);
				
			return true;
		}
	}
	
	
	public function _displayRedsys()
	{
        return $this->display(__FILE__, 'info.tpl');
    }

	public function _getForm()
	{
		// Init Fields form array
		$configuracion_tarjeta = [
			'form' => [
				'legend' => [
					'title' => 'Configuración de Pago con Tarjeta por Redirección',
					'icon' => 'icon-credit-card',
				],
				'input' => [
					[
						'type' => 'switch',
                        'name' => 'REDSYS_ACTIVAR_TARJETA',
                        'label' => 'Activación',
                        'hint' => 'Controle si el pago con Tarjeta por redirección debe mostrarse a los clientes como opción de pago disponible',
						'desc' => 'Los campos sólo son obligatorios si se activa el método de pago.',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
					],
					[
						'type' => 'switch',
                        'name' => 'REDSYS_ACTIVAR_TARJETA_MODAL',
                        'label' => 'Habilitar ventana de pago modal',
						'desc' => 'Se muestra el formulario de pago en una ventana modal, creando la sensación al cliente de que en ningún momento está abandonando la tienda para realizar el pago.',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
						'disabled' => false,
					],
					[
						'type' => 'select',
                        'name' => 'REDSYS_URLTPV_REDIR',
                        'label' => 'Entorno de Operación',
						'hint' => 'Cuando el módulo se encuentra configurado como modo "Sandbox", las operaciones no tienen ningún efecto contable',
						'desc' => 'Recuerde no activar el modo "Sandbox" en su entorno de producción, de lo contrario podrían producirse ventas no deseadas. Dispone de más información sobre cómo realizar pruebas <a href=https://pagosonline.redsys.es/entornosPruebas.html target="_blank" rel="noopener noreferrer">aquí</a>.',
						'options' => [
                            'query' => [
                                [
                                    'id' => 0,
                                    'name' => $this->l('Sandbox'),
								],
                                [
                                    'id' => 1,
                                    'name' => $this->l('Producción'),
								],
							],
                            'id' => 'id',
                            'name' => 'name',
						],
					],
					[
						'type' => 'text',
						'label' => 'Número de Comercio',
						'name' => 'REDSYS_FUC_TARJETA',
						'maxlength' => '9',
						'validation' => 'isInt',
						'hint' => 'El número de comercio, también denominado FUC, es un número que identifica a su comercio y debe habérselo provisto su Entidad Bancaria',
						'required' => true,
					],
					[
						'type' => 'text',
						'label' => 'Número de Terminal',
						'name' => 'REDSYS_TERMINAL_TARJETA',
						'maxlength' => '3',
						'validation' => 'isInt',
						'hint' => 'El número de terminal es el número que identifica el terminal dentro de su comercio y debe habérselo provisto su Entidad Bancaria',
						'required' => true,
					],
					[
						'type' => 'text',
						'label' => 'Clave de Encriptación SHA-256',
						'name' => 'REDSYS_CLAVE256_TARJETA',
						'hint' => 'Esta clave permite firmar todas las operaciones enviadas por el módulo y ha debido ser provista de ella por su Entidad Bancaria. Recuerde guardarla en un lugar seguro.',
						'desc' => 'Para realizar pruebas en el entorno Sandbox, puede usar: sq7HjrUOBfKmC576ILgskD5srU870gJ7 o la provista por su Entidad Bancaria',
						'required' => true,
					],
				],
			],
		];

		$configuracion_tarjeta_insite = [
			'form' => [
				'legend' => [
					'title' => 'Configuración de Pago con Tarjeta inSite',
					'icon' => 'icon-credit-card',
				],
				'input' => [
					[
						'type' => 'switch',
                        'name' => 'REDSYS_ACTIVAR_TARJETA_INSITE',
                        'label' => 'Activación',
                        'hint' => 'Controle si el pago con Tarjeta inSite debe mostrarse a los clientes como opción de pago disponible',
						'desc' => 'Los campos sólo son obligatorios si se activa el método de pago.',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
					],
					[
						'type' => 'select',
                        'name' => 'REDSYS_URLTPV_INSITE',
                        'label' => 'Entorno de Operación',
						'hint' => 'Cuando el módulo se encuentra configurado como modo "Sandbox", las operaciones no tienen ningún efecto contable',
						'desc' => 'Recuerde no activar el modo "Sandbox" en su entorno de producción, de lo contrario podrían producirse ventas no deseadas. Dispone de más información sobre cómo realizar pruebas <a href=https://pagosonline.redsys.es/entornosPruebas.html target="_blank" rel="noopener noreferrer">aquí</a>.',
						'options' => [
                            'query' => [
                                [
                                    'id' => 0,
                                    'name' => $this->l('Sandbox'),
								],
                                [
                                    'id' => 1,
                                    'name' => $this->l('Producción'),
								],
							],
                            'id' => 'id',
                            'name' => 'name',
						],
					],
					[
						'type' => 'text',
						'label' => 'Número de Comercio',
						'name' => 'REDSYS_FUC_TARJETA_INSITE',
						'maxlength' => '9',
						'validation' => 'isInt',
						'hint' => 'El número de comercio, también denominado FUC, es un número que identifica a su comercio y debe habérselo provisto su Entidad Bancaria',
						'required' => true,
					],
					[
						'type' => 'text',
						'label' => 'Número de Terminal',
						'name' => 'REDSYS_TERMINAL_TARJETA_INSITE',
						'maxlength' => '3',
						'validation' => 'isInt',
						'hint' => 'El número de terminal es el número que identifica el terminal dentro de su comercio y debe habérselo provisto su Entidad Bancaria',
						'required' => true,
					],
					[
						'type' => 'text',
						'label' => 'Clave de Encriptación SHA-256',
						'name' => 'REDSYS_CLAVE256_TARJETA_INSITE',
						'hint' => 'Esta clave permite firmar todas las operaciones enviadas por el módulo y ha debido ser provista de ella por su Entidad Bancaria. Recuerde guardarla en un lugar seguro.',
						'desc' => 'Para realizar pruebas en el entorno Sandbox, puede usar: sq7HjrUOBfKmC576ILgskD5srU870gJ7 o la provista por su Entidad Bancaria',
						'required' => true,
					],
					[
						'label' => '<br>',
					],
					[
						'label' => '<b>Personalización</b>',
						'desc' => 'Modificar algunos de estos parámetros puede provocar problemas a la hora de mostrar el iframe',
						'required' => false,
					],
					[
						'type' => 'text',
						'label' => 'Texto del botón',
						'name' => 'REDSYS_TEXT_BTN',
						'hint' => 'Texto que se mostrará en el botón de pagar',
						'desc' => '',
						'required' => false,
					],
					[
						'type' => 'text',
						'label' => 'Estilo del botón',
						'name' => 'REDSYS_STYLE_BTN',
						'hint' => 'Personalice el estilo del botón de pagar',
						'desc' => '',
						'required' => false,
					],
					[
						'type' => 'text',
						'label' => 'Estilo del iframe',
						'name' => 'REDSYS_STYLE_BODY',
						'hint' => 'Personalice el color de fondo o modifique el color o estilo de los textos',
						'desc' => '',
						'required' => false,
					],
					[
						'type' => 'text',
						'label' => 'Estilo del formulario',
						'name' => 'REDSYS_STYLE_FORM',
						'hint' => 'Personalice el color de fondo para la caja de introducción de los datos. El color del texto aplicado en este elemento se aplicará al "placeholder" de los elementos',
						'desc' => '',
						'required' => false,
					],
					[
						'type' => 'text',
						'label' => 'Estilo del texto del formulario',
						'name' => 'REDSYS_STYLE_TEXT',
						'hint' => 'Personalice el tipo de letra o color utilizado en el texto de los campos de introducción de datos',
						'desc' => '',
						'required' => false,
					],
				],
			],
		];

		$configuracion_bizum = [
			'form' => [
				'legend' => [
					'title' => 'Configuración de Pago BIZUM',
					'icon' => 'icon-mobile',
				],
				'input' => [
					[
						'type' => 'switch',
                        'desc' => 'ATENCIÓN: Esta configuración podría requerir activación por parte de su Entidad Bancaria. <br> Los campos sólo son obligatorios si se activa el método de pago.',
                        'name' => 'REDSYS_ACTIVAR_BIZUM',
                        'label' => 'Activación',
                        'hint' => 'Controle si el pago con Bizum debe mostrarse a los clientes como opción de pago disponible',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
					],
					[
						'type' => 'select',
                        'name' => 'REDSYS_URLTPV_BIZUM',
                        'label' => 'Entorno de Operación',
						'hint' => 'Cuando el módulo se encuentra configurado como modo "Sandbox", las operaciones no tienen ningún efecto contable',
						'desc' => 'Recuerde no activar el modo "Sandbox" en su entorno de producción, de lo contrario podrían producirse ventas no deseadas. Dispone de más información sobre cómo realizar pruebas <a href=https://pagosonline.redsys.es/entornosPruebas.html target="_blank" rel="noopener noreferrer">aquí</a>.',
						'options' => [
                            'query' => [
                                [
                                    'id' => 0,
                                    'name' => $this->l('Sandbox'),
								],
                                [
                                    'id' => 1,
                                    'name' => $this->l('Producción'),
								],
							],
                            'id' => 'id',
                            'name' => 'name',
						],
					],
					[
						'type' => 'text',
						'label' => 'Número de Comercio',
						'name' => 'REDSYS_FUC_BIZUM',
						'maxlength' => '9',
						'validation' => 'isInt',
						'hint' => 'El número de comercio, también denominado FUC, es un número que identifica a su comercio y debe habérselo provisto su Entidad Bancaria',
						'required' => true,
					],
					[
						'type' => 'text',
						'label' => 'Número de Terminal',
						'name' => 'REDSYS_TERMINAL_BIZUM',
						'maxlength' => '3',
						'validation' => 'isInt',
						'hint' => 'El número de terminal es el número que identifica el terminal dentro de su comercio y debe habérselo provisto su Entidad Bancaria',
						'required' => true,
					],
					[
						'type' => 'text',
						'label' => 'Clave de Encriptación SHA-256',
						'name' => 'REDSYS_CLAVE256_BIZUM',
						'hint' => 'Esta clave permite firmar todas las operaciones enviadas por el módulo y ha debido ser provista de ella por su Entidad Bancaria. Recuerde guardarla en un lugar seguro.',
						'desc' => 'Para realizar pruebas en el entorno Sandbox, puede usar: sq7HjrUOBfKmC576ILgskD5srU870gJ7 o la provista por su Entidad Bancaria',
						'required' => true,
					],
				],
			],
		];

		$parametros_generales = [
			'form' => [
				'legend' => [
					'title' => 'Parámetros Generales del TPV',
					'icon' => 'icon-wrench',
				],
				'input' => [
					[
						'type' => 'text',
						'label' => 'Nombre del Comercio',
						'name' => 'REDSYS_NOMBRE',
						'maxlength' => '50',
						'hint' => 'Nombre de su comercio que se establecerá a la hora de enviar las operaciones',
						'desc' => 'El nombre del comercio no puede superar los 50 caracteres',
						'required' => true,
					],
					[
						'type' => 'switch',
                        'desc' => 'ATENCIÓN: Esta configuración podría requerir activación por parte de su Entidad Bancaria',
                        'name' => 'REDSYS_REFERENCIA',
                        'label' => 'Pago por Referencia',
                        'hint' => 'El Pago por Referencia permite al cliente guardar su tarjeta para futuras compras en formato de Token y de forma totalmente segura',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
					],
					[
						'type' => 'switch',
                        'desc' => 'Se recomienda el envío de esta información en los datos de la operación',
                        'name' => 'REDSYS_ACTIVAR_3DS',
                        'label' => 'Pago seguro usando 3D Secure',
                        'hint' => 'Esta opción permite enviar información adicional del cliente que está realizando la compra, proporcionando más seguirdad a la hora de autenticar la operación',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
					],
					[
						'type' => 'select',
                        'label' => 'Estado del pedido al verificarse el pago',
                        'name' => 'REDSYS_ESTADO_PEDIDO',
						'hint' => 'Aquí puede configurar el estado en el que se mostrará el pedido en el apartado "Pedidos" de su backoffice una vez el módulo reciba la notificación de que el pago ha sido correcto',
						'options' => [
                            'query' => OrderState::getOrderStates($this->context->language->id),
                            'id' => 'id_order_state',
                            'name' => 'name',
						],
					],
					[
						'type' => 'select',
                        'name' => 'REDSYS_NUMERO_PEDIDO',
                        'label' => 'Método de generación del número de pedido',
						'hint' => 'Configure cómo se generará el número de pedido que se enviará a Redsys para identificar la operación en el Portal de Administración del TPV Virtual',
						'desc' => 'Esta opción no modifica la forma en la que se identifica la orden en su Backoffice, sino el número de pedido (adaptado para que siempre ocupe doce dígitos) que se envía a Redsys para identificar la operación<br>Recuerde que en los detalles de cada orden puede ver el número de pedido que identifica la operación en el Portal de Administración del TPV Virtual.',
						'options' => [
                            'query' => [
                                [
                                    'id' => 0,
                                    'name' => $this->l('Híbrido (recomendado)'),
								],
                                [
                                    'id' => 1,
                                    'name' => $this->l('Sólo ID del carrito'),
								],
								[
                                    'id' => 2,
                                    'name' => $this->l('Aleatorio'),
								],
							],
                            'id' => 'id',
                            'name' => 'name',
						],
					],
					[
						'type' => 'switch',
                        'name' => 'REDSYS_PEDIDO_EXTENDIDO',
                        'label' => 'Permitir número de pedido extendido',
                        'hint' => 'Marque esta opción si su terminal está configurado para admitir números de pedidos extendidos. Esto es útil para tiendas cuyos número de pedidos podrían exceder las doce posiciones que tiene como máximo un número de pedido estándar',
						'desc' => 'ATENCIÓN: Esta configuración podría requerir activación por parte de su Entidad Bancaria<br>Esta opción no es compatible con el formulario inSite, por lo que recomendamos mantenerla desactivada para usar inSite como pasarela.',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
					],
					[
						'type' => 'switch',
                        'name' => 'REDSYS_MANTENER_CARRITO',
                        'label' => 'Mantener el carrito en caso de error',
//                        'hint' => 'Si activa esta opción, el carrito no se borrará si la operación no es correcta y el cliente podrá intentarlo de nuevo',
						'hint' => 'Esta característica está desactivada temporalmente',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
						'disabled' => true,
					],
					[
						'type' => 'switch',
                        'name' => 'REDSYS_IDIOMAS_ESTADO',
                        'label' => 'Permitir seleccionar idioma en el TPV',
                        'hint' => 'Si activa esta opción, el cliente podrá visualizar en su idioma, además de seleccionar cualquier otro, la pantalla de pago de Redsys',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
					],
					[
						'type' => 'select',
                        'label' => 'Guardar registros de comportamiento',
                        'name' => 'REDSYS_LOG',
						'hint' => 'Si activa esta opción, se guardarán registros (logs) de los procesos que realice el módulo dentro del archivo \'redsysLog.log\' en la carpeta logs del módulo',
                        'desc' => 'A la hora de notificar cualquier incidencia, los logs completos son de gran utilidad para poder detectar el problema',
						'options' => [
                            'query' => [
                                [
                                    'id' => '0',
                                    'name' => $this->l('No'),
								],
                                [
                                    'id' => '1',
                                    'name' => $this->l('Sí, sólo informativos'),
								],
								[
                                    'id' => '2',
                                    'name' => $this->l('Sí, todos los registros'),
								],
							],
                            'id' => 'id',
                            'name' => 'name',
						],
					],
					[
						'type' => 'switch',
                        'label' => 'Imprimir información del carrito y del cliente en el registro de comportamiento',
                        'name' => 'REDSYS_LOG_CART',
						'hint' => 'Guarda la información contenida por el objeto $cart y $customer para posterior análisis. Útil si se tiene problemas de pérdida de información del pedido o el cliente al validar una orden',
                        'desc' => 'ATENCIÓN: Salvo para depuración de errores, recomendamos mantener desactivada esta opción, ya que estos objetos podrían contener información sensible del cliente y de la tienda',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
					],
					[
						'type' => 'switch',
                        'label' => 'Mostrar respuesta de Redsys en la información del pedido',
                        'name' => 'REDSYS_MENSAJES_BACKOFFICE',
						'hint' => 'Debido a un problema ajeno a Redsys, esta característica está desactivada temporalmente por resultar inestable',
                        'desc' => 'Esta opción permite adjuntar en el pedido la respuesta recibida por parte de Redsys para ser consultada rápidamente desde la tienda',
						'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                        'is_bool' => true,
						'disabled' => true,
					],
					[
						'label' => '<br>',
					],
					[
						'label' => '<b>Parámetros avanzados</b>',
						'desc' => 'Los cambios en estos ajustes se realizan bajo su propia responsabilidad.',
					],
					[
						'type' => 'text',
						'label' => 'Moneda personalizada para operaciones',
						'name' => 'REDSYS_MONEDA',
						'maxlength' => '3',
						'validation' => 'isInt',
						'hint' => 'Configure la moneda que se enviará en el campo Ds_Mercant_Currency, deberá especificar el codigo ISO de la moneda a utilizar',
						'desc' => 'ATENCIÓN: Esta configuración sobreescribirá la detección automática de moneda, su terminal deberá estar configurado para usar la moneda que aquí establezca si es distinta al Euro. <br>Deje en blanco para usar la detección automática. Use esta configuración sí y sólo sí su comercio está recibiendo errores SIS0015 o SIS0027.',
						'placeholder' => 'Introduzca el código ISO de la moneda, sólo uno y sólo el número (978: EUR, 840: USD, 826: GBP, ...)',
						'required' => false,
					],
					[
						'type' => 'text',
						'label' => 'URL para operaciones correctas',
						'name' => 'REDSYS_URLOK',
						'hint' => 'Este campo, denominado URL_OK, establece a qué página se redirigirá al cliente al volver de Redsys una vez la operación haya finalizado y esta sea correcta',
						'desc' => 'Si este campo se rellena, se ignorará la configuración del parámetro establecida en el Portal de Administración del TPV Virtual. Tenga en cuenta que deberá establecer este campo con la dirección completa de la página a la que quiere redirigir, usando procotolo (https://) y dominio completos',
						'required' => false,
					],
					[
						'type' => 'text',
						'label' => 'URL para operaciones erróneas',
						'name' => 'REDSYS_URLKO',
						'hint' => 'Este campo, denominado URL_KO, establece a qué página se redirigirá al cliente al volver de Redsys una vez la operación haya finalizado y esta haya tenido algún error',
						'desc' => 'Si este campo se rellena, se ignorará la configuración del parámetro establecida en el Portal de Administración del TPV Virtual. Tenga en cuenta que deberá establecer este campo con la dirección completa de la página a la que quiere redirigir, usando procotolo (https://) y dominio completos',
						'required' => false,
					],
				],
				'buttons' => [
					'array' => [
						'title' => 'Versión del módulo: '. $this->version,
						'class' => 'btn',
						'disabled' => 'true',
					]
				],
				'submit' => [
					'title' => 'Guardar configuración',
					'class' => 'btn btn-default pull-right',
				],
			],
		];

		$helper = new HelperForm();

		// Module, token and currentIndex
		$helper->table = $this->table;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
//		$helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&id='.Tools::getValue('id');
		$helper->submit_action = 'btnSubmit';

		$helper->show_cancel_button = true;

		// Default language
		$helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

		// Load current value into the form
		$helper->fields_value['REDSYS_URLTPV_REDIR'] = Tools::getValue('REDSYS_URLTPV_REDIR', Configuration::get('REDSYS_URLTPV_REDIR'));
		$helper->fields_value['REDSYS_URLTPV_INSITE'] = Tools::getValue('REDSYS_URLTPV_INSITE', Configuration::get('REDSYS_URLTPV_INSITE'));
		$helper->fields_value['REDSYS_URLTPV_BIZUM'] = Tools::getValue('REDSYS_URLTPV_BIZUM', Configuration::get('REDSYS_URLTPV_BIZUM'));
		$helper->fields_value['REDSYS_ACTIVAR_TARJETA'] = Tools::getValue('REDSYS_ACTIVAR_TARJETA', Configuration::get('REDSYS_ACTIVAR_TARJETA'));
		$helper->fields_value['REDSYS_ACTIVAR_TARJETA_MODAL'] = Tools::getValue('REDSYS_ACTIVAR_TARJETA_MODAL', Configuration::get('REDSYS_ACTIVAR_TARJETA_MODAL'));
		$helper->fields_value['REDSYS_ACTIVAR_BIZUM'] = Tools::getValue('REDSYS_ACTIVAR_BIZUM', Configuration::get('REDSYS_ACTIVAR_BIZUM'));
		$helper->fields_value['REDSYS_ACTIVAR_TARJETA_INSITE'] = Tools::getValue('REDSYS_ACTIVAR_TARJETA_INSITE', Configuration::get('REDSYS_ACTIVAR_TARJETA_INSITE'));
		$helper->fields_value['REDSYS_NOMBRE'] = Tools::getValue('REDSYS_NOMBRE', Configuration::get('REDSYS_NOMBRE'));
		$helper->fields_value['REDSYS_FUC_TARJETA'] = Tools::getValue('REDSYS_FUC_TARJETA', Configuration::get('REDSYS_FUC_TARJETA'));
		$helper->fields_value['REDSYS_TERMINAL_TARJETA'] = Tools::getValue('REDSYS_TERMINAL_TARJETA', Configuration::get('REDSYS_TERMINAL_TARJETA'));
		$helper->fields_value['REDSYS_CLAVE256_TARJETA'] = Tools::getValue('REDSYS_CLAVE256_TARJETA', Configuration::get('REDSYS_CLAVE256_TARJETA'));
		$helper->fields_value['REDSYS_FUC_BIZUM'] = Tools::getValue('REDSYS_FUC_BIZUM', Configuration::get('REDSYS_FUC_BIZUM'));
		$helper->fields_value['REDSYS_TERMINAL_BIZUM'] = Tools::getValue('REDSYS_TERMINAL_BIZUM', Configuration::get('REDSYS_TERMINAL_BIZUM'));
		$helper->fields_value['REDSYS_CLAVE256_BIZUM'] = Tools::getValue('REDSYS_CLAVE256_BIZUM', Configuration::get('REDSYS_CLAVE256_BIZUM'));
		$helper->fields_value['REDSYS_FUC_TARJETA_INSITE'] = Tools::getValue('REDSYS_FUC_TARJETA_INSITE', Configuration::get('REDSYS_FUC_TARJETA_INSITE'));
		$helper->fields_value['REDSYS_TERMINAL_TARJETA_INSITE'] = Tools::getValue('REDSYS_TERMINAL_TARJETA_INSITE', Configuration::get('REDSYS_TERMINAL_TARJETA_INSITE'));
		$helper->fields_value['REDSYS_CLAVE256_TARJETA_INSITE'] = Tools::getValue('REDSYS_CLAVE256_TARJETA_INSITE', Configuration::get('REDSYS_CLAVE256_TARJETA_INSITE'));
		$helper->fields_value['REDSYS_ACTIVAR_3DS'] = Tools::getValue('REDSYS_ACTIVAR_3DS', Configuration::get('REDSYS_ACTIVAR_3DS'));
		$helper->fields_value['REDSYS_MANTENER_CARRITO'] = Tools::getValue('REDSYS_MANTENER_CARRITO', Configuration::get('REDSYS_MANTENER_CARRITO'));
		$helper->fields_value['REDSYS_LOG'] = Tools::getValue('REDSYS_LOG', Configuration::get('REDSYS_LOG'));
		$helper->fields_value['REDSYS_LOG_CART'] = Tools::getValue('REDSYS_LOG_CART', Configuration::get('REDSYS_LOG_CART'));
		$helper->fields_value['REDSYS_MENSAJES_BACKOFFICE'] = Tools::getValue('REDSYS_MENSAJES_BACKOFFICE', Configuration::get('REDSYS_MENSAJES_BACKOFFICE'));
		$helper->fields_value['REDSYS_IDIOMAS_ESTADO'] = Tools::getValue('REDSYS_IDIOMAS_ESTADO', Configuration::get('REDSYS_IDIOMAS_ESTADO'));
		$helper->fields_value['REDSYS_ESTADO_PEDIDO'] = Tools::getValue('REDSYS_ESTADO_PEDIDO', Configuration::get('REDSYS_ESTADO_PEDIDO'));
		$helper->fields_value['REDSYS_NUMERO_PEDIDO'] = Tools::getValue('REDSYS_NUMERO_PEDIDO', Configuration::get('REDSYS_NUMERO_PEDIDO'));
		$helper->fields_value['REDSYS_PEDIDO_EXTENDIDO'] = Tools::getValue('REDSYS_PEDIDO_EXTENDIDO', Configuration::get('REDSYS_PEDIDO_EXTENDIDO'));
		$helper->fields_value['REDSYS_REFERENCIA'] = Tools::getValue('REDSYS_REFERENCIA', Configuration::get('REDSYS_REFERENCIA'));
		$helper->fields_value['REDSYS_TEXT_BTN'] = Tools::getValue('REDSYS_TEXT_BTN', Configuration::get('REDSYS_TEXT_BTN'));
		$helper->fields_value['REDSYS_STYLE_BTN'] = Tools::getValue('REDSYS_STYLE_BTN', Configuration::get('REDSYS_STYLE_BTN'));
		$helper->fields_value['REDSYS_STYLE_BODY'] = Tools::getValue('REDSYS_STYLE_BODY', Configuration::get('REDSYS_STYLE_BODY'));
		$helper->fields_value['REDSYS_STYLE_FORM'] = Tools::getValue('REDSYS_STYLE_FORM', Configuration::get('REDSYS_STYLE_FORM'));
		$helper->fields_value['REDSYS_STYLE_TEXT'] = Tools::getValue('REDSYS_STYLE_TEXT', Configuration::get('REDSYS_STYLE_TEXT'));
		$helper->fields_value['REDSYS_ACTIVAR_3DS'] = Tools::getValue('REDSYS_ACTIVAR_3DS', Configuration::get('REDSYS_ACTIVAR_3DS'));
		$helper->fields_value['REDSYS_MONEDA'] = Tools::getValue('REDSYS_MONEDA', Configuration::get('REDSYS_MONEDA'));
		$helper->fields_value['REDSYS_URLOK'] = Tools::getValue('REDSYS_URLOK', Configuration::get('REDSYS_URLOK'));
		$helper->fields_value['REDSYS_URLKO'] = Tools::getValue('REDSYS_URLKO', Configuration::get('REDSYS_URLKO'));

		return $helper->generateForm(array($configuracion_tarjeta, $configuracion_tarjeta_insite, $configuracion_bizum, $parametros_generales));
	}

	public function getContent()
    {
		$return = '';
		if (Tools::isSubmit('btnSubmit')) {
			$result = $this->_postProcess();
			
			if(!$result)
				$return .= $this->displayError('Error guardando la configuración, revise que todos los datos requeridos se han introducido.');
			else
				$return .= $this->displayConfirmation('Se ha guardado la configuración correctamente.');
		}

		$return .= $this->_displayRedsys();
        $return .= $this->_getForm();

        return $return;
    }
	
	private function createParameter($params){
		
		// Valor de compra
		$currency = new Currency($params['cart']->id_currency);
		$currency_decimals = is_array($currency) ? (int) $currency['decimals'] : (int) $currency->decimals;
		$cart_details = $params['cart']->getSummaryDetails(null, true);
		$decimals = $currency_decimals * _PS_PRICE_DISPLAY_PRECISION_;
		
		$shipping = $cart_details['total_shipping_tax_exc'];
		$subtotal = $cart_details['total_price_without_tax'] - $cart_details['total_shipping_tax_exc'];
		$tax = $cart_details['total_tax'];
		
		$total_price = Tools::ps_round($shipping + $subtotal + $tax, $decimals);
		$cantidad = number_format($total_price, 2, '', '');
		$cantidad = (int)$cantidad;
	
		// NUMERO DE PEDIDO - Añadimos time() para evitar SIS0051.	
		$orderId = $params ['cart']->id;
		if( ! isset($_COOKIE['nPedSession']) )
			$numpedido = $this->generaNumeroPedido($orderId, Configuration::get ( 'REDSYS_NUMERO_PEDIDO' ), Configuration::get ( 'REDSYS_PEDIDO_EXTENDIDO' ) == 1);
		else
			$numpedido = $_COOKIE['nPedSession'];

		// ISO Moneda
		$moneda = $currency->iso_code_num;

		// URL de Respuesta Online
		if (empty ( $_SERVER ['HTTPS'] )) {
			$protocolo = 'http://';
			$urltienda = $protocolo . $_SERVER ['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?fc=module&module=redsyspur&controller=validation';
		} else {
			$protocolo = 'https://';
			$urltienda = $protocolo . $_SERVER ['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?fc=module&module=redsyspur&controller=validation';
		}
		
		// Product Description
		$products = $params ['cart']->getProducts ();
		$productos = '';
		foreach ( $products as $product )
			$productos .= $product ['quantity'] . ' ' . Tools::truncate ( $product ['name'], 50 ) . ' ';
		
		$productos = str_replace ( "%", "&#37;", $productos );
	
		// Idiomas del TPV
		$idiomas_estado = $this->REDSYS_IDIOMAS_ESTADO;
		if ($idiomas_estado == 'si') {
			$idioma_web = Tools::substr ( $_SERVER ['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
				
			switch ($idioma_web) {
				case 'es' :
					$idioma_tpv = '001';
					break;
				case 'en' :
					$idioma_tpv = '002';
					break;
				case 'ca' :
					$idioma_tpv = '003';
					break;
				case 'fr' :
					$idioma_tpv = '004';
					break;
				case 'de' :
					$idioma_tpv = '005';
					break;
				case 'nl' :
					$idioma_tpv = '006';
					break;
				case 'it' :
					$idioma_tpv = '007';
					break;
				case 'sv' :
					$idioma_tpv = '008';
					break;
				case 'pt' :
					$idioma_tpv = '009';
					break;
				case 'pl' :
					$idioma_tpv = '011';
					break;
				case 'gl' :
					$idioma_tpv = '012';
					break;
				case 'eu' :
					$idioma_tpv = '013';
					break;
				default :
					$idioma_tpv = '002';
			}
		} else
			$idioma_tpv = '0';
				
			// Variable cliente
			$customer = new Customer ( $params ['cart']->id_customer );
			$id_cart = ( int ) $params ['cart']->id;
			$miObj = new RedsysAPI ();
			$miObj->setParameter ( "DS_MERCHANT_AMOUNT", $cantidad );
			$miObj->setParameter ( "DS_MERCHANT_ORDER", strval ( $numpedido ) );
			$miObj->setParameter ( "DS_MERCHANT_MERCHANTCODE", Configuration::get( 'REDSYS_FUC_TARJETA' ) );
			$miObj->setParameter ( "DS_MERCHANT_CURRENCY", $moneda );
			$miObj->setParameter ( "DS_MERCHANT_TRANSACTIONTYPE", 0 ); //TransactionType must be 0.
			$miObj->setParameter ( "DS_MERCHANT_TERMINAL", Configuration::get( 'REDSYS_TERMINAL_TARJETA' ) );
			$miObj->setParameter ( "DS_MERCHANT_MERCHANTURL", $urltienda );
			$miObj->setParameter ( "Ds_Merchant_ConsumerLanguage", $idioma_tpv );
			$miObj->setParameter ( "Ds_Merchant_ProductDescription", $productos );
			$miObj->setParameter ( "Ds_Merchant_Titular", $customer->firstname . " " . $customer->lastname );
			$miObj->setParameter ( "Ds_Merchant_MerchantName", Configuration::get( 'REDSYS_NOMBRE' ) );
			$miObj->setParameter ( "Ds_Merchant_PayMethods", '' );
			$miObj->setParameter ( "Ds_Merchant_Module", "PR-PURv" . $this->version );

			$merchantData = $this->createMerchantData($this->moduleComent, $id_cart);
			$miObj->setParameter ( "Ds_Merchant_MerchantData", b64url_encode($merchantData) );


			/** FIJACION DE URL OK Y KO EN FUNCION DE SI ESTÁN CONFIGURADAS EN BD */
			if ( Configuration::get( 'REDSYS_URLOK' ) != NULL || Configuration::get( 'REDSYS_URLOK' )!= '' )
				$miObj->setParameter ( "DS_MERCHANT_URLOK", Configuration::get( 'REDSYS_URLOK' ) );
			else
				$miObj->setParameter ( "DS_MERCHANT_URLOK", $protocolo . $_SERVER ['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $id_cart . '&id_module=' . $this->id . '&id_order=' . $this->currentOrder . '&key=' . $customer->secure_key );

			if ( Configuration::get( 'REDSYS_URLKO' ) != NULL || Configuration::get( 'REDSYS_URLKO' )!= '' )
				$miObj->setParameter ( "DS_MERCHANT_URLKO", Configuration::get( 'REDSYS_URLKO' ) );
			else
				$miObj->setParameter ( "DS_MERCHANT_URLKO", $urltienda );
			/** */
				
			if ($this->REDSYS_ACTIVAR_3DS == 'si')
				include 'redsys_3ds.php';

			// Datos de configuración
			$this->version2 = getVersionClave ();

			// Clave del comercio que se extrae de la configuración del comercio
			// Se generan los parámetros de la petición
			$request = "";
			$this->paramsBase64 = $miObj->createMerchantParameters ();
			$this->signatureMac = $miObj->createMerchantSignature ( Configuration::get( 'REDSYS_CLAVE256_TARJETA' ) );

			$withRef = false;
			$allowReference = Configuration::get( 'REDSYS_REFERENCIA' )==1;
			if($allowReference){
				$miObj->setParameter("Ds_Merchant_Identifier", "REQUIRED");
				$this->paramsBase64SaveRef = $miObj->createMerchantParameters ();
				$this->signatureMacSaveRef = $miObj->createMerchantSignature ( Configuration::get( 'REDSYS_CLAVE256_TARJETA' ) );
	
				$ref=$this->getCustomerRef($params['cart']->id_customer);
				$withRef = ($ref != null);
				if($withRef){
					$miObj->setParameter("Ds_Merchant_Identifier", $ref[0]);
					$this->paramsBase64WithRef = $miObj->createMerchantParameters ();
					$this->signatureMacWithRef = $miObj->createMerchantSignature ( Configuration::get( 'REDSYS_CLAVE256_TARJETA' ) );
				}
			}

			$allowBizum = Configuration::get( 'REDSYS_ACTIVAR_BIZUM' )==1;

			if($allowBizum){
				$miObj->setParameter("Ds_Merchant_Identifier", '');
				$miObj->setParameter("Ds_Merchant_PayMethods", 'z');

				$miObj->setParameter ( "DS_MERCHANT_MERCHANTCODE", Configuration::get( 'REDSYS_FUC_BIZUM' ) );
				$miObj->setParameter ( "DS_MERCHANT_TERMINAL", Configuration::get( 'REDSYS_TERMINAL_BIZUM' ) );

				$this->paramsBase64WithBizum = $miObj->createMerchantParameters ();
				$this->signatureMacWithBizum = $miObj->createMerchantSignature ( Configuration::get( 'REDSYS_CLAVE256_BIZUM' ) );
			}

			$this->smarty->assign ( array (
					'urltpv' => $this->urlTPVredir,
					'signatureVersion' => $this->version2,
					'parameter' => $this->paramsBase64,
					'signature' => $this->signatureMac,
					'this_path' => $this->_path
			) );
				
	}
	
	public function hookDisplayPaymentEU($params){
		if ($this->hookPayment($params) == null) {
			return null;
		}
	
		return array(
				'cta_text' => "Pagar con tarjeta",
				'logo' => _MODULE_DIR_."redsyspur/img/tarjetas.png",
				'form' => $this->display(__FILE__, "views/templates/hook/payment_eu.tpl"),
		);
	}
	
	
	/*
	 * HOOK V1.6
	 */
	public function hookPayment($params) {
		
		if (! $this->active) {
			return;
		}
		
		if (! $this->checkCurrency ( $params ['cart'] )) {
			return;
		}
		
		$this->createParameter($params);
		
		return $this->display(__FILE__, 'payment.tpl');
	}
	
	/*
	 * HOOK V1.7
	 */
	public function hookPaymentOptions($params) {

		if (! $this->active) {
			return;
		}
		
		if (! $this->checkCurrency ( $params ['cart'] )) {
			return;
		}
		$payment_options = array();
		
		$this->createParameter($params);

		$allowTarjeta = 0;
		$allowTarjetaModal = 0;
		$allowReference = 0;
		$allowBizum = 0;
		$allowTarjetaInsite = 0;

		$logLevel  = Configuration::get( 'REDSYS_LOG' );
		$logString = Configuration::get( 'REDSYS_LOG_STRING' );
		$numpedido = json_decode(base64_decode($this->paramsBase64))->DS_MERCHANT_ORDER;

		$idLog = generateIdLog($logLevel, $logString, $numpedido);

		$allowTarjeta = (Configuration::get( 'REDSYS_ACTIVAR_TARJETA' ) == 1);
		$allowReference = Configuration::get( 'REDSYS_REFERENCIA' )==1;
		$allowTarjetaModal = Configuration::get( 'REDSYS_ACTIVAR_TARJETA_MODAL' )==1;
		$allowBizum = Configuration::get( 'REDSYS_ACTIVAR_BIZUM' )==1;
		$allowTarjetaInsite = (Configuration::get( 'REDSYS_ACTIVAR_TARJETA_INSITE' ) == 1);

		if($allowTarjeta) {
			if($allowTarjetaModal){
				$this->context->smarty->assign ( array (
					'url_modal' => $this->urlModal,
					'environment_modal' => $this->environmentModal,
					'Ds_SignatureVersion' => $this->version2,
					'Ds_MerchantParameters' => $this->paramsBase64,
					'Ds_Signature' => $this->signatureMac,
					'url_ko' => $this->_endpoint_paymentko,
				) );
			
				$newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
				$newOption->setCallToActionText($this->l('Pago con tarjeta'))
					->setModuleName($this->name)
					->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/templates/front/img/tarjetas-ico.png'))
					->setAdditionalInformation($this->context->smarty->fetch('module:redsyspur/views/templates/front/paymentmodal.tpl'))
					->setBinary(true);
				$payment_options[] = $newOption;
			}else{

				$newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
				$newOption->setCallToActionText ($this->l('Pago con tarjeta'))
						->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/templates/front/img/tarjetas-ico.png'))
						->setAction ($this->urlTPVredir)
						->setInputs(array(
								'Ds_SignatureVersion' => array(
										'name' =>'Ds_SignatureVersion',
										'type' =>'hidden',
										'value' =>$this->version2,
								),
								'Ds_MerchantParameters' => array(
										'name' =>'Ds_MerchantParameters',
										'type' =>'hidden',
										'value' =>$this->paramsBase64,
								),
								'Ds_Signature' => array(
										'name' =>'Ds_Signature',
										'type' =>'hidden',
										'value' => $this->signatureMac,
								),
								'PayNew' => array(
									'name' =>'PayNew',
									'type' =>'hidden',
									'value' => 'PayNew',
								),
				));
	
				$payment_options[] = $newOption;
	
				if($allowReference){
					$html = '<input name="checkbox_save_ref" type="checkbox" onchange="onChangeSaveRef()"> Guardar tarjeta para futuras compras en esta tienda';
					$html .= '<script type="text/javascript">
						const Ds_MerchantParameters_New = "{Ds_MerchantParameters_New}";
						const Ds_Signature_New = "{Ds_Signature_New}";
						const Ds_MerchantParameters_SaveRef = "{Ds_MerchantParameters_SaveRef}";
						const Ds_Signature_SaveRef = "{Ds_Signature_SaveRef}";
						var saveRef = false;
		
						window.document.onload = function(){ 
							onChangeParameters();
						}
		
						function onChangeSaveRef(){
							saveRef = $("input[type=checkbox][name=checkbox_save_ref]").is(":checked");
							onChangeParameters();
						}
						
						function onChangeParameters(){
							if(saveRef){
								$("input[type=hidden][name=PayNew]").closest("form").find("input[type=hidden][name=Ds_MerchantParameters]").val(Ds_MerchantParameters_SaveRef);
								$("input[type=hidden][name=PayNew]").closest("form").find("input[type=hidden][name=Ds_Signature]").val(Ds_Signature_SaveRef);
							}
							else
							{
								$("input[type=hidden][name=PayNew]").closest("form").find("input[type=hidden][name=Ds_MerchantParameters]").val(Ds_MerchantParameters_New);
								$("input[type=hidden][name=PayNew]").closest("form").find("input[type=hidden][name=Ds_Signature]").val(Ds_Signature_New);
							}
						}
					</script>';
		
					$html=str_replace("{Ds_MerchantParameters_New}", $this->paramsBase64,$html);
					$html=str_replace("{Ds_Signature_New}", $this->signatureMac,$html);
					$html=str_replace("{Ds_MerchantParameters_SaveRef}", $this->paramsBase64SaveRef,$html);
					$html=str_replace("{Ds_Signature_SaveRef}", $this->signatureMacSaveRef,$html);
		
					$newOption->setAdditionalInformation($html);
				}

				if($allowReference && $this->getCustomerRef($params['cart']->id_customer) != null){
					$refRegister=$this->getCustomerRef($params['cart']->id_customer);
					$cardNumber=$refRegister[1];
					$brand=$refRegister[2];
					$cardType=$refRegister[3];
					
					$newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
					$newOption->setCallToActionText ($this->l('Pago con tarjeta') . " " . $cardNumber)
							->setAction ($this->urlTPVredir)
							->setInputs(array(
									'Ds_SignatureVersion' => array(
											'name' =>'Ds_SignatureVersion',
											'type' =>'hidden',
											'value' =>$this->version2,
									),
									'Ds_MerchantParameters' => array(
											'name' =>'Ds_MerchantParameters',
											'type' =>'hidden',
											'value' =>$this->paramsBase64WithRef,
									),
									'Ds_Signature' => array(
											'name' =>'Ds_Signature',
											'type' =>'hidden',
											'value' => $this->signatureMacWithRef,
									)
					));
					if($brand!=null){
						$newOption->setLogo(__PS_BASE_URI__.'modules/'.$this->name.'/views/templates/front/img/brands/'.$brand.'.jpg');
					}
			
					$payment_options[] = $newOption;
				}
			}
		}

		if($allowBizum){
			$newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
			$newOption->setCallToActionText ($this->l('Pago con Bizum'))
					->setAction ($this->urlTPVbizum)
					->setInputs(array(
							'Ds_SignatureVersion' => array(
									'name' =>'Ds_SignatureVersion',
									'type' =>'hidden',
									'value' =>$this->version2,
							),
							'Ds_MerchantParameters' => array(
									'name' =>'Ds_MerchantParameters',
									'type' =>'hidden',
									'value' =>$this->paramsBase64WithBizum,
							),
							'Ds_Signature' => array(
									'name' =>'Ds_Signature',
									'type' =>'hidden',
									'value' => $this->signatureMacWithBizum,
							)
			));
			
			$payment_options[] = $newOption;
		}

		if($allowTarjetaInsite) {
			if($this->getRedsysCookie($params['cart']->id)==null){	
				$params2=$this->createParameters($params['cart']->id);
				$this->context->smarty->assign ( array (
						'disk_path' => realpath(dirname(__FILE__)),
						'this_path' => __PS_BASE_URI__ . 'modules/' . $this->name,
						'merchant_fuc' => Configuration::get ( 'REDSYS_FUC_TARJETA_INSITE' ),
						'merchant_term' => Configuration::get ( 'REDSYS_TERMINAL_TARJETA_INSITE' ),
						'merchant_order' => $numpedido,
						'idCart' => $params['cart']->id,
						'merchant_amount' => $params2 ["amount"] . " " . $params2 ["currency"],
						'shop_name' => Configuration::get ( 'PS_SHOP_NAME' ),
						'proc_url' => $this->_endpoint_processpayment,
						'ref_url' => $this->_endpoint_processpaymentref,
						'url_ko' => $this->_endpoint_paymentko,
						'allow_ref' => $params2["allow_ref"],
						'btn_text'	=> Configuration::get( 'REDSYS_TEXT_BTN' ),
						'btn_style' => Configuration::get( 'REDSYS_STYLE_BTN' ),
						'body_style' => Configuration::get( 'REDSYS_STYLE_BODY' ),
						'form_style' => Configuration::get( 'REDSYS_STYLE_FORM' ),
						'form_text_style' => Configuration::get( 'REDSYS_STYLE_TEXT' ),
						'redsys_domain' => $params2 ["redsys_domain"]
				) );
				
				if( !$params['cart']->isGuestCartByCartId($params['cart']->id) 
						&& $this->getCustomerRef($params['cart']->id_customer)!=null){
					$refRegister=$this->getCustomerRef($params['cart']->id_customer);
					$cardNumber=$refRegister[1];
					$brand=$refRegister[2];
					$cardType=$refRegister[3];
					
					$newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
					$newOption->setCallToActionText ($this->l('Pago con tarjeta') . " " . $cardNumber)
						->setAdditionalInformation($this->context->smarty->fetch('module:redsyspur/views/templates/front/paymentrefform.tpl'))
						->setBinary(true);
					if($brand!=null){
						$newOption->setLogo(__PS_BASE_URI__.'modules/'.$this->name.'/views/templates/front/img/brands/'.$brand.'.jpg');
					}
					
					$payment_options[] = $newOption;
				}
				
				$newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
				$newOption->setCallToActionText($this->l('Pago con tarjeta'))
					->setModuleName($this->name)
					->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/templates/front/img/tarjetas-ico.png'))
					->setAdditionalInformation($this->context->smarty->fetch('module:redsyspur/views/templates/front/paymentform.tpl'))
					->setBinary(true);
				$payment_options[] = $newOption;
			}
		}

		$this->logInitialStatus($idLog, $numpedido, $this->paramsBase64, $this->signatureMac, $allowTarjeta, $allowReference, $allowBizum, $allowTarjetaInsite);	
		return $payment_options;
	}
	
	
	public function hookPaymentReturn($params) {
		$totaltoPay = null;
		$idOrder = null;

		if(isset($_COOKIE['nPedSession']))
            setcookie("nPedSession", "", time() - 3600);
	
		if( _PS_VERSION_ >= 1.7){
			$totaltoPay = Tools::displayPrice ( $params ['order']->getOrdersTotalPaid (), new Currency ( $params ['order']->id_currency ), false );
			$idOrder = $params ['order']->id;
		}else{
			$totaltoPay = Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false);
			$idOrder = $params['objOrder']->id;
		}
		
		if (! $this->active) {
			return;
		}
		
		$this->smarty->assign(array(
				'total_to_pay' => $totaltoPay,
				'status' => 'ok',
				'id_order' => $idOrder,
				'this_path' => $this->_path
		));
		
		return $this->display ( __FILE__, 'payment_return.tpl' );
	}

	public function logInitialStatus($idLog, $numpedido, $params, $firma, $allowTarjeta, $allowReference, $allowBizum, $allowTarjetaInsite) {

		if(isset($_COOKIE['nPedSession']))
			return;
		
		setcookie("nPedSession", $numpedido, time()+120);	

		escribirLog("DEBUG", $idLog, "**************************");
		escribirLog("INFO ", $idLog, "****** NUEVO PEDIDO ******");
		escribirLog("INFO ", $idLog, "****** ". $numpedido ." ******");
		escribirLog("DEBUG", $idLog, "**************************");

		escribirLog("DEBUG", $idLog, "Parámetros de la solicitud: " . $params);
		escribirLog("DEBUG", $idLog, "Firma calculada y enviada : " . $firma);
		escribirLog("DEBUG", $idLog, "Configuración de los métodos de pago del TPV [TARJETA|REFERENCIA|BIZUM|INSITE]: [" . ($allowTarjeta ? 'ACTIVADO' : 'DESACTIVADO') . "|" . ($allowReference ? 'ACTIVADO' : 'DESACTIVADO') . "|" . ($allowBizum ? 'ACTIVADO' : 'DESACTIVADO') . "|" . ($allowTarjetaInsite ? 'ACTIVADO' : 'DESACTIVADO') . "]");
		escribirLog("DEBUG", $idLog, "Versión del módulo: PR-PURv" . $this->version);
		escribirLog("DEBUG", $idLog, "Versión de Prestashop: " . _PS_VERSION_);
		escribirLog("DEBUG", $idLog, "Versión de PHP: " . phpversion());

	}

	public function createMerchantData($moduleComent, $idCart) {

		$data = (object) [
			'moduleComent' => $moduleComent,
			'idCart' => $idCart
		];
		
		return json_encode($data);

	}

	function generaNumeroPedido($idCart, $tipo, $pedidoExtendido = false) {
		
		switch (intval($tipo)) {
			case 0 : // Hibrido
				$out = str_pad ( $idCart . "z" . time()%1000, 12, "0", STR_PAD_LEFT );
				$outExtended = str_pad ( $idCart . "z" . time()%1000, 4, "0", STR_PAD_LEFT );
	
				break;
			case 1 : // idCart de la Tienda
				$out = str_pad ( intval($idCart), 12, "0", STR_PAD_LEFT );
				$outExtended = str_pad ( intval($idCart), 4, "0", STR_PAD_LEFT );
	
				break;
			case 2: // Aleatorio
				$out = mt_rand (100000000000, 999999999999);
				$outExtended = mt_rand (1000, PHP_INT_MAX);
	
				break;
		}
	
		(strlen($out) <= 12) ? $out : (substr($out, -12));
		return ($pedidoExtendido) ? $outExtended : $out;
	}
	
	public function checkCurrency($cart) {
		$currency_order = new Currency ( $cart->id_currency );
		$currencies_module = $this->getCurrency ( $cart->id_currency );
		
		if (is_array ( $currencies_module )) {
			foreach ( $currencies_module as $currency_module ) {
				if ($currency_order->id == $currency_module ['id_currency']) {
					return true;
				}
			}
		}
		return false;
	}
	
	public function getRedsysCookie($order){
		$key="redsys".str_pad($order,12,"0",STR_PAD_LEFT);
		
		if(_PS_VERSION_ < 1.7){
			if(isset($_COOKIE[$key]))
				return $_COOKIE[$key];
		}
		else{
			if(Context::getContext()->cookie->__isset($key))
				return Context::getContext()->cookie->__get($key);
		}
		
		return null;
	}
	
	public function setRedsysCookie($order){
		$key="redsys".str_pad($order,12,"0",STR_PAD_LEFT);
		
		if(_PS_VERSION_ < 1.7){
			setcookie ( "redsys" . $_POST ["idCart"], "N", time () + (3600 * 24), __PS_BASE_URI__ );
		}
		else{
			Context::getContext()->cookie->__set($key,"N");
		}
	}

	public function validateOk($cart, $customer, $miObj){
		$merchantIdentifier = $miObj->getParameter('Ds_Merchant_Identifier');
		if (Configuration::get ( 'REDSYS_REFERENCIA' ) == 1 && ! $cart->isGuestCartByCartId ( $cart->id ) && $merchantIdentifier != null) {
			$cardNumber=$miObj->getParameter('Ds_Card_Number');
			$brand=$miObj->getParameter('Ds_Card_Brand');
			$cardType=$miObj->getParameter('Ds_Card_Type');
			$this->saveReference ( $customer->id, $merchantIdentifier, $cardNumber, $brand, $cardType);
		}

		Redsys_Refund::SaveOrderId($cart->id, $miObj->getParameter('Ds_Merchant_Order'), 'insite');
	}

	public function addPaymentInfo($pedidoSecuencial = NULL, $pedido = NULL, $metodo = NULL, $idLog = NULL, $update = false, $card_number = "", $card_brand = "", $card_expiration = "", $card_holder = ""){
		
		if(is_null($pedidoSecuencial) || is_null($pedido) || is_null($metodo) || is_null($idLog)) {

			escribirLog("ERROR", $idLog, "No se ha podido añadir la información del pago porque alguno de los parámetros es nulo.");
			return;
		} 
		
		$id_order = Order::getOrderByCartId($pedidoSecuencial);
		$order = new Order($id_order);
		$result = false;

		if ($update) {

			$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->execute(
				'UPDATE '._DB_PREFIX_.'order_payment SET payment_method = "'.pSQL($metodo).'" WHERE order_reference = "'.$order->reference.'"'
			);
		
		} else {

			$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->execute(
				'INSERT INTO '._DB_PREFIX_.'order_payment (order_reference, id_currency, amount, payment_method, conversion_rate, transaction_id, card_number, card_brand, card_expiration, card_holder, date_add) VALUES("'.$order->reference.'","'.$order->id_currency.'","'.$order->total_paid.'","'.pSQL($metodo).'", "'.$order->conversion_rate.'", "'.pSQL($pedido).'", "'.pSQL($card_number).'", "'.pSQL($card_brand).'", "'.pSQL($card_expiration).'", "'.pSQL($card_holder).'", "'.date("Y-m-d H:i:s").'")'
			);
		}

		if ($result)
			escribirLog("DEBUG", $idLog, "La información del pago se ha guardado o actualizado correctamente en la base de datos.");
		else
			escribirLog("ERROR", $idLog, "La información del pago no se pudo guardar correctamente en la base de datos.");

		return;
	}

	public function saveReference($idCustomer, $reference, $cardNumber, $brand, $cardType){
		$supportedBrands=array(1,2,8,9,22);
		if(!in_array($brand, $supportedBrands))
			$brand=null;			
		
		$this->createRefTable();
		if($reference!=null && strlen($reference)>0 && $this->checkRefTable()){
			$oldRef=$this->getCustomerRef($idCustomer);
			$maskedCard=$this->maskCardNumber($cardNumber);
			if($oldRef==null){
				Db::getInstance(_PS_USE_SQL_SLAVE_)->execute("INSERT INTO $this->_dbRefTable VALUES(".$idCustomer.", '".$this->version."','".$reference."','".$maskedCard."',".$brand.", '".$cardType."')");
			}
			else{
				Db::getInstance(_PS_USE_SQL_SLAVE_)->execute("UPDATE $this->_dbRefTable SET reference='".$reference."', version='".$this->version."', cardNumber='".$maskedCard."', brand=".$brand.", cardType='".$cardType."' where id_customer=".$idCustomer);
			}
		}
		else{
			
		}
	}
	
	public function getCustomerRef($idCustomer){
		if($this->checkRefTable()){
			$reference=Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS("SELECT * FROM ".$this->_dbRefTable." WHERE id_customer=".$idCustomer.";");
			foreach($reference as $ref)
				return array($ref["reference"],$ref["cardNumber"],$ref["brand"],$ref["cardType"]);
		}
		return null;
	}
	public function checkRefTable(){
		$tablas=Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS("SHOW TABLES LIKE '".$this->_dbRefTable."'");
		if(sizeof($tablas)<=0)
			$this->createRefTable();

		$tablas=Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS("SHOW TABLES LIKE '".$this->_dbRefTable."'");
		return sizeof($tablas)>0;
	}
	public function createRefTable(){
		Db::getInstance(_PS_USE_SQL_SLAVE_)->execute('CREATE TABLE IF NOT EXISTS `'.$this->_dbRefTable.'` (
				`id_customer` INT NOT NULL PRIMARY KEY, 
				`version` VARCHAR(10) NOT NULL, 
				`reference` VARCHAR(128) NOT NULL, 
				`cardNumber` VARCHAR(24), 
				`brand` SMALLINT, 
				`cardType` VARCHAR(1), 
				INDEX (`id_customer`) 
			) ENGINE = '._MYSQL_ENGINE_.' CHARACTER SET utf8 COLLATE utf8_general_ci'
		);
	}
	public function dropRefTable(){
		Db::getInstance(_PS_USE_SQL_SLAVE_)->execute('DROP TABLE `'.$this->_dbRefTable.'`');
	}
	public static function maskCardNumber($cardNumber){
		if(strlen($cardNumber)<=4)
			return $cardNumber;
	
			return str_pad(substr($cardNumber, -4, 4), strlen($cardNumber), "*", STR_PAD_LEFT);
	}

	public function addMessage($id_customer, $id_order, $message){

		if (!Configuration::get('REDSYS_MENSAJES_BACKOFFICE'))
			return;
			
        if (null === $this->customerThread) {
            $customer_thread = new CustomerThread();
            $customer_thread->id_contact = 0;
            $customer_thread->id_customer = (int)$id_customer;
            $customer_thread->id_shop = (int)$this->context->shop->id;
            $customer_thread->id_order = (int)$id_order;
            $customer_thread->id_lang = (int)$this->context->language->id;
            $customer_thread->email = $this->context->customer->email;
            $customer_thread->status = 'open';
            $customer_thread->token = Tools::passwdGen(12);
            $customer_thread->add();

            $this->customerThread = $customer_thread;
        }

        $customer_message = new CustomerMessage();
        $customer_message->id_customer_thread = $this->customerThread->id;
        $customer_message->id_employee = 1;
        $customer_message->message = $message;
        $customer_message->private = 1;

        if (!$customer_message->add()) {
            $this->errors[] = $this->trans('An error occurred while saving the message.', [], 'Admin.Notifications.Error');
        }
    }

	private function createParameters($idCart) {
		$params = array ();
		$cart = new Cart ( $idCart );
		
		$currency = new Currency ( $cart->id_currency );
		$currency_decimals = is_array ( $currency ) ? ( int ) $currency ['decimals'] : ( int ) $currency->decimals;
		$cart_details = $cart->getSummaryDetails ( null, true );
		$decimals = $currency_decimals * _PS_PRICE_DISPLAY_PRECISION_;
		
		$shipping = $cart_details ['total_shipping_tax_exc'];
		$subtotal = $cart_details ['total_price_without_tax'] - $cart_details ['total_shipping_tax_exc'];
		$tax = $cart_details ['total_tax'];
		
		$total_price = Tools::ps_round ( $shipping + $subtotal + $tax, $decimals );
		
		$params ["idCart"] = $idCart;
		$params ["amount"] = $total_price;
		$params ["currency"] = $currency->iso_code;
		$params ["allow_ref"] = Configuration::get( 'REDSYS_REFERENCIA' )==1 && !$cart->isGuestCartByCartId($idCart);
		$params ["redsys_domain"] = RESTConstants::getJSPath(Configuration::get ( 'REDSYS_URLTPV_INSITE' ));
		
		return $params;
	}

	public static function createEndpointParams($endpoint, $object, $idCart, $protocolVersion = null, $idLog = null) {

		$endpoint .= "?order=".$object->getOrder();
		$endpoint .= "&currency=".$object -> getCurrency();
		$endpoint .= "&amount=".$object -> getAmount();
		$endpoint .= "&merchant=".$object -> getMerchant();
		$endpoint .= "&terminal=".$object -> getTerminal();
		$endpoint .= "&transactionType=".$object -> getTransactionType();
		$endpoint .= "&idCart=".$idCart;

		if (!empty($protocolVersion))
			$endpoint .= "&protocolVersion=".$protocolVersion;
		
		if (!empty($idLog))
			$endpoint .= "&idLog=".$idLog;

		return $endpoint;
	}

	public function validateCart($cart, $nPed, $idCart, $customer, $amount, $idCurrency, $merchantIdentifier, $cardNumber, $brand, $cardType, $authCode, $metodoOrder, $idLog){
		if (Configuration::get ( 'REDSYS_REFERENCIA' ) == '1' && ! $cart->isGuestCartByCartId ( $cart->id ) && $merchantIdentifier != null) {
			escribirLog("DEBUG", $idLog, "Se guarda el token para el cliente " . $customer->id);
			$this->saveReference ( $customer->id, $merchantIdentifier, $cardNumber, $brand, $cardType);
		}

		Redsys_Refund::SaveOrderId($cart->id, $idCart, 'insite');
		
		$this->validateOrder ( $cart->id, Configuration::get ( "REDSYS_ESTADO_PEDIDO" ), $amount, "Redsys - Tarjeta", null, array('transaction_id' => $nPed), ( int ) $idCurrency, false, $customer->secure_key );
		$this->addPaymentInfo( $cart->id, $nPed, $metodoOrder, $idLog, true);

		$protocol="http";
		if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {
			$protocol=$protocol."s";
		}
		return $protocol."://".$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->id . '&id_order=' . $this->currentOrder . '&key=' . $customer->secure_key;
	}

	/** ANÁLISIS DE RESPUESTA DEL SIS */

	function checkRespuestaSIS($codigo_respuesta, $authCode) {

		$erroresSIS = array();
		$errorBackofficeSIS = "";

		include 'controllers/front/erroresSIS.php';

		if (array_key_exists($codigo_respuesta, $erroresSIS)) {
			
			$errorBackofficeSIS  = $codigo_respuesta;
			$errorBackofficeSIS .= ' - '.$erroresSIS[$codigo_respuesta].'.';
		
		} else {

			$errorBackofficeSIS = "La operación ha finalizado con errores. Consulte el módulo de administración del TPV Virtual.";
		}

		$metodoOrder = "N/A";

		if (($codigo_respuesta < 101) && (strpos($codigo_respuesta, "SIS") === false))
			$metodoOrder = "Autorizada " . $authCode;    
		else {
			if (strpos($codigo_respuesta, "SIS") !== false)
				$metodoOrder = "Error " . $codigo_respuesta;
			else 
				$metodoOrder = "Denegada " . $codigo_respuesta;
		}
		return array($errorBackofficeSIS, $metodoOrder);
	}
	
	function tep_sanitize_string_rds($string) {
	    $patterns = array ('/ +/','/[<>]/');
	    $replace = array (' ', '_');
	    return preg_replace($patterns, $replace, trim($string));
	}
	
	public function generate3DS2() {
		$customer				= $this->context->customer;
		$cart					= $this->context->cart;
		$shippingInfo			= $cart->id_address_delivery ? new Address($cart->id_address_delivery) : null;
		$billingInfo			= new Address($cart->id_address_invoice);
		$isLoggedIn				= !boolval($customer->is_guest);
		
		///// 3DSecure | TABLA 4 - Json Object acctInfo
		// chAccAgeInd & chAccDate
		if (!$isLoggedIn) {
			$chAccAgeInd			= "01";
		}
		else {
			$accountCreated			= intval( (strtotime("now") - strtotime($customer->date_add))/60 );
			$nDays					= intval($accountCreated/1440);
		
			$dt						= new DateTime($customer->date_upd);
			$chAccDate				= $dt->format('Ymd');
		
			if ($accountCreated < 20) {
				$chAccAgeInd 		= "02";
			}
			elseif ($nDays < 30) {
				$chAccAgeInd 		= "03";
			}
			elseif ($nDays >= 30 && $nDays <= 60) {
				$chAccAgeInd 		= "04";
			}
			else {
				$chAccAgeInd 		= "05";
			}
		}
		
		// chAccChange & chAccChangeInd
		if ($isLoggedIn) {
			$dt						= new DateTime($customer->date_upd);
			$chAccChange			= $dt->format('Ymd');
			$accountModified		= intval( (strtotime("now") - strtotime($customer->date_upd))/60 );
			$nDays					= intval($accountModified/1440);
			if($accountModified < 20) {
				$chAccChangeInd		= "01";
			}
			elseif ($nDays < 30) {
				$chAccChangeInd		= "02";
			}
			elseif ($nDays >= 30 && $nDays <= 60) {
				$chAccChangeInd		= "03";
			}
			else {
				$chAccChangeInd		= "04";
			}
		}
		
		//// nbPurchaseAccount
		if ($isLoggedIn) {
			$customerId				= $customer->id;
			$fechaBase				= strtotime("-6 month");
			$dt						= new DateTime("@$fechaBase");
			$query					= Db::getInstance()->executeS('SELECT COUNT(*) x FROM `'._DB_PREFIX_.'orders` o LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON o.id_order = od.id_order WHERE o.valid = 1 AND o.`id_customer` = '.intval($customerId).' AND o.`date_add` > "'.$dt->format('Y-m-d H:i:s').'";');
			$nbPurchaseAccount		= $query[0]['x'];
		}
		
		//// txnActivityDay
		if ($isLoggedIn) {
			$customerId				= $customer->id;
			$fechaBase				= strtotime("-1 day");
			$dt 					= new DateTime("@$fechaBase");
			$query					= Db::getInstance()->executeS('SELECT COUNT(*) x FROM `'._DB_PREFIX_.'orders` o LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON o.id_order = od.id_order WHERE o.valid = 1 AND o.`id_customer` = '.intval($customerId).' AND o.`date_add` > "'.$dt->format('Y-m-d H:i:s').'";');
			$txnActivityDay			= $query[0]['x'];
		}
		
		//// txnActivityYear
		if ($isLoggedIn) {
			$customerId				= $customer->id;
			$fechaBase				= strtotime("-1 year");
			$dt 					= new DateTime("@$fechaBase");
			$query					= Db::getInstance()->executeS('SELECT COUNT(*) x FROM `'._DB_PREFIX_.'orders` o LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON o.id_order = od.id_order WHERE o.valid = 1 AND o.`id_customer` = '.intval($customerId).' AND o.`date_add` > "'.$dt->format('Y-m-d H:i:s').'";');
			$txnActivityYear		= $query[0]['x'];
		}
		//// shipAddressUsage & shipAddressUsageInd
		if ($shippingInfo) {
			$shippingAddress1		= $this->tep_sanitize_string_rds($shippingInfo->address1);
			$shippingAddress2		= $this->tep_sanitize_string_rds($shippingInfo->address2);
			$shippingPostcode		= $this->tep_sanitize_string_rds($shippingInfo->postcode);
			$shippingCity			= $this->tep_sanitize_string_rds($shippingInfo->city);
			$shippingCountry		= $this->tep_sanitize_string_rds($shippingInfo->id_country);
			$query					= Db::getInstance()->executeS("SELECT o.date_add FROM "._DB_PREFIX_."orders o, "._DB_PREFIX_."address a WHERE a.id_address = o.id_address_delivery AND o.valid = '1' AND a.address1 = '". $shippingAddress1 ."' AND a.address2 = '". $shippingAddress2 . "' AND a.postcode = '" . $shippingPostcode . "' AND a.city = '" . $shippingCity . "' AND a.id_country = '" . $shippingCountry . "' ORDER BY o.date_add;" );
			if (count($query) != 0) {
				$queryResult		= $query[0]['date_add'];
				$dt					= new DateTime($queryResult);
				$shipAddressUsage	= $dt->format('Ymd');
				
				$duringTransaction	= intval( (strtotime("now") - strtotime($queryResult))/60 );
				$nDays 				= intval($duringTransaction/1440);
				if ($nDays < 30) {
					$shipAddressUsageInd = "02";
				}
				elseif ($nDays >= 30 && $nDays <= 60) {
					$shipAddressUsageInd = "03";
				}
				else {
					$shipAddressUsageInd = "04";
				}
			}
			else {
				$fechaBase				= strtotime("now");
				$dt						= new DateTime("@$fechaBase");
				$shipAddressUsage		= $dt->format('Ymd');
				$shipAddressUsageInd	= "01";
			}
		}
		
		///// 3DSecure | FIN TABLA 4
	
		///// 3DSecure | TABLA 1 - Ds_Merchant_EMV3DS (json Object)
		//// addrMatch
		if ($shippingInfo) {
			if (
				($shippingInfo->address1 == $billingInfo->address1)
				&&
				($shippingInfo->address2 == $billingInfo->address2)
				&&
				($shippingInfo->city == $billingInfo->city)
				&&
				($shippingInfo->postcode == $billingInfo->postcode)
				&&
				($shippingInfo->country == $billingInfo->country)
			) {
				$addrMatch			= "Y";
			}
			else {
				$addrMatch			= "N";
			}
		}
		else {
			$addrMatch				= "N";
		}
		
		//// billAddrCity
		$billAddrCity				= $billingInfo->city;
		
		//// billAddrLine1
		$billAddrLine1 				= $billingInfo->address1;
		
		//// billAddrLine2			
		$billAddrLine2				= $billingInfo->address22;
		
		//// billAddrPostCode
		$billAddrPostCode			= $billingInfo->postcode;
		
		//// Email
		$Email						= $customer->email;

		//// homePhone
		$homePhone					= array("subscriber"=>$billingInfo->phone);
		
		//// mobilePhone
		$mobilePhone				= $billingInfo->mobile_phone ? array("subscriber"=>$billingInfo->mobile_phone) : null;
		
		if ($shippingInfo) {
			//// shipAddrCity
			$shipAddrCity 			= $shippingInfo->city;
			
			//// shipAddrLine1
			$shipAddrLine1 			= $shippingInfo->address1;
			
			//// shipAddrLine2		
			$shipAddrLine2			= $shippingInfo->address2;
			
			//// shipAddrPostCode
			$shipAddrPostCode		= $shippingInfo->postcode;
		}
		
		//// acctInfo				| Información de la TABLA 4
		$acctInfo					= array(
			'chAccAgeInd'			=> $chAccAgeInd
		);
		if ($shippingInfo) {
			$acctInfo['shipAddressUsage']		= $shipAddressUsage;
			$acctInfo['shipAddressUsageInd']	= $shipAddressUsageInd;
		}
		if ($isLoggedIn) {
			$acctInfo['chAccDate']			= $chAccDate;
			$acctInfo['chAccChange']		= $chAccChange;
			$acctInfo['chAccChangeInd']		= $chAccChangeInd;
			$acctInfo['nbPurchaseAccount']	= $nbPurchaseAccount;
			$acctInfo['txnActivityDay']		= $txnActivityDay;
			$acctInfo['txnActivityYear']	= $txnActivityYear;
		}
		
		///// 3DSecure | FIN TABLA 1
		$Ds_Merchant_EMV3DS 		= array(
			'addrMatch'				=> $addrMatch,
			'billAddrCity'			=> $billAddrCity,
			'billAddrLine1'			=> $billAddrLine1,
			'billAddrPostCode'		=> $billAddrPostCode,
			'Email'					=> $Email,
			'homePhone'				=> $homePhone,
			'acctInfo'				=> $acctInfo
		);
		if ($billAddrLine2) {
			$Ds_Merchant_EMV3DS['billAddrLine2']	= $billAddrLine2;
		}
		if ($mobilePhone) {
			$Ds_Merchant_EMV3DS['mobilePhone']		= $mobilePhone;	
		}
		if ($shippingInfo) {
			$Ds_Merchant_EMV3DS['shipAddrCity']		= $shipAddrCity;
			$Ds_Merchant_EMV3DS['shipAddrLine1']	= $shipAddrLine1;
			$Ds_Merchant_EMV3DS['shipAddrPostCode']	= $shipAddrPostCode;
			if ($shipAddrLine2) {
				$Ds_Merchant_EMV3DS['shipAddrLine2']	= $shipAddrLine2;
			}
		}
		
		$Ds_Merchant_EMV3DS 		= json_encode($Ds_Merchant_EMV3DS);
		
		return $Ds_Merchant_EMV3DS;
	}

	public function hookActionProductCancel($params)
	{
		$order = $params['order'];

		$redsys_order = Redsys_Refund::getOrderId($order->id_cart);
		if($redsys_order){
			$orderId = $redsys_order['redsys_order'];
			$gateway_params = array();
			switch($redsys_order['method']){
				case 'insite':
					$gateway_params = array(
						'fuc' => Configuration::get( 'REDSYS_FUC_TARJETA_INSITE' ),
						'terminal' => Configuration::get ( 'REDSYS_TERMINAL_TARJETA_INSITE' ),
						'clave256' => Configuration::get ( 'REDSYS_CLAVE256_TARJETA_INSITE' ),
						'entorno' => Configuration::get ( 'REDSYS_URLTPV_INSITE' )
					);
					break;
				case 'redireccion':
					$gateway_params = array(
						'fuc' => Configuration::get( 'REDSYS_FUC_TARJETA' ),
						'terminal' => Configuration::get ( 'REDSYS_TERMINAL_TARJETA' ),
						'clave256' => Configuration::get ( 'REDSYS_CLAVE256_TARJETA' ),
						'entorno' => Configuration::get ( 'REDSYS_URLTPV_REDIR' )
					);
					break;
				case 'bizum':
					$gateway_params = array(
						'fuc' => Configuration::get( 'REDSYS_FUC_BIZUM' ),
						'terminal' => Configuration::get ( 'REDSYS_TERMINAL_BIZUM' ),
						'clave256' => Configuration::get ( 'REDSYS_CLAVE256_BIZUM' ),
						'entorno' => Configuration::get ( 'REDSYS_URLTPV_BIZUM' )
					);
					break;
				default:
					return;
			}

			$amount = 0;

			if ($params['action'] === 1) { //CancellationActionType::STANDARD_REFUND
				$amount = $order->total_paid_real;
			} else if ($params['action'] === 2) { //CancellationActionType::RETURN_PRODUCT
				if(array_key_exists('cancel_amount', $params)){
					$amount = $params['cancel_amount'];
				}else{
					throw new Exception('Solo es posible realizar una devolución parcial en las versiones > 1.7.8');
				}
			}

			// Valor de compra
			$currency = new Currency($params['cart']->id_currency);
			$currency_decimals = is_array($currency) ? (int) $currency['decimals'] : (int) $currency->decimals;
			$decimals = $currency_decimals * _PS_PRICE_DISPLAY_PRECISION_;

			$amount = number_format($amount, 2, '', '');
			$amount = (int)$amount;

			$gateway_params['moneda'] = $currency->iso_code_num;

			$logLevel  = Configuration::get( 'REDSYS_LOG' );
			$logString = Configuration::get( 'REDSYS_LOG_STRING' );
			$idLog = generateIdLog($logLevel, $logString, $orderId);
			$idLog = iniciarLog( Configuration::get( 'REDSYS_LOG' ), $idLog );
	
			if(!Redsys_Refund::refund($gateway_params, $orderId, $amount, '', $idLog)){
				throw new Exception('Error al realizar una devolución');
			}
		}
	}
}
