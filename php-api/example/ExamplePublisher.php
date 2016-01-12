<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
****************************************************************************/
require_once(dirname(__FILE__).'/../frame/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/../frame/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');
require_once(dirname(__FILE__).'/../BigpipePublisher.class.php');
require_once(dirname(__FILE__).'/../test/TestUtilities.class.php');

/**
 * ������һ���û��Զ����partitioner��
 * bigpipe publisher�ڷ���(send)��Ϣʱ��ʹ��partitioner��get_pipelet_id�ӿڣ�ָ����Ϣ�����pipelet��
 * partitioner��Ϊ�������ͣ���bigpipe publisher initʱ�����롣
 * �û�������publisher����sendǰ���ı�partitioner����Ϊ���Ӷ�������Ϣ�����pipelet��
 */
class TestPubPartitioner
{
    /**
     * ֻ��һ��pipelet����
     */
    public function get_pipelet_id($msg_package, $partition_num)
    {
        return 0;
    }
}

// ��ʼ��log����
$log_conf = new BigpipeLogConf;
$log_conf->file = 'publisher.php';
if (BigpipeLog::init($log_conf))
{
    echo "[success] ==> [open publisher log]\n";
}
else
{
    echo "[failure] ==> [open publisher log]\n";
    print_r($log_conf);
    die;
}

// ����configure�ļ����ط�ʽ��ͨ��php-configure������ub-style��configure�ļ�
// ub-style��configure�ļ���c-api��confige�ļ����ơ�
// BigpipeConf������ͨ��load�ӿڽ���array��ʽ��configure���롣
$conf = new BigpipeConf;
$conf_dir = './conf';
$conf_file = './php-api.conf';
if (false === bigpipe_load_file($conf_dir, $conf_file, $conf))
{
    echo "[failure][when load configure]\n";
    die;
}

// ������������
// pipe name, token, paritioner��BigpipeConf
$pipe_name = 'yzytest1';
$token = 'token';
$partitioner = new TestPubPartitioner;

// �����˰�����������message����
$max_package_message_count = 300;
// ��������з�������
$max_package_count = 50000;
echo "[Publish $max_package_count packages]\n";
$pub_file = fopen('./pub.txt', 'w+');

// ����������һ��bigpipe publisher�ķ�������
// �������ʺ�������״̬����(��ÿ�ζ���init + send)
$uid = BigpipeUtilities::get_uid();
$count = 0;
$succeed = 0;
while ($count < $max_package_count)
{
    $pub = new BigpipePublisher;
    if ($pub->init_ex($pipe_name, $token, $partitioner, $conf))
    {
        // �������̴ӵ���init�ӿڳɹ���ʼ
        echo '[Success][init publisher]\n';
        // ��c-api��ͬ��
        // bigpipe php-api ���͵���һ��message package
        // message package��С���ܳ���2MB
        // �û�����BigpipeMessagePackage��, ����push�ӿ���package��ӵ���message
        $max_msg_count = rand(1, $max_package_message_count);
        echo "[Pack $max_msg_count messages to package <$count>]\n";
        $msg = null;
        $msg_package = new BigpipeMessagePackage;
        $msg_count = 0;
        while ($msg_count++ < $max_msg_count)
        {
            $msg = sprintf("[php-api-test][bigpipe_pvt_cluster3][uid:%s][package:%u][seq:%u]\n", $uid, $count, $msg_count);
            // �û���message package��ӵ���message
            if (!$msg_package->push($msg))
            {
                echo "[fail to pack message]$msg_count\n";
                break; // ����loop
            }
        }// end of add message to package
        if ($msg_count != $max_msg_count + 1)
        {
            echo "[expected:$max_msg_count][actual:$msg_count]\n";
            continue; // ���ʧ�ܣ��˳�
        }

        // ����Ϣ������ʹ��publisher��send�ӿڣ���������
        // send�ɹ������ص�pub_result�а�����pipelet_id��pipelet_msg_id
        // pipelet_msg_idΨһ��ʶ�˱������ݰ���pipelet�е�λ��
        // sendʧ�ܣ��û�����ѡ�����send����ʱsend�ӿ��ڲ������״̬���������·���
        $pub_result = $pub->send($msg_package);
        if (false === $pub_result)
        {
            echo "[fail to publish message package][count:$count]\n";
            break; // �����ֹͣ
        }
        else
        {
            // write result to file
            $session = TestUtilities::get_private_var($pub, '_session');
            $ret_str = sprintf("%d,%u,%u,%u,%s\n", 
                $pub_result->error_no, 
                $pub_result->pipelet_id,
                $pub_result->pipelet_msg_id,
                $pub_result->session_msg_id,
                $session
            );
            fwrite($pub_file, $ret_str);
            $succeed++;
        }
        echo "[Publisher][count:$count][success:$succeed]====\n";
        // ������������ʹ��uninitǿ����շ���״̬
        $pub->uninit();
    }
    else
    {
        echo "[Failure][init publisher]\n";
    }
    $count++;
} // end of while
BigpipeLog::close();
?>
