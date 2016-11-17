<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['mailtype'] = 'html';

$config['protocol'] = 'mail';

$config['charset']  = 'utf-8'; 

$config['newline']  = "\r\n"; 

$config['flag_to_redirect'] = FLAG_TO_REDIRECT; 

$config['redirect_to_emailid']   = MAILID_TO_REDIRECT; 

$config['smtp_host'] = SMTP_HOST;

$config['smtp_port'] = SMTP_PORT;

$config['smtp_user'] = SMTP_USER;

$config['smtp_pass'] = SMTP_PASS;