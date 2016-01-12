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
 * 对bigpipe stomp协议发送接收的封装
 * @author yangzhenyu@baidu.com
 */
class BigpipeStompAdapter
{
    //=== 以下是公共变量
    /**
     * stomp connect执行角色
     * @var BStompRoleType
     */
     public $role = null;
    
     /**
     * 用户生成, 唯一标识一个stomp客户端
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
     * connected成功后的返回值
     * @var number
     */
     public $session_message_id = null;
    
    //=== 以下是对外接口
    /**
     * 默认构造函数
     * @param BigpipeStompConf $conf
     */
    public function __construct($conf)
    {
        // 初始化连接类
        $this->_connection = new BigpipeConnection($conf->conn_conf);
        $this->_peek_timeo_ms = $conf->peek_timeo;
        $this->_peek_time = 0;
    }
    
    /**
     * 用户设置stomp目标地址<p>
     * 目标地址是个array型如：<p>
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
     * 建立stomp connection (client与broker的连接)
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
     * 关闭连接
     * @return void type
     */
    public function close()
    {
        $this->_connection->close();
    }
    
    /**
     * 向目标(broker)发送一条stomp命令
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
     * 接收一条cmd
     * return cmd buffer on success or null on failure
     */
    public function receive()
    {
        $res_body = $this->_connection->receive();
        if (null != $res_body)
        {
            // 看看是否为标准错误包
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
            } // end of 解析error frame
        }
        return $res_body;
    }
    
    /**
     * 等待read buffer来数据
     * @return number error code
     */
    public function peek($timeo_ms)
    {
        $ret = $this->_connection->is_readable($timeo_ms);
        if (BigpipeErrorCode::READABLE == $ret)
        {
            // 有数据
            $this->_peek_time = 0;
            return BigpipeErrorCode::READABLE;
        }
        
        // 处理timeout
        if (BigpipeErrorCode::TIMEOUT == $ret)
        {
            $this->_peek_time += $timeo_ms;
            if ($this->_peek_time > $this->_peek_timeo_ms
                &&
                BigpipeCommonDefine::NO_PEEK_TIMEOUT !== $this->_peek_timeo_ms)
            {
                // 返回time out
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
        $this->_peek_time = 0; // 重置peek timeout
        return BigpipeErrorCode::PEEK_ERROR; // 其它错误提示
    }
    
    //=== 以下是内部函数
    /**
     * 向终端发送CONNECT命令
     */
    private function _stomp_connect()
    {
        // 发送CONNECT
        $cmd = new BStompConnectFrame;
        $cmd->role = $this->role;
        $cmd->session_id = $this->session_id;
        $cmd->topic_name = $this->topic_name;
        if (!$this->send($cmd))
        {
            BigpipeLog::warning("[stomp connect error]");
            return false;
        }
        
        // 接收CONNECTED
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
        
        // 更新session id和session message id
        $this->session_id = $ack->session_id;
        $this->session_message_id = $ack->session_message_id + 1;
        return true;
    }
    
    //=== 以下是内部变量
    // 以下是私有变量
    /**
     * stomp的socket连接
     * @var BigpipeConnection
     */
    private $_connection = null;
    /**
     * bigpipe connection的配置文件
     * @var BigpipeConnectionConf
     */
    private $_conf = null;
    
    /** 记录从上次peek成功后已经连续peek的时间 */
    private $_peek_time = 0;
    
    /**
     * peek 超时等待，单位(毫秒)<p>
     * 值为零，表示不考虑time out
     */
    private $_peek_timeo_ms = 0;
    
} // BigpipeStompAdapter
?>
