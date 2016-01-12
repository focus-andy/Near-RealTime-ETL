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

function run_tuning_sub($seq, $pid, $sp, $max_count)
{
    // 初始化log配置
    $log_conf = new BigpipeLogConf;
    $log_conf->file = sprintf('subscribe-%u-%u.php', $seq, $pid);
    if (BigpipeLog::init($log_conf))
    {
        //echo "[Success] [open subscribe log]\n\n";
        //print_r($log_conf);
        //echo '\n';
    }
    else
    {
        echo '[Failure] [open subscribe log]\n';
        print_r($log_conf);
        echo '\n';
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
    
    // 订阅参数
    $pipe_name = 'pipe2';
    $pipelet_id = $pid; //
    $start_point = $sp;
    $token = 'token';

    $peek_time_ms = 200;

    $suffix_str = sprintf('-%u-%u', $seq, $pid);
    $file_sub_succ = fopen('./sub_succ-'.$suffix_str.'.csv', 'w+');
    $file_sub_wait = fopen('./sub_wait-'.$suffix_str.'.csv', 'w+');
    $file_sub_fail = fopen('./sub_fail-'.$suffix_str.'.csv', 'w+');
    $sub = new BigpipeSubscriber;
    if ($sub->init($pipe_name, $token, $pipelet_id, $start_point, $conf))
    {
        echo '[Success][init subscriber]\n';
        $count = 0;
        $failure = 0;
        $success = 0;
        $wait_cnt = 0;
        $run_time_str = null;
        $total_start = BigpipeUtilities::get_time_us();
        while ($success  < $max_count && 
               $wait_cnt < $max_count && 
               $failure  < $max_count)
        {
            $pret = $sub->peek($peek_time_ms);
            if (BigpipeErrorCode::READABLE == $pret)
            {
                $msg = $sub->receive();
                if (false === $msg)
                {
                    $failure++;
                }
                else
                {
                    if (true === $msg->is_last_msg)
                    {
                        $end_time = BigpipeUtilities::get_time_us();
                        if (0 == strncmp('timestamp:', $msg->content, 10))
                        {
                            $t_str = substr($msg->content, 10);
                            $run_time_str = 
                                sprintf("%u,%u,%s,%u\n", $pipelet_id, $msg->msg_id, $t_str, $end_time);

                            fwrite($file_sub_succ, $run_time_str);
                        }
                    }
                    $success++;
                }
            }
            else if (BigpipeErrorCode::UNREADABLE == $pret)
            {
                $wait_cnt++;
            }
            else
            {
                $failure++;
            }
            $count++;
        }
        $total_end = BigpipeUtilities::get_time_us();
        printf("[Leave][seq:%u][count:%u][succ:%u][fail:%u][wait:%u]\n\n",
               $seq, $count, $success, $failure, $wait_cnt);
        $avg = (float)($total_end - $total_start) / (float)(1000 * $count); 
        printf("[Subscriber][seq:%u][avg_time:%f(ms)]\n", $seq, $avg);
    } // end if
    else
    {
        echo '[Failure][init subscribe]\n';
    }

    $sub->uninit();
    BigpipeLog::close();
}
?>
