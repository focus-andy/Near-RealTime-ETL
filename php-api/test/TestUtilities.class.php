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
 * 封装一些test case中的常用操作
 */
class TestUtilities
{
    /**
     * 注意本接口只有当php版本大于5.3时才有效
     */
    public static function set_private_var(&$obj, $prop_name, $val)
    {
        $ret = version_compare(PHP_VERSION, '5.3.0', '>=') ? true : false;
        if (true === $ret)
        {
            // 使用5.3.0的功能
            $prop = new ReflectionProperty($obj, $prop_name);
            $prop->setAccessible(true); // >=php5.3可用
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
     * 注意本接口只有当php版本大于5.3时才有效
     * 用于修改某个对象的父类的一个私有属性
     */
    public static function set_parent_private_var(&$obj, $prop_name, $val)
    {
        $ret = version_compare(PHP_VERSION, '5.3.0', '>=') ? true : false;
        if (true === $ret)
        {
            // 使用5.3.0的功能
            $cls = new ReflectionClass($obj);
            $parent = $cls->getParentClass();
            $prop = $parent->getProperty($prop_name);
            $prop->setAccessible(true); // >=php5.3可用
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
     * 注意本接口只有当php版本大于5.3时才有效
     */
    public static function get_private_var(&$obj, $prop_name)
    {
        $ret = version_compare(PHP_VERSION, '5.3.0', '>=') ? true : false;
        if (true === $ret)
        {
            // 使用5.3.0的功能
            $prop = new ReflectionProperty($obj, $prop_name);
            $prop->setAccessible(true); // >=php5.3可用
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
            // 使用5.3.0的功能
            $method = new ReflectionMethod($obj, $method_name);
            $method->setAccessible(true); // >=php5.3可用
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
 * 用于测试send时的异常分支
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
