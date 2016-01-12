<?php
/***************************************************************************
 * 
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/
 
 
 
/**
 * @file Init.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/09 16:13:28
 * @version $Revision$ 
 * @brief 
 *  
 **/
require_once( "./Base.php" ) ; 

class ETL_Init extends ETL_Base{
	public function init( $argc, $argv ){
		//STREAM 1. 检查输入参数是否合法
		if( !self :: paramsCheck( $argc, $argv ) )
			exit ;

		if( parent :: $isInit == false ){
			//STEP 2. 加载配置文件
			self :: loadAppConf() ;
			//STEP 3. 加载event文件
			self :: loadAppEvent() ;
			//STEP 4. 加载MAP映射文件
			self :: loadAppMap() ;
			//SETP 5. 检查表是否存在，不存在则创建表
			//		self :: createTableIfNoExist() ;
			//STEP 5. 加载用户自定义Hook文件
			self :: loadAppHook() ;
			//SETP 6. 加载公共函数库文件
			self :: loadComLib() ;
			parent :: $isInit = true ;
		}
	}

	private function paramsCheck( $argc, $argv ){
		if( $argc != 3 ){
			echo "WARNING! Please input params 'app=XXX pipelet=XXX'\n" ;
			return false ;
		}	
		$arrApp = explode( "=", $argv[1] ) ;
		if( !isset($arrApp[0]) || $arrApp[0] != 'app' ){
			echo "WARNING! Please input params 'app=XXX pipelet=XXX'\n" ;
			return false ;
		}
		$arrPipelet = explode( "=", $argv[2] ) ;
		if( !isset($arrPipelet[0]) || $arrPipelet[0] != 'pipelet' ){
			echo "WARNING! Please input params 'app=XXX pipelet=XXX'\n" ;
			return false ;
		}
		parent :: $app = trim($arrApp[1]) ;
		parent :: $pipelet = intval($arrPipelet[1]) ;

		return true ;
	}
	
	//框架自身配置文件
	private function loadConf(){
		parent :: $arrConf = Bd_Conf :: getConf( '/etl') ;
	}

	//APP独有配置文件
	private function loadAppConf(){
		$confFile = "/". parent :: $app. "/". parent :: $app ;	
		parent :: $arrAppConf = Bd_Conf::getConf( $confFile ) ;
		if( parent :: $arrAppConf == false )
			return false ;
		return true ;
	}

	//APPevent信息类
	private function loadAppEvent(){
		$appEventFile = ETL_APP. parent :: $app. "/event/event.php" ;
		require_once( $appEventFile ) ;
	}
	private function loadAppMap(){
		$mapFile =  ETL_APP. parent :: $app. "/map/map.php" ;
		require_once( $mapFile ) ;
		parent :: $arrMap = $arrMap ;
	}

	private function createTableIfNoExist(){
		exec( "sh hive_tool.sh hql \"DESC iknowetl_". parent :: $app. "\"", $out, $ret ) ;
		if( $ret != 0 ){
			$fileHql = ETL_APP. parent :: $app. "/event/". parent :: $app. ".hql" ;
			if( 0 != exec( "sh hive_tool.sh fhql $fileHql" ) ){
				Bd_Conf::warning( "Failed: Cannot create table". parent :: $app ) ;
				return false ;
			}
		}
	}

	private function loadAppHook(){
		$appHookFile = ETL_APP. parent :: $app. "/hook/hook.php" ;
		require_once( $appHookFile ) ;
	}

	private function loadComLib(){
		$comLibFile = ETL_CLIB. "/common.php" ;
		require_once( $comLibFile ) ;
	}
}




/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
