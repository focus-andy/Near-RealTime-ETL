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
 * ��c_socket�ķ�װ�����ڷ��ͺͽ���nsheadЭ��
 * @author yangzhenyu@baidu.com
*/
class BigpipeConnection
{
    /**
     * ���캯��
     * @param BigpipeConnectionConf $conf
     */
    public function __construct($conf)
    {
        // todo �����$conf�ļ�飨�����׳��쳣��
        $this->_max_try_time = $conf->try_time;
        $this->_conn_timeo = $conf->conn_timeo;
        $this->_check_frame = $conf->check_frame;
        //set_time_limit($conf->time_limit);
    }

    /**
     * ����ʱ�ر�����
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * �û�����Ŀ���ַ�б�<p>
     * Ŀ���ַ�б���һ��array, ��Ԫ����Ŀ���ַ<p>
     * Ŀ���ַҲ�Ǹ�array���磺<p>
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
     * �ر�����
     */
    public function close()
    {
        if (!empty($this->_socket))
        {
            // �ͷ�����
            $this->_socket->close();
        }
        $this->_socket = null;
        $this->_target = null;
    }

    /**
     * ���connection�Ƿ���Ч
     * @return ���connection�����򷵻�true������������򷵻�false
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
     * ����һ��nshead��װ����Ϣ
     * @return true on success or false on failure
     */
    public function send($buffer, $buffer_size)
    {
        if (null == $this->_socket) // ���ж�socket�����������write��ʧ��
        {
            BigpipeLog::warning('[send error][lose connection][target:%s:%u]',
            $this->_target['socket_address'], $this->_target['socket_port']);
            return false; // ������
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
            // ����У��д��nshead��reserved�ֶ���
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
     * ����һ��nshead��װ����Ϣ
     * @return ����ɹ��������յ���nshead body; ���ʧ�ܣ�����null
     */
    public function receive()
    {
        if (null == $this->_socket)  // ���ж�socket�����������read��ʧ��
        {
            BigpipeLog::warning('[receive error][lose connection with %s:%u]',
            $this->_target['socket_address'], $this->_target['socket_port']);
            return null; // ������
        }

        $res = array();
        $buff = $this->_socket->read(36); // ��ȡһ��nsheadͷ
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
            // ��ȡnshead
            // �����ж������ϵ�����
            $res_head = $res['head'];
            if (false === $res_head)
            {
                $err_msg = '[fail to read head]';
                break;
            }

            if (!isset($res_head['body_len']) || 0 == $res_head['body_len'])
            {
                // ��������sizeΪ0�İ�
                $err_msg = '[no body_len]';
                break;
            }

            //��ȡ���ݰ�����
            $res['body'] = $this->_socket->read($res_head['body_len']);
            if (false === $res['body'])
            {
                $err_msg = '[no message body]';
                break;
            }

            if (true === $this->_check_frame)
            {
                // ����У��
                if (BigpipeCommonDefine::NSHEAD_CHECKSUM_MAGICNUM != $res_head['magic_num'])
                {
                    // ������checksumҲû���⣬����Ҫ��ӡ��־׷һ��
                    BigpipeLog::debug('[receive][frame does not have checksum, skip checksum]');
                }
                else
                {
                    // checksum��nshead, reserved�ֶ���
                    $checksum = BigpipeUtilities::adler32($res['body']);
                    if ($res_head['reserved'] != $checksum)
                    {
                        $err_msg = sprintf('[checksum failed][send:%u][recv:%u]', 
                                           $res_head['reserved'], $checksum);
                        $res['body'] = null;
                        break; // У��ʧ�ܣ�������
                    }
                }
            } // end of check frame integerity
            $is_ok = true;
        } while (false); // ���ڷ�֧���

        if (!$is_ok)
        {
            // ��ӡ������־
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
            $this->close(); // ��ֹ�ظ�����connection
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
        $idx = $curr_agent; // ��curr_agent��ʼ��������meta agent
        while ($try-- > 0)
        {
            $agent = $agents[$idx];
            // $stub_sock���ڲ���ʱ����mocked c_socket
            $socket = (true === isset($this->unittest)) ? $this->stub_sock : new c_socket();
            $agent['socket_timeout'] = $this->_conn_timeo; // c_socket�ڲ�ʹ��socket_timeout��Ϊread/write timeout
            $socket->set_vars_from_array($agent);

            //<<< ������Ϣ
            BigpipeLog::debug('[connect to][%s:%u]', $agent['socket_address'], $agent['socket_port']);

            // ����agent
            if ($socket->connect($this->_conn_timeo))
            {
                $this->_socket = $socket;
                $this->_target = $agent; // ��¼���Ӷ���
                break;
            }
            else // ����ʧ��
            {
                // ����ַ����
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
     * @param $timeo: �û�ָ���ĳ�ʱ�ȴ�ֵ�������ָ�����ȡ����(��λ������)
     * @return number
     */
    public function is_readable($timeo_ms)
    {
        if (empty($timeo_ms) || $timeo_ms <= 0)
        {
            // ��������
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

    /** c_socket��һ��ʵ�� */
    private $_socket = null;
    /** ��������ʱ����¼���Ӷ��� */
    private $_target = null;
    /** ���Ӷ���ĵ�ַ�б� */
    private $_destinations = null;
    /** ��������Ӵ���*/
    private $_max_try_time = null;
    /** ���ӳ�ʱ, ��λ: �� */
    private $_conn_timeo = null;
    /** �Ƿ�Ҫ������У�� */
    private $_check_frame = true;

} // end of BigpipeConnection
?>
