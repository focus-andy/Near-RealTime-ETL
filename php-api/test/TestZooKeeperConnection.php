<?php
/**==========================================================================
 * 
 * TestZooKeeperConnection.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2013-01-04 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../frame/ZooKeeperConnection.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

class TestZooKeeperConnection extends PHPUnit_Framework_TestCase
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
        $subject = new ZooKeeperConnection;
        $subject->unittest = true;

        // 测试1 init已被init的对象
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->init($this->meta_params));

        // 测试2 检查meta配置
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
        $host = $this->meta_params['meta_host'];
        unset($this->meta_params['meta_host']);
        $this->assertFalse($subject->init($this->meta_params));
        $this->meta_params['meta_host'] = $host;

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(false, true));
        $subject->stub_zk = $this->stub_zk;

        // 测试3 zk connect失败
        $this->assertFalse($subject->init($this->meta_params));

        // 测试4 init成功
        $this->assertTrue($subject->init($this->meta_params));
    }

    public function testReconnect()
    {
        $subject = new ZooKeeperConnection;
        // 测试1：未init时调用接口
        $this->assertFalse($subject->reconnect());

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));
        
        // 测试2：reconnect失败
        $this->assertFalse($subject->reconnect());
        // 测试3：reconnect成功
        $this->assertTrue($subject->reconnect());
    }

    public function testUninit()
    {
        $subject = new ZooKeeperConnection;
        // 测试1：未init时调用接口
        $subject->uninit();

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $subject->uninit();
    }

    public function testHost()
    {
        $subject = new ZooKeeperConnection;
        // 测试1：未init时调用接口
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_host', 'local'));
        $this->assertEquals('local', $subject->host());
    }

    public function testExists()
    {
        $subject = new ZooKeeperConnection;
        $path = '/path';
        // 测试1：未init时调用接口
        $this->assertFalse($subject->exists($path));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));
        
        // 测试2: 成功
        $this->assertTrue($subject->exists($path));
    }

    public function testSet()
    {
        $stub = $this->getMock(
            'ZooKeeperConnection',
            array('exists', 'make_path', 'make_node')
        );

        // mock ZooKeeperConnection行为
        $stub->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $stub->expects($this->once())
            ->method('make_path')
            ->will($this->returnValue(true));
        $stub->expects($this->once())
            ->method('make_node')
            ->will($this->returnValue(true));
        
        $path = 'path';
        $val = 'val';

        // 测试1：未init时调用接口
        $this->assertFalse($stub->set($path, $val));

        // 定义zk行为
        $this->stub_zk->expects($this->once())
            ->method('set')
            ->will($this->returnValue(false));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_zk', $this->stub_zk));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_inited', true));

        // 测试2 exists == false时，测试make node
        $this->assertTrue($stub->set($path, $val));
        // 测试3 exists == true, 测试set失败
        $this->assertFalse($stub->set($path, $val));
    }

    public function testUpdate()
    {
        $stub = $this->getMock(
            'ZooKeeperConnection',
            array('exists', 'make_path', 'make_node')
        );

        // mock ZooKeeperConnection行为
        $stub->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $path = 'path';
        $val = 'val';
        $ver = -1;

        // 测试1：未init时调用接口
        $this->assertFalse($stub->update($path, $val, $ver));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_inited', true));

        // 测试2：version错误
        $this->assertFalse($stub->update($path, $val, $ver));
        
        // 定义zk行为
        $this->stub_zk->expects($this->once())
            ->method('set')
            ->will($this->returnValue(null));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_zk', $this->stub_zk));

        // 测试3 exists == false
        $ver = 1;
        $this->assertFalse($stub->update($path, $val, $ver));
        // 测试3 exists == true, 测试set失败
        $this->assertFalse($stub->update($path, $val, $ver));
    }

    public function testGet()
    {
        $subject = new ZooKeeperConnection;
        $path = '/path';
        $stat = null;
        // 测试1：未init时调用接口
        $this->assertFalse($subject->get($path, $stat));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $this->stub_zk->expects($this->any())
            ->method('get')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));

        // 测试2：entry不存在
        $this->assertFalse($subject->get($path, $stat)); 
        // 测试3: 成功
        $this->assertTrue($subject->get($path, $stat));
    }

    public function testRemovePath()
    {
        $stub = $this->getMock(
            'ZooKeeperConnection',
            array('exists', 'get_children')
        );

        // mock ZooKeeperConnection行为
        $stub->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(
                false, 
                true,true,true,true,true,true));
        $stub->expects($this->any())
            ->method('get_children')
            ->will($this->onConsecutiveCalls(
                array('/sub'),
                array(), array()
            ));
        $path = '/path/sub';

        // 测试1：未init时调用接口
        $this->assertFalse($stub->remove_path($path));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_inited', true));

        // 测试2：remove的path本来就不存在
        $this->assertTrue($stub->remove_path($path));
        
        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('delete')
            ->will($this->onConsecutiveCalls(
                null,true,true
            ));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_zk', $this->stub_zk));

        // 测试3 exists == false
        $this->assertFalse($stub->remove_path($path));
        // 测试4 exists == true, 测试set失败
        $this->assertTrue($stub->remove_path($path));
    }

    public function testMakePath()
    {
        $stub = $this->getMock(
            'ZooKeeperConnection',
            array('exists', 'make_node')
        );

        // mock ZooKeeperConnection行为
        $stub->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(
                false, 
                false,
                true));
        $stub->expects($this->once())
            ->method('make_node')
            ->will($this->returnValue(false));
        $path = '/path/sub';
        $mk_parent = false;

        // 测试1：未init时调用接口
        $this->assertFalse($stub->make_path($path, $mk_parent));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_inited', true));

        // 测试2：不允许递归创建目录
        $this->assertFalse($stub->make_path($path, $mk_parent));
        
        // 测试3 make node失败
        $mk_parent = true;
        $this->assertFalse($stub->make_path($path, $mk_parent));
        // 测试4 make path成功
        $this->assertTrue($stub->make_path($path, $mk_parent));
    }

    public function testMakeNode()
    {
        $subject = new ZooKeeperConnection;
        $path = '/path';
        $val = 'val';
        
        // 测试1：未init时调用接口
        $this->assertFalse($subject->make_node($path, $val));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('create')
            ->will($this->onConsecutiveCalls(null, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));

        // 测试2：create失败
        $this->assertFalse($subject->make_node($path, $val)); 
        // 测试3: create成功
        $this->assertTrue($subject->make_node($path, $val));
    }

    public function testGetChildren()
    {
        $subject = new ZooKeeperConnection;
        $path = '/path/sub';
        
        // 测试1：未init时调用接口
        $this->assertFalse($subject->get_children($path));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->once())
            ->method('getChildren')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));

        // 测试2：create失败
        $this->assertTrue($subject->get_children($path));
    }
} // end of TestZooKeeperConnection

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
