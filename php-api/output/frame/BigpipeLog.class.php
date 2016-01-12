<?php
/***************************************************************************
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 ****************************************************************************/
require_once(dirname(__FILE__)."/../ext/mc_log.inc.php");

/**
 * 封装__mc_log定义log的等级
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
 * 日志文件配置
 * @author yangzhenyu@baidu.com
 *
 */
class BigpipeLogConf
{
    /** 日志目录名 */
    public $dir      = './log/';
    /** 日志不含后缀的文件名 */
    public $file     = 'meta-agent';
    /** 日志输出等级 */
    public $severity = BigpipeLogSeverity::WARNING;
    /** 选择日志包含的基本信息 */
    public $format = array(
            'logid',
            'reqip',
            'uid',
            'uname',
            'method',);
    /** 日志是否直接输出到硬盘，false的话会有4k缓存 */
    public $flush = true;
    /** 是否禁止向文件/标准流输出 */
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
     * 打印DEBUG日志
     * @param string $fmt:      格式字符串
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
     * 打印TRACE日志
     * @param string $fmt:      格式字符串
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
     * 打印NOTICE日志,一般一次请求只打一条
     * @param string $fmt:      格式字符串
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
     * 打印MONITOR日志,主要用于监控
     * @param string $fmt      格式字符串
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
     * 打印WANRING日志
     * @param string $fmt      格式字符串
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
     * 打印FATAL日志,会同时打出MONITOR日志的标识
     * @param string $fmt      格式字符串
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
     * 打印日志
     * @param BigpipeLogSeverity $severity
     * @param array $args
     */
    private static function log($severity, $args)
    {
        if (self::$_severity < $severity)
        {
            // 跳过低级别
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
        // [严重性][时间]
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
