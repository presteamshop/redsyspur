<?php

function tep_sanitize_string_rds($string) {
    $patterns = array ('/ +/','/[<>]/');
    $replace = array (' ', '_');
    return preg_replace($patterns, $replace, trim($string));
}

$customer					= $this->context->customer;
$cart						= $this->context->cart;
$shippingInfo				= $cart->id_address_delivery ? new Address($cart->id_address_delivery) : null;
$billingInfo				= new Address($cart->id_address_invoice);
$isLoggedIn					= !boolval($customer->is_guest);

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

//// chAccPwChange			| No se puede sacar este dato
// $chAccPwChange			= "";

//// chAccPwChangeInd		| No se puede sacar este dato
// $chAccPwChangeInd		= "";

//// nbPurchaseAccount
if ($isLoggedIn) {
	$customerId				= pSQL($customer->id);
	$fechaBase				= strtotime("-6 month");
	$dt						= new DateTime("@$fechaBase");
	$query					= Db::getInstance()->executeS('SELECT COUNT(*) x FROM `'._DB_PREFIX_.'orders` o LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON o.id_order = od.id_order WHERE o.valid = 1 AND o.`id_customer` = '.intval($customerId).' AND o.`date_add` > "'.$dt->format('Y-m-d H:i:s').'";');
	$nbPurchaseAccount		= $query[0]['x'];
}

//// provisionAttemptsDay	| No se puede sacar este dato
// $provisionAttemptsDay	= "";

//// txnActivityDay
if ($isLoggedIn) {
	$customerId				= pSQL($customer->id);
	$fechaBase				= strtotime("-1 day");
	$dt 					= new DateTime("@$fechaBase");
	$query					= Db::getInstance()->executeS('SELECT COUNT(*) x FROM `'._DB_PREFIX_.'orders` o LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON o.id_order = od.id_order WHERE o.valid = 1 AND o.`id_customer` = '.intval($customerId).' AND o.`date_add` > "'.$dt->format('Y-m-d H:i:s').'";');
	$txnActivityDay			= $query[0]['x'];
}

//// txnActivityYear
if ($isLoggedIn) {
	$customerId				= pSQL($customer->id);
	$fechaBase				= strtotime("-1 year");
	$dt 					= new DateTime("@$fechaBase");
	$query					= Db::getInstance()->executeS('SELECT COUNT(*) x FROM `'._DB_PREFIX_.'orders` o LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON o.id_order = od.id_order WHERE o.valid = 1 AND o.`id_customer` = '.intval($customerId).' AND o.`date_add` > "'.$dt->format('Y-m-d H:i:s').'";');
	$txnActivityYear		= $query[0]['x'];
}

//// paymentAccAge			| No se puede sacar este dato
// $paymentAccAge			= "";

//// paymentAccInd			| No se puede sacar este dato
// $paymentAccInd			= "";

//// shipAddressUsage & shipAddressUsageInd
if ($shippingInfo) {
	// $shippingAddress1		= tep_sanitize_string_rds($shippingInfo->address1);
	// $shippingAddress2		= tep_sanitize_string_rds($shippingInfo->address2);
	// $shippingPostcode		= tep_sanitize_string_rds($shippingInfo->postcode);
	// $shippingCity			= tep_sanitize_string_rds($shippingInfo->city);
	// $shippingCountry		= tep_sanitize_string_rds($shippingInfo->id_country);

	$shippingAddress1		= pSQL($shippingInfo->address1);
	$shippingAddress2		= pSQL($shippingInfo->address2);
	$shippingPostcode		= pSQL($shippingInfo->postcode);
	$shippingCity			= pSQL($shippingInfo->city);
	$shippingCountry		= pSQL($shippingInfo->id_country);

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

//// shipNameIndicator		| No se puede sacar este dato
// $shipNameIndicator		= "";

//// suspiciousAccActivity	| No se puede sacar este dato
// $suspiciousAccActivity	= "";

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

//// billAddrCountry		| No se puede sacar este dato
//$billAddrCountry 			= "";

//// billAddrLine1
$billAddrLine1 				= $billingInfo->address1;

//// billAddrLine2			
$billAddrLine2				= $billingInfo->address22;

//// billAddrLine3			| No se puede sacar este dato
// $billAddrLine3			= "";

//// billAddrPostCode
$billAddrPostCode			= $billingInfo->postcode;

//// billAddrState			| Se puede sacar, pero como no se puede sacar el ISO del país, no lo ponemos
//$billAddrState			= "";

//// Email
$Email						= $customer->email;

//// homePhone
$homePhone					= $billingInfo->phone ? array("subscriber"=>$billingInfo->phone, "cc" => "34") : null;

//// mobilePhone
$mobilePhone				= $billingInfo->mobile_phone ? array("subscriber"=>$billingInfo->mobile_phone, "cc" => "34") : null;

//// cardholderName 		| No se puede sacar este dato
// $cardholderName			= "";

if ($shippingInfo) {
	//// shipAddrCity
	$shipAddrCity 			= $shippingInfo->city;
	
	//// shipAddrCountry	| No se puede sacar este dato
	//$shipAddrCountry 		= "";
	
	//// shipAddrLine1
	$shipAddrLine1 			= $shippingInfo->address1;
	
	//// shipAddrLine2		
	$shipAddrLine2			= $shippingInfo->address2;
	
	//// shipAddrLine3		| No se puede sacar este dato
	// $shipAddrLine3		= "";
	
	//// shipAddrPostCode
	$shipAddrPostCode		= $shippingInfo->postcode;
	
	//// shipAddrState		| Se puede sacar, pero como no se puede sacar el ISO del país, no lo ponemos
	//$shipAddrState		= "";
}

//// workPhone
// $workPhone				= "";

//// threeDSRequestorAuthenticationInfo | No lo ponemos

//// acctInfo					| Información de la TABLA 4
$acctInfo						= array(
	'chAccAgeInd'				=> $chAccAgeInd
);
if ($shippingInfo) {
	$acctInfo['shipAddressUsage']		= strval($shipAddressUsage);
	$acctInfo['shipAddressUsageInd']	= strval($shipAddressUsageInd);
}
if ($isLoggedIn) {
	$acctInfo['chAccDate']			= strval($chAccDate);
	$acctInfo['chAccChange']		= strval($chAccChange);
	$acctInfo['chAccChangeInd']		= strval($chAccChangeInd);
	$acctInfo['nbPurchaseAccount']	= strval($nbPurchaseAccount);
	$acctInfo['txnActivityDay']		= strval($txnActivityDay);
	$acctInfo['txnActivityYear']	= strval($txnActivityYear);
}

//// purchaseInstalData		| No se puede sacar este dato
// $purchaseInstalData		= "";

//// recurringExpiry		| No se puede sacar este dato
// $recurringExpiry			= "";

//// recurringFrequency		| No se puede sacar este dato
// $recurringFrequency		= "";

//// merchantRiskIndicator	| No se puede sacar este dato
// $merchantRiskIndicator   = array();

//// challengeWindowSize	| No se puede sacar este dato
// $challengeWindowSize 	= "";


///// 3DSecure | FIN TABLA 1

///// 3DSecure | Insertamos el parámetro "Ds_Merchant_EMV3DS" en $miObj
$Ds_Merchant_EMV3DS 		= array(
	'addrMatch'				=> $addrMatch,
	'billAddrCity'			=> $billAddrCity,
	'billAddrLine1'			=> $billAddrLine1,
	'billAddrPostCode'		=> $billAddrPostCode,
	'email'					=> $Email,
	'acctInfo'				=> $acctInfo
);
if ($homePhone) {
	$Ds_Merchant_EMV3DS['homePhone']	= $homePhone;
}
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
	// $Ds_Merchant_EMV3DS['acctInfo']			= array(
	// 	'shipAddressUsage'					=> $shipAddressUsage,
	// 	'shipAddressUsageInd'				=> $shipAddressUsageInd
	// );
	if ($shipAddrLine2) {
		$Ds_Merchant_EMV3DS['shipAddrLine2']	= $shipAddrLine2;
	}
}

function quitarNulos($array) {

	foreach ($array as $key => $value) {
   
	if (is_array($value)) {
		$array[$key] = quitarNulos($array[$key]);
	}
   
	if (is_null($array[$key])) {
		unset($array[$key]);
	}
   
	}
   
	return $array;
}

$Ds_Merchant_EMV3DS 		= quitarNulos($Ds_Merchant_EMV3DS);
$Ds_Merchant_EMV3DS 		= json_encode($Ds_Merchant_EMV3DS);

$miObj->setParameter("Ds_Merchant_EMV3DS", $Ds_Merchant_EMV3DS);