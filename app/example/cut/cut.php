<?php
/***************************************************************************
 * 
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/
 
 
 
/**
 * @file cut.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/20 22:49:36
 * @version $Revision$ 
 * @brief 
 *  
 **/

function cut( $str ){
//	$str = "NOTICE: 08-19 19:00:02 submit * 21142 [logid=0002898763 filename=/home/iknow/php/phplib/saf/base/Log.php lineno=28 errno=0 optime=1376910002.895 client_ip=119.36.161.102 local_ip=10.26.222.59 product=ORP subsys=ORP module=submit uniqid=0 cgid=1491012416 uid=405954646 Entry= Score=experience%3D%271282%27%20wealth%3D%27609%27 Grade=title%3D%270%27%20index%3D%274%27%20iconType%3D%276%27 Sign=msg%3D%27success%27%20status%3D%270%27%20num%3D%273%27 Cmd=cm%3D%27100509%27%20errorNo%3D%270%27 Cost=all_ms%3D38%20kvs_ucloud_ms%3D2%20qcm_ms%3D2 un=%A1%BA%B1%B1%B3%C7%D2%D4%B1%B1%A1%BB mobilephone=15549943802 email=592760219%40qq.com baiduid=522CFFE3CF22CDBE5EBA5FFE7005A6FA%3AFG%3D1 url=%2Fsubmit%2Fuser refer=http%3A%2F%2Fzhidao.baidu.com%2Fquestion%2F581453024.html uip=119.36.161.102 ua=Mozilla%2F5.0%20%28Windows%20NT%205.1%29%20AppleWebKit%2F537.1%20%28KHTML%2C%20like%20Gecko%29%20Chrome%2F21.0.1180.89%20Safari%2F537.1 host=zhidao.baidu.com cost=40 errmsg=]" ;
	$arrRes = array() ;
	preg_match( "/^NOTICE: (\d+-\d+) (\d\d:\d\d:\d\d).*\[(.*)\]/", $str, $out ) ;
	$arrRes['date'] = $out[1] ;
	$arrRes['time'] = $out[2] ;
	$arrTmp = explode( " ", $out[3] ) ;
	foreach( $arrTmp as $value ){
		$arrT = explode( "=", $value ) ;
		$arrRes[$arrT[0]] = urldecode( $arrT[1] ) ;
	}

	return $arrRes ;
}




/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
