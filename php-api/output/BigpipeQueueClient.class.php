<?php
/**==========================================================================
 *
 * BigpipeQueueClient.class.php - INF / DS / BIGPIPE
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 * Created on 2012-12-14 by YANG ZHENYU (yangzhenyu@baidu.com)
 *
 * --------------------------------------------------------------------------
 *
 * Description
 *     Queue Client API
 *
 * --------------------------------------------------------------------------
 *
 * Change Log
 *
 *
 ==========================================================================**/
require_once(dirname(__FILE__).'/idl/queue_pack.idl.php');
require_once(dirname(__FILE__).'/frame/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/frame/bigpipe_configures.inc.php');
require_once(dirname(__FILE__).'/frame/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/frame/BigpipeConnection.class.php');
require_once(dirname(__FILE__).'/frame/QueueServerMeta.class.php');

/**
 * queue mesage 消息格式
 */
class BigpipeQueueMessage
{
    /**
     * default constructor
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * 重置queue message包
     */
    public function reset()
    {
        $this->pipe_name = null;
        $this->pipelet_id = 0;
        $this->pipelet_msg_id = 0;
        $this->seq_id = 0;
        $this->message_body = 0;
        $this->cur_srvtime = 0;
        $this->timeout = 0;
        $this->msg_flag = 0;
    }

    public $pipe_name = null;
    public $pipelet_id = null;
    public $pipelet_msg_id = null;
    public $seq_id = null;
    public $message_body = null;
    public $cur_srvtime = null;
    public $timeout = null;
    public $msg_flag = null;
} // end of BigpipeQueueMessage

/**
 * bigpipe queue server客户端
 */
class BigpipeQueueClient
{
    public function __destruct()
    {
        if (true === $this->_inited)
        {
            $this->uninit();
        }
    }

    /**
     * 初始化queue server client
     * @param string $name : queue name
     * @param string $token: token of the queue (for authorize)
     * @param
     * @return true on success or false on failure
     */
    public function init($name, $token, $conf)
    {
        if (true === $this->_inited)
        {
            BigpipeLog::fatal("[%s:%u][%s][queue server client is inited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 初始化
        if (false === $this->_init($name, $token, $conf))
        {
            BigpipeLog::fatal("[init][fatal error]");
            return false;
        }

        $this->_inited = true;
        $this->_base_srvtime = 0;
        $this->_base_loctime = 0;
        $this->_api_version = "qclient api_1 by loopwizard";
        return true;
    }

    /**
     * 向queue server发送接收请求(仅在第一次peek, 或failover时发送请求), 并等待消息到达
     * @param int64_t $timo_ms 等待超时（ms）
     * @return error code
     */
    public function peek($timo_ms)
    {
        if (!$this->_inited)
        {
            BigpipeLog::fatal("[peek][queue server client is not inited]");
            return BigpipeErrorCode::UNINITED;
        }

        if (!$this->_subscribed)
        {
            // 发现还没有订阅从queue server订阅数据时, 尝试发起订阅
            if (!$this->refresh())
            {
                return BigpipeErrorCode::ERROR_SUBSCRIBE;
            }
        }

        return $this->_peek($timo_ms);
    }

    /**
     * 接收消息
     * @return 接收成功返回 一条BigpipeQueueMessage类型的消息; 接收失败, 返回false
     */
    public function receive()
    {
        if (!$this->_inited)
        {
            BigpipeLog::fatal("[receive][queue client is not inited]");
            return false;
        }

        if (!$this->_subscribed)
        {
            // 没有订阅
            BigpipeLog::warning('[receive from unsbscribed queue][name:%s]', $this->_meta->queue_name());
            return false;
        }
        return $this->_receive();
    }

    /**
     * 刷新连接信息 (receive失败后调用)
     * @return true on success or false on failure
     */
    public function refresh()
    {
        //线程安全
        usleep($this->_sleep_timeo);

        // refresh的时侯清零基准服务器和本地时间
        $this->_base_srvtime = 0;
        $this->_base_loctime = 0;

        if (false === $this->_inited)
        {
            BigpipeLog::fatal("[%s:%u][%s][client is not inited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 关闭连接, 取消原有订阅
        if (true === $this->_subscribed)
        {
            $this->_disconnect();
        }

        if (false === $this->_connect())
        {
            BigpipeLog::warning('[refresh][fail to connect to queue server]');
            return false; // 连接失败
        }

        if (false === $this->_subscribe())
        {
            BigpipeLog::warning('[refresh][fail to subscribe from queue server]');
            $this->_disconnect();
            return false;
        }
        return true;
    }

    /**
     * 通知queue server已接收到message
     * @param BigpipeQueueMessage $msg : 可以没有message_body, 
     *                            但是结构中的其余信息必须填写完整,
     *                            $is_slow_cli :
     *                            标记是否是慢速消费客户端，默认是快速消费客户端
     * @return true on success or false on failure
     */
    public function ack($msg,$is_slow_cli=false)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::fatal("[%s:%u][%s][client is not inited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 检查参数
        if (empty($msg->pipe_name))
        {
            return false;
        }

        //检查消息超时
        if ($this->_check_timeout($msg))
        {
            BigpipeLog::warning("[ack][message time out]");
            return false;
        }

        // fill in package
        $ret = false;
        $pkg = false;
        try
        {
            $ack = new idl_queue_ack_t;
            if(false === $is_slow_cli)
            {
                //快速消费端，回复真实ack（让server推送真实数据）
                $ack->setcmd_no(BigpipeQueueSvrCmdType::ACK_QUEUE_TRUE_DATA);
            }
            else
            {   
                //慢速消费端，回复虚假ack（让server推送虚假数据）
                $ack->setcmd_no(BigpipeQueueSvrCmdType::ACK_QUEUE_FAKE_DATA);
            }
            $ack->setqueue_name($this->_meta->queue_name());
            $ack->setpipe_name($msg->pipe_name);
            $ack->setpipelet_id($msg->pipelet_id);
            $ack->setpipelet_msg_id($msg->pipelet_msg_id);
            $ack->setseq_id($msg->seq_id);

            $ack_arr = array();
            $ack->save($ack_arr);
            $pkg = mc_pack_array2pack($ack_arr);
        }
        catch(ErrorException $e)
        {
            BigpipeLog::warning('[ack][error idl package][%s]', $e->getMessage());
        }

        if (false != $pkg)
        {
            // 发送package
            $ret = $this->_connection->send($pkg, strlen($pkg));
            if (true === $ret)
            {
                BigpipeLog::notice("[ack][ack msg][pipe_name:%s] [pipelet_id:%d] [pipelet_msg_id:%d] [seq_id:%d]",
                    $msg->pipe_name, $msg->pipelet_id, $msg->pipelet_msg_id, $msg->seq_id);
            }
        }
        return $ret;
    }

    public function uninit()
    {
        if (false === $this->_inited)
        {
            return;
        }

        $this->_disconnect();
    }

    private function _init($name, $token, $conf)
    {
        // 检查name和token是否存在
        if (empty($name) || empty($token))
        {
            BigpipeLog::fatal("[_init][miss queue name or queue token]");
            return false;
        }

        // 初始化queue server meta
        $this->_meta = (isset($this->unittest)) ? $this->stub_meta : new QueueServerMeta;
        if (false === $this->_meta->init($name, $token, $conf->meta))
        {
            BigpipeLog::fatal("[_init][fail to init meta]");
            return false;
        }

        // 初始化bigpipe connection
        $this->_connection = new BigpipeConnection($conf->conn);
        $this->_wnd_size = $conf->wnd_size;
        $this->_rw_timeo = $conf->rw_timeo;
        $this->_peek_timeo = $conf->peek_timeo;
        $this->_delay_ratio = $conf->delay_ratio;
        $this->_sleep_timeo = $conf->sleep_timeo;
    }

    /**
     * 刷新queue server meta信息, 向queue server重新发起连接
     * @return true on success or false on failure
     */
    private function _connect()
    {
        if (true == $this->_subscribed)
        {
            // 先尝试取消订阅, 但是不用考虑错误 (因为failover中有错误是常态)
            $this->_disconnect();
            $this->_subscribed = false;
        }

        // 从meta更新queue server信息
        if (false === $this->_meta->update())
        {
            BigpipeLog::warning("[_connect][can not update meta]");
            return false;
        }

        $queue_addr = $this->_meta->queue_address();
        if (false === $queue_addr)
        {
            BigpipeLog::warning("[_connect][miss queue server address]");
            return false;
        }

        // try to connect to queue server
        $sub_dest = array(
            "socket_address" => $queue_addr['socket_address'],
            "socket_port"    => $queue_addr['socket_port'],
            "socket_timeout" => $this->_rw_timeo,
        );
        $this->_connection->set_destinations(array($sub_dest));
		$is_ok = false;
        $is_ok = $this->_connection->create_connection();
        if (true === $is_ok)
        {
            BigpipeLog::debug("[_connect][succeed connect on the queue server][ip:%s[port:%u][timeo:%u]",
                $queue_addr['socket_address'], $queue_addr['socket_port'], $this->_rw_timeo);
        }
        return $is_ok;
    }

    /**
     * 等待read buffer来数据
     * @return number error code
     */
    private function _peek($timeo_ms)
    {
        $ret = $this->_connection->is_readable($timeo_ms);
        if (BigpipeErrorCode::READABLE == $ret)
        {
            // 有数据, 重置peek计时
            $this->_peek_time = 0;
            $ret = BigpipeErrorCode::READABLE;
        }
        else if (BigpipeErrorCode::TIMEOUT == $ret)
        {
            // 处理timeout
            $this->_peek_time += $timeo_ms;
            if ($this->_peek_time > $this->_peek_timeo
                && BigpipeCommonDefine::NO_PEEK_TIMEOUT !== $this->_peek_timeo)
            {
                // 返回time out
                BigpipeLog::warning('[peek time out][peek timeo:%u][max peek timeo:%u]',
                    $timeo_ms, $this->_peek_timeo);
                $this->_peek_time = 0;
                $ret = BigpipeErrorCode::PEEK_TIMEOUT;
            }
            else
            {
                $ret = BigpipeErrorCode::UNREADABLE;
            }
        }
        else
        {
            // 其它错误提示
            BigpipeLog::warning('[fail in peek][ret:%d]', $ret);
            $ret = BigpipeErrorCode::PEEK_ERROR;
        }
        return $ret;
    }

    /**
     * 从queue server订阅数据
     * @return bool
     */
    private function _subscribe()
    {
        // 检查参数
        if (true === $this->_subscribed)
        {
            // 为了防止错误编码导致的重复订阅，当重复订阅时，返回false
            BigpipeLog::warning('[_subscribe][client has already subscribed a queue]');
            return false;
        }

        // fill in package
        $ret = false;
        $pkg = false;
        try
        {
            $req = new idl_queue_req_t();
            $req->setcmd_no(BigpipeQueueSvrCmdType::REQ_QUEUE_DATA);
            $req->setqueue_name($this->_meta->queue_name());
            $req->settoken($this->_meta->token());
            $req->setwindow_size($this->_wnd_size);
            $req->setapi_version($this->_api_version);
            $req_arr = array();
            $req->save($req_arr);
            $pkg = mc_pack_array2pack($req_arr);
        }
        catch(ErrorException $e)
        {
            BigpipeLog::warning('[_subscribe][error idl package][%s]', $e->getMessage());
        }

        if (false != $pkg)
        {
            // 发送package
            $ret = $this->_connection->send($pkg, strlen($pkg));
            if (true === $ret)
            {
                BigpipeLog::notice("[_subscribe][succeed subscribe from the queue][queue name:%s]",
                    $this->_meta->queue_name());
                $this->_subscribed = true;
            }
        }
        return $ret;
    }

    /**
     * 从read buffer读取一个message package
     * @return false on failure or BigpageQueueMessage on success
     */
    private function _receive()
    {
        $msg = false;
        do
    {
        $res_body = $this->_connection->receive();
        if (null === $res_body)
        {
            BigpipeLog::warning('[_receive][empty data package]');
            break;
        }
        // 接收成功，读取数据
        $pkg_data = @mc_pack_pack2array($res_body);
        if (false === $pkg_data)
        {
            BigpipeLog::warning('[_receive][unknown data package format][%s]', $res_body);
            break;
        }

        try
        {
            // 成功读取数据并开始解包
            $pkg = new idl_queue_data_t;
            $pkg->load($pkg_data);
            // check result of the data package
            if(BigpipeErrorCode::OK != $pkg->geterr_no())
            {
                if(true === $pkg->haserr_msg())
                {
                    BigpipeLog::warning('[_receive][error package][%s][%d]', $pkg->geterr_msg(), $pkg->geterr_no());
                }
                break;
            }
            else
            {
                // 接收成功, 填充message
                $msg = new BigpipeQueueMessage;
                $msg->pipe_name = $pkg->getpipe_name();
                $msg->pipelet_id = $pkg->getpipelet_id();
                $msg->pipelet_msg_id = $pkg->getpipelet_msg_id();
                $msg->seq_id = $pkg->getseq_id();
                $msg->message_body = $pkg->getmsg_body();
                $msg->cur_srvtime = $pkg->getsrvtime();  //服务器时间戳
                $msg->timeout = $pkg->gettimeout();      //获取超时时间
                $msg->msg_flag = $pkg->getmsg_flag();    //获取消息类型

                BigpipeLog::notice("[_receive][recv msg][pipe_name:%s] [pipelet_id:%d] [pipelet_msg_id:%d] [seq_id:%d] [len:%u] [srvtime:%d] [timeout:%d] [msg_flag:%d]",
                    $msg->pipe_name, $msg->pipelet_id, $msg->pipelet_msg_id,
                    $msg->seq_id, strlen($msg->message_body), $msg->cur_srvtime, $msg->timeout, $msg->msg_flag);
            }
            // 拦截fake message，代替客户端回复真实的ack,等待server回真实消息(假消息换真消息)，即使失败，最糟糕的情形也只是客户端refresh一次
            if(1 === $msg->msg_flag)
            {
                $msg = $this->_wait_real_msg($msg);
            }
        }
        catch(ErrorException $e)
        {
            // message包问题
            BigpipeLog::warning('[_receive][load package error][%s]', $e->getMessage());
        }
    } while (false);

        return $msg;
    }

    /**
     * 关闭socket连接, 清除订阅状态
     * @return void type
     */
    private function _disconnect()
    {
        $this->_connection->close();
        $this->_subscribed = false;
    }


    /**
     * 检查消息是否超时
     * @return true on timeout
     */
    private function _check_timeout($msg)
    {
        $cur_machine_time = @gettimeofday();
        $cur_loctime = $cur_machine_time['sec']*1000000+$cur_machine_time['usec'];

        //first blood!建立连接后，收到第一条消息，进行基准时间的设定
        if(0 === $this->_base_srvtime)
        {
            //更新基准消息的本地时间和服务器时间
            $this->_base_loctime = $cur_loctime;
            $this->_base_srvtime = $msg->cur_srvtime;
            return false;
        }

        //客户端判定消息已超时
        if($cur_loctime - $this->_base_loctime - ($msg->cur_srvtime - $this->_base_srvtime) > ($msg->timeout)*$this->_delay_ratio*1000000)
        {
            BigpipeLog::warning('[_check_timeout][msg timeout,will not send ack]');
            return true;
        }

        return false;
    }

    /**
     * 替客户端回复真实的ack,等待server回真实消息
     * @return void type
     */
    private function _wait_real_msg($msg)
    {

        //回复ack（快速消费客户端）
        if (false === $this->ack($msg))
        {
            return false;
        }

        //peek+receive
        $peek = 0;
        $msg = false;
        while($peek < 3)
        {
            $pret = $this->peek(300);
            if (BigpipeErrorCode::READABLE == $pret)
            {
                $msg = $this->receive();
                break;
            }
            else if (BigpipeErrorCode::UNREADABLE == $pret)
            {
                $peek++;
            }
            else
            {
                BigpipeLog::warning('[_wait_real_msg][peek error]');
                return false;
            }
        }

        return $msg;
    }

    /** QueueServerMeta实例 */
    private $_meta = null;
    ///** meta的配置 */
    //private $_meta_conf = null;

    /** 标识client是否已经被初始化 */
    private $_inited = false;
    /** 标识client是否已经向queue server订阅消息 */
    private $_subscribed = false;

    /**
     * 与queue server的socket connection
     * @var: BigpipeConnection
     */
    private $_connection = null;
    /** 已经peek计时 (单位: 毫秒) */
    private $_peek_time = null;
    /** peek的超时时间 (单位: 毫秒) */
    private $_peek_timeo = null;

    /** 指定queue在server中的消息接收窗的大小*/
    private $_wnd_size = null;
    /** 指定client socket connection的读写 time out (单位：毫秒) */
    private $_rw_timeo = null;
    /** 接收到的第一条消息的服务器时间                            */
    private $_base_srvtime = null;
    /** 接收到的第一条消息的本地时间               */
    private $_base_loctime = null;
    /** 消息延时本地汇率，0-1，建议0.8            */
    private $_delay_ratio = null;
    /** api 版本号  						   */
    private $_api_version = null;
    /** refresh的睡眠时间 (单位: 毫秒)           */
    private $_sleep_timeo = null;

    //     /** 发起连接失败, 最大尝试重新发起连接的次数 */
    //     private $max_try_count = null;
    //     /** 每次连接失败后, 睡眠的时间 (防止queue server失效时, 多client频繁尝试重连) */
    //     private $conn_wait_time = null;

    //     const FAILOVER_BASE_WAIT_TIME = 100000; /** wait 100ms */

} // end of BigpipeQueueClient
/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
