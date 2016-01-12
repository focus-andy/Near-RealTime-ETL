<?php
/***************************************************************************
 * hook function
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/
 
 
 
/**
 * @file hook.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/26 23:19:49
 * @version $Revision$ 
 * @brief 
 *  
 **/

function hook_url( $arrParams ){
	$strOutEventUrl = '' ;
	$strOutEventUrlHost = '' ;
	$strOutEventUrlPath = '' ;
	$strOutEventUrlParams = array() ;
	$strOutEventQuery = '' ;
	
	$strOutEventUrl = $arrParams['in']['host']. $arrParams['in']['url'] ;
	$strOutEventUrlHost = $arrParams['in']['host'] ;
	list( $strOutEventUrlPath, $strInEventUrlParams ) = explode( "?", $arrParams['in']['url'] ) ;
	$arrTmpParams = explode( "&", $strInEventUrlParams ) ;
	foreach( $arrTmpParams as $param ){
		$arrTmp = explode( "=", $param ) ;
		$strOutEventUrlParams[$arrTmp[0]] = $arrTmp[1]  ;
		if( $arrTmp[0] == 'word' )
			$strOutEventQuery = $arrTmp[1] ;
	}
	
	$arrParams['out']['event_url'] = $strOutEventUrl ;
	$arrParams['out']['event_url_hostname'] = $strOutEventUrlHost ;
	$arrParams['out']['event_urlpath'] = $strOutEventUrlPath ;
	$arrParams['out']['event_urlparamsmap'] = array2map( $strOutEventUrlParams ) ;
	$arrParams['out']['event_query'] = $strOutEventQuery ;

	return true ;
}

function hook_refer( $arrParams ){
	$strOutEventRefer = '' ;
	$strOutEventReferHost = '' ;
	$strOutEventReferPath = '' ;
	$strOutEventReferParams = array() ;
	
	$strOutEventRefer = $arrParams['in']['refer'] ;
	$strOutEventUrlHost = $arrParams['in']['host'] ;
	list( $strOutEventUrlPath, $strInEventReferParams ) = explode( "?", $arrParams['in']['refer'] ) ;
	$arrTmpParams = explode( "&", $strInEventReferParams ) ;
	foreach( $arrTmpParams as $param ){
		$arrTmp = explode( "=", $param ) ;
		$strOutEventReferParams[$arrTmp[0]] = $arrTmp[1]  ;
	}
	
	$arrParams['out']['event_referer'] = $strOutEventRefer ;
	$arrParams['out']['event_refererpath'] = $strOutEventReferPath ;
	$arrParams['out']['event_refererparamsmap'] = array2map( $strOutEventReferParams ) ;
	$arrParams['out']['event_referer_hostname'] = $strOutEventReferHost ;

	return true ;
}




/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
