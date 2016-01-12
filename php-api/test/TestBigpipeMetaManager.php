<?php
/**==========================================================================
 * 
 * TestBigpipeMetaManager.php - INF / DS / BIGPIPE
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
require_once(dirname(__FILE__).'/../frame/BigpipeMetaManager.class.php');
require_once(dirname(__FILE__).'/../frame/ZooKeeperConnection.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

class TestBigpipeMetaManager extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $meta_conf = new BigpipeMetaConf;
        $meta_conf->meta_host = '10.218.32.11:2181,10.218.32.20:2181,10.218.32.21:2181,10.218.32.22:2181,10.218.32.23:2181';
        $meta_conf->root_path = '/bigpipe_pvt_cluster3';
        $this->meta_params = $meta_conf->to_array();

        // 生成 zk_connection的mock实例
        $this->stub_zk = $this
            ->getMockBuilder('ZooKeeperConnection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testInit()
    {
        $subject = new BigpipeMetaManager;
        $subject->unittest = true;

        // 测试1 init已被init的对象
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->init($this->meta_params));

        // 测试2 检查meta配置
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
        $rpath = $this->meta_params['root_path'];
        unset($this->meta_params['root_path']);
        $this->assertFalse($subject->init($this->meta_params));
        $this->meta_params['root_path'] = $rpath;

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('init')
            ->will($this->onConsecutiveCalls(false, true, true));
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $subject->stub_zk = $this->stub_zk;

        //  测试3 zk init失败
        $this->assertFalse($subject->init($this->meta_params));

        //  测试4 zk exists失败
        $this->assertFalse($subject->init($this->meta_params));

        // 测试5 init成功
        $this->assertTrue($subject->init($this->meta_params));
    }

    public function testUninit()
    {
        $subject = new BigpipeMetaManager;
        // 测试 false 分支
        $subject->uninit();
        $this->assertTrue(true);
    }

    public function testEntryExists()
    {
        $subject = new BigpipeMetaManager;
        // 测试1：未init时调用接口
        $path = '/path';
        $this->assertFalse($subject->entry_exists($path));

        // 测试2：返回exist结果
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));
        
        $this->assertTrue($subject->entry_exists($path));
    }

    public function testCreateEntry()
    {
        $subject = new BigpipeMetaManager;
        // 测试1：未init时调用接口
        $path = '/path';
        $this->assertFalse($subject->create_entry($path));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(true, false, false));
        $this->stub_zk->expects($this->any())
            ->method('make_path')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));
        
        // 测试2：在exist的path上创建entry
        $this->assertFalse($subject->create_entry($path));
        // 测试3：make_path失败
        $this->assertFalse($subject->create_entry($path));
        // 测试4：成功
        $this->assertTrue($subject->create_entry($path));
    }

    public function testSetEntry()
    {
        $subject = new BigpipeMetaManager;
        // 测试1：未init时调用接口
        $path = '/path';
        $val = 'val';
        $this->assertFalse($subject->set_entry($path, $val));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true, true, true));
        $this->stub_zk->expects($this->any())
            ->method('set')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));
        
        // 测试2：set的etnry不存在
        $this->assertFalse($subject->set_entry($path, $val));
        // 测试3：value不能被serialzie
        $this->assertFalse($subject->set_entry($path, $val));
        // 测试4：set操作失败
        $val = array('path' => 'path');
        $this->assertFalse($subject->set_entry($path, $val));
        // 测试5：成功set
        $this->assertTrue($subject->set_entry($path, $val));
    }

    public function testGetEntry()
    {
        $subject = new BigpipeMetaManager;
        // 测试1：未init时调用接口
        $path = '/path';
        $stat = null;
        $this->assertFalse($subject->get_entry($path, $stat));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true, true, true));
        $val_arr = $this->_gen_zk_node();
        $this->stub_zk->expects($this->any())
            ->method('get')
            ->will($this->onConsecutiveCalls(
                false,
                $val_arr['bad'],
                $val_arr['good'] 
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));

        // 测试2：entry不存在
        $this->assertFalse($subject->get_entry($path, $stat));
        // 测试3：node value不存在
        $this->assertFalse($subject->get_entry($path, $val));
        // 测试4：node value不能被deserialize
        $this->assertFalse($subject->get_entry($path, $val));
        // 测试5：成功get
        $stat = array('version' => 9527);
        $ret = $subject->get_entry($path, $val);
        $this->assertTrue(false != $ret);
    }

    public function testUpdateEntry()
    {
        $subject = new BigpipeMetaManager;
        // 测试1：未init时调用接口
        $path = '/path';
        $new_val = 'new unit test';
        $version = -1;
        $this->assertFalse($subject->update_entry($path, $new_val, $version));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        // 测试2：version小于0
        $this->assertFalse($subject->update_entry($path, $new_val, $version));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true, true, true));
        $val_arr = $this->_gen_zk_node();
        $this->stub_zk->expects($this->any())
            ->method('update')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));

        // 测试3：entry不存在
        $version = 9527;
        $this->assertFalse($subject->update_entry($path, $new_val, $version));
        // 测试4：value serialize失败
        $this->assertFalse($subject->update_entry($path, $new_val, $version));
        // 测试5：update失败
        $new_val = array('val' => 'new unit test');
        $this->assertFalse($subject->update_entry($path, $new_val, $version));
        // 测试6：成功get
        $this->assertTrue($subject->update_entry($path, $new_val, $version));
    }

    public function testDeleteEntry()
    {
        $subject = new BigpipeMetaManager;
        // 测试1：未init时调用接口
        $path = '/path';
        $this->assertFalse($subject->delete_entry($path));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义zk行为
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $this->stub_zk->expects($this->once())
            ->method('remove_path')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));
        
        // 测试2：set的etnry不存在
        $this->assertFalse($subject->delete_entry($path));
        // 测试5：成功set
        $this->assertTrue($subject->delete_entry($path));
    }

    private function _gen_zk_node()
    {
        $node_frame = new MetaNode;
        $val = array('val' => 'unit test');
        $node_value = $node_frame->serialize($val);

        $bad_node = pack("C2S1L1", 0, 64, 0, 0);

        return array(
            'good' => $node_value,
            'bad'  => $bad_node,
        );
    }
} // end of TestBigpipeMetaManager

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
