<?php
/**==========================================================================
 * 
 * TestBigpipeConfiguration.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-23 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

class TestBigpipeConfiguration extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->actual_elem = new BigpipeConnectionConf;
    }
    
    public function tearDown()
    {
    }
    
    /**
     * 测试1 测试BgipipeConnectionConf中的每个property都被正确赋值
     */
    public function testArrayToObject()
    {
        $expected_conf = array(
                'try_time'    => 20,
                'conn_timeo'  => 10000,
                'read_timeo'  => 15000,
                'time_limit'  => 30,
                'check_frame' => true,
        );

        $ret = $this->actual_elem->load($expected_conf);
        $this->assertTrue($ret); // 返回值必须正确
        foreach($expected_conf as $key => $val)
        {
            $prop = new ReflectionProperty($this->actual_elem, $key);
            $actual_val = $prop->getValue($this->actual_elem);
            $this->assertEquals($val, $actual_val);
        }
    }
    
    /**
     * 测试2 测试BgipipeConnectionConf错误分支
     */
    public function testArrayToObjectError()
    {
        // 以下conf中缺少了read_timeo
        $expected_error_conf = array(
                'try_time'    => 20,
                'conn_timeo'  => 10000,
                'time_limit'  => 30,
                'check_frame' => true,
        );
        $this->actual_elem->read_timeo = null;

        // 错误1: 某个mandatory元素未被读取
        $ret = $this->actual_elem->load($expected_error_conf);
        $this->assertFalse($ret); // 返回值是错误值
        
        // 错误2: 传入的configure是个复合conf(config array中嵌套congfig array数组)
        $expected_error_conf['read_timeo'] = array(
                15000,
                25000,
                35000,);
        $ret = $this->actual_elem->load($expected_error_conf);
        $this->assertFalse($ret);
    }
    
    /**
     * 测试BgipipeConnectionConf异常分支
     */
    public function testArrayToObjectException()
    {
        $expected_error_conf = array(
                'try_time'    => 20,
                'conn_timeo'  => 10000,
                'read_timeo'  => 15000,
                'time_limit'  => 30,
                'check_frame' => true,
                'mk_exception'=> 100,
        );

        $exception_elem = new FakeConf;
        $ret = $exception_elem->load($expected_error_conf);
        $this->assertFalse($ret); // 返回值是错误值
    }

    /**
     * 测试读取BigpieConf
     */
    public function testBigpieConf()
    {
        $conf_dir = './conf';
        $conf_file = 'php-api.conf';
        $content = new BigpipeConf;

        // 测试1 成功分支
        $this->assertTrue(bigpipe_load_file($conf_dir, $conf_file, $content));

        // 测试2 错误分支
        $conf_arr = config_load($conf_dir, $conf_file);
        $this->assertTrue(false != $conf_arr);

        // 测试2.1 无stomp
        $stomp = $conf_arr['stomp'];
        $conf_arr['stomp'] = null;
        $this->assertFalse($content->load($conf_arr));

        // 测试2.2 load stomp节点失败
        // 无peek_timeo
        $conf_arr['stomp'] = $stomp;
        $peek_timeo = $conf_arr['stomp']['peek_timeo'];
        unset($conf_arr['stomp']['peek_timeo']);
        $this->assertFalse($content->load($conf_arr));
        $conf_arr['stomp']['peek_timeo'] = $peek_timeo;
        // 无connection
        $connection = $conf_arr['stomp']['connection'];
        unset($conf_arr['stomp']['connection']);
        $this->assertFalse($content->load($conf_arr));
        $conf_arr['stomp']['connection'] = $connection;

        // 测试2.3 无meta_agent
        $meta_agent = $conf_arr['meta_agent'];
        $conf_arr['meta_agent'] = null;
        $this->assertFalse($content->load($conf_arr));
        $conf_arr['meta_agent'] = $meta_agent;

        // 测试2.4 load meta agent节点失败
        // meta 没设
        $meta = $conf_arr['meta_agent']['meta'];
        unset($conf_arr['meta_agent']['meta']);
        $this->assertFalse($content->load($conf_arr));
        $conf_arr['meta_agent']['meta'] = $meta;
        // agent 没设
        $agent = $conf_arr['meta_agent']['agent'];
        unset($conf_arr['meta_agent']['agent']);
        $this->assertFalse($content->load($conf_arr));
        $conf_arr['meta_agent']['agent'] = $agent;
        // connection 没设
        $connection = $conf_arr['meta_agent']['connection'];
        unset($conf_arr['meta_agent']['connection']);
        $this->assertFalse($content->load($conf_arr));
        $conf_arr['meta_agent']['connection'] = $connection;
    }

    /**
     * 测试读取queue client
     */
    public function testBigpipeQueueConf()
    {
        $conf_dir = './conf';
        $conf_file = 'queue_util.conf';
        $content = config_load($conf_dir, $conf_file);
        $this->assertTrue(false != $content);
        $conf = new BigpipeQueueConf;
        // 测试1 成功分支
        $this->assertTrue($conf->load($content));

        // 测试2 失败
        $queue = $content['queue'];
        $content['queue'] = null;
        $this->assertFalse($conf->load($content));
        $content['queue'] = $queue;
    }
} // end of TestSubscribeStartPoint

/**
 * 用于测试异常分支的类
 */
class FakeConf extends BigpipeConnectionConf
{
    private $mk_exception = null;
}

//$suite = new PHPUnit_Framework_TestSuite("TestBigpipeConfiguration");
//$suite->addTestFile
//$result = PHPUnit::run($suite);
//print_r($result);
//PHPUnit_TextUI_TestRunner::run($suite);
/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
