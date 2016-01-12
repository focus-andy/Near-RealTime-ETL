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
 * ����pipelet_id
 * @author yangzhenyu@baidu.com 
 */
class BigpipePubPartitioner
{
    /**
     * �������ĸ�pipelet����
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
 * ���ڱ��淢�����
 * @author yangzhenyu@baidu.com
 */
class BigpipePubResult
{
    /** ��Ϣ����״̬ */
    public $error_no = BigpipeErrorCode::OK;
    /** ��Ϣ��������pipelet */
    public $pipelet_id = null;
    /** ��Ϣ������bigpipeΪ������pipelet�ڲ�Ψһ����id (start point) */
    public $pipelet_msg_id = null;
    /** ��Ϣ�ڱ�session�е�id */
    public $session_msg_id = null;
} // BigpipePubResult

/**
 * publisher��
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
            // �ͷ���Դ
            $this->uninit();
        }
    }

    /**
     * ��ʼ��������<p>
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
        // ͨ��meta adapter����meta agent
        if (!$this->_meta_adapter->connect())
        {
            BigpipeLog::warning("[%s:%u][%s][can not connect to meta agent]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ��֤
        $this->_num_piplet = $this->_authorize($token);
        if (false === $this->_num_piplet)
        {
            BigpipeLog::fatal("[%s:%u][%s][fail to authorize BigpipePublisher]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        $this->_conf->session_level = 0; // Ŀǰinit�ӿ�ֻ֧��0
        $this->_inited = true;
        return true;
    }

    /**
     * ��ʼ��������<p>
     * ���ӿ��кϲ��˶�meta-agent��init meta��authorize�������������������Ӵ�����������ʧ
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
     * @brief: ��primary broker ����һ����Ϣ
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
            // ��pub_list��, ����pub������
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
            $pub->stop(); // ���ͳ��ִ���ֹͣtask���ȴ��´�������
        }
        unset($pub); // �����ͷ�$pub��pub task����ϵ��������ܻ�Ӱ�쵽_pub_list�б����õ�Ԫ�ء�
        return $pub_result;
    }

    /**
     * ���״̬
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

        // �ͷ�pub list
        $this->_pub_list = null; // task �ͷ�ʱ�������Ͽ�����
        $this->_pipe_name = null;
        unset($this->_partitioner); // �Ƴ�partitioner������
        $this->_partitioner = null;
        $this->_conf = null;
        $this->_num_piplet = null;
        $this->_inited = false;
    }
    
    private function _init($pipe_name, $token, &$partitioner, $conf)
    {
        // todo ��is_a check partitioner����
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
        
        // ��λ����
        $conf->stomp_conf->conn_conf->check_frame = false;
        $conf->meta_conf->conn_conf->check_frame = false; // meta agent��������У��
        // ����checksum leve�޸�meta��stomp��connection����
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
        $this->_conf = $conf; // ��¼conf����publish task

        if (false === $this->_meta_adapter->init($conf->meta_conf))
        {
            BigpipeLog::fatal('[%s:%u][%s][fail to init MetaAgentAdapter]',
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $this->_pub_list = array();

        // initʱ����task session id��ͳһǰ׺
        // Ŀǰ��1.5���ڣ�ͬһ����������һ��pipe����������publisherӵ����ͬ��session
        $this->_session = BigpipeUtilities::get_session_id($pipe_name);
        return true;
    }
    
    /**
     * ʹ��pipe_name��token��meta��֤�Ƿ���Է���
     * @return �����֤�ɹ����ؿɷ�����pipelet number, ���򷵻�false
     */
    private function _authorize($token)
    {
        // todo ������pipe��12��pipelet
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
    
    /** ��ǰpipeӵ�е�piplet��Ŀ, ��֤���� */
    private $_num_piplet = null;
    private $_meta_adapter = null;
    /** �Ƿ��meta��ȡsession */
    private $_session = null;
    private $_session_id = null;
    private $_session_timestamp = null;

    /**
     * �����б�ÿ��Ԫ�ض���Ӧһ��BigpipePublishTask
     * @var array
     */
    private $_pub_list = null;
    private $_inited = false;
} // end of BigpipePublisher

/**
 * ��������
 * @author yangzhenyu@baidu.com
 */
class BigpipePublishTask
{
    /**
     * ����һ��publish task
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
        // $conf�е�checksum level��publisher��ʼ��ʱ�ѽ��жϹ���Ч�ԣ����ﴫ���ֵһ������ȷ�ġ�
        $this->_enable_checksum = false;
        if (BigpipeChecksumLevel::CHECK_MESSAGE <= $conf->checksum_level)
        {
            $this->_enable_checksum = true;
        }

        if (0 == $conf->session_level)
        {
            // session levelΪ0����ʾʹ���Զ����ɵ�session
            $this->_session_id = sprintf('%s-%u', $session_id, $pipelet_id);
        }
        else 
        {
            $this->_session_id = $session_id;
        }
        $this->_session_msg_id = BigpipeUtilities::get_time_us(); // ���ó�ʼsession msg id
    }
    
    /**
     * ֹͣ������һ��task
     */
    public function __destruct()
    {
        if ($this->_is_started)
        {
            $this->stop();
        }
        unset($this->_meta_adapter); // ���õı�����unset�ͷ�
    }
    
    /**
     * ��ʼһ����������, ���������ӵ��ɷ�����broker
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
        // session id���ڣ����ǲ��������״̬��
        // �����Ǹ�trick��_fo_sleep_time = 0ʱ���ǻ�����failover�е�sleep
        // ��ʼʱ��connect����Ҫsleep
        $this->_fo_sleep_time = 0;
        $this->_is_started = $this->_connect(); // ���ӳɹ�����ɹ���ʼһ������
        
        // startʱ��Ԥ���ϴ�send��ʧ�ܵ�
        $this->_last_send_ok = false;
        return $this->_is_started;
    }

    /**
     * ����Publish Task�Ƿ��ѱ�����
     * @return boolean
     */
    public function is_started()
    {
        return $this->_is_started;
    }
    
    /**
     * ֹͣ��������
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
     * ����һ����Ϣ
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

        // ����CONNECT
        $cmd = new BStompSendFrame;
        if (!$msg_package->store($cmd->message_body))
        {
            // �����󣬲��ÿ���failover��
            BigpipeLog::warning("[fail to store message body]");
            return false;
        }

        if (true === $this->_last_send_ok)
        {
            // ���ϴη��ͳɹ�������session mesage id
            // ����message id���䣬��ֹ�ظ�����
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
            // ����message��У��
            $cmd->last_checksum = $this->_last_sign;
            $sign = creat_sign_mds64($cmd->message_body); 
            $cmd->cur_checksum = $sign[2]; // ע�⺯������ֵ
            $this->_last_sign = $cmd->cur_checksum;
        }

        // ���뷢������,
        // ����ʧ�������failover���̣�ֱ��failoverʧ�ܡ�
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
                // ACKʧ��
                // ���ÿ���BMQ_E_COMMAND_DUPLICATEMSG, ȥ����broker��,
                // �û������յ�duplicate����.
                BigpipeLog::warning("[send message ack error][session:%s][session_msg_id:%u]",
                $cmd->session_id, $cmd->session_message_id);
                continue;
            }
            else
            {
                BigpipeLog::notice("[send message success][pipelet:%u][session:%s][session_msg_id:%u]",
                $this->_pipelet_id, $cmd->session_id, $cmd->session_message_id);
                break; // �����ɹ�
            }
        } while ($this->_failover());

        if (false !== $send_result)
        {
            $this->_active(); // ����failover״̬
        }
        else
        {
            $this->_last_send_ok = false;
        }
        return $send_result;
    }

    /**
     * ����meta��Ϣ������broker
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
     * ���շ�����Ϣ, �жϷ����Ƿ�ɹ�
     * @return BigpipePubResult on success or false on failure
     */
    private function _check_ack($receipt_id, &$send_result)
    {
        // ����CONNECTED
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

        // ���result
        $send_result = new BigpipePubResult;
        $send_result->error_no = $ack->status;
        $send_result->pipelet_id = $this->_pipelet_id;
        $send_result->pipelet_msg_id = $ack->topic_message_id;
        $send_result->session_msg_id = $ack->session_message_id;
        return $send_result;
    }

    /**
     * ��meta���¿ɷ�����broker
     * @return primary broker on success or false on failure
     */
    private function _update_meta()
    {
        $broker = $this->_meta_adapter->get_pub_broker($this->_pipe_name, $this->_pipelet_id);
        // ���broker group��״̬
        return $broker;
    }

    /**
     * ����meta��Ϣ, ��¼���Դ���
     * @return boolean
     */
    private function _failover()
    {
        if ($this->_fo_count >= $this->_max_fo_cnt)
        {
            // ����failover
            BigpipeLog::fatal("[_failover][can not do more][max_cnt:%d]", $this->_max_fo_cnt);
            $this->_fo_sleep_time = 0;
            $this->_fo_count = 0;
            return false;
        }

        if (0 == $this->_fo_sleep_time)
        {
            // php��ֻ��΢�뼶��usleep���뼶��sleep
            // Ŀǰֻ�е�startʱ�Ż���������֧��
            // ��ʱ���ǻ�����failover
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
            // failover sleep time��������������
            $this->_fo_sleep_time = BigpipeCommonDefine::MAX_FO_SLEEP_TIME;
        }

        // ͨ��meta����stripe
        $broker = $this->_update_meta();
        if (false === $broker)
        {
            BigpipeLog::fatal("[_failover][can not update meta from meta agent]");
            return false;
        }

        // ����broker
        $pub_dest = array(
                "socket_address" => $broker['ip'],
                "socket_port"    => $broker['port'],
                "socket_timeout" => $this->_conn_timeo,
        );
        $this->_stomp_adapter->set_destination($pub_dest);
        $this->_stomp_adapter->role = BStompRoleType::PUBLISHER;
        $this->_stomp_adapter->topic_name = $broker['stripe'];
        $this->_stomp_adapter->session_id = $this->_session_id; // ����ʱsession����
        if ($this->_stomp_adapter->connect())
        {
            BigpipeLog::debug("[Success][connected on broker][ip:%s][port:%u]", $broker['ip'], $broker['port']);
            BigpipeLog::debug('[session message id][%u]', $this->_stomp_adapter->session_message_id);
            $this->_broker = $broker;
            return true;
        }

        return false; // ����ʧ��
    }

    /**
     * @return string stripe name
     */
    private function _get_stripe_name()
    {
        return $this->_broker['stripe'];
    }

    // ��ʼ��ʱָ�������ᱻ����
    private $_pipe_name = null;
    private $_pipelet_id = null;

    private $_fo_count = null;
    private $_fo_sleep_time = null;
    
    // ���ļ��ж�ȡ������
    /** ����broker�ĳ�ʱ�ȴ�ʱ�� ��λ���� */
    private $_conn_timeo = null;
    /** �Ƿ���ȥ��: 0 ȥ��; 1 ��ȥ�� */
    private $_no_dedupe = null;
    /** �Ƿ�Ϊmessage body����checksum */
    private $_enable_checksum = null;
    /** ������Դ��� */
    private $_max_fo_cnt = null;
    
    
    private $_meta_adapter = null;  // ���task���Թ���һ������, ������
    private $_stomp_adapter = null; // ÿ��taskһ������
    private $_broker = null;

    private $_is_started = false;
    /** ��¼���һ�η��͵�message package������ǩ��*/
    private $_last_sign = null;
    private $_session_id = null;
    private $_session_msg_id = null;
    private $_last_send_ok = false;
    
}
?>
