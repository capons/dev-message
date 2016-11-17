<?php

/**
 * This class manages formatting and sending of messages.
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 07/24/2015
 */
 
class _messenger extends CI_Model {
	
	#Constructor to set some default values at class load
	public function __construct()
    {
        parent::__construct();
		$this->load->library('email');
		#Use the message cache if its enabled
		$this->load->helper('message_list');
    }
	
	
	
	# Notify a user by sending a message to their email, sms and in the system
	# $required - makes sure that the sending formats required were successful, although the other formats are still attempted
	# $formatStrict - send using only the required formats
	function send($receiverId, $message, $required=array('system'), $formatStrict=FALSE)
	{
		log_message('debug', '_messenger/send');
		log_message('debug', '_messenger/send:: [1] receiverId='.$receiverId.' message='.json_encode($message).' required='.json_encode($required).' formatStrict='.$formatStrict);
		
		# Send to store staff if the receipient type is store
		if(!empty($message['receivertype']) && $message['receivertype'] == 'store')
		{
			$staff = server_curl(API_URL.'store/staff', array('storeId'=>$receiverId));
			# Form an array of store staff user-ids
			$receiverId = array();
			foreach($staff AS $row) array_push($receiverId, $row['_staff_user_id']);
		}
		
		$results = array();
		if(!empty($receiverId) && !empty($message['code']))
		{
			# 1. If email address or first name is not provided, then fetch it using the user id
			if(empty($message['emailaddress']) || empty($message['firstname']) || empty($message['telephone']) )
			{
				$user = $this->_query_reader->get_row_as_array('get_user_by_id', array('user_id'=>$receiverId));
				if(!empty($user))
				{
					$message['emailaddress'] = !empty($message['emailaddress'])? $message['emailaddress']: $user['email_address'];
					$message['firstname'] = !empty($message['firstname'])? $message['firstname']: $user['first_name'];
					$message['telephone'] = !empty($message['telephone'])? $message['telephone']: $user['telephone'];
				}
			}
			
			# Fetch the message template and populate the necessary details
			$template = $this->get_template_by_code($message['code']);
			$messageData = $this->populate_template($template, $message);
			$message = array_merge($messageData, $message);
			
			# Sending email
			if(!$formatStrict || ($formatStrict && in_array('email', $required))){ 
				if(is_array($receiverId)) 
				{
					$resultArray = array();
					foreach($receiverId AS $id) array_push($resultArray, $this->send_email_message($id, $message));
					$results['email'] = get_decision($resultArray);
				}
				else $results['email'] = $this->send_email_message($receiverId, $message);
			}
			else  $results['email'] = FALSE;
			
			
			# Sending SMS
			if(!$formatStrict || ($formatStrict && in_array('sms', $required))){ 
				if(is_array($receiverId)) 
				{
					$resultArray = array();
					foreach($receiverId AS $id) array_push($resultArray, $this->send_sms_message($id, $message));
					$results['sms'] = get_decision($resultArray);
				}
				else $results['sms'] = $this->send_sms_message($receiverId, $message);
			}
			else  $results['sms'] = FALSE;
			
			
			# Sending System
			if(!$formatStrict || ($formatStrict && in_array('system', $required))){ 
				if(is_array($receiverId)) 
				{
					$resultArray = array();
					foreach($receiverId AS $id) array_push($resultArray, $this->send_system_message($id, $message));
					$results['system'] = get_decision($resultArray);
				}
				else $results['system'] = $this->send_system_message($receiverId, $message);
			}
			else  $results['system'] = FALSE;
		}
		
		#If the sending format required passed then return the result as successful even if the others may have failed
		$considered = array();
		foreach($results AS $key=>$value) if(in_array($key, $required)) array_push($considered, $value);
		log_message('debug', '_messenger/send:: [1] considered='.json_encode($considered));
		
		return get_decision($considered);
	}
	
	
	
	# Send email message
	function send_email_message($userId, $messageDetails)
	{
		log_message('debug', '_messenger/send_email_message');
		log_message('debug', '_messenger/send_email_message:: [1] userId='.$userId.' messageDetails='.json_encode($messageDetails));
		
		$isSent = false;
		
		if(check_sending_settings($this, $userId, 'email'))
		{
			# 1. Send message
			if(!empty($messageDetails['emailaddress']) && !empty($messageDetails['details']))
			{
				$m['emailaddress'] = $messageDetails['emailaddress'];
				$m['emailfrom'] = NOREPLY_EMAIL;
				$m['fromname'] = (!empty($m['fromname'])? $m['fromname']: SITE_TITLE);
				$m['subject'] = $messageDetails['subject'];
				$m['details'] = $messageDetails['details'].$this->add_disclaimer($m['emailaddress']);
				if(!empty($messageDetails['cc'])) $m['cc'] = $messageDetails['cc'];
				if((!empty($messageDetails['copyadmin']) && $messageDetails['copyadmin'] == 'Y') && $messageDetails['emailfrom'] != SITE_ADMIN_MAIL) {
					$messageDetails['bcc'] = !empty($messageDetails['bcc'])? array_merge($messageDetails['bcc'], array(SITE_ADMIN_MAIL)): SITE_ADMIN_MAIL;
				}
				if(!empty($messageDetails['bcc'])) $m['bcc'] = $messageDetails['bcc'];
				if(!empty($message['fileurl'])) $m['fileurl'] = $message['fileurl'];
				
				$isSent = $this->email($m,  (!empty($messageDetails['__method'])? $messageDetails['__method']: 'server'));
				
				# 2. Record messsage sending event
				$this->log_message_event($userId, $isSent, 'email__message_sent', $messageDetails);
			}
		}
		log_message('debug', '_messenger/send_email_message:: [2] isSent='.$isSent);
		
		return $isSent;
	}
	
	
	
	# Send an SMS to the specified user
	function send_sms_message($userId, $messageDetails)
	{
		log_message('debug', '_messenger/send_sms_message');
		log_message('debug', '_messenger/send_sms_message:: [1] userId='.$userId.' messageDetails='.json_encode($messageDetails));
		
		$isSent = false;
		if(check_sending_settings($this, $userId, 'sms'))
		{
			$messageDetails['emailfrom'] = NOREPLY_EMAIL;
			$messageDetails['fromname'] = (!empty($messageDetails['fromname'])? $messageDetails['fromname']: SITE_GENERAL_NAME);
			
			if(!empty($messageDetails['telephone']))
			{
				#Attempt sending by SMS and then by API
				$domain = $this->_query_reader->get_row_as_array('get_provider_email_domain', array('telephone'=>$messageDetails['telephone'], 'user_id'=>$userId)); 
				$providerEmailDomain = !empty($domain['email_domain'])? $domain['email_domain']: '';
				# if domain is provided, send SMS by email
				if(!empty($providerEmailDomain))
				{
					$m['emailaddress'] = $messageDetails['telephone'].'@'.$providerEmailDomain;
					$m['fromname'] = $messageDetails['fromname'];
					$m['emailfrom'] = NOREPLY_EMAIL;
					$m['subject'] = $messageDetails['subject'];
					$m['details'] = limit_string_length($messageDetails['sms'],150,FALSE);
					$m['__format'] = 'text';
					#if(!empty($messageDetails['__format'])) $m['__format'] = $messageDetails['__format'];
					log_message('debug', '_messenger/send_sms_message:: [2] sending details = '.json_encode($m));
					$isSent = $this->email($m, (!empty($messageDetails['__method'])? $messageDetails['__method']: 'server'));
				}
			}
			
			#Else use the SMS-Global gateway to send the SMS
			if(!$isSent && !empty($messageDetails['telephone']) && !empty($messageDetails['sms']))
			{
				$this->load->library('Sms_global', array('user'=>SMS_GLOBAL_USERNAME, 'pass'=>SMS_GLOBAL_PASSWORD, 'from'=>SMS_GLOBAL_VERIFIED_SENDER)); 
				
				$this->sms_global->to($messageDetails['telephone']);
				$this->sms_global->from(SMS_GLOBAL_VERIFIED_SENDER);
				$this->sms_global->message(limit_string_length($messageDetails['sms'],150,FALSE));
				$this->sms_global->send();
				
				# only use this to output the message details on screen for debugging
				#$this->sms_global->print_debugger(); 
				
				$isSent = !empty($this->sms_global->get_sms_id())? true: false; 
			}
		
				
			#Record messsage sending event
			$this->log_message_event($userId, $isSent, 'sms__message_sent', $messageDetails);
		}
		log_message('debug', '_messenger/send_sms_message:: [2] isSent='.$isSent);
		
		return $isSent;
	}	
			
	
	
	
	
	
	
	
	
	
	
	
	# Send a system message to the specified user
	function send_system_message($userId, $messageDetails)
	{
		log_message('debug', '_messenger/send_system_message');
		log_message('debug', '_messenger/send_system_message:: [1] userId='.$userId.' messageDetails='.json_encode($messageDetails));
		
		$isSent = false;
		
		if(check_sending_settings($this, $userId, 'system', (!empty($messageDetails['allow_message_code'])? $messageDetails['allow_message_code']: 'all') ))
		{
			#Make the sender the no-reply user if no sender id is given
			$messageDetails['senderid'] = !empty($messageDetails['senderid'])? $messageDetails['senderid']: '2';
			
			# 1. Record the message exchange to be accessed by the recipient in their inbox
			$isSent[0] = $this->_query_reader->run('record_message_exchange', array('template_code'=>(!empty($messageDetails['code'])? $messageDetails['code']: 'user_defined_message'), 'details'=>htmlentities($messageDetails['details'], ENT_QUOTES), 'subject'=>htmlentities($messageDetails['subject'], ENT_QUOTES), 'attachment_url'=>(!empty($messageDetails['fileurl'])? substr(strrchr($messageDetails['fileurl'], "/"),1): ''), 'sender_id'=>$messageDetails['senderid'], 'recipient_id'=>$userId));
			
			# 2. copy admin if required
			if(!empty($messageDetails['copyadmin']) && $messageDetails['copyadmin'] == 'Y')
			{
			 	$isSent[1] = $this->_query_reader->run('record_message_exchange', array('template_code'=>(!empty($messageDetails['code'])? $messageDetails['code']: 'user_defined_message'), 'details'=>htmlentities($messageDetails['details'], ENT_QUOTES), 'subject'=>htmlentities($messageDetails['subject'], ENT_QUOTES), 'attachment_url'=>(!empty($messageDetails['fileurl'])? substr(strrchr($messageDetails['fileurl'], "/"),1): ''), 'sender_id'=>$messageDetails['senderid'], 'recipient_id'=>implode("','", $this->get_admin_users()) ));
			}
			
			$isSent = get_decision($isSent);
		}
		log_message('debug', '_messenger/send_system_message:: [2] isSent='.$isSent);
		
		return $isSent;
	}	
			



	
	
	# Returns admin user ids
	function get_admin_users()
	{
		log_message('debug', '_messenger/get_admin_users');
		return server_curl(IAM_SERVER_URL,  array('__action'=>'get_users_in_group_type', 'group_type'=>'admin', 'offset'=>'0', 'limit'=>'1000'));
	}





	# Log message sending
	function log_message_event($userId, $isSent, $activityCode, $messageDetails)
	{
		log_message('debug', '_messenger/log_message_event');
		log_message('debug', '_messenger/log_message_event:: [1] userId='.$userId.' isSent='.$isSent.' activityCode='.$activityCode.' messageDetails='.$messageDetails);
		
		$sentTo = '';
		if(!empty($messageDetails['firstname']) && !empty($messageDetails['emailaddress'])) $sentTo = $messageDetails['emailaddress'].' ('.$messageDetails['firstname'].')';
		else if(!empty($messageDetails['emailaddress'])) $sentTo = $messageDetails['emailaddress'];
		else if(!empty($messageDetails['telephone'])) $sentTo = $messageDetails['telephone'];
		log_message('debug', '_messenger/log_message_event:: [2] sentTo='.$sentTo);
		
		$this->_logger->add_event(array(
				'user_id'=>$userId, 
				'activity_code'=>$activityCode, 
				'result'=>($isSent? 'SUCCESS':'FAIL'), 
				
				'log_details'=>"message=".$messageDetails['subject']."|sent_to=".$sentTo."|sent_by=".(!empty($messageDetails['emailfrom'])? $messageDetails['emailfrom']: NOREPLY_EMAIL)." (".(!empty($messageDetails['fromname'])? $messageDetails['fromname']: SITE_TITLE).")".(!empty($messageDetails['cc'])? "|cc=".$messageDetails['cc']: ""),
				
				'uri'=>(!empty($messageDetails['uri'])? $messageDetails['uri']: ''),
				'ip_address'=>(!empty($messageDetails['ip_address'])? $messageDetails['ip_address']: '')
			));
	}

	
	
	
	
	# Send a direct email message 
	# Use only when sending to NON-REGISTERED emails
	function send_direct_email($recipientEmail, $userId, $message, $method='unverified')
	{
		log_message('debug', '_messenger/send_direct_email');
		log_message('debug', '_messenger/send_direct_email:: [1] recipientEmail='.$recipientEmail.' userId='.$userId.' message='.json_encode($message).' method='.$method);
		
		$isSent = FALSE;
		
		if(!empty($message['code']))
		{
			if(!empty($userId)){
				$user = $this->_query_reader->get_row_as_array('get_user_by_id', array('user_id'=>$userId));
				if(!empty($user)){ 
					$message['firstname'] = $user['first_name'];
					$message['fromname'] = $user['first_name'].' '.$user['last_name'];
					$message['emailfrom'] = $user['email_address'];
				}
			}
			
			# Put default if user is not specified
			if(empty($message['emailfrom'])) $message['emailfrom'] = NOREPLY_EMAIL;
			if(empty($message['fromname'])) $message['fromname'] = SITE_TITLE;
			
			$template = $this->get_template_by_code($message['code']);
			$messageData = $this->populate_template($template, $message);
			$message = array_merge($messageData, $message);
			
			$m['fromname'] = $message['fromname'];
			$m['emailaddress'] = $recipientEmail;
			$m['emailfrom'] = NOREPLY_EMAIL;
			$m['subject'] = $message['subject'];
			
			if(!empty($message['cc'])) $m['cc'] = $message['cc'];
			if(!empty($message['bcc'])) $m['bcc'] = $message['bcc'];
			
			# handle text messages differently
			if(!empty($message['__format']) && $message['__format'] == 'text'){
				$m['details'] = $message['details'];
				$m['__format'] = 'text';
			} else {
				$m['details'] = $message['details'].$this->add_disclaimer($m['emailaddress']);
				if(!empty($message['fileurl'])) $m['fileurl'] = $message['fileurl'];
			}
			
			$isSent = $this->email($m, $method);
			
			# 2. copy admin if required
			if(!empty($message['copyadmin']) && $message['copyadmin'] == 'Y')
			{
			 	$isSent = $this->_query_reader->run('record_message_exchange', array('template_code'=>(!empty($message['code'])? $message['code']: 'user_defined_message'), 'details'=>htmlentities($message['details'], ENT_QUOTES), 'subject'=>htmlentities($message['subject'], ENT_QUOTES), 'attachment_url'=>(!empty($message['fileurl'])? substr(strrchr($message['fileurl'], "/"),1): ''), 'sender_id'=>(!empty($userId)? $userId: '1'), 'recipient_id'=>implode("','", $this->get_admin_users()) ));
			}
			
			#Record messsage sending event
			$this->log_message_event($userId, $isSent, 'email__message_sent', $message);
		}
		log_message('debug', '_messenger/send_direct_email:: [2] isSent='.$isSent);
		
		return $isSent;
	}
	
	
	


	
	# Get a template of the message given its code
	function get_template_by_code($code)
	{
		log_message('debug', '_messenger/get_template_by_code');
		log_message('debug', '_messenger/get_template_by_code:: [1] code='.$code);
		
		$cachedMessage = ENABLE_MESSAGE_CACHE? get_sys_message($code):'';
		log_message('debug', '_messenger/get_template_by_code:: [2] cachedMessage='.$cachedMessage);
		
		return (!empty($cachedMessage) && ENABLE_MESSAGE_CACHE)? $cachedMessage: $this->_query_reader->get_row_as_array('get_message_template', array('message_type'=>$code));
	}	
				
	
	
	# Populate the template to generate the actual message
	function populate_template($template, $values=array(), $type='email')
	{
		log_message('debug', '_messenger/populate_template');
		log_message('debug', '_messenger/populate_template:: [1] template='.json_encode($template).' values='.json_encode($values).' type='.$type);
		
		$values['messageid'] = $this->assign_message_id($template['id']);
		$values['loginlink'] = !empty($values['loginlink'])? trim($values['loginlink'],'/'): 'https://www.clout.com';	
		
		# Order keys by length - longest first
		array_multisort(array_map('strlen', array_keys($values)), SORT_DESC, $values);
		
		# SMS message
		if(!empty($template['subject']) && !empty($template['sms']))
		{
			foreach($values AS $key=>$value)
			{
				$template['subject'] = str_replace('_'.strtoupper($key).'_', html_entity_decode($value, ENT_QUOTES), $template['subject']);
				$template['sms'] = str_replace('_'.strtoupper($key).'_', html_entity_decode($value, ENT_QUOTES), $template['sms']);
			}
		}
		
		# Email or system message
		if(!empty($template['subject']) && !empty($template['details']))
		{
			# Go through all passed values and replace where they appear in the template text
			foreach($values AS $key=>$value)
			{
				$template['subject'] = str_replace('_'.strtoupper($key).'_', html_entity_decode($value, ENT_QUOTES), $template['subject']);
				$template['details'] = str_replace('_'.strtoupper($key).'_', html_entity_decode($value, ENT_QUOTES), $template['details']);
			}
		}
		
		return $template;
	}
	
	
	
	
	
	#Generate and assign a message ID
	public function assign_message_id($templateId)
	{
		log_message('debug', '_messenger/assign_message_id');
		log_message('debug', '_messenger/assign_message_id:: [1] templateId='.$templateId);
		
		return strtoupper(date('Ym').dechex($templateId).date('his'));
	}
	
	
	
	
	
	
	
	
	# send message based on exchange record
	function send_exchange_message($exchangeId, $method)
	{
		log_message('debug', '_messenger/send_exchange_message');
		log_message('debug', '_messenger/send_exchange_message:: [1] exchangeId='.$exchangeId.' method='.$method);
		
		$message = $this->_query_reader->get_row_as_array('get_message_details', array('download_url'=>UPLOAD_DIRECTORY, 'message_id'=>$exchangeId));
		if(!empty($message['recipient_id'])) $user = $this->_query_reader->get_row_as_array('get_user_by_id', array('user_id'=>$message['recipient_id']));
		
		# send by email
		if($method == 'email'){
			if(!empty($user['email_address'])){
				$m['fromname'] = $user['first_name'].' '.$user['last_name'];
				$m['emailaddress'] = $user['email_address'];
				$m['emailfrom'] = NOREPLY_EMAIL;
				$m['subject'] = $message['subject'];
				$m['details'] = html_entity_decode($message['details'],ENT_QUOTES);
				if(!empty($message['attachment_url'])) $m['fileurl'] = $message['attachment_url'];
				$m['__method'] = 'ses';
				
				$result = $this->send_email_message($message['recipient_id'], $m);
			}
			
			$result = $this->_query_reader->run('update_message_exchange_field', array('field_name'=>'send_email_result', 'field_value'=>(!empty($result) && $result? 'success': 'fail'), 'exchange_id'=>$exchangeId));
		}
		
		# send by sms
		else if($method == 'sms'){
			$result = !empty($user['telephone'])? $this->send_sms_message($message['recipient_id'], array('telephone'=>$user['telephone'], 'subject'=>'Message From Clout', 'sms'=>$message['sms'], '__method'=>'ses', '__format'=>'text')): FALSE;
			
			$result = $this->_query_reader->run('update_message_exchange_field', array('field_name'=>'send_sms_result', 'field_value'=>($result? 'success': 'fail'), 'exchange_id'=>$exchangeId));
		} 
		
		# send by system
		else {
			$result = $this->_query_reader->run('add_message_status', array('message_id'=>$exchangeId, 'user_id'=>$message['recipient_id'], 'status'=>'received'));
			$result = $this->_query_reader->run('update_message_exchange_field', array('field_name'=>'send_system_result', 'field_value'=>($result? 'success': 'fail'), 'exchange_id'=>$exchangeId));
		}
		log_message('debug', '_messenger/send_exchange_message:: [2] result='.$result);
		
		return $result;
	}
	
	
	
	
	
	
	# send an email based on passed parameters
	function email($message, $sendMethod = 'unverified')
	{
		log_message('debug', '_messenger/email');
		log_message('debug', '_messenger/email:: [1] message='.json_encode($message).' sendMethod='.$sendMethod);
		
		# do not proceed if the user unsubscribed
		if($this->_query_reader->get_count('check_if_user_unsubscribed_by_email', array('email_address'=>$message['emailaddress'])) > 0) {
			return FALSE;
		}

		# make sure you only use SES in production environment
		if(ENVIRONMENT != 'production' && $sendMethod == 'ses') $sendMethod = 'server';
		if(ENVIRONMENT == 'production') $sendMethod = 'ses';
		
		
		# send using the Amazon SES
		if($sendMethod == 'ses'){
			require_once APPPATH."libraries/ses/SimpleEmailServiceRequest.php";
			require_once APPPATH."libraries/ses/SimpleEmailServiceMessage.php";
			require_once APPPATH."libraries/ses/SimpleEmailService.php";
			
			# create the message object
			$m = new SimpleEmailServiceMessage();
			
			$m->addTo($message['emailaddress']);
			$m->setFrom($message['emailfrom']);
			$m->addReplyTo($message['emailfrom']);
			$m->setSubject($message['subject']);
			# parameters $m->setMessageFromString($plainTextBody, $HTMLBody); 
			if(!empty($message['__format']) && $message['__format'] == 'text') $m->setMessageFromString($message['details'],'');
			else $m->setMessageFromString('',$message['details']);
			
			if(!empty($message['fileurl'])) $m->addAttachmentFromFile(basename($message['fileurl']), $message['fileurl'], mime_content_type($message['fileurl']));
			
			if(!empty($message['cc'])) $m->addCC((is_array($message['cc'])? $message['cc']: explode(',',$message['cc'])));
			if(!empty($message['bcc'])) $m->addBCC((is_array($message['bcc'])? $message['bcc']: explode(',',$message['bcc'])));
			
			# copy admin if he is not the sender
			if((!empty($message['copyadmin']) && $message['copyadmin'] == 'Y') && $message['emailfrom'] != SITE_ADMIN_MAIL) {
				$m->addBCC(SITE_ADMIN_MAIL);
			}
			
			# finaly send the message
			$ses = new SimpleEmailService(SES_ACCESS_KEY, SES_SECRET);
			$response = $ses->sendEmail($m);
			
			# use this line to check the AWS api response
			# echo "SES API RESPONSE: "; print_r($response);
			log_message('debug', '_messenger/email:: [3] SES response = '.json_encode($response));
			$isSent = !empty($response['MessageId']);
		} 
		
		
		
		# send using the system servers
		else if($sendMethod == 'server') {
			$message['fromname'] = !empty($message['fromname'])? $message['fromname']: SITE_TITLE;
			
			$this->email->to($message['emailaddress']);
			$this->email->from($message['emailfrom'], $message['fromname']);
			$this->email->reply_to($message['emailfrom'], $message['fromname']);
			$this->email->subject($message['subject']);
			$this->email->message($message['details']);	
			
			if(!empty($message['fileurl'])) $this->email->attach($message['fileurl']);

			if(!empty($message['cc'])) $this->email->cc($message['cc']);
			if(!empty($message['bcc'])) $this->email->bcc($message['bcc']);
			
			# copy admin if he is not the sender
			if((!empty($message['copyadmin']) && $message['copyadmin'] == 'Y') && $message['emailfrom'] != SITE_ADMIN_MAIL) {
				$this->email->bcc(SITE_ADMIN_MAIL);
			}
			
			# use this line to test sending of email without actually sending it
			#echo $this->email->print_debugger();
			
			$isSent = $this->email->send();
			$this->email->clear(TRUE);
		}
		
		
		
		
		
		# send using the unverified messaging servers
		else $isSent = server_curl(INVITATION_SERVER_URL, array('__action'=>'send_to_unverified_email', 'return'=>'plain', 'message'=>$message));
		
		log_message('debug', '_messenger/email:: [2] isSent='.$isSent);
		return $isSent;
	}
	
	
	
	
	
	
	
	
	# add a disclaimer to the email message
	function add_disclaimer($email)
	{
		log_message('debug', '_messenger/add_disclaimer');
		$style = "style='font-family:Arial, Helvetica, sans-serif; font-size:10px; font-weight:bold;color:#999;'";
		
		return "<hr />
		<span ".$style.">We don't check this mailbox, so please don't reply to this message. If you have a question, go to our <a href='https://www.clout.com/page/contact' ".$style.">Contact Page</a>. To stop receiving these messages, click here to  <a href='https://www.clout.com/x/".encrypt_value($email)."' ".$style.">unsubscribe</a>.
<br>
<br>Clout sent this message to ".$email.".
<br>Clout is committed to your privacy. Learn more about our <a href='https://www.clout.com/page/privacy' ".$style.">Privacy Policy</a> and <a href='https://www.clout.com/page/terms' ".$style.">User Agreement</a>.
<br>&copy;".@date('Y')." CLT Inc., 8306 Wilshire Blvd. #300, Beverly Hills, CA 90211</span>";
	}
	
	
	
	
	
	
	
	#Load queries into the message file
	public function load_messages_into_cache()
	{
		log_message('debug', '_messenger/load_messages_into_cache');
		$messages = $this->db->query("SELECT * FROM message_templates")->result_array();
		
		#Now load the queries into the file
		file_put_contents(MESSAGE_FILE, "<?php ".PHP_EOL."global \$sysMessage;".PHP_EOL); 
		foreach($messages AS $message)
		{
			$messageString = "\$sysMessage['".$message['message_type']."'] = array('id'=>\"".$message['id']."\", 'message_type'=>\"".$message['message_type']."\", 'subject'=>\"".str_replace('"', '\"', $message['subject'])."\", 'details'=>\"".str_replace('"', '\"', $message['details'])."\", 'sms'=>\"".str_replace('"', '\"', $message['sms'])."\", 'copy_admin'=>'".$message['copy_admin']."', 'date_entered'=>\"".$message['date_entered']."\", '_entered_by'=>\"".$message['_entered_by']."\", 'last_updated'=>\"".$message['last_updated']."\", '_last_updated_by'=>\"".$message['_last_updated_by']."\");".PHP_EOL;  
			file_put_contents(MESSAGE_FILE, $messageString, FILE_APPEND);
		}
		
		file_put_contents(MESSAGE_FILE, PHP_EOL.PHP_EOL." function get_sys_message(\$code) { ".PHP_EOL."global \$sysMessage; ".PHP_EOL."return !empty(\$sysMessage[\$code])? \$sysMessage[\$code]: '';".PHP_EOL." }".PHP_EOL, FILE_APPEND); 
		
		echo "MESSAGE CACHE FILE HAS BEEN UPDATED [".date('F d, Y H:i:sA T')."]";
	}
	
}

?>