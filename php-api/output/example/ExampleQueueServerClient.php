<?php
/***************************************************************************
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 ****************************************************************************/
require_once(dirname(__FILE__).'/../frame/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/../frame/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');
require_once(dirname(__FILE__).'/../BigpipeQueueClient.class.php');

// 初始化log配置
$log_dir = './conf';
$log_file = 'queue_util_example.conf';
$log_content = config_load($log_dir, $log_file);
$conf = new BigpipeQueueConf;
if (false === $log_content)
{
    echo config_error_message();
}
else
{
    if (false === $conf->load($log_content))
    {
        echo "[Failure][load configure][use default configure]\n";
    }
}

$log_conf = new BigpipeLogConf;
$log_conf->file = 'queue-client.php';
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
$queue_name = 'loop_queue_10_0_1';
$token = 'token';

$peek_time_ms = 100; // 每次peek最多等待100ms

$file = fopen('./queue-client.txt', 'w+');
$cli = new BigpipeQueueClient;
// 测试中，我们希望能取完所有窗口的数据
$max_count = 50000;
if ($cli->init($queue_name, $token, $conf))
{
    echo "[Success][init queue server client]\n";
    $count = 0;
    $success = 0;
    $failure = 0;
    $peek = 0;
    $ack = 0;
    while ($count < $max_count)
    {
        $pret = $cli->peek($peek_time_ms);
        if (BigpipeErrorCode::READABLE == $pret)
        {
            $msg = $cli->receive();
            if (false === $msg)
            {
                echo sprintf("[Failure][receive msg][cnt:%u]\n", $count);
                $failure++;
            }
            else
            {
                // 回复ack
				//if (false === $cli->ack($msg,true))  慢速客户端请调用此接口！！！同时设置滑动窗口为1
                if (false === $cli->ack($msg))
                {
                    // ack失败常由于msg处理超时引起，这时queuesvr中waiting队列中对应msg会被归还send队列。
                    // 用户不应该继续使用这条message。
                    // 这时多次重试ack也没有必要，因为一旦超时，socket就会被queuesvr主动关闭。
                    echo sprintf("[Failure][ack][cnt:%u]\n", $count);
                    $msg = false;
                }
                else
                {
                    // 我们以成功发送ack作为数据订阅成功的标志
                    // 如果ack发送失败, queue server会将数据重新放回发布队列,
                    // 因此我们必须在ack成功后才将数据落地.
                    // 否则会导致数据被重复订阅.
                    $success++;
                    $msg_str = sprintf("[Success][begin msg]\n[pipe:%s]\n[pipelet:%u]\n[id:%u]\n[seq:%u]\n[msg][%s]\n[Success][end msg]===\n",
                        $msg->pipe_name, $msg->pipelet_id, $msg->pipelet_msg_id, $msg->seq_id, $msg->message_body);
                    fwrite($file, $msg_str);
                    $ack++;
                }
            }

            if (false == $msg)
            {
                // 订阅过程中出现错无，刷新订阅连接
                if (false === $cli->refresh())
                {
                    echo sprintf("[Failure][refresh][cnt:%u]\n", $count);
                    break;
                }
            }
        }
        else if (BigpipeErrorCode::UNREADABLE == $pret)
        {
            $peek++;
        }
        else
        {
            echo sprintf("[Failure][peek][cnt:%u][ret:%u]\n", $count, $pret);
            $failure++;
            if (false == $cli->refresh())
            {
                echo sprintf("[Failure][refresh][cnt:%u]\n", $count);
                break;
            }
        }

        $count++;
    }
    echo sprintf("[Leave][queue server client][cnt:%u][succ:%u][ack:%u][peek:%u][fail:%u]\n", $count, $success, $ack, $peek, $failure);
}
else
{
    echo "[Failure][init queue server client]\n";
}

$cli->uninit();
BigpipeLog::close();
?>

