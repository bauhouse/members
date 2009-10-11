<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	Class contentExtensionMembersRoles extends AdministrationPage{
		
		private $_driver;

		function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle('Symphony &ndash; Member Roles');
			
			$ExtensionManager = new ExtensionManager($parent);
			$this->_driver = $ExtensionManager->create('members');
			
		}
		
		private function __deleteMembers($role_id){
			$sql = "SELECT `entry_id` FROM `tbl_entries_data_".$this->_driver->roleField()."` WHERE `role_id` = $role_id";
			$members = Administration::Database()->fetchCol('entry_id', $sql);
			
			###
			# Delegate: Delete
			# Description: Prior to deletion of entries. Array of Entries is provided.
			#              The array can be manipulated
			Administration::instance()->ExtensionManager->notifyMembers('Delete', '/publish/', array('entry_id' => &$checked));

			$entryManager = new EntryManager($this->_Parent);
			$entryManager->delete($members);
			
		}
		
		public function action() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete-members':
						foreach($checked as $role_id){
							$this->__deleteMembers($role_id);
						}
						break; 
				}
			}
		}	
	
		function view(){

			$this->_Parent->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 70);
			
			$create_button = Widget::Anchor('Create a New Role', extension_members::baseURL() . 'new/', 'Create a new role', 'create button');

			$this->setPageType('table');
			$this->appendSubheading('Member Roles ' . $create_button->generate(false));


			$aTableHead = array(

				array('Name', 'col'),
				array('Members', 'col'),		

			);	
		
			$roles = $this->_driver->fetchRoles();
		
			$aTableBody = array();

			if(!is_array($roles) || empty($roles)){

				$aTableBody = array(
									Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', NULL, count($aTableHead))))
								);
			}

			elseif(!$this->_driver->memberSectionID()){
				$this->pageAlert(__('You must set a section in <a href="%1$s">Member Preferences?</a>', array(extension_members::baseURL() . 'preferences/')), Alert::ERROR);

				$aTableBody = array(
									Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', NULL, count($aTableHead))))
								);
			}

			else{
				
			    $sectionManager = new SectionManager($this->_Parent);
			    $section = $sectionManager->fetch($this->_Parent->Database->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '".$this->_driver->usernameAndPasswordField()."' LIMIT 1"));
				
				$bEven = true;
				
				$role_field_name = $this->_Parent->Database->fetchVar('element_name', 0, "SELECT `element_name` FROM `tbl_fields` WHERE `id` = '".$this->_driver->roleField()."' LIMIT 1");
				
				foreach($roles as $role){
					
					$member_count = $this->_Parent->Database->fetchVar('count', 0, "SELECT COUNT(*) AS `count` FROM `tbl_entries_data_".$this->_driver->roleField()."` WHERE `role_id` = '".$role->id()."'");
					
					## Setup each cell
					$td1 = Widget::TableData(Widget::Anchor($role->name(), extension_members::baseURL() . 'edit/' . $role->id() . '/', NULL, 'content'));
					$td2 = Widget::TableData(Widget::Anchor("$member_count", URL . '/symphony/publish/' . $section->get('handle') . '/?filter='.$role_field_name.':' . $role->id()));
					
					$td2->appendChild(Widget::Input("items[".$role->id()."]", null, 'checkbox'));
					
					## Add a row to the body array, assigning each cell to the row
					$aTableBody[] = Widget::TableRow(array($td1, $td2), ($bEven ? 'odd' : NULL));		
					
					$bEven = !$bEven;
					
				}
			
			}
			
			$table = Widget::Table(
								Widget::TableHead($aTableHead), 
								NULL, 
								Widget::TableBody($aTableBody)
						);
					
			$this->Form->appendChild($table);
			
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				array('delete-members', false, __('Delete Members'))							
			);
			
			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($tableActions);			

		}
	}
	
