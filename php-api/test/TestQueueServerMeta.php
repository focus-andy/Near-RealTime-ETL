<?php
/**==========================================================================
 * 
 * TestQueueServerMeta.php - INF / DS / BIGPIPE
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
require_once(dirname(__FILE__).'/../frame/QueueServerMeta.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

class TestQueueServerMeta extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $meta_conf = new BigpipeMetaConf;
        $meta_conf->meta_host = '10.218.32.11:2181,10.218.32.20:2181,10.218.32.21:2181,10.218.32.22:2181,10.218.32.23:2181';
        $meta_conf->root_path = '/bigpipe_pvt_cluster3';
        $this->meta_params = $meta_conf->to_array();
        $this->meta_params['zk_recv_timeout'] = 10000;

        // 生成 zk_connection的mock实例
        $this->stub_zk = $this
            ->getMockBuilder('Zookeeper')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testInit()
    {
        $subject = new QueueServerMeta;

        // 测试1 empty token
        $name = null;
        $token = 'token';
        $this->assertFalse($subject->init($name, $token, $this->meta_params));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 测试2 meta_params错误
        $root_path = $this->meta_params['root_path'];
        unset($this->meta_params['root_path']);
        $name = 'queue'; 
        $this->assertFalse($subject->init($name, $token, $this->meta_params));
        $this->meta_params['root_path'] = $root_path;

        // 测试3 成功init
        $this->assertTrue($subject->init($name, $token, $this->meta_params));
    }

    public function testUninit()
    {
        $subject = new QueueServerMeta;
        // 测试1：uninit未被初始化的类
        $subject->uninit();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        // 测试2：uninit对象
        $subject->uninit();
    }

    public function testUpdate()
    {
        $subject = new QueueServerMeta;
        // 测试1 update uninited的对象
        $this->assertFalse($subject->update());
        $name = 'queue';
        $token = 'token';
        $this->assertTrue($subject->init($name, $token, $this->meta_params));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(false, true));
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(
                false, 
                true, true,
                true, true
            ));
        $this->stub_zk->expects($this->any())
            ->method('get')
            ->will($this->onConsecutiveCalls(
                'error ip', 
                '127.0.0.0:9527'));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));

        // 测试2 connect失败
        $this->assertFalse($subject->update());

        // 测试3 update queue server info
        // 测试3.1 reg_path不存在
        $this->assertFalse($subject->update());
        // 测试3.2 reg_path格式错误
        $this->assertFalse($subject->update());
        // 测试3.3 成功
        $this->assertTrue($subject->update());

        // 测试3.4 完成其他分支
        $this->assertEquals($name, $subject->queue_name());
        $this->assertEquals($token, $subject->token());
        $address = array(
            'socket_address' => '127.0.0.0',
            'socket_port'    => 9527,
        );
        $this->assertEquals($address, $subject->queue_address());
    }
} // end of TestQueueServerMeta

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
