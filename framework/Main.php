<?php
/***************************************************************************
 * ETL入库程序
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/
 
 
 
/**
 * @file Main.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/08 16:58:32
 * @version $Revision$ 
 * @brief 
 *  
 **/

require_once( "./Init.php" ) ;
require_once( "./Data.php" ) ;
$init = new ETL_Init() ;
$init->init( $argc, $argv ) ;
$data = new ETL_Data() ;
$data->execute() ;

echo "\n\ndone\n" ;




/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
