<?php

	include_once(DOCROOT . '/extensions/library/lib/class.configurationaccessor.php');
	include_once(TOOLKIT . '/class.entrymanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');
	include_once(DOCROOT . '/extensions/asdc/lib/class.asdc.php');
	
	Class Role{
		
		private $_id;
		private $_name;
		private $_email_body;
		private $_email_subject;
		
		private $_forbidden_pages;
		private $_event_permissions;
		
		public function __construct($id, $name, array $event_permissions=array(), array $forbidden_pages=array(), $email_subject=NULL, $email_body=NULL){
			$this->_id = $id;
			$this->_name = $name;
			$this->_forbidden_pages = $forbidden_pages;
			$this->_event_permissions = $event_permissions;
			
			$this->_email_body = $email_body;
			$this->_email_subject = $email_subject;
		}
		
		private function __call($name, $var){
			return $this->{"_$name"};
		}
		
		public function forbiddenPages(){
			return $this->_forbidden_pages;
		}
		
		public function eventPermissions(){
			return $this->_event_permissions;			
		}
		
		public function canAccessPage($page_id){
			return !@in_array($page_id, $this->_forbidden_pages);
		}
		
		public function canPerformEventAction($event_handle, $action){	
			return ($this->_event_permissions[$event_handle][$action] == 'yes');
		}		
		
	}

	Final Class extension_Members extends Extension{
		
		private $_cookie;
		private $_member_id;
		public $Member;
		static private $_failed_login_attempt = false;
		
		const TOKEN_EXPIRY_TIME = 3600; // 1 hour
		
		public static function baseURL(){
			return URL . '/symphony/extension/members/';
		}

		public function fetchNavigation(){
			return array(
				array(
					'location' => 330,
					'name' => 'Members',
					'children' => array(
						array(
							'name' => 'Roles',
							'link' => '/roles/'
						),
						
						array(
							'name' => 'Preferences',
							'link' => '/preferences/'
						),						
					)
				)
				
			);
		}		
		
		public function roleExists($name){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_members_roles` WHERE `name` = '$name' LIMIT 1");
		}
		
		public function about(){
			return array('name' => 'Members',
						 'version' => '1.0',
						 'release-date' => '2008-04-12',
						 'author' => array('name' => 'Symphony Team',
										   'website' => 'http://www.symphony21.com',
										   'email' => 'team@symphony21.com')
				 		);
		}

		public function install(){
			
			Symphony::Configuration()->set('cookie-prefix', 'sym-members', 'members');
			$this->_Parent->saveConfig();
			
			Symphony::Database()->import("
			
				CREATE TABLE `tbl_fields_member` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				);


				CREATE TABLE `tbl_fields_memberlink` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				);


				CREATE TABLE `tbl_fields_memberrole` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				);

				CREATE TABLE `tbl_members_login_tokens` (
				  `member_id` int(11) unsigned NOT NULL,
				  `token` varchar(8)  NOT NULL,
				  `expiry` int(11) NOT NULL,
				  PRIMARY KEY  (`member_id`),
				  KEY `token` (`token`)
				) ;


				CREATE TABLE `tbl_members_roles` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `name` varchar(60)  NOT NULL,
				  `email_subject` varchar(255)  default NULL,
				  `email_body` longtext ,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `name` (`name`)
				) ;


				CREATE TABLE `tbl_members_roles_event_permissions` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `role_id` int(11) unsigned NOT NULL,
				  `event` varchar(50)  NOT NULL,
				  `action` varchar(60)  NOT NULL,
				  `allow` enum('yes','no')  NOT NULL default 'no',
				  PRIMARY KEY  (`id`),
				  KEY `role_id` (`role_id`,`event`,`action`)
				) ;


				CREATE TABLE `tbl_members_roles_forbidden_pages` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `role_id` int(11) unsigned NOT NULL,
				  `page_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `role_id` (`role_id`,`page_id`)
				)
			
			");
								
			Symphony::Database()->query("INSERT INTO `tbl_members_roles` VALUES (1, 'Guest', NULL, NULL);");
			
		}	

		public function uninstall(){
			Symphony::Configuration()->remove('members');			
			$this->_Parent->saveConfig();
			Symphony::Database()->query("DROP TABLE `tbl_members_login_tokens`, `tbl_members_roles`, `tbl_members_roles_event_permissions`, `tbl_members_roles_forbidden_pages`;");
		}

		public function fetchRole($role_id, $include_permissions=false){
			if(!$row = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_roles` WHERE `id` = $role_id LIMIT 1")) return;
			
			$forbidden_pages = array();
			$event_permissions = array();
			
			if($include_permissions){			
				$forbidden_pages = Symphony::Database()->fetchCol('page_id', "SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = '".$row['id']."' ");

				$tmp = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '".$row['id']."' AND `allow` = 'yes' ");
				if(is_array($tmp) && !empty($tmp)){
					foreach($tmp as $e){
						if(!isset($event_permissions[$e['event']])) 
							$event_permissions[$e['event']] = array();
						
						$event_permissions[$e['event']][$e['action']] = 'yes';
					}
				}
			}
			
			return new Role($row['id'], $row['name'], $event_permissions, $forbidden_pages, $row['email_subject'], $row['email_body']);
		}

		public function fetchRoles($include_permissions=false){
			if(!$rows = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles` ORDER BY `id` ASC")) return;
			
			$roles = array();
			
			foreach($rows as $r){
				
				$forbidden_pages = array();
				$event_permissions = array();
				
				if($include_permissions){	
					$forbidden_pages = Symphony::Database()->fetchCol('page_id', "SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = '".$r['id']."' ");

					$tmp = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '".$r['id']."' AND `allow` = 'yes' ");
					if(is_array($tmp) && !empty($tmp)){
						foreach($tmp as $e){
							if(!isset($event_permissions[$e['event']])) 
								$event_permissions[$e['event']] = array();
							
							$event_permissions[$e['event']][$e['action']] = 'yes';
						}
					}
				}
				
				$roles[] = new Role($r['id'], $r['name'], $event_permissions, $forbidden_pages, $r['email_subject'], $r['email_body']);
				
			}
			
			return $roles;
		}

		public function getSubscribedDelegates(){
			return array(


						array(
							'page' => '/frontend/',
							'delegate' => 'FrontendPageResolved', //'FrontendProcessEvents',
							'callback' => 'checkFrontendPagePermissions'							
						),
						
						array(
							'page' => '/frontend/',
							'delegate' => 'FrontendProcessEvents',
							'callback' => 'appendLoginStatusToEventXML'							
						),
					
						array(
							'page' => '/frontend/',
							'delegate' => 'EventPostSaveFilter',
							'callback' => 'processEventData'							
						),
						
						array(
							'page' => '/frontend/',
							'delegate' => 'EventPreSaveFilter',
							'callback' => 'checkEventPermissions'							
						),							
																		
						array(
							'page' => '/publish/new/',
							'delegate' => 'EntryPostCreate',
							'callback' => 'emailNewMember'
						),		
						
			);
		}
		
		public static function purgeTokens($member_id=NULL){
			Symphony::Database()->query("DELETE FROM `tbl_members_login_tokens` WHERE `expiry` <= ".time().($member_id ? " OR `member_id` = '$member_id'" : NULL));
		}
		
		public function generateToken($member_id){
			
			## First check if a token already exists
			$token = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_login_tokens` WHERE `member_id` = '$member_id' AND `expiry` > ".time()." LIMIT 1");
			
			if(is_array($token) && strlen($token['token']) == 8) return $token['token'];
				
			## Generate a token
			$token = rand(10000000, 99999999); //substr(md5(time() . rand(0, 5000)), 0, 8);
			
			Symphony::Database()->insert(array('member_id' => $member_id, 'token' => $token, 'expiry' => (time() + self::TOKEN_EXPIRY_TIME)), 'tbl_members_login_tokens', true);

			return $token;
		}
		
		public function sendForgotPasswordEmail($member_id){
			
			$entry = $this->fetchMemberFromID($member_id);
			
			if(!($entry instanceof Entry)){
				throw new Exception('Invalid member ID specified');
			}
			
			$token = $this->generateToken($member_id);
			
			$role_data = $entry->getData($this->roleField());
						
			$email_address_data = $entry->getData(self::memberEmailFieldID());
			
			$to_address = $email_address_data['value'];
			$subject = $this->__replaceFieldsInString(stripslashes(Symphony::Configuration()->get('forgotten_pass_email_subject', 'members')), $entry);
			$body = $this->__replaceFieldsInString(stripslashes(Symphony::Configuration()->get('forgotten_pass_email_body', 'members')), $entry);
			
			$body = str_replace(array('{$root}', '{$member-token}'), array(URL, $token), $body);

			$body = str_replace('{$' . $this->usernameAndPasswordFieldHandle() . '::username}', $fields[$this->usernameAndPasswordFieldHandle()]['username'], $body);
	
			$sender_email = 'noreply@' . parse_url(URL, PHP_URL_HOST);
			$sender_name = Symphony::Configuration()->get('sitename', 'general');
		
			General::sendEmail($to_address,  $sender_email, $sender_name, $subject, $body);			
		}
		
		private function __sendNewRegistrationEmail(Entry $entry, array $fields=array()){

			$role_data = $entry->getData($this->roleField());
			
			if(!$role = $this->fetchRole($role_data['role_id'])) return;
			
			if($role->email_body() == NULL || self::memberEmailFieldID() == NULL || $role->email_subject() == NULL) return;
						
			$email_address_data = $entry->getData(self::memberEmailFieldID());
			
			$to_address = $email_address_data['value'];
			$subject = $this->__replaceFieldsInString($role->email_subject(), $entry);
			$body = $this->__replaceFieldsInString($role->email_body(), $entry);

			$token = $this->generateToken($entry->get('id'));
			
			$body = str_replace(array('{$root}', '{$activation-token}'), array(URL, $token), $body);
	
			$body = str_replace('{$' . $this->usernameAndPasswordFieldHandle() . '::plaintext-password}', $fields[$this->usernameAndPasswordFieldHandle()]['password'], $body);
			$body = str_replace('{$' . $this->usernameAndPasswordFieldHandle() . '::username}', $fields[$this->usernameAndPasswordFieldHandle()]['username'], $body);			

			$sender_email = 'noreply@' . parse_url(URL, PHP_URL_HOST);
			$sender_name = Symphony::Configuration()->get('sitename', 'general');
	
			General::sendEmail($to_address,  $sender_email, $sender_name, $subject, $body);
						
		}
		
		public function emailNewMember($context){
			if($context['section'] == $this->memberSectionHandle()) return $this->__sendNewRegistrationEmail($context['entry'], $context['fields']);
		}

		public function processEventData($context){
			if($context['event']->getSource() == self::memberSectionID() && isset($_POST['action']['save-member'])){
				return $this->__sendNewRegistrationEmail($context['entry'], $context['fields']);	
			}
		}
		
		public function checkEventPermissions($context){
			$action = 'add';
			$entry_id = NULL;
			
			if(isset($_POST['id'])){
				$entry_id = (int)$_POST['id'];
				$action = 'edit';
			}
			
			$this->initialiseCookie();
			$this->initialiseMemberObject();
			$isLoggedIn = $this->isLoggedIn();	
			
			if($isLoggedIn && is_object($this->Member)){
				$role_data = $this->Member->getData($this->roleField());
			}
			
			$role = $this->fetchRole(($isLoggedIn ? $role_data['role_id'] : 1), true);
			
			$event_handle = strtolower(preg_replace('/^event/i', NULL, get_class($context['event'])));
			
			$is_owner = false;
			
			if($action == 'edit'){
				$section_id = $context['event']->getSource();
			
				$member_field = Symphony::Database()->fetchVar(
					'id', 0, 
					"SELECT `id` FROM `tbl_fields` WHERE `parent_section` = {$section_id} AND `type` = 'memberlink' LIMIT 1"
				);
				
				$member_id = Symphony::Database()->fetchVar(
					'member_id', 0,
					"SELECT `member_id` FROM `tbl_entries_data_{$member_field}` WHERE `entry_id` = {$entry_id} LIMIT 1"
				);
			
				$is_owner = ($isLoggedIn ? ((int)$this->Member->get('id') == $member_id) : false);

			}
			
			$success = false;
			if($role->canPerformEventAction($event_handle, $action) || ($is_owner === true && $role->canPerformEventAction($event_handle, "{$action}_own"))){
				$success = true;
			}
			
			$context['messages'][] = array('permission', $success, ($success === false ? 'not authorised to perform this action' : NULL));
			
		}
		
		private function __replaceFieldsInString($string, Entry $entry){
			
			$fields = $this->__findFieldsInString($string, true);

			if(is_array($fields) && !empty($fields)){
				
				$FieldManager = new FieldManager($this->_Parent);
				
				foreach($fields as $element_name => $field_id){

					if($field_id == NULL) continue;
					
					$field_data = $entry->getData($field_id);
					$fieldObj = $FieldManager->fetch($field_id);
					$value = $fieldObj->prepareTableValue($field_data);
					
					$string = str_replace('{$'.$element_name.'}', $value, $string);
					$string = str_replace('{$'.$element_name.'::handle}', Lang::createHandle($value), $string);
				}
			}
			
			return $string;
			
		}
		
		private function __findFieldsInString($string, $resolveIDValues=false){
			
			preg_match_all('/{\$([^:}]+)(::handle)?}/', $string, $matches);

			$field_handles = array_unique($matches[1]);
			
			if(!$resolveIDValues || !is_array($field_handles) || empty($field_handles)) return array();			
			
			$fields = array();
			foreach($field_handles as $h){
				$field_id = Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `element_name` = '$h' AND `parent_section` = ".self::memberSectionID()." LIMIT 1");
				
				$fields[$h] = $field_id;
				
			}
			
			return $fields;
			
		}
		
		public function appendLoginStatusToEventXML($context){

			$this->initialiseCookie();
			
			## Cookies only show up on page refresh. This flag helps in making sure the correct XML is being set
			$loggedin = $this->isLoggedIn();
			
			$this->initialiseMemberObject();
			
			if($loggedin == true){
				$this->__updateSystemTimezoneOffset();
			}

			$context['wrapper']->appendChild($this->buildXML());	
			
		}
		
		public function checkFrontendPagePermissions($context){

			$this->initialiseCookie();
			
			## Cookies only show up on page refresh. This flag helps in making sure the correct XML is being set
			$loggedin = false;
			
			$action = $_REQUEST['member-action'];

			if(trim($action) == 'logout'){
				$this->logout();
				redirect(URL);
			}
			
			elseif(isset($action['login'])){	
								
				$username = Symphony::Database()->cleanValue($_REQUEST['username']);
				$password = Symphony::Database()->cleanValue($_REQUEST['password']);	
				
				if($this->login($username, $password)){ 
					
					if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);
					
					redirect(URL . $_SERVER['REQUEST_URI']);
				}
				
				self::$_failed_login_attempt = true;
				
			}
			
			elseif(isset($context['env']['url']['member-token']) && preg_match('/^[a-f0-9]{8}$/', $context['env']['url']['member-token'])){

				$token = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_login_tokens` WHERE `token` = '".$context['env']['url']['member-token']."' LIMIT 1");
				
				if(is_array($token) && !empty($token)){
					$entry = $this->fetchMemberFromID($token['member_id']);
					$username_field_data = $entry->getData($this->usernameAndPasswordField());

					$loggedin = $this->login($username_field_data['username'], $username_field_data['password'], true);
					
					self::purgeTokens($token['member_id']);
				}
				
				
			}
			
			else $loggedin = $this->isLoggedIn();

			
			$this->initialiseMemberObject();

			if($loggedin && is_object($this->Member)){
				$role_data = $this->Member->getData($this->roleField());
			}
						
			$role = $this->fetchRole(($loggedin ? $role_data['role_id'] : 1), true);
		
			if(!$role->canAccessPage((int)$context['page_data']['id'])):

				/*
					Array
					(
					    [id] => 115
					    [parent] => 91
					    [title] => New
					    [handle] => new
					    [path] => downloads
					    [params] => type
					    [data_sources] => menu
					    [events] => save_download
					    [sortorder] => 13
					    [type] => Array
					        (
					        )

					    [filelocation] => /Users/pointybeard/Sites/projects/overture/public/workspace/pages/downloads_new.xsl
					)
					
					Array
					(
					    [id] => 136
					    [parent] => 
					    [title] => Forbidden
					    [handle] => forbidden
					    [path] => 
					    [params] => 
					    [data_sources] => menu
					    [events] => 
					    [sortorder] => 37
					)
				*/

				if($row = Symphony::Database()->fetchRow(0, "SELECT `tbl_pages`.* FROM `tbl_pages`, `tbl_pages_types` 
															  WHERE `tbl_pages_types`.page_id = `tbl_pages`.id AND tbl_pages_types.`type` = '403' 
															  LIMIT 1")){
	
					//redirect(URL . '/' . $row['path'] . '/' . $row['handle']);
					
					//$page['filelocation'] = $this->resolvePageFileLocation($page['path'], $page['handle']);
					//$page['type'] = $this->__fetchPageTypes($page['id']);
					
					$row['type'] = Symphony::Database()->fetchCol('type', "SELECT `type` FROM `tbl_pages_types` WHERE `page_id` = '".$row['id']."' ");
					$row['filelocation'] = (PAGES . '/' . trim(str_replace('/', '_', $row['path'] . '_' . $row['handle']), '_') . '.xsl');
					
					$context['page_data'] = $row;
					return;
					
				}
				
				$this->_Parent->customError(E_USER_ERROR, 'Forbidden', 'Please <a href="'.URL.'/symphony/login/">login</a> to view this page.', false, true, 'error', array('header' => 'HTTP/1.0 403 Forbidden'));
				
			endif;

			//$context['wrapper']->appendChild($this->buildXML());
						
		}
	
		public function roleField(){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = '".self::memberSectionID()."' AND `type` = 'memberrole' LIMIT 1");
		}
		
		public function usernameAndPasswordField(){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = '".self::memberSectionID()."' AND `type` = 'member' LIMIT 1");			
		}

		public function usernameAndPasswordFieldHandle(){
			return Symphony::Database()->fetchVar('element_name', 0, "SELECT `element_name` FROM `tbl_fields` WHERE `parent_section` = '".self::memberSectionID()."' AND `type` = 'member' LIMIT 1");			
		}
		
		public static function memberSectionID(){
			return (int)Symphony::Configuration()->get('member_section', 'members');
		}
		
		public static function memberEmailFieldID(){
			return (int)Symphony::Configuration()->get('email_address_field_id', 'members');
		}
		
		public static function memberTimezoneOffsetFieldID(){
			return (int)Symphony::Configuration()->get('timezone_offset_field_id', 'members');
		}		
					
		public function memberSectionHandle(){
			$section_id = self::memberSectionID();
			
			return Symphony::Database()->fetchVar('handle', 0, "SELECT `handle` FROM `tbl_sections` WHERE `id` = $section_id LIMIT 1");
		}	
		
		private function __updateSystemTimezoneOffset(){

			
			$offset = Symphony::Database()->fetchVar('value', 0, "SELECT `value` 
																	FROM `tbl_entries_data_".self::memberTimezoneOffsetFieldID()."` 
																	WHERE `entry_id` = '".Symphony::Database()->cleanValue($this->Member->get('id'))."'
																	LIMIT 1");
			
			if(strlen(trim($offset)) == 0) return;
															
			//When using 'Etc/GMT...' the +/- signs are reversed. E.G. GMT+10 == Etc/GMT-10
			DateTimeObj::setDefaultTimezone('Etc/GMT' . ($offset >= 0 ? '-' : '+') . abs($offset)); 

		}
		
		public function buildXML(){
			
			if(!empty($this->_member_id)){
				$result = new XMLElement('member-login-info');
				$result->setAttribute('logged-in', 'true');

				if(!$this->Member) $this->initialiseMemberObject();

				$result->setAttributeArray(array('id' => $this->Member->get('id')));

				$entryManager = new EntryManager($this->_Parent);

				foreach($this->Member->getData() as $field_id => $values){

					if(!isset($fieldPool[$field_id]) || !is_object($fieldPool[$field_id]))
						$fieldPool[$field_id] =& $entryManager->fieldManager->fetch($field_id);

					$fieldPool[$field_id]->appendFormattedElement($result, $values, false);

				}
				
				$role_data = $this->Member->getData($this->roleField());
				$role = $this->fetchRole($role_data['role_id'], true);
			
				$permission = new XMLElement('permissions');
				
				$forbidden_pages = $role->forbiddenPages();
				if(is_array($forbidden_pages) && !empty($forbidden_pages)){
					$pages = new XMLElement('forbidden-pages');
					foreach($forbidden_pages as $page_id) 
						$pages->appendChild(new XMLElement('page', NULL, array('id' => $page_id)));
						
					$permission->appendChild($pages);
				}

				$event_permissions = $role->eventPermissions();
				if(is_array($event_permissions) && !empty($event_permissions)){

					foreach($event_permissions as $event_handle => $e){
						$obj = new XMLElement($event_handle);
						
						foreach(array_keys($e) as $action) $obj->appendChild(new XMLElement($action));
						
						$permission->appendChild($obj);
					}
					
				}
				
				$result->appendChild($permission);
			}
			
			else{
				$result = new XMLElement('member-login-info');
				$result->setAttribute('logged-in', 'false');
				
				if(self::$_failed_login_attempt === true){
					$result->setAttribute('failed-login-attempt', 'true');
				}
			}
			
			return $result;
			
		}
		
		public function initialiseMemberObject($member_id = NULL){
			
			$member_id = ($member_id ? $member_id : $this->_member_id);

			$this->Member = $this->fetchMemberFromID($member_id);
			
			return $this->Member;
		}
		
		public function fetchMemberFromID($member_id){
		
			$entryManager = new EntryManager($this->_Parent);
			$Member = $entryManager->fetch($member_id, NULL, NULL, NULL, NULL, NULL, false, true);
			$Member = $Member[0];
			
			return $Member;			
		}
		
		public function initialiseCookie(){
			if(!$this->_cookie) $this->_cookie =& new Cookie(Symphony::Configuration()->get('cookie-prefix', 'members'), TWO_WEEKS, __SYM_COOKIE_PATH__);
		}
		
		private function __findMemberIDFromCredentials($username, $password){
			$entry_id = Symphony::Database()->fetchVar('entry_id', 0, "SELECT `entry_id` 
																		   FROM `tbl_entries_data_".$this->usernameAndPasswordField()."` 
																		   WHERE `username` = '".Symphony::Database()->cleanValue($username)."' 
																			AND `password` = '".Symphony::Database()->cleanValue($password)."' 
																		   LIMIT 1");
			
			return (is_null($entry_id) ? NULL : $entry_id);
		}

		public function findMemberIDFromEmail($email){
			return Symphony::Database()->fetchCol('entry_id', "SELECT `entry_id` 
																		   FROM `tbl_entries_data_".self::memberEmailFieldID()."` 
																		   WHERE `value` = '".Symphony::Database()->cleanValue($email)."'");	
		}
		
		public function findMemberIDFromUsername($username){
			return Symphony::Database()->fetchVar('entry_id', 0, "SELECT `entry_id` 
																FROM `tbl_entries_data_".$this->usernameAndPasswordField()."` 
															 	WHERE `username` = '".Symphony::Database()->cleanValue($username)."' 
																LIMIT 1");	
		}
				
		public function isLoggedIn(){
			
			if($id = $this->__findMemberIDFromCredentials($this->_cookie->get('username'), $this->_cookie->get('password'))){
				$this->_member_id = $id;
				return true;
			}
			
			$this->_cookie->expire();
			return false;
		}

		public function logout(){
			$this->_cookie->expire();
		}
		
		public function login($username, $password, $isHash=false){
			
			if(!$isHash) $password = md5($password);
		
			if($id = $this->__findMemberIDFromCredentials($username, $password)){
				$this->_member_id = $id;
				
				try{	
					$this->_cookie->set('username', $username);
					$this->_cookie->set('password', $password);
										
				}catch(Exception $e){
					trigger_error($e->message(), E_USER_ERROR);
				}
				
				return true;
			}

			return false;
			
		}
		
		public static function buildRolePermissionTableBody(array $rows){
			$array = array();
			foreach($rows as $r){
				$array[] = self::buildRolePermissionTableRow($r[0], $r[1], $r[2], $r[3]);
			}
			return $array;
		}
		
		public static function buildRolePermissionTableRow($label, $event, $handle, $checked=false){
			$td1 = Widget::TableData($label);
			$td2 = Widget::TableData(Widget::Input('fields[permissions]['.$event.']['.$handle.']', 'yes', 'checkbox', ($checked === true ? array('checked' => 'checked') : NULL)));
			return Widget::TableRow(array($td1, $td2));	
		}

	}

