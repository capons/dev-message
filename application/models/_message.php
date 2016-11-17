<?php

/**
 * This class manages message information.
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 10/03/2015
 */

class _message extends CI_Model {

	# Get list of messages
	function get_list($userId, $offset, $limit, $filters=array())
	{
		log_message('debug', '_message/get_list');
		log_message('debug', '_message/get_list:: [1] userId='.$userId.' offset='.$offset.' limit='.$limit.' filters='.json_encode($filters));
		$phraseCondition = !empty($filters['phrase'])? " AND MATCH(X.subject) AGAINST (+\"".htmlentities($filters['phrase'], ENT_QUOTES)."\") ": '';
		$phraseCondition .= !empty($filters['category'])? " AND X._category_id='".$filters['category']."' ": '';
		$phraseCondition .= !empty($filters['cashback'])? " AND X.cashback >= '".current(explode("_", $filters['cashback']))."' ": ''; 		if(!empty($filters['type'])){
			if($filters['type'] == 'cashback') $phraseCondition .= " AND X.cashback > 0 ";
			if($filters['type'] == 'perk') $phraseCondition .= " AND X.is_perk = 'Y' ";
			if($filters['type'] == 'system') $phraseCondition .= " AND (X.is_perk = 'N' AND X.cashback = 0 ) ";
		}

		$senderCondition = !empty($filters['sender'])? " AND sender LIKE '%".htmlentities($filters['sender'], ENT_QUOTES)."%' " : "";
		$senderCondition .= !empty($filters['location'])? " AND location LIKE '%".htmlentities($filters['location'], ENT_QUOTES)."%' ": '';

		$result = $this->_query_reader->get_list('get_user_messages', array('user_id'=>$userId, 'limit_text'=>" LIMIT ".$offset.",".$limit." ", 'phrase_condition'=>$phraseCondition, 'sender_condition'=>$senderCondition));
		log_message('debug', '_message/get_list:: [2] result='.json_encode($result));
		return $result;
	}


	# Get inbox statistics
	function statistics($userId, $fields)
	{
		log_message('debug', '_message/statistics');
		log_message('debug', '_message/statistics:: [1] userId='.$userId.' fileds='.json_encode($fields));

		$stats = $this->_query_reader->get_row_as_array('get_message_statistics', array('user_id'=>$userId));
		log_message('debug', '_message/statistics:: [2] stats='.json_encode($stats));
		$fieldArray = explode(',',$fields);
		$final = array();
		foreach($stats AS $key=>$value) if(in_array($key, $fieldArray)) $final[$key] = $value;
		# fetch only if field exists
		if(in_array('events', $fieldArray)) $final['events'] = current(server_curl(CRON_SERVER_URL, array('__action'=>'get_row_as_array', 'query'=>'get_user_event_count', 'variables'=>array('user_id'=>$userId) )));
		log_message('debug', '_message/statistics:: [3] final='.json_encode($final));
		return $final;
	}


	# Get a message details
	function details($userId, $messageId)
	{
		log_message('debug', '_message/details');
		log_message('debug', '_message/details:: [1] userId='.$userId.' messageId='.$messageId);

		$details = $this->_query_reader->get_row_as_array('get_message_details', array('message_id'=>$messageId, 'download_url'=>''));
		log_message('debug', '_message/details:: [2] details='.json_encode($details));

		# Mark the message as read by the user
		if(!empty($details)) $result = $this->_query_reader->run('add_message_status', array('message_id'=>$messageId, 'user_id'=>$userId, 'status'=>'read'));
		log_message('debug', '_message/details:: [3] result='.$result);

		return $details;
	}



	# Add a like action to a message
	function like_message($userId, $messages, $action)
	{
		log_message('debug', '_message/like_message');
		log_message('debug', '_message/like_message:: [1] userId='.$userId.' message='.json_encode($messages).' action='.$action);

		$result = $this->_query_reader->run('record_like_messages', array(
				'user_id'=>$userId,
				'messages'=>implode("','",$messages),
				'like'=>($action == 'like'? 'Y': 'N'),
				'dislike'=>($action == 'dislike'? 'Y': 'N')
			));
		log_message('debug', '_message/like_message:: [1] result='.$result);
		return array('result'=>($result? 'SUCCESS': 'FAIL'));
	}




	# Add a mark to a message
	function add_mark($userId, $messages, $action)
	{
		log_message('debug', '_message/add_mark');
		log_message('debug', '_message/add_mark:: [1] userId='.$userId.' message='.json_encode($messages).' action='.$action);

		# Mark message as read
		if($action == 'read'){
			$results = array();
			foreach($messages AS $messageId) array_push($results, $this->_query_reader->run('add_message_status', array('message_id'=>$messageId, 'user_id'=>$userId, 'status'=>'read')));

			$result = get_decision($results);
		}

		# Collect any store in the selected messages and record as the user's favorite
		else if($action == 'favorite'){
			$messageFavorites = $this->_query_reader->run('extract_favorites_from_messages', array('user_id'=>$userId, 'messages'=>implode("','",$messages) ));

			if(!empty($messageFavorites)) $result = server_curl(MYSQL_SERVER_URL, array('__action'=>'add_store_favorites_from_messages', 'return'=>'plain', 'list'=>$messageFavorites));
		}

		log_message('debug', '_message/add_mark:: [2] result='.$result);
		return array('result'=>(!empty($result) && $result? 'SUCCESS': 'FAIL'));
	}






	# Send a contact message
	function send_contact_msg($name, $emailAddress, $telephone, $message, $userId='')
	{
		log_message('debug', '_message/send_contact_msg');
		log_message('debug', '_message/send_contact_msg:: [1] name='.$name.' emailAddress='.$emailAddress.' telephone='.$telephone.' message='.json_encode($message).' userId='.$userId);

		$details = array(
			'code'=>'website_contact_message',
			'sendername'=>htmlentities($name, ENT_QUOTES),
			'senderemail'=>$emailAddress,
			'senderphone'=>$telephone,
			'sendermessage'=>htmlentities($message, ENT_QUOTES),
			'datesent'=>date('m/d/Y h:iA', strtotime('now')),
			'bcc'=>HELP_EMAIL
			);
		log_message('debug', '_message/send_contact_msg:: [2] details='.json_encode($details));

		$result = $this->_messenger->send_direct_email($emailAddress,$userId, $details);
		log_message('debug', '_message/send_contact_msg:: [3] result='.$result);

		return array('result'=>($result? 'SUCCESS': 'FAIL'));
	}







	# get message templates
	function templates($ownerId, $ownerType, $phrase, $offset, $limit)
	{
		log_message('debug', '_message/templates');
		log_message('debug', '_message/templates:: [1] ownerId='.$ownerId.' ownerType='.$ownerType.' phrase='.$phrase.' offset='.$offset.' limit='.$limit);

		$result = $this->_query_reader->get_list('get_message_templates', array(
				'owner_id'=>$ownerId,
				'owner_type'=>$ownerType,
				'base_url'=>S3_URL,
				'phrase_condition'=> " AND name LIKE '%".htmlentities($phrase, ENT_QUOTES)."%' ",
				'limit_text'=>" LIMIT ".$offset.",".$limit
			 ));

		log_message('debug', '_message/templates:: [2] result='.json_encode($result));
		return $result;
	}




	# schedule sending a message
	function schedule_send($message, $userId, $organizationId='', $organizationType='')
	{
		log_message('debug', '_message/schedule_send');
		log_message('debug', '_message/schedule_send:: [1] message='.json_encode($message).' userId='.$userId.' organizationId'.$organizationId.' organizationId'.$organizationId);

		# a-1) set the initial message requirements
		$message['attachment'] = (!empty($message['attachment'])? download_from_url($message['attachment']): '');

		if(empty($message['attachment']) && !empty($message['templateAttachment'])) $message['attachment'] = $message['templateAttachment'];

		$senderId = ($message['senderType'] == 'organization' && !empty($organizationId)? $organizationId: $userId);

		$senderType = ($message['senderType'] == 'organization' && !empty($organizationId)? $organizationType: 'user');

		$result = FALSE;
		$msg = '';

		# a) check if the user wants to save this as a template
		if($message['saveTemplate'] == 'Y'){
			$templateId = $this->_query_reader->add_data('add_user_message_template', array(
				'owner_id'=>$senderId,
				'owner_type'=>$senderType,
				'name'=>(!empty($message['saveTemplateName'])? htmlentities($message['saveTemplateName'],ENT_QUOTES): 'Template '.date('F d, Y')),
				'subject'=>htmlentities($message['subject'],ENT_QUOTES),
				'body'=>htmlentities($message['body'],ENT_QUOTES),
				'sms'=>htmlentities($message['sms'],ENT_QUOTES),
				'attachment'=>$message['attachment'],
				'user_id'=>$userId
			));

			log_message('debug', '_message/schedule_send:: [2] templateId='.$templateId);
			if(empty($templateId)) $msg = 'The new template could not be saved. Check that no template with the same name exists.';

		} else if(!empty($message['templateVariables'])) {
			$result = $this->_messenger->populate_template($message['template'], $message['templateVariables']);
			log_message('debug', '_message/schedule_send:: [2] populate_template='.json_encode($result));
			$message['subject'] = $result['subject'];
			$message['body'] = $result['details'];
			$message['sms'] = $result['sms'];
			$templateId = $message['templateId'];
		}


		# b) get recipient list
		if(($message['saveTemplate'] == 'Y' && !empty($templateId))|| $message['saveTemplate'] == 'N'){
			if($message['sendToType'] == 'list') $recipients = $message['sendTo'];
			else if($message['sendToType'] == 'filter') $recipients = $this->get_users_by_filter($message['sendTo'],$message['select_user']);
			else $recipients = array();
		}
		log_message('debug', '_message/schedule_send:: [3] recipients='.json_encode($recipients));

		# c) send to all recipients
		$results = array();

		if(!empty($recipients)){
			 foreach($recipients AS $sendToId){
			 	$results[] = $this->_query_reader->run('add_custom_message_exchange', array(
			 		'template_id'=>(!empty($templateId)? $templateId: (!empty($message['templateId']) && !empty($message['userTemplate']) && $message['userTemplate'] == 'Y'? $message['templateId']: '0')),
					'template_type'=>'user',
					'details'=>htmlentities($message['body'],ENT_QUOTES),
					'sms'=>htmlentities($message['sms'],ENT_QUOTES),
					'subject'=>htmlentities($message['subject'],ENT_QUOTES),
					'attachment_url'=>$message['attachment'],
					'sender_id'=>$senderId,
					'sender_type'=>$senderType,
					'recipient_id'=>$sendToId,
					'cashback'=>'0',
					'is_perk'=>'N',
					'category_id'=>'0',
					'send_date'=>(!empty($message['sendDate'])? date('Y-m-d H:i:s',strtotime($message['sendDate'])): ''),
					'send_system'=>(in_array('system',$message['methods'])? 'Y': 'N'),
					'send_email'=>(in_array('email',$message['methods'])? 'Y': 'N'),
					'send_sms'=>(in_array('sms',$message['methods'])? 'Y': 'N'),
					'send_system_result'=>'pending',
					'send_email_result'=>'pending',
					'send_sms_result'=>'pending',
               'scheduled_send_date'=>(!empty($message['scheduledSendDate'])? date('Y-m-d H:i:s',strtotime($message['scheduledSendDate'])): ''),
					'user_id'=>$userId
				));
			 }

			 $result = get_decision($results);
			 if(!$result) $msg = "The messages could not be scheduled.";
		}

		log_message('debug', '_message/schedule_send:: [4] result='.$result.' msg='.$msg);
		return array('boolean'=>$result, 'msg'=>$msg);
	}



	# get users for the given filter code
	function get_users_by_filter($filterCode,$users_id = '')
	{
		log_message('debug', '_message/get_users_by_filter');
		log_message('debug', '_message/get_users_by_filter:: [1] filterCode='.$filterCode);

		switch($filterCode){
			case "all_users":
				return $this->get_types(array('invited_shopper','random_shopper','clout_merchant'));
			break;

			case "all_admins":
				return $this->get_types(array('clout_owner','clout_admin_user'));
			break;

			case "all_store_owners":
				return $this->get_types(array('store_owner_owner'));
			break;

			case "all_shoppers":
				return $this->get_types(array('invited_shopper','random_shopper'));
			break;

			case "shoppers_without_bank_account":
				return server_curl(CRON_SERVER_URL, array('__action'=>'get_single_column_as_array', 'query'=>'get_users_without_bank_account', 'column'=>'user_id', 'variables'=>array() ));
			break;

			case "shoppers_without_network":
				return server_curl(CRON_SERVER_URL, array('__action'=>'get_single_column_as_array', 'query'=>'get_users_without_network', 'column'=>'user_id', 'variables'=>array() ));
			break;

			case "select_user":
				return $this->get_select_user($users_id);
			break;
			default:
				return array();
			break;
		}
	}



	# get user types
	function get_types($types)
	{
		log_message('debug', '_message/get_types');
		log_message('debug', '_message/get_types:: [1] array='.json_encode(array('__action'=>'get_users_in_group_type', 'group_type'=>implode("','",$types), 'offset'=>0, 'limit'=>'1000' )));
		return server_curl(IAM_SERVER_URL,  array('__action'=>'get_users_in_group_type', 'group_type'=>implode("','",$types), 'offset'=>0, 'limit'=>'1000' ));
	}

	#get select users
	function get_select_user($users)
	{

		return server_curl(IAM_SERVER_URL,  array('__action'=>'get_users_in_p_reservation', 'user_id'=>implode("','",$users), 'offset'=>0, 'limit'=>'1000' )); //get_select_users | implode("','",$users)
	}








	# unsubscribe a user
	function unsubscribe($emailAddress, $telephone, $reason)
	{
		log_message('debug', '_message/unsubscribe');
		log_message('debug', '_message/unsubscribe:: [1] email='.$emailAddress.' telephone='.$telephone.' reason='.$reason);

		$result = $this->_query_reader->run('add_user_to_unsubscribe_list', array(
			'email_address'=>$emailAddress,
			'telephone'=>$telephone,
			'reason'=>htmlentities($reason, ENT_QUOTES),
			'expiry_date'=>date("Y-m-d H:i:s", strtotime("+".UNSUBSCRIBE_EXPIRY." months"))
		));

		log_message('debug', '_message/unsubscribe:: [2] result='.$result);
		return array('boolean'=>$result);
	}



}

?>
