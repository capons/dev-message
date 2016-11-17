<?php
/**
 * Used to define all messages sent to users.
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 07/23/2015
 */





#Check the sending settings for a user
function check_sending_settings($obj, $userId, $format, $messageType='all') 
{
	log_message('debug', 'communication_helper/check_sending_settings');
	log_message('debug', 'communication_helper/check_sending_settings:: [1] array='.json_encode(array('user_id'=>$userId, 'message_format'=>$format, 'message_type'=>$messageType)));
	$channel = $obj->_query_reader->get_row_as_array('get_sending_format', array('user_id'=>$userId, 'message_format'=>$format, 'message_type'=>$messageType));
	
	return !empty($channel);
}



?>