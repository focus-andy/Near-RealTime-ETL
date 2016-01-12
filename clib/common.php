<?php
/***************************************************************************
 * common function
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/
 
 
 
/**
 * @file common.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/26 22:26:26
 * @version $Revision$ 
 * @brief 
 *  
 **/

dl( "iknow_ip.so" ) ;

function common_copy( $arrParams ){
	$strOut = '' ;
	foreach( $arrParams['in'] as $strIn ){
		$strOut = $strIn ;
	}
	foreach( $arrParams['out'] as $key => $value ){
		$arrParams['out'][$key] = $strOut ;
	}
	
	return true ;
}

function common_copy_decode( $arrParams ){
	$strOut = '' ;
	foreach( $arrParams['in'] as $strIn ){
		$strOut = urldecode( $strIn );
	}
	foreach( $arrParams['out'] as $key => $value ){
		$arrParams['out'][$key] = $strOut ;
	}
	
	return true ;
}

function common_copy_decode_utf8( $arrParams ){
	$strOut = '' ;
	foreach( $arrParams['in'] as $strIn ){
		$strOut = urldecode( $strIn );
	}
	foreach( $arrParams['out'] as $key => $value ){
		$arrParams['out'][$key] = mb_convert_encoding( $strOut, 'UTF-8', 'GBK' ) ;
	}
	
	return true ;
}
function common_date_time( $arrParams ){
	$strOutEventYear = date( 'Y', time() ) ;
	$strOutEventMonth = '' ;
	$strOutEventDay = '' ;
	$strOutEventTime = '' ;
	$strOutEventTimeStamp = '' ;
	
	list( $strOutEventMonth, $strOutEventDay ) = explode( '-', $arrParams['in']['date'] ) ;
	$strOutEventTime = $arrParams['in']['time'] ;
	$strFullTime = sprintf( "%s-%s %s", $strOutEventYear, $arrParams['in']['date'], $strOutEventTime ) ;
	$strOutEventTimeStamp = strtotime( $strFullTime ) ;
	
	$arrParams['out']['event_year'] = $strOutEventYear ;
	$arrParams['out']['event_month'] = $strOutEventMonth ;
	$arrParams['out']['event_time'] = $strOutEventTime ;
	$arrParams['out']['event_timestamp'] = $strOutEventTimeStamp ;
	$arrParams['out']['event_dayofmonth'] = $strOutEventDay ;

	return true ; 
}

function common_ip( $arrParams ){
	$strOutEventIp = $arrParams['in']['client_ip'] ;
	$strOutEventNetProvider = '' ;
	$strOutEventCity = '' ;
	$strOutEventProvince = '' ;
	
	$strCity = mb_convert_encoding( ip2city($strOutEventIp), 'UTF-8', 'GBK' ) ;
	list( $strTmp, $strOutEventNetProvider ) = explode( ",", $strCity ) ;
	if( preg_match( "/^(.*省)(.*市).*$/", $strTmp, $arrOut ) ){
		$strOutEventCity = $arrOut[2] ;
		$strOutEventProvince = $arrOut[1] ;
	}
	
	$arrParams['out']['event_ip'] = $strOutEventIp ;
	$arrParams['out']['event_net_provider'] = $strOutEventNetProvider ;
	$arrParams['out']['event_city'] = $strOutEventCity ;
	$arrParams['out']['event_province'] = $strOutEventProvince ;

	return true ;	
}

function common_baiduid( $arrParams ){
	$strInBaiduid = $arrParams['in']['baiduid'] ;
	$strInBaiduid = trim( $strInBaiduid, '"' ) ;
	$strInBaiduid = trim( $strInBaiduid, "'" ) ;
	$strOutBaiduid = substr( $strInBaiduid, 0, 32 ) ;
	
	$arrParams['out']['event_baiduid'] = $strOutBaiduid ;

	return true ; 
}

function common_loginfo( $arrParams ){
	return array2map( $arrParams ) ;
}

function common_copy_iknow_array( $arrParams ){
	$strIn = '' ;
	$strOut = '' ;

	if( count($arrParams['in'] > 0 ) ){
		foreach( $arrParams['in'] as $tmp ){
			$strIn = trim($tmp) ;
		} 
		if( strlen( $strIn ) > 0 ){
			$arrTmp = explode( " ", $strIn ) ; 
			$arrRes = array() ;
			foreach( $arrTmp as $tmp ){
				$arrT = explode( "=", $tmp ) ;
				$str = trim($arrT[1], "'" ) ;
				$str = urldecode( $str ) ;
				$str = mb_convert_encoding( $str, "UTF-8", "GBK" ) ;
				$arrRes[$arrT[0]] = $str ;
			}
			$strOut = array2map( $arrRes ) ;
		}
	}

	foreach( $arrParams['out'] as $key => $value ){
		$arrParams['out'][$key] = $strOut ;
	}

	return true ;
}
function array2map( $arrInput ){
	$outputMap = "" ;
	if( count($arrInput) > 0 ){
		foreach( $arrInput as $k => $v ){
			if( $outputMap == "" )
				$outputMap = "$k:$v" ;
			else
				$outputMap .= ",$k:$v" ;
		}
	}

	return $outputMap ;
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
