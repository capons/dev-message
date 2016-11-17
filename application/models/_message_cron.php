<?php
/**
 * Handles messaging cron job functionality on the system.
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 02/10/2016
 */
class _message_cron extends CI_Model
{
	
	#Constructor to set some default values at class load
	public function __construct()
    {
        parent::__construct();
	}
	
	
	
	# activate more messages for sending
	function activate_more_messages()
	{
		log_message('debug', '_message_cron/activate_more_messages');
		# get users with paused invitations
		$users = $this->_query_reader->get_single_column_as_array('get_invitation_list_users', 'user_id', array('status'=>'paused', 'limit_text'=>' LIMIT '.MAXIMUM_INVITE_BATCH_LIMIT));
		log_message('debug', '_message_cron/activate_more_messages:: [1] users='.json_encode($users));
		# check if the user has the limit rule applying to them
		$results = array();
		foreach($users AS $i=>$user){
			if(rule_check('invite_daily_limit_10', array('user_id'=>$user))) {
				$results[$i] = $this->_query_reader->run('update_invite_status_with_limit', array('user_id'=>$user, 'limit_text'=>' LIMIT 10 ', 'status_condition'=>" AND message_status = 'paused' ", 'new_status'=>'pending')); 
			}
			else if(rule_check('invite_daily_limit_30', array('user_id'=>$user))) {
				$results[$i] = $this->_query_reader->run('update_invite_status_with_limit', array('user_id'=>$user, 'limit_text'=>' LIMIT 30 ', 'status_condition'=>" AND message_status = 'paused' ", 'new_status'=>'pending')); 
			}
			else if(rule_check('invite_daily_limit_unlimited', array('user_id'=>$user))) {
				$results[$i] = $this->_query_reader->run('update_invite_status_with_limit', array('user_id'=>$user, 'limit_text'=>'', 'status_condition'=>" AND message_status = 'paused' ", 'new_status'=>'pending')); 
			}
		}	
		log_message('debug', '_message_cron/activate_more_messages:: [2] results='.json_encode($results));
		
		$reason = empty($results)? 'no-users': '';
		return array('result'=>(get_decision($results)? 'SUCCESS': 'FAIL'), 'count'=>count($results), 'reason'=>$reason);
	}
	
	
	
	




	# send first invitation reminder
	function send_first_reminder()
	{
		log_message('debug', '_message_cron/send_first_reminder');
		# get non-responsive invites that are more than FIRST_INVITE_PERIOD days old
		$list = $this->_query_reader->get_list('get_non_responsive_invitations', array('days_old'=>FIRST_INVITE_PERIOD, 'limit_text'=>' LIMIT '.MAXIMUM_INVITE_BATCH_LIMIT, 'old_message_code'=>'invitation_to_join_clout'));
		log_message('debug', '_message_cron/send_first_reminder:: [1] list='.json_encode($list));
		# resend old invite as a reminder
		$results = array();
		foreach($list AS $i=>$row){
			$results[$i] = $this->_query_reader->run('resend_old_invite', array('invite_id'=>$row['invite_id'], 'new_code'=>'first_reminder_to_join_clout', 'new_status'=>'pending'));
		}
		log_message('debug', '_message_cron/send_first_reminder:: [2] results='.json_encode($results));
		$reason = empty($results)? 'no-users': '';
		return array('result'=>(get_decision($results)? 'SUCCESS': 'FAIL'), 'count'=>count($results), 'reason'=>$reason);
	}
	
	
	



	# send second invitation reminder
	function send_second_reminder()
	{
		log_message('debug', '_message_cron/send_second_reminder');
		# get non-responsive invites that are more than FIRST_INVITE_PERIOD days old
		$list = $this->_query_reader->get_list('get_non_responsive_invitations', array('days_old'=>SECOND_INVITE_PERIOD, 'limit_text'=>' LIMIT '.MAXIMUM_INVITE_BATCH_LIMIT, 'old_message_code'=>'first_reminder_to_join_clout'));
		log_message('debug', '_message_cron/send_second_reminder:: [1] list='.json_encode($list));
		# check if the user has the limit rule applying to them
		$results = array();
		foreach($list AS $i=>$row){
			$results[$i] = $this->_query_reader->run('resend_old_invite', array('invite_id'=>$row['invite_id'], 'new_code'=>'second_reminder_to_join_clout', 'new_status'=>'pending'));
		}
		log_message('debug', '_message_cron/send_second_reminder:: [2] results='.json_encode($results));
		if(empty($results)) $reason = 'no-users';
	
		return array('result'=>(get_decision($results)? 'SUCCESS': 'FAIL'), 'count'=>count($results), 'reason'=>$reason);
	}




	
	# send pending invitations
	function send_pending_invitations()
	{
		log_message('debug', '_message_cron/send_pending_invitations');
		$list = $this->_query_reader->get_list('get_invitation_list', array('status'=>'pending', 'limit_text'=>' LIMIT '.MAXIMUM_INVITE_BATCH_LIMIT));
		
		log_message('debug', '_message_cron/send_pending_invitations:: [1] list='.json_encode($list));
		$runCount = count($list);
		$codes = array();
		$reason = '';
		
		
		foreach($list AS $row){
			$result = $sresult = $this->_messenger->send_direct_email($row['email_address'], $row['user_id'], array('emailaddress'=>$row['email_address'], 'code'=>$row['invite_message'], 'joinlink'=>$row['join_link']), 'unverified');
			
			# yay! one successful send
			if(empty($sendResult) && $result) $sendResult = TRUE;
			if(!in_array($row['invite_message'], $codes)) array_push($codes, $row['invite_message']);
			
			# now record the result of the message invite
			$result = $this->_query_reader->run('update_invite_status', array('invite_id'=>$row['invite_id'], 'message_status'=>($result? 'sent': 'bounced')));
			
			# cancel invitations that have failed to be sent twice
			if($row['invite_message'] == 'first_reminder_to_join_clout' && !$sresult) {
				$result = $this->_query_reader->run('cancel_invitation_message', array('invite_id'=>$row['invite_id']));
			}
		}
		
		# there were no sucessful sends
		if(empty($sendResult) && empty($list)) {
			$sendResult = FALSE;
			$reason = "no-users";
		}
		else if(empty($sendResult)) $sendResult = FALSE; 
		log_message('debug', '_message_cron/send_pending_invitations:: [2] result='.json_encode(array('result'=>($sendResult? 'SUCCESS': 'FAIL'), 'count'=>$runCount, 'codes'=>$codes, 'reason'=>$reason)));
		
		return array('result'=>($sendResult? 'SUCCESS': 'FAIL'), 'count'=>$runCount, 'codes'=>$codes, 'reason'=>$reason);
	}





	
	# send pending messages
	function send_pending_messages()
	{
		log_message('debug', '_message_cron/send_pending_messages');
		$list = $this->_query_reader->get_list('get_message_exchange_list', array('status'=>'pending', 'template_type'=>'user', 'limit_text'=>' LIMIT '.MAXIMUM_INVITE_BATCH_LIMIT));
		log_message('debug', '_message_cron/send_pending_messages:: [1] list='.json_encode($list));
		
		$runCount = count($list);
		
		if(!empty($list)){
			# go through the list and send out the messages by exchange record
			$results = array();
			foreach($list AS $row){
				if($row['send_email'] == 'Y' && $row['send_email_result'] == 'pending') $results[] = $this->send_exchange($row['id'], 'email');
				if($row['send_sms'] == 'Y' && $row['send_sms_result'] == 'pending') $results[] = $this->send_exchange($row['id'], 'sms');
				if($row['send_system'] == 'Y' && $row['send_system_result'] == 'pending') $results[] = $this->send_exchange($row['id'], 'system');
			}
			
			$sendResult = get_decision($results);
			if(!$sendResult) $reason = "Some messages failed.";
		}
		else {
			$sendResult = FALSE;
			$reason = "No pending messages.";
		}
	}




	
	
	
	
	# send exchange
	function send_exchange($id, $method)
	{
		log_message('debug', '_message_cron/send_exchange');
		log_message('debug', '_message_cron/send_exchange:: [1] id='.$id.' method='.$method);
		$result1 = $this->_messenger->send_exchange_message($id, $method);
		$result2 = ($method == 'system')? $result1: $this->_query_reader->run('update_message_exchange_field', array('field_name'=>'send_'.$method.'_result', 'field_value'=>($result1? 'success': 'fail'), 'exchange_id'=>$id));
		
		log_message('debug', '_message_cron/send_exchange:: [1] result='.$result1 && $result2);
		
		return $result1 && $result2;
	}

	
		
}


?>