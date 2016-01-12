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

class TestPubPartitioner
{
    public function __construct($id)
    {
        $this->_pipelet = $id;
    }

    /**
     * 只向一个pipelet发布
     */
    public function get_pipelet_id($msg_package, $partition_num)
    {
        return $this->_pipelet;
    }

    private $_pipelet = null;
}

function run_tuning_pub($seq, $pid, $max_cnt, $one_msg)
{
    // 初始化log配置
    $log_conf = new BigpipeLogConf;
    $log_conf->file = sprintf('publisher-%u-%u.log', $seq, $pid);
    if (BigpipeLog::init($log_conf))
    {
        //echo '[Success] [open meta agent log]\n';
        //print_r($log_conf);
        //echo '\n';
    }
    else
    {
        echo "[Failure] [open meta agent log]\n";
        print_r($log_conf);
        echo "\n";
		return;
    }

    // subscriber的configure
    $conf = new BigpipeConf;
    $conf_dir = './conf';
    $conf_file = './for-tuning.conf';
    if (false === bigpipe_load_file($conf_dir, $conf_file, $conf))
    {
        echo "[failure][when load configure]\n";
        return;
    }
    
    // 发布参数
    $pipe_name = 'pipe2';
    //$pipelet_id = 1; // 加1在哪里做好呢？
    $token = 'token';
    $partitioner = new TestPubPartitioner($pid);

    // 生成一个1K左右的包
    $max_msg_count = 12; // 500k
    $msg = null;
    $the_one = sprintf("[case:%u]\n", $seq);
    $uid = BigpipeUtilities::get_uid();
    $msg_package = new BigpipeMessagePackage;
    $msg_count = 0;
    while ($msg_count++ < $max_msg_count)
    {
        $msg = sprintf("[php-api-test][bigpipe_comlog_pvt_mm][uid:%s][package:%u][seq:%u]\n", $uid, 0, $msg_count);
        if (true === $one_msg)
        {
            $the_one .= $msg;
        }
        else
        {
            if (!$msg_package->push($msg))
            {
                echo "[fail to pack message]$msg\n";
                break; // 退出
            }
        }
    }// end of add message to package

    if (true == $one_msg)
    {
        if (!$msg_package->push($the_one))
        {
            echo "[fail to pack message]$the_one\n";
            return; // 退出
        }
    }

    // 定义测试中发包条数
    $max_package_count = $max_cnt;
    echo "\n\n[Publish $max_package_count packages]\n\n";
    $pub_file_name = sprintf('./pub-timer-%u-%u.csv', $seq, $pid);
    $pub_file = fopen($pub_file_name, 'w+');
    $pub = new BigpipePublisher;
    $stat = array(
        0 => 0, // < 10ms
        1 => 0, // [10ms, 20ms)
        2 => 0, // >= 20ms
    );
    if ($pub->init($pipe_name, $token, $partitioner, $conf))
    {
        echo "[Success][init publisher]\n\n";
        $count = 0;
        $succeed = 0;
        $total_start = BigpipeUtilities::get_time_us();
        $is_first = true;
        printf("[case:%u][start:%u]\n\n", $seq, $total_start);
        while ($count < $max_package_count)
        {
            $count++;
            //$max_msg_count = rand(1, $max_package_message_count);
            //echo "[Pack $max_msg_count messages to package <$count>]\n";
            // if ($msg_count != $max_msg_count + 1)
            // {
            //     echo "[expected:$max_msg_count][actual:$msg_count]\n";
            //     continue; // 打包失败，退出
            // }
            $start_time = BigpipeUtilities::get_time_us();
            $msg_package->push(sprintf('timestamp:%u', $start_time)); // 加入时间戳
            $pub_result = $pub->send($msg_package);
            $end_time = BigpipeUtilities::get_time_us();
            if (false === $pub_result)
            {
                //echo "[fail to publish message package][count:$count]\n";
                //break; // 出错便停止
            }
            else
            {
                // write result to file
                //$ret_str = sprintf("%d,%u,%u,%u\n", 
                //                   $pub_result->error_no, 
                //                   $pub_result->pipelet_id,
                //                   $pub_result->pipelet_msg_id,
                //                   $pub_result->session_msg_id);
                //fwrite($pub_file, $ret_str);
                if (true == $is_first)
                {
                    printf('[%u][%u]\n', $pub_result->pipelet_id, $pub_result->pipelet_msg_id);
                    $is_first = false;
                }
                $succeed++;

            }
            $msg_package->pop(); // 弹出时间戳
            $t = (float)($end_time - $start_time) / 1000;
            $t_str = sprintf("%u\n", $t);
            fwrite($pub_file, $t_str);
            
        }
        $total_end = BigpipeUtilities::get_time_us();
        echo "[Publisher][seq:$seq][count:$count][success:$succeed]====\n";
        $avg = (float)($total_end - $total_start) / (float)(1000 * $max_package_count); 
        printf("\n[Publisher][case:%u][avg_time:%f(ms)]\n", $seq, $avg);
    }
    else
    {
        echo '[Failure][init publisher]\n';
    }

    $pub->uninit();
    BigpipeLog::close();
}

?>
