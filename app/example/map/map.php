<?php
/***************************************************************************
 * 
 * Copyright (c) 2013 Baidu.com, Inc. All Rights Reserved
 * $Id$ 
 * 
 **************************************************************************/
 
 
 
/**
 * @file map.php
 * @author niuyunkun(niuyunkun@baidu.com)
 * @date 2013/08/20 22:06:52
 * @version $Revision$ 
 * @brief 
 *  
 **/

$arrMap = array(
	0 => array(
		'in' => array( 'logid' ),
		'out' => array( 'event_logid' ),
		'hook' => 'common_copy',
	),
	1 => array(
		'in' => array( 'date', 'time' ),
		'out' => array( 'event_year', 'event_month', 'event_time', 'event_timestamp', 'event_dayofmonth' ),
		'hook' => 'common_date_time',
	),
	2 => array(
		'in' => array( 'client_ip' ),
		'out' => array( 'event_ip', 'event_net_provider', 'event_city', 'event_province' ),
		'hook' => 'common_ip',
	),
	3 => array(
		'in' => array( 'baiduid' ),
		'out' => array( 'event_baiduid' ),
		'hook' => 'common_baiduid',
	),
	4 => array(
		'in' => array( 'userid' ),
		'out' => array( 'event_userid' ),
		'hook' => 'common_copy',
	),
	5 => array(
		'in' => array( 'cookie' ),
		'out' => array( 'event_cookie' ),
		'hook' => 'common_copy',
	),
	6 => array(
		'in' => array( 'un' ),
		'out' => array( 'event_username' ),
		'hook' => 'common_copy_decode_utf8',
	),
	7 => array(
		'in' => array( 'host', 'url' ),
		'out' => array( 'event_url', 'event_url_hostname', 'event_urlpath', 'event_urlparamsmap', 'event_query' ),
		'hook' => 'hook_url',
	),
	8 => array(
		'in' => array( 'refer' ),
		'out' => array( 'event_referer', 'event_refererpath', 'event_refererparamsmap', 'event_referer_hostname' ),
		'hook' => 'hook_refer',
	),
	9 => array(
		'in' => array( 'ua' ),
		'out' => array( 'event_useragent' ),
		'hook' => 'common_copy_decode',
	),
	10 => array(
		'in' => array( 'errno' ),
		'out' => array( 'iknow_submit_errno' ),
		'hook' => 'common_copy',
	),
	11 => array(
		'in' => array( 'optime' ),
		'out' => array( 'iknow_submit_optime' ),
		'hook' => 'common_copy',
	),
	12 => array(
		'in' => array( 'local_ip' ),
		'out' => array( 'iknow_submit_local_ip' ),
		'hook' => 'common_copy',
	),
	13 => array(
		'in' => array( 'uniqid' ),
		'out' => array( 'iknow_submit_uniqid' ),
		'hook' => 'common_copy',
	),
	14 => array(
		'in' => array( 'cgid' ),
		'out' => array( 'iknow_submit_cgid' ),
		'hook' => 'common_copy',
	),
	15 => array(
		'in' => array( 'Entry' ),
		'out' => array( 'iknow_submit_entry' ),
		'hook' => 'common_copy',
	),
	16 => array(
		'in' => array( 'fromId' ),
		'out' => array( 'iknow_submit_from_id' ),
		'hook' => 'common_copy',
	),
	17 => array(
		'in' => array( 'submitFrom' ),
		'out' => array( 'iknow_submit_submit_from' ),
		'hook' => 'common_copy',
	),
	18 => array(
		'in' => array( 'UipCmd' ),
		'out' => array( 'iknow_submit_uip_cmd' ),
		'hook' => 'common_copy',
	),
	19 => array(
		'in' => array( 'UipUser' ),
		'out' => array( 'iknow_submit_uip_user' ),
		'hook' => 'common_copy',
	),
	20 => array(
		'in' => array( 'BaiduUid' ),
		'out' => array( 'iknow_submit_baidu_uid' ),
		'hook' => 'common_copy',
	),
	21 => array(
		'in' => array( 'Cmd' ),
		'out' => array( 'iknow_submit_cmd' ),
		'hook' => 'common_copy_iknow_array',
	),
	22 => array(
		'in' => array( 'Cost' ),
		'out' => array( 'iknow_submit_cost' ),
		'hook' => 'common_copy_iknow_array',
	),
	23 => array(
		'in' => array( 'mobilephone' ),
		'out' => array( 'iknow_submit_mobilephone' ),
		'hook' => 'common_copy',
	),
	24 => array(
		'in' => array( 'uip' ),
		'out' => array( 'iknow_submit_uip' ),
		'hook' => 'common_copy',
	),
	25 => array(
		'in' => array( 'errmsg' ),
		'out' => array( 'iknow_submit_errmsg' ),
		'hook' => 'common_copy',
	),
	26 => array(
		'in' => array( 'Score' ),
		'out' => array( 'iknow_submit_score' ),
		'hook' => 'common_copy_iknow_array',
	),
	27 => array(
		'in' => array( 'Grade' ),
		'out' => array( 'iknow_submit_grade' ),
		'hook' => 'common_copy_iknow_array',
	),
	28 => array(
		'in' => array( 'MouseTrace' ),
		'out' => array( 'iknow_submit_mousetrace' ),
		'hook' => 'common_copy',
	),
	29 => array(
		'in' => array( 'VcodeResArr' ),
		'out' => array( 'iknow_submit_vcode_res' ),
		'hook' => 'common_copy_iknow_array',
	),
	30 => array(
		'in' => array( 'imeiType' ),
		'out' => array( 'iknow_submit_imei_type' ),
		'hook' => 'common_copy',
	),
	31 => array(
		'in' => array( 'imeiValue' ),
		'out' => array( 'iknow_submit_imei_value' ),
		'hook' => 'common_copy',
	),
	32 => array(
		'in' => array( 'hasCookie' ),
		'out' => array( 'iknow_submit_has_cookie' ),
		'hook' => 'common_copy',
	),
	33 => array(
		'in' => array( 'isExpert' ),
		'out' => array( 'iknow_submit_is_expert' ),
		'hook' => 'common_copy',
	),
	34 => array(
		'in' => array( 'spamRes' ),
		'out' => array( 'iknow_submit_spam_res' ),
		'hook' => 'common_copy_iknow_array',
	),
	35 => array(
		'in' => array( 'spamArr' ),
		'out' => array( 'iknow_submit_spam' ),
		'hook' => 'common_copy_iknow_array',
	),
	36 => array(
		'in' => array( 'oi_spam_flag' ),
		'out' => array( 'iknow_submit_oi_spam_flag' ),
		'hook' => 'common_copy',
	),
	37 => array(
		'in' => array( 'qscore' ),
		'out' => array( 'iknow_submit_qscore' ),
		'hook' => 'common_copy',
	),
	38 => array(
		'in' => array( 'Sign' ),
		'out' => array( 'iknow_submit_sign' ),
		'hook' => 'common_copy',
	),
	39 => array(
		'in' => array( 'Nik_Icached' ),
		'out' => array( 'iknow_submit_nik_icached' ),
		'hook' => 'common_copy',
	),
	40 => array(
		'in' => array( 'Lbs' ),
		'out' => array( 'iknow_submit_lbs' ),
		'hook' => 'common_copy',
	),
	41 => array(
		'in' => array( 'Vcode' ),
		'out' => array( 'iknow_submit_vcode' ),
		'hook' => 'common_copy',
	),
	42 => array(
		'in' => array( 'FileWealth' ),
		'out' => array( 'iknow_submit_file_wealth' ),
		'hook' => 'common_copy',
	),
	43 => array(
		'in' => array( 'Comment' ),
		'out' => array( 'iknow_submit_comment' ),
		'hook' => 'common_copy',
	),
	44 => array(
		'in' => array( 'Rich' ),
		'out' => array( 'iknow_submit_rich' ),
		'hook' => 'common_copy',
	),
) ;




/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
