<?php

	require_once(TOOLKIT . '/class.event.php');
	
	Class eventforgot_password extends Event{
					
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
				    &lt;input name="member-email-address" type="text"/>
				    &lt;input name="action[member-retrieve-password]" value="go" type="submit"/>
				  &lt;/form></code></pre>				
				</p>
				
				<h3>Example Response</h3>
				<p><code>&lt;forgot-password sent="true">Email sent&lt;/forgot-password></code></p>
			
			';
		}
		
		protected function __trigger(){
			
			$ExtensionManager = new ExtensionManager($this->_Parent);
			$driver = $ExtensionManager->create('members');
			
			$email = $_POST['member-email-address'];
			
			if($members = $driver->findMemberIDFromEmail($email)){				
				foreach($members as $member_id) $driver->sendForgotPasswordEmail($member_id);
			}
			
			return new XMLElement('forgot-password', 'Email sent', array('sent' => 'true'));
		}		

	}
