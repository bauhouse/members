<?php

	Class FacebookMember extends Members {

		public static $connection = null;
		public static $session = null;
		public static $symphonyMember = null;

		public function __construct($driver, $connection, $session, $cookie) {
			parent::__construct($driver);

			self::$connection = $connection;
			self::$session = $session;
			self::$symphonyMember = $cookie->get('id');
		}

		public function getSession() {
			return self::$session;
		}

		public function getConnection() {
			return self::$connection;
		}

		public function getSymphonyMember() {
			return self::$symphonyMember;
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/
		public function login(Array $credentials) {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			extract($credentials);

			if($uid == 0) {
				self::$connection = null;
				self::$session = null;
				return false;
			}

			if($id = $this->findMemberIDFromCredentials($credentials)) {
				try{
					self::$member_id = $id;

					$this->initialiseCookie();
					$this->initialiseMemberObject();

					$this->cookie->set('facebook_auth', '1');
					$this->cookie->set('facebook_uid', $uid);
					$this->cookie->set('facebook_access_token', $access_token);

					self::$isLoggedIn = true;

					redirect(self::$connection->getCurrentUrl());

				} catch(Exception $ex) {
					throw new UserException($ex);
				}

				return true;
			}

			$this->logout();

			return false;

		}

		public function isLoggedIn(){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(self::$isLoggedIn) return true;

			$this->initialiseCookie();

			if($id = $this->findMemberIDFromCredentials(array(
					'uid' => $this->cookie->get('facebook_uid'),
					'access_token' => $this->cookie->get('facebook_access_token')
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
			return null;
		}

		public function sendNewPasswordEmail(Entry $entry, Role $role) {
			return null;
		}

		public function sendResetPasswordEmail(Entry $entry, Role $role) {
			return null;
		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/
		public function findMemberIDFromCredentials(Array $credentials) {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			extract($credentials);

			if(is_null($uid) || is_null($access_token)) return null;

			$member = SymRead(extension_Members::memberSectionHandle())
						->get(SymQuery::SYSTEM_ID)
						->where('facebook-user-id', $uid)
						->where('facebook-access-token', $access_token)
						->readDataIterator();

			if($member->valid()) {
				$member = $member->current();
				return $member[SymQuery::SYSTEM_ID];
			}
			else return null;
		}

		public function isExistingSymphonyMember(Array $credentials) {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			extract($credentials);

			if(is_null($facebook_id)) return false;

			$member = SymRead(extension_Members::memberSectionHandle())
					->get(SymQuery::SYSTEM_ID)
					->get('facebook-user-id')
					->where('facebook-user-id', $facebook_id)
					->readDataIterator();

			if($member->valid()) return $member->current();

			return false;
		}

	}
