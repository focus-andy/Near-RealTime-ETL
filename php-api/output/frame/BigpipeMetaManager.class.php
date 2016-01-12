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
 *     管理一个zookeeper连接的实例，提供接口供用户操作bigpipe meta
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
 * 管理一个zookeepr connection的实例<p>
 * 封装了对meta的操作
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
     * 建立与zoo keeper的连接
     * @param unknown $meta_params
     */
    public function init($meta_params)
    {
        if (true === $this->_inited)
        {
            // 不允许multi-init
            BigpipeLog::warning("[%s:%u][%s][multi-init]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 检查meta配置
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
     * 反初始化
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

        $this->_zk_connection = null; // 使用ZooKeeperConnection封装的析构函数
        $this->_root_path = null;
        $this->_inited = false;
    }

    /**
     * check节点是否在
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
     * 在$root_path/meta下创建一个zoo keeper entry (允许迭代创建parent entry)
     * @param string $entry_path : entry的路径
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
            // 不允许创建已经存在的entry
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
     * 为一个meta entry赋值
     * @param string $entry_path : 节点相对路径（相对于meta）
     * @param stdobject $value  : stdclass格式的value
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
     * 取得meta entry的当前值
     * @param string $entry_path : 节点相对路径（相对于meta）
     * @param stdobject $value  : stdclass格式的value
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
     * 更新一个meta entry的值
     * @param string $entry_path : 节点相对路径（相对于meta）
     * @param stdobject $value  : stdclass格式的value
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
            // zookeeper中version == -1表示强制更新节点
            // 这里我们不允许有这种操作
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
     * 删除一个meta entry
     * @param string $entry_path : 节点相对路径 (相对于meta)
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
     * 重连meta
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
 * meta节点
 * @author yangzhenyu@baidu.com
 */
class MetaNode
{
    public function __construct()
    {
        $this->_version = BigpipeCommonDefine::META_HEADER_VERSION_CURRENT;
        $this->_length = 8; // 目前head length是个定值
        $this->_body_len = 0;
        $this->_flags = MetaNodeStatus::NORMAL;
    }

    /**
     * 设置meta node修改标志（此时的node不能被外界使用）
     */
    public function lock_node()
    {
        $this->flags = MetaNodeStatus::MODIFICATION;
    }

    /**
     * 序列化node value数组(json), 并写入zoo keeper
     * @param array $node : node值组成的array
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
     * 从zk中读取一个node并，反序列化node value数组
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

    /** zkc头：节点的版本号 */
    private $_version  = null;
    /** zkc头：头节点的长度 */
    private $_length   = null;
    /** zkc头： 内容长度 */
    private $_body_len = null;
    /** zkc头：标识当前节点的状态 */
    private $_flags = null;
    /** content内容 */
    private $_content = null;
} // end of MetaNode

?>

