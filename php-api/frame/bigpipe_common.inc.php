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
*     bigpipe php-api���õ���common define
*
* --------------------------------------------------------------------------
*
* Change Log
*
*
==========================================================================**/
/**
 * php-api�еĳ�������
 * @author: yangzhenyu@baidu.com
 */
class BigpipeCommonDefine
{
    // magic numbers
    /** CNsHead�Զ���д��magic number*/
    const NSHEAD_MAGICNUM             = 0xfb709394;
    /** bigpipe ָʾ������У���magic number */
    const NSHEAD_CHECKSUM_MAGICNUM    = 1234321;
    /** zkc node��Ȼ�汾�� */
    const META_HEADER_VERSION_CURRENT = 1;

    // common
    const MAX_SIZE_NAME            = 128;
    const MAX_SIZE_META_NAME       = 255;
    const MAX_SIZE_SESSION         = 255;

    /** ��Ϣ��󳤶� (2 * 1024 * 1024) */
    const MAX_MESSAGE_LEN    = 2097152;
    /** ��������󳤶� (2 * 1024 * 1024 + 1024) */
    const MAX_MBUFFER_LEN    = 2098176;

    // for connection
    const DEFAULT_READ_TIMEO = 60;

    /** ��ʼfailover sleep time ��λ: ����*/
    const INIT_FO_SLEEP_TIME = 100;
    /** ���failover sleepʱ�䣬 ��λ������*/
    const MAX_FO_SLEEP_TIME  = 3000;

    /** ���岻����peek timeout*/
    const NO_PEEK_TIMEOUT = 0;

    // for bigpipe stomp
    const MAX_SIZE_RECEIPT_ID    = 128;
    const MAX_SIZE_SELECTOR      = 1024;
    const MAX_SIZE_ERROR_MESSAGE = 1024;
    const MESSAGE_NO_DEDUPLICATE = 1;
} // end of BigpipeCommonDefine

/**
 * ���ĵ�����
 * @author yangzhenyu@baidu.com
 */
class SubscribeStartPoint
{
    const START_FROM_FIRST_POINT = -2;
    const START_FROM_CURRENT_POINT = -1;
    const START_FROM_FILE = 0;

    /**
     * ��鶩�ĵ��Ƿ���Ч
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
 * ѡ��broker���Ӳ���
 * @author yangzhenyu@baidu.com
 */
class BigpipeConnectPreferType
{
    /** ֻ������broker */
    const PRIMARY_BROKER_ONLY   = 0x1;
    /** ֻ���Ӵ�broker */
    const SECONDARY_BROKER_ONLY = 0x2;
    /** ֻ����common broker */
    const COMMON_BROKER_ONLY    = 0x4;
} // end of BigpipeConnectPreferType

/**
 * broker��ɫ����
 * @author yangzhenyu@baidu.com
 */
class BigpipeBrokerRole
{
    const PRIMARY   = 1;
    const SECONDARY = 2;
} // end of BigpipeBrokerRole

/**
 * ��meta�б�ע��һ��broker group�Ĺ���״̬
 */
class BigpipeBrokerGroupStatus
{
    /** group �ɶ��ģ������ܷ���  */
    const SAFE   = 1;
    /** group �ɶ��ġ�����  */
    const NORMAL = 2;
    /** group������ */
    const FAIL   = 3;
} // end of BigpipeBrokerGroupStatus

/**
 * ��meta�б�ע��һ��queue��״̬
 */
class BigpipeQueueStatus
{
    const CREATED = 0;
    const STARTED = 1;
    const STOPPED = 2;
    const DELETED = 3;
    
    /** ��queue״̬תΪ��������*/
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
    
    /** queue״̬�������� */
    private static $_status_msg = array(
            self::CREATED => 'CREATED',
            self::STARTED => 'STARTED',
            self::STOPPED => 'STOPPED',
            self::DELETED => 'DELETED',
            );
} // end of BigpipeQueueStatus

/**
 * ��ע��meta��һ��entry node��״̬
 */
class MetaNodeStatus
{
    /** �ڵ���� */
    const NORMAL = 0;
    /** �ڵ����ڱ��޸� */
    const MODIFICATION = 1;
} // MetaContentStatus

/**
 * bigpipe php api ������
 * @author yangzhenyu@baidu.com
 */
class BigpipeErrorCode
{
    // �����ǻ���������
    /** �޴� */
    const OK               = 0;
    /** �������Ӵ��� */
    const ERROR_CONNECTION = -1;
    /** ��ʱ���� */
    const TIMEOUT          = -2;
    /** �������� */
    const INVALID_PARAM    = -3;
    /** û�г�ʼ�� */
    const UNINITED         = -4;

    // ������subscribe peekʱ�Ĵ�����
    /** �пɽ������� */
    const READABLE         = -10;
    /** �޿ɽ��յ����� */
    const UNREADABLE       = -11;
    /** ����ʧ�� */
    const ERROR_SUBSCRIBE  = -12;
    /** peek��ʱ���� */
    const PEEK_TIMEOUT     = -13;
    const PEEK_ERROR       = -14;
} // end of BigpipeErrorCode

class BigpipeChecksumLevel
{
    /** û��checksum */
    const DISABLE = 0;
    /** ��message������ǩ��У�� */
    const CHECK_MESSAGE = 1;
    /** ��frame����checksumУ�� */
    const CHECK_FRAME = 3;
} // end of BigpipeChecksumLevel

/**
 * Queue Sever��command type
 * @author yangzhenyu@baidu.com
 */
class BigpipeQueueSvrCmdType
{
    /** ��queue server���������� */
    const REQ_QUEUE_DATA = 1;
    /** ��queue
     *  serverӦ���ѽ��յ������ݣ���ʵӦ�𣬸�֪server�ƶ���ʵ����  */
    const ACK_QUEUE_TRUE_DATA = 2;
    /** ��queue
     *  serverӦ���ѽ��յ������ݣ����Ӧ�𣬸�֪server�������������  */
    const ACK_QUEUE_FAKE_DATA = 3;
} // BigpipeQueueCmdType

?>
