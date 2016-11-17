<?php
/**
 * Logs events on the system.
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 07/24/2015
 */
class _logger extends CI_Model
{
	
	# Add an event
	function add_event($eventDetails)
	{
		return $this->_query_reader->run('add_event_log', array(
			'user_id'=>(!empty($eventDetails['user_id'])? $eventDetails['user_id']: ''), 
			'activity_code'=>$eventDetails['activity_code'], 
			'result'=>$eventDetails['result'], 
			'uri'=>(!empty($eventDetails['uri'])? $eventDetails['uri']: uri_string()), 
			'log_details'=>$eventDetails['log_details'], 
			'ip_address'=>(!empty($eventDetails['ip_address'])? $eventDetails['ip_address']: $this->input->ip_address())
		));
	}
		
}


?>