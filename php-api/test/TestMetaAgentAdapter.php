<?php
/**==========================================================================
 * 
 * ./TestMetaAgentAdapter.php - INF / DS / BIGPIPE
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
require_once(dirname(__FILE__).'/../frame/MetaAgentAdapter.class.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_stomp_frames.inc.php');
require_once(dirname(__FILE__).'/../frame/bigpipe_configures.inc.php');

class TestMetaAgentAdapter extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->conf = new MetaAgentConf;
        $meta_conf = new BigpipeMetaConf;
        $this->conf->meta = $meta_conf->to_array();
        $this->conf->agents = array (
            array ("socket_address"  => "10.46.46.54",
            "socket_port"     => 8021,                       
            "socket_timeout"  => 300),                
        );
        $this->conf->conn_conf->try_time = 1;

        // 生成c_socket的一个mock实例
        $this->stub_conn = $this
            ->getMockBuilder('BigpipeConnection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testInit()
    {
        $subject = new MetaAgentAdapter;
        
        // 测试1 init已被初始化的对象
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->init($this->conf));

        // 测试2 成功初始化
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
        $this->assertTrue($subject->init($this->conf));
    }

    /**
     * 测试uninit和close接口
     * 完成对私有函数_uninit_meta, _connected, _disconnect, _create_connection的测试
     */
    public function testUninit()
    {
        $subject = new MetaAgentAdapter;
        
        // 测试1 uninit还未被初始化的对象
        $subject->uninit();

        // 测试2 uninit flow 
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        // mock connection 行为
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));
        $subject->uninit();
    }

    /**
     * 经过测试uninit接口，close接口只要测试一种情况
     */
    public function testClose()
    {
        $subject = new MetaAgentAdapter;
        
        // 测试1 close还未被初始化的对象
        $subject->close();
    }

    /**
     * 测试connect接口
     * 测试init_meta过程
     */
    public function testConnect()
    {
        $subject = new MetaAgentAdapter;
        
        // 测试1 connect还未被初始化的对象
        $this->assertFalse($subject->connect());

        // 测试2 init meta
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));

        // 测试2.1 package init meta 包失败
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_conf', $this->conf));
        $this->assertFalse($subject->connect());

        // 补全conf
        $this->conf->meta['meta_host'] = '0.0.0.0:0';
        $this->conf->meta['root_path'] = '/root';
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_conf', $this->conf));

        // mock connection 行为
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('close');

        $ack_pkgs = $this->_gen_init_meta_ack();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                $ack_pkgs['bad'],
                $ack_pkgs['good'] 
            ));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));
        
        // 测试2.2 request null
        $this->assertFalse($subject->connect());
        // 测试2.3 ack wrong pakcage
        $this->assertFalse($subject->connect());
        // 测试2.4 成功 
        $this->assertTrue($subject->connect());
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
    }

    /**
     * 测试connect_ex接口
     * 测试init_api过程
     */
    public function testConnectEx()
    {
        $subject = new MetaAgentAdapter;
        $pipe_name = 'pipe';
        $token = 'token';
        $role = BStompRoleType::PUBLISHER;

        // 测试1 connect还未被初始化的对象
        $this->assertFalse($subject->connect_ex($pipe_name, $token, $role));

        // 测试2 init api
        $this->assertTrue(TestUtilities::set_private_var(
                $subject, '_inited', true));

        // 测试2.1 package init meta 包失败
        $this->assertTrue(TestUtilities::set_private_var(
                $subject, '_conf', $this->conf));
        $this->assertFalse($subject->connect_ex($pipe_name, $token, $role));

        // 补全conf
        $this->conf->meta['meta_host'] = '0.0.0.0:0';
        $this->conf->meta['root_path'] = '/root';
        $this->assertTrue(TestUtilities::set_private_var(
                $subject, '_conf', $this->conf));

        // mock connection 行为
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('close');

        $ack_pkgs = $this->_gen_init_api_ack();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                   null,
                   $ack_pkgs['bad'],
                   $ack_pkgs['failed'],
                   $ack_pkgs['passed']
        ));

        $this->assertTrue(TestUtilities::set_private_var(
                $subject, '_connection', $this->stub_conn));

        // 测试2.2 request null
        $this->assertFalse($subject->connect_ex($pipe_name, $token, $role));
        // 测试2.3 ack wrong pakcage
        $this->assertFalse($subject->connect_ex($pipe_name, $token, $role));
        // 测试2.4 认证失败
        $this->assertFalse($subject->connect_ex($pipe_name, $token, $role));
        // 测试2.5 成功
        $this->assertEquals(10, $subject->connect_ex($pipe_name, $token, $role));
        $this->assertTrue(TestUtilities::set_private_var(
                $subject, '_inited', false));
    }

    public function testGetPubBroker()
    {
        $subject = new MetaAgentAdapter;
        $pipe_name = 'pipe';
        $pipelet_id = 2;

        // 测试1 从还未被初始化的对象调用接口
        $this->assertFalse($subject->get_pub_broker($pipe_name, $pipelet_id));

        // 测试2 meta name为空
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->get_pub_broker(null, $pipelet_id));

        // mock connection 行为
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('close');

        $ack_pkgs = $this->_gen_get_pubinfo_ack();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                $ack_pkgs['bad'],
                $ack_pkgs['good'] 
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));
        
        // 测试2.2 request null
        $subject->meta_name = 'meta';
        $this->assertFalse($subject->get_pub_broker($pipe_name, $pipelet_id));
        // 测试2.3 ack wrong pakcage
        $this->assertFalse($subject->get_pub_broker($pipe_name, $pipelet_id));
        // 测试2.4 成功 
        $broker = $subject->get_pub_broker($pipe_name, $pipelet_id);
        $this->assertTrue(false != $broker);
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
    }

    public function testGetSubBrokerGroup()
    {
        $subject = new MetaAgentAdapter;
        $pipe_name = 'pipe';
        $pipelet_id = 2;
        $start_point = 4;

        // 测试1 从还未被初始化的对象调用接口
        $this->assertFalse($subject->get_sub_broker_group($pipe_name, $pipelet_id, $start_point));

        // 测试2 meta name为空
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->get_sub_broker_group(null, $pipelet_id, $start_point));

        // mock connection 行为
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('close');

        $ack_pkgs = $this->_gen_get_subinfo_ack();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                $ack_pkgs['bad'],
                $ack_pkgs['good'] 
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));
        
        // 测试2.2 request null
        $subject->meta_name = 'meta';
        $this->assertFalse($subject->get_sub_broker_group($pipe_name, $pipelet_id, $start_point));
        // 测试2.3 ack wrong pakcage
        $this->assertFalse($subject->get_sub_broker_group($pipe_name, $pipelet_id, $start_point));
        // 测试2.4 成功 
        $broker = $subject->get_sub_broker_group($pipe_name, $pipelet_id, $start_point);
        $this->assertTrue(false != $broker);
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
    }

    public function testAuthorize()
    {
        $subject = new MetaAgentAdapter;
        $pipe_name = 'pipe';
        $token = 'token';
        $role = BStompRoleType::PUBLISHER;

        // 测试1 从还未被初始化的对象调用接口
        $this->assertFalse($subject->authorize($pipe_name, $token, $role));

        // 测试2 meta name为空
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', true));
        $this->assertFalse($subject->authorize(null, $token, $role));

        // mock connection 行为
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('close');

        $ack_pkgs = $this->_gen_authorize_ack();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                $ack_pkgs['bad'],
                $ack_pkgs['failed'],
                $ack_pkgs['passed'] 
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));
        
        // 测试2.2 request null
        $subject->meta_name = 'meta';
        $this->assertFalse($subject->authorize($pipe_name, $token, $role));
        // 测试2.3 ack wrong pakcage
        $this->assertFalse($subject->authorize($pipe_name, $token, $role));
        // 测试2.4 认证不通过
        $auth_ret = $subject->authorize($pipe_name, $token, $role);
        $this->assertTrue(false != $auth_ret);
        $this->assertFalse($auth_ret['authorized']);
        // 测试2.5 认证通过
        $auth_ret = $subject->authorize($pipe_name, $token, $role);
        $this->assertTrue(false != $auth_ret);
        $this->assertTrue($auth_ret['authorized']);
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
    }

    /**
     * 测试私有函数
     */
    public function testRequest()
    {
        $subject = new MetaAgentAdapter;
        $metod = TestUtilities::get_private_method($subject, '_request');
        $this->assertTrue(false != $metod);

        // mock connection 行为
        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->onConsecutiveCalls(
                false, 
                true, true, true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->onConsecutiveCalls(
                false, 
                true, true));
        $this->stub_conn->expects($this->any())
            ->method('close');

        $ack = new MetaAgentErrorAckFrame;
        $ack->error_code = 369;
        $ack->error_code = 'bingo';
        $ack->store();
        $ack_data = $ack->buffer();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                $ack_data
            ));
        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        $frame = new FakeFrame;

        // 测试1 create connection失败
        $this->assertNull($metod->invoke($subject, $frame));

        // 测试2 buffer size为0
        $this->assertNull($metod->invoke($subject, $frame));

        // 使用UninitMetaFrame测试
        $frame = new UninitMetaFrame;
        $frame->meta_name = 'meta';

        // 测试3 send失败 
        $this->assertNull($metod->invoke($subject, $frame));
        // 测试4 send成功，但收到server error包
        $this->assertNull($metod->invoke($subject, $frame));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_inited', false));
    }

    public function testUninitMeta()
    {
        $subject = new MetaAgentAdapter;
        $metod = TestUtilities::get_private_method($subject, '_uninit_meta');
        $this->assertTrue(false != $metod);

        // 测试1 进入close流程
        // 测试1.1 测试_uninit_meta方法

        // 定义connection行为
        $this->stub_conn->expects($this->any())
            ->method('is_connected')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('create_connection')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('send')
            ->will($this->returnValue(true));
        $this->stub_conn->expects($this->any())
            ->method('close');

        $ack_pkgs = $this->_gen_uninit_meta_ack();
        $this->stub_conn->expects($this->any())
            ->method('receive')
            ->will($this->onConsecutiveCalls(
                null,
                $ack_pkgs['bad'],
                $ack_pkgs['good'] 
            ));

        $this->assertTrue(TestUtilities::set_private_var(
            $subject, '_connection', $this->stub_conn));

        // 测试1.1.1 meta_name为空
        $subject->meta_name = null;
        $metod->invoke($subject, $subject->meta_name);

        // 测试2.1.2 request返回为空
        $subject->meta_name = 'meta';
        $metod->invoke($subject, $subject->meta_name);

        // 测试2.1.3 res_body无法解包
        $subject->meta_name = 'meta';
        $metod->invoke($subject, $subject->meta_name);

        // 测试2.1.4 res_body正常
        $subject->meta_name = 'meta';
        $metod->invoke($subject, $subject->meta_name);
    }

    private function _gen_uninit_meta_ack()
    {
        $ack = new UninitMetaAckFrame;
        $ack->status = 0;
        $ack->store();
        $data = $ack->buffer();

        $err_ack = new InitMetaAckFrame;
        $err_ack->status = 0;
        $err_ack->meta_name = 'meta'; 
        $err_ack->store();
        $err_data = $err_ack->buffer();
        return array(
            'good' => $data,
            'bad'  => $err_data,
        );
    }

    private function _gen_init_meta_ack()
    {
        $ack = new InitMetaAckFrame;
        $ack->status = 0;
        $ack->meta_name = 'meta';
        $ack->store();
        $data = $ack->buffer();

        $err_ack = new UninitMetaAckFrame;
        $err_ack->status = 0;
        $err_ack->store();
        $err_data = $err_ack->buffer();

        return array(
            'good' => $data,
            'bad'  => $err_data,
        );
    }

    private function _gen_get_pubinfo_ack()
    {
        $ack = new GetPubInfoAckFrame;
        $ack->status = 0;
        $ack->broker_ip = '1.1.1.1';
        $ack->broker_port = 1;
        $ack->stripe_name = 'stripe';
        $ack->store();
        $data = $ack->buffer();

        $ret_arr = $this->_gen_init_meta_ack();
        $ret_arr['good'] = $data;
        return $ret_arr;
    }

    private function _gen_get_subinfo_ack()
    {
        $pathname = './sub_info.json';
        if (false === file_exists($pathname))
        {
            // missing example file
            return false;
        }
        $content = file_get_contents($pathname);

        $ack = new GetSubInfoAckFrame;
        $ack->status = 0;
        $ack->stripe_name = 'stripe';
        $ack->stripe_id = 1;
        $ack->begin_pos = 1;
        $ack->end_pos = 9527;
        $ack->broker_group = $content;
        $ack->store();
        $data = $ack->buffer();

        $ret_arr = $this->_gen_init_meta_ack();
        $ret_arr['good'] = $data;
        return $ret_arr;
    }

    private function _gen_authorize_ack()
    {
        $ack = new AuthorizeAckFrame;
        $ack->status = 0;
        $ack->num_pipelet = 10;
        $ack->store();
        $passed = $ack->buffer();

        $ack->status = 10;
        $ack->error_message = 'failed';
        $ack->store();
        $failed = $ack->buffer();

        $bad_ack = new UninitMetaAckFrame;
        $bad_ack->status = 0;
        $bad_ack->store();
        $bad = $bad_ack->buffer();

        return array(
            'passed' => $passed,
            'failed' => $failed,
            'bad'    => $bad,
        );
    }

    private function _gen_init_api_ack()
    {
        $ack = new InitApiAckFrame;
        $ack->status = 0;
        $ack->num_pipelet = 10;
        $ack->meta_name = 'meta';
        $ack->store();
        $passed = $ack->buffer();
    
        $ack->status = 10;
        $ack->error_message = 'failed';
        $ack->store();
        $failed = $ack->buffer();
    
        $bad_ack = new UninitMetaAckFrame;
        $bad_ack->status = 0;
        $bad_ack->store();
        $bad = $bad_ack->buffer();
    
        return array(
                'passed' => $passed,
                'failed' => $failed,
                'bad'    => $bad,
        );
    }
} // end of TestMetaAgentAdapter

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
