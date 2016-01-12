<?php
/**==========================================================================
 *
* MetaAgentFrame.class.php - INF / DS / BIGPIPE
*
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
* Created on 2012-11-19 by YANG ZHENYU (yangzhenyu@baidu.com)
*
* --------------------------------------------------------------------------
*
* Description
*     meta agent的基本通讯协议
*
* --------------------------------------------------------------------------
*
* Change Log
*
*
==========================================================================**/
require_once (dirname(__FILE__).'/CBmqException.class.php');

/**
 * 定义了meta agent通讯协议类型
 * 对应meta_agent_cmd_type_t
 */
class MetaAgentFrameType
{
    const UNKNOWN_TYPE    = 0x0000;
    const CMD_INIT_META   = 0x0001;
    const CMD_UNINIT_META = 0x0002;
    const CMD_GET_PUBINFO = 0x0003;
    const CMD_GET_SUBINFO = 0x0004;

    const ACK_ERROR_PACK  = 0x0100;
    const ACK_INIT_META   = 0x0101;
    const ACK_UNINIT_META = 0x0102;
    const ACK_GET_PUBINFO = 0x0103;
    const ACK_GET_SUBINFO = 0x0104;
} // end of MetaAgentFrameType

/**
 * 定义了meta agent用讯协议类型对应的文字描述
 */
class MetaAgentFrameTypeString
{
    public static $FRAMETYPE_STRING = array (
            MetaAgentFrameType::CMD_INIT_META => "META_AGENT_CMD_INIT_META",
            MetaAgentFrameType::CMD_UNINIT_META => "META_AGENT_CMD_UNINIT_META",
            MetaAgentFrameType::CMD_GET_PUBINFO => "META_AGENT_CMD_GET_PUBINFO",
            MetaAgentFrameType::CMD_GET_SUBINFO => "META_AGENT_CMD_GET_SUBINFO",
            MetaAgentFrameType::ACK_ERROR_PACK => "META_AGENT_CMD_ACK_ERROR_PACK",
            MetaAgentFrameType::ACK_INIT_META => "META_AGENT_CMD_ACK_INIT_META",
            MetaAgentFrameType::ACK_UNINIT_META => "META_AGENT_CMD_ACK_UNINIT_META",
            MetaAgentFrameType::ACK_GET_PUBINFO => "META_AGENT_CMD_ACK_GET_PUBINFO",
            MetaAgentFrameType::ACK_GET_SUBINFO => "META_AGENT_CMD_ACK_GET_SUBINFO",
    );
} // end of MetaAgentFrameTypeString

/**
 * @brief: 解析MetaAgent通讯协议的基类
 * @author yangzhenyu@baidu.com
 */
class MetaAgentFrame
{
    /** frame头: 命令类型 */
    public $command_type = null;
    /** frame头: 扩展字段 */
    public $extension    = 'meta-agent';

    /**
     * 定义了frame中的各字段类型及读取/存储顺序<p>
     * key: field name<p>
     * val: field type
     * @var array
     */
    protected $_fields = array();
    
    /**
     * 定义了frame中一些字段的限制条件<p>
     * key: field name<p>
     * val: field restriction
     * @var array
     */
    protected $_fields_restriction = array();

    /**
     * MetaAgent Frame系列的标准头信息
     * @var array
     */
    private $_head = array(
            'command_type' => 'int32',
            'extension'    => 'blob',);

    /**
     * 最近一次错误的错误信息
     * @var string
     */
    private $_last_error_message = null;
    
    /**
     * load时指示解析进度
     * @var binary string
     */
    private $_buffer = null;

    /**
     * Constructor<p>
     * php中缺乏虚函数, 需要在constructor中初始化fields和fields_restriction
     * @param string $command
     */
    public function __construct ($cmd_type)
    {
        $this->command_type = $cmd_type;
    }

    /**
     * 从buff读取frame
     * @param $buff: 二进制协议流
     * @return true on success or false on failure
     */
    public function load($buff)
    {
        if (null == $buff)
        {
            $this->_last_error_message = 'empty input buffer';
            return false;
        }
        
        if (!$this->_check_cmd_type($buff))
        {
            return false;
        }
        
        $this->_buffer = $buff;
        if (!$this->_load($this->_head))
        {
            return false;
        }
        
        // 读取自定义字段
        if (!$this->_load($this->_fields))
        {
            return false;
        }
        
        return true;
    }

    /**
     * 将frame中各字段打包到$this->_buffer
     * @return pack size on success or 0 on failure
     */
    public function store()
    {
        $this->_buffer = $this->_store($this->_head);
        if (null == $this->_buffer)
        {
            // todo error to pack head
            return 0;
        }
        
        $body = $this->_store($this->_fields);
        if (null == $body)
        {
            // todo error when write fields
            $this->_buffer = null; // 清空buffer
            return 0;
        }
        else
        {
            $this->_buffer .= $body;
        }
        
        return strlen($this->_buffer);
    }
    
    /**
     * 使用field(key/value)填充一个frame
     * @param array $fields: 传入field key和field value
     * @return boolean
     */
    public function pack($fields)
    {
        try
        {
            $obj = new ReflectionClass($this);
            while (list($key, $value) = each($fields))
            {
                // 动态读取field以及相应的解析方法
                $property = $obj->getProperty($key);
                $property->setValue($this, $value);
            }
        }
        catch(Exception $e)
        {
            $this->_last_error_message = "pack error [err:" . $e->getMessage() . "]";
            return false;
        }
        return true;
    }
    
    /**
     * 返回最近一次错误的错误信息
     * @return string
     */
    public function last_error_message()
    {
        return $this->_last_error_message;
    }

    /**
     * 返回frame的buffer
     * @return binary string
     */
    public function buffer()
    {
        return $this->_buffer;
    }
    
    /**
     *  从传入buff中读取command type
     * @param binary string $buff:传入协议流
     * @return int32_t command type on success or UNKNOWN_TYPE on failure
     */
    public static function get_command_type($buff)
    {
        if (2 > strlen($buff))
        {
            $this->_last_error_message = 'no command type';
            return MetaAgentFrameType::UNKNOWN_TYPE;
        }
        $ai = unpack("l1int", $buff);
        $type = $ai["int"];
        return $type;
    }
    
    /**
     * 检查传入协议是否是frame可以解析的协议
     * @param binary $buff: 传入协议流
     * @return boolean
     */
    private function _check_cmd_type($buff)
    {
        $type = self::get_command_type($buff);
        if (MetaAgentFrameType::UNKNOWN_TYPE == $type)
        {
            return false;
        }

        if ($type != $this->command_type)
        {
            $this->_last_error_message = "command type error [require:$this->command_type][actual:$type]";
            return false;
        }
        $this->_buffer = substr($this->_buffer, 2);
        return true;
    }

    /**
     * 从$this->_buffer中读取fields中的字段
     * @param $buff  : binary string
     * @param $fields: 需要从buff中读取的字段array
     * @return true on success or false on failure
     */
    private function _load($fields)
    {
        try 
        {
            $obj = new ReflectionClass($this);
            while (list($name, $type) = each($fields))
            {
                // 动态读取field以及相应的解析方法
                $property = $obj->getProperty($name);
                $method = $obj->getMethod('_unpack' . $type);
                $method->setAccessible(true);
                
                // 调用unpack方法解析fields的值
                $prop_val = null;
                if (null != $this->_fields_restriction && isset($this->_fields_restriction[$name]))
                {
                    $restriction = $this->_fields_restriction[$name];
                    $prop_val = $method->invoke($this, $restriction);
                }
                else
                {
                    $prop_val = $method->invoke($this);
                }
                $property->setValue($this, $prop_val);
            }
        }
        catch(Exception $e)
        {
            $this->_last_error_message = sprintf("_load error [err:%s]", $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 将frame中各字段打包到$this->_buffer
     * @return packed data on success or null on failure
     */
    private function _store($fields)
    {
        $offset = 0;
        try
        {
            $obj = new ReflectionClass($this);
            $packed_data = null;
            while (list($name, $type) = each($fields))
            {
                // 读取fields以及相应的打包方法
                $property = $obj->getProperty($name);
                $method = $obj->getMethod('_pack' . $type);
                $method->setAccessible(true);

                // 调用pack方法解析fields的值
                $prop_val = $property->getValue($this);
                
                
                if (null != $this->_fields_restriction && isset($this->_fields_restriction[$name]))
                {
                    $restriction = $this->_fields_restriction[$name];
                    $packed_data .= $method->invoke($this, $prop_val, $restriction);
                }
                else
                {
                    $packed_data .= $method->invoke($this, $prop_val);
                }
            }
            
            return $packed_data;
        }
        catch(Exception $e)
        {
            $this->_last_error_message = "_store error [err:" . $e->getMessage() . "]";
        }
        return null;
    }

    private function _unpackint16($max = 0)
    {
        if (2 > strlen($this->_buffer))
        {
            throw new BmqException("not enough buffer for int16");
        }
        $ai = unpack("s1int", $this->_buffer);
        $ret = $ai["int"];
        if (($max > 0) && ($ret > $max))
        {
            throw new BmqException("int16 exceed maxlen.");
        }
        $this->_buffer = substr($this->_buffer, 2);
        return $ret;
    }

    private function _unpackint32($max = 0)
    {
        if (4 > strlen($this->_buffer))
        {
            throw new BmqException("not enough buffer for int32");
        }
        $ai = unpack("l1int", $this->_buffer);
        $ret = $ai["int"];
        if (($max > 0) && ($ret > $max))
        {
            throw new BmqException("int32 exceed maxlen.");
        }
        $this->_buffer = substr($this->_buffer, 4);
        return $ret;
    }
    
    private function _unpackuint32($max = 0)
    {
        if (4 > strlen($this->_buffer))
        {
            throw new BmqException("not enough buffer for uint32");
        }
        $ai = unpack("L1int", $this->_buffer);
        $ret = $ai["int"];
        if (($max > 0) && ($ret > $max))
        {
            throw new BmqException("uint32 exceed maxlen.");
        }
        $this->_buffer = substr($this->_buffer, 4);
        return $ret;
    }

    private function _unpackint64($max = 0, $byteorder =0)
    {
        if (8 > strlen($this->_buffer))
        {
            throw new BmqException("not enough buffer for int64");
        }
        $ai = Array();
        if (0 == $byteorder)
        {
            //小头
            $ai = unpack("V1low/V1high", $this->_buffer);
        }
        else
        {
            //大头
            $ai = unpack("N1high/N1low", $this->_buffer);
        }
        $ret = (($ai["high"] & 0x00000007fffffff) << 32) | ($ai["low"] & 0x0000000ffffffff);
        if (($max > 0) && ($ret > $max))
        {
            throw new BmqException("int64 exceed maxlen.");
        }
        $this->_buffer = substr($this->_buffer, 8);
        return $ret;
    }

    /**
     * 从buffer中解析一个string结构<p>
     * 注意字符串长度不能超过65535（含'\0'）个字符
     * @param number $maxlen : 限制条件, string的最大长度
     * @throws BmqException
     * @return multitype:number string
     */
    private function _unpackstring($maxlen = 0)
    {
        $buff_len = strlen($this->_buffer);
        if (2 > $buff_len)
        {
            throw new BmqException("error string");
        }
        $retarr = Array();
        $alen = unpack("S1len", $this->_buffer);
        $len = $alen["len"];
        if ((0 == $len) || (($maxlen > 0) && ($len > $maxlen))
                || ($len + 2 > $buff_len))
        {
            throw new BmqException("not enough buffer for a string.[maxlen:$maxlen][str:$len][actual:$buff_len");
        }
        $retarr["size"] = $len -1;
        $retarr["str"] = substr($this->_buffer, 2, $len -1);
        $this->_buffer = substr($this->_buffer, 2 + $len);
        return $retarr['str'];
    }

    /**
     * 从buffer中解析一个blob结构<p>
     * @param number $maxlen: 限制条件blob的最大长度
     * @throws BmqException
     * @return multitype:string binary
     */
    private function _unpackblob($maxlen = 0)
    {
        $buff_len = strlen($this->_buffer);
        if (4 > $buff_len)
        {
            throw new BmqException("error blob");
        }
        $retarr = Array();
        $alen = unpack("L1len", $this->_buffer);
        $len = $alen["len"];
        if ((($maxlen > 0) && ($len > $maxlen))
            || 
            ($len + 4 > $buff_len))
        {
            throw new BmqException("not enough buffer for a blob.[maxlen:$maxlen][blob:$len][actual:$buff_len");
        }
        $retarr["size"] = $len;
        $retarr["blob"] = substr($this->_buffer, 4, $len);
        $this->_buffer = substr($this->_buffer, 4 + $len);
        return $retarr['blob'];
    }

    private function _packint16($ui, $maxi = 0)
    {
        if (!isset($ui))
        {
            $ui = 0;
        }
        if ((0 < $maxi) && ($ui > $maxi))
        {
            throw new BmqException("int16 exceed maxlen.");
        }
        return pack("s1", $ui);
    }

    private function _packuint16($ui, $maxi = 0)
    {
        if (!isset($ui))
        {
            $ui = 0;
        }
        if ((0 < $maxi) && ($ui > $maxi))
        {
            throw new BmqException("uint16 exceed maxlen.");
        }
        return pack("S1", $ui);
    }
    
    private function _packuint32($ui, $maxi = 0)
    {
        if (!isset($ui))
        {
            $ui = 0;
        }
        if ((0 < $maxi) && ($ui > $maxi))
        {
            throw new BmqException("uint32 exceed maxlen.");
        }
        return pack("L1", $ui);
    }
    
    private function _packint32($ui, $maxi = 0)
    {
        if (!isset($ui))
        {
            $ui = 0;
        }
        if ((0 < $maxi) && ($ui > $maxi))
        {
            throw new BmqException("int32 exceed maxlen.");
        }
        return pack("l1", $ui);
    }
    
    private function _packint64($ui, $byteorder = 0, $maxi = 0)
    {
        if (!isset($ui))
        {
            $ui = 0;
        }
        //TODO 字节序的自动识别
        if ((0 < $maxi) && ($ui > $maxi))
        {
            throw new BmqException("int64 exceed maxlen.");
        }
        $high = ($ui >> 32) & 0x00000007fffffff;
        $low  = $ui & 0x0000000ffffffff;
        if (0 == $byteorder)
        {
            //小头
            return pack("V2", $low, $high);
        }
        else
        {
            //大头
            return pack("N2", $high, $low);
        }
    }

    private function _packstring($str, $maxlen)
    {
        if (!isset($str))
        {
            $str = "";
        }
        $len = strlen($str);
        $len = $len+1;
        if ($len >= $maxlen)
        {
            throw new BmqException("string exceed maxlen.");
        }
        $data = pack("S1", $len);
        $data .= $str;
        $data .= pack("C1", 0);
        return $data;
    }

    private function _packblob($blob, $maxlen=0)
    {
        if (!isset($blob))
        {
            $blob = "";
        }
        $len = strlen($blob);
        if ((0 != $maxlen) && ($len >= $maxlen))
        {
            throw new BmqException("blob exceed maxlen[max:$maxlen] [blob:$len].");
        }
        $data = pack("L1", $len);
        $data .= $blob;
        return $data;
    }
} // end of MetaAgentFrame
?>
