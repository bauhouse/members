<?php
	
	Class fieldMemberLink extends Field{
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Member Link';
			$this->_required = true;
			$this->set('required', 'yes');
		}

		function isSortable(){
			return true;
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

		function groupRecords($records){
			
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

		function prepareTableValue($data, XMLElement $link=NULL){
			return parent::prepareTableValue(array('value' => $data['username']), $link);
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$username = $data['username'];		
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', 'Optional'));
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, (strlen($username) != 0 ? $username : NULL)));

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}

		/*public function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$wrapper->appendChild(new XMLElement('h4', $this->get('label') . ' <i>'.$this->Name().'</i>'));
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));	
			$wrapper->appendChild($label);
			
		}*/
		
		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			$message = NULL;

			if($this->get('required') == 'yes' && strlen($data) == 0){
				$message = "This is a required field.";
				return self::__MISSING_FIELDS__;
			}
			
			if(!is_numeric($data) && !$this->fetchMemberFromUsername($data)){
				$message = "Invalid Member username supplied";
				return self::__INVALID_FIELDS__;				
			}

			if(is_numeric($data) && !$this->fetchMemberFromID((int)$data)){
				$message = "Invalid Member id supplied";
				return self::__INVALID_FIELDS__;				
			}

			return self::__OK__;		
		}
		
		function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!isset($data['username']) || !isset($data['member_id'])) return;
			$wrapper->appendChild(new XMLElement($this->get('element_name'), $data['username'], array('id' => $data['member_id'])));
		}

		public function fetchMemberFromID($member_id){
			$ExtensionManager = new ExtensionManager($this->_Parent);
			$driver =& $ExtensionManager->create('members');
			return $driver->initialiseMemberObject($member_id);					
		}

		public function fetchMemberFromUsername($username){
			$ExtensionManager = new ExtensionManager($this->_Parent);
			$driver =& $ExtensionManager->create('members');
			$member_id = $this->Database->fetchVar('entry_id', 0, "SELECT `entry_id` FROM `tbl_entries_data_".$driver->usernameAndPasswordField()."` WHERE `username` = '".$username."' LIMIT 1");
			
			return ($member_id ? $this->fetchMemberFromID($member_id) : NULL);
		}		

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			
			$status = self::__OK__;

			if(is_numeric($data) && $Member = $this->fetchMemberFromID($data)){
				$driver =& $this->_engine->ExtensionManager->create('members');
				$username = $Member->getData($driver->usernameAndPasswordField());
				$username = $username['username'];
				$member_id = $data;
			}
			
			elseif(!is_numeric($data) && $Member = $this->fetchMemberFromUsername($data)){
				$member_id = $Member->get('id');
				$username = $data;
			}
			
			if(strlen($username) == 0 && !is_numeric($data)) $username = $data;
			elseif(strlen($member_id) == 0 && is_numeric($data)) $member_id = $data;	
			
			return array(
				'member_id' => $member_id,
				'username' => $username,
			);
		}

		function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			
			$this->_engine->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");		
			return $this->_engine->Database->insert($fields, 'tbl_fields_' . $this->handle());
					
		}
		
		public function createTable(){
			
			return $this->Database->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `member_id` int(11) default NULL,
				  `username` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `member_id` (`member_id`),
				  KEY `username` (`username`)
				) TYPE=MyISAM;"
			
			);
		}		

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			
			$field_id = $this->get('id');
			
			if(self::isFilterRegex($data[0])):
				
				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.username REGEXP '$pattern' ";

			
			elseif($andOperation):
			
				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND (`t$field_id$key`.username = '$bit' OR `t$field_id$key`.member_id = '$bit') ";
				}
							
			else:
			
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (`t$field_id`.username IN ('".@implode("', '", $data)."') OR `t$field_id`.member_id IN ('".@implode("', '", $data)."')) ";
						
			endif;
			
			return true;
			
		}
				
	}

?>