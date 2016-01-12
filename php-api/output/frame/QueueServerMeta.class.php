<?php
/**==========================================================================
 *
 * QueueServerMeta.class.php - INF / DS / BIGPIPE
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 * Created on 2012-12-14 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/BigpipeLog.class.php');
//require_once(dirname(__FILE__).'/BigpipeMetaManager.class.php');

/**
 * 封装了meta(zookeeper), 提供了从meta中获取queue server配置的方法
 * @author yangzhenyu@baidu.com
 */
class QueueServerMeta
{
    /**
     * 初始化并连接zookeeper,
     * 通过name和token认证用户权限
     * @param array $meta_params : zookeeper的参数集合
     * @return true on success or false on failure
     */
    public function init($name, $token, $meta_params)
    {
        // 检查name和token
        if (empty($name) || empty($token))
        {
            BigpipeLog::fatal("[init][miss queue name or token]");
            return false;
        }
        $this->_queue_name = $name;
        $this->_queue_token = $token;

        // 通过conf准备meta
        // 检查meta配置
        if (!isset($meta_params['meta_host']) || !isset($meta_params['root_path']))
        {
            BigpipeLog::fatal("[init][miss meta host or root path]");
            return false;
        }
        $this->_meta_host = $meta_params['meta_host'];
        $this->_root_path = $meta_params['root_path'];
        $this->_recv_timeo = $meta_params['zk_recv_timeout'];
        $this->_zk = new Zookeeper;
        // 实例并初始化meta manager
                /*
        $this->_meta_manager = new BigpipeMetaManager;
        if (false === $this->_meta_manager->init($meta_params))
        {
            BigpipeLog::warning("[init][fail to init meta manager]");
            return false;
                } 
                 */ 
        $this->_clean_meta_info();

        // 初始化connection
        if (false === $this->_zk->connect($this->_meta_host, null, $this->_recv_timeo))
        {
            BigpipeLog::warning("[init][fail to connect to meta][host:%s]", $this->_meta_host);
            return false;
        }

        //置零update次数
        //        $this->_update_cnt = 0;

        $this->_inited = true;
        // init中不用从meta获取信息, 让用户主动调用update获取信息
        //         if (false === $this->update())
        //         {
        //             $this->_inited = false;
        //             return false;
        //         }

        return true;
    }

    /**
     * 反初始化
     * @return void type
     */
    public function uninit()
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[duplcate uninit ZookeeperWrapper]");
            return;
        }
/*
        if (null != $this->_meta_manager)
        {
            $this->_meta_manager->uninit();
        }
 */
        // do nothing, but clean all the variables
        $this->_zk = null;
        //        $this->_meta_manager = null;
        $this->_meta_host = null;
        $this->_root_path = null;
        $this->_recv_timeo = null;
        $this->_inited = null;

        $this->_queue_name = null;
        $this->_queue_token = null;

        $this->_clean_meta_info();
    }

    /**
     * 尝试从zookeeper更新queue meta节点
     */
    public function update()
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[update][try to update uninited meta]");
            return false;
        }

        //check queue的状态
/*
        do
        {
            if(0 === $this->_update_cnt)
            {
                //客户端调用refresh时，check queue的状态，休眠100ms
                usleep(100000);
            }
            else
            {
                //queue处于非start状态，update不断尝试获取queue的状态，直到等到start，轮询间隔5s
                BigpipeLog::warning("[update][queue is not running]");
                sleep(5);
            }

            if (false === $this->_check_queue_status())
            {
                BigpipeLog::warning("[update][check queue status fail]");
                return false;
            }

            $this->_update_cnt++;
        } while (false === $this->_queue_status);

        //置零update次数
        $this->_update_cnt = 0;
 */
        // connect成功时的返回值是null    bugfix:check connection first
        if(Zookeeper::CONNECTED_STATE != $this->_zk->getState())
        {
            if (false === $this->_zk->connect($this->_meta_host, null, $this->_recv_timeo))
            {
                BigpipeLog::warning("[update][fail to connect to meta][host:%s]", $this->_meta_host);
                return false;
            }
        }

        // 更新queue server信息
        if (false === $this->_update_queue_server_info())
        {
            BigpipeLog::warning("[update][fail to update queue server inforamtion in meta]");
            return false;
        }

        // 成功更新节点
        return true;
    }

    /**
     * get queue server address
     */
    public function queue_address()
    {
        return $this->_queue_address;
    }

    /**
     * get name of the queue server
     * @return string $_queue_name
     */
    public function queue_name()
    {
        return $this->_queue_name;
    }

    /**
     * get token of the queue server
     * @return string $_queue_token
     */
    public function token()
    {
        return $this->_queue_token;
    }

    /**
     * 清除从meta中得到的信息
     */
    private function _clean_meta_info()
    {
        $this->_queue_address = false;
        //        $this->_queue_status = false;
    }

    /**
     * 通过meta更新queue server的信息
     * @return false on failure or true on success
     */
    private function _update_queue_server_info()
    {
        // 检查meta节点
        $reg_path = $this->_get_register_path();
        $meta_path = $this->_get_meta_path();
        if (false === $this->_exists($reg_path) || false === $this->_exists($meta_path))
        {
            return false;
        }

        // 从注册表中获得新的queue server地址
        $addr_str = $this->_zk->get($reg_path);
        if (null == $addr_str)
        {
            BigpipeLog::warning("[path deleted at last moment][%s]", $reg_path);
                return false;
        }
        $addr = explode(':', $addr_str);
        if (count($addr) != 2)
        {
            BigpipeLog::warning("[invalid queue server address][%s]", $addr_str);
            return false;
        }

        $this->_queue_address = array(
            'socket_address' => $addr[0],
            'socket_port'    => (int)$addr[1],
        );

        return true;
    }

    /**
     * 获取queue server的注册信息在meta中的路径
     */
    private function _get_register_path()
    {
        return ($this->_root_path . QueueServerMeta::REGISTER_FOLDER . $this->_queue_name);
    }

    /**
     * 获取queue server在meta中的路径
     */
    private function _get_meta_path()
    {
        return ($this->_root_path . QueueServerMeta::META_FOLDER . $this->_queue_name);
    }

    /**
     * 检查queue的状态
     */
/*
        private function _check_queue_status()
    {

        //_meta_manager reconnect:按需重连meta
        if (false === $this->_meta_manager->reconnect())
        {
            BigpipeLog::warning("[update][_meta_manager reconnect fail]");
            return false;
        }

        // 读取queue entry信息
        $full_entry = sprintf('queue/%s', $this->_queue_name);
        $stat = array ('version' => 'for test'); //仅在测试时有用
        $queue_info = $this->_meta_manager->get_entry($full_entry, $stat);
        if (false === $queue_info)
        {
            BigpipeLog::fatal("[%s:%u][%s][can not access to queue under /%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            return false;
        }

        $status = $queue_info['status'];
        if (BigpipeQueueStatus::STARTED != $status)
        {
            BigpipeLog::warning("[%s:%u][%s][queue is not runing now][entry:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_entry);
            $this->_queue_status = false;
        }
        else
        {
            $this->_queue_status = true;
        }

        return true;
    }
 */

    /**
     * 检查路径下的node是否存在，如不存在打印错误日志
     * @param string $node_path : node path in meta
     * @return true on success or false on failure
     */
    private function _exists($node_path)
    {
        if (false === $this->_zk->exists($node_path))
        {
            BigpipeLog::warning("[invalid meta node][%s]", $node_path);
            return false;
        }
        return true;
    }

    /** 初始化标志 */
    private $_inited = false;

    /** zookeeper实例 */
    private $_zk = null;
    //private $_meta_manager = null;
    private $_meta_host = null;
    private $_root_path = null;
    private $_recv_timeo = null;

    private $_queue_name = null;
    private $_queue_token = null;

    /**
     * 从meta中读取的queue server的address <p>
     * 并根据connection要求, 被解析为数组
     * @var array("socket_address" => "x.x.x.x", "socket_port" => 9527)
     */
    private $_queue_address = null;

    //    private $_queue_status = null;
    //    private $_update_cnt = null;

    const REGISTER_FOLDER = '/_register/';
    const META_FOLDER     = '/meta/queue/';
} // end of QueueServerMeta

?>
