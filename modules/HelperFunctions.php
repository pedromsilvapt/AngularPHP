<?php

define('DBDateFormat', 'Y-m-d G:i:s');

function getDBFormatedDATE($timespan = ''){
	if ($timespan == ''){
		$timespan = time();
	}
	return(date(DBDateFormat, $timespan));
}

function getFormatedDate($timespan = ''){
	if ($timespan == ''){
		$timespan = time();
	}
	
	return(date('F jS, Y', $timespan));
}

function getFormatedDateTime($timespan = ''){
	if ($timespan == ''){
		$timespan = time();
	}
	
	return(date('F jS, Y, \a\t g:i a', $timespan));
}

function getFormatedDateFromDB($date){
	$date = date_create_from_format(DBDateFormat, $date);
	return(getFormatedDate($date->getTimestamp()));
}

function getFormatedDateTimeFromDB($date){
	$date = date_create_from_format(DBDateFormat, $date);
	return(getFormatedDateTime($date->getTimestamp()));
}


/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param string $email The email address
 * @param string $s Size in pixels, defaults to 80px [ 1 - 512 ]
 * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
 * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
 * @param boole $img True to return a complete IMG tag False for just the URL
 * @param array $atts Optional, additional key/value attributes to include in the IMG tag
 * @return String containing either just a URL or a complete image tag
 * @source http://gravatar.com/site/implement/images/php/
 */
function getGravatar( $email, $s = 80, $d = 'mm', $r = 'pg', $img = false, $atts = array() ) {
	$url = 'http://www.gravatar.com/avatar/';
	$url .= md5( strtolower( trim( $email ) ) );
	$url .= "?s=$s&d=$d&r=$r";
	if ( $img ) {
		$url = '<img src="' . $url . '"';
		foreach ( $atts as $key => $val )
			$url .= ' ' . $key . '="' . $val . '"';
		$url .= ' />';
	}
	return $url;
}


function validateEmail($email){
	$normal = "^[a-z0-9_\+-]+(\.[a-z0-9_\+-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*\.([a-z]{2,4})$"; 
	
	if (preg_match($normal, $email) === false){
		return(false);
	} else {
		return(true);
	}
}

?>