<?php
/***************************************************************************
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 ****************************************************************************/
require_once(dirname(__FILE__)."/../ext/mc_log.inc.php");

/**
 * ��װ__mc_log����log�ĵȼ�
 * @author yangzhenyu@baidu.com
 *
 */
class BigpipeLogSeverity
{
    const FATAL   = 1;
    const WARNING = 2;
    const MONITOR = 3;
    const NOTICE  = 4;
    const TRACE   = 8;
    const DEBUG   = 16;
    
    /**
     * @param $severity: severity level
     * @return the string of severity type
     */
    public static function to_string($severity)
    {
        if (isset(self::$_typestring[$severity]))
        {
            return self::$_typestring[$severity];
        }
        
        return 'unknown';
    }
    
    /** severity string type */
    private static $_typestring = array (
            BigpipeLogSeverity::FATAL   => 'fatal',
            BigpipeLogSeverity::WARNING => 'warning',
            BigpipeLogSeverity::MONITOR => 'monitor',
            BigpipeLogSeverity::NOTICE  => 'notice',
            BigpipeLogSeverity::TRACE   => 'trace',
            BigpipeLogSeverity::DEBUG   => 'debug',
            );

} // end of BigpipeLogSeverity

/**
 * ��־�ļ�����
 * @author yangzhenyu@baidu.com
 *
 */
class BigpipeLogConf
{
    /** ��־Ŀ¼�� */
    public $dir      = './log/';
    /** ��־������׺���ļ��� */
    public $file     = 'meta-agent';
    /** ��־����ȼ� */
    public $severity = BigpipeLogSeverity::WARNING;
    /** ѡ����־�����Ļ�����Ϣ */
    public $format = array(
            'logid',
            'reqip',
            'uid',
            'uname',
            'method',);
    /** ��־�Ƿ�ֱ�������Ӳ�̣�false�Ļ�����4k���� */
    public $flush = true;
    /** �Ƿ��ֹ���ļ�/��׼����� */
    public $disable_ostream = false;
} // end of BigpipeLogConf

class BigpipeLog
{
    public static function init($conf)
    {
        if (!file_exists($conf->dir))
        {
            if (!mkdir($conf->dir, 0777, true))
            {
                echo "[fail to init log][can not mkdir][path: $conf->dir]<br>";
                return false;
            }
        }

        $ret = ub_log_init($conf->dir, $conf->file, $conf->severity, $conf->format, $conf->flush);
        if (!$ret)
        {
            return $ret;
        }
//        self::$_logfile = fopen("$conf->dir/$conf->file", "a+");
//        echo "[start log][write log to $conf->dir/$conf->file]<br>";
        self::$_severity = $conf->severity;
        self::$_disable_ostream = $conf->disable_ostream;
        self::$_logconf = $conf;
        return true;
    }

    /**
     * ��ӡDEBUG��־
     * @param string $fmt:      ��ʽ�ַ���
     * @param mixed  $arg:      data
     * @return void
     */
    public static function debug()
    {
        $arg = func_get_args();
        if (false === self::$_disable_ostream)
        {
            __ub_log(__mc_log::LOG_DEBUG, $arg);
        }
        self::log(BigpipeLogSeverity::DEBUG, $arg);
    }

    /**
     * ��ӡTRACE��־
     * @param string $fmt:      ��ʽ�ַ���
     * @param mixed  $arg:      data
     * @return void
     */
    public static function trace()
    {
        $arg = func_get_args();
        if (false === self::$_disable_ostream)
        {
            __ub_log(__mc_log::LOG_TRACE, $arg);
        }
        self::log(BigpipeLogSeverity::TRACE, $arg);
    }

    /**
     * ��ӡNOTICE��־,һ��һ������ֻ��һ��
     * @param string $fmt:      ��ʽ�ַ���
     * @param mixed  $arg:      data
     * @return void
     */
    public static function notice()
    {       
        $arg = func_get_args();
        if (false === self::$_disable_ostream)
        {
            __ub_log(__mc_log::LOG_NOTICE, $arg);
        }
        self::log(BigpipeLogSeverity::NOTICE, $arg);
    }

    /**
     * ��ӡMONITOR��־,��Ҫ���ڼ��
     * @param string $fmt      ��ʽ�ַ���
     * @param mixed  $arg      data
     * @return void
     */
    public static function monitor()
    {
        $arg = func_get_args();
        if (false === self::$_disable_ostream)
        {
            __ub_log(__mc_log::LOG_MONITOR, $arg);
        }
        self::log(BigpipeLogSeverity::MONITOR, $arg);
    }

    /**
     * ��ӡWANRING��־
     * @param string $fmt      ��ʽ�ַ���
     * @param mixed  $arg      data
     * @return void
     */
    public static function warning()
    {
        $arg = func_get_args();
        if (false === self::$_disable_ostream)
        {
            __ub_log(__mc_log::LOG_WARNING, $arg);
        }
        self::log(BigpipeLogSeverity::WARNING, $arg);
    }

    /**
     * ��ӡFATAL��־,��ͬʱ���MONITOR��־�ı�ʶ
     * @param string $fmt      ��ʽ�ַ���
     * @param mixed  $arg      data
     * @return void
     */
    public static function fatal()
    {
        $arg = func_get_args();
        if (false === self::$_disable_ostream)
        {
            __ub_log(__mc_log::LOG_FATAL, $arg);
        }
        self::log(BigpipeLogSeverity::FATAL, $arg);
    }

    /**
     * close log handler
     */
    public static function close()
    {
        $conf = self::$_logconf;
        // fclose(self::$_logfile);
        self::$_last_error_message = sprintf('[%s:%u][%s][stop log]', __FILE__, __LINE__, __FUNCTION__);
    }

    public static function get_last_error_message()
    {
        return self::$_last_error_message;
    }

    /**
     * ��ӡ��־
     * @param BigpipeLogSeverity $severity
     * @param array $args
     */
    private static function log($severity, $args)
    {
        if (self::$_severity < $severity)
        {
            // �����ͼ���
            return;
        }
        $log_str = null;
        $num_args = count($args);
        if (1 < $num_args)
        {
            $format = array_shift($args);
            $log_str = vsprintf($format, $args);
        }
        else if (1 == $num_args)
        {
            $log_str = vsprintf('%s', $args[0]);
        }
        else
        {
            $log_str = "[empty log args]";
        }
        // [������][ʱ��]
        $out_str = sprintf("[%s]%s\n", BigpipeLogSeverity::to_string($severity), $log_str);
        self::$_last_error_message = $out_str;
        //         else
        //         {
        //             fwrite(self::$_logfile, $out_str);
        //         }
    }

    private static $_logfile = null;
    private static $_severity = BigpipeLogSeverity::DEBUG;
    private static $_logconf = null;
    private static $_disable_ostream = false;
    private static $_last_error_message = '';
} // end of MetaAgentLogger
?>
