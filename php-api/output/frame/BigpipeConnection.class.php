<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
****************************************************************************/
require_once(dirname(__FILE__).'/../ext/CNsHead.class.php');
require_once(dirname(__FILE__).'/../ext/socket.inc.php');
require_once(dirname(__FILE__).'/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/bigpipe_utilities.inc.php');
require_once(dirname(__FILE__).'/BigpipeLog.class.php');

/**
 * 对c_socket的封装，用于发送和接收nshead协议
 * @author yangzhenyu@baidu.com
*/
class BigpipeConnection
{
    /**
     * 构造函数
     * @param BigpipeConnectionConf $conf
     */
    public function __construct($conf)
    {
        // todo 加入对$conf的检查（出错抛出异常）
        $this->_max_try_time = $conf->try_time;
        $this->_conn_timeo = $conf->conn_timeo;
        $this->_check_frame = $conf->check_frame;
        //set_time_limit($conf->time_limit);
    }

    /**
     * 析构时关闭连接
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 用户设置目标地址列表<p>
     * 目标地址列表是一个array, 其元素是目标地址<p>
     * 目标地址也是个array型如：<p>
     * array( "socket_address"  => "x.x.x.x",
     *        "socket_port"     => 9527,
     *        "socket_timeout"  => 300,
     *        ...);
     * @param array $dest_list
     * @return void type
     */
    public function set_destinations($dest_list)
    {
        $this->_destinations = $dest_list;
    }

    /**
     * 关闭连接
     */
    public function close()
    {
        if (!empty($this->_socket))
        {
            // 释放连接
            $this->_socket->close();
        }
        $this->_socket = null;
        $this->_target = null;
    }

    /**
     * 检查connection是否有效
     * @return 如果connection存在则返回true，如果不存在则返回false
     */
    public function is_connected()
    {
        if (!empty($this->_socket) && $this->_socket->is_connected())
        {
            return true;
        }

        return false;
    }

    /**
     * 发送一条nshead封装的消息
     * @return true on success or false on failure
     */
    public function send($buffer, $buffer_size)
    {
        if (null == $this->_socket) // 简单判断socket，如果无连接write将失败
        {
            BigpipeLog::warning('[send error][lose connection][target:%s:%u]',
            $this->_target['socket_address'], $this->_target['socket_port']);
            return false; // 无连接
        }

        // build nshead package
        if (0 == $buffer_size)
        {
            BigpipeLog::warning('[send error][empty buffer]');
            return false;
        }
        $req_head = new NsHead;
        $req_arr = array('body_len' => $buffer_size,);
        if (true === $this->_check_frame)
        {
            // 整包校验写在nshead的reserved字段中
            $req_arr['magic_num'] = BigpipeCommonDefine::NSHEAD_CHECKSUM_MAGICNUM;
            $req_arr['reserved'] = BigpipeUtilities::adler32($buffer);
        }
        $data = $req_head->build_nshead($req_arr) . $buffer;

        BigpipeLog::debug("[send data][%s]", $data);
        if (!$this->_socket->write($data))
        {
            BigpipeLog::warning('[fail to send data][may be network error]');
            return false;
        }
        return true;
    }

    /**
     * 解析一条nshead封装的消息
     * @return 如果成功，返回收到的nshead body; 如果失败，返回null
     */
    public function receive()
    {
        if (null == $this->_socket)  // 简单判断socket，如果无连接read将失败
        {
            BigpipeLog::warning('[receive error][lose connection with %s:%u]',
            $this->_target['socket_address'], $this->_target['socket_port']);
            return null; // 无连接
        }

        $res = array();
        $buff = $this->_socket->read(36); // 读取一个nshead头
        BigpipeLog::debug("[receive nshead][$buff]");
        if (empty($buff) || strlen($buff) < 36)
        {
            $buff_size = strlen($buff);
            BigpipeLog::warning("[receive nshead error][head_size:$buff_size]");
            return null;
        }

        $nshead = new NsHead();
        $res['head'] = $nshead->split_nshead($buff, false);
        $is_ok = false;
        $err_msg = null;
        do
        {
            // 读取nshead
            // 下述判断理论上到不了
            $res_head = $res['head'];
            if (false === $res_head)
            {
                $err_msg = '[fail to read head]';
                break;
            }

            if (!isset($res_head['body_len']) || 0 == $res_head['body_len'])
            {
                // 不可能有size为0的包
                $err_msg = '[no body_len]';
                break;
            }

            //读取数据包内容
            $res['body'] = $this->_socket->read($res_head['body_len']);
            if (false === $res['body'])
            {
                $err_msg = '[no message body]';
                break;
            }

            if (true === $this->_check_frame)
            {
                // 整包校验
                if (BigpipeCommonDefine::NSHEAD_CHECKSUM_MAGICNUM != $res_head['magic_num'])
                {
                    // 包不带checksum也没问题，但是要打印日志追一下
                    BigpipeLog::debug('[receive][frame does not have checksum, skip checksum]');
                }
                else
                {
                    // checksum在nshead, reserved字段中
                    $checksum = BigpipeUtilities::adler32($res['body']);
                    if ($res_head['reserved'] != $checksum)
                    {
                        $err_msg = sprintf('[checksum failed][send:%u][recv:%u]', 
                                           $res_head['reserved'], $checksum);
                        $res['body'] = null;
                        break; // 校验失败，丢弃包
                    }
                }
            } // end of check frame integerity
            $is_ok = true;
        } while (false); // 用于分支检测

        if (!$is_ok)
        {
            // 打印错误日志
            BigpipeLog::warning("[receive][frame error]%s", $err_msg);
            return null;
        }
        return $res['body'];
    }

    /**
     * Create a connection between server and php client<p>
     * Or close the existing connection and re-connect it.
     * @param no parameter
     * @return true on success or false on failure.
     */
    public function create_connection()
    {
        if ($this->is_connected())
        {
            $this->close(); // 防止重复创建connection
        }

        $agents = $this->_destinations;
        $num = count($agents);
        if (0 == $num)
        {
            BigpipeLog::warning("[no destination]");
            return false;
        }
        $try = $this->_max_try_time;
        $curr_agent = rand() % $num;
        $idx = $curr_agent; // 从curr_agent开始尝试连接meta agent
        while ($try-- > 0)
        {
            $agent = $agents[$idx];
            // $stub_sock用于测试时传入mocked c_socket
            $socket = (true === isset($this->unittest)) ? $this->stub_sock : new c_socket();
            $agent['socket_timeout'] = $this->_conn_timeo; // c_socket内部使用socket_timeout作为read/write timeout
            $socket->set_vars_from_array($agent);

            //<<< 调试信息
            BigpipeLog::debug('[connect to][%s:%u]', $agent['socket_address'], $agent['socket_port']);

            // 连接agent
            if ($socket->connect($this->_conn_timeo))
            {
                $this->_socket = $socket;
                $this->_target = $agent; // 记录连接对象
                break;
            }
            else // 连接失败
            {
                // 换地址重试
                if (!array_key_exists($idx + 1, $agents))
                {
                    // next idx will be out of range
                    // reset $idx and try from the first agent
                    $idx = 0;
                }
                else
                {
                    $idx++;
                }
            } // end of connect
        } // end of try
        return $this->is_connected();
    }

    /**
     * Check if there is a frame to read
     * @param $timeo: 用户指定的超时等待值，如果无指定则读取配置(单位：毫秒)
     * @return number
     */
    public function is_readable($timeo_ms)
    {
        if (empty($timeo_ms) || $timeo_ms <= 0)
        {
            // 参数错误
            BigpipeLog::warning("[is_readable][invalid parameter]");
            return BigpipeErrorCode::INVALID_PARAM;
        }

        $timeo_s = floor($timeo_ms / 1000);
        $timeo_us = ($timeo_ms % 1000) * 1000;
        $ret = $this->_socket->is_readable($timeo_s, $timeo_us);
        if (c_socket::ERROR == $ret)
        {
            $msg = $this->_socket->__get('socket_error');
            BigpipeLog::warning("[peek to read error][$msg]");
            $ret = BigpipeErrorCode::ERROR_CONNECTION;
        }
        else if (c_socket::TIMEOUT == $ret)
        {
            BigpipeLog::warning("[peek to read time out]");
            $ret = BigpipeErrorCode::TIMEOUT;
        }
        else
        {
            $ret = BigpipeErrorCode::READABLE;
        }
        return $ret;
    }

    /** c_socket的一个实例 */
    private $_socket = null;
    /** 存在连接时，记录连接对象 */
    private $_target = null;
    /** 连接对象的地址列表 */
    private $_destinations = null;
    /** 最大尝试连接次数*/
    private $_max_try_time = null;
    /** 连接超时, 单位: 秒 */
    private $_conn_timeo = null;
    /** 是否要做整包校验 */
    private $_check_frame = true;

} // end of BigpipeConnection
?>
