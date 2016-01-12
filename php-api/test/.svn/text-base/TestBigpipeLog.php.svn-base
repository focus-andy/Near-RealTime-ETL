<?php
/**==========================================================================
 * 
 * TestBigpipeLog.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2013-01-05 by YANG ZHENYU (yangzhenyu@baidu.com)
 * 
 * --------------------------------------------------------------------------
 * 
 * Description
 * 
 * 
 * --------------------------------------------------------------------------
 * 
 * Change Log
 * 
 * 
 ==========================================================================**/
require_once(dirname(__FILE__).'/TestUtilities.class.php'); 
require_once(dirname(__FILE__).'/../frame/BigpipeLog.class.php');

class TestBigpipeLog extends PHPUnit_Framework_TestCase
{
    public function testAllInOne()
    {
        // ²âÊÔ´òÓ¡µ½ÆÁÄ»
        BigpipeLog::debug('[%s:%u][%s][debug]',__FILE__, __LINE__, __FUNCTION__);
        BigpipeLog::trace('[%s:%u][%s][trace]',__FILE__, __LINE__, __FUNCTION__);
        BigpipeLog::notice('[%s:%u][%s][notice]',__FILE__, __LINE__, __FUNCTION__);
        BigpipeLog::monitor('[%s:%u][%s][monitor]',__FILE__, __LINE__, __FUNCTION__);
        BigpipeLog::warning('[%s:%u][%s][warning]',__FILE__, __LINE__, __FUNCTION__);
        BigpipeLog::fatal('[%s:%u][%s][fatal]',__FILE__, __LINE__, __FUNCTION__);

        // ²âÊÔ²»Êä³ö´òÓ¡ÐÅÏ¢log configure
        $conf = new BigpipeLogConf;
        $conf->severity = BigpipeLogSeverity::FATAL;
        $conf->disable_ostream = true;
        $fatal_log = sprintf('[%s:%u][%s][fatal message]',__FILE__, __LINE__, __FUNCTION__);
        $warning_Log = sprintf('[%s:%u][%s][warning]',__FILE__, __LINE__, __FUNCTION__);
        $this->assertTrue(BigpipeLog::init($conf));
        BigpipeLog::fatal('%s', $fatal_log);
        $msg = BigpipeLog::get_last_error_message();
        BigpipeLog::warning('%s', $warning_Log);
        $this->assertEquals($msg, BigpipeLog::get_last_error_message());
        $new_fatal_log = sprintf('[%s:%u][%s][fatal new message]',__FILE__, __LINE__, __FUNCTION__);
        $this->assertTrue($msg == BigpipeLog::get_last_error_message());

        // ²âÊÔclose
        BigpipeLog::close();
    }
} // end of TestBigpipeLog

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
