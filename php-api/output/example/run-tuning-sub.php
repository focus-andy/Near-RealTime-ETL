<?php
/**==========================================================================
 * 
 * sub0.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-06 by YANG ZHENYU (yangzhenyu@baidu.com)
 * 
 * --------------------------------------------------------------------------
 * 
 * Description
 *     subscriber tuningÊµÑé½Å±¾
 * 
 * --------------------------------------------------------------------------
 * 
 * Change Log
 * 
 * 
 ==========================================================================**/
 
require_once(dirname(__FILE__)."/TuningSubscriber.php");

$args = $_SERVER['argv'];
$prog_name = array_shift($args);

if (count($args) != 4)
{
    echo 'error subscribe arguments<br>';
    print_r($args);
}
else
{
    // sequence
    // pipelet
    // start point
    // max count
    run_tuning_sub($args[0], $args[1], $args[2], $args[3]);
}

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>

