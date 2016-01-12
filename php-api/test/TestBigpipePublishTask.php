<?php
/**==========================================================================
 * 
 * ./test/TestBigpipePublishTask.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-26 by YANG ZHENYU (yangzhenyu@baidu.com)
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

class TestBigpipePublishTask extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->pipe_name = 'test';
        $this->pipelet_id = 2;
        $this->session_id = BigpipeUtilities::get_uid();
        $this->conf = new BigpipeConf;
        $this->conf->checksum_level = BigpipeChecksumLevel::CHECK_FRAME;
        $this->conf->max_failover_cnt = 2; // 设置failover count 

        // 生成MetaAgentAdapter的mock实例
        $this->stub_meta = $this
            ->getMockBuilder('MetaAgentAdapter')
            ->disableOriginalConstructor()
            ->getMock();
        // 生成 StompAdapter的mock实例
        $this->stub_stomp = $this
            ->getMockBuilder('BigpipeStompAdapter')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testStart()
    {
        // 测试1 meta adapter不存在
        // 这种情况只有在meta在外部被回收时才会发生，
        // 本程序内可以任务不会发生
        $subject = new BigpipePublishTask(
            $this->pipe_name,
            $this->pipelet_id,
            $this->session_id,
            $this->conf,
            $this->sub_meta);
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_adapter', null));
        $this->assertFalse($subject->start());

        // 测试2 task已经开始
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_started', true));
        $this->assertTrue($subject->start());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_started', false));

        // 设置meta行为
        $broker = $this->_gen_pub_broker();
        $this->assertTrue(false !== $broker);
        $this->stub_meta->expects($this->any())
            ->method('get_pub_broker')
            ->will($this->onConsecutiveCalls(false, $broker, $broker));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_adapter', $this->stub_meta));

        // 设置stomp adapter行为
        $this->stub_stomp->expects($this->any())
            ->method('set_destination')
            ->will($this->returnValue(true));
        $this->stub_stomp->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));
        // 测试3 获取broker失败
        $this->assertFalse($subject->start());
        // 测试4 connect stomp失败
        $this->assertFalse($subject->start());
        // 测试5 connect stomp成功
        $this->assertTrue($subject->start());
    }

    public function testIsStarted()
    {
        // 增加代码覆盖率
        $subject = new BigpipePublishTask(
            $this->pipe_name,
            $this->pipelet_id,
            $this->session_id,
            $this->conf,
            $this->sub_meta);
        $this->assertFalse($subject->is_started());
    }

    public function testStop()
    {
        // 定义stomp行为
        $this->stub_stomp->expects($this->atLeastOnce())
            ->method('close')
            ->will($this->returnValue(true));
        $subject = new BigpipePublishTask(
            $this->pipe_name,
            $this->pipelet_id,
            $this->session_id,
            $this->conf,
            $this->sub_meta);
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));

        // 测试1 stop还未start的task
        $subject->stop();
        
        // 测试2 stop流程
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_started', true));
        $subject->stop();
        $this->assertTrue(true);
    }

    public function testSend()
    {
        $subject = new BigpipePublishTask(
            $this->pipe_name,
            $this->pipelet_id,
            $this->session_id,
            $this->conf,
            $this->sub_meta);
        $subject->unittest = true;
        $pkg = new BigpipeMessagePackage;

        // 测试1: send on unstarted object
        $this->assertFalse($subject->send($pkg));

        // set object to be started
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_started', true));
        
        // 测试2 生成message package失败
        $this->assertFalse($subject->send($pkg));

        // set last send ok
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_last_send_ok', true));
        $this->assertTrue($pkg->push('Testing Publisher Task'));

        // 定义meta行为
        $broker = $this->_gen_pub_broker();
        $this->assertTrue(false !== $broker);
        $this->stub_meta->expects($this->any())
            ->method('get_pub_broker')
            ->will($this->returnValue($broker));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_adapter', $this->stub_meta));

        // 设置stomp行为
        $this->stub_stomp->expects($this->any())
            ->method('send')
            ->will($this->onConsecutiveCalls(false, false, false, true, true, true, true));
        $this->stub_stomp->expects($this->any())
            ->method('set_destination')
            ->will($this->returnValue(true));
        $this->stub_stomp->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(true));
        // mock ack result
        $res_arr = $this->_gen_ack_response($subject);
        $this->stub_stomp->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null, 
                $res_arr['bad_session'], 
                $res_arr['bad_receipt'],
                $res_arr['error_body'],
                $res_arr['good']));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));

        // 测试3 send失败，failover失败并强制退出
        $this->assertFalse($subject->send($pkg));

        // 测试4 ack失败状态（ack三个失败分支）
        $this->assertFalse($subject->send($pkg));

        // 测试5 error body 和 send成功
        $this->assertFalse(false === $subject->send($pkg));
    }

    /**
     * 从文件中读取broker提供给测试用例
     * @return pub_broker on success or false on failure
     */
    private function _gen_pub_broker()
    {
        $pathname = './pub_broker.json';
        if (false === file_exists($pathname))
        {
            // missing example file
            return false;
        }

        $content = file_get_contents($pathname);
        $broker = json_decode($content, true);
        if (null === $broker)
        {
            return false;
        }
        return $broker;
    }

    /**
     * 生成ack响应供测试程序使用
     * @return ack响应集供test case选择
     */
    private function _gen_ack_response($subject)
    {
        $ack = new BStompAckFrame;
        $ack->status = BStompIdAckType::TOPIC_ID;
        $ack->ack_type = BStompFrameType::ACK;
        $ack->session_message_id = TestUtilities::get_private_var(
            $subject, '_session_msg_id') + 1;
        $this->assertFalse(false === $ack->session_message_id);
        $ack->topic_message_id = 369;
        $ack->global_message_id = 7659;
        $ack->delay_time = 0;
        $ack->destination = 'unknown';
        $ack->receipt_id = 'fake-receipt-id'; 

        $ack->store();
        $good_ack = $ack->buffer(); 

        $orig_smid = $ack->session_message_id;
        $ack->session_message_id = $orig_smid + 10;
        $ack->store();
        $bad_session = $ack->buffer();

        $ack->session_message_id = $orig_smid;
        $ack->receipt_id = BigpipeUtilities::get_uid();
        $ack->store();
        $bad_receipt = $ack->buffer(); 

        $res_arr = array(
            'good' => $good_ack,
            'bad_session' => $bad_session,
            'bad_receipt' => $bad_receipt,
            'error_body'  => 'error',
        );
        return $res_arr;
    }
} // end of TestBigpipePublishTask

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
