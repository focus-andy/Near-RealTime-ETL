
#把源码发布到output下
mkdir output
cp *.php output
cp README output
cp build.demo.sh output

## 把文件夹发布到output下
cp -r conf    output
cp -r ext     output
cp -r frame   output
cp -r idl     output
cp -r test    output
cp -r example output


## 从php-sign获取文件
cp ../../../../public/php-ex/php-sign/output/sign.php output/ext
