<?php

	Class SymphonyMember extends Members {
		public function __construct($driver) {
			parent::__construct($driver);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		public static function usernameAndPasswordField(){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = ". extension_Members::getConfigVar('member_section'). " AND `type` = 'member' LIMIT 1");
		}

		public static function usernameAndPasswordFieldHandle(){
			return Symphony::Database()->fetchVar('element_name', 0, "SELECT `element_name` FROM `tbl_fields` WHERE `parent_section` = ". extension_Members::getConfigVar('member_section') ." AND `type` = 'member' LIMIT 1");
		}

		public static function getSalt() {
			return Symphony::Database()->fetchVar('salt', 0, sprintf("
					SELECT `salt`
					FROM `tbl_fields_member`
					WHERE `field_id` = %d
					LIMIT 1
				", self::usernameAndPasswordField()
			));
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/
		public function login(Array $credentials){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			extract($credentials);

			if($id = $this->findMemberIDFromCredentials(array(
					'username' => $username,
					'password' => md5(self::getSalt() . $password)
				))
			) {

				try{
					self::$member_id = $id;
					$this->initialiseCookie();
					$this->initialiseMemberObject();

					$this->cookie->set('id', $id);
					$this->cookie->set('username', $username);
					$this->cookie->set('password', md5(self::getSalt() . $password));

					self::$isLoggedIn = true;

				} catch(Exception $ex){
					throw new UserException($ex);
				}

				return true;
			}

			$this->logout();

			return false;

		}

		public function isLoggedIn() {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(self::$isLoggedIn) return true;

			$this->initialiseCookie();

			if($id = $this->findMemberIDFromCredentials(array(
				'username' => $this->cookie->get('username'),
				'password' => $this->cookie->get('password')
				))
			) {
				self::$member_id = $id;
				self::$isLoggedIn = true;
				return true;
			}

			$this->logout();

			return false;
		}

	/*-------------------------------------------------------------------------
		Emails:
	-------------------------------------------------------------------------*/

		public function sendNewRegistrationEmail(Entry $entry, Role $role, Array $fields = array()) {
			$email_template = EmailTemplate::find(
				($role->id() == extension_Members::INACTIVE_ROLE_ID ? 'activate-account' : 'welcome'),
				$role->id()
			);
			$member_field_handle = self::usernameAndPasswordFieldHandle();

			if(!$email_template instanceof EmailTemplate) return null;

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::plaintext-password" => $fields[$member_field_handle]['password'],
				"{$member_field_handle}::username" => $fields[$member_field_handle]['username'],
				'code' => extension_Members::generateCode($entry->get('id')),
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));
		}

		public function sendNewPasswordEmail(Entry $entry, Role $role) {
			$new_password = General::generatePassword();

			// Attempt to update the password
			Symphony::Database()->query(sprintf(
				"UPDATE `tbl_entries_data_%d` SET `password` = '%s' WHERE `entry_id` = %d LIMIT 1",
				self::usernameAndPasswordField(),
				md5(self::getSalt() . $new_password),
				$member_id
			));

			$email_template = EmailTemplate::find('new-password', $role->id());
			$member_field_handle = self::usernameAndPasswordFieldHandle();

			if(!$email_template instanceof EmailTemplate) return null;

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::username" => $entry->getData($member_field_handle, true)->username,
				'new-password' => $new_password,
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));
		}

		public function sendResetPasswordEmail(Entry $entry, Role $role) {
			$email_template = EmailTemplate::find('reset-password', $role->id());
			$member_field_handle = self::usernameAndPasswordFieldHandle();

			if(!$email_template instanceof EmailTemplate) return null;

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::username" => $entry->getData($member_field_handle, true)->username,
				'code' => extension_Members::generateCode($entry->get('id')),
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));

		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/
		public function findMemberIDFromCredentials(Array $credentials) {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			extract($credentials);

			// It's expected that $password is md5'd and salted.
			if(is_null($username) || is_null($password)) return null;

			$entry_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf("
					SELECT `entry_id`
					FROM `tbl_entries_data_%d`
					WHERE `username` = '%s'
					AND `password` = '%s'
					LIMIT 1
				",
				$this->usernameAndPasswordField(), Symphony::Database()->cleanValue($username), $password
			));

			return (is_null($entry_id) ? null : $entry_id);
		}

		public function findMemberIDFromUsername($username = null){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(is_null($username)) return null;

			$entry_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf("
					SELECT `entry_id`
					FROM `tbl_entries_data_%d`
					WHERE `username` = '%s'
					LIMIT 1
				", $this->usernameAndPasswordField(), Symphony::Database()->cleanValue($username)
			));

			return (is_null($entry_id) ? null : $entry_id);
		}
	}
