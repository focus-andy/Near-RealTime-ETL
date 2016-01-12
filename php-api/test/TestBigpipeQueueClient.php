<?php
/**==========================================================================
 * 
 * TestBigpipeQueueClient.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-29 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../BigpipeQueueClient.class.php');

class TestBigpipeQueueClient extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->que_name = 'queue';
        $this->token = 'token';
        $this->conf = new BigpipeQueueConf;
        $this->stub_meta = $this
            ->getMockBuilder('QueueServerMeta')
            ->disableOriginalConstructor()
            ->getMock();
        $this->stub_conn = $this
            ->getMockBuilder('BigpipeConnection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * 测试init
     */
    public function testInit()
    {
        $subject = new BigpipeQueueClient;
        $subject->unittest = true;

        // 测试1 multi-init错误
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->init($this->que_name, $this->token, $this->conf));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));

        // 测试2 空token
        $this->assertFalse($subject->init($this->que_name, null, $this->conf));

        // 改变meta行为
        $this->stub_meta->expects($this->any())
            ->method('init')
            ->will($this->onConsecutiveCalls(false, true));
        $subject->stub_meta = $this->stub_meta;

        // 测试3 QueueServerMeta初始化失败
        $this->assertFalse($subject->init($this->que_name, $this->token, $this->conf));

        // 测试4 init成功
        $this->assertTrue($subject->init($this->que_name, $this->token, $this->conf));
    }

    /**
     * 测试peek和_peek接口
     */
    public function testPeek()
    {
        $subject = new BigpipeQueueClient;
        $timeo_ms = 10;

        // 测试1 uninit下调用接口
        $this->assertEquals(BigpipeErrorCode::UNINITED, $subject->peek($timeo_ms));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        
        // 改变meta行为
        $this->stub_meta->expects($this->any())
            ->method('update')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta', $this->stub_meta));
        
        // 测试2 未订阅情况下调用init, 且refresh失败
        $this->assertEquals(BigpipeErrorCode::ERROR_SUBSCRIBE, $subject->peek($timeo_ms));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_subscribed', true));

        // 测试3 测试 _peek
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_readable')
            ->will($this->onConsecutiveCalls(
                BigpipeErrorCode::PEEK_ERROR,
                BigpipeErrorCode::TIMEOUT,
                BigpipeErrorCode::TIMEOUT,
                BigpipeErrorCode::READABLE
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // 测试3.1 peek error情况
        $this->assertEquals(BigpipeErrorCode::PEEK_ERROR, $subject->peek($timeo_ms));

        // 测试3.2 unreadable情况
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_peek_timeo', 15));
        $this->assertEquals(BigpipeErrorCode::UNREADABLE, $subject->peek($timeo_ms));

        // 测试3.3 timeout情况
        $this->assertEquals(BigpipeErrorCode::PEEK_TIMEOUT, $subject->peek($timeo_ms));

        // 测试3.4 readable情况
        $this->assertEquals(BigpipeErrorCode::READABLE, $subject->peek($timeo_ms));
    }

    /**
     * 测试receive和_receive接口
     */
    public function testReceive()
    {
        $subject = new BigpipeQueueClient;

        // 测试1 uninit下调用接口
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 定义meta行为
        $this->stub_meta->expects($this->once())
            ->method('queue_name')
            ->will($this->returnValue('queue_client'));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta', $this->stub_meta));

        // 测试2 未订阅时调用receive
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_subscribed', true));

        // 测试3 测试_receive函数
        // 定义connection行为
        $idl_arr = $this->_gen_idl_pack();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                ' ',
                $idl_arr['req'],
                $idl_arr['res_err'],
                $idl_arr['res']
            ));
        $this->stub_conn->expects($this->any())
            ->method('close')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // 测试3.1 none响应
        $this->assertFalse($subject->receive());

        // 测试3.2 mc_pack error
        $this->assertFalse($subject->receive());

        // 测试3.3 wrong package
        $this->assertFalse($subject->receive());

        // 测试3.4 print error message in package
        $this->assertFalse($subject->receive());

        // 测试4 成功接收
        $msg = $subject->receive();
        $this->assertTrue(false != $msg);
        $expected = new BigpipeQueueMessage;
        $expected->pipe_name = 'pipe';
        $expected->pipelet_id = 2;
        $expected->pipelet_msg_id = 65535;
        $expected->seq_id = 9527;
        $expected->message_body = 'Testing Queue Client';
        $this->assertEquals($expected, $msg);
    }

    /**
     * 测试refresh接口及_subscribe过程
     */
    public function testRefresh()
    {
        $subject = new BigpipeQueueClient;

        // 测试1 uninit下调用接口
        $this->assertFalse($subject->refresh());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        // 使程序进入call _disconnect分支
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_subscribed', true));

        // 定义connection的行为
        $this->stub_conn->expects($this->any())
            ->method('close')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('set_destinations')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // 定义meta的行为
        $this->stub_meta->expects($this->any())
            ->method('update')
            ->will($this->onConsecutiveCalls(false, true, true));
        $address = array(
            'socket_address' => '127.0.0.1',
            'socket_port'    => 803);
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_rw_timeo', 5000));
        $this->stub_meta->expects($this->any())
            ->method('queue_address')
            ->will($this->onConsecutiveCalls(false, $address, $address));
        $this->stub_meta->expects($this->any())
            ->method('queue_name')
            ->will($this->returnValue($this->que_name));
        $this->stub_meta->expects($this->any())
            ->method('token')
            ->will($this->returnValue($this->token));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta', $this->stub_meta));
        
        // 测试2:
        // 1 进入_disconnect分支
        // 2 _connect失败
        $this->assertFalse($subject->refresh());

        // 测试3: 测试_connect
        // 测试3.1: 取queue_address失败
        $this->assertFalse($subject->refresh());
        // 测试3.2: _connect成功, 订阅失败
        $this->assertFalse($subject->refresh());
        // 测试3.2: _connect成功,订阅成功 
        $this->assertTrue($subject->refresh());
    }

    public function testAck()
    {
        $subject = new BigpipeQueueClient;

        // 测试1 uninit下调用接口
        $this->assertFalse($subject->ack(null));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 测试2 参数检查失败
        $ack_msg = new BigpipeQueueMessage;
        $this->assertFalse($subject->ack($ack_msg));
        $ack_msg->pipe_name = 'pipe';

        // 定义connection的行为
        $this->stub_conn->expects($this->once())
            ->method('send')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // 定义meta的行为
        // todo 无法控制只抛出一次异常,异常接收分支会被跳过
        $e = new ErrorException('php unit test');
        $this->stub_meta->expects($this->any())
            ->method('queue_name')
            ->will($this->returnValue($this->que_name)
            );
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta', $this->stub_meta));
        $this->assertTrue($subject->ack($ack_msg));
    }

    private function _gen_idl_pack()
    {
        // response包
        $res_arr = array();
        $idl_qudata = new idl_queue_data_t;
        $idl_qudata->seterr_no(0);
        $idl_qudata->setqueue_name($this->que_name);
        $idl_qudata->setpipe_name('pipe');
        $idl_qudata->setpipelet_id(2);
        $idl_qudata->setpipelet_msg_id(65535);
        $idl_qudata->setseq_id(9527);
        $idl_qudata->setmsg_body('Testing Queue Client');
        $idl_qudata->save($res_arr);
        $res_pkg = mc_pack_array2pack($res_arr);

        $idl_qudata->seterr_no(10);
        $idl_qudata->seterr_msg('Test error mesage return');
        $res_errmsg_arr = array();
        $idl_qudata->save($res_errmsg_arr);
        $res_errmsg_pkg = mc_pack_array2pack($res_errmsg_arr);

        // reuqire包
        $wnd_size = 30;
        $req_arr = array();
        $idl_req = new idl_queue_req_t();
        $idl_req->setcmd_no(BigpipeQueueSvrCmdType::REQ_QUEUE_DATA);
        $idl_req->setqueue_name($this->que_name);
        $idl_req->settoken($this->token);
        $idl_req->setwindow_size($wnd_size);
        $idl_req_arr = array();
        $idl_req->save($req_arr);
        $req_pkg = mc_pack_array2pack($req_arr);

        return array(
            'res'     => $res_pkg,
            'res_err' => $res_errmsg_pkg,
            'req'     => $req_pkg,
        );
    }
}



/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
