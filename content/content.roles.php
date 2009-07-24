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
			

		}
	}
	
