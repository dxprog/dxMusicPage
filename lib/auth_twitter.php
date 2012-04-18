<?php

// Include the TwitterOAuth framework
require_once ('twitteroauth/twitteroauth.php');
 
/**
 * Keys to be used with Twitter's OAuth stuff
 */
$GLOBALS["_twitterKey"] = Dx::getOption('twitter_key');
$GLOBALS["_twitterSecret"] = Dx::getOption('twitter_secret');

/**
 * Returns the URL to allow the user to login via Twitter
 */
function auth_getTwitterUrl ()
{

	global $_twitterKey, $_twitterSecret, $_baseURI;

	// Create our twitter object
	$to = new TwitterOAuth ($_twitterKey, $_twitterSecret);
	$token = '';
	
	// Get or generate the OAuth token as need. Twitter tokens do not expire, so set a cookie for a damn long time
	if (isset($_COOKIE['twitterToken'])) {
		$token = $_COOKIE['twitterToken'];
	} else {
		$token = $to->getRequestToken();
		setcookie('twitterToken', $token['oauth_token'], time() + 86400, '/');
		setcookie('twitterSecret', $token['oauth_token_secret'], time() + 86400, '/');
	}

	$url = $to->getAuthorizeUrl($token);
	
	return $url;

}

/**
 * Returns the user's information
 */
function auth_getTwitterUser($token)
{
	
	global $_twitterKey, $_twitterSecret;
	
	$retVal = '';
		
	$authToken = $token['oauth_token'];
	$authSecret = $token['oauth_token_secret'];
	$to = new TwitterOAuth($_twitterKey, $_twitterSecret, $authToken, $authSecret);
	$retVal = $to->OAuthRequest("https://twitter.com/account/verify_credentials.json", "GET");
	
	return json_decode($retVal);
	
}

/**
 * Retrieves the Twitter access token
 */
function auth_getTwitterAccessToken()
{
	
	global $_twitterKey, $_twitterSecret;
	
	$retVal = '';

	if (isset($_COOKIE['twitterToken']) && isset($_COOKIE['twitterSecret'])) {
		$authToken = $_COOKIE['twitterToken'];
		$authSecret = $_COOKIE['twitterSecret'];
		$to = new TwitterOAuth($_twitterKey, $_twitterSecret, $authToken, $authSecret);
		$token = $to->getAccessToken();
		setcookie('twitterToken', $token['oauth_token'], time() + 86400, '/');
		setcookie('twitterSecret', $token['oauth_token_secret'], time() + 86400, '/');
	}
	
	return $token;
	
}

function auth_signout()
{
	setcookie('twitterToken', '', time() - 86400);
	setcookie('twitterSecret', '', time() - 86400);
	setCookie('authType', '', time() - 86400);
}