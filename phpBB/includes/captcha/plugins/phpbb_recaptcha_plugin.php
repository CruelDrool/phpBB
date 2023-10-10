<?php
/**
*
* @package VC
* @version $Id$
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB')) {
	exit;
}

if (!class_exists('phpbb_default_captcha')) {
	// we need the classic captcha code for tracking solutions and attempts
	include($phpbb_root_path . 'includes/captcha/plugins/captcha_abstract.' . $phpEx);
}

/**
* @package VC
*/
class phpbb_recaptcha extends phpbb_default_captcha {
	private const NAME = 'recaptcha';
	private const NAME_UPPER = 'RECAPTCHA';
	private const NAME_PREFIX_CONFIG = self::NAME.'_';
	private const NAME_PREFIX_TPL_VAR = self::NAME_UPPER.'_';
	private const NAME_PREFIX_LANG = self::NAME_PREFIX_TPL_VAR;
	private const API_URL_FORMAT = 'https://www.%s/recaptcha/api%s';

	/** @var bool Need this to display the v3 footer even after being verified. */
	static $captcha_added = false;

	/** @var array CAPTCHA types mapped to form names */
	private const FORMS = [
		0              => 'default',
		CONFIRM_REG    => 'register',
		CONFIRM_LOGIN  => 'login',
		CONFIRM_POST   => 'post',
		CONFIRM_REPORT => 'report',
	];

	/** @var array Possible domain names to load the script and verify the token */ 
	private const DOMAINS = [
		'GOOGLE'    => 'google.com',
		'RECAPTCHA' => 'recaptcha.net',
	];

	/** @var array Default settings */
	private const DEFAULTS = [
		'domain'                     => self::DOMAINS['GOOGLE'],
		'language'                   => '',
		'placement'                  => 'bottomright',
		'privkey'                    => '',
		'pubkey'                     => '',
		'theme'                      => 'light',
		'version'                    => 'v2_checkbox',
		'v2_checkbox_size'           => 'normal',
		'v3_load_all'                => true,
		'v3_load_all_guests_only'    => true,
		'v3_threshold'               => 0.5,
		'verify_origin'              => false,
		'require_remote_ip'          => true,
		'v2_checkbox_error_message'  => '',
		'v2_invisible_error_message' => '',
		'v3_error_message'           => '',
	];

	/**
	 * Returns value of an option. If no value can be found, return a default value.
	 *
	 * @param string $option Name of option
	 * @param mixed $default Optional. Provide a fallback default value
	 *
	 * @return mixed
	 */
	private static function get_option($option, $default = '') {
		global $config;
		$default = self::DEFAULTS[$option] ?? $default;
		$value = $config[self::NAME_PREFIX_CONFIG.$option] ?? $default;

		return $value;
	}

	/**
	 * Retrieves the selected reCAPTCHA domain. Also verifies that the domain is a valid one.
	 *
	 * @return string
	 */
	private static function get_domain() {
		$domain = self::get_option('domain');
		$domain = in_array($domain, self::DOMAINS) ? $domain : self::DEFAULTS['domain'];

		return $domain;
	}

	/**
	 * Get the reCAPTCHA API script url.
	 *
	 * @return string
	 */
	private static function get_api_script_url() {
		$query_data = [
			'hl'     => trim( self::get_option('language') ),
			'onload' => 'phpbbRecaptchaOnLoad',
			'render' => 'explicit'
		];

		$url = sprintf('%s?%s',
			sprintf(self::API_URL_FORMAT, self::get_domain(), '.js'),
			http_build_query($query_data, '', '&')
		);

		return $url; 
	}

	/**
	 * 
	 *
	 * @param string $version 
	 * @param bool $preview 
	 *
	 * @return string HTML with JavaScript
	 */
	private function get_html_output($version, $preview = false){
		global $user;

		$noscript = $user->lang[self::NAME_PREFIX_LANG.'NOSCRIPT'];

		if ($version == 'v2_checkbox' || $preview) {
			$placement = $version == 'v2_checkbox' ? '' : 'inline';
			$size = $version == 'v2_checkbox' ? self::get_option('v2_checkbox_size') : null;
			return self::_get_html_output(null, $noscript, $placement, $size);
		} else { // v2 Invisible or v3.
			$action = '';
			if ($version == 'v3') {
				$form = self::FORMS[$this->type] ?? self::FORMS[0];
				$action = self::get_option("v3_action_{$form}", $form);
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

	/**
	 * TODO: Come up with a better name!?
	 *
	 * @param null|string $script 
	 * @param null|string $noscript 
	 * @param null|string $placement 
	 * @param null|string $size 
	 *
	 * @return string HTML with JavaScript
	 */
	private static function _get_html_output($script = null, $noscript = null, $placement = null, $size = null) {	
		$pubkey = self::get_option('pubkey');

		if (empty($pubkey)) {
			return '';
		}

		$size = $size ?? 'invisible';
		$placement = $placement ?? self::get_option('placement');
		$theme = self::get_option('theme');
		$url = self::get_api_script_url();
		$badge = !empty($placement) ? sprintf(' data-badge="%s"', $placement) : '';
		$noscript = !empty($noscript) ? "<noscript>\n\t\t<p>{$noscript}</p>\n\t</noscript>" : '';
		if (empty($script)) {
			// If no script is set, use a very simple onload callback for explicit rendering.
			$script = <<<HTML

	<script>
		window.phpbbRecaptchaOnLoad = function() {
			var captchaContainer = document.getElementById("g-recaptcha");
			grecaptcha.render(captchaContainer, {});
		};
	</script>
HTML;
			}

			$html =<<<HTML

	<div id="g-recaptcha" data-sitekey="$pubkey" data-theme="$theme" data-size="$size"$badge></div>
	$script
	<script src="$url" async defer></script>
	$noscript
HTML;

		self::$captcha_added = true;

		return $html;
	}

	/**
	 * The v3 footer
	 *
	 * @return string HTML with JavaScript.
	 */
	static function page_footer_output() {	
		return !self::$captcha_added ? self::_get_html_output() : '';
	}

	function init($type) {
		global $user;

		$user->add_lang('captcha_'.self::NAME);

		parent::init($type);
	}

	static function is_available() {
		global $user;

		$user->add_lang('captcha_'.self::NAME);

		return ( !empty(self::get_option('pubkey')) && !empty(self::get_option('privkey')) );
	}

	/**
	*  API function
	*/
	function has_config() {
		return true;
	}

	static function get_name() {
		return 'CAPTCHA_'.self::NAME_UPPER;
	}

	function get_class_name() {
		return __CLASS__;
	}

	/**
	 * Sanitizes a v3 action name. Allowed characters are alphanumeric, underscores, and forward slashes.
	 *
	 * @param string $name The name of the action.
	 * @param string $default
	 *
	 * @return string
	 */
	function sanitize_action_name($name, $default) {
		// This regex matches any characters that aren't in the list.
		$name = preg_replace('/[^a-zA-Z0-9_\/]+/', '', $name);

		// Empty value, fallback to default.
		if (empty($name)) {
			$name = $default;
		}

		return $name;
	}

	/**
	 * Sanitizes a v3 threshold value to ensure it's a double value between 0.0 and 1.0.
	 *
	 * @param string $value 
	 *
	 * @return double
	 */
	function sanitize_threshold_value($value) {
		$value = floatval($value);
		if ( $value < 0 ) {
			$value = 0.0;
		} elseif ( $value > 1 ) {
			$value = 1.0;
		}
		return $value;
	}

	function acp_page($id, &$module) {
		global $config, $template, $user;

		$module->tpl_name = 'captcha_'.self::NAME.'_acp';
		$module->page_title = 'ACP_VC_SETTINGS';
		$form_key = 'acp_captcha';
		add_form_key($form_key);

		$submit = request_var('submit', '');
		$vars = self::DEFAULTS;
		unset($vars['v3_threshold']);

		if ($submit) {
			// Check if form is valid. Will trigger error if not valid.
			check_form_key($form_key, false, adm_back_link($module->u_action), true);

			foreach ($vars as $var => $default_value) {
				$config_name = self::NAME_PREFIX_CONFIG.$var;
				// set_config($config_name, request_var($config_name, $default_value, true));

				set_config($config_name, request_var($config_name, '', true));
			}

			foreach (self::FORMS as $form) {
				$config_name = self::NAME_PREFIX_CONFIG."v3_threshold_{$form}";
				set_config( $config_name, $this->sanitize_threshold_value( request_var($config_name, self::DEFAULTS['v3_threshold']) ) );

				$config_name = self::NAME_PREFIX_CONFIG."v3_action_{$form}";
				set_config( $config_name, $this->sanitize_action_name( request_var($config_name, $form) , $form) );
			}

			add_log('admin', 'LOG_CONFIG_VISUAL');
			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($module->u_action));
		} else {

			foreach (self::FORMS as $form) {

				$key = self::NAME_PREFIX_CONFIG."v3_action_{$form}";

				$template->assign_block_vars('actions', [
					'KEY'           => $key,
					'VALUE'         => $config[$key] ?? $form,
					'PLACEHOLDER'   => $form,
					'L_KEY'         => $user->lang[strtoupper($key)] ?? '',
					'L_KEY_EXPLAIN' => $user->lang[strtoupper($key).'_EXPLAIN'] ?? '',
				]);

				$key = self::NAME_PREFIX_CONFIG."v3_threshold_{$form}";

				$template->assign_block_vars('thresholds', [
					'KEY'           => $key,
					'VALUE'         => $config[$key] ?? self::DEFAULTS['v3_threshold'],
					'L_KEY'         => $user->lang[strtoupper($key)] ?? '',
					'L_KEY_EXPLAIN' => $user->lang[strtoupper($key).'_EXPLAIN'] ?? '',
				]);
			}

			foreach (self::DOMAINS as $name => $domain) {
				$template->assign_block_vars('domains',[
					'DOMAIN' => $domain,
				]);
			}

			foreach ($vars as $var => $default_value) {
				$config_name = self::NAME_PREFIX_CONFIG.$var;
				$value = $config[$config_name] ?? $default_value;

				$template->assign_var(strtoupper($config_name), $value);
			}

			$template->assign_vars([
				'CAPTCHA_PREVIEW' => $this->get_demo_template($id),
				'CAPTCHA_NAME'    => $this->get_class_name(),
				'U_ACTION'        => $module->u_action,
			]);

		}
	}

	// not needed
	function execute_demo() {
	}

	// not needed
	function execute() {
	}

	function get_template() {
		global $config, $user, $template;

		if ($this->is_solved()) {
			return false;
		} else {			
			$explain = $user->lang(($this->type != CONFIRM_POST) ? 'CONFIRM_EXPLAIN' : 'POST_CONFIRM_EXPLAIN', '<a href="mailto:' . htmlspecialchars($config['board_contact']) . '">', '</a>');

			$version = self::get_option('version');

			$template->assign_vars([
				self::NAME_PREFIX_TPL_VAR.'HTML'               => $this->get_html_output($version),
				self::NAME_PREFIX_TPL_VAR.'VERSION'            => $version,
				'S_'.self::NAME_PREFIX_TPL_VAR.'AVAILABLE'     => $this->is_available(),
				'S_CONFIRM_CODE'                               => true,
				'S_TYPE'                                       => $this->type,
				'L_CONFIRM_EXPLAIN'                            => $explain,
				'L_'.self::NAME_PREFIX_TPL_VAR.'NOT_AVAILABLE' => $user->lang(self::NAME_PREFIX_LANG.'NOT_AVAILABLE', htmlspecialchars($config['board_contact'])),
			]);

			return 'captcha_recaptcha.html';
		}
	}

	function get_demo_template($id) {
		global $user, $template;

		$version = self::get_option('version');

		$template->assign_vars([
			self::NAME_PREFIX_TPL_VAR.'HTML'                      => $this->get_html_output($version, true),
			'L_'.self::NAME_PREFIX_TPL_VAR.'VERSION_NAME'         => $user->lang[self::NAME_PREFIX_LANG.strtoupper($version)],
			'L_'.self::NAME_PREFIX_TPL_VAR.'VERSION_NAME_EXPLAIN' => $user->lang[self::NAME_PREFIX_LANG.strtoupper($version).'_PREVIEW_EXPLAIN'],
			'S_'.self::NAME_PREFIX_TPL_VAR.'AVAILABLE'            => $this->is_available(),
			'L_'.self::NAME_PREFIX_TPL_VAR.'NOT_AVAILABLE'        => $user->lang[self::NAME_PREFIX_LANG.'PREVIEW_NOT_AVAILABLE'],
		]);

		return 'captcha_recaptcha.html';
	}

	function get_hidden_fields() {
		$hidden_fields = [];

		// this is required for posting.php - otherwise we would forget about the captcha being already solved
		if ($this->solved) {
			$hidden_fields['confirm_code'] = $this->code;
		}

		$hidden_fields['confirm_id'] = $this->confirm_id;

		return $hidden_fields;
	}

	function uninstall() {
		self::garbage_collect(0);
	}

	function install() {
		return;
	}

	function validate() {
		if (!parent::validate()) {
			return false;
		}

		return $this->check_answer();
	}

	/**
	 * Determines the user's actual IP address.
	 *
	 * @return false|string
	 */
	private function get_remote_ip() {
		$client_ip = false;

		// In order of preference, with the best ones for this purpose first.
		$address_headers = [
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $address_headers as $header ) {
			if ( array_key_exists( $header, $_SERVER ) ) {
				/*
				 * HTTP_X_FORWARDED_FOR can contain a chain of comma-separated
				 * addresses. The first one is the original client. It can't be
				 * trusted for authenticity, but we don't need to for this purpose.
				 */
				$address_chain = explode( ',', $_SERVER[ $header ] );
				$client_ip = trim( $address_chain[0] ?? '' );

				break;
			}
		}

		return filter_var($client_ip, FILTER_VALIDATE_IP);
	}

	/**
	 * Submits a HTTPS POST with cURL to a reCAPTCHA server
	 *
	 * @param array $params 
	 *
	 * @return string response
	 */
	private function http_post($params) {

		$timeout = 10;
		$options = [
			CURLOPT_URL            => sprintf(self::API_URL_FORMAT, self::get_domain(), '/siteverify'),
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $params,
			CURLINFO_HEADER_OUT    => false,
			CURLOPT_HEADER         => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT        => $timeout,
		];

		$handle = curl_init();
		curl_setopt_array($handle, $options);
		$data = curl_exec($handle);
		$error_code = curl_errno($handle);
		$error_msg = curl_error($handle);
		$response_code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		curl_close($handle);

		if ( $error_code > 0 ) {
			add_log('critical', 'LOG_'.self::NAME_PREFIX_LANG.'CURL_ERROR', $error_code, $error_msg);
		} elseif ( $response_code !== 200 ) {
			add_log('critical', 'LOG_'.self::NAME_PREFIX_LANG.'WRONG_RESPONSE_CODE', $response_code);
		}

		return ( $data !== false && $response_code === 200 ) ? $data : '';
	}

	/**
	 * Verify the user's answer.
	 *
	 * @return bool|string
	 */
	private function check_answer() {
		global $user;

		$version = self::get_option('version');
		$error_msg = self::get_option("{$version}_error_message");
		$error_msg = empty( $error_msg ) ? $user->lang[self::NAME_PREFIX_LANG.strtoupper($version).'_INCORRECT'] : $error_msg;
		$response_token = request_var('g-recaptcha-response', '', true);

		// No response token. Possible when the JavaScript was removed using the browser's developer tools interface.
		if ( empty($response_token) ) {
			return $error_msg;
		}

		$remote_ip = $this->get_remote_ip();

		if ( self::get_option('require_remote_ip') && $remote_ip === false ) {
			return $error_msg;
		}

		$post_params = [
			'secret'   => self::get_option('privkey'),
			'response' => $response_token,
		];

		if ( $remote_ip !== false ) {
			$post_params['remoteip'] = $remote_ip;
		}

		$response = $this->http_post($post_params);

		$result = json_decode( $response, true );

		if ( !is_array( $result ) ) {
			return $error_msg;
		}

		if ( !empty($result['error-codes']) ) {
			add_log('critical', 'LOG_'.self::NAME_PREFIX_LANG.'VERIFY_ERROR', implode(', ', $result['error-codes']));
			return $error_msg;
		}

		if ( !isset( $result['success'] ) ) {
			return $error_msg;
		}

		$is_success = false;
		$hostname_match = self::get_option('verify_origin') ? ($result['hostname'] ?? '') === $_SERVER['SERVER_NAME'] : true;

		if ( $hostname_match ) {
			if ( $result['success'] == true ) {	
				if ($version == 'v3') {
					$form = self::FORMS[$this->type] ?? self::FORMS[0];
					$threshold = (double) self::get_option("v3_threshold_{$form}", self::DEFAULTS['v3_threshold']);
					$expected_action = self::get_option("v3_action_{$form}", $form);

					$score = $result['score'] ?? 0.0;
					$action = $result['action'] ?? '';

					$is_success = $score >= $threshold  && $action === $expected_action;
				} else { // v2
					$is_success = true;
				}
			}
		}

		if ( $is_success ) {
			$this->solved = true;

			return false;
		}

		return $error_msg;
	}
}

?>
