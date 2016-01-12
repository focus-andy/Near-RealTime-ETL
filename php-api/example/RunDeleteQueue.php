<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
****************************************************************************/
require_once(dirname(__FILE__).'/../frame/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/../frame/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');
require_once(dirname(__FILE__).'/../BigpipeQueueAdministrationTools.class.php');

// 初始化log配置
$log_conf = new BigpipeLogConf;
$log_conf->file = 'queue-operation-php';
$log_conf->severity = BigpipeLogSeverity::DEBUG;
if (BigpipeLog::init($log_conf))
{
    echo "[Success] [open queue client log]\n";
    print_r($log_conf);
    echo "\n";
}
else
{
    echo "[Failure] [open queue client log]\n";
    print_r($log_conf);
    echo "\n";
}

// 订阅参数
$conf_dir = './conf';
$conf_file = 'util_1.conf';
$name = 'autopush_t1';
$token = 'token';

$args = $_SERVER['argv'];
$prog_name = array_shift($args);
if (4 == count($args))
{
    // 读取命令行参数
    $conf_dir = $args[0];
    $conf_file = $args[1];
    $name = $args[2];
    $token = $args[3];
}


$conf_content = config_load($conf_dir, $conf_file);
if (false === $conf_content)
{
    echo config_error_message();
    die;
}

$ret = BigpipeQueueAdministrationTools::delete_queue($name, $token, $conf_content['meta']);
if (false === $ret)
{
    echo "afwul delete queue\n";
}
else
{
    echo "\n queue deleted \n";
}
BigpipeLog::close();
?>

