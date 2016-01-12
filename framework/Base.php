<?php
/***************************************************************************
 * 虚基类Base
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/
 
 
 
/**
 * @file Base.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/09 15:55:43
 * @version $Revision$ 
 * @brief 
 *  
 **/
require_once( '/home/iknow/odp2.4/php/phplib/bd/Conf.php' ) ;
require_once( '/home/iknow/odp2.4/php/phplib/bd/Log.php' ) ;

define( 'ETL_ROOT', dirname(dirname(__FILE__)) ) ;
define( 'ETL_CONF', ETL_ROOT."/conf/" ) ;
define( 'ETL_APP', ETL_ROOT."/app/" ) ;
define( 'ETL_CLIB', ETL_ROOT."/clib/" ) ;
define( 'ETL_LOG', ETL_ROOT."/log/" ) ;
define( 'ETL_API', ETL_ROOT."/php-api/" ) ;
define( 'ETL_DATA', ETL_ROOT."/data/" ) ;
define( 'MODULE', 'iknow_submit' ) ;

abstract class ETL_Base{
	protected static $app = '' ;
	protected static $pipelet = -1 ;	
	protected static $arrConf = array() ;
	protected static $arrAppConf = array() ;
	protected static $arrMap = array() ;
	protected static $isInit = false ;

	public function getApp(){
		return self :: $app ;
	}

	public function getPipelet(){
		return self :: $pipelet ;
	}
	
	public function getConf(){
		return self :: $arrConf ;
	}

	public function getAppConf(){
		return self :: $arrAppConf ;
	}
}




/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
