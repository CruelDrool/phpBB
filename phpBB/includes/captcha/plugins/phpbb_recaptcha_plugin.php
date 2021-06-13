<?php
/**
*
* @package VC
* @version $Id$
* @copyright (c) 2006, 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (!class_exists('phpbb_default_captcha'))
{
	// we need the classic captcha code for tracking solutions and attempts
	include($phpbb_root_path . 'includes/captcha/plugins/captcha_abstract.' . $phpEx);
}

/**
* @package VC
*/
class phpbb_recaptcha extends phpbb_default_captcha
{
	private const NAME = 'recaptcha';
	private const NAME_UPPER = 'RECAPTCHA';
	private const NAME_PREFIX_CONFIG = self::NAME.'_';
	private const NAME_PREFIX_TPL_VAR = self::NAME_UPPER.'_';
	private const NAME_PREFIX_LANG = self::NAME_PREFIX_TPL_VAR;
	private const API_URL_FORMAT = 'https://www.%s/recaptcha/api%s';
		
	/** @var array CAPTCHA types mapped to their action */
	private $actions = [
		0				=> 'default',
		CONFIRM_REG		=> 'register',
		CONFIRM_LOGIN	=> 'login',
		CONFIRM_POST	=> 'post',
		CONFIRM_REPORT	=> 'report',
	];
	
	/** @var array Possible domain names to load the script and verify the token */ 
	private const DOMAINS = [
		'GOOGLE' => 'google.com',
		'RECAPTCHA' => 'recaptcha.net',
	];
	
	/** @var array Default settings */
	private const DEFAULTS = [
		'domain'					=> self::DOMAINS['GOOGLE'],
		'language'					=> '',
		'placement'					=> 'bottomright',
		'privkey'					=> '',
		'pubkey'					=> '',
		'theme'						=> 'light',
		'version'					=> 'v2_checkbox',
		'v2_checkbox_size'			=> 'normal',
		'v3_load_all'				=> true,
		'v3_load_all_guests_only'	=> true,
		'v3_threshold' 				=> 0.5,
		'verify_origin'				=> false,
	];
	
	private static function get_api_script_url() {
		global $config;
		$queries = http_build_query(
			[
				'hl'		=> $config[self::NAME_PREFIX_CONFIG.'language'] ?? self::DEFAULTS['language'],
				'onload'	=> 'phpbbRecaptchaOnLoad',
				'render'	=> 'explicit'
			],'','&');
			
		$url = sprintf('%s?%s',
			sprintf(self::API_URL_FORMAT, ($config[self::NAME_PREFIX_CONFIG.'domain'] ?? self::DEFAULTS['domain']), '.js'),
			$queries,
			);
		return $url; 
	}

	private function get_html_output($version, $preview = false){
		global $config, $user;
		
		$noscript = $user->lang[self::NAME_PREFIX_LANG.'NOSCRIPT'];
				
		if ($version == 'v2_checkbox' || $preview) {
			$placement = $version == 'v2_checkbox' ? '' : 'inline';
			$size = $version == 'v2_checkbox' ? ($config[self::NAME_PREFIX_CONFIG.'v2_checkbox_size'] ?? self::DEFAULTS['v2_checkbox_size']) : null;
			return self::_get_html_output(null, $noscript, $placement, $size);
		} else { // v2 Invisible or v3.
			$action = '';
			if ($version == 'v3') {
				$action = $this->actions[$this->type] ?? reset($this->actions);
				$action = "{action: '$action'}";
			 }
		
			$script = <<<HTML

	<script>
			var phpbb = {};
			
			phpbb.recaptcha = {
				button: null,
				ready: false,
				form: null,
				findParentForm: function(elem){
					var match = null;
					while ( ( elem = elem[ "parentNode" ] ) && elem.nodeType !== 9 ) {
						if ( elem.nodeType === 1 ) {
							if ( elem.nodeName && elem.nodeName.toLowerCase() === 'form' ) {
								match = elem;
								break;
							}
						}
					}
					return match;
				},
				load: function() {
					var captchaContainer = document.getElementById("g-recaptcha");
					this.form = this.findParentForm(captchaContainer);
					grecaptcha.render(captchaContainer, {
					  'callback' : phpbbRecaptchaOnSubmit,
					});
					this.bindButton();
					this.bindForm();
				},
				bindButton: function() {
					this.form.querySelectorAll('input[type="submit"]').forEach(function (element) {
						element.addEventListener('click', function() {
							// Listen to all the submit buttons for the form that has reCAPTCHA protection,
							// and store it so we can click the exact same button later on when we are ready.
							phpbb.recaptcha.button = this;
						});
					});
				},
				bindForm: function() {
					this.form.addEventListener('submit', function(e) {
						// If ready is false, it means the user pressed a submit button.
						// And the form was not submitted by us, after the token was loaded.
						if (!phpbb.recaptcha.ready && phpbb.recaptcha.button.name != 'cancel') {
							
							grecaptcha.execute($action);
							
							// Do not submit the form
							e.preventDefault();
						}
					});
				},
				submitForm: function() {
					// Now we are ready, so set it to true.
					// so the 'submit' event doesn't run multiple times.
					this.ready = true;
					
					if (this.button) {
						// If there was a specific button pressed initially, trigger the same button
						
						// Remove any onclick events from the button, so we don't run them again.
						this.button.onclick = '';
						
						// Push the button!
						this.button.click();
					} else {
						if (typeof this.form.submit !== 'function') {
							// Rename input[name="submit"] so that we can submit the form
							phpbb.recaptcha.form.submit.name = 'submit_btn';
						}

						this.form.submit();
					}
				}
			};
			
			// reCAPTCHA doesn't accept callback functions nested inside objects
			// so we need to make this helper functions here
			window.phpbbRecaptchaOnLoad = function() {
				phpbb.recaptcha.load();
			};

			window.phpbbRecaptchaOnSubmit = function() {
				phpbb.recaptcha.submitForm();
			};
		</script>	
HTML;
			return self::_get_html_output($script, $noscript);
		}
	}
	
	private static function _get_html_output($script = null, $noscript = null, $placement = null, $size = null) {
		global $config;
		
		$pubkey = $config[self::NAME_PREFIX_CONFIG.'pubkey'] ?? self::DEFAULTS['pubkey'];
		
		if (empty($pubkey)) {return '';}
		
		$size = $size ?? 'invisible';
		$placement = $placement ?? ($config[self::NAME_PREFIX_CONFIG.'placement'] ?? self::DEFAULTS['placement']);
		$theme = $config[self::NAME_PREFIX_CONFIG.'theme'] ?? self::DEFAULTS['theme'];
		$url = self::get_api_script_url();
		$badge = !empty($placement) ? sprintf(' data-badge="%s"', $placement) : '';
		
		if (empty($script)) {
			// If no script is set, use a very simple onload callback for explicit rendering.
			$script = <<<HTML

	<script>
		window.phpbbRecaptchaOnLoad = function() {
				var captchaContainer = document.getElementById("g-recaptcha");
				grecaptcha.render(captchaContainer, {
				});
			};
	</script>
HTML;
			}
			
			$html =<<<HTML

	<div id="g-recaptcha" data-sitekey="$pubkey" data-theme="$theme" data-size="$size"$badge></div>
	$script
	<script src="$url" async defer></script>
	<noscript>
		<p>$noscript</p>
	</noscript>
HTML;
		return $html;
	}
	
	static function page_footer_output() {
		global $config;
		
		if ( ($config[self::NAME_PREFIX_CONFIG.'version'] ?? self::DEFAULTS['version']) != 'v3'  ) {return '';}
		return self::_get_html_output();
	}

	function init($type)
	{
		global $config, $db, $user;

		$user->add_lang('captcha_'.self::NAME);
		
		parent::init($type);
	}

	function is_available()
	{
		global $config, $user;
		
		$user->add_lang('captcha_'.self::NAME);
		
		return ( !empty($config[self::NAME_PREFIX_CONFIG.'pubkey'] ?? self::DEFAULTS['pubkey']) && !empty($config[self::NAME_PREFIX_CONFIG.'privkey'] ?? self::DEFAULTS['privkey']) );
	}

	/**
	*  API function
	*/
	function has_config()
	{
		return true;
	}

	function get_name()
	{
		return 'CAPTCHA_'.self::NAME_UPPER;
	}

	function get_class_name()
	{
		return 'phpbb_'.self::NAME;
	}

	function acp_page($id, &$module)
	{
		global $config, $db, $template, $user;


		$module->tpl_name = 'captcha_'.self::NAME.'_acp';
		$module->page_title = 'ACP_VC_SETTINGS';
		$form_key = 'acp_captcha';
		add_form_key($form_key);

		$submit = request_var('submit', '');
		$vars = self::DEFAULTS;
		unset($vars['v3_threshold']);
		
		if ($submit)
		{
			// Check if form is valid. Will trigger error if not valid.
			check_form_key($form_key, false, adm_back_link($module->u_action), true);
			
			foreach ($vars as $var => $default_value)
			{
				$config_name = self::NAME_PREFIX_CONFIG.$var;
				// set_config($config_name, request_var($config_name, $default_value, true));
				
				set_config($config_name, request_var($config_name, '', true));
			}
			
			foreach ($this->actions as $action)
			{
				$config_name = self::NAME_PREFIX_CONFIG."v3_threshold_{$action}";
				// set_config($config_name, request_var($config_name, self::DEFAULTS['v3_threshold']));
				
				set_config($config_name, request_var($config_name, ''));
			}
			
			add_log('admin', 'LOG_CONFIG_VISUAL');
			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($module->u_action));
		}
		else
		{
			
			foreach ($this->actions as $action)
			{
				$key = self::NAME_PREFIX_CONFIG."v3_threshold_{$action}";

				$template->assign_block_vars('thresholds', [
					'KEY'	=> $key,
					'VALUE'	=> $config[$key] ?? self::DEFAULTS['v3_threshold'],
					'L_KEY' => $user->lang[strtoupper($key)] ?? '',
					'L_KEY_EXPLAIN' => $user->lang[strtoupper($key).'_EXPLAIN'] ?? '',
				]);
			}
			
			foreach (self::DOMAINS as $name => $domain) {
				$template->assign_block_vars('domains',[
					'DOMAIN' => $domain,
				]);
			}
			
			foreach ($vars as $var => $default_value)
			{
				$config_name = self::NAME_PREFIX_CONFIG.$var;
				$value = $config[$config_name] ?? $default_value;
				
				$template->assign_var(strtoupper($config_name), $value);
			}
			
			$template->assign_vars([
				'CAPTCHA_PREVIEW'	=> $this->get_demo_template($id),
				'CAPTCHA_NAME'		=> $this->get_class_name(),
				'U_ACTION'			=> $module->u_action,
			]);

		}
	}

	// not needed
	function execute_demo()
	{
	}

	// not needed
	function execute()
	{
	}

	function get_template()
	{
		global $config, $user, $template;

		if ($this->is_solved())
		{
			return false;
		}
		else
		{			
			$explain = $user->lang(($this->type != CONFIRM_POST) ? 'CONFIRM_EXPLAIN' : 'POST_CONFIRM_EXPLAIN', '<a href="mailto:' . htmlspecialchars($config['board_contact']) . '">', '</a>');
			
			$version = $config[self::NAME_PREFIX_CONFIG.'version'] ?? self::DEFAULTS['version'];
			
			$template->assign_vars([
				self::NAME_PREFIX_TPL_VAR.'HTML'				=> $this->get_html_output($version),
				self::NAME_PREFIX_TPL_VAR.'VERSION'				=> $version,
				'S_'.self::NAME_PREFIX_TPL_VAR.'AVAILABLE'		=> $this->is_available(),
				'S_CONFIRM_CODE'								=> true,
				'S_TYPE'										=> $this->type,
				'L_CONFIRM_EXPLAIN'								=> $explain,
				'L_'.self::NAME_PREFIX_TPL_VAR.'NOT_AVAILABLE' 	=> $user->lang(self::NAME_PREFIX_LANG.'NOT_AVAILABLE', htmlspecialchars($config['board_contact'])),
			]);

			return 'captcha_recaptcha.html';
		}
	}

	function get_demo_template($id)
	{
		global $config, $user, $template;
		
		$version = $config[self::NAME_PREFIX_CONFIG.'version'] ?? self::DEFAULTS['version'];
		
		$template->assign_vars([
			self::NAME_PREFIX_TPL_VAR.'HTML'						=> $this->get_html_output($version, true),
			'L_'.self::NAME_PREFIX_TPL_VAR.'VERSION_NAME'			=> $user->lang[self::NAME_PREFIX_LANG.strtoupper($version)],
			'L_'.self::NAME_PREFIX_TPL_VAR.'VERSION_NAME_EXPLAIN'	=> $user->lang[self::NAME_PREFIX_LANG.strtoupper($version).'_PREVIEW_EXPLAIN'],
			'S_'.self::NAME_PREFIX_TPL_VAR.'AVAILABLE'				=> $this->is_available(),
			'L_'.self::NAME_PREFIX_TPL_VAR.'NOT_AVAILABLE' 			=> $user->lang[self::NAME_PREFIX_LANG.'PREVIEW_NOT_AVAILABLE'],
		]);
		return 'captcha_recaptcha.html';
	}

	function get_hidden_fields()
	{
		$hidden_fields = [];

		// this is required for posting.php - otherwise we would forget about the captcha being already solved
		if ($this->solved)
		{
			$hidden_fields['confirm_code'] = $this->code;
		}
		$hidden_fields['confirm_id'] = $this->confirm_id;
		return $hidden_fields;
	}

	function uninstall()
	{
		$this->garbage_collect(0);
	}

	function install()
	{
		return;
	}

	function validate()
	{
		if (!parent::validate())
		{
			return false;
		}

		return $this->recaptcha_check_answer();
	}

	/**
	* Submits an HTTPS POST with cURL to a reCAPTCHA server
	* @param string $url
	* @param array $params
	* @return array response
	*/
	function _recaptcha_http_post($url, $params)
	{
        $handle = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params, '', '&'),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLINFO_HEADER_OUT => false,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ];
        
        curl_setopt_array($handle, $options);

        $response = curl_exec($handle);
        curl_close($handle);

        return $response;
	}

	/**
	* Calls an HTTPS POST function to verify if the user's guess was correct
	* @return ReCaptchaResponse
	*/
	function recaptcha_check_answer()
	{
		global $config, $user;
		
		$version = $config[self::NAME_PREFIX_CONFIG.'version'] ??  self::DEFAULTS['version'];
		$response_token = request_var('g-recaptcha-response', '', true);

		//discard spam submissions
		if (empty($response_token))
		{
			return $user->lang[self::NAME_PREFIX_LANG.strtoupper($version).'_INCORRECT'];
		}
		
		$verify_url = sprintf(self::API_URL_FORMAT, ($config[self::NAME_PREFIX_CONFIG.'domain'] ?? self::DEFAULTS['domain']), '/siteverify');
		
		$response = $this->_recaptcha_http_post($verify_url,
			[
				'secret'	    => $config[self::NAME_PREFIX_CONFIG.'privkey'],
				'remoteip'		=> $user->ip,
				'response'		=> $response_token,
			]
		);

		$result = json_decode($response, true);
		
		if (isset($result['error-codes']) && count($result['error-codes']) > 0) {
			add_log('critical', 'LOG_'.self::NAME_PREFIX_LANG.'VERIFY_ERROR', implode(', ', $result['error-codes']));
		}
		
		$is_success = false;
		if (isset($result['success']) && $result['success'] == true)
		{
			$verify_origin = $config[self::NAME_PREFIX_CONFIG.'verify_origin'] ?? self::DEFAULTS['verify_origin'];
			
			$hostname_match = $verify_origin ? ($result['hostname'] ?? '') === $_SERVER['SERVER_NAME'] : true;
			
			if ( $hostname_match == true ) {	
				if ($version == 'v3') {
					$expected_action = $this->actions[$this->type] ?? reset($this->actions);
					$threshold = (double) $config[self::NAME_PREFIX_CONFIG."v3_threshold_{$expected_action}"] ?? self::DEFAULTS['v3_threshold'];
					
					$score = $result['score'] ?? 0.0;
					$action = $result['action'] ?? '';

					$is_success = $score >= $threshold  && $action === $expected_action;

				} else { // v2
					$is_success = true;
				}
			}
		}
		if ($is_success) {
			$this->solved = true;
			
			return false;
		}

		return $user->lang[self::NAME_PREFIX_LANG.strtoupper($version).'_INCORRECT'];
	}
}

?>
