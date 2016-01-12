<?php
/**==========================================================================
 * 
 * TestBigpipeSubscriber.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-27 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../BigpipeSubscriber.class.php');
require_once(dirname(__FILE__).'/../frame/BigpipeMessagePackage.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

class TestBigpipeSubscriber extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->pipe_name = 'test';
        $this->token = 'token';
        $this->pipelet_id = 2;
        $this->start_point = -2;
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

    public function testInit()
    {
        // 测试1 multi-init an object
        $subject = new BigpipeSubscriber;
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->pipelet_id,
            $this->start_point,
            $this->conf));

        // 测试2 invalid start point
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->pipelet_id,
            -50,
            $this->conf));

        // mock meta adapter
        $this->stub_meta->expects($this->any())
            ->method('init')
            ->will($this->onConsecutiveCalls(false, true, true, true, true, true));
        $this->stub_meta->expects($this->any())
            ->method('connect')
            ->will($this->onConsecutiveCalls(false, true, true, true, true));

        $max_pipelet_id = 10;
        $good_author = array(
            'authorized'  => true,
            'num_pipelet' => $max_pipelet_id,
        );

        $bad_author = false;

        $failed_author = array(
            'authorized' => false,
            'reason'     => 'fake author'
        );

        $this->stub_meta->expects($this->any())
            ->method('authorize')
            ->will($this->onConsecutiveCalls($bad_author, $failed_author, $good_author, $good_author));
        $this->assertTrue(TestUtilities::set_private_var($subject, '_meta_adapter', $this->stub_meta));

        // 测试3 init_meta失败
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->pipelet_id,
            $this->start_point,
            $this->conf));

        // 测试4 checksum level error
        $this->conf->checksum_level = -10;
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->pipelet_id,
            $this->start_point,
            $this->conf));
        $this->conf->checksum_level = BigpipeChecksumLevel::CHECK_FRAME;

        // 测试5 meta adapter connect error
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->pipelet_id,
            $this->start_point,
            $this->conf));

        // 测试6 认证失败
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->pipelet_id,
            $this->start_point,
            $this->conf));

        // 测试7 认证被拒
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $this->pipelet_id,
            $this->start_point,
            $this->conf));

        // 测试8 认证成功，但pipelet id错误
        $this->assertFalse($subject->init(
            $this->pipe_name,
            $this->token,
            $max_pipelet_id,
            $this->start_point,
            $this->conf));

        // 测试9 init成功
        $this->assertTrue($subject->init(
            $this->pipe_name,
            $this->token,
            $this->pipelet_id,
            $this->start_point,
            $this->conf));

        // _inited 复位
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
    }

    public function testPeek()
    {
        $subject = new BigpipeSubscriber;
        $timo_ms = 100;

        // 测试1 not inited
        $this->assertEquals(BigpipeErrorCode::UNINITED, $subject->peek($timo_ms));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 设置meta_adapter的行为
        $this->stub_meta->expects($this->once())
            ->method('get_sub_broker_group')
            ->will($this->onConsecutiveCalls(false));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_adapter', $this->stub_meta));

        // 测试2 flush subscribe error
        $this->assertEquals(BigpipeErrorCode::ERROR_SUBSCRIBE, $subject->peek($timo_ms));

        // 测试3 测试package为空
        $pkg = new BigpipeMessagePackage;
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_package', $pkg));
        $this->stub_stomp->expects($this->once())
            ->method('peek')
            ->will($this->returnValue(BigpipeErrorCode::PEEK_TIMEOUT));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));
        $this->assertEquals(BigpipeErrorCode::PEEK_TIMEOUT, $subject->peek($timo_ms));

        // 测试4 package 非空
        $pkg->push('A testing message');
        $this->assertEquals(BigpipeErrorCode::READABLE, $subject->peek($timo_ms));

        // _inited 复位
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
    }

    public function testReceive()
    {
        $subject = new BigpipeSubscriber;
        $timo_ms = 100;
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_pipelet_msg_id', 65535));

        echo "test receive\n";
        // 测试1 not inited
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 测试2 未订阅失败
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));

        // 测试3 存在message package
        $pkg = new BigpipeMessagePackage;
        $msg = 'A testing message';
        $pkg->push($msg);
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_package', $pkg));
        $recv_ret = $subject->receive();
        $this->assertTrue(false !== $recv_ret);
        $this->assertEquals($msg, $recv_ret->content);

        // 测试4 测试_receive()
        // 定制meta adapter行为
        $sub_info = $this->_gen_sub_broker_group();
        $sub_info['end_pos'] = 65535; // 修改end pos 
        $this->stub_meta->expects($this->any())
            ->method('get_sub_broker_group')
            ->will($this->returnValue($sub_info));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_adapter', $this->stub_meta));

        // 定制stomp adapter行为
        $res_arr = $this->_gen_recv_response();
        $this->stub_stomp->expects($this->any())
            ->method('send')
            ->will($this->returnValue(false));
        $this->stub_stomp->expects($this->any())
            ->method('close')
            ->will($this->returnValue(true));
        $this->stub_stomp->expects($this->any())
            ->method('connect')
            ->will($this->returnValue(true));
        $this->stub_stomp->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null, 
                'failure',
                $res_arr['bad_topic'],
                $res_arr['bad_body'],
                $res_arr['bad_checksum'],
                $res_arr['bad_pkg'],
                $res_arr['empty_pkg'],
                $res_arr['good']
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));

        // 测试4.1 空response body
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));

        // 测试4.2 load response body错误
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));

        // 测试4.3 topic message id错误
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));

        // 测试4.4 空message body错误
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));

        // 测试4.5 校验码错误
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));

        // 测试4.6 load package错误
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));
        
        // 测试4.7 空package pop时出错
        $this->assertFalse($subject->receive());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));

        // 测试4.8 成功（且切换stripe）
        $this->assertTrue(false != $subject->receive());
    }

    /**
     * 测试uninit和_unsubscribe
     */
    public function testUninit()
    {
        $subject = new BigpipeSubscriber;
        // 测试1 uninted
        $subject->uninit();

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));
        $sub_info = $this->_gen_sub_broker_group();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stripe', $sub_info));
        $subject->unittest = true;

        // 设置meta_adapter的行为
        $this->stub_meta->expects($this->any())
            ->method('close')
            ->will($this->returnValue(true));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_adapter', $this->stub_meta));

        // 设置stomp adapter行为
        $this->stub_stomp->expects($this->any())
            ->method('send')
            ->will($this->returnValue(true));
        $unsub_arr = $this->_get_unsub_response();
        $this->stub_stomp->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                'wrong-response',
                $unsub_arr['bad'],
                $unsub_arr['good']
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));

        // 测试2 取消订阅失败情况
        // 测试2.1 没有response body
        $subject->uninit();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));
        $sub_info = $this->_gen_sub_broker_group();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stripe', $sub_info));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));

        // 测试2.2 load ack 失败
        $subject->uninit();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));
        $sub_info = $this->_gen_sub_broker_group();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stripe', $sub_info));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));
        
        // 测试2.3 receipt_id失败
        $subject->uninit();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_is_subscribed', true));
        $sub_info = $this->_gen_sub_broker_group();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stripe', $sub_info));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));

        // 测试3 订阅成功
        $subject->uninit();
    }

    /**
     * 测试_subscribe
     */
    public function testSubscribe()
    {
        $subject = new BigpipeSubscriber;
        $method = TestUtilities::get_private_method($subject, '_subscribe');
        $this->assertTrue(false !== $method);

        // 配置subject私有变量
        $sub_info = $this->_gen_sub_broker_group();
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stripe', $sub_info));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_pipelet_msg_id', -2));
        $subject->unittest = true;

        // 配置stomp行为
        $this->stub_stomp->expects($this->any())
            ->method('send')
            ->will($this->returnValue(true));
        $unsub_arr = $this->_get_unsub_response();
        $this->stub_stomp->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                'wrong-response',
                $unsub_arr['bad'],
                $unsub_arr['good']
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_stomp_adapter', $this->stub_stomp));

        // 测试1 res_body为null
        $this->assertFalse($method->invoke($subject));

        // 测试2 load ack失败
        $this->assertFalse($method->invoke($subject));

        // 测试3 receipt id不对
        $this->assertFalse($method->invoke($subject));

        // 测试4 成功
        $this->assertTrue($method->invoke($subject));
    }

    /**
     * 测试_update_meta
     */
    public function testUpdateMeta()
    {
        $subject = new BigpipeSubscriber;
        $method = TestUtilities::get_private_method($subject, '_update_meta');
        $this->assertTrue(false !== $method);

        // 设置meta_adapter的行为
        $sub_info = $this->_gen_sub_broker_group();
        $grp_fail = $this->_gen_sub_broker_group();
        $grp_fail['broker_group']->status = BigpipeBrokerGroupStatus::FAIL;
        $no_cand = $this->_gen_sub_broker_group();
        $no_cand['broker_group']->brokers[1]->role = BigpipeBrokerRole::PRIMARY;
        $this->stub_meta->expects($this->any())
            ->method('get_sub_broker_group')
            ->will($this->onConsecutiveCalls($grp_fail, $no_cand, $sub_info, $sub_info, $sub_info));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_meta_adapter', $this->stub_meta));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_pipelet_msg_id', SubscribeStartPoint::START_FROM_FIRST_POINT));

        // 测试1 取到的group是fail状态
        $this->assertFalse($method->invoke($subject));

        // 测试2 无候选者
        $this->assertFalse($method->invoke($subject));

        // 测试3 prefer条件不对
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_pref_conn', 9));
        $this->assertFalse($method->invoke($subject));

        // 测试4 成功并选取primary broker
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_pref_conn', BigpipeConnectPreferType::PRIMARY_BROKER_ONLY));
        $this->assertTrue($method->invoke($subject));
        $brokers = TestUtilities::get_private_var($subject, '_brokers');
        $this->assertTrue(false != $brokers);
        $this->assertEquals(1, count($brokers));
        $this->assertEquals(BigpipeBrokerRole::PRIMARY, $brokers[0]->role);

        // 测试5 成功并选取secondary broker
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_pref_conn', BigpipeConnectPreferType::SECONDARY_BROKER_ONLY));
        $this->assertTrue($method->invoke($subject));
        $brokers = TestUtilities::get_private_var($subject, '_brokers');
        $this->assertTrue(false != $brokers);
        $this->assertEquals(1, count($brokers));
        $this->assertEquals(BigpipeBrokerRole::SECONDARY, $brokers[0]->role);
    }

    /**
     * 从文件中读取broker group提供给测试用例
     * @return sub_info on success or false on failure
     */
    private function _gen_sub_broker_group()
    {
        $pathname = './sub_info.json';
        if (false === file_exists($pathname))
        {
            // missing example file
            return false;
        }

        $content = file_get_contents($pathname);
        $broker_group = json_decode($content);
        if (null === $broker_group)
        {
            return false;
        }

        $sub_info = array(
            'stripe_name' => $broker_group->stripe_name,
            'stripe_id'   => $broker_group->stripe_id,
            'begin_pos'   => $broker_group->begin_pos,
            'end_pos'     => $broker_group->end_pos,
            'broker_group' => $broker_group->broker_group,
        );
        return $sub_info;
    }

    private function _gen_recv_response()
    {
        $topic_id = 65535;
        $bad_topic_id = 0;
        $pkg = new BigpipeMessagePackage;
        $msg = 'This is a test case';
        $pkg->push($msg);
        $msg_body = null;
        $pkg->store($msg_body);
        $sign = creat_sign_mds64($msg_body);

        $frame = new BStompMessageFrame;
        $frame->priority = 10;
        $frame->persistent = 1;
        $frame->no_dedupe  = 1;
        $frame->timeout = BigpipeUtilities::get_time_us();
        $frame->destination = 'cluster-for-unittest';
        $frame->session_id = BigpipeUtilities::get_uid();
        $frame->subscribe_id = BigpipeUtilities::get_uid();
        $frame->receipt_id = BigpipeUtilities::gen_receipt_id();
        $frame->session_message_id = BigpipeUtilities::get_uid();
        $frame->topic_message_id = $topic_id;
        $frame->global_message_id = 76248;
        $frame->cur_checksum = $sign[2]; 
        $frame->last_checksum = 0;
        $frame->message_body = $msg_body;

        $frame->store();
        $good =$frame->buffer();

        // topic message id 错误的case
        $frame->topic_message_id = $bad_topic_id;
        $frame->store();
        $bad_topic = $frame->buffer();

        // message body 错误的case
        $frame->topic_message_id = $topic_id;
        $frame->message_body = '';
        $frame->store();
        $bad_body = $frame->buffer();

        // checksum错误的case
        $frame->message_body = $msg_body;
        $frame->cur_checksum = 201;
        $frame->store();
        $bad_checksum = $frame->buffer();

        // 创造一个error的包
        $err_pkg = '1';
        $frame->message_body = $err_pkg;
        $err_sign = creat_sign_mds64($err_pkg);
        $frame->cur_checksum = $err_sign[2];
        $frame->store();
        $bad_pkg = $frame->buffer();

        // 创造一个pop error的包
        $frame->message_body = pack("L2", 1, 5); // 这是一个长度为5，但是没有数据的坏包
        $empty_sign = creat_sign_mds64($frame->message_body);
        $frame->cur_checksum = $empty_sign[2];
        $frame->store();
        $empty_pkg = $frame->buffer();

        $res_arr = array(
            'good'      => $good,
            'bad_topic' => $bad_topic,
            'bad_body'  => $bad_body,
            'bad_checksum' => $bad_checksum,
            'bad_pkg'   => $bad_pkg,
            'empty_pkg' => $empty_pkg,
        );
        return $res_arr;
    }

    private function _get_unsub_response()
    {
        $ack = new BStompReceiptFrame;
        $ack->receipt_id = 'unittest-receipt-id';
        $ack->store();
        $good = $ack->buffer();

        $ack->receipt_id = 'wrong-receipt-id';
        $ack->store();
        $bad = $ack->buffer();

        $unsub_ack = array(
            'good' => $good,
            'bad'  => $bad,
        );
        return $unsub_ack;
    }
} // end of TestBigpipeSubscriber

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>

