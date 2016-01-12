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
     * ����init
     */
    public function testInit()
    {
        $subject = new BigpipeQueueClient;
        $subject->unittest = true;

        // ����1 multi-init����
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->init($this->que_name, $this->token, $this->conf));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));

        // ����2 ��token
        $this->assertFalse($subject->init($this->que_name, null, $this->conf));

        // �ı�meta��Ϊ
        $this->stub_meta->expects($this->any())
            ->method('init')
            ->will($this->onConsecutiveCalls(false, true));
        $subject->stub_meta = $this->stub_meta;

        // ����3 QueueServerMeta��ʼ��ʧ��
        $this->assertFalse($subject->init($this->que_name, $this->token, $this->conf));

        // ����4 init�ɹ�
        $this->assertTrue($subject->init($this->que_name, $this->token, $this->conf));
    }

    /**
     * ����peek��_peek�ӿ�
     */
    public function testPeek()
    {
        $subject = new BigpipeQueueClient;
        $timeo_ms = 10;

        // ����1 uninit�µ��ýӿ�
        $this->assertEquals(BigpipeErrorCode::UNINITED, $subject->peek($timeo_ms));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        
        // �ı�meta��Ϊ
        $this->stub_meta->expects($this->any())
            ->method('update')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta', $this->stub_meta));
        
        // ����2 δ��������µ���init, ��refreshʧ��
        $this->assertEquals(BigpipeErrorCode::ERROR_SUBSCRIBE, $subject->peek($timeo_ms));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_subscribed', true));

        // ����3 ���� _peek
        // ����connection��Ϊ
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

        // ����3.1 peek error���
        $this->assertEquals(BigpipeErrorCode::PEEK_ERROR, $subject->peek($timeo_ms));

        // ����3.2 unreadable���
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_peek_timeo', 15));
        $this->assertEquals(BigpipeErrorCode::UNREADABLE, $subject->peek($timeo_ms));

        // ����3.3 timeout���
        $this->assertEquals(BigpipeErrorCode::PEEK_TIMEOUT, $subject->peek($timeo_ms));

        // ����3.4 readable���
        $this->assertEquals(BigpipeErrorCode::READABLE, $subject->peek($timeo_ms));
    }

    /**
     * ����receive��_receive�ӿ�
     */
    public function testReceive()
    {
        $subject = new BigpipeQueueClient;

        // ����1 uninit�µ��ýӿ�
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����meta��Ϊ
        $this->stub_meta->expects($this->once())
            ->method('queue_name')
            ->will($this->returnValue('queue_client'));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta', $this->stub_meta));

        // ����2 δ����ʱ����receive
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_subscribed', true));

        // ����3 ����_receive����
        // ����connection��Ϊ
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

        // ����3.1 none��Ӧ
        $this->assertFalse($subject->receive());

        // ����3.2 mc_pack error
        $this->assertFalse($subject->receive());

        // ����3.3 wrong package
        $this->assertFalse($subject->receive());

        // ����3.4 print error message in package
        $this->assertFalse($subject->receive());

        // ����4 �ɹ�����
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
     * ����refresh�ӿڼ�_subscribe����
     */
    public function testRefresh()
    {
        $subject = new BigpipeQueueClient;

        // ����1 uninit�µ��ýӿ�
        $this->assertFalse($subject->refresh());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        // ʹ�������call _disconnect��֧
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_subscribed', true));

        // ����connection����Ϊ
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

        // ����meta����Ϊ
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
        
        // ����2:
        // 1 ����_disconnect��֧
        // 2 _connectʧ��
        $this->assertFalse($subject->refresh());

        // ����3: ����_connect
        // ����3.1: ȡqueue_addressʧ��
        $this->assertFalse($subject->refresh());
        // ����3.2: _connect�ɹ�, ����ʧ��
        $this->assertFalse($subject->refresh());
        // ����3.2: _connect�ɹ�,���ĳɹ� 
        $this->assertTrue($subject->refresh());
    }

    public function testAck()
    {
        $subject = new BigpipeQueueClient;

        // ����1 uninit�µ��ýӿ�
        $this->assertFalse($subject->ack(null));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // ����2 �������ʧ��
        $ack_msg = new BigpipeQueueMessage;
        $this->assertFalse($subject->ack($ack_msg));
        $ack_msg->pipe_name = 'pipe';

        // ����connection����Ϊ
        $this->stub_conn->expects($this->once())
            ->method('send')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // ����meta����Ϊ
        // todo �޷�����ֻ�׳�һ���쳣,�쳣���շ�֧�ᱻ����
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
        // response��
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

        // reuqire��
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
