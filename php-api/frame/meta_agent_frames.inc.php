<?php
/***************************************************************************
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * @file  : meta_agent_frames.inc.php
 * @brief :
 *     meta agent通讯协议
 *
 ****************************************************************************/
require_once(dirname(__FILE__).'/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/BigpipeFrame.class.php');

/**
 * @brief: 对应meta_agent_cmd_type_t
 * 
 * @author: yangzhenyu@baidu.com
 *
 */
class MetaAgentFrameType
{
    const UNKNOWN_TYPE    = 0x0000;
    const CMD_INIT_META   = 0x0001;
    const CMD_UNINIT_META = 0x0002;
    const CMD_GET_PUBINFO = 0x0003;
    const CMD_GET_SUBINFO = 0x0004;
    const CMD_AUTHORIZE   = 0x0005;
    const CMD_INIT_API    = 0x0006;
    const CMD_UNINIT_API  = 0x0007;

    const ACK_ERROR_PACK  = 0x0100;
    const ACK_INIT_META   = 0x0101;
    const ACK_UNINIT_META = 0x0102;
    const ACK_GET_PUBINFO = 0x0103;
    const ACK_GET_SUBINFO = 0x0104;
    const ACK_AUTHORIZE   = 0x0105;
    const ACK_INIT_API    = 0x0106;
    const ACK_UNINIT_API  = 0x0107;
} // end of MetaAgentFrameType

/**
 * @brief:
 *
 * @author: yangzhenyu@baidu.com
 */
class MetaAgentFrameTypeString
{
    public static $FRAMETYPE_STRING = array (
            MetaAgentFrameType::CMD_INIT_META   => "META_AGENT_CMD_INIT_META",
            MetaAgentFrameType::CMD_UNINIT_META => "META_AGENT_CMD_UNINIT_META",
            MetaAgentFrameType::CMD_GET_PUBINFO => "META_AGENT_CMD_GET_PUBINFO",
            MetaAgentFrameType::CMD_GET_SUBINFO => "META_AGENT_CMD_GET_SUBINFO",
            MetaAgentFrameType::CMD_AUTHORIZE   => "META_AGENT_CMD_AUTHORIZE",
            MetaAgentFrameType::CMD_INIT_API    => "META_AGENT_CMD_INIT_API",
            MetaAgentFrameType::CMD_UNINIT_API  => "META_AGENT_CMD_UNINIT_API",
            MetaAgentFrameType::ACK_ERROR_PACK  => "META_AGENT_CMD_ACK_ERROR_PACK",
            MetaAgentFrameType::ACK_INIT_META   => "META_AGENT_CMD_ACK_INIT_META",
            MetaAgentFrameType::ACK_UNINIT_META => "META_AGENT_CMD_ACK_UNINIT_META",
            MetaAgentFrameType::ACK_GET_PUBINFO => "META_AGENT_CMD_ACK_GET_PUBINFO",
            MetaAgentFrameType::ACK_GET_SUBINFO => "META_AGENT_CMD_ACK_GET_SUBINFO",
            MetaAgentFrameType::ACK_AUTHORIZE   => "META_AGENT_CMD_ACK_AUTHORIZE",
            MetaAgentFrameType::ACK_INIT_API    => "META_AGENT_CMD_ACK_INIT_API",
            MetaAgentFrameType::ACK_UNINIT_API  => "META_AGENT_CMD_ACK_UNINIT_API",
    );
} // end of MetaAgentFrameTypeString


// 以下是meta agent协议包部分

///////////////////////////////////////////////////////////////////////////////
/// standard error ack of meta ack
class MetaAgentErrorAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::ACK_ERROR_PACK);

        // 定义fields
        $this->_fields = array(
                'error_code' => 'int32',
                'error_msg'  => 'string',
                );
        $this->_fields_restriction = array(
                'error_msg' => BigpipeCommonDefine::MAX_SIZE_ERROR_MESSAGE,
                );
    }

    /** */
    public $error_code = 0;
    public $error_msg  = null;
} // end of MetaAgentErrorAckFrame

///////////////////////////////////////////////////////////////////////////////
/// init meta command & its ack
class InitMetaFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::CMD_INIT_META);

        // 定义fields
        $this->_fields = array(
                'meta_host' => 'string',
                'root_path' => 'string',
                'max_cache_count' => 'int64',
                'watcher_timeout' => 'int64',
                'setting_timeout' => 'int64',
                'recv_timeout'    => 'int64',
                'max_value_size'  => 'int64',
                'zk_log_level'    => 'int64',
                'reinit_register_random' => 'int64',);
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'meta_host' => BigpipeCommonDefine::MAX_SIZE_META_NAME,
                'root_path' => BigpipeCommonDefine::MAX_SIZE_NAME,);
    }

    /** field: meta host */
    public $meta_host = null;
    public $root_path = null;
    public $max_cache_count = null;
    public $watcher_timeout = null;
    public $setting_timeout = null;
    public $recv_timeout = null;
    public $max_value_size = null;
    public $zk_log_level = null;
    public $reinit_register_random = null;
} // end of InitMetaFrame

class InitMetaAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::ACK_INIT_META);

        // 定义fields
        $this->_fields = array(
                'status'    => 'int32',
                'meta_name' => 'string',);
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'meta_name' => BigpipeCommonDefine::MAX_SIZE_META_NAME,);
    }

    /** field: 接收返回状态 */
    public $status = null;
    /** field: 一个meta实例的唯一标识名 */
    public $meta_name = null;
} // end of InitMetaAckFrame

///////////////////////////////////////////////////////////////////////////////
/// uninit meta command & its ack
class UninitMetaFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::CMD_UNINIT_META);

        // 定义fields
        $this->_fields = array(
                'meta_name' => 'string',);
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'meta_name' => BigpipeCommonDefine::MAX_SIZE_META_NAME,);
    }

    /** field: 一个meta实例的唯一标识名*/
    public $meta_name = null;
} // end of InitMetaFrame

class UninitMetaAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::ACK_UNINIT_META);

        // 定义fields
        $this->_fields = array(
                'status' => 'int32',);
    }

    /** field: 接收返回状态 */
    public $status = null;
} // end of InitMetaAckFrame

///////////////////////////////////////////////////////////////////////////////
/// get pub info command & its ack

class GetPubInfoFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::CMD_GET_PUBINFO);

        // 定义fields
        $this->_fields = array(
                'meta_name'  => 'string',
                'pipe_name'  => 'string',
                'pipelet_id' => 'uint32',);
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'meta_name' => BigpipeCommonDefine::MAX_SIZE_META_NAME,
                'pipe_name' => BigpipeCommonDefine::MAX_SIZE_NAME,);
    }

    /** field: meta host */
    public $meta_name = null;
    public $pipe_name = null;
    public $pipelet_id = null;
} // end of GetPubInfoFrame

class GetPubInfoAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::ACK_GET_PUBINFO);

        // 定义fields
        $this->_fields = array(
                'status'      => 'int32',
                'stripe_name' => 'string',
                'broker_ip'   => 'string',
                'broker_port' => 'int64');
        // 定义fields限制条件
        $this->_fields_restriction = array(
            'stripe_name' => BigpipeCommonDefine::MAX_SIZE_NAME,
            'broker_ip'   => BigpipeCommonDefine::MAX_SIZE_NAME,
        );
    }

    /** field: 接收返回状态 */
    public $status = null;
    /** field: 当前可发布的stripe */
    public $stripe_name = null;
    /** field: ip of primary borker */
    public $broker_ip = null;
    /** field: port of primary broker */
    public $broker_port = null;
} // end of GetPubInfoAckFrame

///////////////////////////////////////////////////////////////////////////////
/// get sub info command & its ack
class GetSubInfoFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::CMD_GET_SUBINFO);

        // 定义fields
        $this->_fields = array(
                'meta_name'   => 'string',
                'pipe_name'   => 'string',
                'pipelet_id'  => 'uint32',
                'start_point' => 'int64');
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'meta_name' => BigpipeCommonDefine::MAX_SIZE_META_NAME,
                'pipe_name' => BigpipeCommonDefine::MAX_SIZE_NAME,);
    }

    /** field: meta host */
    public $meta_name   = null;
    public $pipe_name   = null;
    public $pipelet_id  = null;
    /** field: -2 从最旧可订阅点开始订阅；-1 从最新可订阅点开始订阅 */
    public $start_point = null;
} // end of GetSubInfoFrame

class GetSubInfoAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::ACK_GET_SUBINFO);

        // 定义fields
        $this->_fields = array(
                'status'       => 'int32',
                'stripe_name'  => 'string',
                'stripe_id'    => 'int64',
                'begin_pos'    => 'int64',
                'end_pos'      => 'int64',
                'broker_group' => 'blob',);
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'stripe_name' => BigpipeCommonDefine::MAX_SIZE_NAME,);
    }

    /** field: 接收返回状态 */
    public $status      = null;
    public $stripe_name = null;
    public $stripe_id   = null;
    public $begin_pos   = null;
    public $end_pos     = null;
    /** field: json文件，含broker list */
    public $broker_group= null;
} // end of GetSubInfoAckFrame

///////////////////////////////////////////////////////////////////////////////
/// authorize command & its ack
class AuthorizeFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::CMD_AUTHORIZE);

        // 定义fields
        $this->_fields = array(
                'meta_name'  => 'string',
                'pipe_name'  => 'string',
                'token'      => 'string',
                'role'       => 'int16'
                );
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'meta_name' => BigpipeCommonDefine::MAX_SIZE_META_NAME,
                'pipe_name' => BigpipeCommonDefine::MAX_SIZE_NAME,
                'token'     => BigpipeCommonDefine::MAX_SIZE_NAME,
                );
    }

    /** field: meta host */
    public $meta_name = null;
    public $pipe_name = null;
    public $token = null;
    /**  field: 认证客户端的角色（发布者，或订阅者） */
    public $role = null;
} // end of GetPubInfoFrame

class AuthorizeAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::ACK_AUTHORIZE);

        // 定义fields
        $this->_fields = array(
                'status'        => 'int32',
                'num_pipelet'   => 'int64',
                'error_message' => 'string',
                );
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'error_message' => BigpipeCommonDefine::MAX_SIZE_ERROR_MESSAGE,
                );
    }

    /** field: 接收返回状态 */
    public $status = null;
    /** field: 可供操作的pipelet数目 */
    public $num_pipelet = null;
    /** field: 认证失败时的错误消息 */
    public $error_message = null;
} // end of GetPubInfoAckFrame

///////////////////////////////////////////////////////////////////////////////
/// init api command & its ack
class InitApiFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::CMD_INIT_API);

        // 定义fields
        $this->_fields = array(
                'meta_host' => 'string',
                'root_path' => 'string',
                'pipe_name'  => 'string',
                'token'      => 'string',
                'role'       => 'int16',
                'max_cache_count' => 'int64',
                'watcher_timeout' => 'int64',
                'setting_timeout' => 'int64',
                'recv_timeout'    => 'int64',
                'max_value_size'  => 'int64',
                'zk_log_level'    => 'int64',
                'reinit_register_random' => 'int64',);
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'meta_host' => BigpipeCommonDefine::MAX_SIZE_META_NAME,
                'root_path' => BigpipeCommonDefine::MAX_SIZE_NAME,
                'pipe_name' => BigpipeCommonDefine::MAX_SIZE_NAME,
                'token'     => BigpipeCommonDefine::MAX_SIZE_NAME,
                );
    }

    /** field: meta host */
    public $meta_host = null;
    public $root_path = null;
    public $pipe_name = null;
    public $token = null;
    /**  field: 认证客户端的角色（发布者，或订阅者） */
    public $role = null;
    public $max_cache_count = null;
    public $watcher_timeout = null;
    public $setting_timeout = null;
    public $recv_timeout = null;
    public $max_value_size = null;
    public $zk_log_level = null;
    public $reinit_register_random = null;
} // end of InitApiFrame

class InitApiAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::ACK_INIT_API);

        // 定义fields
        $this->_fields = array(
                'status'    => 'int32',
                'meta_name' => 'string',
                'num_pipelet'   => 'int64',
                'session'       => 'string',
                'session_id'    => 'int64',
                'session_timestamp' => 'int64',
                'error_message'     => 'string',
                );
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'meta_name' => BigpipeCommonDefine::MAX_SIZE_META_NAME,
                'session'   => BigpipeCommonDefine::MAX_SIZE_SESSION,
                'error_message' => BigpipeCommonDefine::MAX_SIZE_ERROR_MESSAGE,
                );
    }

    /** field: 接收返回状态 */
    public $status = null;
    /** field: 一个meta实例的唯一标识名 */
    public $meta_name = null;
    /** field: 可供操作的pipelet数目 */
    public $num_pipelet = null;
    /** meta agent中获得的session */
    public $session = null;
    /** session在meta agent session池中的位置 */
    public $session_id = null;
    /** 取出session的时间 */
    public $session_timestamp = null;
    /** field: 认证失败时的错误消息 */
    public $error_message = null;
} // end of InitApiAckFrame

///////////////////////////////////////////////////////////////////////////////
/// uninit api command & its ack
class UninitApiFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::CMD_UNINIT_API);

        // 定义fields
        $this->_fields = array(
                'session'    => 'string',
                'session_id' => 'int64',
                'session_timestamp' => 'int64'
        );
        // 定义fields限制条件
        $this->_fields_restriction = array(
                'session' => BigpipeCommonDefine::MAX_SIZE_SESSION,
        );
    }

    /** field: 一个meta实例的唯一标识名*/
    public $session = null;
    public $session_id = null;
    public $session_timestamp = null;
} // end of UninitApiFrame

class UninitApiAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(MetaAgentFrameType::ACK_UNINIT_API);

        // 定义fields
        $this->_fields = array(
                'status' => 'int32',);
    }

    /** field: 接收返回状态 */
    public $status = null;
} // end of UninitApiAckFrame

?>
