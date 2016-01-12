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

        // ���� zk_connection��mockʵ��
        $this->stub_zk = $this
            ->getMockBuilder('Zookeeper')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testInit()
    {
        $subject = new ZooKeeperConnection;
        $subject->unittest = true;

        // ����1 init�ѱ�init�Ķ���
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->init($this->meta_params));

        // ����2 ���meta����
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
        $host = $this->meta_params['meta_host'];
        unset($this->meta_params['meta_host']);
        $this->assertFalse($subject->init($this->meta_params));
        $this->meta_params['meta_host'] = $host;

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(false, true));
        $subject->stub_zk = $this->stub_zk;

        // ����3 zk connectʧ��
        $this->assertFalse($subject->init($this->meta_params));

        // ����4 init�ɹ�
        $this->assertTrue($subject->init($this->meta_params));
    }

    public function testReconnect()
    {
        $subject = new ZooKeeperConnection;
        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($subject->reconnect());

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));
        
        // ����2��reconnectʧ��
        $this->assertFalse($subject->reconnect());
        // ����3��reconnect�ɹ�
        $this->assertTrue($subject->reconnect());
    }

    public function testUninit()
    {
        $subject = new ZooKeeperConnection;
        // ����1��δinitʱ���ýӿ�
        $subject->uninit();

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $subject->uninit();
    }

    public function testHost()
    {
        $subject = new ZooKeeperConnection;
        // ����1��δinitʱ���ýӿ�
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_host', 'local'));
        $this->assertEquals('local', $subject->host());
    }

    public function testExists()
    {
        $subject = new ZooKeeperConnection;
        $path = '/path';
        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($subject->exists($path));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));
        
        // ����2: �ɹ�
        $this->assertTrue($subject->exists($path));
    }

    public function testSet()
    {
        $stub = $this->getMock(
            'ZooKeeperConnection',
            array('exists', 'make_path', 'make_node')
        );

        // mock ZooKeeperConnection��Ϊ
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

        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($stub->set($path, $val));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->once())
            ->method('set')
            ->will($this->returnValue(false));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_zk', $this->stub_zk));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_inited', true));

        // ����2 exists == falseʱ������make node
        $this->assertTrue($stub->set($path, $val));
        // ����3 exists == true, ����setʧ��
        $this->assertFalse($stub->set($path, $val));
    }

    public function testUpdate()
    {
        $stub = $this->getMock(
            'ZooKeeperConnection',
            array('exists', 'make_path', 'make_node')
        );

        // mock ZooKeeperConnection��Ϊ
        $stub->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $path = 'path';
        $val = 'val';
        $ver = -1;

        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($stub->update($path, $val, $ver));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_inited', true));

        // ����2��version����
        $this->assertFalse($stub->update($path, $val, $ver));
        
        // ����zk��Ϊ
        $this->stub_zk->expects($this->once())
            ->method('set')
            ->will($this->returnValue(null));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_zk', $this->stub_zk));

        // ����3 exists == false
        $ver = 1;
        $this->assertFalse($stub->update($path, $val, $ver));
        // ����3 exists == true, ����setʧ��
        $this->assertFalse($stub->update($path, $val, $ver));
    }

    public function testGet()
    {
        $subject = new ZooKeeperConnection;
        $path = '/path';
        $stat = null;
        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($subject->get($path, $stat));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $this->stub_zk->expects($this->any())
            ->method('get')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));

        // ����2��entry������
        $this->assertFalse($subject->get($path, $stat)); 
        // ����3: �ɹ�
        $this->assertTrue($subject->get($path, $stat));
    }

    public function testRemovePath()
    {
        $stub = $this->getMock(
            'ZooKeeperConnection',
            array('exists', 'get_children')
        );

        // mock ZooKeeperConnection��Ϊ
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

        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($stub->remove_path($path));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_inited', true));

        // ����2��remove��path�����Ͳ�����
        $this->assertTrue($stub->remove_path($path));
        
        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('delete')
            ->will($this->onConsecutiveCalls(
                null,true,true
            ));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_zk', $this->stub_zk));

        // ����3 exists == false
        $this->assertFalse($stub->remove_path($path));
        // ����4 exists == true, ����setʧ��
        $this->assertTrue($stub->remove_path($path));
    }

    public function testMakePath()
    {
        $stub = $this->getMock(
            'ZooKeeperConnection',
            array('exists', 'make_node')
        );

        // mock ZooKeeperConnection��Ϊ
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

        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($stub->make_path($path, $mk_parent));
        $this->assertTrue(TestUtilities::set_parent_private_var(
            $stub, '_inited', true));

        // ����2��������ݹ鴴��Ŀ¼
        $this->assertFalse($stub->make_path($path, $mk_parent));
        
        // ����3 make nodeʧ��
        $mk_parent = true;
        $this->assertFalse($stub->make_path($path, $mk_parent));
        // ����4 make path�ɹ�
        $this->assertTrue($stub->make_path($path, $mk_parent));
    }

    public function testMakeNode()
    {
        $subject = new ZooKeeperConnection;
        $path = '/path';
        $val = 'val';
        
        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($subject->make_node($path, $val));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('create')
            ->will($this->onConsecutiveCalls(null, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));

        // ����2��createʧ��
        $this->assertFalse($subject->make_node($path, $val)); 
        // ����3: create�ɹ�
        $this->assertTrue($subject->make_node($path, $val));
    }

    public function testGetChildren()
    {
        $subject = new ZooKeeperConnection;
        $path = '/path/sub';
        
        // ����1��δinitʱ���ýӿ�
        $this->assertFalse($subject->get_children($path));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->once())
            ->method('getChildren')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk', $this->stub_zk));

        // ����2��createʧ��
        $this->assertTrue($subject->get_children($path));
    }
} // end of TestZooKeeperConnection

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
