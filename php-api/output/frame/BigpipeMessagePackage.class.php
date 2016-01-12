<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
****************************************************************************/
require_once (dirname(__FILE__).'/BigpipeLog.class.php');

/**
 * message结构<p>
 * @author yangzhenyu@baidu.com
 *
 */
class BigpipeMessage
{
    /** 消息字段 */
    public $content = null;
    /** topiec message id (订阅点) */
    public $msg_id = null;
    /** 消息在当前消息包中的序号，从1开始 */
    public $seq_id = null;
    /** 指示该消息是否是包中最后一条 */
    public $is_last_msg = null;
}

/**
 * message包结构<p>
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
     * 从message package中取出一条message
     * @return 如果包不为空, 则返回一个消息(BigpipeMessage); 反之则返回false
     */
    public function pop()
    {
        $head_len = 4; // head中记录了message length
        if ($this->_size < $head_len)
        {
            return false; // 没有下一条message了
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
     * 检查message package是否为空
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
     * 从buffer load一个message package
     * @param binary $buff
     * @param number $buff_size
     * @param uint64 $msg_id
     * @return boolean
     */
    public function load($buff, $msg_id)
    {
        $head_len = 4; // 4B的message count (这个field可能为0)
        $msg_head_len = 4; // 4B的message length
        $buff_size = strlen($buff);
        $this->_size = $buff_size - $head_len;
        if ($this->_size < $msg_head_len)
        {
            BigpipeLog::warning('[insufficient pakcage size][actual:%u][msg_id:%u]',
                                $this->_size, $msg_id);
            return false;
        }

        // 读取message
        $arr = unpack('L1cnt', $buff);
        $this->_count = $arr['cnt'];
        $this->_data = substr($buff, $head_len, $this->_size);
        $this->_message_id = $msg_id;
        $this->_seq_id = 1;
        return true;
    }

    /**
     * 将一条消息送入message package
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
     * 将message package 写入buffer<p>
     * @param binary $buff: 传入一个buffer的引用
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
