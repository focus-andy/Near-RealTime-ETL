<?php
/***************************************************************************
 *
* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
*
* @file  : bigpipe_utilities.inc.php
* @brief :
*     bigpipe php-api中用到的函数
*
****************************************************************************/
require_once(dirname(__FILE__).'/BigpipeLog.class.php');

/**
 * 封装bigpipe php-api中调用的函数
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
     * 生成unique id
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

        // 此处两个随机数代替pid和tid
        $uid = sprintf('%s-%u-%u%u', $hostip, self::get_time_us(), rand(), rand());
        return $uid;
    }

    /**
     * 生成host address
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
     * 生成unique id
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

        // session格式为
        // hostip + 精确得到1天半的时间戳 + pid + pipe_name
        $timestamp = (time() >> 17); // 精确到1天半的时间戳
        $uid = sprintf('%s-%u-%u-%s', $hostip, $timestamp, getmypid(), $pipe_name);
        return $uid;
    }

    /**
     * 生成receipt id
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
     * 计算adler32校验码
     * @param binary string $data
     * @return uint32类型的校验码
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
