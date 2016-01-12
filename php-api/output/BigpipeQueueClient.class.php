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
 * queue mesage ��Ϣ��ʽ
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
     * ����queue message��
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
 * bigpipe queue server�ͻ���
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
     * ��ʼ��queue server client
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

        // ��ʼ��
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
     * ��queue server���ͽ�������(���ڵ�һ��peek, ��failoverʱ��������), ���ȴ���Ϣ����
     * @param int64_t $timo_ms �ȴ���ʱ��ms��
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
            // ���ֻ�û�ж��Ĵ�queue server��������ʱ, ���Է�����
            if (!$this->refresh())
            {
                return BigpipeErrorCode::ERROR_SUBSCRIBE;
            }
        }

        return $this->_peek($timo_ms);
    }

    /**
     * ������Ϣ
     * @return ���ճɹ����� һ��BigpipeQueueMessage���͵���Ϣ; ����ʧ��, ����false
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
            // û�ж���
            BigpipeLog::warning('[receive from unsbscribed queue][name:%s]', $this->_meta->queue_name());
            return false;
        }
        return $this->_receive();
    }

    /**
     * ˢ��������Ϣ (receiveʧ�ܺ����)
     * @return true on success or false on failure
     */
    public function refresh()
    {
        //�̰߳�ȫ
        usleep($this->_sleep_timeo);

        // refresh��ʱ�������׼�������ͱ���ʱ��
        $this->_base_srvtime = 0;
        $this->_base_loctime = 0;

        if (false === $this->_inited)
        {
            BigpipeLog::fatal("[%s:%u][%s][client is not inited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // �ر�����, ȡ��ԭ�ж���
        if (true === $this->_subscribed)
        {
            $this->_disconnect();
        }

        if (false === $this->_connect())
        {
            BigpipeLog::warning('[refresh][fail to connect to queue server]');
            return false; // ����ʧ��
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
     * ֪ͨqueue server�ѽ��յ�message
     * @param BigpipeQueueMessage $msg : ����û��message_body, 
     *                            ���ǽṹ�е�������Ϣ������д����,
     *                            $is_slow_cli :
     *                            ����Ƿ����������ѿͻ��ˣ�Ĭ���ǿ������ѿͻ���
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

        // ������
        if (empty($msg->pipe_name))
        {
            return false;
        }

        //�����Ϣ��ʱ
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
                //�������Ѷˣ��ظ���ʵack����server������ʵ���ݣ�
                $ack->setcmd_no(BigpipeQueueSvrCmdType::ACK_QUEUE_TRUE_DATA);
            }
            else
            {   
                //�������Ѷˣ��ظ����ack����server����������ݣ�
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
            // ����package
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
        // ���name��token�Ƿ����
        if (empty($name) || empty($token))
        {
            BigpipeLog::fatal("[_init][miss queue name or queue token]");
            return false;
        }

        // ��ʼ��queue server meta
        $this->_meta = (isset($this->unittest)) ? $this->stub_meta : new QueueServerMeta;
        if (false === $this->_meta->init($name, $token, $conf->meta))
        {
            BigpipeLog::fatal("[_init][fail to init meta]");
            return false;
        }

        // ��ʼ��bigpipe connection
        $this->_connection = new BigpipeConnection($conf->conn);
        $this->_wnd_size = $conf->wnd_size;
        $this->_rw_timeo = $conf->rw_timeo;
        $this->_peek_timeo = $conf->peek_timeo;
        $this->_delay_ratio = $conf->delay_ratio;
        $this->_sleep_timeo = $conf->sleep_timeo;
    }

    /**
     * ˢ��queue server meta��Ϣ, ��queue server���·�������
     * @return true on success or false on failure
     */
    private function _connect()
    {
        if (true == $this->_subscribed)
        {
            // �ȳ���ȡ������, ���ǲ��ÿ��Ǵ��� (��Ϊfailover���д����ǳ�̬)
            $this->_disconnect();
            $this->_subscribed = false;
        }

        // ��meta����queue server��Ϣ
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
     * �ȴ�read buffer������
     * @return number error code
     */
    private function _peek($timeo_ms)
    {
        $ret = $this->_connection->is_readable($timeo_ms);
        if (BigpipeErrorCode::READABLE == $ret)
        {
            // ������, ����peek��ʱ
            $this->_peek_time = 0;
            $ret = BigpipeErrorCode::READABLE;
        }
        else if (BigpipeErrorCode::TIMEOUT == $ret)
        {
            // ����timeout
            $this->_peek_time += $timeo_ms;
            if ($this->_peek_time > $this->_peek_timeo
                && BigpipeCommonDefine::NO_PEEK_TIMEOUT !== $this->_peek_timeo)
            {
                // ����time out
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
            // ����������ʾ
            BigpipeLog::warning('[fail in peek][ret:%d]', $ret);
            $ret = BigpipeErrorCode::PEEK_ERROR;
        }
        return $ret;
    }

    /**
     * ��queue server��������
     * @return bool
     */
    private function _subscribe()
    {
        // ������
        if (true === $this->_subscribed)
        {
            // Ϊ�˷�ֹ������뵼�µ��ظ����ģ����ظ�����ʱ������false
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
            // ����package
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
     * ��read buffer��ȡһ��message package
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
        // ���ճɹ�����ȡ����
        $pkg_data = @mc_pack_pack2array($res_body);
        if (false === $pkg_data)
        {
            BigpipeLog::warning('[_receive][unknown data package format][%s]', $res_body);
            break;
        }

        try
        {
            // �ɹ���ȡ���ݲ���ʼ���
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
                // ���ճɹ�, ���message
                $msg = new BigpipeQueueMessage;
                $msg->pipe_name = $pkg->getpipe_name();
                $msg->pipelet_id = $pkg->getpipelet_id();
                $msg->pipelet_msg_id = $pkg->getpipelet_msg_id();
                $msg->seq_id = $pkg->getseq_id();
                $msg->message_body = $pkg->getmsg_body();
                $msg->cur_srvtime = $pkg->getsrvtime();  //������ʱ���
                $msg->timeout = $pkg->gettimeout();      //��ȡ��ʱʱ��
                $msg->msg_flag = $pkg->getmsg_flag();    //��ȡ��Ϣ����

                BigpipeLog::notice("[_receive][recv msg][pipe_name:%s] [pipelet_id:%d] [pipelet_msg_id:%d] [seq_id:%d] [len:%u] [srvtime:%d] [timeout:%d] [msg_flag:%d]",
                    $msg->pipe_name, $msg->pipelet_id, $msg->pipelet_msg_id,
                    $msg->seq_id, strlen($msg->message_body), $msg->cur_srvtime, $msg->timeout, $msg->msg_flag);
            }
            // ����fake message������ͻ��˻ظ���ʵ��ack,�ȴ�server����ʵ��Ϣ(����Ϣ������Ϣ)����ʹʧ�ܣ�����������Ҳֻ�ǿͻ���refreshһ��
            if(1 === $msg->msg_flag)
            {
                $msg = $this->_wait_real_msg($msg);
            }
        }
        catch(ErrorException $e)
        {
            // message������
            BigpipeLog::warning('[_receive][load package error][%s]', $e->getMessage());
        }
    } while (false);

        return $msg;
    }

    /**
     * �ر�socket����, �������״̬
     * @return void type
     */
    private function _disconnect()
    {
        $this->_connection->close();
        $this->_subscribed = false;
    }


    /**
     * �����Ϣ�Ƿ�ʱ
     * @return true on timeout
     */
    private function _check_timeout($msg)
    {
        $cur_machine_time = @gettimeofday();
        $cur_loctime = $cur_machine_time['sec']*1000000+$cur_machine_time['usec'];

        //first blood!�������Ӻ��յ���һ����Ϣ�����л�׼ʱ����趨
        if(0 === $this->_base_srvtime)
        {
            //���»�׼��Ϣ�ı���ʱ��ͷ�����ʱ��
            $this->_base_loctime = $cur_loctime;
            $this->_base_srvtime = $msg->cur_srvtime;
            return false;
        }

        //�ͻ����ж���Ϣ�ѳ�ʱ
        if($cur_loctime - $this->_base_loctime - ($msg->cur_srvtime - $this->_base_srvtime) > ($msg->timeout)*$this->_delay_ratio*1000000)
        {
            BigpipeLog::warning('[_check_timeout][msg timeout,will not send ack]');
            return true;
        }

        return false;
    }

    /**
     * ��ͻ��˻ظ���ʵ��ack,�ȴ�server����ʵ��Ϣ
     * @return void type
     */
    private function _wait_real_msg($msg)
    {

        //�ظ�ack���������ѿͻ��ˣ�
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

    /** QueueServerMetaʵ�� */
    private $_meta = null;
    ///** meta������ */
    //private $_meta_conf = null;

    /** ��ʶclient�Ƿ��Ѿ�����ʼ�� */
    private $_inited = false;
    /** ��ʶclient�Ƿ��Ѿ���queue server������Ϣ */
    private $_subscribed = false;

    /**
     * ��queue server��socket connection
     * @var: BigpipeConnection
     */
    private $_connection = null;
    /** �Ѿ�peek��ʱ (��λ: ����) */
    private $_peek_time = null;
    /** peek�ĳ�ʱʱ�� (��λ: ����) */
    private $_peek_timeo = null;

    /** ָ��queue��server�е���Ϣ���մ��Ĵ�С*/
    private $_wnd_size = null;
    /** ָ��client socket connection�Ķ�д time out (��λ������) */
    private $_rw_timeo = null;
    /** ���յ��ĵ�һ����Ϣ�ķ�����ʱ��                            */
    private $_base_srvtime = null;
    /** ���յ��ĵ�һ����Ϣ�ı���ʱ��               */
    private $_base_loctime = null;
    /** ��Ϣ��ʱ���ػ��ʣ�0-1������0.8            */
    private $_delay_ratio = null;
    /** api �汾��  						   */
    private $_api_version = null;
    /** refresh��˯��ʱ�� (��λ: ����)           */
    private $_sleep_timeo = null;

    //     /** ��������ʧ��, ��������·������ӵĴ��� */
    //     private $max_try_count = null;
    //     /** ÿ������ʧ�ܺ�, ˯�ߵ�ʱ�� (��ֹqueue serverʧЧʱ, ��clientƵ����������) */
    //     private $conn_wait_time = null;

    //     const FAILOVER_BASE_WAIT_TIME = 100000; /** wait 100ms */

} // end of BigpipeQueueClient
/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
