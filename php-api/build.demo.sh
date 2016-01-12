
#这里演示了使用php 动态库做扩展,的方法
#我们认为您已经安装了php
#如果没有请自行安装php

#工作目录
WORKROOT=../../../..
#php 路径
#PHP_SRC=/local/php/include/php
PHP_SRC=/home/iknow/php2/include/php
#public/php-ex的路径
PHP_EXPATH=$WORKROOT/public/php-ex

#这里加入需要使用的扩展，这里加上php-mcpack, 如果有多个可以加多个,用空格隔开，最后再加上""如
#PHP_EX="php-fcrypt php-mcpack"
PHP_EX="php-mcpack php-sign php-config php-sockets"
#PHP_EX="php-sockets"
#指定扩展的.so最后存放的位置
OUTPUT=./output/extension


WORKPATH=$PWD

#判断php是否存在
if [ ! -d $PHP_SRC ]; then
  echo "build error, no found php src $PHP_SRC"
  exit;
fi

cd $WORKPATH
if [ ! -d $OUTPUT ]; then
  mkdir -p $OUTPUT
else
  #目录存在，先清空目录
  rm -f $OUTPUT
fi

#对每个php 扩展进行编译，并把结果的.so拷到输出目录下
for modules in $PHP_EX;
do
  #编译
  cd $PHP_EXPATH/$modules
  make PHP_SRC=$PHP_SRC
  cd $WORKPATH
  #复制到目标位置
  cp $PHP_EXPATH/$modules/output/lib/*.so* $OUTPUT
done

#在编译php-mcpack时可能遇到以下几个问题, 此时需要人工干预:
#问题1: bsl代码路径不对
#原因: 多出现于64位机器。如果本机$uname -i结果为unkonw则, 则mc-pack无法自动获取bsl库正确路径
#解决: 进入php-mcpack目录，修改Makefile文件，注释ifeq ($(shell uname -i),x86_64)条件判断.                                                          
#问题2: 出现错误提示 error: `tsrm_ls' was not declared in this scope
#原因: 代码不兼容php thread-safe模式
#解决: 手动修改 php_mc_pack.cpp 文件，在调用HASH_OF宏之前都添加宏TSRMLS_FETCH()

#编译zookeeper
PHP_ZKPATH=$WORKROOT/app/search/ksarch/commit/php-zookeeper
if [ ! -d $PHP_ZKPATH ]; then
  echo "build error, no found php zookeeper path $PHP_ZKPATH"
  exit
fi

#编译php zookeeper
cd $PHP_ZKPATH
$PHP_SRC/../../bin/phpize
./configure --with-libzookeeper-dir=./zk --with-php-config=$PHP_SRC/../../bin/php-config 
make
make test
## install后so会被cp到php/extension目录下
make install

#完成后执行以下几步
#1. 将output/extension下的so文件move到php的extension目录下
#php的extension目录通常在php/lib/php/extensia1o
#2. 修改php.ini文件，添加extension=xxx.so (xxx.so为output/lib下的so文件名)
#php.ini文件通常在php/lib目录下

