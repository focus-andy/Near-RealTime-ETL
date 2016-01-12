<?php
/**==========================================================================
 * 
 * TestBigpipeConnection.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2013-01-01 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../frame/BigpipeConnection.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

class TestBigpipeConnection extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->conf = new BigpipeConnectionConf;

        // ����c_socket��һ��mockʵ��
        $this->stub_sock = $this
            ->getMockBuilder('c_socket')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testSend()
    {
        $subject = new BigpipeConnection($this->conf);

        // ����1 socket_address������
        $this->assertFalse($subject->send(null, 0));

        // ����socket��Ϊ
        $this->stub_sock->expects($this->any())
            ->method('write')
            ->will($this->onConsecutiveCalls(false, true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_socket', $this->stub_sock));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_check_frame', true));

        // ����2 buffer_sizeΪ0
        $this->assertFalse($subject->send(null, 0));

        // ����3 writeʧ��
        $buffer = 'This is a test';
        $buffer_size = strlen($buffer);
        $this->assertFalse($subject->send($buffer, $buffer_size));

        // ����4 write�ɹ�
        $this->assertTrue($subject->send($buffer, $buffer_size));
    }

    public function testReceive()
    {
        $subject = new BigpipeConnection($this->conf);

        // ����1 socket_address������
        $agent = array(
            'socket_address' => '0.0.0.0',
            'socket_port'    => 0,
            'socket_timeout' => 20,
        );
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_target', $agent));
        $this->assertNull($subject->receive());

        // ����socket��Ϊ
        $body = 'test body';
        $head_arr = $this->_gen_head_buff();
        $this->stub_sock->expects($this->any())
            ->method('read')
            ->will($this->onConsecutiveCalls(
                'error_head',
                $head_arr['bad'],
                $head_arr['good'],
                false,
                $head_arr['no_magic'],
                $body,
                $head_arr['good'],
                'bad body',
                $head_arr['good'],
                $body
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_socket', $this->stub_sock));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_check_frame', true));

        // ����2 head�ֽڲ���
        $this->assertNull($subject->receive());

        // ����3 body len Ϊ0
        $this->assertNull($subject->receive());
        // ����4 message body error
        $this->assertNull($subject->receive());
        // ����5 no magic number
        $this->assertEquals($body, $subject->receive());
        // ����6 checksum error
        $this->assertNull($subject->receive());
        // ����7 �ɹ�
        $this->assertEquals($body, $subject->receive());
    }

    public function testCreateConnection()
    {
        $subject = new BigpipeConnection($this->conf);

        // ����1 destination������
        $this->assertFalse($subject->create_connection());
        // set destination
        $agents = array(
            array(
                'socket_address' => '0.0.0.0',
                'socket_port'    => 0,
                'socket_timeout' => 20,
            ),
        );
        $subject->set_destinations($agents);
        // ����c_socket����Ϊ
        $this->stub_sock->expects($this->any())
            ->method('set_vars_from_array');
        $this->stub_sock->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(false, false, true));
        $this->stub_sock->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));

        $subject->unittest = true;
        $subject->stub_sock = $this->stub_sock;
        // ����socket����is_connected == true�ķ�֧
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_socket', $this->stub_sock));

        // ����2 ����ʧ�ܣ��޵�ַ�ɻ�
        $this->assertFalse($subject->create_connection());

        // ����3 ����ʧ�ܣ�����һ�γɹ�
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_max_try_time', 2));
        $agents[1] = array(
            'socket_address' => '0.0.0.1',
            'socket_port'    => 2,
            'socket_timeout' => 30,
        );
        $subject->set_destinations($agents);
        $this->assertTrue($subject->create_connection());
    }

    public function testIsReadable()
    {
        $subject = new BigpipeConnection($this->conf);

        // ����1 ��������
        $timeo = -4;
        $this->assertEquals(BigpipeErrorCode::INVALID_PARAM, $subject->is_readable($timeo));

        // ����socket��Ϊ
        $this->stub_sock->expects($this->any())
            ->method('is_readable')
            ->will($this->onConsecutiveCalls(
                c_socket::ERROR,
                c_socket::TIMEOUT,
                c_socket::OK
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_socket', $this->stub_sock));

        $timeo = 40;
        // ����2 socket error
        $this->assertEquals(BigpipeErrorCode::ERROR_CONNECTION, $subject->is_readable($timeo));

        // ����3 ��ʱ����
        $this->assertEquals(BigpipeErrorCode::TIMEOUT, $subject->is_readable($timeo));

        // ����4 READABLE
        $this->assertEquals(BigpipeErrorCode::READABLE, $subject->is_readable($timeo));
    }

    /**
     * ����head buffer
     */
    private function _gen_head_buff()
    {
        $body = 'test body';
        $body_len = strlen($body);

        $head = array();
        $head['body_len'] = $body_len;
        $head['magic_num'] = BigpipeCommonDefine::NSHEAD_CHECKSUM_MAGICNUM;
        $head['reserved'] = BigpipeUtilities::adler32($body);
        $head_builder = new NsHead;
        $head_data = $head_builder->build_nshead($head);

        $head['magic_num'] = 0;
        $no_magic_num = $head_builder->build_nshead($head);

        $head['body_len'] = 0;
        $bad_head = $head_builder->build_nshead($head);

        $ret_arr = array(
            'good' => $head_data,
            'bad'  => $bad_head,
            'no_magic' => $no_magic_num,
        );
        return $ret_arr;
    }
} // end of TestBigpipeConnection

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
