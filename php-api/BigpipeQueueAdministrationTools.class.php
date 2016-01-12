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
 * bigpipe queue�Ĺ�����<p>
 * ʵ���˴���, ����, ֹͣ, ɾ��һ��queue�Ĺ���
 * @author yangzhenyu@baidu.com
 */
final class BigpipeQueueAdministrationTools
{
    /**
     * ����meta��queue�����ã���meta�ϴ���һ���µ�queue
     * @param array $meta_params  : meta����
     * @param array $queue_params : queue������
     * @param array $node_stat    : �����ɹ��󷵻ؽڵ���Ϣ
     * @return true on success or false on failure
     */
    public static function create_queue($meta_params, $queue_params, &$node_stat)
    {
        // �������
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

        // ��һ��queue params��ʹ���Ϊqueue node�ɽ��ո�ʽ
        $queue_node = self::_normalize_queue_params($queue_params);
        if (false === $queue_node)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to normalize queue node]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ����һ�����ֵд��ڵ�
        $full_entry = sprintf('queue/%s', $queue_node['name']);
        if (false === $meta->create_entry($full_entry))
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to create entry under /%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        // set �ڵ�
        if (false === $meta->set_entry($full_entry, $queue_node))
        {
            return false;
        }

        // �ռ��½ڵ��ͳ����Ϣ
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
     * ����һ��queue
     * @param string $name        : name of the queue
     * @param string $token       : token of the queue
     * @param array  $meta_params : meta������Ϣ
     * @param array  $node_stat   : �ɹ��󷵻ص�meta��Ϣ
     * @return boolean
     */
    public static function start_queue($name, $token, $meta_params, &$node_stat)
    {
        // �������
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

        // ��ȡqueue entry��Ϣ
        $full_entry = sprintf('queue/%s', $name);
        $stat = array ('version' => 'for test'); //���ڲ���ʱ����
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
     * ��ͣһ������started��created̬��queue
     * @param string $name        : name of the queue
     * @param string $token       : token of the queue
     * @param array  $meta_params : meta������Ϣ
     * @param array  $node_stat   : �ɹ��󷵻ص�meta��Ϣ
     * @return boolean
     */
    public static function stop_queue($name, $token, $meta_params, &$node_stat)
    {
        // �������
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

        // ��ȡqueue entry��Ϣ
        $full_entry = sprintf('queue/%s', $name);
        $stat = array ('version' => 'for test'); //���ڲ���ʱ����
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
            // ���ı�����״̬��queue node
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
     * ɾ��һ��stop״̬��queue
     * @param string $name        : name of the queue
     * @param string $token       : token of the queue
     * @param array  $meta_params : meta������Ϣ
     * @return boolean
     */
    public static function delete_queue($name, $token, $meta_params)
    {
        // �������
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

        // ��ȡqueue entry��Ϣ
        $full_entry = sprintf('queue/%s', $name);
        $stat = array ('version' => 'for test'); //���ڲ���ʱ����
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
            // ֻɾ��stop״̬��queue node
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

    /** ���±���������testʱ��ʹ�� */
    public static $unittest = false;
    public static $stub_meta = null;

    /**
     * ��ʼ��һ��meta������
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
     * �������queue�Ĳ�����֯��queue node value array
     * @param array $queue_params
     * @return queue node value in array on success or false on failure
     */
    private static function _normalize_queue_params($queue_params)
    {
        $value_arr = array();
        try 
        {
            // queue ��������
            self::_check_assign_array($value_arr, 'name', $queue_params, 'queue_name', false);
            self::_check_assign_array($value_arr, 'token', $queue_params, 'queue_token', false);
            self::_check_assign_array($value_arr, 'msg_timeo', $queue_params, 'msg_timeo');
            self::_check_assign_array($value_arr, 'save_count', $queue_params, 'save_cnt');
            self::_check_assign_array($value_arr, 'save_interval', $queue_params, 'save_interval');
            self::_check_assign_array($value_arr, 'weight', $queue_params, 'queue_weight');
            self::_check_assign_array($value_arr, 'window_cnt', $queue_params, 'window_cnt');
            self::_check_assign_array($value_arr, 'window_size', $queue_params, 'window_size');
            // ����4����Ϊselector������meta��
            self::_check_assign_array($value_arr, 'selector_enabled', $queue_params, 'selector_enabled');
            self::_check_assign_array($value_arr, 'selector_so_path', $queue_params, 'selector_so_path', false);
            self::_check_assign_array($value_arr, 'selector_conf_path', $queue_params, 'selector_conf_path', false);
            self::_check_assign_array($value_arr, 'selector_conf_file', $queue_params, 'selector_conf_file', false);
            // ����2����Ϊ���Ͷ���������meta��
            self::_check_assign_array($value_arr, 'autopush_enabled', $queue_params, 'autopush_enabled');
            self::_check_assign_array($value_arr, 'autopush_url', $queue_params, 'autopush_url', false);

            $value_arr['status'] = BigpipeQueueStatus::CREATED;

            // ��䶩�ĵ�pipelets
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
     * ��Դ�����ָ��Ԫ�ظ�ֵ��Ŀ�������ָ��Ԫ��<p>
     * ��Դ����ָ��Ԫ�ز�����ʱ�׳��쳣<p>
     * Ŀ������ָ��Ԫ�ش���ʱ�����Ǹ�Ԫ�ء�
     * @param array  $dest_array : Ŀ������
     * @param string $dest_key   : Ŀ��Ԫ�ص�key
     * @param array  $src_array  : Դ����
     * @param string $src_key    : ԴԪ�ص�key
     */
    private static function _check_assign_array(&$dest_array, $dest_key, &$src_array, $src_key, $is_numberic = true)
    {
        // ���Դ��key�Ƿ����
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
// ����������´�����Ҫ�ƶ�������php�ļ�
class BigpipeQueueUtilConf
{
    /**
     * ��ʼ��ʱ��������
     */
    public function __construct()
    {
        // ��ʼ��meta����
        // todo set zookeeper log file
        $this->meta = array(
                'meta_host'       => '10.218.32.11:2181,10.218.32.20:2181,10.218.32.21:2181,10.218.32.22:2181,10.218.32.23:2181',
                'root_path'       => '/bigpipe_pvt_cluster3',
                'zk_recv_timeout' => 1000,
                'zk_log_file'     => './log/queue.zk.log',
                'zk_log_level'    => 4,
        );

        // ��ʼ��connection����
        $this->conn = new BigpipeConnectionConf;

        // ��ʼ��queue client����
        $this->wnd_size = 10;
        $this->rw_timeo = 1000;
        $this->peek_timeo = 60000; // 60s
    }

    /**
     * ��com-configure��������array�ж�ȡqueue client������
     */
    public function load($conf_arr)
    {
        // �������������
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
     * ��configure array�ж�ȡqueue������
     * @param array $queue_conf : [queue]�µ����ýڵ�
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

        // ����read/write timeout
        if (isset($queue_conf['rw_timeo']))
        {
            $this->rw_timeo = $queue_conf['rw_timeo'];
        }

        // connection����Ҳ��queue�������¶�ȡ
        if (false === $this->conn->load($queue_conf))
        {
            BigpipeLog::fatal('[queue client configure][error when configure socket connection]');
            return false;
        }

        return true;
    }

    private function _load_meta($meta_conf)
    {
        // �������������
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

    /** meta�����ļ� */
    public $meta = null;
    /** bigpipe connection���� */
    public $conn = null;
    /** queue server ���ڴ�С */
    public $wnd_size = null;
    /** queue server ��дtime out (��λ�� ����)*/
    public $rw_timeo = null;
    /** queue server peek time out */
    public $peek_timeo = null;
} // end of BigpipeQueueConf
?>
