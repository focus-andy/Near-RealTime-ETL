<?php
/***************************************************************************
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 * @file  : bigpipe_stomp_frames.inc.php
 * @brief :
 *     bigpipe stomp协议
 *
****************************************************************************/
require_once (dirname(__FILE__).'/bigpipe_common.inc.php');
require_once (dirname(__FILE__).'/BigpipeFrame.class.php');
/**
 * 定义了bigpipe stomp协议中的command type常量
 * @author yangzhenyu@baidu.com
 */
class BStompFrameType
{
    const CONNECT = 0x0001;
    const CONNECTED = 0x0002;
    const SEND = 0x0003;
    const CMDSEND = 0x0004;
    const SUBSCRIBE = 0x0005;
    const UNSUBSCRIBE = 0x0006;
    const BEGIN = 0x0007;
    const COMMIT = 0x0008;
    const ABORT = 0x0009;
    const ACK = 0x000A;
    const DISCONNECT = 0x000B;
    const SUBSCRIBEALL = 0x000C;
    const MESSAGE = 0x000D;
    const CMDMESSAGE = 0x000E;
    const RECEIPT = 0x000F;
    const ERROR = 0x0010;
    const MESSAGEPACK = 0x0011;
} // BStompFrameType

/**
 * 定义了bigpipe stomp协议中的command type常量对应的文字说明
 * @author yangzhenyu@baidu.com
 */
class BStompFrameTypeString
{
    /**
     * bigpipe stomp frame对应命令字符串
     * @var string array
     */
    private static $FRAMETYPE_STRING = array (
            BStompFrameType::CONNECT => 'BIGPIPE_STOMP_CONNECT',
            BStompFrameType::CONNECTED => 'BIGPIPE_STOMP_CONNECTED',
            BStompFrameType::SEND => 'BIGPIPE_STOMP_SEND',
            BStompFrameType::CMDSEND => 'BIGPIPE_STOMP_CMDSEND',
            BStompFrameType::SUBSCRIBE => 'BIGPIPE_STOMP_SUBSCRIBE',
            BStompFrameType::UNSUBSCRIBE => 'BIGPIPE_STOMP_UNSUBSCRIBE',
            BStompFrameType::BEGIN => 'BIGPIPE_STOMP_BEGIN',
            BStompFrameType::COMMIT => 'BIGPIPE_STOMP_COMMIT',
            BStompFrameType::ABORT => 'BIGPIPE_STOMP_ABORT',
            BStompFrameType::ACK => 'BIGPIPE_STOMP_ACK',
            BStompFrameType::DISCONNECT => 'BIGPIPE_STOMP_DISCONNECT',
            BStompFrameType::SUBSCRIBEALL => 'BIGPIPE_STOMP_SUBSCRIBEALL',
            BStompFrameType::MESSAGE => 'BIGPIPE_STOMP_MESSAGE',
            BStompFrameType::CMDMESSAGE => 'BIGPIPE_STOMP_CMDMESSAGE',
            BStompFrameType::RECEIPT => 'BIGPIPE_STOMP_RECEIPT',
            BStompFrameType::ERROR => 'BIGPIPE_STOMP_ERROR',
            BStompFrameType::MESSAGEPACK => 'BIGPIPE_STOMP_MESSAGEPACK',
    );
    
    public static function get($type)
    {
         if (isset(self::$FRAMETYPE_STRING[$type]))
         {
             return self::$FRAMETYPE_STRING[$type];
         }
         
         return 'BIGPIPE_STOMP_UNKNOWN';
    }
} // BStompFrameTypeString

/**
 * bigpipe stomp连接发起者的角色定义
 * @author yangzhenyu@baidu.com
 */
class BStompRoleType
{
    const UNDEFINED    = 0;
    const PRIMARY_MB   = 1;
    const SECONDARY_MB = 2;
    const COMMON_MB    = 3;
    const PUBLISHER    = 4;
    const SUBSCRIBER   = 5;
} // BStompRoleType

/**
 * client接受消息时使用的返回ack的方式类型
 * @author yangzhenyu@baidu.com
 */
class BStompClientAckType
{
    // SUBSCRIBE消息中设置返回ACK
    
    /** 目前都是用自动返回  */
    const AUTO   = 1;
    /** 接口不自动ACK，由Client手动ACK */
    const MANUAL = 2;
}
/**
 * bigpipe stomp ack 中message id的类型
 * @author yangzhenyu@baidu.com
 */
class BStompIdAckType
{
    const TOPIC_ID   = 1;
    const GLOBAL_ID  = 2;
    const SESSION_ID = 3;
} // BStompAckType

// 以下定义了bigpipe stomp具体协议
/**
 * bigpipe stomp的通用ack frame
 * @author yangzhenyu@baidu.com
 */
class BStompAckFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(BStompFrameType::ACK);
    
        // 定义fields
        // bigpipe中id的定义是uint64，php暂时定义为int64
        $this->_fields = array(
                'status'             => 'int64',
                'ack_type'           => 'int16',
                'session_message_id' => 'int64',
                'topic_message_id'   => 'int64',
                'global_message_id'  => 'int64',
                'delay_time'         => 'int64',
                'destination'        => 'string',
                'receipt_id'         => 'string',
        );
        $this->_fields_restriction = array(
                'destination' => BigpipeCommonDefine::MAX_SIZE_NAME,
                'receipt_id'  => BigpipeCommonDefine::MAX_SIZE_RECEIPT_ID,
                );
    }
    
    /** 
     * field: connect发起者的角色
     * @var BStompRoleType
     */
    public $status = 0;
    /**
     * ack中message id的类型
     * @var BStompIdAckType
     */
    public $ack_type = null;
    public $session_message_id = null;
    public $topic_message_id = null;
    public $global_message_id = null;
    public $delay_time = null;
    public $destination = null;
    public $receipt_id = null;
} // BStompAckFrame

/**
 * bigpipe stomp 标准错误返回
 * @author yangzhenyu@baidu.com
 */
class BStompErrorFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(BStompFrameType::ERROR);
    
        // 定义fields
        $this->_fields = array(
                'error_no'       => 'int32',
                'error_message' => 'string',
        );
        $this->_fields_restriction = array(
                'error_message' => BigpipeCommonDefine::MAX_SIZE_ERROR_MESSAGE,
                );
    }
    
    /** field: error code*/
    public $error_no = 0;
    /** field: error message */
    public $error_message = null;
} // end of BStompErrorFrame

/**
 * bigpipe stomp 连接命令
 * @author yangzhenyu@baidu.com
 */
class BStompConnectFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(BStompFrameType::CONNECT);
    
        // 定义fields
        $this->_fields = array(
                'role'       => 'int16',
                'session_id' => 'string',
                'topic_name' => 'string',
                );
        $this->_fields_restriction = array(
                'session_id' => BigpipeCommonDefine::MAX_SIZE_NAME,
                'topic_name' => BigpipeCommonDefine::MAX_SIZE_NAME,
                );
    }
    
    /** field: connect发起者的角色*/
    public $role = 0;
    public $session_id = null;
    public $topic_name = null;
} // BStompConnectFrame

/**
 * bigpipe stomp 连接命令返回
 * @author yangzhenyu@baidu.com
 */
class BStompConnectedFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(BStompFrameType::CONNECTED);
    
        // 定义fields
        $this->_fields = array(
                'session_id'         => 'string',
                'session_message_id' => 'int64',
        );
        $this->_fields_restriction = array(
                'session_id' => BigpipeCommonDefine::MAX_SIZE_NAME,
                );
    }
    
    /** fields: connect发起者的session id*/
    public $session_id = null;
    public $session_message_id = null;
}  // BStompConnectedFrame

/**
 * bigpipe stomp 订阅命令
 * @author yangzhenyu@baidu.com
 */
class BStompSubscribeFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(BStompFrameType::SUBSCRIBE);
    
        // 定义fields
        // bigpipe中id的定义是uint64，php暂时定义为int64
        $this->_fields = array(
                'destination'  => 'string',
                'ack_type'     => 'int16',
                'start_point'  => 'int64',
                'subscribe_id' => 'string',
                'selector'     => 'string',
                'receipt_id'   => 'string',
        );
        $this->_fields_restriction = array(
                'destination' => BigpipeCommonDefine::MAX_SIZE_NAME,
                'subscribe_id'=> BigpipeCommonDefine::MAX_SIZE_NAME,
                'selector'    => BigpipeCommonDefine::MAX_SIZE_SELECTOR,
                'receipt_id'  => BigpipeCommonDefine::MAX_SIZE_RECEIPT_ID,
        );
    }
    
    /** field: stripe name (topic name) */
    public $destination = null;
    /**
     * field: client接收ack的模式
     * @var BStompClientAckType 
     */
    public $ack_type = BStompClientAckType::AUTO;
    public $start_point = null;
    /** field: 目前无用 */
    public $subscribe_id = null;
    /** field: 目前无用 */
    public $selector = null;
    /** field: 订阅号，由订阅者产生 "receipt-id-{时间戳}-{随机数}" */
    public $receipt_id = null;
} // BStompSubscribeFrame

/**
 * bigpipe stomp 取消订阅命令
 * @author yangzhenyu@baidu.com
 */
class BStompUnsubscribeFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(BStompFrameType::UNSUBSCRIBE);

        // 定义fields
        // bigpipe中id的定义是uint64，php暂时定义为int64
        $this->_fields = array(
                'destination'  => 'string',
                'subscribe_id' => 'string',
                'receipt_id'   => 'string',
        );
        $this->_fields_restriction = array(
                'destination' => BigpipeCommonDefine::MAX_SIZE_NAME,
                'subscribe_id'=> BigpipeCommonDefine::MAX_SIZE_NAME,
                'receipt_id'  => BigpipeCommonDefine::MAX_SIZE_RECEIPT_ID,
        );
    }

    /** field: stripe name (topic name) */
    public $destination = null;
    /** field: 目前无用 */
    public $subscribe_id = null;
    /** field: 订阅号，由订阅者产生 "receipt-id-{时间戳}-{随机数}" */
    public $receipt_id = null;
} // BStompSubscribeFrame

/**
 * bigpipe stomp 订阅命令的返回
 * @author yangzhenyu@baidu.com
 *
 */
class BStompReceiptFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(BStompFrameType::RECEIPT);
    
        // 定义fields
        // bigpipe中id的定义是uint64，php暂时定义为int64
        $this->_fields = array(
                'receipt_id'   => 'string',
        );
        $this->_fields_restriction = array(
                'receipt_id'  => BigpipeCommonDefine::MAX_SIZE_RECEIPT_ID,
        );
    }
    
    /** field: 订阅号，用于确认订阅消息 */
    public $receipt_id = null;
} // end of BStompReceiptFrame

/**
 * bigpipe stomp 订阅时接收的消息包
 * @author yangzhenyu@baidu.com
 */
class BStompMessageFrame extends BigpipeFrame
{
    public function __construct ()
    {
        parent::__construct(BStompFrameType::MESSAGE);

        // 定义fields
        // bigpipe中id的定义是uint64，php暂时定义为int64
        $this->_fields = array(
                'priority'   => 'int16',
                'persistent' => 'int16',
                'no_dedupe'  => 'int32',
                'timeout'    => 'int64',
                'destination'  => 'string',
                'session_id'   => 'string',
                'subscribe_id' => 'string',
                'receipt_id'   => 'string',
                'session_message_id' => 'int64',
                'topic_message_id'   => 'int64',
                'global_message_id'  => 'int64',
                'cur_checksum'       => 'int64',
                'last_checksum'      => 'int64',
                'message_body'       => 'blob',
        );
        $this->_fields_restriction = array(
                'destination'  => BigpipeCommonDefine::MAX_SIZE_NAME,
                'session_id'   => BigpipeCommonDefine::MAX_SIZE_NAME,
                'subscribe_id' => BigpipeCommonDefine::MAX_SIZE_NAME,
                'receipt_id'   => BigpipeCommonDefine::MAX_SIZE_RECEIPT_ID,
        );
    }

    // fields
    public $priority = null;
    public $persistent = null;
    public $no_dedupe = null;
    public $timeout = null;
    public $destination = null;
    public $session_id = null;
    public $subscribe_id = null;
    public $receipt_id = null;
    public $global_message_id = null;
    public $session_message_id = null;
    public $topic_message_id = null;
    public $cur_checksum = null;
    public $last_checksum = null;
    public $message_body = null;
} // end of BStompReceiptFrame

/**
 * bigpipe stomp 发布时用的消息包
 * @author yangzhenyu@baidu.com
 */
class BStompSendFrame extends BStompMessageFrame
{
    public function __construct()
    {
        parent::__construct();
        $this->command_type = BStompFrameType::SEND;
    }
} //end of BStompSendFrame

?>
