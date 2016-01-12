<?php
/**==========================================================================
 * 
 * test/pub.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-05 by YANG ZHENYU (yangzhenyu@baidu.com)
 * 
 * --------------------------------------------------------------------------
 * 
 * Description
 *    publisher的tuning实验脚本
 * 
 * --------------------------------------------------------------------------
 * 
 * Change Log
 * 
 * 
 ==========================================================================**/
require_once(dirname(__FILE__)."/TuningPublisher.php");

$args = $_SERVER['argv'];
$prog_name = array_shift($args);

if (count($args) != 3)
{
    echo 'error publisher arguments<br>';
    print_r($args);
}
else
{
    // sequence
    // pipelet
    // max count 
    // only a message in a package
    run_tuning_pub($args[0], $args[1], $args[2], true);
}

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
