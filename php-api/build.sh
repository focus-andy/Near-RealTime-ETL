
#��Դ�뷢����output��
mkdir output
cp *.php output
cp README output
cp build.demo.sh output

## ���ļ��з�����output��
cp -r conf    output
cp -r ext     output
cp -r frame   output
cp -r idl     output
cp -r test    output
cp -r example output


## ��php-sign��ȡ�ļ�
cp ../../../../public/php-ex/php-sign/output/sign.php output/ext
