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
 * 本类是一个用户自定义的partitioner类
 * bigpipe publisher在发布(send)消息时，使用partitioner的get_pipelet_id接口，指定消息发向的pipelet。
 * partitioner作为引用类型，在bigpipe publisher init时被传入。
 * 用户可以在publisher调用send前，改变partitioner的行为，从而控制消息发向的pipelet。
 */
class TestPubPartitioner
{
    /**
     * 只向一个pipelet发布
     */
    public function get_pipelet_id($msg_package, $partition_num)
    {
        return 0;
    }
}

// 初始化log配置
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

// 以下configure文件加载方式是通过php-configure，读入ub-style的configure文件
// ub-style的configure文件与c-api的confige文件相似。
// BigpipeConf还可以通过load接口接收array形式的configure输入。
$conf = new BigpipeConf;
$conf_dir = './conf';
$conf_file = './php-api.conf';
if (false === bigpipe_load_file($conf_dir, $conf_file, $conf))
{
    echo "[failure][when load configure]\n";
    die;
}

// 发布参数包括
// pipe name, token, paritioner和BigpipeConf
$pipe_name = 'yzytest1';
$token = 'token';
$partitioner = new TestPubPartitioner;

// 定义了包中最多包涵的message条数
$max_package_message_count = 300;
// 定义测试中发包条数
$max_package_count = 50000;
echo "[Publish $max_package_count packages]\n";
$pub_file = fopen('./pub.txt', 'w+');

// 以下描述了一个bigpipe publisher的发布流程
// 该流程适合用于无状态发布(既每次都是init + send)
$uid = BigpipeUtilities::get_uid();
$count = 0;
$succeed = 0;
while ($count < $max_package_count)
{
    $pub = new BigpipePublisher;
    if ($pub->init_ex($pipe_name, $token, $partitioner, $conf))
    {
        // 发布流程从调用init接口成功后开始
        echo '[Success][init publisher]\n';
        // 与c-api不同，
        // bigpipe php-api 发送的是一个message package
        // message package大小不能超过2MB
        // 用户声明BigpipeMessagePackage后, 调用push接口向package添加单条message
        $max_msg_count = rand(1, $max_package_message_count);
        echo "[Pack $max_msg_count messages to package <$count>]\n";
        $msg = null;
        $msg_package = new BigpipeMessagePackage;
        $msg_count = 0;
        while ($msg_count++ < $max_msg_count)
        {
            $msg = sprintf("[php-api-test][bigpipe_pvt_cluster3][uid:%s][package:%u][seq:%u]\n", $uid, $count, $msg_count);
            // 用户向message package添加单条message
            if (!$msg_package->push($msg))
            {
                echo "[fail to pack message]$msg_count\n";
                break; // 跳出loop
            }
        }// end of add message to package
        if ($msg_count != $max_msg_count + 1)
        {
            echo "[expected:$max_msg_count][actual:$msg_count]\n";
            continue; // 打包失败，退出
        }

        // 当消息添加完后，使用publisher的send接口，发布数据
        // send成功，返回的pub_result中包含了pipelet_id和pipelet_msg_id
        // pipelet_msg_id唯一标识了本个数据包在pipelet中的位置
        // send失败，用户可以选择继续send，这时send接口内部会更新状态，尝试重新发布
        $pub_result = $pub->send($msg_package);
        if (false === $pub_result)
        {
            echo "[fail to publish message package][count:$count]\n";
            break; // 出错便停止
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
        // 当发布结束后，使用uninit强制清空发布状态
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
