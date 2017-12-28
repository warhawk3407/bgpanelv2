<?php

/**
 * LICENSE:
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @package		Bright Game Panel V2
 * @version		0.1
 * @category	Systems Administration
 * @author		warhawk3407 <warhawk3407@gmail.com> @NOSPAM
 * @copyright	Copyleft 2015, Nikita Rousseau
 * @license		GNU General Public License version 3.0 (GPLv3)
 * @link		http://www.bgpanel.net/
 */

if ( !class_exists('Core_Abstract_Module_Controller')) {
	trigger_error('Controller_Login -> BGP_Controller is missing !');
}

/**
 * Login Controller
 */

class Core_Module_Controller_Login extends Core_Abstract_Module_Controller
{

	function __construct( )	{
	
		// Call parent constructor
		parent::__construct( basename(__DIR__) );
	}


    /**
     * @api {post} /login Creates a new session for a user.
     * @author Nikita Rousseau
     * @apiVersion v1
     * @apiName PostLogin
     * @apiGroup Login
     *
     * @apiDescription Creates a new session for a user in Bright Game Panel. Once a session has been successfully
     * created it can be used to access any of Bright Game Panel's remote APIs and also the web UI by passing the
     * appropriate HTTP Cookie header.
     * Note that it is generally preferable to use HTTP BASIC / API-KEY authentication with the REST API.
     *
     * @apiParam {String} username Name of the User.
     * @apiParam {String} password Password of the User.
     * @apiParam {Boolean} rememberMe Saving info preference of the User.
     *
     * @apiSuccess {String} id The Users-ID of the User.
     *
     * @param $username
     * @param $password
     * @param $rememberMe
     * @return array
     */
	public function login( $username, $password, $rememberMe ) {
		$form = array (
			'username' => $username,
			'password' => $password,
			'rememberMe' => $rememberMe
		);

		$errors			= array();  	// array to hold validation errors
		$data 			= array(); 		// array to pass back data

		$dbh = Core_DBH::getDBH();		// Get Database Handle

		// validate the variables ======================================================

		$v = new Valitron\Validator( $form );

		$rules = [
				'required' => [
					['username'],
					['password']
				],
				'alphaNum' => [
					['username']
				]
			];

		$v->rules( $rules );
		$v->validate();

		$errors = $v->errors();

		// Verify the form =============================================================

		if (empty($errors))
		{
			$username = $form['username'];
			$password = Core_AuthService::getHash($form['password']);

			try {
				$sth = $dbh->prepare("
					SELECT user_id, username, firstname, lastname, lang, template
					FROM user
					WHERE
						username = :username AND
						password = :password AND
						status = 'Active'
					;");

				$sth->bindParam(':username', $username);
				$sth->bindParam(':password', $password);

				$sth->execute();

				$result = $sth->fetchAll();
			}
			catch (PDOException $e) {
				echo $e->getMessage().' in '.$e->getFile().' on line '.$e->getLine();
				die();
			}

			if (!empty($result)) {

				session_regenerate_id( TRUE );

				// Give User Privilege

				$authService = Core_AuthService::getAuthService();

				// Reset Login Attempts
				$authService->resetBanCounter();

				$authService->setSession(
					$result[0]['user_id'],
					$result[0]['username'],
					$result[0]['firstname'],
					$result[0]['lastname'],
					$result[0]['lang'],
					$result[0]['template']
					);

				$authService->setSessionPerms();

				// Database update

				try {
					$sth = $dbh->prepare("
						UPDATE user
						SET
							last_login		= :last_login,
							last_activity	= :last_activity,
							last_ip 		= :last_ip,
							last_host		= :last_host,
							token 			= :token
						WHERE
							user_id			= :user_id
						;");

					$last_login = date('Y-m-d H:i:s');
					$last_activity = date('Y-m-d H:i:s');
					$last_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
					$token = session_id();

					$sth->bindParam(':last_login', $last_login);
					$sth->bindParam(':last_activity', $last_activity);
					$sth->bindParam(':last_ip', $_SERVER['REMOTE_ADDR']);
					$sth->bindParam(':last_host', $last_host);
					$sth->bindParam(':token', $token);
					$sth->bindParam(':user_id', $result[0]['user_id']);

					$sth->execute();
				}
				catch (PDOException $e) {
					echo $e->getMessage().' in '.$e->getFile().' on line '.$e->getLine();
					die();
				}

				// Cookies

				// Remember Me
				if ( $form['rememberMe'] == TRUE ) {
					$this->setRememberMeCookie( $result[0]['username'] );
				}
				else if ( isset($_COOKIE['USERNAME']) ) {
					$this->rmCookie( 'USERNAME' );
				}

				// Language
				$this->setLangCookie( $result[0]['lang'] );

				// Log Event
				$logger = self::getLogger();
				$logger->info('Log in.');
			}
			else {
				// Cookie
				if ( isset($_COOKIE['USERNAME']) ) {
					$this->rmCookie( 'USERNAME' );
				}

				// Call security component
				$authService = Core_AuthService::getAuthService();
				$authService->incrementBanCounter();

				// Log Event
				$logger = self::getLogger();
				$logger->info('Login failure.');

				// Messages
				$errors['username'] = T_('Invalid Credentials.');
				$errors['password'] = T_('Invalid Credentials.');
			}
		}

		// return a response ===========================================================

		// response if there are errors
		if (!empty($errors)) {

			// if there are items in our errors array, return those errors
			$data['success'] = false;
			$data['errors']  = $errors;

			// notification
			$authService = Core_AuthService::getAuthService();

			if ( $authService->isBanned() ) {
				$data['msgType'] = 'warning';
				$data['msg'] = T_('You have been banned') . ' ' . CONF_SEC_BAN_DURATION . ' ' . T_('seconds!');
			}
			else {
				$data['msgType'] = 'warning';
				$data['msg'] = T_('Login Failure!');
			}
		}
		else {

			$data['success'] = true;
		}

		// return all our data to an AJAX call
		return $data;
	}

    /**
     * @api {get} /login Returns information about the currently authenticated user.
     * @author Nikita Rousseau
     * @apiVersion v1
     * @apiName GetLogin
     * @apiGroup Login
     *
     * @apiDescription Returns information about the currently authenticated user.
     * If the caller is not authenticated they will get a 401 Unauthorized status code.
     *
     * @apiSuccess {String[]} information The User information.
     *
     * @return array
     */
	public function getUser() {
	    // TODO: to be implemented
        return array();
    }

    /**
     * @api {get} /login/newPassword Regenerates a password for a user.
     * @author Nikita Rousseau
     * @apiVersion v1
     * @apiName GetNewPassword
     * @apiGroup Login
     * @apiPrivate
     *
     * @apiDescription Creates a new random password for a user.
     *
     * @apiParam {String} username Name of the User.
     * @apiParam {String} email Email address of the User.
     * @apiParam {Boolean} captcha_validation Is captcha valid ?
     *
     * @apiSuccess
     * @param $username
     * @param $email
     * @param bool $captcha_validation
     * @return array
     */
	public function newPassword( $username, $email, $captcha_validation = TRUE ) {
		$form = array (
			'username' => $username,
			'email'    => $email
		);

		$errors			= array();  	// array to hold validation errors
		$data 			= array(); 		// array to pass back data

		$dbh = Core_DBH::getDBH();		// Get Database Handle

		// validate the variables ======================================================

		$v = new Valitron\Validator( $form );

		$rules = [
				'required' => [
					['username'],
					['email']
				],
				'alphaNum' => [
					['username']
				],
				'email' => [
					['email']
				]
			];

		$v->rules( $rules );
		$v->validate();

		$errors = $v->errors();

		// Verify the form =============================================================

		if (empty($errors))
		{
			$username 	= $form['username'];
			$email 		= $form['email'];

			try {
				$sth = $dbh->prepare("
					SELECT user_id, email
					FROM user
					WHERE
						username = :username AND
						email 	 = :email AND
						status   = 'active'
					;");

				$sth->bindParam(':username', $username);
				$sth->bindParam(':email', $email);

				$sth->execute();

				$result = $sth->fetchAll();
			}
			catch (PDOException $e) {
				echo $e->getMessage().' in '.$e->getFile().' on line '.$e->getLine();
				die();
			}

			if ( !empty($result) && ($captcha_validation == TRUE) ) {
				$authService = Core_AuthService::getAuthService();

				// Reset Login Attempts
				$authService->resetBanCounter();

				// Reset User Passwd
				$plainTextPasswd = bgp_create_random_password( 13 );
				$digestPasswd = Core_AuthService::getHash($plainTextPasswd);

				try {
					// Update User Passwd
					$sth = $dbh->prepare("
						UPDATE user
						SET
							password 	= :password
						WHERE
							user_id		= :user_id
						;");

					$sth->bindParam(':password', $digestPasswd);
					$sth->bindParam(':user_id', $result[0]['user_id']);

					$sth->execute();
				}
				catch (PDOException $e) {
					echo $e->getMessage().' in '.$e->getFile().' on line '.$e->getLine();
					die();
				}

				// Send Email
				$to = htmlentities($result[0]['email'], ENT_QUOTES);

				$subject = T_('Reset Password');

				$message = T_('Your password has been reset to:');
				$message .= "<br /><br />" . $plainTextPasswd . "<br /><br />";
				$message .= T_('With IP').': ';
				$message .= $_SERVER['REMOTE_ADDR'];

				$headers  = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
				$headers .= 'From: Bright Game Panel System <root@'. $_SERVER['SERVER_NAME'] .'>' . "\r\n";
				$headers .= 'X-Mailer: PHP/' . phpversion();

				$mail = mail($to, $subject, $message, $headers);

				// Log Event
				$logger = self::getLogger();
				$logger->info('Password reset.');
			}
			else {
				// Call security component
				$authService = Core_AuthService::getAuthService();
				$authService->incrementBanCounter();

				// Log Event
				$logger = self::getLogger();
				$logger->info('Bad password reset.');

				// Messages
				if ( empty($result) ) {
					$errors['username'] = T_('Wrong information.');
					$errors['email'] = T_('Wrong information.');
				}

				if ($captcha_validation == FALSE) {
					$errors['captcha'] = T_('Wrong CAPTCHA Code.');
				}
			}
		}

		// return a response ===========================================================

		// response if there are errors
		if (!empty($errors)) {

			// if there are items in our errors array, return those errors
			$data['success'] = false;
			$data['errors']  = $errors;

			// notification
			$authService = Core_AuthService::getAuthService();

			if ( $authService->isBanned() ) {
				$data['msgType'] = 'warning';
				$data['msg'] = T_('You have been banned') . ' ' . CONF_SEC_BAN_DURATION . ' ' . T_('seconds!');
			}
			else {
				$data['msgType'] = 'warning';
				$data['msg'] = T_('Invalid information provided!');
			}
		}
		else if (!$mail) {

			// mail delivery error
			$data['success'] = false;

			// notification
			$data['msgType'] = 'danger';
			$data['msg'] = T_('An error has occured while sending the email. Contact your system administrator.');
		}
		else {

			$data['success'] = true;
		}

		// return all our data to an AJAX call
		return $data;
	}

    /**
     * @api {delete} /login Logs the current user out of the application.
     * @author Nikita Rousseau
     * @apiVersion v1
     * @apiName DeleteLogout
     * @apiGroup Login
     *
     * @apiDescription Logs the current user out of Bright Game Panel, destroying the existing session, if any.
     *
     * @return array
     */
	public function logout() {
	    // TODO : to be implemented
        return array();
    }

    private function setRememberMeCookie( $username ) {
        setcookie('USERNAME', htmlentities($username, ENT_QUOTES), time() + (86400 * 7 * 2), BASE_URL); // 86400 = 1 day
    }

    private function setLangCookie( $lang ) {
        setcookie('LANG', htmlentities($lang, ENT_QUOTES), time() + (86400 * 7 * 2), BASE_URL);
    }

    private function rmCookie( $cookie ) {
        setcookie($cookie, '', time() - 3600, BASE_URL);
    }
}
