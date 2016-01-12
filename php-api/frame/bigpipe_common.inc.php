<?php
/**==========================================================================
 *
* bigpipe_common.inc.php - INF / DS / BIGPIPE
*
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
* Created on 2012-12-19 by YANG ZHENYU (yangzhenyu@baidu.com)
*
* --------------------------------------------------------------------------
*
* Description
*     bigpipe php-api中用到的common define
*
* --------------------------------------------------------------------------
*
* Change Log
*
*
==========================================================================**/
/**
 * php-api中的常量定义
 * @author: yangzhenyu@baidu.com
 */
class BigpipeCommonDefine
{
    // magic numbers
    /** CNsHead自动填写的magic number*/
    const NSHEAD_MAGICNUM             = 0xfb709394;
    /** bigpipe 指示做整包校验的magic number */
    const NSHEAD_CHECKSUM_MAGICNUM    = 1234321;
    /** zkc node当然版本号 */
    const META_HEADER_VERSION_CURRENT = 1;

    // common
    const MAX_SIZE_NAME            = 128;
    const MAX_SIZE_META_NAME       = 255;
    const MAX_SIZE_SESSION         = 255;

    /** 消息最大长度 (2 * 1024 * 1024) */
    const MAX_MESSAGE_LEN    = 2097152;
    /** 缓冲区最大长度 (2 * 1024 * 1024 + 1024) */
    const MAX_MBUFFER_LEN    = 2098176;

    // for connection
    const DEFAULT_READ_TIMEO = 60;

    /** 初始failover sleep time 单位: 毫秒*/
    const INIT_FO_SLEEP_TIME = 100;
    /** 最长的failover sleep时间， 单位：毫秒*/
    const MAX_FO_SLEEP_TIME  = 3000;

    /** 定义不设置peek timeout*/
    const NO_PEEK_TIMEOUT = 0;

    // for bigpipe stomp
    const MAX_SIZE_RECEIPT_ID    = 128;
    const MAX_SIZE_SELECTOR      = 1024;
    const MAX_SIZE_ERROR_MESSAGE = 1024;
    const MESSAGE_NO_DEDUPLICATE = 1;
} // end of BigpipeCommonDefine

/**
 * 订阅点类型
 * @author yangzhenyu@baidu.com
 */
class SubscribeStartPoint
{
    const START_FROM_FIRST_POINT = -2;
    const START_FROM_CURRENT_POINT = -1;
    const START_FROM_FILE = 0;

    /**
     * 检查订阅点是否有效
     * @return true on success or false on failure
     */
    public static function is_valid($start_point)
    {
        if ($start_point < -2)
        {
            return false;
        }
        return true;
    }
} // end of SubscribePointType

/**
 * 选择broker连接策略
 * @author yangzhenyu@baidu.com
 */
class BigpipeConnectPreferType
{
    /** 只连接主broker */
    const PRIMARY_BROKER_ONLY   = 0x1;
    /** 只连接从broker */
    const SECONDARY_BROKER_ONLY = 0x2;
    /** 只连接common broker */
    const COMMON_BROKER_ONLY    = 0x4;
} // end of BigpipeConnectPreferType

/**
 * broker角色定义
 * @author yangzhenyu@baidu.com
 */
class BigpipeBrokerRole
{
    const PRIMARY   = 1;
    const SECONDARY = 2;
} // end of BigpipeBrokerRole

/**
 * 在meta中标注了一个broker group的工作状态
 */
class BigpipeBrokerGroupStatus
{
    /** group 可订阅，但不能发布  */
    const SAFE   = 1;
    /** group 可订阅、发布  */
    const NORMAL = 2;
    /** group不可用 */
    const FAIL   = 3;
} // end of BigpipeBrokerGroupStatus

/**
 * 在meta中标注了一个queue的状态
 */
class BigpipeQueueStatus
{
    const CREATED = 0;
    const STARTED = 1;
    const STOPPED = 2;
    const DELETED = 3;
    
    /** 将queue状态转为文字描述*/
    public static function to_string($status)
    {
        if ($status < self::CREATED
            || $status > self::DELETED)
        {
            return 'UNKNOWN';
        }
        else
        {
            return self::$_status_msg[$status];
        }
    }
    
    /** queue状态文字描述 */
    private static $_status_msg = array(
            self::CREATED => 'CREATED',
            self::STARTED => 'STARTED',
            self::STOPPED => 'STOPPED',
            self::DELETED => 'DELETED',
            );
} // end of BigpipeQueueStatus

/**
 * 标注了meta中一个entry node的状态
 */
class MetaNodeStatus
{
    /** 节点可用 */
    const NORMAL = 0;
    /** 节点正在被修改 */
    const MODIFICATION = 1;
} // MetaContentStatus

/**
 * bigpipe php api 错误码
 * @author yangzhenyu@baidu.com
 */
class BigpipeErrorCode
{
    // 以下是基本错误码
    /** 无错 */
    const OK               = 0;
    /** 网络连接错误 */
    const ERROR_CONNECTION = -1;
    /** 超时错误 */
    const TIMEOUT          = -2;
    /** 参数错误 */
    const INVALID_PARAM    = -3;
    /** 没有初始化 */
    const UNINITED         = -4;

    // 以下是subscribe peek时的错误码
    /** 有可接收数据 */
    const READABLE         = -10;
    /** 无可接收的数据 */
    const UNREADABLE       = -11;
    /** 订阅失败 */
    const ERROR_SUBSCRIBE  = -12;
    /** peek超时错误 */
    const PEEK_TIMEOUT     = -13;
    const PEEK_ERROR       = -14;
} // end of BigpipeErrorCode

class BigpipeChecksumLevel
{
    /** 没有checksum */
    const DISABLE = 0;
    /** 对message做数字签名校验 */
    const CHECK_MESSAGE = 1;
    /** 对frame包做checksum校验 */
    const CHECK_FRAME = 3;
} // end of BigpipeChecksumLevel

/**
 * Queue Sever的command type
 * @author yangzhenyu@baidu.com
 */
class BigpipeQueueSvrCmdType
{
    /** 向queue server请求订阅数据 */
    const REQ_QUEUE_DATA = 1;
    /** 向queue
     *  server应答已接收到的数据，真实应答，告知server推动真实数据  */
    const ACK_QUEUE_TRUE_DATA = 2;
    /** 向queue
     *  server应答已接收到的数据，虚假应答，告知server端推送虚假数据  */
    const ACK_QUEUE_FAKE_DATA = 3;
} // BigpipeQueueCmdType

?>
