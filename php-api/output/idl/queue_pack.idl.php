<?php

class UnExistOperationException extends Exception {
    public function __construct($message,$code = 0) {
        parent::__construct($message,$code);
    }
    public function __toString() {
        return __CLASS__. ": [{$this->code}] : {$this->message} : {$this->getFile()} : {$this->getLine()}";
    }
}
class idl_queue_req_t {
    //@@int32_t cmd_no;
    public function hascmd_no() {
        return $this->field_flag["cmd_no"];
    }
    public function getcmd_no() {
        return $this->cmd_no;
    }
    public function setcmd_no($cmd_no) {
        $this->field_flag["cmd_no"] = true;
        $this->cmd_no = $cmd_no;
    }
    //@@string queue_name;
    public function hasqueue_name() {
        return $this->field_flag["queue_name"];
    }
    public function getqueue_name() {
        return $this->queue_name;
    }
    public function setqueue_name($queue_name) {
        $this->field_flag["queue_name"] = true;
        $this->queue_name = $queue_name;
    }
    //@@string token;
    public function hastoken() {
        return $this->field_flag["token"];
    }
    public function gettoken() {
        return $this->token;
    }
    public function settoken($token) {
        $this->field_flag["token"] = true;
        $this->token = $token;
    }
    //@@int32_t window_size=optional();
    public function haswindow_size() {
        return $this->field_flag["window_size"];
    }
    public function getwindow_size() {
        return $this->window_size;
    }
    public function setwindow_size($window_size) {
        $this->field_flag["window_size"] = true;
        $this->window_size = $window_size;
    }
	//@@string api_version=optional();
    public function hasapi_version() {
        return $this->field_flag["api_version"];
    }
    public function getapi_version() {
        return $this->api_version;
    }
    public function setapi_version($api_version) {
        $this->field_flag["api_version"] = true;
        $this->api_version = $api_version;
    }
    private $cmd_no;
    private $queue_name;
    private $token;
    private $window_size;
	private $api_version;
    private $field_flag = array("cmd_no"=>false,"queue_name"=>false,"token"=>false,"window_size"=>false,"api_version"=>false);
    public function save(&$pack) {
        if($this->field_flag["cmd_no"]) {
            $pack["cmd_no"] = $this->cmd_no;
        } else {
            throw new ErrorException("key cmd_no is not exist");
        }
        if($this->field_flag["queue_name"]) {
            $pack["queue_name"] = $this->queue_name;
        } else {
            throw new ErrorException("key queue_name is not exist");
        }
        if($this->field_flag["token"]) {
            $pack["token"] = $this->token;
        } else {
            throw new ErrorException("key token is not exist");
        }
        if($this->field_flag["window_size"]) {
            $pack["window_size"] = $this->window_size;
        }
		if($this->field_flag["api_version"]) {
            $pack["api_version"] = $this->api_version;
        }
    }
    public function load($pack) {
        if(array_key_exists("cmd_no",$pack)) {
            $this->setcmd_no($pack["cmd_no"]);
        } else {
            throw new ErrorException("key cmd_no is not exist");
        }
        if(array_key_exists("queue_name",$pack)) {
            $this->setqueue_name($pack["queue_name"]);
        } else {
            throw new ErrorException("key queue_name is not exist");
        }
        if(array_key_exists("token",$pack)) {
            $this->settoken($pack["token"]);
        } else {
            throw new ErrorException("key token is not exist");
        }
        if(array_key_exists("window_size",$pack)) {
            $this->setwindow_size($pack["window_size"]);
		}
		if(array_key_exists("api_version",$pack)) {
            $this->setapi_version($pack["api_version"]);
        }
    }
}
class idl_queue_data_t {
    //@@int32_t err_no;
    public function haserr_no() {
        return $this->field_flag["err_no"];
    }
    public function geterr_no() {
        return $this->err_no;
    }
    public function seterr_no($err_no) {
        $this->field_flag["err_no"] = true;
        $this->err_no = $err_no;
    }
    //@@string err_msg=optional();
    public function haserr_msg() {
        return $this->field_flag["err_msg"];
    }
    public function geterr_msg() {
        return $this->err_msg;
    }
    public function seterr_msg($err_msg) {
        $this->field_flag["err_msg"] = true;
        $this->err_msg = $err_msg;
    }
    //@@string queue_name=optional();
    public function hasqueue_name() {
        return $this->field_flag["queue_name"];
    }
    public function getqueue_name() {
        return $this->queue_name;
    }
    public function setqueue_name($queue_name) {
        $this->field_flag["queue_name"] = true;
        $this->queue_name = $queue_name;
    }
    //@@string pipe_name=optional();
    public function haspipe_name() {
        return $this->field_flag["pipe_name"];
    }
    public function getpipe_name() {
        return $this->pipe_name;
    }
    public function setpipe_name($pipe_name) {
        $this->field_flag["pipe_name"] = true;
        $this->pipe_name = $pipe_name;
    }
    //@@int32_t pipelet_id=optional();
    public function haspipelet_id() {
        return $this->field_flag["pipelet_id"];
    }
    public function getpipelet_id() {
        return $this->pipelet_id;
    }
    public function setpipelet_id($pipelet_id) {
        $this->field_flag["pipelet_id"] = true;
        $this->pipelet_id = $pipelet_id;
    }
    //@@int64_t pipelet_msg_id=optional();
    public function haspipelet_msg_id() {
        return $this->field_flag["pipelet_msg_id"];
    }
    public function getpipelet_msg_id() {
        return $this->pipelet_msg_id;
    }
    public function setpipelet_msg_id($pipelet_msg_id) {
        $this->field_flag["pipelet_msg_id"] = true;
        $this->pipelet_msg_id = $pipelet_msg_id;
    }
    //@@int32_t seq_id=optional();
    public function hasseq_id() {
        return $this->field_flag["seq_id"];
    }
    public function getseq_id() {
        return $this->seq_id;
    }
    public function setseq_id($seq_id) {
        $this->field_flag["seq_id"] = true;
        $this->seq_id = $seq_id;
    }
    //@@binary msg_body=optional();
    public function hasmsg_body() {
        return $this->field_flag["msg_body"];
    }
    public function getmsg_body() {
        return $this->msg_body;
    }
    public function setmsg_body($msg_body) {
        $this->field_flag["msg_body"] = true;
        $this->msg_body = $msg_body;
    }
    //@@int64_t srvtime=optional();
    public function hassrvtime() {
        return $this->field_flag["srvtime"];
    }
    public function getsrvtime() {
        return $this->srvtime;
    }
    public function setsrvtime($srvtime) {
        $this->field_flag["srvtime"] = true;
        $this->srvtime = $srvtime;
    }
    //@@int32_t timeout=optional();
    public function hastimeout() {
        return $this->field_flag["timeout"];
    }
    public function gettimeout() {
        return $this->timeout;
    }
    public function settimeout($timeout) {
        $this->field_flag["timeout"] = true;
        $this->timeout = $timeout;
    }
    //@@int32_t msg_flag=optional();
    public function hasmsg_flag() {
        return $this->field_flag["msg_flag"];
    }
    public function getmsg_flag() {
        return $this->msg_flag;
    }
    public function setmsg_flag($msg_flag) {
        $this->field_flag["msg_flag"] = true;
        $this->msg_flag = $msg_flag;
    }

    private $err_no;
    private $err_msg;
    private $queue_name;
    private $pipe_name;
    private $pipelet_id;
    private $pipelet_msg_id;
    private $seq_id;
    private $msg_body;
    private $srvtime;
    private $timeout;
    private $msg_flag;
    private $field_flag = array("err_no"=>false,"err_msg"=>false,"queue_name"=>false,"pipe_name"=>false,"pipelet_id"=>false,"pipelet_msg_id"=>false,"seq_id"=>false,"msg_body"=>false,"srvtime"=>false,"timeout"=>false,"msg_flag"=>false);
    public function save(&$pack) {
        if($this->field_flag["err_no"]) {
            $pack["err_no"] = $this->err_no;
        } else {
            throw new ErrorException("key err_no is not exist");
        }
        if($this->field_flag["err_msg"]) {
            $pack["err_msg"] = $this->err_msg;
        }
        if($this->field_flag["queue_name"]) {
            $pack["queue_name"] = $this->queue_name;
        }
        if($this->field_flag["pipe_name"]) {
            $pack["pipe_name"] = $this->pipe_name;
        }
        if($this->field_flag["pipelet_id"]) {
            $pack["pipelet_id"] = $this->pipelet_id;
        }
        if($this->field_flag["pipelet_msg_id"]) {
            $pack["pipelet_msg_id"] = $this->pipelet_msg_id;
        }
        if($this->field_flag["seq_id"]) {
            $pack["seq_id"] = $this->seq_id;
        }
        if($this->field_flag["msg_body"]) {
            $pack["(raw)msg_body"] = $this->msg_body;
        }
        if($this->field_flag["srvtime"]) {
            $pack["srvtime"] = $this->srvtime;
        }
        if($this->field_flag["timeout"]) {
            $pack["timeout"] = $this->timeout;
        }
        if($this->field_flag["msg_flag"]) {
            $pack["msg_flag"] = $this->msg_flag;
        }

    }
    public function load($pack) {
        if(array_key_exists("err_no",$pack)) {
            $this->seterr_no($pack["err_no"]);
        } else {
            throw new ErrorException("key err_no is not exist");
        }
        if(array_key_exists("err_msg",$pack)) {
            $this->seterr_msg($pack["err_msg"]);
        }
        if(array_key_exists("queue_name",$pack)) {
            $this->setqueue_name($pack["queue_name"]);
        }
        if(array_key_exists("pipe_name",$pack)) {
            $this->setpipe_name($pack["pipe_name"]);
        }
        if(array_key_exists("pipelet_id",$pack)) {
            $this->setpipelet_id($pack["pipelet_id"]);
        }
        if(array_key_exists("pipelet_msg_id",$pack)) {
            $this->setpipelet_msg_id($pack["pipelet_msg_id"]);
        }
        if(array_key_exists("seq_id",$pack)) {
            $this->setseq_id($pack["seq_id"]);
        }
        if(array_key_exists("msg_body",$pack)) {
            $this->setmsg_body($pack["msg_body"]);
        }
        if(array_key_exists("srvtime",$pack)) {
            $this->setsrvtime($pack["srvtime"]);
        }
        if(array_key_exists("timeout",$pack)) {
            $this->settimeout($pack["timeout"]);
        }
        if(array_key_exists("msg_flag",$pack)) {
            $this->setmsg_flag($pack["msg_flag"]);
        }
    }
}
class idl_queue_ack_t {
    //@@int32_t cmd_no;
    public function hascmd_no() {
        return $this->field_flag["cmd_no"];
    }
    public function getcmd_no() {
        return $this->cmd_no;
    }
    public function setcmd_no($cmd_no) {
        $this->field_flag["cmd_no"] = true;
        $this->cmd_no = $cmd_no;
    }
    //@@string queue_name;
    public function hasqueue_name() {
        return $this->field_flag["queue_name"];
    }
    public function getqueue_name() {
        return $this->queue_name;
    }
    public function setqueue_name($queue_name) {
        $this->field_flag["queue_name"] = true;
        $this->queue_name = $queue_name;
    }
    //@@string pipe_name;
    public function haspipe_name() {
        return $this->field_flag["pipe_name"];
    }
    public function getpipe_name() {
        return $this->pipe_name;
    }
    public function setpipe_name($pipe_name) {
        $this->field_flag["pipe_name"] = true;
        $this->pipe_name = $pipe_name;
    }
    //@@int32_t pipelet_id;
    public function haspipelet_id() {
        return $this->field_flag["pipelet_id"];
    }
    public function getpipelet_id() {
        return $this->pipelet_id;
    }
    public function setpipelet_id($pipelet_id) {
        $this->field_flag["pipelet_id"] = true;
        $this->pipelet_id = $pipelet_id;
    }
    //@@int64_t pipelet_msg_id;
    public function haspipelet_msg_id() {
        return $this->field_flag["pipelet_msg_id"];
    }
    public function getpipelet_msg_id() {
        return $this->pipelet_msg_id;
    }
    public function setpipelet_msg_id($pipelet_msg_id) {
        $this->field_flag["pipelet_msg_id"] = true;
        $this->pipelet_msg_id = $pipelet_msg_id;
    }
    //@@int32_t seq_id;
    public function hasseq_id() {
        return $this->field_flag["seq_id"];
    }
    public function getseq_id() {
        return $this->seq_id;
    }
    public function setseq_id($seq_id) {
        $this->field_flag["seq_id"] = true;
        $this->seq_id = $seq_id;
    }
    private $cmd_no;
    private $queue_name;
    private $pipe_name;
    private $pipelet_id;
    private $pipelet_msg_id;
    private $seq_id;
    private $field_flag = array("cmd_no"=>false,"queue_name"=>false,"pipe_name"=>false,"pipelet_id"=>false,"pipelet_msg_id"=>false,"seq_id"=>false);
    public function save(&$pack) {
        if($this->field_flag["cmd_no"]) {
            $pack["cmd_no"] = $this->cmd_no;
        } else {
            throw new ErrorException("key cmd_no is not exist");
        }
        if($this->field_flag["queue_name"]) {
            $pack["queue_name"] = $this->queue_name;
        } else {
            throw new ErrorException("key queue_name is not exist");
        }
        if($this->field_flag["pipe_name"]) {
            $pack["pipe_name"] = $this->pipe_name;
        } else {
            throw new ErrorException("key pipe_name is not exist");
        }
        if($this->field_flag["pipelet_id"]) {
            $pack["pipelet_id"] = $this->pipelet_id;
        } else {
            throw new ErrorException("key pipelet_id is not exist");
        }
        if($this->field_flag["pipelet_msg_id"]) {
            $pack["pipelet_msg_id"] = $this->pipelet_msg_id;
        } else {
            throw new ErrorException("key pipelet_msg_id is not exist");
        }
        if($this->field_flag["seq_id"]) {
            $pack["seq_id"] = $this->seq_id;
        } else {
            throw new ErrorException("key seq_id is not exist");
        }
    }
    public function load($pack) {
        if(array_key_exists("cmd_no",$pack)) {
            $this->setcmd_no($pack["cmd_no"]);
        } else {
            throw new ErrorException("key cmd_no is not exist");
        }
        if(array_key_exists("queue_name",$pack)) {
            $this->setqueue_name($pack["queue_name"]);
        } else {
            throw new ErrorException("key queue_name is not exist");
        }
        if(array_key_exists("pipe_name",$pack)) {
            $this->setpipe_name($pack["pipe_name"]);
        } else {
            throw new ErrorException("key pipe_name is not exist");
        }
        if(array_key_exists("pipelet_id",$pack)) {
            $this->setpipelet_id($pack["pipelet_id"]);
        } else {
            throw new ErrorException("key pipelet_id is not exist");
        }
        if(array_key_exists("pipelet_msg_id",$pack)) {
            $this->setpipelet_msg_id($pack["pipelet_msg_id"]);
        } else {
            throw new ErrorException("key pipelet_msg_id is not exist");
        }
        if(array_key_exists("seq_id",$pack)) {
            $this->setseq_id($pack["seq_id"]);
        } else {
            throw new ErrorException("key seq_id is not exist");
        }
    }
}
?>
