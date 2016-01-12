<?php
/**==========================================================================
 * 
 * TestSubscribeStartPoint.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-18 by YANG ZHENYU (yangzhenyu@baidu.com)
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
//require_once(dirname(__FILE__).'/PHPUnit/Framework.php');
//require_once(dirname(__FILE__).'/PHPUnit/TextUI/TestRunner.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_common.inc.php');

class TestSubscribeStartPoint extends PHPUnit_Framework_TestCase
{
    public function testIsValid()
    {
        $this->assertTrue(SubscribeStartPoint::is_valid(SubscribeStartPoint::START_FROM_FIRST_POINT));
        $this->assertTrue(SubscribeStartPoint::is_valid(SubscribeStartPoint::START_FROM_CURRENT_POINT));
        $normal_position = 65535;
        $this->assertTrue(SubscribeStartPoint::is_valid($normal_position));
        $abnormal_position = -42;
        $this->assertFalse(SubscribeStartPoint::is_valid($abnormal_position));
    }

} // end of TestSubscribeStartPoint

//$suite = new PHPUnit_Framework_TestSuite("TestSubscribeStartPoint");
//$suite->addTestFile
//$result = PHPUnit::run($suite);
//print_r($result);
//PHPUnit_TextUI_TestRunner::run($suite);
/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
