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
 * bigpipe ���Ľӿ�
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
     * @brief: ��ʼ��������
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
            // ����ʲô�������ͼ��ʼ��һ���ѱ���ʼ����subscribe���Ǵ����
            BigpipeLog::fatal("[%s:%u][%s][mulitple init]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ��ʼ��
        if (!$this->_init($pipe_name, $token, $pipelet_id, $start_point, $conf))
        {
            return false;
        }

        // ������֤
        $this->_num_pipelet = $this->_authorize($token);
        if (false === $this->_num_pipelet)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to authorize BigpipeSubscriber]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ���piplet id
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
     * @brief: �ȴ���Ϣ����
     * @param int64_t $timo_ms �ȴ���ʱ��ms��
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

        // ���ķ���
        if (!$this->_is_subscribed)
        {
            // û�ж���ʱ���Է�����
            $this->_fo_sleep_time = 0; // ʹ��0,��ʾfailover�в�sleep
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
     * @brief: ������Ϣ
     * @return ���ճɹ�����BigpipeMsgPack $msg; ����ʧ��, ����false
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
            // û�ж���
            BigpipeLog::warning('[%s:%u][%s][receive from unsbscribed stripe]', 
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $msg = $this->_package->pop();
        if (false === $msg)
        {
            // ��ǰpackageΪ��, ����һ����Ϣ
            $msg = $this->_receive();
        }

        return $msg;
    }
     
    /**
     * �ͷ�ֹͣ���ģ��ͷ�����
     */
    public function uninit()
    {
        if (!$this->_inited)
        {
            return;
        }

        if ($this->_is_subscribed)
        {
            $this->_unsubscribe(); // ��������
        }

        // �Ͽ�stomp
        $this->_stomp_adapter->close();
        $this->_stomp_adapter = null; // stomp adapter ��initʱ��new����

        // �Ͽ�meta
        $this->_meta_adapter->close();

        // ���״̬
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
     * �ڲ�ʵ�ֳ�ʼ��subscriber��Ա�Ĺ���
     * @return true on success or false on failure
     */
    private function _init($pipe_name, $token, $pipelet_id, $start_point, $conf)
    {
        // todo ��ϸ������

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

        // ��λ����
        $conf->stomp_conf->conn_conf->check_frame = false;
        $conf->meta_conf->conn_conf->check_frame = false; // meta agent��������У��
        if (false === $this->_meta_adapter->init($conf->meta_conf))
        {
            BigpipeLog::fatal('[%s:%u][%s][fail to init MetaAgentAdapter]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        // ����checksum leve�޸�meta��stomp��connection����
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
        // ����meta agent, ��ʼ��һ��metaʵ��
        if (!$this->_meta_adapter->connect())
        {
            BigpipeLog::fatal("[%s:%u][%s][can not connect to meta agent]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        return true;
    }

    /**
     * ������֤
     * @param string $token
     * @return �����֤�ɹ����ؿɷ�����pipelet number, ���򷵻�false
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
     * ����meta��Ϣ������broker
     */
    private function _connect()
    {
        //         $ret = false;
        //         while(!$ret && $this->_failover_count < $this->_max_failover_cnt)
        //         {
        //             $ret = $this->_failover();
        //         }
        // ֱ�ӵ���failover ��������
        return $this->_failover();
    }

    /**
     * ��meta���¿ɶ���broker
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

        // ���broker group��״̬
        $grp_status = $this->_stripe['broker_group']->status;
        if (BigpipeBrokerGroupStatus::FAIL == $grp_status)
        {
            BigpipeLog::warning('[%s:%u][%s][group status is fail][name:%s]',
            __FILE__, __LINE__, __FUNCTION__, $this->_stripe['broker_group']->name);
            return false;
        }

        if (SubscribeStartPoint::START_FROM_FIRST_POINT == $this->_pipelet_msg_id)
        {
            // �û�������ɶ��ĵ�ʱ����Ҫ�ֶ�����start point
            $this->_pipelet_msg_id = $this->_stripe['begin_pos'];
        }
        return $this->_select_brokers($this->_stripe['broker_group'], $this->_pref_conn);
    }

    /**
     * ָ�����ĵ㣬��connect��receive����
     * @return bool
     */
    private function _subscribe()
    {
        // ��䶩�İ�
        $cmd = new BStompSubscribeFrame;
        $cmd->destination = $this->_stripe['stripe_name'];
        $cmd->start_point = $this->_pipelet_msg_id;
        // $cmd->subscribe_id = null; Ŀǰ����
        // $cmd->selector = null; Ŀǰ����
        $cmd->receipt_id = (isset($this->unittest)) ? 'unittest-receipt-id' : BigpipeUtilities::gen_receipt_id();
        if (!$this->_stomp_adapter->send($cmd))
        {
            BigpipeLog::warning('[%s:%u][%s][send error]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ����RECEIPT
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

        // ��鶩���Ƿ�ɹ�
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
     * ȡ������
     */
    private function _unsubscribe()
    {
        if (!$this->_is_subscribed)
        {
            return true; // ȡ���ɹ�
        }

        // ���ȡ�����İ�
        $cmd = new BStompUnsubscribeFrame;
        $cmd->destination = $this->_stripe['stripe_name'];
        // $cmd->subscribe_id = null; Ŀǰ����
        $cmd->receipt_id = (isset($this->unittest)) ? 'unittest-receipt-id' : BigpipeUtilities::gen_receipt_id();
        if (!$this->_stomp_adapter->send($cmd))
        {
            BigpipeLog::warning('[%s:%u][%s][send error]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ����RECEIPT
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

        // ���ȡ�������Ƿ�ɹ�
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
     * ˢ�¶��ģ��Ͽ�ԭ�ж��ģ��ҵ��µ�broker���¶���
     * @return true on success or false on failure
     */
    private function _flush_subscribe()
    {
        // �ر�ԭ�ж���
        if ($this->_is_subscribed)
        {
            $this->_unsubscribe();
            $this->_stomp_adapter->close();
        }

        if (!$this->_connect())
        {
            BigpipeLog::warning('[%s:%u][%s][fail to connect to new broker]',
            __FILE__, __LINE__, __FUNCTION__);
            return false; // ����ʧ��
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
     * ����prefer�Ӵ����broker_groupѡȡ��������������broker
     * @param $broker_group: broker group
     * @param $prefer: brokerѡȡ����
     * @return ѡ����ɷ��ؿɶ��ĵ�brokers, ���ʧ�ܷ���fasle
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
        // ѡ����ѡbroker
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
     * ���ѡ��ɶ��ĵ�broker, ѡ���Ӻ�ѡbrokers���Ƴ���ѡ�е�
     * @return ѡ����ɷ��ؿɶ��ĵ�broker, ���ʧ�ܷ���fasle
     */
    private function _random_select_broker()
    {
        if (empty($this->_brokers))
        {
            return false; // brokerΪ��
        }
        $num_candidates = count($this->_brokers);
        if (0 == $num_candidates)
        {
            return false; // �޿�ѡ��broker
        }

        $brk_index = rand() % $num_candidates;
        $brk = $this->_brokers[$brk_index];
        unset($this->_brokers[$brk_index]);
        return $brk;
    }

    /**
     * ��ǰ���Ļ�Ծ������failover״̬
     */
    private function _active()
    {
        $this->_fo_count = 0;
        $this->_fo_sleep_time = BigpipeCommonDefine::INIT_FO_SLEEP_TIME * 1000;
    }

    /**
     * ����meta��Ϣ, ��¼���Դ���
     * @return boolean
     */
    private function _failover()
    {
        // failoverʱ, ���ġ�����״̬��Ч������״̬
        if (true == $this->_is_subscribed)
        {
            $this->_unsubscribe(); // �ȳ���ȡ������, ���ǲ��ÿ��Ǵ��� (��Ϊfailover���д����ǳ�̬)
            $this->_is_subscribed = false;
        }

        if ($this->_fo_count > $this->_max_fo_cnt)
        {
            // ����failover
            BigpipeLog::fatal("[%s:%u][%s][can not do more]",
            __FILE__, __LINE__, __FUNCTION__);
            $this->_fo_sleep_time = 0;
            $this->_fo_count = 0;
            return false;
        }

        if (0 == $this->_fo_sleep_time)
        {
            // ��һ��flush subscribeʱ������������ϣ���ȴ���
            // �����ʱ����sleep����
            // php��ֻ��΢�뼶��usleep���뼶��sleep
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
            // failover sleep time��������������
            $this->_fo_sleep_time = BigpipeCommonDefine::MAX_FO_SLEEP_TIME;
        }

        // ͨ��meta����stripe
        if (false === $this->_update_meta())
        {
            BigpipeLog::fatal("[%s:%u][%s][can not update meta from meta agent]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ���ѡ������һ��broker
        $is_ok = false;
        do
        {
            $broker = $this->_random_select_broker();
            if (false === $broker)
            {
                // ����broker��ѡ, failoverʧ��
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
                break; // ��������
            }
        } while (true);

        return $is_ok;
    }

    /**
     * ��read buffer��ȡһ��message package
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
                continue; // ֱ���ض���
            }
            // ���ճɹ�����ȡ����
            $msg = new BStompMessageFrame;
            if (!$msg->load($res_body))
            {
                // message������
                BigpipeLog::warning('[%s:%u][%s][receive msg error][%s]', 
                __FILE__, __LINE__, __FUNCTION__, $msg->last_error_message());
                continue;
            }
            // ��ȡmsg��
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
                continue; // ����messageʧ�ܣ�failover
            }

            // message ���ճɹ�������ack
            if (BStompClientAckType::AUTO == $this->_client_ack_type)
            {
                // ����ack��
                $ack = new BStompAckFrame;
                $ack->receipt_id = $msg->receipt_id;
                $ack->topic_message_id = $msg->topic_message_id;
                $ack->destination = $msg->destination;
                $ack->ack_type = BStompIdAckType::TOPIC_ID;
                if (!$this->_stomp_adapter->send($ack))
                {
                    // message���ճɹ�������ack����ʧ�ܣ����Բ������
                    // ��Ϊ�´�receiveʱ�������failover
                    BigpipeLog::warning('[%s:%u][%s][fail to ack message]',
                    __FILE__, __LINE__, __FUNCTION__);
                }
            }

            // ����message
            if ($this->_enable_checksum)
            {
                $sign = creat_sign_mds64($msg_body);
                if ($sign[2] != $msg->cur_checksum)
                {
                    // checksumУ��ʧ��, ����failover
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
                // ���յİ�������, ����failover
                BigpipeLog::warning('[%s:%u][%s][message package error][name:%s][msg_id:%u]',
                __FILE__, __LINE__, __FUNCTION__,
                $this->_get_stripe_name(),
                $msg->topic_message_id);
                continue;
            }

            $obj = $this->_package->pop(); // �����ܳɹ�ȡһ��message
            if (false === $obj)
            {
                // ���е��������ʣ�����failover
                BigpipeLog::warning('[%s:%u][%s][empty message package][name:%s][msg_id:%u]',
                __FILE__, __LINE__, __FUNCTION__,
                $this->_get_stripe_name(),
                $msg->topic_message_id);
                continue;
            }

            $this->_active(); // ���û�Ծ״̬
            $this->_pipelet_msg_id = $msg->topic_message_id + 1;
            if ($this->_get_end_pos() < $this->_pipelet_msg_id)
            {
                // �ѽ��յ�stripeĩβ��������stripe
                // �������¶��ģ� �������ʧ��Ҳû��ϵ���´�receiveʱ���Դ���
                // �������flush_subscribe����Ҫsleep
                $this->_fo_sleep_time = 0;
                if ($this->_flush_subscribe())
                {
                    $this->_active(); //
                }
            }
            break; // �ɹ��յ�message ����ѭ��
        } while ($this->_flush_subscribe());

        return $obj;
    }

    /**
     * ��ȡ��ǰstripe��end position
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

    /** ָʾsubscribe�Ƿ񱻳�ʼ�� */
    private $_inited = false;

    // �����Ƕ���ǰ����ı���
    private $_pipe_name = null;
    private $_token = null;
    private $_pipelet_id = null;
    private $_start_point = null;

    private $_num_pipelet = null;

    // ��������������ջ
    /**
    * ��meta agent����
    * @var MetaAgentAdapter
    */
    private $_meta_adapter = null;

    /**
     * ��stompЭ��ջ����
     * @var BigpipeStompAdapter
     */
    private $_stomp_adapter = null;

    // ���������
    private $_pref_conn = BigpipeConnectPreferType::SECONDARY_BROKER_ONLY;
    /** �ɶ��ĵ�broker�б� */
    private $_brokers = null;
    /** meta�л�ȡ��stripe��Ϣ�� */
    private $_stripe = null;
    /** ��ʶ�Ƿ��ж��� */
    private $_is_subscribed = false;
    private $_conn_timeo = null;
    private $_fo_count = 0; // ��ǰ����failover����
    private $_max_fo_cnt = 0; // ���failover����
    private $_fo_sleep_time = 0; // failover������ʱ��

    // ������subscribe���õ�����Ϣ
    private $_pipelet_msg_id = null; // topic message id ���Ŷ��Ľ��̶��Ķ�
    private $_client_ack_type = BStompClientAckType::AUTO;
    private $_enable_checksum = true;
    private $_package = null; // message package
} // BigpipeSubscriber

?>
