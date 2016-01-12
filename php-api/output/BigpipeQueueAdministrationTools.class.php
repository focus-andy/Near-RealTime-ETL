<?php
/**==========================================================================
 *
* BigpipeQueueAdministrationTools.class.php - INF / DS / BIGPIPE
*
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
* Created on 2012-12-19 by YANG ZHENYU (yangzhenyu@baidu.com)
*
* --------------------------------------------------------------------------
*
* Description
*
*
* --------------------------------------------------------------------------
*
* Change Log
*
*
==========================================================================**/
require_once(dirname(__FILE__).'/frame/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/frame/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/frame/BigpipeMetaManager.class.php');

/**
 * bigpipe queue的管理类<p>
 * 实现了创建, 启动, 停止, 删除一个queue的功能
 * @author yangzhenyu@baidu.com
 */
final class BigpipeQueueAdministrationTools
{
    /**
     * 传入meta和queue的配置，在meta上创建一个新的queue
     * @param array $meta_params  : meta配置
     * @param array $queue_params : queue的配置
     * @param array $node_stat    : 创建成功后返回节点信息
     * @return true on success or false on failure
     */
    public static function create_queue($meta_params, $queue_params, &$node_stat)
    {
        // 参数检查
        if (false === is_array($meta_params)
            || false === is_array($queue_params))
        {
            BigpipeLog::fatal("[%s:%u][%s][invalid params]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $meta = (true === self::$unittest) ? self::$stub_meta : self::_init_meta($meta_params);
        if (false === $meta)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to init meta]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 归一化queue params，使其成为queue node可接收格式
        $queue_node = self::_normalize_queue_params($queue_params);
        if (false === $queue_node)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to normalize queue node]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 将归一化后的值写入节点
        $full_entry = sprintf('queue/%s', $queue_node['name']);
        if (false === $meta->create_entry($full_entry))
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to create entry under /%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        // set 节点
        if (false === $meta->set_entry($full_entry, $queue_node))
        {
            return false;
        }

        // 收集新节点的统计信息
        $stat = null;
        $new_node = $meta->get_entry($full_entry, $stat);
        if (false === $new_node)
        {
            BigpipeLog::fatal("[%s:%u][%s][can not access to queue under /%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        $node_stat = array(
            'content' => $new_node,
            'version' => $stat);
        return true;
    }

    /**
     * 启动一个queue
     * @param string $name        : name of the queue
     * @param string $token       : token of the queue
     * @param array  $meta_params : meta配置信息
     * @param array  $node_stat   : 成功后返回的meta信息
     * @return boolean
     */
    public static function start_queue($name, $token, $meta_params, &$node_stat)
    {
        // 参数检查
        if (false === is_array($meta_params)
            || true === empty($name)
            || true === empty($token))
        {
            BigpipeLog::fatal("[%s:%u][%s][invalid params]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $meta = (true === self::$unittest) ? self::$stub_meta : self::_init_meta($meta_params);
        if (false === $meta)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to init meta]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 读取queue entry信息
        $full_entry = sprintf('queue/%s', $name);
        $stat = array ('version' => 'for test'); //仅在测试时有用
        $queue_info = $meta->get_entry($full_entry, $stat);
        if (false === $queue_info)
        {
            BigpipeLog::fatal("[%s:%u][%s][can not access to queue under /%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        if ($token != $queue_info['token'])
        {
            BigpipeLog::fatal("[%s:%u][%s][wrong token][entry:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        $status = $queue_info['status'];
        if (BigpipeQueueStatus::STARTED == $status)
        {
            BigpipeLog::warning("[%s:%u][%s][queue is runing now][entry:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            $node_stat = $stat;
            return true;
        }
        else if (BigpipeQueueStatus::DELETED == $status)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to start the queue. queue is to be deleted]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $curr_version = $stat['version'];
        $queue_info['status'] = BigpipeQueueStatus::STARTED; // change status of a queue
        $ret = $meta->update_entry($full_entry, $queue_info, $curr_version);
        if (false === $ret)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to start the queue][name:%s][ver:%u]",
                __FILE__, __LINE__, __FUNCTION__, $name, $curr_version);
            return false;
        }

        BigpipeLog::notice("[%s:%u][%s][queue is started][name:%s]",
            __FILE__, __LINE__, __FUNCTION__, $name);
        return true;
    }

    /**
     * 暂停一个处于started或created态的queue
     * @param string $name        : name of the queue
     * @param string $token       : token of the queue
     * @param array  $meta_params : meta配置信息
     * @param array  $node_stat   : 成功后返回的meta信息
     * @return boolean
     */
    public static function stop_queue($name, $token, $meta_params, &$node_stat)
    {
        // 参数检查
        if (false === is_array($meta_params)
            || true === empty($name)
            || true === empty($token))
        {
            BigpipeLog::fatal("[%s:%u][%s][invalid params]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $meta = (true === self::$unittest) ? self::$stub_meta : self::_init_meta($meta_params);
        if (false === $meta)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to init meta]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 读取queue entry信息
        $full_entry = sprintf('queue/%s', $name);
        $stat = array ('version' => 'for test'); //仅在测试时有用
        $queue_info = $meta->get_entry($full_entry, $stat);
        if (false === $queue_info)
        {
            BigpipeLog::fatal("[%s:%u][%s][can not access to queue under /%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        if ($token != $queue_info['token'])
        {
            BigpipeLog::fatal("[%s:%u][%s][wrong token][entry:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        $status = $queue_info['status'];
        if (BigpipeQueueStatus::STARTED != $status
            && BigpipeQueueStatus::CREATED != $status)
        {
            // 不改变其它状态的queue node
            BigpipeLog::warning("[%s:%u][%s][queue is not started now][entry:%s][status:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry, BigpipeQueueStatus::to_string($status));
            $node_stat = $stat;
            return true;
        }

        $curr_version = $stat['version'];
        $queue_info['status'] = BigpipeQueueStatus::STOPPED; // change status of a queue
        $ret = $meta->update_entry($full_entry, $queue_info, $curr_version);
        if (false === $ret)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to stop the queue][name:%s][ver:%u]",
                __FILE__, __LINE__, __FUNCTION__, $name, $curr_version);
            return false;
        }

        BigpipeLog::notice("[%s:%u][%s][queue is stopped][name:%s]",
            __FILE__, __LINE__, __FUNCTION__, $name);
        return true;
    }

    /**
     * 删除一个stop状态的queue
     * @param string $name        : name of the queue
     * @param string $token       : token of the queue
     * @param array  $meta_params : meta配置信息
     * @return boolean
     */
    public static function delete_queue($name, $token, $meta_params)
    {
        // 参数检查
        if (false === is_array($meta_params)
            || true === empty($name)
            || true === empty($token))
        {
            BigpipeLog::fatal("[%s:%u][%s][invalid params]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $meta = (true === self::$unittest) ? self::$stub_meta : self::_init_meta($meta_params);
        if (false === $meta)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to init meta]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 读取queue entry信息
        $full_entry = sprintf('queue/%s', $name);
        $stat = array ('version' => 'for test'); //仅在测试时有用
        $queue_info = $meta->get_entry($full_entry, $stat);
        if (false === $queue_info)
        {
            BigpipeLog::fatal("[%s:%u][%s][can not access to queue under /%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        if ($token != $queue_info['token'])
        {
            BigpipeLog::fatal("[%s:%u][%s][wrong token][entry:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        $status = $queue_info['status'];
        if (BigpipeQueueStatus::STOPPED != $status)
        {
            // 只删除stop状态的queue node
            BigpipeLog::warning("[%s:%u][%s][please stop the queue before delete it][entry:%s][status:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry, BigpipeQueueStatus::to_string($status));
            return false;
        }

        $curr_version = $stat['version'];
        $queue_info['status'] = BigpipeQueueStatus::STOPPED; // change status of a queue
        $ret = $meta->delete_entry($full_entry);
        if (false === $ret)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to delete the queue][name:%s][ver:%u]",
                __FILE__, __LINE__, __FUNCTION__, $name, $curr_version);
            return false;
        }

        BigpipeLog::notice("[%s:%u][%s][queue is deleted][name:%s]",
            __FILE__, __LINE__, __FUNCTION__, $name);
        return true;
    }

    /** 以下变量仅仅在test时被使用 */
    public static $unittest = false;
    public static $stub_meta = null;

    /**
     * 初始化一个meta管理类
     * @param MetaManager $meta_param
     * @return inited MetaManager on success or false on failure
     */
    private static function _init_meta($meta_param)
    {
        $meta = new BigpipeMetaManager;
        if (false === $meta->init($meta_param))
        {
            return false;
        }

        return $meta;
    }

    /**
     * 将传入的queue的参数组织成queue node value array
     * @param array $queue_params
     * @return queue node value in array on success or false on failure
     */
    private static function _normalize_queue_params($queue_params)
    {
        $value_arr = array();
        try 
        {
            // queue 基本属性
            self::_check_assign_array($value_arr, 'name', $queue_params, 'queue_name', false);
            self::_check_assign_array($value_arr, 'token', $queue_params, 'queue_token', false);
            self::_check_assign_array($value_arr, 'msg_timeo', $queue_params, 'msg_timeo');
            self::_check_assign_array($value_arr, 'save_count', $queue_params, 'save_cnt');
            self::_check_assign_array($value_arr, 'save_interval', $queue_params, 'save_interval');
            self::_check_assign_array($value_arr, 'weight', $queue_params, 'queue_weight');
            self::_check_assign_array($value_arr, 'window_cnt', $queue_params, 'window_cnt');
            self::_check_assign_array($value_arr, 'window_size', $queue_params, 'window_size');
            // 以下4项是为selector新增的meta项
            self::_check_assign_array($value_arr, 'selector_enabled', $queue_params, 'selector_enabled');
            self::_check_assign_array($value_arr, 'selector_so_path', $queue_params, 'selector_so_path', false);
            self::_check_assign_array($value_arr, 'selector_conf_path', $queue_params, 'selector_conf_path', false);
            self::_check_assign_array($value_arr, 'selector_conf_file', $queue_params, 'selector_conf_file', false);
            // 以下2项是为推送队列新增的meta项
            self::_check_assign_array($value_arr, 'autopush_enabled', $queue_params, 'autopush_enabled');
            self::_check_assign_array($value_arr, 'autopush_url', $queue_params, 'autopush_url', false);

            $value_arr['status'] = BigpipeQueueStatus::CREATED;

            // 填充订阅的pipelets
            if (false == isset($queue_params['pipelet']) 
                || false === is_array($queue_params['pipelet'])
                || 0 === count($queue_params['pipelet']))
            {
                BigpipeLog::fatal("[%s:%u][%s][no pipelet in the queue][name:%s]",
                    __FILE__, __LINE__, __FUNCTION__, $queue_params['queue_name']);
                return false;
            }

            $pipelet_count = 0;
            $dest_pipelets = array();
            foreach($queue_params['pipelet'] as $pipelet)
            {
                if (false === is_array($pipelet))
                {
                    BigpipeLog::fatal("[%s:%u][%s][invalid pipelet in the queue params]",
                        __FILE__, __LINE__, __FUNCTION__);
                    return false;
                }

                $dest_pipelet = array();
                self::_check_assign_array($dest_pipelet, 'pipe_name', $pipelet, 'pipe_name', false);
                self::_check_assign_array($dest_pipelet, 'pipelet_id', $pipelet, 'pipelet_id');
                $start_pos = array();
                self::_check_assign_array($start_pos, 'pipelet_msg_id', $pipelet, 'pipelet_msg_id');
                self::_check_assign_array($start_pos, 'seq_id', $pipelet, 'seq_id');
                $dest_pipelet['start_pos'] = $start_pos;
                $dest_pipelet['unsend_window'] = array(); // add empty window
                $dest_pipelets[$pipelet_count] = $dest_pipelet;
                $pipelet_count++;
            }
            $value_arr['pipelets'] = $dest_pipelets;
        }
        catch(Exception $e)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to assign the queue value array][msg:%s]",
                __FILE__, __LINE__, __FUNCTION__, $e->getMessage());
            return false;
        }

        return $value_arr;
    }
    
    /**
     * 将源数组的指定元素赋值给目标数组的指定元素<p>
     * 当源数组指定元素不存在时抛出异常<p>
     * 目标数组指定元素存在时，覆盖该元素。
     * @param array  $dest_array : 目标数组
     * @param string $dest_key   : 目标元素的key
     * @param array  $src_array  : 源数组
     * @param string $src_key    : 源元素的key
     */
    private static function _check_assign_array(&$dest_array, $dest_key, &$src_array, $src_key, $is_numberic = true)
    {
        // 检查源的key是否存在
        if (false === isset($src_array[$src_key]))
        {
            $err_msg = sprintf('key: %s is not set in array', $src_key);
            $e = new Exception($err_msg);
            throw $e;
        } 

        if (true === $is_numberic)
        {
            $dest_array[$dest_key] = (int)$src_array[$src_key];
        }
        else
        {
            $dest_array[$dest_key] = $src_array[$src_key];
        }
    }

    private static $__meta = null;
    private $_inited = null;
} // end of BigpipeQueueAdministrationTools

//////////////////////////////////////////////////////////////////////////////////////////
// todo
// 调试完成以下代码需要移动到独立php文件
class BigpipeQueueUtilConf
{
    /**
     * 初始化时定义配置
     */
    public function __construct()
    {
        // 初始化meta配置
        // todo set zookeeper log file
        $this->meta = array(
                'meta_host'       => '10.218.32.11:2181,10.218.32.20:2181,10.218.32.21:2181,10.218.32.22:2181,10.218.32.23:2181',
                'root_path'       => '/bigpipe_pvt_cluster3',
                'zk_recv_timeout' => 1000,
                'zk_log_file'     => './log/queue.zk.log',
                'zk_log_level'    => 4,
        );

        // 初始化connection配置
        $this->conn = new BigpipeConnectionConf;

        // 初始化queue client配置
        $this->wnd_size = 10;
        $this->rw_timeo = 1000;
        $this->peek_timeo = 60000; // 60s
    }

    /**
     * 从com-configure解析出的array中读取queue client的配置
     */
    public function load($conf_arr)
    {
        // 检查配置完整性
        if (false === isset($conf_arr['meta'])
                || false === isset($conf_arr['queue']))
        {
            BigpipeLog::fatal('[queue client configure][missing mandatory conf node]');
            return false;
        }

        if (false === $this->_load_meta($conf_arr['meta']))
        {
            return false;
        }

        if (false === $this->_load_queue($conf_arr['queue']))
        {
            return false;
        }

        return true;
    }

    /**
     * 从configure array中读取queue的配置
     * @param array $queue_conf : [queue]下的配置节点
     * @return true on success or false on failure
     */
    private function _load_queue($queue_conf)
    {
        if (isset($queue_conf['window_size']))
        {
            $this->wnd_size = $queue_conf['window_size'];
        }

        if (isset($queue_conf['peek_timeo']))
        {
            $this->peek_timeo = $queue_conf['peek_timeo'];
        }

        // 配置read/write timeout
        if (isset($queue_conf['rw_timeo']))
        {
            $this->rw_timeo = $queue_conf['rw_timeo'];
        }

        // connection配置也从queue的配置下读取
        if (false === $this->conn->load($queue_conf))
        {
            BigpipeLog::fatal('[queue client configure][error when configure socket connection]');
            return false;
        }

        return true;
    }

    private function _load_meta($meta_conf)
    {
        // 检查必须的配置项
        if (false === isset($meta_conf['meta_host'])
                || false === isset($meta_conf['root_path'])
                || false === isset($meta_conf['zk_recv_timeout']))
        {
            BigpipeLog::fatal('[queue client configure][missing mandatory meta configure]');
            return false;
        }

        $this->meta = $meta_conf;
        return true;
    }

    /** meta配置文件 */
    public $meta = null;
    /** bigpipe connection配置 */
    public $conn = null;
    /** queue server 窗口大小 */
    public $wnd_size = null;
    /** queue server 读写time out (单位： 毫秒)*/
    public $rw_timeo = null;
    /** queue server peek time out */
    public $peek_timeo = null;
} // end of BigpipeQueueConf
?>
