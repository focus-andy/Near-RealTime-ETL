<?php
/***************************************************************************
 * 
 * Copyright (c) 2009 Baidu.com, Inc. All Rights Reserved
 * 
 **************************************************************************/
/**
 * @file: TestMetaAgentAdapter.php
 * @brief: 测试MetaAgentAdapter
 **/
require_once(dirname(__FILE__).'/../frame/MetaAgentAdapter.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');
require_once(dirname(__FILE__).'/../frame/BigpipeLog.class.php');
// 初始化log配置
$log_conf = new BigpipeLogConf;
$log_conf->severity = BigpipeLogSeverity::DEBUG;
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

// 准备configuration
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

// 取得可发布broker
$pipe_name = 'pipe1';
$pipelet_id = 2; // 加1在哪里做好呢？
$broker = $adapter->get_pub_broker($pipe_name, $pipelet_id);
if (!$broker)
{
   echo "[Failure][get pub info]\n";
}
else
{
   echo "[Success][get pub info]\n";
   print_r($broker);
   echo "\n";
}

// 取得可订阅broker_group
$start_point = -1;
$sub_info = $adapter->get_sub_broker_group($pipe_name, $pipelet_id, $start_point);
if (false === $sub_info)
{
    echo "[Failure][get sub info]\n";
}
else
{
    echo "[Success][get sub info]\n";
    // 解析sub info各个字段
    while (list($name, $value) = each($sub_info))
    {
        // 打印除了broker group以外的字段
        if ($name != 'broker_group')
        {
            echo "[$name][$value]\n";
        }
    }
    
    // 解析json object
    $broker_group = $sub_info['broker_group'];
    echo "[group name      ][$broker_group->name]\n";
    echo "[epoch           ][$broker_group->epoch]\n";
    echo "[repair_last_data][$broker_group->repair_last_data]\n";
    echo "[status          ][$broker_group->status]\n";
    echo "[to_delete       ][$broker_group->to_delete]\n";
    echo "[timestamp       ][$broker_group->to_delete_update_timestamp]\n";
    echo "[brokers         ]\n";
    // brokers
    foreach ($broker_group->brokers as $bk)
    {
        echo "[   group][$bk->group]\n";
        echo "[    name][$bk->name]\n";
        echo "[      ip][$bk->ip]\n";
        echo "[    port][$bk->port]\n";
        echo "[    role][$bk->role]\n";
    }
    echo "\n";

    $ofs = fopen('sub.json', 'w+');
    $oval = json_encode($sub_info);
    fwrite($ofs, $oval);
    fclose($ofs);
}

// todo 用户操作

$adapter->close();
?>

