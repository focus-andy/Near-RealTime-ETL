<?php
/***************************************************************************
 *
 * Copyright (c) 2009 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/
/**
 * @file: TestBigpipeStompAdapter.php
 * @brief: 测试TestBigpipeStompAdapter
 **/
require_once(dirname(__FILE__).'/../frame/MetaAgentAdapter.class.php');
require_once(dirname(__FILE__).'/../frame/BigpipeStompAdapter.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');
require_once(dirname(__FILE__).'/../frame/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_utilities.inc.php');

// 初始化log配置
$log_conf = new BigpipeLogConf;
if (BigpipeLog::init($log_conf))
{
    echo '[Success] [open meta agent log]\n';
    print_r($log_conf);
    echo '\n';
}
else
{
    echo '[Failure] [open meta agent log]\n';
    print_r($log_conf);
    echo '\n';
}

// 准备meta agent
$meta_conf = new BigpipeMetaConf;
$meta_conf->meta_host = '10.218.32.11:2181,10.218.32.20:2181,10.218.32.21:2181,10.218.32.22:2181,10.218.32.23:2181';
$meta_conf->root_path = '/bigpipe_pvt_cluster3';
$agent_conf = new MetaAgentConf;
$agent_conf->meta = $meta_conf->to_array();
$agent_conf->agents = array (
    array ("socket_address"  => "10.46.46.54",
           "socket_port"     => 8021,                       
           "socket_timeout"  => 300),                
);
$agent_conf->conn_conf->try_time = 1; // 调试方便
$adapter = new MetaAgentAdapter;
if (false === $adapter->init($agent_conf))
{
    die;
}

if ($adapter->connect())
{
    echo "[Success] [connect to meta] [$adapter->meta_name]\n";
}
else
{
    echo "[Failure] [$adapter->last_error_message]\n";
}

// 准备bigpipe stomp
$stomp_conf = new BigpipeStompConf;
$stomp_conf->conn_conf->try_time = 1;
$stomp = new BigpipeStompAdapter($stomp_conf);

// 发布/订阅参数
$pipe_name = 'pipe1';
$pipelet_id = 2; // 加1在哪里做好呢？
$start_point = -1;

// 取得可发布broker
$broker = $adapter->get_pub_broker($pipe_name, $pipelet_id);
if (false === $broker)
{
    echo "[Failure][get pub info]\n";
}
else
{
    echo "[Success][get pub info]\n";
    // 向broker发起连接请求
    $pub_dest = array(
            "socket_address" => $broker['ip'],
            "socket_port"    => $broker['port'],
            "socket_timeout" => 300,
    );
    $stomp->set_destination($pub_dest);
    $stomp->role = BStompRoleType::PUBLISHER;
    $stomp->topic_name = $broker['stripe'];
    $stomp->session_id = BigpipeUtilities::get_pipelet_name($pipe_name, $pipelet_id) . '_' . BigpipeUtilities::get_uid();
    if ($stomp->connect())
    {
        echo '[Success][connected on broker][ip:'. $broker['ip'] . '][port:' . $broker['port'] . ']\n';
        echo '[session message id][' . $stomp->session_message_id . ']\n';
    }
    echo "\n";
    $ofs = fopen('pub.json', 'w+');
    $oval = json_encode($broker);
    fwrite($ofs, $oval);
    fclose($ofs);
}

// 取得可订阅broker_group

// todo 用户操作
$stomp->close();
$adapter->uninit();
?>
