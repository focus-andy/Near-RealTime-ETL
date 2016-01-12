<?php
/**==========================================================================
 * 
 * ./test/TestBigpipePublisher.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-24 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../BigpipePublisher.class.php');
require_once(dirname(__FILE__).'/../frame/BigpipeMessagePackage.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

/**
 * 测试bigpipe发布类
 */
class TestBigpipePublisher extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->pipe_name = 'unit-test';
        $this->token = 'unit-test-token';
        $this->partitioner = new BigpipePubPartitioner;
        $this->conf = new BigpipeConf;
        $this->conf->checksum_level = BigpipeChecksumLevel::CHECK_FRAME;
    }

    public function testInit()
    {
        // create a stub for MetaAgentAdapter
        $stub_meta = $this
            ->getMockBuilder('MetaAgentAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        // configure the stubs
        $stub_meta->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(true, false, true, true));

        $stub_meta->expects($this->any())
            ->method('init')
            ->will($this->onConsecutiveCalls(true, false, true, true, true));

        $good_author = array(
            'authorized'  => true,
            'num_pipelet' => 10,
        );

        $bad_author = false;

        $failed_author = array(
            'authorized' => false,
            'reason'     => 'fake author'
        );

        $stub_meta->expects($this->any())
            ->method('authorize')
            ->will($this->onConsecutiveCalls($good_author, $bad_author, $failed_author));

        $subject = new BigpipePublisher;
        // 将set mock class to subject
        
        $this->assertTrue(TestUtilities::set_private_var($subject, '_meta_adapter', $stub_meta));

        // 测试1 成功 init
        $this->assertTrue($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $this->conf)
        );

        // 测试2 重复初始化
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $this->conf));

        // 测试3 empty conf
        $this->assertTrue(TestUtilities::set_private_var($subject, '_inited', false));
        $error_conf = null;
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $error_conf));

        // 测试4 checksum level问题
        $error_conf = new BigpipeConf;
        $error_conf->checksum_level = 65535;
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $error_conf));

        // 测试5 empty stomp configure
        $error_conf = new BigpipeConf;
        $error_conf->stomp_conf = null;
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $error_conf));
        // 测试6 init fail
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $this->conf));

        // 测试7 connect failed
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $this->conf));
        
        // 测试8 认证返回失败
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $this->conf));

        // 测试9 认证不通过:
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->partitioner,
            $this->conf));
    }

    public function testInitEx()
    {
        // create a stub for MetaAgentAdapter
        $stub_meta = $this
            ->getMockBuilder('MetaAgentAdapter')
            ->disableOriginalConstructor()
            ->getMock();
    
        // configure the stubs
        $num_piplet = 10;
        $stub_meta->expects($this->any())
            ->method('connect_ex')
            ->will($this->onConsecutiveCalls(
                $num_piplet, false));

        $subject = new BigpipePublisher;
        // 将set mock class to subject
        $this->assertTrue(TestUtilities::set_private_var($subject, '_meta_adapter', $stub_meta));

        // 测试1 成功 init
        $this->assertTrue($subject->init_ex(
                $this->pipe_name,
                $this->token,
                $this->partitioner,
                $this->conf)
        );

        // 测试2 重复初始化
        $this->assertFalse($subject->init_ex(
                $this->pipe_name,
                $this->token,
                $this->partitioner,
                $this->conf));

        // 测试3 empty conf
        $this->assertTrue(TestUtilities::set_private_var($subject, '_inited', false));
        $error_conf = null;
        $this->assertFalse($subject->init_ex(
                $this->pipe_name,
                $this->token,
                $this->partitioner,
                $error_conf));

        // 测试4 checksum level问题
        $error_conf = new BigpipeConf;
        $error_conf->checksum_level = 65535;
        $this->assertFalse($subject->init(
                $this->pipe_name,
                $this->token,
                $this->partitioner,
                $error_conf));

        // 测试5 empty stomp configure
        $error_conf = new BigpipeConf;
        $error_conf->stomp_conf = null;
        $this->assertFalse($subject->init_ex(
                $this->pipe_name,
                $this->token,
                $this->partitioner,
                $error_conf));

        // 测试6 connect failed
        $this->assertFalse($subject->init_ex(
                $this->pipe_name,
                $this->token,
                $this->partitioner,
                $this->conf));
    }

    public function testSend()
    {
        $pkg = new BigpipeMessagePackage;
        $subject = new BigpipePublisher;
        $num_piplet = 10;

        // 测试1 使用未初始化的发布类
        $this->assertFalse($subject->send($pkg));

        // 强制设定已初始化
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 测试2 get_pipelet_id错误
        $stub_paritioner = $this
            ->getMockBuilder('BigpipePubPartitioner')
            ->disableOriginalConstructor()
            ->getMock();

        // configure the stubs
        $test_piplet = 2;
        $stub_paritioner->expects($this->any())
            ->method('get_pipelet_id')
            ->will($this->onConsecutiveCalls($num_piplet + 1, $test_piplet, $test_piplet, $test_piplet));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_partitioner', $stub_paritioner));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_num_piplet', $num_piplet));

        $this->assertFalse($subject->send($pkg));

        // 测试3 pub task start失败
        // create a stub array of BigpipePublishTask
        $stub_tasks = array();
        $stub_tasks[$test_piplet] = $this
            ->getMockBuilder('BigpipePublishTask')
            ->disableOriginalConstructor()
            ->getMock();
        $stub_tasks[$test_piplet]->expects($this->any())
            ->method('start')
            ->will($this->onConsecutiveCalls(false, true, true));
        $pub_ret = new BigpipePubResult;
        $stub_tasks[$test_piplet]->expects($this->any())
            ->method('send')
            ->will($this->onConsecutiveCalls(false, $pub_ret));
        $stub_tasks[$test_piplet]->expects($this->any())
            ->method('stop')
            ->will($this->returnValue(null));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_pub_list', $stub_tasks));

        $this->assertFalse($subject->send($pkg));

        // 测试4 发布失败
        $this->assertFalse($subject->send($pkg));
        // 测试5 成功发布
        print_r($pub_ret);
        $this->assertEquals($pub_ret, $subject->send($pkg));;
    }

    public function testUninit()
    {
        // 测试1 uninit未初始化的publisher
        $subject = new BigpipePublisher;
        $subject->uninit();

        // 测试2 uninit成功
        $stub_meta = $this
            ->getMockBuilder('MetaAgentAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        // configure the stubs
        $stub_meta->expects($this->once())
            ->method('uninit')
            ->will($this->returnValue(null));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_adapter', $stub_meta));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        $subject->uninit();
        $this->assertTrue(true);
    }
} // end of TestBigpipePublisher

//$suite = new PHPUnit_Framework_TestSuite("TestBigpipePublisher");
//$test_runner_args = array();
////$test_runner_args['coverageClover'] = './coverage.xml';
////$test_runner_args['reportDirectory'] = './coverage';
////$test_runner_args['reportCharset'] = 'UTF8';
//$test_runner_args['configuration'] = 'bigpipe-unit-test.xml';
//PHPUnit_TextUI_TestRunner::run($suite, $test_runner_args);
/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
