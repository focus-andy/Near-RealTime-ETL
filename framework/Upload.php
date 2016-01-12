<?php
/***************************************************************************
 * 上传数据文件到HDFS,创建partition
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/



/**
 * @file Upload.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/30 14:09:43
 * @version $Revision$ 
 * @brief 
 *  
 **/

#require_once( "./Base.php") ;
require_once( "./Init.php" ) ;

//初始化
$strApp = $argv[1] ;
$strPipelet = $argv[2] ;
list( $tmp, $app ) = explode( "=", $strApp ) ;
list( $tmp, $pipelet ) = explode( "=", $strPipelet ) ; 
$init = new ETL_Init() ;
$init->init( $argc, $argv ) ;
$arrAppConf = $init->getAppConf() ;
$dataPath = ETL_DATA. $app ;
define( 'MODULE', $app ) ;

while( true ){
	//检查待上传文件，push到队列中
	exec( "cd $dataPath && ls {$app}_{$pipelet}_*", $dir, $ret ) ;
	$arrFileToUpload = array() ;
	foreach( $dir as $key => $file ){
		if( preg_match( "/^({$app}_{$pipelet}_\d{12})$/", $file, $arrMatch ) ){
			$arrFileToUpload[$arrMatch[1]] = '' ;
		} else if( preg_match( "/^({$app}_{$pipelet}_\d{12})\.done$/", $file, $arrMatch ) ){
			$arrFileToUpload[$arrMatch[1]] = "done" ;
		} 
	}

	//从队列中删除已上传文件
	foreach( $arrFileToUpload as $key => $value ){
		if( $value == 'done' )
			unset( $arrFileToUpload[$key] ) ;
	}

	array_pop( $arrFileToUpload ) ;
	if( count($arrFileToUpload) == 0 ){
		sleep( $arrAppConf['update_sleep_s'] ) ;
		continue ;
	}

	foreach( $arrFileToUpload as $key => $value ){
		preg_match( "/^{$app}_{$pipelet}_(\d{8})(\d{2})(\d{2})/", $key, $arrMatch ) ;
		$event_day = $arrMatch[1] ;
		$event_hour = $arrMatch[2] ;
		$event_minute = $arrMatch[3] ;

		//执行上传文件操作
		$hdfsPath = sprintf( "%sevent_day=%s/event_hour=%s/event_minute=%s/",$arrAppConf['hdfs_path'], $event_day, $event_hour, $event_minute ) ;
		$strCmd = "source ~/.bashrc && hadoop fs -put {$dataPath}/$key $hdfsPath/" ;
		Bd_Log :: trace( "[UPDATE] pipelet_id:$pipelet partition:$event_day$event_hour$event_minute upload_start:".date("h:m:s", time()) ) ;
		exec( $strCmd, $out, $ret ) ;
		Bd_Log :: trace( "[UPDATE] pipelet_id:$pipelet partition:$event_day$event_hour$event_minute upload_end:".date("h:m:s", time())." $ret" ) ;

		//上传成功
		if( $ret == 0 ){
			//对上传成功文件打上done标记
			$strCmdMd5 = "cd $dataPath && touch $key.done" ;
			exec( $strCmdMd5 ) ;

			//更新该pipelet状态
			$db = new mysqli( $arrAppConf['mysql_host'], $arrAppConf['mysql_user'], $arrAppConf['mysql_pwd'], $arrAppConf['mysql_db'], $arrAppConf['mysql_port'] ) ;
			$sql = sprintf( "INSERT INTO %s_%s VALUES(%s%s%s, %s) ON DUPLICATE KEY UPDATE pipelet_id=pipelet_id | %d", 
				$arrAppConf['mysql_table'], $app, $event_day, $event_hour, $event_minute, 1<<$pipelet, 1<<$pipelet ) ;
			$db->query( $sql ) ;

			//检查所有pipelet上传完成状态
			$sql = sprintf( "SELECT pipelet_id FROM %s_%s WHERE partition=%s%s%s FOR UPDATE", 
				$arrAppConf['mysql_table'], $app, $event_day, $event_hour, $event_minute ) ;
			$ret = $db->query( $sql ) ;
			if( $ret === false ){
				echo "query faild!\n" ;
				Bd_Log::fatal( "[UPDATE] pipelet_id:$pipelet partition:$event_day$event_hour$event_minute query_failed") ;
			}
			$tmp = $ret->fetch_row() ;
			$pipeletFull = intval($arrAppConf['pipelet_full']) ;
			Bd_Log :: trace( "[UPDATE] pipelet_id:$pipelet partition:$event_day$event_hour$event_minute [{$tmp[0]}/$pipeletFull]" ) ;
			
			if( $tmp[0] == $pipeletFull ){
				//创建新PARTITION
				$hql = "ALTER TABLE iknowetl_{$app} ADD PARTITION (event_day='{$event_day}', event_hour='{$event_hour}', event_minute='{$event_minute}') LOCATION '{$hdfsPath}'" ;
				$strCmdPartition = "source ~/.bashrc && sh hive_tool.sh hql \"$hql\"" ;
				Bd_Log :: trace( "[UPDATE] pipelet_id:$pipelet partition:$event_day$event_hour$event_minute add_partition_start:$strCmdPartition" ) ;
				exec( $strCmdPartition, $out, $ret ) ;
				Bd_Log :: trace( "[UPDATE] pipelet_id:$pipelet partition:$event_day$event_hour$event_minute add_partition_res:$ret" ) ;
			}

		}
	} 
	sleep( $arrAppConf['update_sleep_s'] ) ;
}


/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
