<?php
/***************************************************************************
 * 
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/



/**
 * @file Data.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/23 12:35:24
 * @version $Revision$ 
 * @brief 
 *  
 **/
require_once( "./Base.php" ) ;
require_once( ETL_API. "/frame/bigpipe_common.inc.php" ) ;
require_once( ETL_API. "/frame/BigpipeLog.class.php" ) ;
require_once( ETL_API. "/frame/bigpipe_configures.inc.php" ) ;
require_once( ETL_API. "/BigpipeSubscriber.class.php" ) ;

class ETL_Data extends ETL_Base{
	public function __construct(){
		require_once( ETL_APP. parent :: $app. "/cut/cut.php" ) ;
	}
	public function execute(){
		$minute_partition = parent :: $arrAppConf['minute_partition'] ;
		$bigpipeLogConf = new BigpipeLogConf() ;
		$bigpipeLogConf->file = 'subscribe.php';
		if (BigpipeLog::init($bigpipeLogConf))
		{
			echo "[Success] [open subscribe log]\n";
//			print_r($bigpipeLogConf);
		}
		else
		{
			echo '[Failure] [open subscribe log]\n';
			print_r($bigpipeLogConf);
			echo "\n";
		}

		$conf = new BigpipeConf;
		$conf_dir = ETL_CONF. parent :: $app ;
		$conf_file = './php-api.conf';
		if (false === bigpipe_load_file($conf_dir, $conf_file, $conf)){
			echo "[failure][when load configure]\n";
			exit ;
		}

		$pipeName = parent :: $arrAppConf['pipe_name'] ;
		$pipelet = parent :: $pipelet ;
		$token = parent :: $arrAppConf['token'] ;
		$peekTimeMs = parent :: $arrAppConf['peek_time_ms'] ;
		$messageIdFile = ETL_DATA. parent :: $app. "/message_pipelet_". parent :: $pipelet. ".flag" ;
		$startPoint = file_get_contents( $messageIdFile ) ;
		if( $startPoint == false  )
			$startPoint = -1 ;
		else if( trim($startPoint) != "-2" )
			$startPoint = intval( trim( $startPoint) ) + 1 ;

		$sub = new BigpipeSubscriber() ;
		if( $sub->init($pipeName, $token, $pipelet, $startPoint, $conf) ){
			$lastPartition = 0 ;
			while( true ){
				$pret = $sub->peek( $peekTimeMs ) ;
				if( BigpipeErrorCode :: READABLE == $pret ){
					$msg = $sub->receive() ;
					if( false == $msg ){
						echo "[Receive][error]\n" ;
						continue ;
					} else {
//						echo $msg->msg_id . "\t" . $msg->seq_id. "\n" ;
						$arrMsgContent = mc_pack_pack2array( $msg->content ) ;

						//cut 
						$arrContent = cut( $arrMsgContent['body'] ) ;

						//map
						$objEvent = new Event() ;
						foreach( parent :: $arrMap as $map ){
							$arrParams = array() ;
							foreach( $map['in'] as $in ){
								$arrParams['in'][$in] = $arrContent[$in] ;
							}
							foreach( $map['out'] as $out ){
								$arrParams['out'][$out] = null ;
							}
							
							$hook_func_callback = $map['hook'] ;
							$res = call_user_func( $hook_func_callback, &$arrParams ) ;
							if( $res === true ){
								foreach( $arrParams['out'] as $key => $value )
									$objEvent->arrEvent[$key] = $value ;
								foreach( $arrParams['in'] as $key => $value ) 
									unset( $arrContent[$key] );
							}
						} 
						$objEvent->arrEvent['event_loginfo'] = common_loginfo($arrContent) ;
						
						//write to data
						$date = sprintf( "%s%s%s", $objEvent->arrEvent['event_year']
												,$objEvent->arrEvent['event_month']
												,$objEvent->arrEvent['event_dayofmonth'] ) ;
						list( $hour, $minute, $second ) = explode( ":", $objEvent->arrEvent['event_time'], 3 ) ;
						$partition = $date.$hour. sprintf( "%02d", intval($minute / parent :: $arrAppConf['minute_partition'] ) * parent :: $arrAppConf['minute_partition'] ) ;
				
						$dataFile = ETL_DATA. parent :: $app. "/". parent :: $app. "_". parent :: $pipelet. "_$partition" ;
						$strEvent = "" ;
						foreach( $objEvent->arrEvent as $item ){
							$item = str_replace( "\001", "", $item ) ;	
							$item = str_replace( "\n", "\003", $item ) ;
							if( $strEvent == "" )
								$strEvent .= $item ;
							else
								$strEvent .= "\001". $item ;
						}
						$strEvent .= "\n" ;

						file_put_contents( $dataFile, $strEvent, FILE_APPEND | LOCK_EX ) ;
						$fpMessageId = fopen( $messageIdFile, "w" ) ;
						fwrite( $fpMessageId, $msg->msg_id ) ;
						fclose( $fpMessageId ) ;
					}
				} else if( BigpipeErrorCode :: UNREADABLE == $pret ){
					sleep( 30 ) ;
				} else {
					echo "[Peek][Error][ret:$pret]\n" ;
				}
			}
		} else {
			echo '[Failure][init subscribe]\n';
		}

		$sub->uninit() ;
		BigpipeLog::close() ;
	}
}





/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
