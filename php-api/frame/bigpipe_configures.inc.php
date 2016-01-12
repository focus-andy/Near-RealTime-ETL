<?php
/**==========================================================================
 *
 * bigpipe_configures.inc.php - INF / DS / BIGPIPE
 *
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 *
 * Created on 2012-12-14 by YANG ZHENYU (yangzhenyu@baidu.com)
 *
 * --------------------------------------------------------------------------
 *
 * Description
 *     bigpipe php-api中用到的configure files的定义
 *
 * --------------------------------------------------------------------------
 *
 * Change Log
 *
 *
 ==========================================================================**/
require_once(dirname(__FILE__).'/BigpipeLog.class.php');

/**
 * 从configure file中读取配置并写入conf对象中<p>
 * @param string $conf_dir : directory of configure file
 * @param string $conf_file: name of configure file
 * @param object &$conf: 提供了load($arr)方法的对象
 * @return true on success or false on failure
 */
function bigpipe_load_file($conf_dir, $conf_file, &$conf)
{
    $content = config_load($conf_dir, $conf_file);
    if (false === $content)
    {
        BigpipeLog::warning('[%s:%u][%s][fail to load config file][path:%s][file:%s]',
            __FILE__, __LINE__, __FUNCTION__, $conf_dir, $conf_file);
        return false;
    }
    return $conf->load($content);
}

/**
 * php-api publisher和subscriber共用的配置
 * @author yangzhenyu@baidu.com
 */
class BigpipeConf
{
    /**
     * bigpipe stomp adapter的配置
     * @var BigpipeStompConf
     */
    public $stomp_conf = null;

    /**
     * meta agent adapter的配置
     * @var MetaAgentConf
     */
    public $meta_conf = null;

    /** 连接broker超时 , 单位: 毫秒*/
    public $conn_timo = 60;
    /** 最大failover重试次数 */
    public $max_failover_cnt = 5;
    /** 是否开启去重 : 0 去重, 1 不去重 */
    public $no_dedupe = 0;
    /** 校验级别（注意：broker1不支持level=3） : 0 无校验, 1 校验message body数字签名, 2 (已废弃不用), 3 做整包校验*/
    public $checksum_level = 1;
    /** 订阅端订阅对象的偏好(可按位叠加)：1 只连接主 2 只连接从*/
    public $prefer_conn = 3;
    /** session级别: 0 自动生成; 1 从meta-agent获取*/
    public $session_level = 1; // 目前只有publisher::init_ex接口支持1
    /**
     * 默认构造函数
     */
    public function __construct()
    {
        $this->stomp_conf = new BigpipeStompConf;
        $this->meta_conf = new MetaAgentConf;
    }

    /**
     * 从configure array中读取配置
     * @param array $conf_arr
     * @return true on success or false on failure
     */
    public function load($conf_arr)
    {
        if (false === $this->_load_stomp($conf_arr)
            || false === $this->_load_meta($conf_arr))
        {
            return false;
        }

        // 读取其它配置
        return $this->_load_other($conf_arr);
    }

    /**
     * 从传入的configure array读取stomp配置
     * @param array $conf_arr: 含stomp配置的array
     * @return true on success or false on failure
     */
    private function _load_stomp($conf_arr)
    {
        $node = 'stomp';
        if (false === isset($conf_arr[$node])
            || false === is_array($conf_arr[$node]))
        {
            BigpipeLog::warning('[%s:%u][%s][missing "stomp" in configure]',
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        if (false === $this->stomp_conf->load($conf_arr[$node]))
        {
            BigpipeLog::warning('[%s:%u][%s][fail to load node][name:%s]',
                __FILE__, __LINE__, __FUNCTION__, $node);
            return false;
        }
        return true;
    }

    /**
     * 从传入的configure array读取meta agent配置
     * @param array $conf_arr: 含meta agent配置的array
     * @return true on success or false on failure
     */
    private function _load_meta($conf_arr)
    {
        $node = 'meta_agent';
        if (false === isset($conf_arr[$node])
            || false === is_array($conf_arr[$node]))
        {
            BigpipeLog::warning('[%s:%u][%s][missing "meta_agent" in stomp]',
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }
        if (false === $this->meta_conf->load($conf_arr[$node]))
        {
            BigpipeLog::warning('[%s:%u][%s][fail to load node][name:%s]',
                __FILE__, __LINE__, __FUNCTION__, $node);
            return false;
        }
        return true;
    }

    /**
     * 从传入的configure array读取剩余配置
     * @param array $conf_arr: 含meta agent配置的array
     * @return true on success or false on failure
     */
    private function _load_other($conf_arr)
    {
        try
        {
            $obj = new ReflectionClass($this);
            foreach($conf_arr as $key => $val)
            {
                if (true === is_array($val))
                {
                    BigpipeLog::debug('[%s:%u][%s][skip configure node][name:%s]',
                        __FILE__, __LINE__, __FUNCTION__, $key);
                    continue;
                }

                if (false === $obj->hasProperty($key))
                {
                    continue;
                }

                BigpipeLog::debug('[%s:%u][%s][an element is configured][key:%s][val:%s]',
                    __FILE__, __LINE__, __FUNCTION__, $key, $val);
                $fld = $obj->getProperty($key);
                $fld->setValue($this, $val);
            }
        }
        catch(Exception $e)
        {
            BigpipeLog::warning('[%s:%u][%s][fail to load configure][error message:%s]',
                __FILE__, __LINE__, __FUNCTION__, $e->getMessage());
            return false;
        }

        // todo
        // 根据checksum level修改check frame标志现在在publish和subscirbe中
        // 以后应该移到这里
        return true;
    }
} // BigpipeConf

/**
 * queue client的配置文件
 * @author yangzhenyu@baidu.com
 */
class BigpipeQueueConf
{
    /**
     * 初始化时定义配置
     */
    public function __construct()
    {
        // 初始化meta配置
        // todo set zookeeper log file
        $this->meta = array(
            'meta_host'       => '10.218.32.11:2181,10.218.32.20:2181,10.218.32.21:2181,10.218.32.22:2181,10.218.32.23:2181',
            'root_path'       => '/bigpipe_pvt_cluster3',
            'zk_recv_timeout' => 30000,
            'zk_log_file'     => './log/queue.zk.log',
            'zk_log_level'    => 4,
        );

        // 初始化connection配置
        $this->conn = new BigpipeConnectionConf;

        // 初始化queue client配置
        $this->wnd_size = 10;
        $this->rw_timeo = 1000;
        $this->peek_timeo = 60000; // 60s
        $this->delay_ratio = 0.9;
        $this->sleep_timeo = 30000; //30ms

    }

    /**
     * 从com-configure解析出的array中读取queue client的配置
     */
    public function load($conf_arr)
    {
        // 检查配置完整性
        if (false === isset($conf_arr['meta'])
            || false === isset($conf_arr['queue']))
        {
            BigpipeLog::warning('[%s:%u][%s][missing mandatory key in configure]',
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (false === $this->_load_meta($conf_arr['meta']))
        {
            return false;
        }

        if (false === $this->_load_queue($conf_arr['queue']))
        {
            return false;
        }

        return true;
    }

    /**
     * 从configure array中读取queue的配置
     * @param array $queue_conf : [queue]下的配置节点
     * @return true on success or false on failure
     */
    private function _load_queue($queue_conf)
    {
        if (isset($queue_conf['window_size']))
        {
            $this->wnd_size = $queue_conf['window_size'];
        }

        if (isset($queue_conf['peek_timeo']))
        {
            $this->peek_timeo = $queue_conf['peek_timeo'];
        }

        // 配置read/write timeout
        if (isset($queue_conf['rw_timeo']))
        {
            $this->rw_timeo = $queue_conf['rw_timeo'];
        }

        // 配置daly_ratio
        if (isset($queue_conf['delay_ratio']))
        {
            $this->delay_ratio = $queue_conf['delay_ratio'];
        }

        // 配置sleep_timeo
        if (isset($queue_conf['sleep_timeo']))
        {
            $this->sleep_timeo = $queue_conf['sleep_timeo']*1000;
        }

        // connection配置也从queue的配置下读取
        if (false === $this->conn->load($queue_conf))
        {
            BigpipeLog::fatal('[queue client configure][error when configure socket connection]');
            return false;
        }

        return true;
    }

    private function _load_meta($meta_conf)
    {
        // 检查必须的配置项
        if (false === isset($meta_conf['meta_host'])
            || false === isset($meta_conf['root_path'])
            || false === isset($meta_conf['zk_recv_timeout']))
        {
            BigpipeLog::fatal('[queue client configure][missing mandatory meta configure]');
            return false;
        }

        $this->meta = $meta_conf;
        return true;
    }

    /** meta配置文件 */
    public $meta = null;
    /** bigpipe connection配置 */
    public $conn = null;
    /** queue server 窗口大小 */
    public $wnd_size = null;
    /** queue server 读写time out (单位： 毫秒)*/
    public $rw_timeo = null;
    /** queue server peek time out */
    public $peek_timeo = null;
    /** 消息延时本地汇率，0-1，建议0.9 */
    public $delay_ratio = null;
} // end of BigpipeQueueConf

/**
 * BigpipeStompAdapter的配置
 * @author: yangzhenyu@baidu.com
 */
class BigpipeStompConf extends BigpipeConfiguration
{
    /**
     * connect的参数配置
     * @var BigpipeConnectionConf
     */
    public $conn_conf = null;

    /**
     * peek 等待超时，单位(毫秒)<p>
     * 值为零，表示不考虑time out
     */
    public $peek_timeo = 0;

    /** 初始化函数 */
    public function __construct()
    {
        $this->conn_conf = new BigpipeConnectionConf;
    }

    /**
     * 从configure array中读取配置
     * @param array $conf_arr
     * @return true on success or false on failure
     */
    public function load($conf_arr)
    {
        if (false === isset($conf_arr['peek_timeo']))
        {
            BigpipeLog::warning('[%s:%u][%s][missing "peek_timeo" in stomp]',
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        if (false === isset($conf_arr['connection'])
            || false === is_array($conf_arr['connection']))
        {
            BigpipeLog::warning('[%s:%u][%s][missing "connection" in stomp]',
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        // 读取connection配置
        return $this->conn_conf->load($conf_arr['connection']);
    }
} // end of BigpipeStompConf

/**
 * MetaAgentAdapter的配置
 * @author: zhyyang@baidu.com
 */
class MetaAgentConf extends BigpipeConfiguration
{
    /**
     * connect的参数配置
     * @var BigpipeConnectionConf
     */
    public $conn_conf = null;

    /**
     * meta模块的配置<p>
     * meta_conf中time out的时间单位都是: 毫秒
     * @var array
     */
    public $meta = null;

    /**
     * meta agent地址列表
     * 一个agent地址有以下成员:
     * socket_address: meta agent的ip
     * socket_port   : meta agent监听端口
     * socket_timeout: 与meta agent的连接超时（单位：秒）
     * @var array
     */
    public $agents = null;

    /**
     * 初始化函数
     */
    public function __construct()
    {
        $this->conn_conf = new BigpipeConnectionConf;
    }

    /**
     * 从配置文件中读取配置
     * @return true on success or false on failure
     */
    public function load($conf_arr)
    {
        $ret = false;
        do
    {
        // load meta
        if (false === isset($conf_arr['meta'])
            || false === is_array($conf_arr['meta']))
        {
            BigpipeLog::warning('[%s:%u][%s][missing "meta" in meta_agent]',
                __FILE__, __LINE__, __FUNCTION__);
            break;
        }

        if (false === $this->_load_meta($conf_arr['meta']))
        {
            break;
        }

        // load agent
        if (false === isset($conf_arr['agent'])
            || false === is_array($conf_arr['agent']))
        {
            BigpipeLog::warning('[%s:%u][%s][missing "agent" in meta_agent]',
                __FILE__, __LINE__, __FUNCTION__);
            break;
        }
        else
        {
            $this->agents = $conf_arr['agent'];
        }

        if (false === isset($conf_arr['connection']))
        {
            BigpipeLog::warning('[%s:%u][%s][missing "connection" in meta_agent]',
                __FILE__, __LINE__, __FUNCTION__);
            break;
        }

        // 读取connection配置
        $ret = $this->conn_conf->load($conf_arr['connection']);
    } while(false);

        return $ret;
    }

    private function _load_meta($meta_arr)
    {
        $meta_node = new BigpipeMetaConf;
        if (false === $meta_node->load($meta_arr))
        {
            BigpipeLog::fatal('[%s:%u][%s][fail to load meta "meta" in configure]',
                __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $this->meta = $meta_node->to_array();
        return true;
    }
} // end of MetaAgentConf

/**
 * BigpipeMetaConf的配置
 * @author: yangzhenyu@baidu.com
 */
class BigpipeMetaConf extends BigpipeConfiguration
{
    public $meta_host = null;
    public $root_path = null;
    public $max_cache_count = 100000;
    public $watcher_timeout = 10000;
    public $setting_timeout = 15000;
    public $recv_timeout    = 30000;
    public $max_value_size  = 10240000;
    public $zk_log_level    = 3;
    public $reinit_register_random = 1;

    /**
     * 将meta配置转为meta array
     * @return array
     */
    public function to_array()
    {
        return (array)$this;
    }

    /**
     * 从配置文件中读取配置
     * @return true on success or false on failure
     */
    public function load($conf_arr)
    {
        return self::_array_to_object($conf_arr, $this);
    }
}

/**
 * BigpipeConnection的配置
 * @author: yangzhenyu@baidu.com
 */
class BigpipeConnectionConf extends BigpipeConfiguration
{
    /** 最大尝试连接次数 */
    public $try_time = 1;
    /** 连接超时, 单位: 毫秒 */
    public $conn_timeo = 5000;
    /** 等待读超时，单位: 毫秒 */
    public $read_timeo = 5000;
    /** socket 保持时间, 单位: 秒，默认无时限（0）*/
    public $time_limit = 0;
    /** 是否要做整包校验 (见checksum level定义) */
    public $check_frame = false; 

    /**
     * 从配置文件中读取配置
     * @return true on success or false on failure
     */
    public function load($conf_arr)
    {
        return self::_array_to_object($conf_arr, $this);
    }
} // end of BigpipeConnectionConf

/**
 * 提供读取配置文件，和快速创建配置的方法<p>
 * 使用方法: <p>
 * 继承本类并实现__construct方法, 在__construct中定义配置项。<p>
 * 必须通过配置文件读取的配置项, 请定义为null类型
 * @author yangzhenyu@baidu.com
 */
class BigpipeConfiguration
{


    /**
     * 从config array中读取配置<p>
     * 不支持嵌套config array, 既config中嵌套config array
     * @param array $conf_arr
     * @return true on success or false on failure
     */
    protected static function _array_to_object($conf_arr, &$elem)
    {
        try
        {
            $obj = new ReflectionClass($elem);
            $fields = $obj->getProperties();
            foreach ($fields as $fld)
            {
                if (isset($conf_arr[$fld->name]))
                {
                    $val = $conf_arr[$fld->name];
                    if (true === is_array($val))
                    {
                        // 不允许用本函数给配置项赋一个array
                        BigpipeLog::warning('[%s:%u][%s][can set an array to the config element][name:%s]',
                            __FILE__, __LINE__, __FUNCTION__, $fld->name);
                        return false;
                    }
                    else
                    {
                        $fld->setValue($elem, $val);
                    }
                } // end of set a field
                else
                {
                    // 检查是否有默认配置
                    $val = $fld->getValue($elem);
                    if (null === $val)
                    {
                        // 配置没有默认值，必须在配置文件里给出
                        BigpipeLog::warning('[%s:%u][%s][missing configure item][name:%s]',
                            __FILE__, __LINE__, __FUNCTION__, $fld->name);
                        return false;
                    }
                } // end of check defulat value
            } // end of traverse each fields of 'this' object
        }
        catch(Exception $e)
        {
            BigpipeLog::warning('[%s:%u][%s][fail to load configure][error message:%s]', 
                __FILE__, __LINE__, __FUNCTION__, $e->getMessage());
            return false;
        }
        return true;
    }
} // end of BigpipeConfiguration
?>
