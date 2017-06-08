<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

abstract class Social
{
	
	protected $_appKey = null;

	protected $_appSecret = null;

	protected $_cbUrl = null;

	protected $_authUrl = null;
	
	protected $_log = null;

	public function initlog()
	{
		$logName = 'meinlogin_log-' . date("Y-m-d") . '.log';

		$this->_log = new Logger('Login');
		$this->_log->pushHandler(new StreamHandler($logName, Logger::ERROR));
	}

	public function validate()
	{
		return (isset($_SESSION['SOCIAL_TYPE']));
	}

	public function getAuthUrl()
	{
		return $this->_authUrl;
	}

	public function createSession($type, $id, $name, $last, $link, $profileImg, $email, $session)
	{
		$_SESSION['SOCIAL_TYPE'] = $type;
		$_SESSION['SOCIAL_ID'] = $id;
		$_SESSION['SOCIAL_NAME'] = $name;
		$_SESSION['SOCIAL_LNAME'] = $last;
		$_SESSION['SOCIAL_LINK'] = $link;
		$_SESSION['SOCIAL_IMG'] = $profileImg;
		$_SESSION['SOCIAL_MAIL'] = $email;
		$_SESSION['SOCIAL_SESSION'] = $session;
	}

	public abstract function login();
}

class MeinFaceBook extends Social
{

	private $fb;


	public function __construct($appKey, $appSecret, $cbUrl)
	{
		if (session_status() == PHP_SESSION_NONE) {
		    session_start();
		}

		$this->initlog();

		$this->_appKey = $appKey;
		$this->_appSecret = $appSecret;
		$this->_cbUrl = $cbUrl;

		try{
			$this->fb = new Facebook\Facebook([
				'app_id' => $this->_appKey,
				'app_secret' => $this->_appSecret,
				'default_graph_version' => 'v2.8']);
		}
		catch(Exception $e){
        	$this->_log->addError($e->getMessage());
        }
	}

	public function login()
	{
		$retValue = false;

		try{
	        $helper = $this->fb->getRedirectLoginHelper();

	        try {
	        	$accessToken = $helper->getAccessToken();
			} 
			catch(Facebook\Exceptions\FacebookResponseException $e) {
				$this->_log->addError($e->getMessage());
			} 
			catch(Facebook\Exceptions\FacebookSDKException $e) {
				$this->_log->addError($e->getMessage());
			}

	        if (isset($accessToken)){
	        	try {
	        		$response = $this->fb->get('/me?fields=id,name,link,email,first_name,last_name', $accessToken);

	        		$user = $response->getGraphUser();
		        	
		        	$fbid = $user['id'];
		        	$fbname = $user['first_name'];
		        	$fblast = $user['last_name'];
		        	$fblink = $user['link'];
		            $fbimg = 'https://graph.facebook.com/' . $fbid . '/picture?type=large';
		            $fbMail  = $user['email'];
		            $session = $accessToken;

		            $this->createSession('FB', $fbid, $fbname, $fblast, $fblink, $fbimg, $fbMail, $session);

		            $retValue = true;
				} 
				catch(Facebook\Exceptions\FacebookResponseException $e) {
					$this->_log->addError($e->getMessage());
				} 
				catch(Facebook\Exceptions\FacebookSDKException $e) {
					$this->_log->addError($e->getMessage());
				}
	        } 
	        else{
	        	$permissions = ['email'];
	        	
	        	$this->_authUrl = $helper->getLoginUrl($this->_cbUrl, $permissions);
	        }
		}
		catch(Exception $e){
        	$this->_log->addError($e->getMessage());
        }

		return $retValue;
	}
}


class MeinTwitter extends Social
{
	public function __construct($appKey, $appSecret, $cbUrl)
	{
		if (session_status() == PHP_SESSION_NONE) {
		    session_start();
		}

		$this->initlog();
		
		$this->_appKey = $appKey;
		$this->_appSecret = $appSecret;
		$this->_cbUrl = $cbUrl;
	}

	public function login()
	{
		$retValue = false;

		try{
			if(isset($_REQUEST['oauth_token']) && $_SESSION['token'] == $_REQUEST['oauth_token']){
				$tw = new \TwitterOAuth\Api($this->_appKey, $this->_appSecret, $_SESSION['token'], $_SESSION['token_secret']);
	            $access_token = $tw->getAccessToken($_REQUEST['oauth_verifier']);

	            if($tw->http_code=='200')
	            {
	                $_SESSION['status'] = 'verified';
	                $_SESSION['request_vars'] = $access_token;
	                
	                $twid   = $_SESSION['request_vars']['user_id'];
	                $twname = $_SESSION['request_vars']['screen_name'];
	                $twlast = $_SESSION['request_vars']['screen_name'];
	                $twlink = 'https://twitter.com/intent/user?user_id=' . $twid;
	                $twImg  = 'https://twitter.com/' . $twname . '/profile_image?size=original';
	                $twMail = 'twitter@twitter.com';

	            	$this->createSession('TW', $twid, $twname, $twlast, $twlink, $twImg, $twMail, $access_token);

	                unset($_SESSION['token']);
	                unset($_SESSION['token_secret']);
	                
	                $retValue = true;
	            }
	            else{
	            	$this->_log->addError($e->getMessage("Twitter error, try again later!"));
	            }
	        }
	        else{
	            $tw = new \TwitterOAuth\Api($this->_appKey, $this->_appSecret);
	            $request_token = $tw->getRequestToken($this->_cbUrl);

	            $_SESSION['token']        = $request_token['oauth_token'];
	            $_SESSION['token_secret'] = $request_token['oauth_token_secret'];
	            
	            if($tw->http_code=='200'){
	                $this->_authUrl = $tw->getAuthorizeURL($request_token['oauth_token']);
	            }
	            else{
	            	$this->_log->addError($e->getMessage("error connecting to Twitter! try again later!"));
	            }
	        }
		}
		catch(Exception $e){
        	$this->_log->addError($e->getMessage());
        }

		return $retValue;
	}
}

class MeinGoogle extends Social
{

	public function __construct($appKey, $appSecret, $cbUrl)
	{
		if (session_status() == PHP_SESSION_NONE) {
		    session_start();
		}

		$this->initlog();
		
		$this->_appKey = $appKey;
		$this->_appSecret = $appSecret;
		$this->_cbUrl = $cbUrl;
	}
	public function login()
	{
		$retValue = false;

		try{
			$client = new Google_Client();
			$client->setClientId($this->_appKey);
			$client->setClientSecret($this->_appSecret);
			$client->setRedirectUri($this->_cbUrl);

			$client->addScope("email");
			$client->addScope("profile");

			$service = new Google_Service_Oauth2($client);

			if (isset($_GET['code'])) {
				$client->authenticate($_GET['code']);

				$_SESSION['access_token'] = $client->getAccessToken();
			}

			if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
				$client->setAccessToken($_SESSION['access_token']);

				$user = $service->userinfo->get();

				$gpid   = $user->getId();
                $gpname = $user->getGivenName();
                $gplast = $user->getFamilyName();
                $gplink = $user->getLink();
                $gpImg  = $user->getPicture();
                $gMail  = $user->getEmail();
                $access_token = $_SESSION['access_token'];

            	$this->createSession('GP', $gpid, $gpname, $gplast, $gplink, $gpImg, $gMail, $access_token);

            	$retValue = true;
			} 
			else {
				$this->_authUrl = $client->createAuthUrl();
			}
		}
		catch(Exception $e){
        	$this->_log->addError($e->getMessage());
        }

		return $retValue;
	}
}
?>