<?php
require_once('Social.class.php');

class Login
{

	private $_social = null;

	const FACEBOOK = 'MeinFaceBook';
	const TWITTER = 'MeinTwitter';
	const GOOGLE = 'MeinGoogle';

	private function __construct($type = self::FACEBOOK, $appKey = '', $appSecret = '', $cbUrl = '')
	{
		if(class_exists($type)){
			$this->_social = new $type($appKey, $appSecret, $cbUrl);
		}
		else{
			die('Social Type ' . $type . ' Not Implemented.');
		}
	}
	
	public static function getInstance($type = self::FACEBOOK, $appKey = '', $appSecret = '', $cbUrl = '')
	{
		$class = __CLASS__;
		return new $class($type, $appKey, $appSecret, $cbUrl);
	}

	public function validate()
	{
		return $this->_social->validate();
	}

	public function login()
	{
		return $this->_social->login();
	}

	public function getAuthUrl()
	{
		return $this->_social->getAuthUrl();
	}
}
?>