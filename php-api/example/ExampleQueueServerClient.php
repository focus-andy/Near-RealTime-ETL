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

// ��ʼ��log����
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

// ���Ĳ���
$queue_name = 'loop_queue_10_0_1';
$token = 'token';

$peek_time_ms = 100; // ÿ��peek���ȴ�100ms

$file = fopen('./queue-client.txt', 'w+');
$cli = new BigpipeQueueClient;
// �����У�����ϣ����ȡ�����д��ڵ�����
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
                // �ظ�ack
				//if (false === $cli->ack($msg,true))  ���ٿͻ�������ô˽ӿڣ�����ͬʱ���û�������Ϊ1
                if (false === $cli->ack($msg))
                {
                    // ackʧ�ܳ�����msg����ʱ������ʱqueuesvr��waiting�����ж�Ӧmsg�ᱻ�黹send���С�
                    // �û���Ӧ�ü���ʹ������message��
                    // ��ʱ�������ackҲû�б�Ҫ����Ϊһ����ʱ��socket�ͻᱻqueuesvr�����رա�
                    echo sprintf("[Failure][ack][cnt:%u]\n", $count);
                    $msg = false;
                }
                else
                {
                    // �����Գɹ�����ack��Ϊ���ݶ��ĳɹ��ı�־
                    // ���ack����ʧ��, queue server�Ὣ�������·Żط�������,
                    // ������Ǳ�����ack�ɹ���Ž��������.
                    // ����ᵼ�����ݱ��ظ�����.
                    $success++;
                    $msg_str = sprintf("[Success][begin msg]\n[pipe:%s]\n[pipelet:%u]\n[id:%u]\n[seq:%u]\n[msg][%s]\n[Success][end msg]===\n",
                        $msg->pipe_name, $msg->pipelet_id, $msg->pipelet_msg_id, $msg->seq_id, $msg->message_body);
                    fwrite($file, $msg_str);
                    $ack++;
                }
            }

            if (false == $msg)
            {
                // ���Ĺ����г��ִ��ޣ�ˢ�¶�������
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

