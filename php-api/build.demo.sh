
#������ʾ��ʹ��php ��̬������չ,�ķ���
#������Ϊ���Ѿ���װ��php
#���û�������а�װphp

#����Ŀ¼
WORKROOT=../../../..
#php ·��
#PHP_SRC=/local/php/include/php
PHP_SRC=/home/iknow/php2/include/php
#public/php-ex��·��
PHP_EXPATH=$WORKROOT/public/php-ex

#���������Ҫʹ�õ���չ���������php-mcpack, ����ж�����ԼӶ��,�ÿո����������ټ���""��
#PHP_EX="php-fcrypt php-mcpack"
PHP_EX="php-mcpack php-sign php-config php-sockets"
#PHP_EX="php-sockets"
#ָ����չ��.so����ŵ�λ��
OUTPUT=./output/extension


WORKPATH=$PWD

#�ж�php�Ƿ����
if [ ! -d $PHP_SRC ]; then
  echo "build error, no found php src $PHP_SRC"
  exit;
fi

cd $WORKPATH
if [ ! -d $OUTPUT ]; then
  mkdir -p $OUTPUT
else
  #Ŀ¼���ڣ������Ŀ¼
  rm -f $OUTPUT
fi

#��ÿ��php ��չ���б��룬���ѽ����.so�������Ŀ¼��
for modules in $PHP_EX;
do
  #����
  cd $PHP_EXPATH/$modules
  make PHP_SRC=$PHP_SRC
  cd $WORKPATH
  #���Ƶ�Ŀ��λ��
  cp $PHP_EXPATH/$modules/output/lib/*.so* $OUTPUT
done

#�ڱ���php-mcpackʱ�����������¼�������, ��ʱ��Ҫ�˹���Ԥ:
#����1: bsl����·������
#ԭ��: �������64λ�������������$uname -i���Ϊunkonw��, ��mc-pack�޷��Զ���ȡbsl����ȷ·��
#���: ����php-mcpackĿ¼���޸�Makefile�ļ���ע��ifeq ($(shell uname -i),x86_64)�����ж�.                                                          
#����2: ���ִ�����ʾ error: `tsrm_ls' was not declared in this scope
#ԭ��: ���벻����php thread-safeģʽ
#���: �ֶ��޸� php_mc_pack.cpp �ļ����ڵ���HASH_OF��֮ǰ����Ӻ�TSRMLS_FETCH()

#����zookeeper
PHP_ZKPATH=$WORKROOT/app/search/ksarch/commit/php-zookeeper
if [ ! -d $PHP_ZKPATH ]; then
  echo "build error, no found php zookeeper path $PHP_ZKPATH"
  exit
fi

#����php zookeeper
cd $PHP_ZKPATH
$PHP_SRC/../../bin/phpize
./configure --with-libzookeeper-dir=./zk --with-php-config=$PHP_SRC/../../bin/php-config 
make
make test
## install��so�ᱻcp��php/extensionĿ¼��
make install

#��ɺ�ִ�����¼���
#1. ��output/extension�µ�so�ļ�move��php��extensionĿ¼��
#php��extensionĿ¼ͨ����php/lib/php/extensia1o
#2. �޸�php.ini�ļ������extension=xxx.so (xxx.soΪoutput/lib�µ�so�ļ���)
#php.ini�ļ�ͨ����php/libĿ¼��

