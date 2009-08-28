<?php

	require_once(TOOLKIT . '/class.event.php');
	require_once(DOCROOT . '/extensions/asdc/lib/class.asdc.php');
		
	Class eventforgot_password extends Event{
		
		private static $_fields;
		private static $_sections;
						
		public static function about(){
			return array(
					 'name' => 'Members: Forgot Password',
					 'author' => array(
							'name' => 'Symphony Team',
							'website' => 'http://randomhouse.local:8888',
							'email' => 'team@symphony21.com'),
					 'version' => '1.0',
					 'release-date' => '2008-04-21');	
		}
	
		public function load(){
			if(isset($_POST['action']['member-retrieve-password'])) return $this->__trigger();
		}
		
		public static function documentation(){
			return '		
				
				<h3>Example Form</h3>
				<p><pre><code>
				  &lt;form action="" method="post">
					&lt;p>Supply either username or email address&lt;/p>
				    &lt;input name="member-email-address" type="text"/>
					&lt;input name="member-username" type="text"/>
				    &lt;input name="action[member-retrieve-password]" value="go" type="submit"/>
				  &lt;/form></code></pre>				
				</p>
				
				<h3>Example Response</h3>
				<p><code>&lt;forgot-password sent="true">Email sent&lt;/forgot-password></code></p>
			
			';
		}
		
		public static function findSectionID($handle){
			return self::$_sections[$handle];
		}

		public static function findFieldID($handle, $section){
			return self::$_fields[$section][$handle];
		}
	
		private static function __init(){
			if(!is_array(self::$_fields)){
				self::$_fields = array();

				$rows = ASDCLoader::instance()->query("SELECT s.handle AS `section`, f.`element_name` AS `handle`, f.`id` 
					FROM `tbl_fields` AS `f` 
					LEFT JOIN `tbl_sections` AS `s` ON f.parent_section = s.id 
					ORDER BY `id` ASC");

				if($rows->length() > 0){
					foreach($rows as $r){
						self::$_fields[$r->section][$r->handle] = $r->id;
					}							
				}
			}

			if(!is_array(self::$_sections)){
				self::$_sections = array();

				$rows = ASDCLoader::instance()->query("SELECT s.handle, s.id 
					FROM `tbl_sections` AS `s`
					ORDER BY s.id ASC");

				if($rows->length() > 0){
					foreach($rows as $r){
						self::$_sections[$r->handle] = $r->id;
					}							
				}
			}			
		}	
			
		protected function __trigger(){

			$success = true;
			$result = new XMLElement('forgot-password');
			
			$Members = $this->_Parent->ExtensionManager->create('members');
						
			$username = $email = $code = NULL;
			
			if(isset($_POST['fields']['code']) && strlen(trim($_POST['fields']['code'])) > 0){
				$code = $_POST['fields']['code'];
				$new_password = General::generatePassword();
				
				self::__init();
				$db = ASDCLoader::instance();
				
				// Make sure we dont accidently use an expired token
				extension_Members::purgeTokens();

				$token_row = $db->query(
					sprintf(
						"SELECT * FROM `tbl_members_login_tokens` WHERE `token` = '%s' LIMIT 1", 
						$db->escape($code)
					)
				)->current();

				// No code, you are a spy!
				if($token_row === false){
					redirect(URL . '/members/reset-pass/failed/');
				}
				
				// Attempt to update the password
				$db->query(sprintf(
					"UPDATE `tbl_entries_data_%d` SET `password` = '%s' WHERE `entry_id` = %d LIMIT 1",
					$Members->usernameAndPasswordField(),
					md5($new_password),
					$token_row->member_id
				));

				extension_Members::purgeTokens($token_row->member_id);
				
				// SEND THE EMAIL!!
				$entry = $Members->initialiseMemberObject($token_row->member_id);
				
				$email_address = $entry->getData(self::findFieldID('email-address', 'members'));
				$name = $entry->getData(self::findFieldID('name', 'members'));				
				
				$subject = 'Your new password';
				$body = 'Dear {$name},

Just now, you have asked the Symphony brain trust to bestow you with a new password.

Well, here it is: {$new-password}

There\'s a good chance that you won\'t like this new password and want to change it - don\'t worry, we\'re not offended.

You can do that once you\'ve logged in by going here: {$root}/members/change-pass/

If you have any trouble, please email us at support@symphony-cms.com and we\'ll do our best to help.

Regards,

Symphony Team';
								
				$body = str_replace(array('{$name}', '{$root}', '{$new-password}'), array($name['value'], URL, $new_password), $body);

				$sender_email = 'noreply@' . parse_url(URL, PHP_URL_HOST);
				$sender_name = Symphony::Configuration()->get('sitename', 'general');

				General::sendEmail($email_address['value'], $sender_email, $sender_name, $subject, $body);
					
				redirect(URL . '/members/reset-pass/success/');
				
			}
			
			
			// Username take precedence
			if(isset($_POST['fields']['member-username']) && strlen(trim($_POST['fields']['member-username'])) > 0){
				$username = $_POST['fields']['member-username'];
			}
			
			if(isset($_POST['fields']['member-email-address']) && strlen(trim($_POST['fields']['member-email-address'])) > 0){
				$email = $_POST['fields']['member-email-address'];
			}
			
			if(is_null($username) && is_null($email)){
				$success = false;
				$result->appendChild(new XMLElement('member-username', NULL, array('type' => 'missing')));
				$result->appendChild(new XMLElement('member-email-address', NULL, array('type' => 'missing')));						
			}
			
			else{
				
				$members = array();
				
				if(!is_null($email)){
					$members = $Members->findMemberIDFromEmail($email);	
				}

				if(!is_null($username)){
					$members[] = $Members->findMemberIDFromUsername($username);
				}
			
				// remove duplicates
				$members = array_unique($members);

				try{
				
					if(is_array($members) && !empty($members)){				
						foreach($members as $member_id){
							$Members->sendForgotPasswordEmail($member_id);
						}
						
						redirect(URL . '/members/reset-pass/code/');
					}
					
					
				}
				catch(Exception $e){
					// Shouldn't get here, but will catch an invalid member ID if it does
				}
				
				$success = false;
				
			}
			
			$result->setAttribute('status', ($success === true ? 'success' : 'error'));

			return $result;
		}		

	}
