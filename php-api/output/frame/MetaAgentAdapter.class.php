<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
**************************************************************************/
require_once(dirname(__FILE__)."/CBmqException.class.php");
require_once(dirname(__FILE__)."/BigpipeLog.class.php");
require_once(dirname(__FILE__).'/BigpipeConnection.class.php');
require_once(dirname(__FILE__)."/meta_agent_frames.inc.php");

/**
 *  php adapter for meta agent<p>
 *  ע��: �ڸ���metaʱ������ĵ�pipelet id�ķ�Χ��[0, num_pipelet), <p>
 *  ����ʱ���pipelet name��pipelet id�ķ�Χ��(0, num_pipelet] <p>
*/
class MetaAgentAdapter
{

    /**
     * Initialize the adapter, connect client to the meta agent, and.
     * @return true on success or false on failure.
     */
    public function __construct()
    {
        $this->_inited = false;
    }

    public function __destruct()
    {
        // meta��Դ��δ�ͷŹ�
        if (true === $this->_inited)
        {
            $this->uninit();
        }
    }

    /**
     * ��ʼ��meta_adapter
     * @param array $conf:configure array
     * @return true on success or false on failure
     */
    public function init($conf)
    {
        if (true === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][multi-init]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        $this->_conf = $conf;
        // ����һ��connection
        $this->_connection = new BigpipeConnection($conf->conn_conf);
        $this->_connection->set_destinations($conf->agents);
        $this->_inited = true;
        return true;
    }

    public function uninit()
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][mulit-unit]",
            __FILE__, __LINE__, __FUNCTION__);
            return;
        }

        $this->close();
        $this->_inited = false;
    }

    /**
     * Close the connection and clean all the variables
     * @return void type
     */
    public function close()
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][call uninited object]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // Ϊ�˼��ٷ��������������˲�����meta agent��������uninit meta����
        // $this->_uninit_meta($this->meta_name); // �����ͷ�meta
        $this->_disconnect(); // �ر�����

        // reset the variables
        $this->_curr_agent = 0;
        $this->last_error_message = null;
        $this->meta_name = null;
    }

    /**
     * Connect to meta agent and create a meta instance on the meta agent
     * @param no parameter
     * @return true on success or false on failure.
     */
    public function connect()
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][call uninited object]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (!$this->_init_meta($this->_conf->meta))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to connect to agent]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        return true;
    }

    /**
     * �����ͷ�session
     * @param array $session_param
     * $session_param = array (
     * 'session'    =>,
     * 'session_id' =>,
     * 'session_timestamp' =>,
     */
    public function release_session($session_param)
    {
        if (false === is_array($session_param) ||
            false === isset($session_param['session']) ||
            false === isset($session_param['session_id']) ||
            false === isset($session_param['session_timestamp']))
        {
            BigpipeLog::warning('[%s:%u][%s][invalid session params]',
            __FILE__, __LINE__, __FUNCTION__);
            return; // ����Ҫ�ͷ�meta
        }
        // create uninit_meta_command
        $cmd = new UninitApiFrame();
        $cmd->session = $session_param['session'];
        $cmd->session_id = $session_param['session_id'];
        $cmd->session_timestamp = $session_param['session_timestamp'];
        
        // send
        $res_body = $this->_request($cmd);
        if (null === $res_body)
        {
            BigpipeLog::warning('[%s:%u][%s][no ack][%s][%s]',
            __FILE__, __LINE__, __FUNCTION__,
            $this->last_error_message, $cmd->last_error_message());
            $this->last_error_message = "release session no ack";
            return;
        }
        
        // parse ack
        $ack = new UninitApiAckFrame();
        if (!$ack->load($res_body))
        {
            $this->last_error_message = 'release session error ack';
            $this->meta_name = null;
            BigpipeLog::warning('[%s:%u][%s][ack error][%s][%s]',
            __FILE__, __LINE__, __FUNCTION__, $this->last_error_message, $cmd->last_error_message());
            return;
        }
        
        return;
    }

    /**
     * Connect to meta agent, create a meta instance on the meta agent
     * and authroize user
     * @param no parameter
     * @return pipelet number and fetched session of connected pipe on success or false on failure.
     */
    public function connect_ex($pipe_name, $token, $role)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][call uninited object]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // �������ڲ����ýӿڣ���˿��Բ�����������
        $ret = $this->_init_api($pipe_name, $token, $role, $this->_conf->meta);
        if (false === $ret)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to connect to agent]",
            __FILE__, __LINE__, __FUNCTION__);
        }
        else if (false == $ret['authorized'])
        {
            BigpipeLog::warning('[%s:%u][%s][fail to authorize][reason:%s]',
            __FILE__, __LINE__, __FUNCTION__, $ret['reason']);
            $ret = false;
        }

        return $ret;
    }

    /**
     * ��meta�л�ȡ�ɷ�����broker
     * @param string $pipe_name : pipe name
     * @param number $pipelet_id: pipelet id
     * @return �ɹ�����broker array('ip'=>, 'port'=>, 'stripe'=>)��ʧ�ܷ���boolean false
     */
    public function get_pub_broker($pipe_name, $pipelet_id)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][call uninited object]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (empty($this->meta_name))
        {
            // ȱ�ٹؼ�����
            BigpipeLog::warning("[%s:%u][%s][missing meta name]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // pack reqestion
        $cmd = new GetPubInfoFrame;
        $cmd->meta_name = $this->meta_name;
        $cmd->pipe_name = $pipe_name;
        $cmd->pipelet_id = $pipelet_id + 1; // �������pipelet name
        // send
        $res_body = $this->_request($cmd);
        if (null === $res_body)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to get pub info][meta:%s][pipe_name:%s][pipelet_id:%d]",
            __FILE__, __LINE__, __FUNCTION__, $this->meta_name, $pipe_name, $pipelet_id);
            return false;
        }

        // parse ack
        $ack = new GetPubInfoAckFrame();
        if (!$ack->load($res_body))
        {
            BigpipeLog::warning("[%s:%u][%s][error ack]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $broker = array(
                'ip'     => $ack->broker_ip,
                'port'   => $ack->broker_port,
                'stripe' => $ack->stripe_name,
        );
        return $broker;
    }

    /**
     * ��meta�л�ȡ�ɶ��ĵ�broker group
     * @return sub_info on success or false on failure
     */
    public function get_sub_broker_group($pipe_name, $pipelet_id, $start_point)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][call uninited object]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (empty($this->meta_name))
        {
            // ȱ�ٹؼ�����
            BigpipeLog::warning("[%s:%u][%s][missing meta name]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // pack reqestion
        $cmd = new GetSubInfoFrame;
        $cmd->meta_name = $this->meta_name;
        $cmd->pipe_name = $pipe_name;
        $cmd->pipelet_id = $pipelet_id + 1;  // �������pipelet name
        $cmd->start_point = $start_point;
        // send
        $res_body = $this->_request($cmd);
        if (null === $res_body)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to get sub info][meta:%s][pipe_name:%s][pipelet_id:%d][start_point:%d]",
            __FILE__, __LINE__, __FUNCTION__, $this->meta_name, $pipe_name, $pipelet_id, $start_point);
            return false;
        }

        // parse ack
        $ack = new GetSubInfoAckFrame();
        if (!$ack->load($res_body))
        {
            BigpipeLog::warning("[%s:%u][%s][error ack]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $broker_group = json_decode($ack->broker_group);
        $sub_info = array(
                'stripe_name'  => $ack->stripe_name,
                'stripe_id'    => $ack->stripe_id,
                'begin_pos'    => $ack->begin_pos,
                'end_pos'      => $ack->end_pos,
                'broker_group' => $broker_group,);
        return $sub_info;
    }

    /**
     * ͨ��meta��ȡ��֤��Ϣ
     * @param string $pipe_name
     * @param string $token
     * @param const  $role (
     * @return authorization reuslt on success or fasle on failure
     */
    public function authorize($pipe_name, $token, $role)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][call uninited object]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (empty($this->meta_name))
        {
            // ȱ�ٹؼ�����
            BigpipeLog::warning("[%s:%u][%s][missing meta name]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // pack reqestion
        $cmd = new AuthorizeFrame;
        $cmd->meta_name = $this->meta_name;
        $cmd->pipe_name = $pipe_name;
        $cmd->role = $role;
        $cmd->token = $token;
        // send
        $res_body = $this->_request($cmd);
        if (null === $res_body)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to autorize][meta:%s][pipe_name:%s][token:%d]",
            __FILE__, __LINE__, __FUNCTION__, $this->meta_name, $pipe_name, $token);
            return false;
        }

        // parse ack
        $ack = new AuthorizeAckFrame;
        if (!$ack->load($res_body))
        {
            BigpipeLog::warning("[%s:%u][%s][ack error]",
            __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ������֤״̬
        $author_result = array();
        if (BigpipeErrorCode::OK == $ack->status)
        {
            $author_result['authorized'] = true;
            $author_result['num_pipelet'] = $ack->num_pipelet;
        }
        else
        {
            // ��֤��ͨ��
            $author_result['authorized'] = false;
            $author_result['reason'] = $ack->error_message;
        }

        return $author_result;
    }

    /**
     *  ���һ�δ�����Ϣ
     *  @var string
     */
    public $last_error_message = null;

    /**
     * Ψһ��ʶһ����meta agent�ϵ�metaʵ��
     * @var string
     */
    public $meta_name = null;

    /**
     * Disconnect between php client and meta agent
     * @return void type
     */
    private function _disconnect()
    {
        if ($this->_connected())
        {
            // �ͷ�����
            $this->_connection->close();
        }
    }

    /**
     * Create a connection between meta agent and php client<p>
     * NOTE: This function CAN NOT be called by any functions except being called by connect()
     * @param no parameter
     * @return true on success or false on failure.
     */
    private function _create_connection()
    {
        if ($this->_connected())
        {
            $this->_disconnect(); // ��ֹ�ظ�����connection
        }

        return $this->_connection->create_connection();
    }

    /**
     * ��meta agent����һ�������
     * @param $cmd_frame: һ�������
     * @return �ɹ��򷵻�һ��nshead��Ӧ��nshead+body��, ʧ���򷵻�null
     */
    private function _request($cmd_frame)
    {
        if (!$this->_create_connection())
        {
            $this->last_error_message = 'CAN NOT connect to meta agent';
            BigpipeLog::warning("[%s:%u][%s][connect error][err_msg:%s]",
            __FILE__, __LINE__, __FUNCTION__, $this->last_error_message);
            return null; // ����ʧ��
        }

        $buff_size = $cmd_frame->store();
        if (0 == $buff_size)
        {
            $this->last_error_message = $cmd_frame->last_error_message();
            BigpipeLog::warning("[%s:%u][%s][package error][err_msg:%s]",
            __FILE__, __LINE__, __FUNCTION__, $this->last_error_message);
            return null;
        }

        if (!$this->_connection->send($cmd_frame->buffer(), $buff_size))
        {
            $this->last_error_message = 'FAIL to write socket';
            BigpipeLog::warning('[%s:%u][%s][net error]',
            __FILE__, __LINE__, __FUNCTION__);
            return null;
        }

        return $this->_wait_for_ack();
    }

    /**
     * ��meta agent����һ��ack��
     * @return һ��nshead��Ӧ��nshead+body��
     */
    private function _wait_for_ack()
    {
        // ������ֻ��request�б�����, ��˲��ÿ���connect������
        $res_body = $this->_connection->receive(); // ����ack
        $is_ok = false;
        do
        {
            if (null === $res_body)
            {
                // ack �����ڻ����ʧ��
                $this->last_error_message = 'fail to read response';
                break;
            }

            // ��ȡ������ack��
            if(!$this->_ack_status_ok($res_body))
            {
                break;
            }

            $is_ok = true;
        } while (false); // ���ڷ�֧���
        $this->_disconnect(); // ��������
        if (!$is_ok)
        {
            // ��ӡ������־
            BigpipeLog::warning("[%s:%u][%s][ack error][err_msg:%s]",
            __FILE__, __LINE__, __FUNCTION__, $this->last_error_message);
            return null;
        }
        return $res_body;
    }

    /**
     * ���ack״̬��������ص��Ǳ�׼�������˵�������ʧ���ˣ���ȡ������Ϣ
     * @param binary string $res_body: ��Ӧ��Ϣ��
     * @return false���ack����status ok, ���򷵻�true
     */
    private function _ack_status_ok($res_body)
    {
        // load command type
        $type = BigpipeFrame::get_command_type($res_body);
        if (MetaAgentFrameType::UNKNOWN_TYPE == $type)
        {
            BigpipeLog::warning('[no cmd_type in ack]');
            return false;
        }
    
        if ($type == MetaAgentFrameType::ACK_ERROR_PACK)
        {
            // �д��󣬷��ص��Ǵ�����ʾ��
            $ack = new MetaAgentErrorAckFrame();
            if (!$ack->load($res_body))
            {
                $this->last_error_message = $ack->last_error_message();
            }
            else
            {
                $this->last_error_message = $ack->error_msg;
            }
            BigpipeLog::warning('[%s:%u][%s][ack error][cmd_type:%d][err:%s]',
            __FILE__, __LINE__, __FUNCTION__, $ack->command_type, $this->last_error_message);
            return false; // ��������ack
        } // �����׼�����
        return true;
    }

    /**
     * ��ȡһ��connection
     * @return ���connection�����򷵻�true������������򷵻�false
     */
    private function _connected()
    {
        return $this->_connection->is_connected();
    }

    /**
     * Initialize the meta on the meta agent
     * @param $conf : array of meta parameters
     * @return true on success or false on failure
     */
    private function _init_meta($meta_params)
    {
        // create init_meta_command
        $cmd = new InitMetaFrame();
        if (!$cmd->pack($meta_params))
        {
            $this->last_error_message = "_init_meta error";
            return false;
        }

        // send
        $res_body = $this->_request($cmd);
        if (null === $res_body)
        {
            $this->last_error_message = "_init_meta no ack";
            return false;
        }

        // parse ack
        $ack = new InitMetaAckFrame();
        if (!$ack->load($res_body))
        {
            $this->last_error_message = '_init_meta error ack';
            return false;
        }

        $this->meta_name = $ack->meta_name; // �ɹ���õ�meta name
        return true;
    }

    /**
     * Uninitialize the meta on meta agent
     * @param $meta_name : string of meta name
     * @return void type
     */
    private function _uninit_meta($meta_name)
    {
        if (null == $this->meta_name)
        {
            return; // ����Ҫ�ͷ�meta
        }
        // create uninit_meta_command
        $cmd = new UninitMetaFrame();
        $cmd->meta_name = $this->meta_name;

        // send
        $res_body = $this->_request($cmd);
        if (null === $res_body)
        {
            $this->meta_name = null;
            BigpipeLog::warning('[uninit_meta error][%s][%s]',
            $this->last_error_message, $cmd->last_error_message());
            $this->last_error_message = "_uninit_meta no ack";
            return;
        }

        // parse ack
        $ack = new UninitMetaAckFrame();
        if (!$ack->load($res_body))
        {
            $this->last_error_message = '_uninit_meta error ack';
            $this->meta_name = null;
            BigpipeLog::warning('[%s:%u][%s][ack error][%s][%s]',
            __FILE__, __LINE__, __FUNCTION__, $this->last_error_message, $cmd->last_error_message());
            return;
        }

        $this->meta_name = null;
    }

    /**
     * ����InitApiFrame
     * @param $conf : array of meta parameters
     * @return authorize result on success or false on failure
     */
    private function _init_api($pipe_name, $token, $role, $meta_params)
    {
        // create init_api_command
        $cmd = new InitApiFrame();
    
        // ����array������meta������ʼ��frame
        if (!$cmd->pack($meta_params))
        {
            $this->last_error_message = "_init_meta error";
            return false;
        }
        // �����֤���ֲ���
        $cmd->pipe_name = $pipe_name;
        $cmd->token = $token;
        $cmd->role = $role;

        // send
        $res_body = $this->_request($cmd);
        if (null === $res_body)
        {
            $this->last_error_message = "_init_meta no ack";
            return false;
        }
    
        // parse ack
        $ack = new InitApiAckFrame();
        if (!$ack->load($res_body))
        {
            $this->last_error_message = '_init_meta error ack';
            return false;
        }

        // ��䲢����init���
        $init_result = array();
        if (BigpipeErrorCode::OK == $ack->status)
        {
            $init_result['authorized'] = true;
            $init_result['num_pipelet'] = $ack->num_pipelet;
            $init_result['session'] = $ack->session;
            $init_result['session_id'] = $ack->session_id;
            $init_result['session_timestamp'] = $ack->session_timestamp;
        }
        else
        {
            // ��֤��ͨ��
            $init_result['authorized'] = false;
            $init_result['reason'] = $ack->error_message;
        }
        
        $this->meta_name = $ack->meta_name; // �ɹ���õ�meta name
        return $init_result;
    }

    /** ��ʼ����־λ */
    private $_inited = null;

    /**
     *  meta agent����������
     *  @var MetaAgentConf
     */
    private $_conf = null;

    /**
     * ��ǰ��ѡ��agent������ֵ
     * @var int
     */
    private $_curr_agent = 0;

    /**
     * client��meta agent֮�������
     * @var BigpipeConnection
     */
    private $_connection = null;
} // end of MetaAgentAdapter
?>
