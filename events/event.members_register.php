<?php

	require_once(TOOLKIT . '/class.event.php');

	Class eventmembers_Register extends Event{

		const ROOTELEMENT = 'members-register';

		private $_driver;

		public function __construct(&$parent, $env=NULL){
			parent::__construct($parent, $env);
			$this->_driver = $this->_Parent->ExtensionManager->create('members');
		}

		public static function showInRolePermissions(){
			return true;
		}

		public static function about(){
			return array(
					 'name' => 'Members: Register',
					 'author' => array(
							'name' => 'Symphony Team',
							'website' => 'http://symphony-cms.com',
							'email' => 'alistair@symphony-cms.com'),
					 'version' => '1.0',
					 'release-date' => '2010-02-05T02:35:13+00:00',
					 'trigger-condition' => 'action[members-register]');
		}

		public static function getSource(){
			return extension_Members::getConfigVar('member_section');
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '
				<p>This event allows new members to register.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. Be sure to adjust the inputs and field names to correspond to your own member section.</p>
				<pre class="XML"><code>&lt;form method="post" action=""&gt;
	&lt;label&gt;Name
		&lt;input name="fields[name]" type="text" /&gt;
	&lt;/label&gt;
	&lt;label&gt;Username
		&lt;input name="fields[username-and-password][username]" type="text" /&gt;
	&lt;/label&gt;
	&lt;label&gt;Password
		&lt;input name="fields[username-and-password][password]" type="password" /&gt;
	&lt;/label&gt;
	&lt;label&gt;Email Address
		&lt;input name="fields[email-address]" type="text" /&gt;
	&lt;/label&gt;
	&lt;label&gt;Timezone Offset
		&lt;input name="fields[timezone-offset]" type="text" /&gt;
	&lt;/label&gt;
	&lt;input name="action['.self::ROOTELEMENT.']" type="submit" value="Submit" /&gt;
&lt;/form&gt;</code></pre>
				<h3>Example Response XML</h3>
				<p>On success...</p>
				<pre class="XML"><code>&lt;'.self::ROOTELEMENT.' id="{new member id}" result="success" type="created"&gt;
	&lt;filter name="permission" status="passed" /&gt;
	&lt;message&gt;Entry created successfully.&lt;/message&gt;
	&lt;post-values&gt;
		&lt;!-- User-submitted POST values --&gt;
		&lt;role&gt;ID of default new member role&lt;/role&gt;
	&lt;/post-values&gt;
&lt;/'.self::ROOTELEMENT.'&gt;</code></pre>
				<p>On failure...</p>
				<pre class="XML"><code>&lt;'.self::ROOTELEMENT.' result="error"&gt;
	&lt;filter name="permission" status="{passed | failed}" /&gt;
	&lt;message&gt;Entry encountered errors when saving.&lt;/message&gt;
	&lt;field-name type="{invalid | missing}" message="{Field validation message}" /&gt;
	&lt;post-values&gt;
		&lt;!-- User-submitted POST values --&gt;
		&lt;role&gt;ID of default new member role&lt;/role&gt;
	&lt;/post-values&gt;
&lt;/'.self::ROOTELEMENT.'&gt;</code></pre>
			';
		}

		public function load(){
			if(isset($_POST['action']['members-register'])) return $this->__trigger();
			if(isset($_GET['first-name'])) return $this->softSignup();
		}

		public function softSignup() {
			$result = new XMLElement(self::ROOTELEMENT);

			$post_values = new XMLElement('post-values');

			General::array_to_xml($post_values, $_GET, true);

			if(isset($post_values) && is_object($post_values)) $result->appendChild($post_values);

			return $result;
		}

		protected function __trigger(){
			$role_field_handle = ASDCLoader::instance()->query(sprintf(
				"SELECT `element_name` FROM `tbl_fields` WHERE `type` = 'memberrole' AND `parent_section` = %d LIMIT 1",
				extension_Members::getConfigVar('member_section')
			))->current()->element_name;

			if(Symphony::Configuration()->get('require_activation', 'members') == 'yes'){
				$role_id = extension_Members::INACTIVE_ROLE_ID;
			}
			else {
				$role_id = extension_Members::getConfigVar('new_member_default_role');
			}

			$_POST['fields'][$role_field_handle] = $role_id;

			$result = new XMLElement(self::ROOTELEMENT);
			$error = false;

			if($_POST['fields']['username-and-password']['username'] == "" && $_POST['fields']['username-and-password']['password'] == "") {
				$error = true;
				$u = new XMLElement('username-and-password');
				$u->setAttribute('type', 'missing');
				$u->setAttribute('message', "Username and Password are required fields.");

				$result->appendChild($u);
			}

			if($error) return $result;

			include(TOOLKIT . '/events/event.section.php');

			$error = false;
			$status = simplexml_load_string($result->generate());

			foreach($status->attributes() as $n => $v) if($n == "result" && $v == "error") {
				$error = true;
				break;
			}

			if(!$error) {
				$this->_driver->Member->login(array(
					'username' => Symphony::Database()->cleanValue($_POST['fields']['username-and-password']['username']),
					'password' => Symphony::Database()->cleanValue($_POST['fields']['username-and-password']['password'])
				));
/*
				if($_SERVER['PHP_AUTH_USER'] !== "battlefront" && $_SERVER['PHP_AUTH_PW'] !== "%4s6;jT") {
					echo "Thanks for registering";
					exit;
				}
*/
				header("Location: ../you/");

			}

			return $result;
		}

	}
