<?php

require_once dirname ( __FILE__ ) . '/ApiRedsysREST/initRedsysApi.php';

class Redsys_Refund {

    const REFUND_TABLE = _DB_PREFIX_."redsys_order";

	public static function refund($gateway_params, $orderId, $amount, $reason = '', $idLog = null){
        $request = new RestOperationMessage();

        $request->setAmount( $amount );
        $request->setCurrency( $gateway_params['moneda'] );
        $request->setMerchant( $gateway_params['fuc'] );
        $request->setTerminal( $gateway_params['terminal'] );
        $request->setOrder( $orderId );
        $request->setTransactionType( RESTConstants::$REFUND );
        $request->addParameter( "DS_MERCHANT_PRODUCTDESCRIPTION", $reason );

        escribirLog("DEBUG", $idLog, "Se inicia una devolucion con los siguientes parametros:");
        escribirLog("DEBUG", $idLog, "  Monto: " . $amount);
        escribirLog("DEBUG", $idLog, "  Moneda: " . $gateway_params['moneda']);
        escribirLog("DEBUG", $idLog, "  Comercio: " . $gateway_params['fuc']);
        escribirLog("DEBUG", $idLog, "  Terminal: " . $gateway_params['terminal']);
        escribirLog("DEBUG", $idLog, "  Orden: " . $orderId);

        $service = new RESTOperationService ( $gateway_params['clave256'], $gateway_params['entorno'] );
        $result = $service->sendOperation ( $request, $idLog );

        if($result->getResult () == RESTConstants::$RESP_LITERAL_OK){
            escribirLog("DEBUG", $idLog, "La devolucion se realizo correctamente");
        }else{
            escribirLog("DEBUG", $idLog, "La devolucion no se realizo correctamente");
        }

        return $result->getResult () == RESTConstants::$RESP_LITERAL_OK;
    }

    public static function saveOrderId($idOrder, $redsysOrder, $method){
        if($idOrder!=null && Redsys_Refund::checkOrderTable()){
            $oldRedsysOrder=Redsys_Refund::getOrderId($idOrder);
            
            if($oldRedsysOrder==null){
                Db::getInstance(_PS_USE_SQL_SLAVE_)->execute("INSERT INTO ".self::REFUND_TABLE." VALUES(".$idOrder.", '".substr($redsysOrder, 0, 20)."', '".substr($method, 0, 20)."')");
            }else{
                Db::getInstance(_PS_USE_SQL_SLAVE_)->execute("UPDATE ".self::REFUND_TABLE." SET redsys_order='".substr($redsysOrder, 0, 20)."', method='".substr($method, 0, 20)."' where id_order=".$idOrder);
            }
        }
    }

    public static function getOrderId($idOrder){
		if(Redsys_Refund::checkOrderTable()){
			$orders=Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS("SELECT * FROM ".self::REFUND_TABLE." WHERE id_order=".$idOrder.";");
			foreach($orders as $order)
				return $order;
		}
		return null;
    }

	public static function checkOrderTable(){
        $tablas=Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS("SHOW TABLES LIKE '".self::REFUND_TABLE."'");
        if(sizeof($tablas)<=0)
            Redsys_Refund::createOrderTable();

        $tablas=Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS("SHOW TABLES LIKE '".self::REFUND_TABLE."'");
        return sizeof($tablas)>0;
	}

	public static function createOrderTable(){
        Db::getInstance(_PS_USE_SQL_SLAVE_)->execute('CREATE TABLE IF NOT EXISTS `'.self::REFUND_TABLE.'` (
                `id_order` INT NOT NULL PRIMARY KEY, 
				`redsys_order` VARCHAR(20) NOT NULL,
                `method` VARCHAR(20) NOT NULL,
                INDEX (`id_order`) 
            ) ENGINE = '._MYSQL_ENGINE_.' CHARACTER SET utf8 COLLATE utf8_general_ci'
        );
    }

	public static function dropOrderTable(){
        Db::getInstance(_PS_USE_SQL_SLAVE_)->execute('DROP TABLE `'.self::REFUND_TABLE.'`');
	}
}