<?php
	/***************************************************************************
	*
	* Copyright (c) 2012 Baidu.com, Inc. All Rights Reserved
	*
	****************************************************************************/

	/**
	* @brief: config for bigpipe
	* @author: yaoxinhong
	* @date: 2012-12-26 14:00
	*/
	class Conf_Bigpipe {

		/** bigpipe api conf */
		static $BIGPIPE_API_CONF = array (
			"max_failover_cnt"	=>	3,
			"conn_timo"			=> 	60,
			"no_dedupe"			=>	1,
			"checksum_level"	=>	1,

			// stomp conf
			"stomp"				=> 	array (
				"peek_timeo"		=>	0,
				"connection"		=>	array (
					"try_time"			=>	3,
					"conn_timeo"		=>	5000,
					"read_timeo"		=>	5000,
					"time_limite"		=>	0,
				),
			),

			// meta agent conf
			"meta_agent"		=> array(

				// agent list conf
				"agent"				=>	array(
					array (
						"socket_address" 	=> 	"10.213.121.18",
						"socket_port" 		=> 	8021,
						"socket_timeoout" 	=> 	5000,
					),
				),

				// meta conf
				"meta"				=>	array(
					"meta_host"     	=> 	"10.213.103.27:2181,10.213.103.30:2182,10.213.103.25:2183",
					"root_path"      	=> 	"/bigpipe_sandbox",
					"max_cache_count" 	=> 	100000,
					"watcher_timeout" 	=> 	10000,
					"setting_timeout" 	=> 	15000,
					"recv_timeout"    	=> 	30000,
					"max_value_size"  	=> 	10240000,
					"zk_log_level"    	=> 	3,
					"zk_log_file"		=>	"./log/zookeeper.log",
					"reinit_register_random" => 1,			
				),

				// conn conf
				"connection"		=>	array(
					"try_time" 			=>	3,
					"conn_timeo"		=>	5000,
					"read_timeo"		=>	5000,
					"time_limite"		=>	0,			
				),
			),

		);

		/** bigpipe pipe name */
		static $PIPE_NAME = 'pcs-pipe';

		/** bigpipe token */
		static $PIPE_TOKEN = 'pcs-PUB';

		/** bigpipe queue read retry time */
		static $RETRY_TIMES = 3;

		/** bigpipe queue peek time out */
		static $PEEK_TIME_MS = 60;

		/** bigpipe queue conf */
		static $BIGPIPE_QUEUE_CONF = array(
			"queue"	=> array(
				"conn_timeo"	=>	1000,
				"read_timeo"	=>	1000,
				"write_timeo"	=>	1000,
				"peek_timeo"	=>	60000,
				"window_size"	=>	1,
			),

			"meta"	=> array(
				"meta_host"			=>	"10.213.103.27:2181,10.213.103.30:2182,10.213.103.25:2183",
				"root_path"			=>	"/bigpipe_sandbox",
				"zk_recv_timeout"	=>	30000,
				"zk_log_file"		=>	"./log/zk.log",
				"zk_log_level"		=>	4,
			),
		);
		}
	?>
