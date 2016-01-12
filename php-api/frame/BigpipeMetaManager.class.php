<?php
/**==========================================================================
 *
 * MetaManager.class.php - INF / DS / BIGPIPE
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 * Created on 2012-12-19 by YANG ZHENYU (yangzhenyu@baidu.com)
 *
 * --------------------------------------------------------------------------
 *
 * Description
 *     ����һ��zookeeper���ӵ�ʵ�����ṩ�ӿڹ��û�����bigpipe meta
 *
 * --------------------------------------------------------------------------
 *
 * Change Log
 *
 *
 ==========================================================================**/
require_once(dirname(__FILE__).'/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/BigpipeLog.class.php');
require_once(dirname(__FILE__).'/ZooKeeperConnection.class.php');

/**
 * ����һ��zookeepr connection��ʵ��<p>
 * ��װ�˶�meta�Ĳ���
 * @author yangzhenyu@baidu.com
 */
class BigpipeMetaManager
{
    /**
     * default construct
     */
    public function __construct()
    {
        $this->_inited = false;
    }

    /**
     * default destruct
     */
    public function __destruct()
    {
        if (true === $this->_inited)
        {
            $this->uninit();
        }
    }

    /**
     * ������zoo keeper������
     * @param unknown $meta_params
     */
    public function init($meta_params)
    {
        if (true === $this->_inited)
        {
            // ������multi-init
            BigpipeLog::warning("[%s:%u][%s][multi-init]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ���meta����
        if (!isset($meta_params['root_path']))
        {
            BigpipeLog::warning("[%s:%u][%s][no root path]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        $this->_root_path = $meta_params['root_path'];

        $this->_zk_connection = isset($this->unittest) ? $this->stub_zk : new ZooKeeperConnection;
        if (false === $this->_zk_connection->init($meta_params))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to init zookeeper]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // check root path
        if (false === $this->_zk_connection->exists($this->_root_path))
        {
            BigpipeLog::warning("[%s:%u][%s][no such root][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $this->_root_path);
            return false;
        }

        $this->_inited = true;
        return true;
    }

    /**
     * ����ʼ��
     * @return void type
     */
    public function uninit()
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][multi-uninit]",
                __FILE__, __LINE__, __FUNCTION__);
            return;
        }

        $this->_zk_connection = null; // ʹ��ZooKeeperConnection��װ����������
        $this->_root_path = null;
        $this->_inited = false;
    }

    /**
     * check�ڵ��Ƿ���
     * @param unknown $entry_path
     * @return node full path if exists or return false
     */
    public function entry_exists($entry_path)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $full_path = sprintf('%s/meta/%s', $this->_root_path, $entry_path);
        return $this->_zk_connection->exists($full_path);
    }

    /**
     * ��$root_path/meta�´���һ��zoo keeper entry (�����������parent entry)
     * @param string $entry_path : entry��·��
     * @return true on success or false on failure
     */
    public function create_entry($entry_path)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $full_path = sprintf('%s/meta/%s', $this->_root_path, $entry_path);
        if (true === $this->_zk_connection->exists($full_path))
        {
            // ���������Ѿ����ڵ�entry
            BigpipeLog::warning("[%s:%u][%s][entry already exists under meta][node path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        if (false === $this->_zk_connection->make_path($full_path, true))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to create an entry path under meta][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        return true;
    }

    /**
     * Ϊһ��meta entry��ֵ
     * @param string $entry_path : �ڵ����·���������meta��
     * @param stdobject $value  : stdclass��ʽ��value
     * @return true on success or false on failure
     */
    public function set_entry($entry_path, $value)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $full_path = sprintf('%s/meta/%s', $this->_root_path, $entry_path);
        if (false === $this->_zk_connection->exists($full_path))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to find node under meta][node path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        // serialize value
        $frame = new MetaNode();
        $node_value = $frame->serialize($value);
        if (false === $node_value)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to serialize value][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        // write value to node
        if (false === $this->_zk_connection->set($full_path, $node_value))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to set value][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        BigpipeLog::notice("[%s:%u][%s][set value to a node][path:%s]",
            __FILE__, __LINE__, __FUNCTION__, $full_path);
        return true;
    }

    /**
     * ȡ��meta entry�ĵ�ǰֵ
     * @param string $entry_path : �ڵ����·���������meta��
     * @param stdobject $value  : stdclass��ʽ��value
     * @return node value array on success or false on failure
     */
    public function get_entry($entry_path, &$stat)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $full_path = sprintf('%s/meta/%s', $this->_root_path, $entry_path);
        if (false === $this->_zk_connection->exists($full_path))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to find node under meta][node path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        // get value from node
        $node_value = $this->_zk_connection->get($full_path, $stat);
        if (false === $node_value)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to get value][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        // print notices
        if (null === $stat)
        {
            BigpipeLog::notice("[%s:%u][%s][get value from a node][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
        }
        else
        {
            BigpipeLog::notice("[%s:%u][%s][get value from a node][path:%s][ver:%u]",
                __FILE__, __LINE__, __FUNCTION__, $full_path, $stat['version']);
        }

        // deserialize node value to value array
        $frame = new MetaNode();
        $value_arr = $frame->deserialize($node_value);
        if (false === $value_arr)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to deserialize value][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        if (false === is_array($value_arr))
        {
            BigpipeLog::warning("[%s:%u][%s][deserialized value is not an array][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        return $value_arr;
    }

    /**
     * ����һ��meta entry��ֵ
     * @param string $entry_path : �ڵ����·���������meta��
     * @param stdobject $value  : stdclass��ʽ��value
     * @return node value array on success or false on failure
     */
    public function update_entry($entry_path, &$new_value, $version)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if ($version < 0)
        {
            // zookeeper��version == -1��ʾǿ�Ƹ��½ڵ�
            // �������ǲ����������ֲ���
            BigpipeLog::warning("[%s:%u][%s][invalid version][ver:%d]",
                __FILE__, __LINE__, __FUNCTION__, $version);
            return false;
        }

        $full_path = sprintf('%s/meta/%s', $this->_root_path, $entry_path);
        if (false === $this->_zk_connection->exists($full_path))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to find node under meta][node path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        // serialize value
        $frame = new MetaNode();
        $node_value = $frame->serialize($new_value);
        if (false === $node_value)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to serialize value][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        // update value in node
        if (false === $this->_zk_connection->update($full_path, $node_value, $version))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to upddate value][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        BigpipeLog::notice("[%s:%u][%s][update value in a node][path:%s]",
            __FILE__, __LINE__, __FUNCTION__, $full_path);
        return true;
    }

    /**
     * ɾ��һ��meta entry
     * @param string $entry_path : �ڵ����·�� (�����meta)
     * @return true on success or false on failure
     */
    public function delete_entry($entry_path)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $full_path = sprintf('%s/meta/%s', $this->_root_path, $entry_path);
        if (false === $this->_zk_connection->exists($full_path))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to find node under meta][node path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $full_path);
            return false;
        }

        // get value from node
        return $this->_zk_connection->remove_path($full_path);
    }

    /**
     * ����meta
     * @return true on success or false on failure
     */
    public function reconnect()
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        return $this->_zk_connection->reconnect();
    }


    /** API of zoo keeper */
    private $_zk_connection = null;
    private $_inited = null;
    private $_root_path = null;

} // end of MetaManager

/**
 * meta�ڵ�
 * @author yangzhenyu@baidu.com
 */
class MetaNode
{
    public function __construct()
    {
        $this->_version = BigpipeCommonDefine::META_HEADER_VERSION_CURRENT;
        $this->_length = 8; // Ŀǰhead length�Ǹ���ֵ
        $this->_body_len = 0;
        $this->_flags = MetaNodeStatus::NORMAL;
    }

    /**
     * ����meta node�޸ı�־����ʱ��node���ܱ����ʹ�ã�
     */
    public function lock_node()
    {
        $this->flags = MetaNodeStatus::MODIFICATION;
    }

    /**
     * ���л�node value����(json), ��д��zoo keeper
     * @param array $node : nodeֵ��ɵ�array
     * @return binary string of serialized node on success or false on failure
     */
    public function serialize($node)
    {
        if (false === is_array($node))
        {
            BigpipeLog::warning("[%s:%u][%s][unknown node value format]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $body = json_encode($node);
        if (false === $body)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to encode node value]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        $this->_body_len = strlen($body);
        // pack head
        $head = pack("C2S1L1", $this->_version, $this->_length, $this->_flags, $this->_body_len);
        $this->_content = $head . $body;
        return $this->_content;
    }

    /**
     * ��zk�ж�ȡһ��node���������л�node value����
     * @return mixed value of a node. <p>
     *         return value converted into associative arrays on success. <p>
     *         return false on failure
     */
    public function deserialize($buff)
    {
        // unpack head
        $this->_content = $buff;
        $head_arr = unpack("C1ver/C1len/S1flags/L1bdlen", $this->_content);
        $this->_version = $head_arr['ver'];
        $this->_length = $head_arr['len'];
        $this->_flags = $head_arr['flags'];
        $this->_body_len = $head_arr['bdlen'];
        $obj = json_decode(substr($this->_content, $this->_length), true);
        if (null === $obj)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to decode meta node][ver:%d][flags:%u][len:%u]",
                __FILE__, __LINE__, __FUNCTION__, $this->_version, $this->_flags, $this->_body_len);
            $obj = false;
        }
        else
        {
            BigpipeLog::notice("[%s:%u][%s][meta node header][ver:%d][flags:%u][len:%u]",
                __FILE__, __LINE__, __FUNCTION__, $this->_version, $this->_flags, $this->_body_len);
        }

        return $obj;
    }

    /** zkcͷ���ڵ�İ汾�� */
    private $_version  = null;
    /** zkcͷ��ͷ�ڵ�ĳ��� */
    private $_length   = null;
    /** zkcͷ�� ���ݳ��� */
    private $_body_len = null;
    /** zkcͷ����ʶ��ǰ�ڵ��״̬ */
    private $_flags = null;
    /** content���� */
    private $_content = null;
} // end of MetaNode

?>

