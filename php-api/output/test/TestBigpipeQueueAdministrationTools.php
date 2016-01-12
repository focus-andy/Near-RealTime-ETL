<?php
/**==========================================================================
 * 
 * TestBigpipeQueueAdministrationTools.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-30 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../BigpipeQueueAdministrationTools.class.php');
class TestBigpipeQueueAdministrationTools extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $conf_dir = './conf';
        $conf_file = 'test_queue_util.conf';
        $conf_content = config_load($conf_dir, $conf_file);
        $this->meta_conf = $conf_content['meta'];
        $this->queue_conf = $conf_content['UTIL'];

        $this->stub_meta = $this
            ->getMockBuilder('BigpipeMetaManager')
            ->disableOriginalConstructor()
            ->getMock();

        BigpipeQueueAdministrationTools::$unittest = true;
    }

    /**
     * ²âÊÔ create_queue½Ó¿Ú
     */
    public function testCreateQueue()
    {
        // ²âÊÔ1 queue param´íÎó
        $states = null;
        $this->assertFalse(BigpipeQueueAdministrationTools::create_queue($this->meta_conf, 369, $states));

        // ²âÊÔ2 _init_meta´íÎó
        BigpipeQueueAdministrationTools::$stub_meta = false;
        $this->assertFalse(BigpipeQueueAdministrationTools::create_queue($this->meta_conf, $this->queue_conf, $states));

        // ¶¨Òåstub_metaÐÐÎª
        $this->stub_meta->expects($this->any())
            ->method('create_entry')
            ->will($this->onConsecutiveCalls(false, true, true, true));
        $this->stub_meta->expects($this->any())
            ->method('set_entry')
            ->will($this->onConsecutiveCalls(false, true, true));
        $this->stub_meta->expects($this->any())
            ->method('get_entry')
            ->will($this->onConsecutiveCalls(false, 'ok'));

        // ²âÊÔ3 _normalize queu param´íÎó
        BigpipeQueueAdministrationTools::$stub_meta = $this->stub_meta;
        $pipelet_arr = array_slice($this->queue_conf['pipelet'], 0);
        $this->queue_conf['pipelet'] = null;
        print_r($this->queue_conf);
        $this->assertFalse(BigpipeQueueAdministrationTools::create_queue($this->meta_conf, $this->queue_conf, $states));
        $this->queue_conf['pipelet'] = array( 0 => 1); 
        $this->assertFalse(BigpipeQueueAdministrationTools::create_queue($this->meta_conf, $this->queue_conf, $states));
        $this->queue_conf['pipelet'] = array_slice($pipelet_arr, 0);

        // ²âÊÔ4 create entry´íÎó
        $this->assertFalse(BigpipeQueueAdministrationTools::create_queue($this->meta_conf, $this->queue_conf, $states));

        // ²âÊÔ5 set entry´íÎó
        $this->assertFalse(BigpipeQueueAdministrationTools::create_queue($this->meta_conf, $this->queue_conf, $states));

        // ²âÊÔ6 get entry´íÎó
        $this->assertFalse(BigpipeQueueAdministrationTools::create_queue($this->meta_conf, $this->queue_conf, $states));

        // ²âÊÔ7 ³É¹¦Á÷³Ì
        $this->assertTrue(BigpipeQueueAdministrationTools::create_queue($this->meta_conf, $this->queue_conf, $states));
    }

    /**
     * ²âÊÔ start_queue½Ó¿Ú
     */
    public function testStartQueue()
    {
        // ²âÊÔ1 queue param´íÎó
        $states = null;
        $name = 'queue';
        $token = 'token';
        $this->assertFalse(BigpipeQueueAdministrationTools::start_queue($name, null, $this->meta_conf, $states));

        // ²âÊÔ2 _init_meta´íÎó
        BigpipeQueueAdministrationTools::$stub_meta = false;
        $this->assertFalse(BigpipeQueueAdministrationTools::start_queue($name, $token, $this->meta_conf, $states));

        // ¶¨Òåstub_metaÐÐÎª
        $this->stub_meta->expects($this->any())
            ->method('update_entry')
            ->will($this->onConsecutiveCalls(false, true));
        $queue_started = array(
            'token'  => 'token',
            'status' => BigpipeQueueStatus::STARTED,
        );
        $queue_deleted = array(
            'token'  => 'token',
            'status' => BigpipeQueueStatus::DELETED,
        );
        $queue_normal = array(
            'token'  => 'token',
            'status' => BigpipeQueueStatus::CREATED,
        );
        $queue_unauthor = array(
            'token'  => 'mistoken',
            'status' => BigpipeQueueStatus::CREATED,
        );
        $this->stub_meta->expects($this->any())
            ->method('get_entry')
            ->will($this->onConsecutiveCalls(
                false,
                $queue_unauthor,
                $queue_deleted,
                $queue_started,
                $queue_normal,
                $queue_normal)); 

        // ²âÊÔ3 get entry´íÎó
        BigpipeQueueAdministrationTools::$stub_meta = $this->stub_meta;
        $this->assertFalse(BigpipeQueueAdministrationTools::start_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ4 token´íÎó
        $this->assertFalse(BigpipeQueueAdministrationTools::start_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ5 queueÊÇÒÑÉ¾³ý×´Ì¬
        $this->assertFalse(BigpipeQueueAdministrationTools::start_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ6 queueÒÑ¾­±»Æô¶¯
        $this->assertTrue(BigpipeQueueAdministrationTools::start_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ7 update entryÊ§°Ü
        $this->assertFalse(BigpipeQueueAdministrationTools::start_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ8 ³É¹¦Á÷³Ì
        $this->assertTrue(BigpipeQueueAdministrationTools::start_queue($name, $token, $this->meta_conf, $states));
    }

    /**
     * ²âÊÔ stop_queue½Ó¿Ú
     */
    public function testStopQueue()
    {
        // ²âÊÔ1 queue param´íÎó
        $states = null;
        $name = 'queue';
        $token = 'token';
        $this->assertFalse(BigpipeQueueAdministrationTools::stop_queue($name, null, $this->meta_conf, $states));

        // ²âÊÔ2 _init_meta´íÎó
        BigpipeQueueAdministrationTools::$stub_meta = false;
        $this->assertFalse(BigpipeQueueAdministrationTools::stop_queue($name, $token, $this->meta_conf, $states));

        // ¶¨Òåstub_metaÐÐÎª
        $this->stub_meta->expects($this->any())
            ->method('update_entry')
            ->will($this->onConsecutiveCalls(false, true));
        $queue_deleted = array(
            'token'  => 'token',
            'status' => BigpipeQueueStatus::DELETED,
        );
        $queue_normal = array(
            'token'  => 'token',
            'status' => BigpipeQueueStatus::CREATED,
        );
        $queue_unauthor = array(
            'token'  => 'mistoken',
            'status' => BigpipeQueueStatus::CREATED,
        );
        $this->stub_meta->expects($this->any())
            ->method('get_entry')
            ->will($this->onConsecutiveCalls(
                false,
                $queue_unauthor,
                $queue_deleted,
                $queue_normal,
                $queue_normal)); 

        // ²âÊÔ3 get entry´íÎó
        BigpipeQueueAdministrationTools::$stub_meta = $this->stub_meta;
        $this->assertFalse(BigpipeQueueAdministrationTools::stop_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ4 token´íÎó
        $this->assertFalse(BigpipeQueueAdministrationTools::stop_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ5 queueÊÇÒÑÉ¾³ý×´Ì¬
        $this->assertTrue(BigpipeQueueAdministrationTools::stop_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ6 update entryÊ§°Ü
        $this->assertFalse(BigpipeQueueAdministrationTools::stop_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ7 ³É¹¦Á÷³Ì
        $this->assertTrue(BigpipeQueueAdministrationTools::stop_queue($name, $token, $this->meta_conf, $states));
    }

    /**
     * ²âÊÔ delete_queue½Ó¿Ú
     */
    public function testDeleteQueue()
    {
        // ²âÊÔ1 queue param´íÎó
        $states = null;
        $name = 'queue';
        $token = 'token';
        $this->assertFalse(BigpipeQueueAdministrationTools::delete_queue($name, null, $this->meta_conf, $states));

        // ²âÊÔ2 _init_meta´íÎó
        BigpipeQueueAdministrationTools::$stub_meta = false;
        $this->assertFalse(BigpipeQueueAdministrationTools::delete_queue($name, $token, $this->meta_conf, $states));

        // ¶¨Òåstub_metaÐÐÎª
        $this->stub_meta->expects($this->any())
            ->method('delete_entry')
            ->will($this->onConsecutiveCalls(false, true));
        $queue_started = array(
            'token'  => 'token',
            'status' => BigpipeQueueStatus::STARTED,
        );
        $queue_deleted = array(
            'token'  => 'token',
            'status' => BigpipeQueueStatus::DELETED,
        );
        $queue_normal = array(
            'token'  => 'token',
            'status' => BigpipeQueueStatus::STOPPED,
        );
        $queue_unauthor = array(
            'token'  => 'mistoken',
            'status' => BigpipeQueueStatus::CREATED,
        );
        $this->stub_meta->expects($this->any())
            ->method('get_entry')
            ->will($this->onConsecutiveCalls(
                false,
                $queue_unauthor,
                $queue_deleted,
                $queue_normal,
                $queue_normal)); 

        // ²âÊÔ3 get entry´íÎó
        BigpipeQueueAdministrationTools::$stub_meta = $this->stub_meta;
        $this->assertFalse(BigpipeQueueAdministrationTools::delete_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ4 token´íÎó
        $this->assertFalse(BigpipeQueueAdministrationTools::delete_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ5 queueÊÇÒÑÉ¾³ý×´Ì¬
        $this->assertFalse(BigpipeQueueAdministrationTools::delete_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ6 delete entryÊ§°Ü
        $this->assertFalse(BigpipeQueueAdministrationTools::delete_queue($name, $token, $this->meta_conf, $states));

        // ²âÊÔ7 ³É¹¦Á÷³Ì
        $this->assertTrue(BigpipeQueueAdministrationTools::delete_queue($name, $token, $this->meta_conf, $states));
    }

    /**
     * ²âÊÔ Ë½ÓÐº¯Êý_init_meta
     */
    public function testOthers()
    {
        $subject = new BigpipeQueueAdministrationTools;
        $method = TestUtilities::get_private_method($subject, '_init_meta');
        $this->assertTrue(false != $method);

        // ²âÊÔinit metaÊ§°ÜÇé¿ö
        $this->assertFalse($method->invoke($subject, null));

        $method = TestUtilities::get_private_method($subject, '_check_assign_array');
        $this->assertTrue(false != $method);

        // ²âÊÔcheckÊ§°ÜÇé¿ö
        $testkey = 'test';
        $catched_count = 0;
        try
        {
            $dest = array();
            $src = array();
            $this->assertFalse($method->invoke($subject, $dest, $testkey, $src, $testkey));
        }
        catch (Exception $e)
        {
            $catched_count++;
        }
        $this->assertEquals(1, $catched_count);

        // ²âÊÔnormalize_queue_paramsÊ§°Ü
        $method = TestUtilities::get_private_method($subject, '_normalize_queue_params');
        $this->assertTrue(false != $method);
        $params = $this->queue_conf;
        unset($params['window_size']); // ÖÆÔì´íÎó
        $this->assertFalse($method->invoke($subject, $params));
    }
} // end of TestBigpipeQueueAdministrationTools


/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>

