<?php
/**==========================================================================
 * 
 * TestBigpipeStompAdapter.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2013-01-02 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../frame/BigpipeStompAdapter.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

class TestBigpipeStompAdapter extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->conf = new BigpipeStompConf;
        $this->peek_timeo = 10;
        $this->conf->conn_conf->try_time = 1;

        // 生成c_socket的一个mock实例
        $this->stub_conn = $this
            ->getMockBuilder('BigpipeConnection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * 测试connect接口
     * 测试_stomp_connect过程
     */
    public function testConnect()
    {
        $subject = new BigpipeStompAdapter($this->conf);
        $subject->session_id = 'id-for-unittest';
        $subject->topic_name = 'stipe';

        // mock connection 行为
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->onConsecutiveCalls(
                false,
                true, true, true, true, true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->onConsecutiveCalls(
                false,
                true, true, true, true));

        $this->stub_conn->expects($this->any())
            ->method('close');

        $ack_pkgs = $this->_gen_connected_frame();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                $ack_pkgs['bad'],
                $ack_pkgs['good'] 
            ));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // 测试1 create connection失败
        $this->assertFalse($subject->connect()); 

        // 测试2 测试stomp connect
        // 测试2.1 connect send失败
        $this->assertFalse($subject->connect()); 
        // 测试2.2 receive null
        $this->assertFalse($subject->connect()); 
        // 测试2.3 receive bad ack
        $this->assertFalse($subject->connect()); 
        // 测试2.4 成功
        $this->assertTrue($subject->connect()); 
    }

    /**
     * 测试send口
     */
    public function testSend()
    {
        $subject = new BigpipeStompAdapter($this->conf);
        $subject->session_id = 'id-for-unittest';
        $subject->topic_name = 'stipe';

        // 定义connection行为
        $this->stub_conn->expects($this->once())
            ->method('send')
            ->will($this->returnValue(true));

        $this->stub_conn->expects($this->any())
            ->method('close');

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // 测试1 command store失败
        $frame = new FakeFrame();
        $this->assertFalse($subject->send($frame)); 

        // 测试2 测试store成功
        $frame = new BStompReceiptFrame;
        $frame->receipt_id = 'receipt-id';
        $this->assertTrue($subject->send($frame)); 
    }

    /**
     * 测试receive接口
     */
    public function testReceive()
    {
        $subject = new BigpipeStompAdapter($this->conf);
        $subject->session_id = 'id-for-unittest';
        $subject->topic_name = 'stipe';

        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('close');

        $normal = $this->_gen_connected_frame();
        $frame = $this->_gen_error_ack();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                $frame,
                $normal['good']
            ));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // 测试1 response是null
        $this->assertNull($subject->receive()); 

        // 测试2 response是stomp标准error包
        $this->assertNull($subject->receive()); 
        // 测试3 response普通包
        $this->assertFalse(null == $subject->receive()); 
    }

    /**
     * 测试peek接口
     */
    public function testPeek()
    {
        $subject = new BigpipeStompAdapter($this->conf);

        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_readable')
            ->will($this->onConsecutiveCalls(
                BigpipeErrorCode::READABLE,
                BigpipeErrorCode::TIMEOUT,
                BigpipeErrorCode::TIMEOUT,
                BigpipeErrorCode::PEEK_ERROR
            ));
        $this->stub_conn->expects($this->any())
            ->method('close');

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        $timeo = 10;
        // 定义peek行为
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_peek_timeo_ms', 19));

        // 测试1 peek readable
        $this->assertEquals(BigpipeErrorCode::READABLE, $subject->peek($timeo)); 
        // 测试2 peek unreadable
        $this->assertEquals(BigpipeErrorCode::UNREADABLE, $subject->peek($timeo)); 
        // 测试3 peek timeout
        $this->assertEquals(BigpipeErrorCode::PEEK_TIMEOUT, $subject->peek($timeo)); 
        // 测试4 peek error
        $this->assertEquals(BigpipeErrorCode::PEEK_ERROR, $subject->peek($timeo)); 
    }

    /**
     * 测试set_destination接口和close接口
     */
    public function testOther()
    {
        $subject = new BigpipeStompAdapter($this->conf);

        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('set_destinations');
        $this->stub_conn->expects($this->any())
            ->method('close');
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // set_destination和close是两个无状态接口
        // 仅覆盖到便可
        $dest = array('fake destination');
        $subject->set_destination($dest);
        $subject->close();
    }

    private function _gen_connected_frame()
    {
        $ack = new BStompConnectedFrame;
        $ack->session_id = 'id-for-unittest';
        $ack->session_message_id = 9527;
        $ack->store();
        $good = $ack->buffer();

        // bad frame
        $bad_ack = new BStompReceiptFrame;
        $bad_ack->receipt_id = 'bad-id';
        $bad_ack->store();
        $bad = $bad_ack->buffer();

        return array(
            'good' => $good,
            'bad'  => $bad,
        );
    }

    private function _gen_error_ack()
    {
        $ack = new BStompErrorFrame;
        $ack->error_no = 10;
        $ack->error_message = 'unit test';
        $ack->store();
        return $ack->buffer();
    }
} // end of TestBigpipeStompAdapter

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
