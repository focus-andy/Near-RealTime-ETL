<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
****************************************************************************/
require_once (dirname(__FILE__).'/BigpipeLog.class.php');

/**
 * message�ṹ<p>
 * @author yangzhenyu@baidu.com
 *
 */
class BigpipeMessage
{
    /** ��Ϣ�ֶ� */
    public $content = null;
    /** topiec message id (���ĵ�) */
    public $msg_id = null;
    /** ��Ϣ�ڵ�ǰ��Ϣ���е���ţ���1��ʼ */
    public $seq_id = null;
    /** ָʾ����Ϣ�Ƿ��ǰ������һ�� */
    public $is_last_msg = null;
}

/**
 * message���ṹ<p>
 * | uint32 | message count  |<p>
 * |--------+----------------+----------<p>
 * | uint32 | message length |<p>
 * +        +                + a message<p>
 * | binary | message body   |<p>
 * +--------+----------------+----------<p>
 * |      other messages     |<p>
 * @author yangzhenyu@baidu.com
 *
 */
class BigpipeMessagePackage
{
    /**
     * ��message package��ȡ��һ��message
     * @return �������Ϊ��, �򷵻�һ����Ϣ(BigpipeMessage); ��֮�򷵻�false
     */
    public function pop()
    {
        $head_len = 4; // head�м�¼��message length
        if ($this->_size < $head_len)
        {
            return false; // û����һ��message��
        }

        $arr = unpack('L1len', $this->_data);
        $msg_len = $arr['len'];
        $left_size = $this->_size - $head_len - $msg_len;
        if ($left_size < 0)
        {
            BigpipeLog::warning('[unexpected pakcage end][msg_len:%u][pkg_size:%u]',
                                $msg_len, $this->_size - $head_len);
            return false;
        }

        // pack
        $msg = new BigpipeMessage;
        $msg->content = substr($this->_data, $head_len, $msg_len);
        $msg->msg_id = $this->_message_id;
        $msg->seq_id = $this->_seq_id;
        $msg->is_last_msg = (0 == $left_size);

        $this->_data = substr($this->_data, $head_len + $msg_len);
        $this->_size = $left_size;
        $this->_seq_id++;
        return $msg;
    }

    /**
     * ���message package�Ƿ�Ϊ��
     * @return boolean
     */
    public function is_empty()
    {
        if ($this->_size > 0)
        {
            return false;
        }
        return true;
    }

    /**
     * ��buffer loadһ��message package
     * @param binary $buff
     * @param number $buff_size
     * @param uint64 $msg_id
     * @return boolean
     */
    public function load($buff, $msg_id)
    {
        $head_len = 4; // 4B��message count (���field����Ϊ0)
        $msg_head_len = 4; // 4B��message length
        $buff_size = strlen($buff);
        $this->_size = $buff_size - $head_len;
        if ($this->_size < $msg_head_len)
        {
            BigpipeLog::warning('[insufficient pakcage size][actual:%u][msg_id:%u]',
                                $this->_size, $msg_id);
            return false;
        }

        // ��ȡmessage
        $arr = unpack('L1cnt', $buff);
        $this->_count = $arr['cnt'];
        $this->_data = substr($buff, $head_len, $this->_size);
        $this->_message_id = $msg_id;
        $this->_seq_id = 1;
        return true;
    }

    /**
     * ��һ����Ϣ����message package
     * @param binary string $msg
     * @return true on success or false on failure
     */
    public function push($msg)
    {
        $msg_head_len = 4;
        $msg_len = strlen($msg);
        if (0 == $msg_len)
        {
            BigpipeLog::warning('[can not push empty message]');
            return false;
        }
        
        $new_size = $this->_size + $msg_head_len + $msg_len;
        if ($msg_len > BigpipeCommonDefine::MAX_MESSAGE_LEN)
        {
            BigpipeLog::warning('[message is too big to publish][size:%u][limitation:%u]',
                                $msg_len, $msg_id);
            return false;
        }
        else if ($new_size > BigpipeCommonDefine::MAX_MBUFFER_LEN)
        {
            BigpipeLog::warning('[insufficient sapce for new message][remaind:%u][msg_len:%u]',
                                BigpipeCommonDefine::MAX_MBUFFER_LEN - $this->_size - $msg_head, $msg_len);
            return false;
        }
        
        // pack the message
        $this->_data .= pack("L1", $msg_len);
        $this->_data .= $msg;
        $this->_size = $new_size;
        $this->_count++;
        return true;
    }
    
    /**
     * ��message package д��buffer<p>
     * @param binary $buff: ����һ��buffer������
     * @return true on success or false on failure
     */
    public function store(&$buff)
    {
        if ($this->is_empty())
        {
            BigpipeLog::warning('[store error][empty message package]');
            return false;
        }
        $buff .= pack("L1", $this->_count);
        $buff .= $this->_data;
        return true;
    }

    public function __construct()
    {
        $this->_data = null;
        $this->_size = 0;
        $this->_seq_id = 1;
        $this->_message_id = null;
        $this->_count = 0;
    }
    
    private $_data = null;
    private $_size = 0;
    private $_seq_id = 1;
    private $_message_id = 0;
    private $_count = 0;
} // BigpipeMessagePackage

?>
