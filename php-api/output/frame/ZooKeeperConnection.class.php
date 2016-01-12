<?php
/**==========================================================================
 *
 * ZooKeeperConnection.class.php - INF / DS / BIGPIPE
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 * Created on 2012-12-19 by YANG ZHENYU (yangzhenyu@baidu.com)
 *
 * --------------------------------------------------------------------------
 *
 * Description
 *     ��zoo keeper api�ķ�װ
 *
 * --------------------------------------------------------------------------
 *
 * Change Log
 *
 *
 ==========================================================================**/
require_once(dirname(__FILE__).'/bigpipe_common.inc.php');
require_once(dirname(__FILE__).'/BigpipeLog.class.php');

/**
 * ��װ��zk api��ʵ�֣�ʹ���������
 * @author yangzhenyu@baidu.com
 *
 */
class ZooKeeperConnection
{
    /** default construct */
    public function __construct()
    {
        $this->_inited = false;
    }

    /** default destruct */
    public function __destruct()
    {
        if (true === $this->_inited)
        {
            $this->uninit();
        }
    }

    /**
     * ��ʼ����������zoo keeper������
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
        if (!isset($meta_params['meta_host']))
        {
            BigpipeLog::warning("[%s:%u][%s][no meta host]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        $this->_meta_host = $meta_params['meta_host'];
        $this->_recv_timeo = $meta_params['zk_recv_timeout'];

        // connect�ɹ�ʱ�ķ���ֵ��null
        $this->_zk = isset($this->unittest) ? $this->stub_zk : new Zookeeper;
        if (false === $this->_zk->connect($this->_meta_host, null, $this->_recv_timeo))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to connect to meta][host:%s]",
                __FILE__, __LINE__, __FUNCTION__, $this->_meta_host);
            return false;
        }

        $this->_inited = true;
        return true;
    }

    /**
     * ����ʧЧʱ����meta
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

        //bugfix:check connection first
        if(Zookeeper::CONNECTED_STATE != $this->_zk->getState())
        {
            if (false === $this->_zk->connect($this->_meta_host, null, $this->_recv_timeo))
            {
                BigpipeLog::warning("[%s:%u][%s][fail to connect to meta][host:%s]",
                    __FILE__, __LINE__, __FUNCTION__, $this->_meta_host);
                return false;
            }
        }

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

        $this->_zk = null; // zookeepû���ṩdisconnect�ӿڣ�ֻ��Ҫ���ԭ����ֵ���
        $this->_meta_host = null;
        $this->_recv_timeo = null;

        $this->_inited = false;
    }

    /**
     * meta host
     * @return meta host string
     */
    public function host()
    {
        return $this->_meta_host;
    }

    /**
     * ���·���µ�node�Ƿ����
     * @param string $node_path : node path in meta
     * @return true on success or false on failure
     */
    public function exists($node_path)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // ע�⣺��node����ʱ��exists�ķ���ֵ�ǽڵ��stat information
        // �ⲻ������ϣ���ķ���ֵ�����ǰ������Ϊtrue����
        $ret = $this->_zk->exists($node_path);
        if (false !== $ret)
        {
            return true;
        }
        return $ret;
    }

    /**
     * ��һ��meta node��ֵ������ڵ㲻����, ���ȴ�������ڵ�<p>
     * ע�⣺set����ʱ��ǿ�Ƹ��½ڵ�, ������½ڵ�, ��ʹ��update����
     * @param string $path : �ڵ�ȫ·������root��
     * @param mixed  $value : ��Ҫset��ֵ
     * @return true on success, or false on failure
     */
    public function set($path, $value)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // set value
        $ret = null;
        if (false === $this->exists($path))
        {
            BigpipeLog::notice("[%s:%u][%s][create a new node][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $path);
            if (true === $this->make_path($path, true))
            {
                $ret = $this->make_node($path, $value);
            }
        }
        else
        {
            $ret = $this->_zk->set($path, $value);
        }

        if (null === $ret || false === $ret)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to set node][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $path);
            $ret = false;
        }

        return $ret;
    }

    /**
     * ����һ��meta node��ֵ
     * @param string $path    : �ڵ�ȫ·������root��
     * @param mixed  $value   : ��Ҫ���µ�ֵ
     * @param number $version : ԭ���ڵ�İ汾
     * @return true on success, or false on failure
     */
    public function update($path, $value, $version)
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

        // set value
        $ret = null;
        if (false === $this->exists($path))
        {
            BigpipeLog::notice("[%s:%u][%s][node does not exist][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $path);
        }
        else
        {
            // get version of old node
            $ret = $this->_zk->set($path, $value, $version);
        }

        if (null === $ret)
        {
            BigpipeLog::warning("[%s:%u][%s][fail to update node][path:%s][ver:%u]",
                __FILE__, __LINE__, __FUNCTION__, $path, $version);
            $ret = false;
        }

        return $ret;
    }

    /**
     * Get the value for the node and the status
     * @param string $path : the path to the node
     * @param array &$stat : stats of the node
     * @return value string on success, null if no value and false on failure
     */
    public function get($path, &$stat = null)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (!$this->exists($path))
        {
            BigpipeLog::warning("[%s:%u][%s][no such node path][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $path);
            return false;
        }
        return $this->_zk->get($path, null, $stat);
    }

    /**
     * Equivalent of "rmdir -r" on ZooKeeper
     * @param string $path         : The path to the node
     * @param boolean $mk_parent : Whether to make parent directory as needed
     * @return bool
     */
    public function remove_path($path)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (!$this->exists($path))
        {
            return true;
        }

        $children = $this->get_children($path);
        foreach ($children as $child)
        {
            $sub_path = sprintf('%s/%s', $path, $child);
            if (false === $this->remove_path($sub_path))
            {
                return false;
            }
        }

        if (null === @ $this->_zk->delete($path))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to remvoe node path][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $path);
            return false;
        }
        return true;
    }


    /**
     * Equivalent of "mkdir" on ZooKeeper
     * @param string $path         : The path to the node
     * @param boolean $mk_parent : Whether to make parent directory as needed
     * @return bool
     */
    public function make_path($path, $mk_parent)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $subpath = null;
        $parts = explode('/', $path);
        $parts = array_filter($parts); // remove empty items
        foreach ($parts as $part)
        {
            $subpath .= '/' . $part;
            if (false === $this->exists($subpath))
            {
                if (false == $mk_parent)
                {
                    // ������ݹ鴴��path
                    BigpipeLog::warning("[%s:%u][%s][no such parent path][path:%s]",
                        __FILE__, __LINE__, __FUNCTION__, $subpath);
                    return false;
                }

                if (false === $this->make_node($path, ''))
                {
                    return false;
                }
            } // end of action when node does not exist on the path
        } // end of traverse the the folders
        return true;
    }

    /**
     * Create a node on ZooKeeper at the given path
     * @param string $path   The path to the node
     * @param string $value  The value to assign to the new node
     * @param array  $params Optional parameters for the Zookeeper node.
     *                       By default, a public node is created
     * @return true on success or false on failure
     */
    public function make_node($path, $value, array $params = array())
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (empty($params))
        {
            $params = array(
                array(
                    'perms'  => Zookeeper::PERM_ALL,
                    'scheme' => 'world',
                    'id'     => 'anyone',
                ),
            );
        }
        if (null === $this->_zk->create($path, $value, $params))
        {
            BigpipeLog::warning("[%s:%u][%s][fail to make node][path:%s]",
                __FILE__, __LINE__, __FUNCTION__, $path);
            return false;
        }
        return true;
    }

    /**
     * List the children of the given path, i.e. the name of the directories
     * within the current node, if any
     * @param string $path the path to the node
     * @return array the subpaths within the given node
     */
    public function get_children($path)
    {
        if (false === $this->_inited)
        {
            BigpipeLog::warning("[%s:%u][%s][uninited]",
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (strlen($path) > 1 && preg_match('@/$@', $path))
        {
            // remove trailing /
            $path = substr($path, 0, -1);
        }
        return $this->_zk->getChildren($path);
    }

    /** һ��zookeeper��apiʵ�� */
    private $_zk = null;
    private $_inited = null;

    private $_meta_host = null;
    private $_recv_timeo = null;
} // end of ZooKeeperConnection

?>
