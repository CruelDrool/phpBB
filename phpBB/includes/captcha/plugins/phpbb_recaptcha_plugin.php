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
	// Serve everything over https.
	var $recaptcha_server = 'https://www.google.com/recaptcha/api';
	var $recaptcha_verify_url = '';
	
	var $response;

	// Constructor
	function __construct()
	{
		$this->recaptcha_verify_url = $this->recaptcha_server . '/siteverify';
	}

	function init($type)
	{
		global $config, $db, $user;

		$user->add_lang('captcha_recaptcha');
		parent::init($type);
		$this->response = request_var('g-recaptcha-response', '');
	}

	function is_available()
	{
		global $config, $user;
		$user->add_lang('captcha_recaptcha');
		return (isset($config['recaptcha_pubkey']) && !empty($config['recaptcha_pubkey']));
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
		return 'CAPTCHA_RECAPTCHA';
	}

	function get_class_name()
	{
		return 'phpbb_recaptcha';
	}

	function acp_page($id, &$module)
	{
		global $config, $db, $template, $user;

		$captcha_vars = array(
			'recaptcha_pubkey'				=> 'RECAPTCHA_PUBKEY',
			'recaptcha_privkey'				=> 'RECAPTCHA_PRIVKEY',
		);

		$module->tpl_name = 'captcha_recaptcha_acp';
		$module->page_title = 'ACP_VC_SETTINGS';
		$form_key = 'acp_captcha';
		add_form_key($form_key);

		$submit = request_var('submit', '');

		if ($submit && check_form_key($form_key))
		{
			$captcha_vars = array_keys($captcha_vars);
			foreach ($captcha_vars as $captcha_var)
			{
				$value = request_var($captcha_var, '');
				if ($value)
				{
					set_config($captcha_var, $value);
				}
			}

			add_log('admin', 'LOG_CONFIG_VISUAL');
			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($module->u_action));
		}
		else if ($submit)
		{
			trigger_error($user->lang['FORM_INVALID'] . adm_back_link($module->u_action));
		}
		else
		{
			foreach ($captcha_vars as $captcha_var => $template_var)
			{
				$var = (isset($_REQUEST[$captcha_var])) ? request_var($captcha_var, '') : ((isset($config[$captcha_var])) ? $config[$captcha_var] : '');
				$template->assign_var($template_var, $var);
			}

			$template->assign_vars(array(
				'CAPTCHA_PREVIEW'	=> $this->get_demo_template($id),
				'CAPTCHA_NAME'		=> $this->get_class_name(),
				'U_ACTION'			=> $module->u_action,
			));

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

			$template->assign_vars(array(
				'RECAPTCHA_SERVER'			=> $this->recaptcha_server,
				'RECAPTCHA_PUBKEY'			=> isset($config['recaptcha_pubkey']) ? $config['recaptcha_pubkey'] : '',
				'RECAPTCHA_ERRORGET'		=> '',
				'S_RECAPTCHA_AVAILABLE'		=> $this->is_available(),
				'S_CONFIRM_CODE'			=> true,
				'S_TYPE'					=> $this->type,
				'L_CONFIRM_EXPLAIN'			=> $explain,
			));

			return 'captcha_recaptcha.html';
		}
	}

	function get_demo_template($id)
	{
		return $this->get_template();
	}

	function get_hidden_fields()
	{
		$hidden_fields = array();

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
		else
		{
			return $this->recaptcha_check_answer();
		}
	}

	/**
	* Submits an HTTPS POST to a reCAPTCHA server
	* @param string $url
	* @param array $params
	* @return array response
	*/
	function _recaptcha_http_post($url, $params)
	{
        $handle = curl_init();

        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params, '', '&'),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
            CURLINFO_HEADER_OUT => false,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        );
        
        curl_setopt_array($handle, $options);

        $response = curl_exec($handle);
        curl_close($handle);

        return $response;
	}

	/**
	* Calls an HTTPS POST function to verify if the user's guess was correct
	* @param array $extra_params an array of extra variables to post to the server
	* @return ReCaptchaResponse
	*/
	function recaptcha_check_answer($extra_params = array())
	{
		global $config, $user;

		//discard spam submissions
		if ($this->response == null || strlen($this->response) == 0)
		{
			return $user->lang['RECAPTCHA_INCORRECT'];
		}

		$response = $this->_recaptcha_http_post($this->recaptcha_verify_url,
			array(
				'secret'	    => $config['recaptcha_privkey'],
				'remoteip'		=> $user->ip,
				'response'		=> $this->response
			) + $extra_params
		);

		$result = json_decode($response, true);

		if (isset($result['success']) && $result['success'] == true)
		{
			$this->solved = true;
			return false;
		}
		else
		{
			return $user->lang['RECAPTCHA_INCORRECT'];
		}
	}
}

?>