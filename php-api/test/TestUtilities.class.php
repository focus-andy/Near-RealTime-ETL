<?php
/**==========================================================================
 * 
 * test/TestUtilities.class.php - INF / DS / BIGPIPE
 * 
 * Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
 * 
 * Created on 2012-12-24 by YANG ZHENYU (yangzhenyu@baidu.com)
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
require_once(dirname(__FILE__).'/../frame/BigpipeLog.class.php');

/**
 * ��װһЩtest case�еĳ��ò���
 */
class TestUtilities
{
    /**
     * ע�Ȿ�ӿ�ֻ�е�php�汾����5.3ʱ����Ч
     */
    public static function set_private_var(&$obj, $prop_name, $val)
    {
        $ret = version_compare(PHP_VERSION, '5.3.0', '>=') ? true : false;
        if (true === $ret)
        {
            // ʹ��5.3.0�Ĺ���
            $prop = new ReflectionProperty($obj, $prop_name);
            $prop->setAccessible(true); // >=php5.3����
            $prop->setValue($obj, $val);
            $ret = true;
        }
        else
        {
            BigpipeLog::warning('unsported function in PHP < 5.3.0');
        }

        return $ret;
    }

    /**
     * ע�Ȿ�ӿ�ֻ�е�php�汾����5.3ʱ����Ч
     * �����޸�ĳ������ĸ����һ��˽������
     */
    public static function set_parent_private_var(&$obj, $prop_name, $val)
    {
        $ret = version_compare(PHP_VERSION, '5.3.0', '>=') ? true : false;
        if (true === $ret)
        {
            // ʹ��5.3.0�Ĺ���
            $cls = new ReflectionClass($obj);
            $parent = $cls->getParentClass();
            $prop = $parent->getProperty($prop_name);
            $prop->setAccessible(true); // >=php5.3����
            $prop->setValue($obj, $val);
            $ret = true;
        }
        else
        {
            BigpipeLog::warning('unsported function in PHP < 5.3.0');
        }

        return $ret;
    }

    /**
     * ע�Ȿ�ӿ�ֻ�е�php�汾����5.3ʱ����Ч
     */
    public static function get_private_var(&$obj, $prop_name)
    {
        $ret = version_compare(PHP_VERSION, '5.3.0', '>=') ? true : false;
        if (true === $ret)
        {
            // ʹ��5.3.0�Ĺ���
            $prop = new ReflectionProperty($obj, $prop_name);
            $prop->setAccessible(true); // >=php5.3����
            $val = $prop->getValue($obj);
            if (null === $val)
            {
                $ret = false;
            }
            else
            {
                $ret = $val;
            }
        }
        else
        {
            BigpipeLog::warning('unsported function in PHP < 5.3.0');
        }

        return $ret;
    }

    public static function get_private_method(&$obj, $method_name)
    {
        $ret = version_compare(PHP_VERSION, '5.3.0', '>=') ? true : false;

        if (true === $ret)
        {
            // ʹ��5.3.0�Ĺ���
            $method = new ReflectionMethod($obj, $method_name);
            $method->setAccessible(true); // >=php5.3����
            return $method;
        }
        else
        {
            BigpipeLog::warning('unsported function in PHP < 5.3.0');
        }

        return $ret;
    }
} // end of TestUtilities 

/**
 * ���ڲ���sendʱ���쳣��֧
 */
class FakeFrame
{
    public function store()
    {
        return 0;
    }

    public function last_error_message()
    {
        return 'only for test';
    }
} // end of FakeFrame

/* vim: set ts=4 sw=4 sts=4 tw=100 : */
?>
