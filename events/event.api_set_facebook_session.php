<?php

	require_once(TOOLKIT . '/class.event.php');
	require_once(EXTENSIONS . '/battlefront_api/lib/facebookphp-sdk/src/facebook.php');

	Class eventapi_set_facebook_session extends Event{

		const ROOTELEMENT = 'api-set-facebook-session';

		private static $members;
		protected static $loggedInID = null;

		public $eParamFILTERS = array(

		);

		public static function about(){
			return array(
					 'name' => 'API: Set Facebook Session',
					 'author' => array(
							'name' => 'Brendan Abbott',
							'website' => 'http://brendan.dev.randb.com.au/battlefront2',
							'email' => 'brendan@randb.com.au'),
					 'version' => '1.0',
					 'release-date' => '2010-07-22T02:18:00+00:00',
					 'trigger-condition' => 'action[api-set-facebook-session]');
		}

		public function priority(){
			return self::kHIGH;
		}

		public static function documentation(){
			return '
				<p>This event listens on each page to determine if a Facebook login event has taken place.</p>
				<p>This is defined as to if <code>$_GET["session"]</code> is set, or an existing Facebook session is existing.
				If no session is set, <code>__trigger()</code> runs, accomplishing the following:</p>
				<ul>
					<li>Checks Symphony to see if this user already has an existing Battlefront account by verifying their
					Battlefront email with their Facebook email. If they match, the user record is updated with Facebook
					Credentials (user can log in via either).</li>
					<li>If the Facebook email doesn\'t match, it\'s deemed to be a new user, so a new record is created</li>
					<li>Finally, the login event is called with an array of credentials. It verifies a user via email, access-token.</li>
				</ul>
			';
		}

		public function load(){
			self::$members = Frontend::instance()->ExtensionManager->create("members");

			//	The members extension will instaniate as a FacebookMember because the $_GET['session']
			//	will be sent by Facebook.
			if(self::$members->Member instanceof FacebookMember && !self::$members->Member->isLoggedIn()) {
				return $this->__trigger();
			}
		}

		protected function __trigger(){
			if(is_null(self::$members->Member->getSession())) {
				$session = json_decode($_GET['session']);
			}
			else {
				$session = self::$members->Member->getSession();
			}

			/*
				When a user logs into facebook, the session is returned to us.
				*	Check our Users section to see if this user exists (facebook_id)
					*	If they do and they haven't already tied Facebook to this account, set their FB ID and access_token
					*	If they don't, create a new User setting F/L Name, Email, Timezone
					*	Log the user in with their access_token
			*/

			self::$loggedInID = self::$members->Member->getSymphonyMember();

			if(!is_null($session)) {
				try {
					$user = self::$members->Member->isExistingSymphonyMember(array(
						'facebook_id' => $session['uid']
					));

					if($user !== false || !is_null(self::$loggedInID)) {
						//if(is_null($user['facebook-user-id']['value']) || !is_null(self::$loggedInID)) {
							$id = is_null(self::$loggedInID) ? $user[SymQuery::SYSTEM_ID] : self::$loggedInID;

							SymWrite('users')
							->set('facebook-user-id', $session['uid'])
							->set('facebook-access-token', $session['access_token'])
							->set(SymQuery::SYSTEM_ID, $id)
							->write();

							Symphony::$Log->pushToLog("Facebook: Updating: " . $id, E_NOTICE, true);
						//}
					}
					else {
						//	Account doesn't exist, create user
						$me = self::$members->Member->getConnection()->api('/me');

						$user = SymWrite('users')
								->set('first-name', $me['first_name'])
								->set('last-name', $me['last_name'])
								->set('time-zone', $me['timezone'])
								->set('email', $me['email'])
								->set('role', extension_Members::getConfigVar('new_member_default_role'))
								->set('terms-of-use', 'yes')
								->set('facebook-user-id', $session['uid'])
								->set('facebook-access-token', $session['access_token'])
								->write();

						Symphony::$Log->pushToLog("Facebook: Writing: " . $me['first_name'] . " " . $me['last_name'] . " [" . $me['email'] . "]", E_NOTICE, true);
					}

					//	If the user already has a Battlefront account, we first want to log
					//	them out before logging them in with Facebook, this prevents the problem
					//	in that you log out of Facebook but are still logged in with a BF account.
					self::$members->Member->logout();
					self::$members->Member->login(array(
						'uid' => $session['uid'],
						'access_token' => $session['access_token']
					));
				}
				catch (SymWriteException $ex) {
					$errors = $ex->getValidationErrors();
					header('Location: ' . URL . '/register/facebook-error/');
				}
				catch (FacebookApiException $ex) {
					throw $ex;
				}
			}
		}

	}

