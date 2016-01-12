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

// ��ʼ��log����
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

// ����configure�ļ����ط�ʽ��ͨ��php-configure������ub-style��configure�ļ�
// ub-style��configure�ļ���c-api��confige�ļ����ơ�
// BigpipeConf������ͨ��load�ӿڽ���array��ʽ��configure���롣
$conf = new BigpipeConf;
$conf_dir = './conf';
$conf_file = './php-api.conf';
if (false ===  $bigpipeConf->load(Conf_Bigpipe::$BIGPIPE_API_CONF))
{
    echo "[failure][when load configure]\n";
    die;
}

// ���Ĳ�������
// pipe name, token, pipelet id, start point��BigpipeConf
// pipelet id�м�������ֵ
// -1 ��ʾ�����µĿɶ��ĵ㿪ʼ������Ϣ
// -2 ��ʾ����ɿɶ��ĵ㿪ʼ������Ϣ
$pipe_name = 'iknow-submit-log-new-test';
$pipelet_id = 1; //
$start_point = -2;
$token = 'iknow';

// peek time��ʾÿ��peek�ȴ���ʱ��
$peek_time_ms = 200;

$file = fopen('./sub.txt', 'w+');
$sub = new BigpipeSubscriber;
// �������̴��û�����init�ӿڳɹ���ʼ
if ($sub->init($pipe_name, $token, $pipelet_id, $start_point, $conf))
{
    echo '[Success][init subscriber]\n';
    $loop_end = false;
    $count = 0;
    while (false === $loop_end)
    {
        // php��peek����һ�¼���
        // ����δ������Ϣʱ����������broker����broker������Ϣ
        // ������Ϣ�󣬼����˿ڣ��ȴ���Ϣ����
        $pret = $sub->peek($peek_time_ms);
        if (BigpipeErrorCode::READABLE == $pret)
        {
            // peek����readable�󣬵���receive��������
            // ��send�Ƿ���message package��ͬ��receive��������message package��
            // ÿ��receive�ɹ�������һ����Ϣ
            $msg = $sub->receive();
            if (false === $msg)
            {
                echo '[Receive][error]\n';
                $loop_end = true; // ��receive��������ʱ�˳�ѭ��
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
// ���Ľ���������unint������
$sub->uninit();
BigpipeLog::close();
?>
