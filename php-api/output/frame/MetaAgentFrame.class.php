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
*     meta agent�Ļ���ͨѶЭ��
*
* --------------------------------------------------------------------------
*
* Change Log
*
*
==========================================================================**/
require_once (dirname(__FILE__).'/CBmqException.class.php');

/**
 * ������meta agentͨѶЭ������
 * ��Ӧmeta_agent_cmd_type_t
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
 * ������meta agent��ѶЭ�����Ͷ�Ӧ����������
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
 * @brief: ����MetaAgentͨѶЭ��Ļ���
 * @author yangzhenyu@baidu.com
 */
class MetaAgentFrame
{
    /** frameͷ: �������� */
    public $command_type = null;
    /** frameͷ: ��չ�ֶ� */
    public $extension    = 'meta-agent';

    /**
     * ������frame�еĸ��ֶ����ͼ���ȡ/�洢˳��<p>
     * key: field name<p>
     * val: field type
     * @var array
     */
    protected $_fields = array();
    
    /**
     * ������frame��һЩ�ֶε���������<p>
     * key: field name<p>
     * val: field restriction
     * @var array
     */
    protected $_fields_restriction = array();

    /**
     * MetaAgent Frameϵ�еı�׼ͷ��Ϣ
     * @var array
     */
    private $_head = array(
            'command_type' => 'int32',
            'extension'    => 'blob',);

    /**
     * ���һ�δ���Ĵ�����Ϣ
     * @var string
     */
    private $_last_error_message = null;
    
    /**
     * loadʱָʾ��������
     * @var binary string
     */
    private $_buffer = null;

    /**
     * Constructor<p>
     * php��ȱ���麯��, ��Ҫ��constructor�г�ʼ��fields��fields_restriction
     * @param string $command
     */
    public function __construct ($cmd_type)
    {
        $this->command_type = $cmd_type;
    }

    /**
     * ��buff��ȡframe
     * @param $buff: ������Э����
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
        
        // ��ȡ�Զ����ֶ�
        if (!$this->_load($this->_fields))
        {
            return false;
        }
        
        return true;
    }

    /**
     * ��frame�и��ֶδ����$this->_buffer
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
            $this->_buffer = null; // ���buffer
            return 0;
        }
        else
        {
            $this->_buffer .= $body;
        }
        
        return strlen($this->_buffer);
    }
    
    /**
     * ʹ��field(key/value)���һ��frame
     * @param array $fields: ����field key��field value
     * @return boolean
     */
    public function pack($fields)
    {
        try
        {
            $obj = new ReflectionClass($this);
            while (list($key, $value) = each($fields))
            {
                // ��̬��ȡfield�Լ���Ӧ�Ľ�������
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
     * �������һ�δ���Ĵ�����Ϣ
     * @return string
     */
    public function last_error_message()
    {
        return $this->_last_error_message;
    }

    /**
     * ����frame��buffer
     * @return binary string
     */
    public function buffer()
    {
        return $this->_buffer;
    }
    
    /**
     *  �Ӵ���buff�ж�ȡcommand type
     * @param binary string $buff:����Э����
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
     * ��鴫��Э���Ƿ���frame���Խ�����Э��
     * @param binary $buff: ����Э����
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
     * ��$this->_buffer�ж�ȡfields�е��ֶ�
     * @param $buff  : binary string
     * @param $fields: ��Ҫ��buff�ж�ȡ���ֶ�array
     * @return true on success or false on failure
     */
    private function _load($fields)
    {
        try 
        {
            $obj = new ReflectionClass($this);
            while (list($name, $type) = each($fields))
            {
                // ��̬��ȡfield�Լ���Ӧ�Ľ�������
                $property = $obj->getProperty($name);
                $method = $obj->getMethod('_unpack' . $type);
                $method->setAccessible(true);
                
                // ����unpack��������fields��ֵ
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
     * ��frame�и��ֶδ����$this->_buffer
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
                // ��ȡfields�Լ���Ӧ�Ĵ������
                $property = $obj->getProperty($name);
                $method = $obj->getMethod('_pack' . $type);
                $method->setAccessible(true);

                // ����pack��������fields��ֵ
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
            //Сͷ
            $ai = unpack("V1low/V1high", $this->_buffer);
        }
        else
        {
            //��ͷ
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
     * ��buffer�н���һ��string�ṹ<p>
     * ע���ַ������Ȳ��ܳ���65535����'\0'�����ַ�
     * @param number $maxlen : ��������, string����󳤶�
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
     * ��buffer�н���һ��blob�ṹ<p>
     * @param number $maxlen: ��������blob����󳤶�
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
        //TODO �ֽ�����Զ�ʶ��
        if ((0 < $maxi) && ($ui > $maxi))
        {
            throw new BmqException("int64 exceed maxlen.");
        }
        $high = ($ui >> 32) & 0x00000007fffffff;
        $low  = $ui & 0x0000000ffffffff;
        if (0 == $byteorder)
        {
            //Сͷ
            return pack("V2", $low, $high);
        }
        else
        {
            //��ͷ
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
