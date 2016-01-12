<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
****************************************************************************/
require_once(dirname(__FILE__).'/../frame/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/../frame/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');
require_once(dirname(__FILE__).'/../BigpipeSubscriber.class.php');
require_once(dirname(__FILE__).'/conf.php');
echo 'step1';

// 初始化log配置
$log_conf = new BigpipeLogConf;
echo 'step2';
$log_conf->file = 'subscribe.php';
echo 'step3'
if (BigpipeLog::init($log_conf))
{
    echo '[Success] [open subscribe log]\n';
    print_r($log_conf);
    echo '\n';
}
else
{
    echo '[Failure] [open subscribe log]\n';
    print_r($log_conf);
    echo '\n';
}

// 以下configure文件加载方式是通过php-configure，读入ub-style的configure文件
// ub-style的configure文件与c-api的confige文件相似。
// BigpipeConf还可以通过load接口接收array形式的configure输入。
$conf = new BigpipeConf;
$conf_dir = './conf';
$conf_file = './php-api.conf';
if (false ===  $bigpipeConf->load(Conf_Bigpipe::$BIGPIPE_API_CONF))
{
    echo "[failure][when load configure]\n";
    die;
}

// 订阅参数包括
// pipe name, token, pipelet id, start point和BigpipeConf
// pipelet id有几个特殊值
// -1 表示从最新的可订阅点开始订阅消息
// -2 表示从最旧可订阅点开始订阅消息
$pipe_name = 'iknow-submit-log-new-test';
$pipelet_id = 1; //
$start_point = -2;
$token = 'iknow';

// peek time表示每次peek等待的时间
$peek_time_ms = 200;

$file = fopen('./sub.txt', 'w+');
$sub = new BigpipeSubscriber;
// 订阅流程从用户调用init接口成功后开始
if ($sub->init($pipe_name, $token, $pipelet_id, $start_point, $conf))
{
    echo '[Success][init subscriber]\n';
    $loop_end = false;
    $count = 0;
    while (false === $loop_end)
    {
        // php的peek包括一下几步
        // 当还未订阅消息时，主动更新broker，向broker订阅消息
        // 订阅消息后，监听端口，等待消息到达
        $pret = $sub->peek($peek_time_ms);
        if (BigpipeErrorCode::READABLE == $pret)
        {
            // peek返回readable后，调用receive接收数据
            // 与send是发布message package不同，receive会主动解message package，
            // 每次receive成功，返回一条消息
            $msg = $sub->receive();
            if (false === $msg)
            {
                echo '[Receive][error]\n';
                $loop_end = true; // 当receive发生错误时退出循环
            }
            else
            {
                $msg_str = sprintf("[begin]===\n[msg_id][%u]\n[seq_id][%u]\n[msg][%s]\n[end]===\n", $msg->msg_id, $msg->seq_id, $msg->content);
                fwrite($file, $msg_str);
            }
            $count = 0;
        }
        else if (BigpipeErrorCode::UNREADABLE == $pret)
        {
            $count++;
            $msg_str = sprintf("[try][count:%d]\n", $count);
            fwrite($file, $msg_str);
        }
        else
        {
            echo "[Peek][Error][ret:$pret]\n";
            $loop_end = true;
        }
    }
    echo "[Leave][Peek][count:$count]====\n";
}
else
{
    echo '[Failure][init subscribe]\n';
}
// 订阅结束后主动unint订阅者
$sub->uninit();
BigpipeLog::close();
?>
