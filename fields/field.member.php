<?php

	Class fieldMember extends Field{

		static private $_driver;
		protected $_strengths = array();
		protected $_strength_map = array();

		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Member: Username &amp; Password');
			$this->_required = true;
			$this->set('required', 'yes');

			if(!(self::$_driver instanceof Extension)){
				if(class_exists('Frontend')){
					self::$_driver = Frontend::instance()->ExtensionManager->create('members');
				}

				else{
					self::$_driver = Administration::instance()->ExtensionManager->create('members');
				}
			}

			$this->_strengths = array(
				array('weak', false, 'Weak'),
				array('good', false, 'Good'),
				array('strong', false, 'Strong')
			);
			$this->_strength_map = array(
				0			=> 1,
				1			=> 1,
				2			=> 2,
				3			=> 3,
				4			=> 3,
				'weak'		=> 1,
				'good'		=> 2,
				'strong'	=> 3
			);
			$this->set('length', '6');
			$this->set('strength', 'good');
		}

		public function createTable(){
			return $this->Database->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `username` varchar(150) default NULL,
				  `password` varchar(32) default NULL,
				  `length` tinyint(2) NOT NULL,
				  `strength` enum('weak', 'good', 'strong') NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `username` (`username`),
				  KEY `length` (`length`)
				) TYPE=MyISAM;"
			);
		}

		function canFilter(){
			return true;
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}

		function allowDatasourceParamOutput(){
			return true;
		}

		function canPrePopulate(){
			return true;
		}

		public function mustBeUnique(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		protected function checkPassword($password) {
			$strength = 0;
			$patterns = array(
				'/[a-z]/', '/[A-Z]/', '/[0-9]/',
				'/[¬!"£$%^&*()`{}\[\]:@~;\'#<>?,.\/\\-=_+\|]/'
			);

			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $password, $matches)) {
					$strength++;
				}
			}

			return $strength;
		}

		protected function compareStrength($a, $b) {
			if ($this->_strength_map[$a] >= $this->_strength_map[$b]) return true;

			return false;
		}

		protected function encodePassword($password) {
			return md5($this->get('salt') . $password);
		}

		protected function getStrengthName($strength) {
			$map = array_flip($this->_strength_map);

			return $map[$strength];
		}

		protected function rememberSalt() {
			$field_id = $this->get('id');

			$salt = Symphony::Database()->fetchVar('salt', 0, "
				SELECT
					f.salt
				FROM
					`tbl_fields_member` AS f
				WHERE
					f.field_id = '$field_id'
				LIMIT 1
			");

			if ($salt and !$this->get('salt')) {
				$this->set('salt', $salt);
			}
		}

		protected function rememberData($entry_id) {
			$field_id = $this->get('id');

			return Symphony::Database()->fetchRow(0, "
				SELECT
					f.username, f.password, f.strength, f.length
				FROM
					`tbl_entries_data_{$field_id}` AS f
				WHERE
					f.entry_id = '{$entry_id}'
				LIMIT 1
			");
		}

		public function fetchMemberFromID($member_id){
			return self::$_driver->Member->initialiseMemberObject($member_id);
		}

		public function fetchMemberFromUsername($username){
			$member_id = Symphony::Database()->fetchVar('entry_id', 0,
				"SELECT `entry_id` FROM `tbl_entries_data_".$this->get('id')."` WHERE `username` = '{$username}' LIMIT 1"
			);
			return ($member_id ? $this->fetchMemberFromID($member_id) : NULL);
		}

		public function getExampleFormMarkup(){

			$div = new XMLElement('div', NULL, array('class' => 'group'));
			$label = Widget::Label('Username');
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][username]'));
			$div->appendChild($label);

			$label = Widget::Label('Password');
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][password]', NULL, 'password'));
			$div->appendChild($label);

			return $div;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$order = $this->get('sortorder');

		// Validator ----------------------------------------------------------

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$label = Widget::Label('Minimum Length');
			$label->appendChild(Widget::Input(
				"fields[{$order}][length]", $this->get('length')
			));

			$group->appendChild($label);

		// Strength -----------------------------------------------------------

			$values = $this->_strengths;

			foreach ($values as &$value) {
				$value[1] = $value[0] == $this->get('strength');
			}

			$label = Widget::Label('Minimum Strength');
			$label->appendChild(Widget::Select(
				"fields[{$order}][strength]", $values
			));

			$group->appendChild($label);
			$wrapper->appendChild($group);

		// Salt ---------------------------------------------------------------

			$label = Widget::Label('Password Salt');
			$input = Widget::Input(
				"fields[{$order}][salt]", $this->get('salt')
			);

			if ($this->get('salt')) {
				$input->setAttribute('disabled', 'disabled');
			}

			$label->appendChild($input);

			if (isset($errors['salt'])) {
				$label = Widget::wrapFormElementWithError($label, $errors['salt']);
			}

			$wrapper->appendChild($label);

			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		public function checkFields(&$errors, $checkForDuplicates = true) {
			parent::checkFields($errors, $checkForDuplicates);

			$this->rememberSalt();

			if (trim($this->get('salt')) == '') {
				$errors['salt'] = 'This is a required field.';
			}
		}

		public function commit(){
			$id = $this->get('id');

			if(!parent::commit() or $id === false) return false;

			$this->rememberSalt();

			$fields = array(
				'field_id' => $id,
				'length' => $this->get('length'),
				'strength' => $this->get('strength'),
				'salt' => $this->get('salt')
			);

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());

		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data=NULL, $error =NULL, $prefix =NULL, $postfix =NULL, $entry_id = null){
			$required = ($this->get('required') == 'yes');
			$field_id = $this->get('id');
			$handle = $this->get('element_name');

			$container = new XMLElement('div');
			$container->setAttribute('class', 'container');

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

		//	Username
			$label = Widget::Label(__('Username'));
			if(!$required) $label->appendChild(new XMLElement('i', __('Optional')));

			$label->appendChild(Widget::Input(
				"fields{$prefix}[{$handle}][username]{$postfix}", $data['username']
			));

			$container->appendChild($label);

		//	Password
			$password = $data['password'];
			$password_set = Symphony::Database()->fetchVar('id', 0, sprintf("
					SELECT
						f.id
					FROM
						`tbl_entries_data_%d` AS f
					WHERE
						f.entry_id = %d
					LIMIT 1
				", $field_id, $entry_id
			));

			if(!is_null($password_set)) {
				$this->displayPublishPassword(
					$group, 'New Password', "{$prefix}[{$handle}][password]{$postfix}"
				);
				$this->displayPublishPassword(
					$group, 'Confirm New Password', "{$prefix}[{$handle}][confirm]{$postfix}"
				);

				$group->appendChild(Widget::Input(
					"fields{$prefix}[{$handle}][optional]{$postfix}", 'yes', 'hidden'
				));

				$container->appendChild($group);

				$help = new XMLElement('p');
				$help->setAttribute('class', 'help');
				$help->setValue(__('Leave new password field blank to keep the current password'));

				$container->appendChild($help);
			}
			else {
				$this->displayPublishPassword(
					$group, 'Password', "{$prefix}[{$handle}][password]{$postfix}"
				);
				$this->displayPublishPassword(
					$group, 'Confirm Password', "{$prefix}[{$handle}][confirm]{$postfix}"
				);

				$container->appendChild($group);
			}

		//	Error?
			if(!is_null($error)) {
				$label = Widget::wrapFormElementWithError($container, $error);
				$wrapper->appendChild($label);
			}
			else {
				$wrapper->appendChild($container);
			}
		}

		public function displayPublishPassword($wrapper, $title, $name) {
			$required = ($this->get('required') == 'yes');

			$label = Widget::Label(__($title));
			if(!$required) $label->appendChild(new XMLElement('i', __('Optional')));

			$input = Widget::Input("fields{$name}", null, 'password', array('autocomplete' => 'off'));

			$label->appendChild($input);
			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$message = null;
			$required = ($this->get('required') == "yes");
			$requires_password = false;

			$username = trim($data['username']);
			$password = trim($data['password']);
			$confirm = trim($data['confirm']);

			//	If the field is required, we should have both a $username and $password.
			if($required && !isset($data['optional']) && (empty($username) || empty($password))) {
				$message = __('Username and Password are required fields.');
				return self::__MISSING_FIELDS__;
			}

			//	Check Username
			if(!empty($username)) {
				if(!General::validateString($username, '/^[\pL\s-_0-9@.]{1,}+$/iu')){
					$message = __('Username contains invalid characters.');
					return self::__INVALID_FIELDS__;
				}

				//	If the optional field is set, then it's an existing entry.
				//	If the username on record doesn't match the one in the Database, then we
				//	want to ensure that the password doesn't remain the same.
				if(isset($data['optional']) && !is_null($entry_id)) {
					$current_username = Symphony::Database()->fetchVar('username', 0, sprintf("
							SELECT
								f.username
							FROM
								`tbl_entries_data_%d` AS f
							WHERE
								f.entry_id = %d
							LIMIT 1
						", $this->get('id'), $entry_id
					));

					if($username !== $current_username) $requires_password = true;
				}

				$existing = $this->fetchMemberFromUsername($username);

				//	If there is an existing username, and it's not the current object (editing), error.
				if($existing instanceof Entry && $existing->get('id') !== $entry_id) {
					$message = __('That username is already taken.');
					return self::__INVALID_FIELDS__;
				}
			}

			//	Check password
			if(!empty($password)) {
				if($confirm !== $password) {
					$message = __('Passwords do not match.');
					return self::__INVALID_FIELDS__;
				}

				if(strlen($password) < (int)$this->get('length')) {
					$message = __('Password is too short. It must be at least %d characters.', array($this->get('length')));
					return self::__INVALID_FIELDS__;
				}

				if (!$this->compareStrength($this->checkPassword($password), $this->get('strength'))) {
					$message = __('Password is not strong enough.');
					return self::__INVALID_FIELDS__;
				}
			}
			else if(!isset($data['optional']) && !empty($username)) {
				$message = __('Password cannot be blank.');
				return self::__MISSING_FIELDS__;
			}
			else if ($requires_password) {
				$message = __('You password must be updated if you wish to change your username.');
				return self::__MISSING_FIELDS__;
			}

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id = null){
			$status = self::__OK__;
			$required = ($this->get('required') == "yes");

			if(empty($data)) return array();

			$username = trim($data['username']);
			$password = trim($data['password']);

			//	We only want to run the processing if the password has been altered
			//	or if the entry hasn't been created yet. If someone attempts to change
			//	their username, but not their password, this will be caught by checkPostFieldData
			if(!empty($password) || is_null($entry_id)) {
				return array(
					'username' 	=> $username,
					'password' 	=> $this->encodePassword($password),
					'strength' 	=> $this->checkPassword($password),
					'length'	=> strlen($password)
				);
			}

			else return $this->rememberData($entry_id);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!isset($data['username']) || !isset($data['password'])) return;
			$wrapper->appendChild(
				new XMLElement(
					$this->get('element_name'),
					NULL,
					array('username' => $data['username'], 'password' => $data['password'])
			));
		}

		public function prepareTableValue($data, XMLElement $link=NULL){
			if(empty($data)) return;

			return parent::prepareTableValue(array('value' => $data['username']), $link);
		}


	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			if(self::isFilterRegex($data[0])):

				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.username REGEXP '$pattern' ";


			elseif($andOperation):

				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.username = '$bit' ";
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.username IN ('".@implode("', '", $data)."') ";

			endif;

			return true;

		}

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC', $useIDFieldForSorting=false){

			$sort_field = (!$useIDFieldForSorting ? 'ed' : 't' . $this->get('id'));

			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `$sort_field` ON (`e`.`id` = `$sort_field`.`entry_id`) ";
			$sort .= (strtolower($order) == 'random' ? 'RAND()' : "`$sort_field`.`username` $order");
		}

		public function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));

				$value = $data['username'];

				if(!isset($groups[$this->get('element_name')][$value])){
					$groups[$this->get('element_name')][$value] = array('attr' => array('value' => $value),
																		 'records' => array(), 'groups' => array());
				}

				$groups[$this->get('element_name')][$value]['records'][] = $r;

			}

			return $groups;
		}
	}

?>