<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
****************************************************************************/
require_once(dirname(__FILE__).'/ext/sign.php');
require_once(dirname(__FILE__).'/frame/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/frame/bigpipe_utilities.inc.php');
require_once(dirname(__FILE__).'/frame/bigpipe_stomp_frames.inc.php');
require_once(dirname(__FILE__).'/frame/bigpipe_configures.inc.php');
require_once(dirname(__FILE__).'/frame/BigpipeStompAdapter.class.php');
require_once(dirname(__FILE__).'/frame/MetaAgentAdapter.class.php');
require_once(dirname(__FILE__).'/frame/BigpipeMessagePackage.class.php');

/**
 * 生成pipelet_id
 * @author yangzhenyu@baidu.com 
 */
class BigpipePubPartitioner
{
    /**
     * 计算向哪个pipelet发布
     * @param BigpipeMessagePackage $msg_package
     * @param number $partition_num
     * @return pipelet id
     */
    public function get_pipelet_id($msg_package, $partition_num)
    {
        $pipelet_id = rand() % $partition_num;
        return $pipelet_id;
    }
}

/**
 * 用于保存发布结果
 * @author yangzhenyu@baidu.com
 */
class BigpipePubResult
{
    /** 消息发布状态 */
    public $error_no = BigpipeErrorCode::OK;
    /** 消息发布到的pipelet */
    public $pipelet_id = null;
    /** 消息发布后bigpipe为其分配的pipelet内部唯一自增id (start point) */
    public $pipelet_msg_id = null;
    /** 消息在本session中的id */
    public $session_msg_id = null;
} // BigpipePubResult

/**
 * publisher类
 * @author yangzhenyu@baidu.com
 */
class BigpipePublisher
{
    /** default constructor */
    public function __construct()
    {
        $this->_meta_adapter = new MetaAgentAdapter;
        $this->_inited = false;
    }
    
    /** default destructor */
    public function __destruct()
    {
        if (true === $this->_inited)
        {
            // 释放资源
            $this->uninit();
        }
    }

    /**
     * 初始化发布类<p>
     * @param string $pipe_name
     * @param string $token
     * @param BigpipePubPartitioner &$partitioner
     * @param BigpipeConfigure $conf
     */
    public function init($pipe_name, $token, &$partitioner, $conf)
    {
        if (true === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][mulitple init]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        
        $ret = $this->_init($pipe_name, $token, $partitioner, $conf);
        if (!$ret)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to init BigpipePublisher]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        // 通过meta adapter连接meta agent
        if (!$this->_meta_adapter->connect())
        {
            BigpipeLog::warning("[%s:%u][%s][can not connect to meta agent]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 认证
        $this->_num_piplet = $this->_authorize($token);
        if (false === $this->_num_piplet)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to authorize BigpipePublisher]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        $this->_conf->session_level = 0; // 目前init接口只支持0
        $this->_inited = true;
        return true;
    }

    /**
     * 初始化发布类<p>
     * 本接口中合并了对meta-agent的init meta和authorize操作，减少了网络连接带来的性能损失
     * @param string $pipe_name
     * @param string $token
     * @param BigpipePubPartitioner &$partitioner
     * @param BigpipeConfigure $conf
     */
    public function init_ex($pipe_name, $token, &$partitioner, $conf)
    {
        if (true === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][mulitple init]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $ret = $this->_init($pipe_name, $token, $partitioner, $conf);
        if (!$ret)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to init BigpipePublisher]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $ret = $this->_meta_adapter->connect_ex($pipe_name, $token, BStompRoleType::PUBLISHER);
        if (false === $ret)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to connect on meta agent]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        $this->_num_piplet = $ret['num_pipelet'];
        if (1 == $this->_conf->session_level)
        {
            $this->_session = $ret['session'];
            $this->_session_id = $ret['session_id'];
            $this->_session_timestamp = $ret['session_timestamp'];
        }
        $this->_inited = true;
        return true;
    }

    /**
     * @brief: 向primary broker 发送一条消息
     * @param BigpipeMessagePackage $msg
     * @return: BigpipePubResult on success or false on failure
    */
    public function send($msg_package)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::fatal("[%s:%u][%s][publisher is not inited]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        
        $pipelet_id = $this->_partitioner->get_pipelet_id($msg_package, $this->_num_piplet);
        if ($pipelet_id >= $this->_num_piplet || $pipelet_id < 0)
        {
            BigpipeLog::fatal("[%s:%u][%s][invalid pipelet][pipelet_id:%u][max_pipelet_id:%u]",
            __FILE__, __LINE__, __FUNCTION__, $pipelet_id, $this->_num_piplet - 1);
            return false;
        }
        
        $pub = null;
        if (!isset($this->_pub_list[$pipelet_id]))
        {
            $pub = new BigpipePublishTask($this->_pipe_name, $pipelet_id, $this->_session, 
                                          $this->_conf, $this->_meta_adapter);
            // 在pub_list中, 保存pub的引用
            $this->_pub_list[$pipelet_id] = &$pub;
        }
        else
        {
            $pub = &$this->_pub_list[$pipelet_id];
        }
        
        $pub_result = $pub->start();
        if (!$pub_result)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to start publish task][pipe_name:%u][pipelet_id:%u]",
            __FILE__, __LINE__, __FUNCTION__, $this->_pipe_name, $pipelet_id);
        }
        else
        {
            $pub_result = $pub->send($msg_package);
        }
        
        if (false === $pub_result)
        {
            $pub->stop(); // 发送出现错误，停止task（等待下次重启）
        }
        unset($pub); // 主动释放$pub与pub task的联系，否则可能会影响到_pub_list中被引用的元素。
        return $pub_result;
    }

    /**
     * 清空状态
     * @return void type
     */
    public function uninit()
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][multi-uninit]",
            __FILE__, __LINE__, __FUNCTION__);
            return;
        }

        if (1 == $this->_conf->session_level)
        {
            $session_param['session'] = $this->_session;
            $session_param['session_id'] = $this->_session_id;
            $session_param['session_timestamp'] = $this->_session_timestamp;
            $this->_meta_adapter->release_session($session_param);
            $this->_session = null;
            $this->_session_id = null;
            $this->_session_timestamp = null;
        }
        $this->_meta_adapter->uninit();

        // 释放pub list
        $this->_pub_list = null; // task 释放时会主动断开连接
        $this->_pipe_name = null;
        unset($this->_partitioner); // 移除partitioner的引用
        $this->_partitioner = null;
        $this->_conf = null;
        $this->_num_piplet = null;
        $this->_inited = false;
    }
    
    private function _init($pipe_name, $token, &$partitioner, $conf)
    {
        // todo 用is_a check partitioner类型
        if (empty($pipe_name) || empty($token) || empty($partitioner) || empty($conf))
        {
            BigpipeLog::warning("[%s:%u][%s][invalid parameters]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        
        $this->_pipe_name = $pipe_name;
        $this->_partitioner = $partitioner;
        if (empty($conf->meta_conf) || empty($conf->stomp_conf))
        {
            BigpipeLog::warning("[%s:%u][%s][invalid configuration]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        
        // 归位操作
        $conf->stomp_conf->conn_conf->check_frame = false;
        $conf->meta_conf->conn_conf->check_frame = false; // meta agent不做整包校验
        // 根据checksum leve修改meta和stomp中connection配置
        if (BigpipeChecksumLevel::CHECK_FRAME == $conf->checksum_level)
        {
            $conf->stomp_conf->conn_conf->check_frame = true;
        }
        else if (BigpipeChecksumLevel::DISABLE != $conf->checksum_level &&
                 BigpipeChecksumLevel::CHECK_MESSAGE != $conf->checksum_level)
        {
            BigpipeLog::fatal('[%s:%u][%s][invalid checksum level][checksum level:%d]', 
            __FILE__, __LINE__, __FUNCTION__, $conf->checksum_level);
            return false;
        }
        $this->_conf = $conf; // 记录conf传给publish task

        if (false === $this->_meta_adapter->init($conf->meta_conf))
        {
            BigpipeLog::fatal('[%s:%u][%s][fail to init MetaAgentAdapter]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $this->_pub_list = array();

        // init时生成task session id的统一前缀
        // 目前在1.5天内，同一个进程内向一个pipe发布的所有publisher拥有相同的session
        $this->_session = BigpipeUtilities::get_session_id($pipe_name);
        return true;
    }
    
    /**
     * 使用pipe_name和token向meta认证是否可以发布
     * @return 如果认证成功返回可发布的pipelet number, 否则返回false
     */
    private function _authorize($token)
    {
        // todo 测试用pipe有12个pipelet
        $ret = false;
        $author_result = $this->_meta_adapter->authorize($this->_pipe_name, $token, BStompRoleType::PUBLISHER);
        if (false === $author_result)
        {
            BigpipeLog::warning('[%s:%u][%s][authorize message error]',
            __FILE__, __LINE__, __FUNCTION__);
        }
        else if (false == $author_result['authorized'])
        {
            BigpipeLog::warning('[%s:%u][%s][fail to authorize][reason:%s]', 
            __FILE__, __LINE__, __FUNCTION__, $author_result['reason']);
        }
        else
        {
            $ret = $author_result['num_pipelet'];
        }
        return $ret;
    }

    private $_pipe_name = null;
    /**
     *
     * @var BigpipePubPartitioner
     */
    private $_partitioner = null;
    private $_conf = null;
    
    /** 当前pipe拥有的piplet数目, 认证后获得 */
    private $_num_piplet = null;
    private $_meta_adapter = null;
    /** 是否从meta获取session */
    private $_session = null;
    private $_session_id = null;
    private $_session_timestamp = null;

    /**
     * 发布列表，每个元素都对应一个BigpipePublishTask
     * @var array
     */
    private $_pub_list = null;
    private $_inited = false;
} // end of BigpipePublisher

/**
 * 发布任务
 * @author yangzhenyu@baidu.com
 */
class BigpipePublishTask
{
    /**
     * 构造一个publish task
     * @param string $pipe_name
     * @param number $pipelet_id
     * @param BigpipeConf $conf
     * @param BigpipeStompAdapter &$meta_adapter
     */
    public function __construct($pipe_name, $pipelet_id, $session_id, $conf, &$meta_adapter)
    {
        $this->_is_started = false;
        
        $this->_meta_adapter = $meta_adapter;
        $this->_stomp_adapter = new BigpipeStompAdapter($conf->stomp_conf);

        $this->_pipe_name = $pipe_name;
        $this->_pipelet_id = $pipelet_id;
        
        $this->_conn_timeo = $conf->conn_timo;
        $this->_no_dedupe = $conf->no_dedupe;
        $this->_max_fo_cnt = $conf->max_failover_cnt;
        $this->_fo_count = 0;
        // $conf中的checksum level在publisher初始化时已近判断过有效性，这里传入的值一定是正确的。
        $this->_enable_checksum = false;
        if (BigpipeChecksumLevel::CHECK_MESSAGE <= $conf->checksum_level)
        {
            $this->_enable_checksum = true;
        }

        if (0 == $conf->session_level)
        {
            // session level为0，表示使用自动生成的session
            $this->_session_id = sprintf('%s-%u', $session_id, $pipelet_id);
        }
        else 
        {
            $this->_session_id = $session_id;
        }
        $this->_session_msg_id = BigpipeUtilities::get_time_us(); // 设置初始session msg id
    }
    
    /**
     * 停止并析构一个task
     */
    public function __destruct()
    {
        if ($this->_is_started)
        {
            $this->stop();
        }
        unset($this->_meta_adapter); // 引用的变量用unset释放
    }
    
    /**
     * 开始一个发布任务, 并将其连接到可发布的broker
     * @return true on success or false on failure
     */
    public function start()
    {
        if ($this->_is_started)
        {
            BigpipeLog::debug("[task is already started][pipelet:%d]", $this->_pipelet_id);
            return true;
        }
        
        if (null === $this->_meta_adapter)
        {
            BigpipeLog::warning("[start task error][invalid meta_adapter]");
            return false;
        }

        $this->_active();
        // session id存在，我们不能清除其状态。
        // 这里是个trick当_fo_sleep_time = 0时我们会跳过failover中的sleep
        // 开始时的connect不需要sleep
        $this->_fo_sleep_time = 0;
        $this->_is_started = $this->_connect(); // 连接成功才算成功开始一个任务
        
        // start时，预计上次send是失败的
        $this->_last_send_ok = false;
        return $this->_is_started;
    }

    /**
     * 返回Publish Task是否已被启动
     * @return boolean
     */
    public function is_started()
    {
        return $this->_is_started;
    }
    
    /**
     * 停止发布任务
     */
    public function stop()
    {
        if (!$this->_is_started)
        {
            return;
        }
        
        $this->_stomp_adapter->close();
    }

    /**
     * 发布一条消息
     * @param BigpipeMessagePackage $msg_pacakge
     * @return BigpipePubResult on success or false on failure
     */
    public function send($msg_package)
    {
        if (!$this->_is_started)
        {
            BigpipeLog::warning("[connection is not established][pipelet:%d]", $this->_pipelet_id);
            return false;
        }

        // 发送CONNECT
        $cmd = new BStompSendFrame;
        if (!$msg_package->store($cmd->message_body))
        {
            // 包错误，不用考虑failover了
            BigpipeLog::warning("[fail to store message body]");
            return false;
        }

        if (true === $this->_last_send_ok)
        {
            // 当上次发送成功才增加session mesage id
            // 否则message id不变，防止重复发送
            $this->_session_msg_id++;
        }

        $cmd->destination = $this->_get_stripe_name();
        // echo "[stripe name][$cmd->destination]<br>";
        $cmd->no_dedupe = $this->_no_dedupe;
        $cmd->session_id = $this->_session_id;
        $cmd->session_message_id = $this->_session_msg_id;
        $cmd->receipt_id = isset($this->unittest) ? 'fake-receipt-id' : BigpipeUtilities::gen_receipt_id();
        if ($this->_enable_checksum)
        {
            // 发送message包校验
            $cmd->last_checksum = $this->_last_sign;
            $sign = creat_sign_mds64($cmd->message_body); 
            $cmd->cur_checksum = $sign[2]; // 注意函数返回值
            $this->_last_sign = $cmd->cur_checksum;
        }

        // 进入发送流程,
        // 发送失败则进入failover流程，直到failover失败。
        $send_result = false;
        do
        {
            if (false === $this->_stomp_adapter->send($cmd))
            {
                BigpipeLog::warning("[send message error][session:%s][session_msg_id:%u]",
                $cmd->session_id, $cmd->session_message_id);
                continue;
            }

            $send_result = $this->_check_ack($cmd->receipt_id, $send_result);
            if (false === $send_result)
            {
                // ACK失败
                // 不用考虑BMQ_E_COMMAND_DUPLICATEMSG, 去重在broker做,
                // 用户不会收到duplicate返回.
                BigpipeLog::warning("[send message ack error][session:%s][session_msg_id:%u]",
                $cmd->session_id, $cmd->session_message_id);
                continue;
            }
            else
            {
                BigpipeLog::notice("[send message success][pipelet:%u][session:%s][session_msg_id:%u]",
                $this->_pipelet_id, $cmd->session_id, $cmd->session_message_id);
                break; // 发布成功
            }
        } while ($this->_failover());

        if (false !== $send_result)
        {
            $this->_active(); // 清理failover状态
        }
        else
        {
            $this->_last_send_ok = false;
        }
        return $send_result;
    }

    /**
     * 更新meta信息，连接broker
     */
    private function _connect()
    {
        return $this->_failover();
    }

    private function _active()
    {
        $this->_fo_count = 0;
        $this->_fo_sleep_time = BigpipeCommonDefine::INIT_FO_SLEEP_TIME * 1000;
        $this->_last_send_ok = true;
    }

    /**
     * 接收返回消息, 判断发布是否成功
     * @return BigpipePubResult on success or false on failure
     */
    private function _check_ack($receipt_id, &$send_result)
    {
        // 接收CONNECTED
        $res_body = $this->_stomp_adapter->receive();
        if (null === $res_body)
        {
            return false;
        }

        // parse ACK
        $ack = new BStompAckFrame;
        if (!$ack->load($res_body))
        {
            BigpipeLog::warning('[stomp parse ack frame error][cmd_type:%d][err_msg:]',
            $ack->command_type, $ack->last_error_message());
            return false;
        }

        if ($ack->session_message_id != $this->_session_msg_id)
        {
            BigpipeLog::warning('[check session message id error][send:%u][ack:%u]',
            $this->_session_msg_id, $ack->session_message_id);
            return false;
        }

        if ($ack->receipt_id != $receipt_id)
        {
            BigpipeLog::warning('[check receipt id error][send:%u][ack:%u]',
            $receipt_id, $ack->receipt_id);
            return false;
        }

        // 填充result
        $send_result = new BigpipePubResult;
        $send_result->error_no = $ack->status;
        $send_result->pipelet_id = $this->_pipelet_id;
        $send_result->pipelet_msg_id = $ack->topic_message_id;
        $send_result->session_msg_id = $ack->session_message_id;
        return $send_result;
    }

    /**
     * 从meta更新可发布的broker
     * @return primary broker on success or false on failure
     */
    private function _update_meta()
    {
        $broker = $this->_meta_adapter->get_pub_broker($this->_pipe_name, $this->_pipelet_id);
        // 检查broker group的状态
        return $broker;
    }

    /**
     * 更新meta信息, 记录重试次数
     * @return boolean
     */
    private function _failover()
    {
        if ($this->_fo_count >= $this->_max_fo_cnt)
        {
            // 重置failover
            BigpipeLog::fatal("[_failover][can not do more][max_cnt:%d]", $this->_max_fo_cnt);
            $this->_fo_sleep_time = 0;
            $this->_fo_count = 0;
            return false;
        }

        if (0 == $this->_fo_sleep_time)
        {
            // php中只有微秒级的usleep和秒级的sleep
            // 目前只有当start时才会进入这个分支。
            // 这时我们会跳过failover
            $this->_fo_sleep_time = BigpipeCommonDefine::INIT_FO_SLEEP_TIME * 1000;
        }
        else
        {
            usleep($this->_fo_sleep_time);
        }
        // usleep($this->_fo_sleep_time);
        $this->_fo_count++;
        $this->_fo_sleep_time *= 2; // increase failover sleep time
        if ($this->_fo_sleep_time > BigpipeCommonDefine::MAX_FO_SLEEP_TIME)
        {
            // failover sleep time不能无限制增长
            $this->_fo_sleep_time = BigpipeCommonDefine::MAX_FO_SLEEP_TIME;
        }

        // 通过meta跟新stripe
        $broker = $this->_update_meta();
        if (false === $broker)
        {
            BigpipeLog::fatal("[_failover][can not update meta from meta agent]");
            return false;
        }

        // 连接broker
        $pub_dest = array(
                "socket_address" => $broker['ip'],
                "socket_port"    => $broker['port'],
                "socket_timeout" => $this->_conn_timeo,
        );
        $this->_stomp_adapter->set_destination($pub_dest);
        $this->_stomp_adapter->role = BStompRoleType::PUBLISHER;
        $this->_stomp_adapter->topic_name = $broker['stripe'];
        $this->_stomp_adapter->session_id = $this->_session_id; // 发布时session不变
        if ($this->_stomp_adapter->connect())
        {
            BigpipeLog::debug("[Success][connected on broker][ip:%s][port:%u]", $broker['ip'], $broker['port']);
            BigpipeLog::debug('[session message id][%u]', $this->_stomp_adapter->session_message_id);
            $this->_broker = $broker;
            return true;
        }

        return false; // 连接失败
    }

    /**
     * @return string stripe name
     */
    private function _get_stripe_name()
    {
        return $this->_broker['stripe'];
    }

    // 初始化时指定，不会被更改
    private $_pipe_name = null;
    private $_pipelet_id = null;

    private $_fo_count = null;
    private $_fo_sleep_time = null;
    
    // 从文件中读取的配置
    /** 连接broker的超时等待时间 单位：秒 */
    private $_conn_timeo = null;
    /** 是否开启去重: 0 去重; 1 不去重 */
    private $_no_dedupe = null;
    /** 是否为message body生成checksum */
    private $_enable_checksum = null;
    /** 最大重试次数 */
    private $_max_fo_cnt = null;
    
    
    private $_meta_adapter = null;  // 多个task可以共享一个链接, 传引用
    private $_stomp_adapter = null; // 每个task一个链接
    private $_broker = null;

    private $_is_started = false;
    /** 记录最近一次发送的message package的数字签名*/
    private $_last_sign = null;
    private $_session_id = null;
    private $_session_msg_id = null;
    private $_last_send_ok = false;
    
}
?>
