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

        // ���� zk_connection��mockʵ��
        $this->stub_zk = $this
            ->getMockBuilder('ZooKeeperConnection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testInit()
    {
        $subject = new BigpipeMetaManager;
        $subject->unittest = true;

        // ����1 init�ѱ�init�Ķ���
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->init($this->meta_params));

        // ����2 ���meta����
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
        $rpath = $this->meta_params['root_path'];
        unset($this->meta_params['root_path']);
        $this->assertFalse($subject->init($this->meta_params));
        $this->meta_params['root_path'] = $rpath;

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('init')
            ->will($this->onConsecutiveCalls(false, true, true));
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $subject->stub_zk = $this->stub_zk;

        //  ����3 zk initʧ��
        $this->assertFalse($subject->init($this->meta_params));

        //  ����4 zk existsʧ��
        $this->assertFalse($subject->init($this->meta_params));

        // ����5 init�ɹ�
        $this->assertTrue($subject->init($this->meta_params));
    }

    public function testUninit()
    {
        $subject = new BigpipeMetaManager;
        // ���� false ��֧
        $subject->uninit();
        $this->assertTrue(true);
    }

    public function testEntryExists()
    {
        $subject = new BigpipeMetaManager;
        // ����1��δinitʱ���ýӿ�
        $path = '/path';
        $this->assertFalse($subject->entry_exists($path));

        // ����2������exist���
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
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
        // ����1��δinitʱ���ýӿ�
        $path = '/path';
        $this->assertFalse($subject->create_entry($path));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(true, false, false));
        $this->stub_zk->expects($this->any())
            ->method('make_path')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));
        
        // ����2����exist��path�ϴ���entry
        $this->assertFalse($subject->create_entry($path));
        // ����3��make_pathʧ��
        $this->assertFalse($subject->create_entry($path));
        // ����4���ɹ�
        $this->assertTrue($subject->create_entry($path));
    }

    public function testSetEntry()
    {
        $subject = new BigpipeMetaManager;
        // ����1��δinitʱ���ýӿ�
        $path = '/path';
        $val = 'val';
        $this->assertFalse($subject->set_entry($path, $val));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true, true, true));
        $this->stub_zk->expects($this->any())
            ->method('set')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));
        
        // ����2��set��etnry������
        $this->assertFalse($subject->set_entry($path, $val));
        // ����3��value���ܱ�serialzie
        $this->assertFalse($subject->set_entry($path, $val));
        // ����4��set����ʧ��
        $val = array('path' => 'path');
        $this->assertFalse($subject->set_entry($path, $val));
        // ����5���ɹ�set
        $this->assertTrue($subject->set_entry($path, $val));
    }

    public function testGetEntry()
    {
        $subject = new BigpipeMetaManager;
        // ����1��δinitʱ���ýӿ�
        $path = '/path';
        $stat = null;
        $this->assertFalse($subject->get_entry($path, $stat));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
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

        // ����2��entry������
        $this->assertFalse($subject->get_entry($path, $stat));
        // ����3��node value������
        $this->assertFalse($subject->get_entry($path, $val));
        // ����4��node value���ܱ�deserialize
        $this->assertFalse($subject->get_entry($path, $val));
        // ����5���ɹ�get
        $stat = array('version' => 9527);
        $ret = $subject->get_entry($path, $val);
        $this->assertTrue(false != $ret);
    }

    public function testUpdateEntry()
    {
        $subject = new BigpipeMetaManager;
        // ����1��δinitʱ���ýӿ�
        $path = '/path';
        $new_val = 'new unit test';
        $version = -1;
        $this->assertFalse($subject->update_entry($path, $new_val, $version));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        // ����2��versionС��0
        $this->assertFalse($subject->update_entry($path, $new_val, $version));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true, true, true));
        $val_arr = $this->_gen_zk_node();
        $this->stub_zk->expects($this->any())
            ->method('update')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));

        // ����3��entry������
        $version = 9527;
        $this->assertFalse($subject->update_entry($path, $new_val, $version));
        // ����4��value serializeʧ��
        $this->assertFalse($subject->update_entry($path, $new_val, $version));
        // ����5��updateʧ��
        $new_val = array('val' => 'new unit test');
        $this->assertFalse($subject->update_entry($path, $new_val, $version));
        // ����6���ɹ�get
        $this->assertTrue($subject->update_entry($path, $new_val, $version));
    }

    public function testDeleteEntry()
    {
        $subject = new BigpipeMetaManager;
        // ����1��δinitʱ���ýӿ�
        $path = '/path';
        $this->assertFalse($subject->delete_entry($path));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����zk��Ϊ
        $this->stub_zk->expects($this->any())
            ->method('exists')
            ->will($this->onConsecutiveCalls(false, true));
        $this->stub_zk->expects($this->once())
            ->method('remove_path')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_zk_connection', $this->stub_zk));
        
        // ����2��set��etnry������
        $this->assertFalse($subject->delete_entry($path));
        // ����5���ɹ�set
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
