<?php
/***************************************************************************
 * 提前创建目录空间脚本
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/
 
 
 
/**
 * @file Space.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/09/05 21:02:12
 * @version $Revision$ 
 * @brief 
 *  
 **/

$tomorrow = date("Ymd", strtotime("+1 day") ) ;
$arrHour = array() ;
$arrMinute = array( "00", "10", "20", "30", "40", "50" ) ;
$i = 0 ;
for( $i; $i < 24; $i++ ) {
	$arrHour[] = sprintf( "%02d", $i ) ;
}

foreach( $arrHour as $hour ){
	foreach( $arrMinute as $minute ) {
		exec( "source ~/.bashrc && hadoop fs -mkdir /app/ns/iknow/hive/etl/iknow_submit/event_day=$tomorrow/event_hour=$hour/event_minute=$minute/") ;
		sleep( 10 ) ;
	} 
}




/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
