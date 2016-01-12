<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
* @file  : bigpipe_utilities.inc.php
* @brief :
*     bigpipe php-api���õ��ĺ���
*
****************************************************************************/
require_once(dirname(__FILE__).'/BigpipeLog.class.php');

/**
 * ��װbigpipe php-api�е��õĺ���
 */
class BigpipeUtilities
{
    public static function get_time_us()
    {
        list($usec, $sec) = explode(' ', microtime());
        $micro = ((float)$sec + (float)$usec) * 1000000;
        return $micro;
    }

    /**
     * ����unique id
     * @return string unique id
     */
    public static function get_uid()
    {
        $hostip = '127.0.0.0'; // default host ip
        ##$host = gethostname(); // can not be used before php 5.3
        $host = php_uname('n');
        if (false === $host)
        {
            BigpipeLog::warning('[%s:%u][%s][no host name. use default value: %s]',
            __FILE__, __LINE__, __FUNCTION__, $hostip);
        }
        else
        {
            $hostip = gethostbyname($host);
            if (false === $hostip)
            {
                BigpipeLog::warning('[%s:%u][%s][no ip on host use default local ip: 127.0.0.0][host name:%s]',
                __FILE__, __LINE__, __FUNCTION__, $host);
            }
        }

        // �˴��������������pid��tid
        $uid = sprintf('%s-%u-%u%u', $hostip, self::get_time_us(), rand(), rand());
        return $uid;
    }

    /**
     * ����host address
     * @return host address
     */
    public static function get_host_address()
    {
        $hostip = '127.0.0.0'; // default host ip
        ##$host = gethostname(); // can not be used before php 5.3
        $host = php_uname('n');
        if (false === $host)
        {
            BigpipeLog::warning('[%s:%u][%s][no host name. use default value: %s]',
            __FILE__, __LINE__, __FUNCTION__, $hostip);
        }
        else
        {
            $hostip = gethostbyname($host);
            if (false === $hostip)
            {
                BigpipeLog::warning('[%s:%u][%s][no ip on host use default local ip: 127.0.0.0][host name:%s]',
                __FILE__, __LINE__, __FUNCTION__, $host);
            }
        }
        return $hostip;
    }

    /**
     * ����unique id
     * @return string unique id
     */
    public static function get_session_id($pipe_name)
    {
        $hostip = '127.0.0.0'; // default host ip
        ##$host = gethostname(); // can not be used before php 5.3
        $host = php_uname('n');
        if (false === $host)
        {
            BigpipeLog::warning('[%s:%u][%s][no host name. use default value: %s]',
            __FILE__, __LINE__, __FUNCTION__, $hostip);
        }
        else
        {
            $hostip = gethostbyname($host);
            if (false === $hostip)
            {
                BigpipeLog::warning('[%s:%u][%s][no ip on host use default local ip: 127.0.0.0][host name:%s]',
                __FILE__, __LINE__, __FUNCTION__, $host);
            }
        }

        // session��ʽΪ
        // hostip + ��ȷ�õ�1����ʱ��� + pid + pipe_name
        $timestamp = (time() >> 17); // ��ȷ��1����ʱ���
        $uid = sprintf('%s-%u-%u-%s', $hostip, $timestamp, getmypid(), $pipe_name);
        return $uid;
    }

    /**
     * ����receipt id
     * @return string
     */
    public static function gen_receipt_id()
    {
        $uid = sprintf('receipt-id-%u-%u', self::get_time_us(), rand());
        return $uid;
    }

    /**
     * pipelet name
     * @param string $pipe_name
     * @param number $pipelet_id
     * @return string
     */
    public static function get_pipelet_name($pipe_name, $pipelet_id)
    {
        return ($pipe_name . '_' . $pipelet_id);
    }

    /**
     * ����adler32У����
     * @param binary string $data
     * @return uint32���͵�У����
     */
    public static function adler32($data)
    {
        //calculate a Adler32 checksum with the bytes data[start..len-1]
        $s1 = 1;
        $s2 = 0;
        $data_len = strlen($data);
        for($n = 0; $n < $data_len; $n++)
        {
            $s1 = ($s1 + ord($data[$n])) % 65521;
            $s2 = ($s2 + $s1) % 65521;
        }
        $adler = ($s2 << 16) | $s1;
        return $adler;
    }
} // end of BigpipeUtilities
?>
