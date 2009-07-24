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
		
		private $_page_permissions;
		private $_event_permissions;
		
		public function __construct($id, $name, array $event_permissions=array(), array $page_permissions=array(), $email_subject=NULL, $email_body=NULL){
			$this->_id = $id;
			$this->_name = $name;
			$this->_page_permissions = $page_permissions;
			$this->_event_permissions = $event_permissions;
			
			$this->_email_body = $email_body;
			$this->_email_subject = $email_subject;
		}
		
		private function __call($name, $var){
			return $this->{"_$name"};
		}
		
		public function pagePermissions(){
			return $this->_page_permissions;
		}
		
		public function eventPermissions(){
			return $this->_event_permissions;			
		}
		
		public function canAccessPage($page_id){
			return @in_array($page_id, $this->_page_permissions);
		}
		
		public function canPerformEventAction($event_handle, $action){	
			return ($this->_event_permissions[$event_handle][$action] == 'yes');
		}		
		
	}

	Final Class extension_members extends Extension{
		
		private $_cookie;
		private $_member_id;
		public $Member;
		
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
			
			ConfigurationAccessor::set('cookie-prefix', 'sym-members', 'members');
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


				CREATE TABLE `tbl_members_roles_page_permissions` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `role_id` int(11) unsigned NOT NULL,
				  `page_id` int(11) unsigned NOT NULL,
				  `allow` enum('yes','no')  NOT NULL default 'no',
				  PRIMARY KEY  (`id`),
				  KEY `role_id` (`role_id`,`page_id`)
				)
			
			");
								
			Symphony::Database()->query("INSERT INTO `tbl_members_roles` VALUES (1, 'Guest', NULL, NULL);");
			
		}	

		public function uninstall(){
			ConfigurationAccessor::remove('members');			
			$this->_Parent->saveConfig();
			Symphony::Database()->query("DROP TABLE `tbl_members_login_tokens`, `tbl_members_roles`, `tbl_members_roles_event_permissions`, `tbl_members_roles_page_permissions`;");
		}

		public function fetchRole($role_id, $include_permissions=false){
			if(!$row = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_roles` WHERE `id` = $role_id LIMIT 1")) return;
			
			$page_permissions = array();
			$event_permissions = array();
			
			if($include_permissions){			
				$page_permissions = Symphony::Database()->fetchCol('page_id', "SELECT `page_id` FROM `tbl_members_roles_page_permissions` WHERE `role_id` = '".$row['id']."' ");

				$tmp = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '".$row['id']."' AND `allow` = 'yes' ");
				if(is_array($tmp) && !empty($tmp)){
					foreach($tmp as $e){
						if(!isset($event_permissions[$e['event']])) 
							$event_permissions[$e['event']] = array();
						
						$event_permissions[$e['event']][$e['action']] = 'yes';
					}
				}
			}
			
			return new Role($row['id'], $row['name'], $event_permissions, $page_permissions, $row['email_subject'], $row['email_body']);
		}

		public function fetchRoles($include_permissions=false){
			if(!$rows = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles` ORDER BY `id` ASC")) return;
			
			$roles = array();
			
			foreach($rows as $r){
				
				$page_permissions = array();
				$event_permissions = array();
				
				if($include_permissions){	
					$page_permissions = Symphony::Database()->fetchCol('page_id', "SELECT `page_id` FROM `tbl_members_roles_page_permissions` WHERE `role_id` = '".$r['id']."' ");

					$tmp = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '".$r['id']."' AND `allow` = 'yes' ");
					if(is_array($tmp) && !empty($tmp)){
						foreach($tmp as $e){
							if(!isset($event_permissions[$e['event']])) 
								$event_permissions[$e['event']] = array();
							
							$event_permissions[$e['event']][$e['action']] = 'yes';
						}
					}
				}
				
				$roles[] = new Role($r['id'], $r['name'], $event_permissions, $page_permissions, $r['email_subject'], $r['email_body']);
				
			}
			
			return $roles;
		}

		public function getSubscribedDelegates(){
			return array(


						array(
							'page' => '/frontend/',
							'delegate' => 'FrontendProcessEvents',
							'callback' => 'checkFrontendPagePermissions'							
						),

						array(
							'page' => '/frontend/',
							'delegate' => 'EventPostSaveFilter',
							'callback' => 'processEventData'							
						),
																		
						array(
							'page' => '/publish/new/',
							'delegate' => 'EntryPostCreate',
							'callback' => 'emailNewMember'
						),		
						
						array(
							'page'		=> '/frontend/',
							'delegate'	=> 'FrontendParamsResolve',
							'callback'	=> 'addParam'
						),
			);
		}
		
		private function __purgeTokens($member_id=NULL){
			Symphony::Database()->query("DELETE FROM `tbl_members_login_tokens` WHERE `expiry` <= ".time().($member_id ? " OR `member_id` = '$member_id'" : NULL));
		}
		
		public function generateToken($member_id){
			
			## First check if a token already exists
			$token = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_login_tokens` WHERE `member_id` = '$member_id' AND `expiry` > ".time()." LIMIT 1");
			
			if(is_array($token) && strlen($token['token']) == 8) return $token['token'];
				
			## Generate a token
			$token = substr(md5(time() . rand(0, 5000)), 0, 8);
			
			Symphony::Database()->insert(array('member_id' => $member_id, 'token' => $token, 'expiry' => (time() + 10800)), 'tbl_members_login_tokens', true);

			return $token;
		}
		
		public function sendForgotPasswordEmail($member_id){
			
			$entry = $this->fetchMemberFromID($member_id);
			
			$token = $this->generateToken($member_id);
			
			$role_data = $entry->getData($this->roleField());
						
			$email_address_data = $entry->getData(self::memberEmailFieldID());
			
			$to_address = $email_address_data['value'];
			$subject = $this->__replaceFieldsInString(stripslashes(ConfigurationAccessor::get('forgotten_pass_email_subject', 'members')), $entry);
			$body = $this->__replaceFieldsInString(stripslashes(ConfigurationAccessor::get('forgotten_pass_email_body', 'members')), $entry);
			
			$body = str_replace('{$member-token}', $token, $body);
			
			$sender_email = 'noreply@' . parse_url(URL, PHP_URL_HOST);
			$sender_name = ConfigurationAccessor::get('sitename', 'general');
		
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

			$body = str_replace('{$' . $this->usernameAndPasswordFieldHandle() . '::plaintext-password}', $fields[$this->usernameAndPasswordFieldHandle()]['password'], $body);
			
			$sender_email = 'noreply@' . parse_url(URL, PHP_URL_HOST);
			$sender_name = ConfigurationAccessor::get('sitename', 'general');
	
			General::sendEmail($to_address,  $sender_email, $sender_name, $subject, $body);
						
		}
		
		public function addParam($context) {

			$this->initialiseCookie();

			if($id = $this->__findMemberIDFromCredentials($this->_cookie->get('username'), $this->_cookie->get('password'))){
				$context = $context['params']['member-id'] = $id;
			}
		}
		
		public function emailNewMember($context){
			if($context['section'] == $this->memberSectionHandle()) return $this->__sendNewRegistrationEmail($context['entry'], $context['fields']);
		}

		public function processEventData($context){
			if($context['event']->getSource() == self::memberSectionID()) return $this->__sendNewRegistrationEmail($context['entry'], $context['fields']);	
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
				$username = $_REQUEST['username'];
				$password = $_REQUEST['password'];	
				
				if($this->login($username, $password)){ 
					
					if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);
					
					redirect(URL . $_SERVER['REQUEST_URI']);
				}
				
			}
			
			elseif(isset($context['env']['url']['member-token']) && preg_match('/^[a-f0-9]{8}$/', $context['env']['url']['member-token'])){

				$token = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_login_tokens` WHERE `token` = '".$context['env']['url']['member-token']."' LIMIT 1");
				
				if(is_array($token) && !empty($token)){
					$entry = $this->fetchMemberFromID($token['member_id']);
					$username_field_data = $entry->getData($this->usernameAndPasswordField());

					$loggedin = $this->login($username_field_data['username'], $username_field_data['password'], true);
					
					$this->__purgeTokens($token['member_id']);
				}
				
				
			}
			
			else $loggedin = $this->isLoggedIn();

			
			$this->initialiseMemberObject();

			if($loggedin && is_object($this->Member)){
				$role_data = $this->Member->getData($this->roleField());
			}
						
			$role = $this->fetchRole(($loggedin ? $role_data['role_id'] : 1), true);
		
			if(!$role->canAccessPage((int)$context['page_data']['id'])):

				if($row = Symphony::Database()->fetchRow(0, "SELECT `tbl_pages`.* FROM `tbl_pages`, `tbl_pages_types` 
															  WHERE `tbl_pages_types`.page_id = `tbl_pages`.id AND tbl_pages_types.`type` = '403' 
															  LIMIT 1")){
	
					redirect(URL . '/' . $row['path'] . '/' . $row['handle']);
																
				}
				
				$this->_Parent->customError(E_USER_ERROR, 'Forbidden', 'Please <a href="'.URL.'/symphony/login/">login</a> to view this page.', false, true, 'error', array('header' => 'HTTP/1.0 403 Forbidden'));
				
			endif;

			$context['wrapper']->appendChild($this->buildXML());
						
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
			return (int)ConfigurationAccessor::get('member_section', 'members');
		}
		
		public static function memberEmailFieldID(){
			return (int)ConfigurationAccessor::get('email_address_field_id', 'members');
		}
					
		public function memberSectionHandle(){
			$section_id = self::memberSectionID();
			
			return Symphony::Database()->fetchVar('handle', 0, "SELECT `handle` FROM `tbl_sections` WHERE `id` = $section_id LIMIT 1");
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
				
				$page_permissions = $role->pagePermissions();
				if(is_array($page_permissions) && !empty($page_permissions)){
					$pages = new XMLElement('pages');
					foreach($page_permissions as $page_id) 
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
			if(!$this->_cookie) $this->_cookie =& new Cookie(ConfigurationAccessor::get('cookie-prefix', 'members'), TWO_WEEKS, __SYM_COOKIE_PATH__);
		}
		
		private function __findMemberIDFromCredentials($username, $password){
			$entry_id = Symphony::Database()->fetchVar('entry_id', 0, "SELECT `entry_id` 
																		   FROM `tbl_entries_data_".$this->usernameAndPasswordField()."` 
																		   WHERE `username` = '".$username."' AND `password` = '".$password."' 
																		   LIMIT 1");
			
			return (is_null($entry_id) ? NULL : $entry_id);
		}

		public function findMemberIDFromEmail($email){
			return Symphony::Database()->fetchCol('entry_id', "SELECT `entry_id` 
																		   FROM `tbl_entries_data_".self::memberEmailFieldID()."` 
																		   WHERE `value` = '".$email."' 
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

	}

