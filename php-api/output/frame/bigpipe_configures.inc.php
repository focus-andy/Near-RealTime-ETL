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
 *     bigpipe php-api���õ���configure files�Ķ���
 *
 * --------------------------------------------------------------------------
 *
 * Change Log
 *
 *
 ==========================================================================**/
require_once(dirname(__FILE__).'/BigpipeLog.class.php');

/**
 * ��configure file�ж�ȡ���ò�д��conf������<p>
 * @param string $conf_dir : directory of configure file
 * @param string $conf_file: name of configure file
 * @param object &$conf: �ṩ��load($arr)�����Ķ���
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
 * php-api publisher��subscriber���õ�����
 * @author yangzhenyu@baidu.com
 */
class BigpipeConf
{
    /**
     * bigpipe stomp adapter������
     * @var BigpipeStompConf
     */
    public $stomp_conf = null;

    /**
     * meta agent adapter������
     * @var MetaAgentConf
     */
    public $meta_conf = null;

    /** ����broker��ʱ , ��λ: ����*/
    public $conn_timo = 60;
    /** ���failover���Դ��� */
    public $max_failover_cnt = 5;
    /** �Ƿ���ȥ�� : 0 ȥ��, 1 ��ȥ�� */
    public $no_dedupe = 0;
    /** У�鼶��ע�⣺broker1��֧��level=3�� : 0 ��У��, 1 У��message body����ǩ��, 2 (�ѷ�������), 3 ������У��*/
    public $checksum_level = 1;
    /** ���Ķ˶��Ķ����ƫ��(�ɰ�λ����)��1 ֻ������ 2 ֻ���Ӵ�*/
    public $prefer_conn = 3;
    /** session����: 0 �Զ�����; 1 ��meta-agent��ȡ*/
    public $session_level = 1; // Ŀǰֻ��publisher::init_ex�ӿ�֧��1
    /**
     * Ĭ�Ϲ��캯��
     */
    public function __construct()
    {
        $this->stomp_conf = new BigpipeStompConf;
        $this->meta_conf = new MetaAgentConf;
    }

    /**
     * ��configure array�ж�ȡ����
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

        // ��ȡ��������
        return $this->_load_other($conf_arr);
    }

    /**
     * �Ӵ����configure array��ȡstomp����
     * @param array $conf_arr: ��stomp���õ�array
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
     * �Ӵ����configure array��ȡmeta agent����
     * @param array $conf_arr: ��meta agent���õ�array
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
     * �Ӵ����configure array��ȡʣ������
     * @param array $conf_arr: ��meta agent���õ�array
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
        // ����checksum level�޸�check frame��־������publish��subscirbe��
        // �Ժ�Ӧ���Ƶ�����
        return true;
    }
} // BigpipeConf

/**
 * queue client�������ļ�
 * @author yangzhenyu@baidu.com
 */
class BigpipeQueueConf
{
    /**
     * ��ʼ��ʱ��������
     */
    public function __construct()
    {
        // ��ʼ��meta����
        // todo set zookeeper log file
        $this->meta = array(
            'meta_host'       => '10.218.32.11:2181,10.218.32.20:2181,10.218.32.21:2181,10.218.32.22:2181,10.218.32.23:2181',
            'root_path'       => '/bigpipe_pvt_cluster3',
            'zk_recv_timeout' => 30000,
            'zk_log_file'     => './log/queue.zk.log',
            'zk_log_level'    => 4,
        );

        // ��ʼ��connection����
        $this->conn = new BigpipeConnectionConf;

        // ��ʼ��queue client����
        $this->wnd_size = 10;
        $this->rw_timeo = 1000;
        $this->peek_timeo = 60000; // 60s
        $this->delay_ratio = 0.9;
        $this->sleep_timeo = 30000; //30ms

    }

    /**
     * ��com-configure��������array�ж�ȡqueue client������
     */
    public function load($conf_arr)
    {
        // �������������
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
     * ��configure array�ж�ȡqueue������
     * @param array $queue_conf : [queue]�µ����ýڵ�
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

        // ����read/write timeout
        if (isset($queue_conf['rw_timeo']))
        {
            $this->rw_timeo = $queue_conf['rw_timeo'];
        }

        // ����daly_ratio
        if (isset($queue_conf['delay_ratio']))
        {
            $this->delay_ratio = $queue_conf['delay_ratio'];
        }

        // ����sleep_timeo
        if (isset($queue_conf['sleep_timeo']))
        {
            $this->sleep_timeo = $queue_conf['sleep_timeo']*1000;
        }

        // connection����Ҳ��queue�������¶�ȡ
        if (false === $this->conn->load($queue_conf))
        {
            BigpipeLog::fatal('[queue client configure][error when configure socket connection]');
            return false;
        }

        return true;
    }

    private function _load_meta($meta_conf)
    {
        // �������������
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

    /** meta�����ļ� */
    public $meta = null;
    /** bigpipe connection���� */
    public $conn = null;
    /** queue server ���ڴ�С */
    public $wnd_size = null;
    /** queue server ��дtime out (��λ�� ����)*/
    public $rw_timeo = null;
    /** queue server peek time out */
    public $peek_timeo = null;
    /** ��Ϣ��ʱ���ػ��ʣ�0-1������0.9 */
    public $delay_ratio = null;
} // end of BigpipeQueueConf

/**
 * BigpipeStompAdapter������
 * @author: yangzhenyu@baidu.com
 */
class BigpipeStompConf extends BigpipeConfiguration
{
    /**
     * connect�Ĳ�������
     * @var BigpipeConnectionConf
     */
    public $conn_conf = null;

    /**
     * peek �ȴ���ʱ����λ(����)<p>
     * ֵΪ�㣬��ʾ������time out
     */
    public $peek_timeo = 0;

    /** ��ʼ������ */
    public function __construct()
    {
        $this->conn_conf = new BigpipeConnectionConf;
    }

    /**
     * ��configure array�ж�ȡ����
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

        // ��ȡconnection����
        return $this->conn_conf->load($conf_arr['connection']);
    }
} // end of BigpipeStompConf

/**
 * MetaAgentAdapter������
 * @author: zhyyang@baidu.com
 */
class MetaAgentConf extends BigpipeConfiguration
{
    /**
     * connect�Ĳ�������
     * @var BigpipeConnectionConf
     */
    public $conn_conf = null;

    /**
     * metaģ�������<p>
     * meta_conf��time out��ʱ�䵥λ����: ����
     * @var array
     */
    public $meta = null;

    /**
     * meta agent��ַ�б�
     * һ��agent��ַ�����³�Ա:
     * socket_address: meta agent��ip
     * socket_port   : meta agent�����˿�
     * socket_timeout: ��meta agent�����ӳ�ʱ����λ���룩
     * @var array
     */
    public $agents = null;

    /**
     * ��ʼ������
     */
    public function __construct()
    {
        $this->conn_conf = new BigpipeConnectionConf;
    }

    /**
     * �������ļ��ж�ȡ����
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

        // ��ȡconnection����
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
 * BigpipeMetaConf������
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
     * ��meta����תΪmeta array
     * @return array
     */
    public function to_array()
    {
        return (array)$this;
    }

    /**
     * �������ļ��ж�ȡ����
     * @return true on success or false on failure
     */
    public function load($conf_arr)
    {
        return self::_array_to_object($conf_arr, $this);
    }
}

/**
 * BigpipeConnection������
 * @author: yangzhenyu@baidu.com
 */
class BigpipeConnectionConf extends BigpipeConfiguration
{
    /** ��������Ӵ��� */
    public $try_time = 1;
    /** ���ӳ�ʱ, ��λ: ���� */
    public $conn_timeo = 5000;
    /** �ȴ�����ʱ����λ: ���� */
    public $read_timeo = 5000;
    /** socket ����ʱ��, ��λ: �룬Ĭ����ʱ�ޣ�0��*/
    public $time_limit = 0;
    /** �Ƿ�Ҫ������У�� (��checksum level����) */
    public $check_frame = false; 

    /**
     * �������ļ��ж�ȡ����
     * @return true on success or false on failure
     */
    public function load($conf_arr)
    {
        return self::_array_to_object($conf_arr, $this);
    }
} // end of BigpipeConnectionConf

/**
 * �ṩ��ȡ�����ļ����Ϳ��ٴ������õķ���<p>
 * ʹ�÷���: <p>
 * �̳б��ಢʵ��__construct����, ��__construct�ж��������<p>
 * ����ͨ�������ļ���ȡ��������, �붨��Ϊnull����
 * @author yangzhenyu@baidu.com
 */
class BigpipeConfiguration
{


    /**
     * ��config array�ж�ȡ����<p>
     * ��֧��Ƕ��config array, ��config��Ƕ��config array
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
                        // �������ñ������������һ��array
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
                    // ����Ƿ���Ĭ������
                    $val = $fld->getValue($elem);
                    if (null === $val)
                    {
                        // ����û��Ĭ��ֵ�������������ļ������
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
