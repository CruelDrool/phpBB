<?php
/**
*
* recaptcha [English]
*
* @package language
* @version $Id$
* @copyright (c) 2009 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'CAPTCHA_RECAPTCHA'				=> 'reCAPTCHA v2 & v3',
	'CAPTCHA_RECAPTCHA_EXPLAIN'		=> 'Protects you against spam and other types of automated abuse. reCAPTCHA uses an advanced risk analysis engine and adaptive challenges to keep malicious software from engaging in abusive activities on your website.',
	
	'RECAPTCHA_NOT_AVAILABLE'			=> 'reCAPTCHA has not been set up properly. Please contact the <a href=mailto:%s>Board Administrator</a>.',
	'RECAPTCHA_PREVIEW_NOT_AVAILABLE'	=> 'Make sure you have reCAPTCHA set up properly.',
	'RECAPTCHA_NOSCRIPT'				=> 'Please enable JavaScript in your browser to load the reCAPTCHA widget.',

	'RECAPTCHA_PUBLIC'				=> 'Site key',
	'RECAPTCHA_PUBLIC_EXPLAIN'		=> 'Your public site key that is used to load the widget. Keys can be obtained on <a href="//www.google.com/recaptcha">www.google.com/recaptcha</a>.',
	'RECAPTCHA_PRIVATE'				=> 'Secret key',
	'RECAPTCHA_PRIVATE_EXPLAIN'		=> 'Your private secret key for communication between your site and the reCAPTCHA verification server. Keys can be obtained on <a href="//www.google.com/recaptcha">www.google.com/recaptcha</a>.',
	
	'RECAPTCHA_EXPLAIN'				=> 'In an effort to prevent automatic submissions, we require that you complete the following challenge.',
	
	'RECAPTCHA_VERIFY_ORIGIN'			=> 'Verify origin of the solutions',
	'RECAPTCHA_VERIFY_ORIGIN_EXPLAIN'	=> '<strong>NB!</strong> Only required if you have chosen not to have Google verify the origin of the solutions. Read more about it on <a href="//developers.google.com/recaptcha/docs/domain_validation">developers.google.com/recaptcha/docs/domain_validation</a>.',
		
	'RECAPTCHA_LANGUAGE'			=> 'Language code',
	'RECAPTCHA_LANGUAGE_EXPLAIN'	=> 'Language of the widget. Leave it blank to auto-detect the language. Read more about language codes on <a href="//developers.google.com/recaptcha/docs/language">developers.google.com/recaptcha/docs/language</a>.',
	
	'RECAPTCHA_DOMAIN'				=> 'Request domain',
	'RECAPTCHA_DOMAIN_EXPLAIN'		=> 'The domain to fetch the script from, and to use when verifying requests.<br>Use <samp>recaptcha.net</samp> when <samp>google.com</samp> is not accessible.',
	
	'RECAPTCHA_VERSION'						=> 'Version',
	'RECAPTCHA_VERSION_EXPLAIN'				=> 'Select your reCAPTCHA version. Make sure to use site key and secret key for your selected version. Read more about the versions on <a href="//developers.google.com/recaptcha/docs/versions">developers.google.com/recaptcha/docs/versions</a>.',
	
	'RECAPTCHA_THEME'		 				=> 'Theme',
	'RECAPTCHA_THEME_LIGHT'					=> 'Light',
	'RECAPTCHA_THEME_DARK'					=> 'Dark',
	'RECAPTCHA_THEME_DARK_EXPLAIN'			=> 'The color theme of the widget.',

	'RECAPTCHA_PLACEMENT'					=> 'Placement',
	'RECAPTCHA_PLACEMENT_EXPLAIN'			=> 'Position of the widget.',
	'RECAPTCHA_PLACEMENT_BOTTOMLEFT'		=> 'Bottom left',
	'RECAPTCHA_PLACEMENT_BOTTOMRIGHT'		=> 'Bottom right',
	'RECAPTCHA_PLACEMENT_INLINE'			=> 'In-line',

	'RECAPTCHA_V2_CHECKBOX'					=> 'v2 "I\'m not a robot" Checkbox',
	'RECAPTCHA_V2_CHECKBOX_INCORRECT'		=> 'The CAPTCHA solution you provided was incorrect.',
	'RECAPTCHA_V2_CHECKBOX_PREVIEW_EXPLAIN'	=> '',
	'RECAPTCHA_V2_CHECKBOX_SIZE' 			=> 'Size',
	'RECAPTCHA_V2_CHECKBOX_SIZE_EXPLAIN' 	=> 'The size of the widget.',
	'RECAPTCHA_V2_CHECKBOX_SIZE_NORMAL' 	=> 'Normal',
	'RECAPTCHA_V2_CHECKBOX_SIZE_COMPACT' 	=> 'Compact',
	
	'RECAPTCHA_V2_INVISIBLE'					=> 'v2 Invisible',
	'RECAPTCHA_V2_INVISIBLE_INCORRECT'			=> 'The CAPTCHA solution you provided was incorrect.',
	'RECAPTCHA_V2_INVISIBLE_PREVIEW_EXPLAIN'	=> 'As a preview, the widget is shown in-line. This also makes it wider than when it\'s placed bottom right/left.',
	
	'RECAPTCHA_V3' 								=> 'v3',
	'RECAPTCHA_V3_INCORRECT'					=> 'reCAPTCHA v3 returns a score based on your interaction with this site. Your score did not meet our threshold requirement set for this particular action.',
	'RECAPTCHA_V3_PREVIEW_EXPLAIN'				=> 'As a preview, the widget is shown in-line. This also makes it wider than when it\'s placed bottom right/left.',
	'RECAPTCHA_V3_THRESHOLD_DEFAULT'			=> 'Default threshold',
	'RECAPTCHA_V3_THRESHOLD_DEFAULT_EXPLAIN'	=> 'Used when none of the other actions are applicable.',
	'RECAPTCHA_V3_THRESHOLD_LOGIN'				=> 'Login threshold',
	'RECAPTCHA_V3_THRESHOLD_POST'				=> 'Post threshold',
	'RECAPTCHA_V3_THRESHOLD_REGISTER'			=> 'Register threshold',
	'RECAPTCHA_V3_THRESHOLD_REPORT'				=> 'Report threshold',
	'RECAPTCHA_V3_THRESHOLDS'					=> 'Thresholds',
	'RECAPTCHA_V3_THRESHOLDS_EXPLAIN'			=> 'reCAPTCHA v3 returns a score (<samp>1.0</samp> is very likely a good interaction, <samp>0.0</samp> is very likely a bot). Here you can set the minimum score per action.',	
	'RECAPTCHA_V3_LOAD_ALL'						=> 'Load on all pages',
	'RECAPTCHA_V3_LOAD_ALL_EXPLAIN'				=> 'For analytics purposes, it\'s recommended to load the widget in the background of all pages.',
	'RECAPTCHA_V3_LOAD_ALL_GUESTS_ONLY'			=> 'Only for guest users',
));

?>