<?php
/***************************************************************************
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/
require_once(dirname(__FILE__).'/CBmqException.class.php');
require_once(dirname(__FILE__).'/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/BigpipeConnection.class.php');
require_once(dirname(__FILE__).'/bigpipe_stomp_frames.inc.php');

/**
 * ��bigpipe stompЭ�鷢�ͽ��յķ�װ
 * @author yangzhenyu@baidu.com
 */
class BigpipeStompAdapter
{
    //=== �����ǹ�������
    /**
     * stomp connectִ�н�ɫ
     * @var BStompRoleType
     */
     public $role = null;
    
     /**
     * �û�����, Ψһ��ʶһ��stomp�ͻ���
     * ip - timestamp - pid tid- pipe name - pipelet id
     * @var string
     */
     public $session_id = null;
    
     /**
     * stripe name
     * @var string
     */
     public $topic_name = null;
    
     /**
     * connected�ɹ���ķ���ֵ
     * @var number
     */
     public $session_message_id = null;
    
    //=== �����Ƕ���ӿ�
    /**
     * Ĭ�Ϲ��캯��
     * @param BigpipeStompConf $conf
     */
    public function __construct($conf)
    {
        // ��ʼ��������
        $this->_connection = new BigpipeConnection($conf->conn_conf);
        $this->_peek_timeo_ms = $conf->peek_timeo;
        $this->_peek_time = 0;
    }
    
    /**
     * �û�����stompĿ���ַ<p>
     * Ŀ���ַ�Ǹ�array���磺<p>
     * "socket_address"  => "x.x.x.x",
     * "socket_port"     => 9527,
     * "socket_timeout"  => 300),
     * @param unknown $dest
     */
    public function set_destination($dest)
    {
        $dest_array = array($dest);
        $this->_connection->set_destinations($dest_array);
    }
    
    /**
     * ����stomp connection (client��broker������)
     * @return true on success or false on failure
     */
    public function connect()
    {
        if (!$this->_connection->create_connection())
        {
            BigpipeLog::warning('[connect error]');
            return false;
        }
        
        return $this->_stomp_connect();
    }
    
    /**
     * �ر�����
     * @return void type
     */
    public function close()
    {
        $this->_connection->close();
    }
    
    /**
     * ��Ŀ��(broker)����һ��stomp����
     * @param BigpipeFrame $cmd
     * @return true on success or false on failure
     */
    public function send($cmd)
    {
        $buff_size = $cmd->store();
        if (0 == $buff_size)
        {
            BigpipeLog::warning("[_request package error][%s]", $cmd->last_error_message());
            return false;
        }
        return $this->_connection->send($cmd->buffer(), $buff_size);
    }
    
    /**
     * ����һ��cmd
     * return cmd buffer on success or null on failure
     */
    public function receive()
    {
        $res_body = $this->_connection->receive();
        if (null != $res_body)
        {
            // �����Ƿ�Ϊ��׼�����
            $cmd_type = BigpipeFrame::get_command_type($res_body);
            if (BStompFrameType::ERROR == $cmd_type)
            {
                $recv_cmd = new BStompErrorFrame;
                if ($recv_cmd->load($res_body))
                {
                    BigpipeLog::warning("[receive error ack frame][%s][error_code:%d]",
                                        $recv_cmd->error_message, $recv_cmd->error_no);
                }
                return null;
            } // end of ����error frame
        }
        return $res_body;
    }
    
    /**
     * �ȴ�read buffer������
     * @return number error code
     */
    public function peek($timeo_ms)
    {
        $ret = $this->_connection->is_readable($timeo_ms);
        if (BigpipeErrorCode::READABLE == $ret)
        {
            // ������
            $this->_peek_time = 0;
            return BigpipeErrorCode::READABLE;
        }
        
        // ����timeout
        if (BigpipeErrorCode::TIMEOUT == $ret)
        {
            $this->_peek_time += $timeo_ms;
            if ($this->_peek_time > $this->_peek_timeo_ms
                &&
                BigpipeCommonDefine::NO_PEEK_TIMEOUT !== $this->_peek_timeo_ms)
            {
                // ����time out
                BigpipeLog::warning('[peek time out][peek timeo:%d][max peek timeo:%d]',
                $timeo_ms, $this->_peek_timeo_ms);
                $this->_peek_time = 0;
                return BigpipeErrorCode::PEEK_TIMEOUT;
            }
            else
            {
                return BigpipeErrorCode::UNREADABLE;
            }
        }
        
        BigpipeLog::warning('[fail in peek][ret:%d]', $ret);
        $this->_peek_time = 0; // ����peek timeout
        return BigpipeErrorCode::PEEK_ERROR; // ����������ʾ
    }
    
    //=== �������ڲ�����
    /**
     * ���ն˷���CONNECT����
     */
    private function _stomp_connect()
    {
        // ����CONNECT
        $cmd = new BStompConnectFrame;
        $cmd->role = $this->role;
        $cmd->session_id = $this->session_id;
        $cmd->topic_name = $this->topic_name;
        if (!$this->send($cmd))
        {
            BigpipeLog::warning("[stomp connect error]");
            return false;
        }
        
        // ����CONNECTED
        $res_body = $this->receive();
        if (null === $res_body)
        {
            BigpipeLog::warning("[stomp receive connected error]");
            return false;
        }
        
        // parse CONNECTED
        $ack = new BStompConnectedFrame;
        if (!$ack->load($res_body))
        {
            BigpipeLog::warning('[stomp parse connected frame error][cmd_type:'
                                . $ack->command_type
                                . '][msg:'
                                . $ack->last_error_message()
                                . ']');
            return false;
        }
        
        // ����session id��session message id
        $this->session_id = $ack->session_id;
        $this->session_message_id = $ack->session_message_id + 1;
        return true;
    }
    
    //=== �������ڲ�����
    // ������˽�б���
    /**
     * stomp��socket����
     * @var BigpipeConnection
     */
    private $_connection = null;
    /**
     * bigpipe connection�������ļ�
     * @var BigpipeConnectionConf
     */
    private $_conf = null;
    
    /** ��¼���ϴ�peek�ɹ����Ѿ�����peek��ʱ�� */
    private $_peek_time = 0;
    
    /**
     * peek ��ʱ�ȴ�����λ(����)<p>
     * ֵΪ�㣬��ʾ������time out
     */
    private $_peek_timeo_ms = 0;
    
} // BigpipeStompAdapter
?>
