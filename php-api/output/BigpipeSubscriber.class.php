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
require_once(dirname(__FILE__).'/frame/BigpipeStompAdapter.class.php');
require_once(dirname(__FILE__).'/frame/MetaAgentAdapter.class.php');
require_once(dirname(__FILE__).'/frame/BigpipeMessagePackage.class.php');

/**
 * bigpipe 订阅接口
 * @author yangzhenyu@baidu.com
*/
class BigpipeSubscriber
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
            $this->uninit();
        }
    }

    /**
     * @brief: 初始化订阅类
     * @param string $pipe_name
     * @param string $token
     * @param uint32_t $pipelet_id
     * @param int64_t  $start_point
     * @param BigpipeConf $conf
     * @return true on success or false on failure
     */
    public function init($pipe_name, $token, $pipelet_id, $start_point, $conf)
    {
        if ($this->_inited)
        {
            // 不管什么情况，试图初始化一个已被初始化的subscribe都是错误的
            BigpipeLog::fatal("[%s:%u][%s][mulitple init]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 初始化
        if (!$this->_init($pipe_name, $token, $pipelet_id, $start_point, $conf))
        {
            return false;
        }

        // 接入认证
        $this->_num_pipelet = $this->_authorize($token);
        if (false === $this->_num_pipelet)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to authorize BigpipeSubscriber]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 检查piplet id
        if ($pipelet_id < 0 || $pipelet_id >= $this->_num_pipelet)
        {
            BigpipeLog::fatal("[%s:%u][%s][invalid pipelet id][id:%d][max id:%d]",
            __FILE__, __LINE__, __FUNCTION__,
            $pipelet_id, $this->_num_pipelet - 1);
            return false;
        }
        $this->_inited = true;
        return true;
    }

    /**
     * @brief: 等待消息到达
     * @param int64_t $timo_ms 等待超时（ms）
     * @return error code
     */
    public function peek($timo_ms)
    {
        if (!$this->_inited)
        {
            BigpipeLog::fatal("[%s:%u][%s][subscriber is not inited]",
            __FILE__, __LINE__, __FUNCTION__);
            return BigpipeErrorCode::UNINITED;
        }

        // 订阅发布
        if (!$this->_is_subscribed)
        {
            // 没有订阅时尝试发起订阅
            $this->_fo_sleep_time = 0; // 使用0,表示failover中不sleep
            if (!$this->_flush_subscribe())
            {
                return BigpipeErrorCode::ERROR_SUBSCRIBE;
            }
        }

        if (!$this->_package->is_empty())
        {
            return BigpipeErrorCode::READABLE;
        }

        return $this->_stomp_adapter->peek($timo_ms);
    }

    /**
     * @brief: 接收消息
     * @return 接收成功返回BigpipeMsgPack $msg; 接收失败, 返回false
     */
    public function receive()
    {
        if (!$this->_inited)
        {
            BigpipeLog::fatal("[%s:%u][%s][subscriber is not inited]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (!$this->_is_subscribed)
        {
            // 没有订阅
            BigpipeLog::warning('[%s:%u][%s][receive from unsbscribed stripe]', 
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $msg = $this->_package->pop();
        if (false === $msg)
        {
            // 当前package为空, 接收一条消息
            $msg = $this->_receive();
        }

        return $msg;
    }
     
    /**
     * 释放停止订阅，释放连接
     */
    public function uninit()
    {
        if (!$this->_inited)
        {
            return;
        }

        if ($this->_is_subscribed)
        {
            $this->_unsubscribe(); // 结束订阅
        }

        // 断开stomp
        $this->_stomp_adapter->close();
        $this->_stomp_adapter = null; // stomp adapter 在init时被new出来

        // 断开meta
        $this->_meta_adapter->close();

        // 清空状态
        $this->_is_subscribed = false;
        $this->_enable_checksum = true;
        $this->_package = null;
        $this->_pipelet_msg_id = null;
        $this->_max_fo_cnt = 0;
        $this->_fo_count = 0;
        $this->_stripe = null;
        $this->_brokers = null;
        $this->_inited = false;
    }

    /**
     * 内部实现初始化subscriber成员的功能
     * @return true on success or false on failure
     */
    private function _init($pipe_name, $token, $pipelet_id, $start_point, $conf)
    {
        // todo 仔细检查参数

        if (!SubscribeStartPoint::is_valid($start_point))
        {
            BigpipeLog::fatal("[%s:%u][%s][invalid start point][val:%d]",
            __FILE__, __LINE__, __FUNCTION__, $start_point);
            return false;
        }
        else
        {
            $this->_start_point = $start_point;
            $this->_pipelet_msg_id = $start_point;
        }

        $this->_pipe_name = $pipe_name;
        $this->_token = $token;
        $this->_pipelet_id = $pipelet_id;

        // 归位操作
        $conf->stomp_conf->conn_conf->check_frame = false;
        $conf->meta_conf->conn_conf->check_frame = false; // meta agent不做整包校验
        if (false === $this->_meta_adapter->init($conf->meta_conf))
        {
            BigpipeLog::fatal('[%s:%u][%s][fail to init MetaAgentAdapter]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        // 根据checksum leve修改meta和stomp中connection配置
        if (BigpipeChecksumLevel::DISABLE == $conf->checksum_level)
        {
            $this->_enable_checksum = false;
        }
        else if (BigpipeChecksumLevel::CHECK_FRAME == $conf->checksum_level ||
                BigpipeChecksumLevel::CHECK_MESSAGE == $conf->checksum_level)
        {
            $this->_enable_checksum = true;
            if (BigpipeChecksumLevel::CHECK_FRAME == $conf->checksum_level)
            {
                $conf->stomp_conf->conn_conf->check_frame = true;
            }
        }
        else
        {
            BigpipeLog::fatal('[%s:%u][%s][invalid checksum level][checksum level:%d]', 
            __FILE__, __LINE__, __FUNCTION__, $conf->checksum_level);
            return false;
        }
        $this->_stomp_adapter = new BigpipeStompAdapter($conf->stomp_conf);

        $this->_conn_timeo = $conf->conn_timo;
        $this->_pref_conn = $conf->prefer_conn;
        $this->_max_fo_cnt = $conf->max_failover_cnt;
        $this->_package = new BigpipeMessagePackage;
        // 连接meta agent, 初始化一个meta实例
        if (!$this->_meta_adapter->connect())
        {
            BigpipeLog::fatal("[%s:%u][%s][can not connect to meta agent]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        return true;
    }

    /**
     * 订阅认证
     * @param string $token
     * @return 如果认证成功返回可发布的pipelet number, 否则返回false
     */
    private function _authorize($token)
    {
        $ret = false;
        $author_result = $this->_meta_adapter->authorize($this->_pipe_name, $token, BStompRoleType::SUBSCRIBER);
        if (false === $author_result)
        {
            BigpipeLog::warning('[%s:%u][%s][send authorization error]',
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

    /**
     * 更新meta信息，连接broker
     */
    private function _connect()
    {
        //         $ret = false;
        //         while(!$ret && $this->_failover_count < $this->_max_failover_cnt)
        //         {
        //             $ret = $this->_failover();
        //         }
        // 直接调用failover 进行连接
        return $this->_failover();
    }

    /**
     * 从meta更新可订阅broker
     * @return true on success or false on failure
     */
    private function _update_meta()
    {
        $this->_brokers = null;
        $this->_stripe = $this->_meta_adapter->get_sub_broker_group($this->_pipe_name, $this->_pipelet_id, $this->_pipelet_msg_id);
        if (false === $this->_stripe)
        {
            return false;
        }

        // 检查broker group的状态
        $grp_status = $this->_stripe['broker_group']->status;
        if (BigpipeBrokerGroupStatus::FAIL == $grp_status)
        {
            BigpipeLog::warning('[%s:%u][%s][group status is fail][name:%s]',
            __FILE__, __LINE__, __FUNCTION__, $this->_stripe['broker_group']->name);
            return false;
        }

        if (SubscribeStartPoint::START_FROM_FIRST_POINT == $this->_pipelet_msg_id)
        {
            // 用户请求最旧订阅点时，需要手动更新start point
            $this->_pipelet_msg_id = $this->_stripe['begin_pos'];
        }
        return $this->_select_brokers($this->_stripe['broker_group'], $this->_pref_conn);
    }

    /**
     * 指定订阅点，被connect、receive调用
     * @return bool
     */
    private function _subscribe()
    {
        // 填充订阅包
        $cmd = new BStompSubscribeFrame;
        $cmd->destination = $this->_stripe['stripe_name'];
        $cmd->start_point = $this->_pipelet_msg_id;
        // $cmd->subscribe_id = null; 目前无用
        // $cmd->selector = null; 目前无用
        $cmd->receipt_id = (isset($this->unittest)) ? 'unittest-receipt-id' : BigpipeUtilities::gen_receipt_id();
        if (!$this->_stomp_adapter->send($cmd))
        {
            BigpipeLog::warning('[%s:%u][%s][send error]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 接收RECEIPT
        $res_body = $this->_stomp_adapter->receive();
        if (null === $res_body)
        {
            BigpipeLog::warning('[%s:%u][%s][receive error]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // parse RECEIPT
        $ack = new BStompReceiptFrame;
        if (!$ack->load($res_body))
        {
            BigpipeLog::warning('[%s:%u][%s][load receipt error][cmd_type:%d][err_msg:%s]',
            __FILE__, __LINE__, __FUNCTION__, $ack->command_type, $ack->last_error_message());
            return false;
        }

        // 检查订阅是否成功
        if ($ack->receipt_id != $cmd->receipt_id)
        {
            BigpipeLog::warning('[%s:%u][%s][error receipt id][send:%u][recv:%u]',
            __FILE__, __LINE__, __FUNCTION__, $cmd->receipt_id, $ack->receipt_id);
            return false;
        }

        $this->_is_subscribed = true;
        return true;
    }

    /**
     * 取消订阅
     */
    private function _unsubscribe()
    {
        if (!$this->_is_subscribed)
        {
            return true; // 取消成功
        }

        // 填充取消订阅包
        $cmd = new BStompUnsubscribeFrame;
        $cmd->destination = $this->_stripe['stripe_name'];
        // $cmd->subscribe_id = null; 目前无用
        $cmd->receipt_id = (isset($this->unittest)) ? 'unittest-receipt-id' : BigpipeUtilities::gen_receipt_id();
        if (!$this->_stomp_adapter->send($cmd))
        {
            BigpipeLog::warning('[%s:%u][%s][send error]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 接收RECEIPT
        $res_body = $this->_stomp_adapter->receive();
        if (null === $res_body)
        {
            BigpipeLog::warning('[%s:%u][%s][receive error]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // parse RECEIPT
        $ack = new BStompReceiptFrame;
        if (!$ack->load($res_body))
        {
            BigpipeLog::warning('[%s:%u][%s][load receipt error][cmd_type:%d][err_msg:%s]',
            __FILE__, __LINE__, __FUNCTION__, $ack->command_type, $ack->last_error_message());
            return false;
        }

        // 检查取消订阅是否成功
        if ($ack->receipt_id != $cmd->receipt_id)
        {
            BigpipeLog::warning('[%s:%u][%s][error receipt id][send:%u][recv:%u]',
            __FILE__, __LINE__, __FUNCTION__, $cmd->receipt_id, $ack->receipt_id);
            return false;
        }

        $this->_is_subscribed = false;
        return true;
    }

    /**
     * 刷新订阅（断开原有订阅，找到新的broker重新订阅
     * @return true on success or false on failure
     */
    private function _flush_subscribe()
    {
        // 关闭原有订阅
        if ($this->_is_subscribed)
        {
            $this->_unsubscribe();
            $this->_stomp_adapter->close();
        }

        if (!$this->_connect())
        {
            BigpipeLog::warning('[%s:%u][%s][fail to connect to new broker]',
            __FILE__, __LINE__, __FUNCTION__);
            return false; // 连接失败
        }

        if (!$this->_subscribe())
        {
            BigpipeLog::warning('[%s:%u][%s][fail to subscribe]',
            __FILE__, __LINE__, __FUNCTION__);
            $this->_stomp_adapter->close();
            return false;
        }

        return true;
    }

    /**
     * 根据prefer从传入的broker_group选取所有满足条件的broker
     * @param $broker_group: broker group
     * @param $prefer: broker选取条件
     * @return 选择完成返回可订阅的brokers, 如果失败返回fasle
     */
    private function _select_brokers($broker_group, $prefer)
    {
        $broker_role = null;
        $max_prefer = BigpipeConnectPreferType::SECONDARY_BROKER_ONLY + BigpipeConnectPreferType::PRIMARY_BROKER_ONLY;
        if ($prefer > $max_prefer)
        {
            BigpipeLog::warning("[%s:%u][%s][unknown prefer value][value:%d][max_value:%d]",
                __FILE__, __LINE__, __FUNCTION__, $prefer, $max_prefer);
            return false;
        }

        $this->_brokers = null; // clear brokers
        // 选出候选broker
        $candidate_brokers = array();
        foreach ($broker_group->brokers as $brk)
        {
            if ( ($brk->role & $prefer) > 0)
            {
                array_push($candidate_brokers, $brk);
            }
        }

        $candidate_count = count($candidate_brokers);
        if (0 == $candidate_count)
        {
            BigpipeLog::warning("[%s:%u][%s][no candidate broker][role:%d]",
            __FILE__, __LINE__, __FUNCTION__, $broker_role);
            return false;
        }

        $this->_brokers = $candidate_brokers;
        return true;
    }

    /**
     * 随机选择可订阅的broker, 选择后从候选brokers中移除被选中的
     * @return 选择完成返回可订阅的broker, 如果失败返回fasle
     */
    private function _random_select_broker()
    {
        if (empty($this->_brokers))
        {
            return false; // broker为空
        }
        $num_candidates = count($this->_brokers);
        if (0 == $num_candidates)
        {
            return false; // 无可选的broker
        }

        $brk_index = rand() % $num_candidates;
        $brk = $this->_brokers[$brk_index];
        unset($this->_brokers[$brk_index]);
        return $brk;
    }

    /**
     * 当前订阅活跃，重置failover状态
     */
    private function _active()
    {
        $this->_fo_count = 0;
        $this->_fo_sleep_time = BigpipeCommonDefine::INIT_FO_SLEEP_TIME * 1000;
    }

    /**
     * 更新meta信息, 记录重试次数
     * @return boolean
     */
    private function _failover()
    {
        // failover时, 订阅、发布状态无效，重置状态
        if (true == $this->_is_subscribed)
        {
            $this->_unsubscribe(); // 先尝试取消订阅, 但是不用考虑错误 (因为failover中有错误是常态)
            $this->_is_subscribed = false;
        }

        if ($this->_fo_count > $this->_max_fo_cnt)
        {
            // 重置failover
            BigpipeLog::fatal("[%s:%u][%s][can not do more]",
            __FILE__, __LINE__, __FUNCTION__);
            $this->_fo_sleep_time = 0;
            $this->_fo_count = 0;
            return false;
        }

        if (0 == $this->_fo_sleep_time)
        {
            // 第一次flush subscribe时，我们往往不希望等待，
            // 因此这时跳过sleep过程
            // php中只有微秒级的usleep和秒级的sleep
            $this->_fo_sleep_time = BigpipeCommonDefine::INIT_FO_SLEEP_TIME * 1000;
        }
        else
        {
            usleep($this->_fo_sleep_time);
        }
        $this->_fo_count++;
        $this->_fo_sleep_time *= 2; // increase failover sleep time
        if ($this->_fo_sleep_time > BigpipeCommonDefine::MAX_FO_SLEEP_TIME)
        {
            // failover sleep time不能无限制增长
            $this->_fo_sleep_time = BigpipeCommonDefine::MAX_FO_SLEEP_TIME;
        }

        // 通过meta跟新stripe
        if (false === $this->_update_meta())
        {
            BigpipeLog::fatal("[%s:%u][%s][can not update meta from meta agent]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 随机选择并连接一个broker
        $is_ok = false;
        do
        {
            $broker = $this->_random_select_broker();
            if (false === $broker)
            {
                // 无新broker可选, failover失败
                BigpipeLog::fatal("[%s:%u][%s][no broker to subcribe]",
                __FILE__, __LINE__, __FUNCTION__);
                break;
            }
            // try to connect to broker
            $sub_dest = array(
                    "socket_address" => $broker->ip,
                    "socket_port"    => $broker->port,
                    "socket_timeout" => $this->_conn_timeo,
            );
            $this->_stomp_adapter->set_destination($sub_dest);
            $this->_stomp_adapter->role = BStompRoleType::SUBSCRIBER;
            $this->_stomp_adapter->topic_name = $this->_stripe['stripe_name'];
            $this->_stomp_adapter->session_id
            = BigpipeUtilities::get_pipelet_name($this->_pipe_name, $this->_pipelet_id) . '_' . BigpipeUtilities::get_uid();
            if ($this->_stomp_adapter->connect())
            {
                BigpipeLog::debug("[%s:%u][%s][Success][connected on broker][ip:%s][port:%u]",
                __FILE__, __LINE__, __FUNCTION__, $broker->ip, $broker->port);
                BigpipeLog::debug('[%s:%u][%s][session message id][smid:%s]',
                __FILE__, __LINE__, __FUNCTION__, $this->_stomp_adapter->session_message_id);
                $is_ok = true;
                break; // 跳出连接
            }
        } while (true);

        return $is_ok;
    }

    /**
     * 从read buffer读取一个message package
     * @return false on failure or BigpageMessage on success
     */
    private function _receive()
    {
        $obj = false;
        do
        {
            $res_body = $this->_stomp_adapter->receive();
            if (null === $res_body)
            {
                continue; // 直接重订阅
            }
            // 接收成功，读取数据
            $msg = new BStompMessageFrame;
            if (!$msg->load($res_body))
            {
                // message包问题
                BigpipeLog::warning('[%s:%u][%s][receive msg error][%s]', 
                __FILE__, __LINE__, __FUNCTION__, $msg->last_error_message());
                continue;
            }
            // 读取msg包
            if (-1 != $this->_pipelet_msg_id && $msg->topic_message_id < $this->_pipelet_msg_id)
            {
                BigpipeLog::warning('[%s:%u][%s][received different start point error][recv: %u][req: %u]',
                __FILE__, __LINE__, __FUNCTION__,
                $msg->topic_message_id,
                $this->_pipelet_msg_id);
                continue;
            }

            $msg_body = $msg->message_body;
            if (empty($msg_body) || false === $msg_body)
            {
                continue; // 接收message失败，failover
            }

            // message 接收成功，返回ack
            if (BStompClientAckType::AUTO == $this->_client_ack_type)
            {
                // 发送ack包
                $ack = new BStompAckFrame;
                $ack->receipt_id = $msg->receipt_id;
                $ack->topic_message_id = $msg->topic_message_id;
                $ack->destination = $msg->destination;
                $ack->ack_type = BStompIdAckType::TOPIC_ID;
                if (!$this->_stomp_adapter->send($ack))
                {
                    // message接收成功，但是ack发送失败，可以不用理会
                    // 因为下次receive时，会进入failover
                    BigpipeLog::warning('[%s:%u][%s][fail to ack message]',
                    __FILE__, __LINE__, __FUNCTION__);
                }
            }

            // 处理message
            if ($this->_enable_checksum)
            {
                $sign = creat_sign_mds64($msg_body);
                if ($sign[2] != $msg->cur_checksum)
                {
                    // checksum校验失败, 进入failover
                    BigpipeLog::warning('[%s:%u][%s][message package checksum error][orig:%u][curr:%u][name:%s][msg_id:%u]',
                    __FILE__, __LINE__, __FUNCTION__,
                    $msg->cur_checksum,
                    $sign[2],
                    $this->_get_stripe_name(),
                    $msg->topic_message_id);
                    continue;
                }
            }
            if (!$this->_package->load($msg_body, $msg->topic_message_id))
            {
                // 接收的包有问题, 进入failover
                BigpipeLog::warning('[%s:%u][%s][message package error][name:%s][msg_id:%u]',
                __FILE__, __LINE__, __FUNCTION__,
                $this->_get_stripe_name(),
                $msg->topic_message_id);
                continue;
            }

            $obj = $this->_package->pop(); // 必须能成功取一条message
            if (false === $obj)
            {
                // 包中的内容有问，进入failover
                BigpipeLog::warning('[%s:%u][%s][empty message package][name:%s][msg_id:%u]',
                __FILE__, __LINE__, __FUNCTION__,
                $this->_get_stripe_name(),
                $msg->topic_message_id);
                continue;
            }

            $this->_active(); // 设置活跃状态
            $this->_pipelet_msg_id = $msg->topic_message_id + 1;
            if ($this->_get_end_pos() < $this->_pipelet_msg_id)
            {
                // 已接收到stripe末尾，更换新stripe
                // 主动更新订阅， 如果更新失败也没关系，下次receive时可以处理
                // 这里进入flush_subscribe不需要sleep
                $this->_fo_sleep_time = 0;
                if ($this->_flush_subscribe())
                {
                    $this->_active(); //
                }
            }
            break; // 成功收到message 跳出循环
        } while ($this->_flush_subscribe());

        return $obj;
    }

    /**
     * 读取当前stripe的end position
     */
    private function _get_end_pos()
    {
        return $this->_stripe['end_pos'];
    }

    private function _get_stripe_name()
    {
        return $this->_stripe['stripe_name'];
    }

    //== internal variables

    /** 指示subscribe是否被初始化 */
    private $_inited = false;

    // 以下是订阅前传入的变量
    private $_pipe_name = null;
    private $_token = null;
    private $_pipelet_id = null;
    private $_start_point = null;

    private $_num_pipelet = null;

    // 以下是两个网络栈
    /**
    * 与meta agent交互
    * @var MetaAgentAdapter
    */
    private $_meta_adapter = null;

    /**
     * 与stomp协议栈交互
     * @var BigpipeStompAdapter
     */
    private $_stomp_adapter = null;

    // 订阅类变量
    private $_pref_conn = BigpipeConnectPreferType::SECONDARY_BROKER_ONLY;
    /** 可订阅的broker列表 */
    private $_brokers = null;
    /** meta中获取的stripe信息集 */
    private $_stripe = null;
    /** 标识是否有订阅 */
    private $_is_subscribed = false;
    private $_conn_timeo = null;
    private $_fo_count = 0; // 当前进入failover次数
    private $_max_fo_cnt = 0; // 最大failover次数
    private $_fo_sleep_time = 0; // failover是休眠时间

    // 以下是subscribe中用到的信息
    private $_pipelet_msg_id = null; // topic message id 随着订阅进程而改动
    private $_client_ack_type = BStompClientAckType::AUTO;
    private $_enable_checksum = true;
    private $_package = null; // message package
} // BigpipeSubscriber

?>
