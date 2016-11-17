<?php 
/**
 * This file helps with checking and applying rules
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 12/11/2015
 */


# check if a rule applies for the given parameters
function rule_check($obj, $code, $parameters=array())
{
	log_message('debug', 'rules_helper/rule_check');
	log_message('debug', 'rules_helper/rule_check:: [1] array='.json_encode(array('__action'=>'rule_check', 'return'=>'plain', 'code'=>$code, 'parameters'=>$parameters)));
	
	return server_curl(IAM_SERVER_URL, array('__action'=>'rule_check', 'return'=>'plain', 'code'=>$code, 'parameters'=>$parameters)); 
}



# apply a rule based on the passed parameters
function apply_rule($obj, $code, $parameters=array())
{
	log_message('debug', 'rules_helper/apply_rule');
	log_message('debug', 'rules_helper/apply_rule:: [1] array='.json_encode(array('__action'=>'apply_rule', 'return'=>'plain', 'code'=>$code, 'parameters'=>$parameters)));
	
	return server_curl(IAM_SERVER_URL, array('__action'=>'apply_rule', 'return'=>'plain', 'code'=>$code, 'parameters'=>$parameters)); 
}

