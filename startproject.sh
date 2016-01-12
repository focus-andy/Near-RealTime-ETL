#!/bin/sh
cur_dir=`pwd`
cd
source .bashrc
cd $cur_dir
cd app
cp -r example $1
cd $cur_dir
cd conf
cp -r example $1
cd $cur_dir
cd data
mkdir $1
