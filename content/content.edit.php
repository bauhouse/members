<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
	
	Class contentExtensionMembersEdit extends AdministrationPage{

		private $_driver;

		function __construct(&$parent){
			parent::__construct($parent);

			$ExtensionManager = new ExtensionManager($parent);
			$this->_driver = $ExtensionManager->create('members');

		}

		function action(){

			if(!$role_id = $this->_context[0]) redirect(extension_members::baseURL());
					
			if(!$existing = $this->_driver->fetchRole($role_id, true))
				$this->_Parent->customError(E_USER_ERROR, 'Role not found', 'The role you requested to edit does not exist.', false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));


			if(isset($_POST['action']['delete']) && $role_id != 1):
				
				if(!$replacement = $this->_driver->fetchRole($_POST['fields']['replacement_role'])) 
					$this->_Parent->customError(E_USER_ERROR, 'Role not found', 'The replacement role does not exist.', false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));;
				
				$sql = "UPDATE `tbl_entries_data_".$this->_driver->roleField()."` SET `role_id` = " . (int)$_POST['fields']['replacement_role'] . " WHERE `role_id` = $role_id";
				$this->_Parent->Database->query($sql);
				
				$this->_Parent->Database->query("DELETE FROM `tbl_members_roles` WHERE `id` = $role_id");
				$this->_Parent->Database->query("DELETE FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = $role_id");
				$this->_Parent->Database->query("DELETE FROM `tbl_members_roles_event_permissions` WHERE `role_id` = $role_id");
				
				redirect(extension_members::baseURL() . 'roles/');
				
			elseif(isset($_POST['action']['move'])):

				if(!$replacement = $this->_driver->fetchRole($_POST['fields']['new_role'])) 
					$this->_Parent->customError(E_USER_ERROR, 'Role not found', 'The replacement role does not exist.', false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));;

				$sql = "UPDATE `tbl_entries_data_".$this->_driver->roleField()."` SET `role_id` = " . (int)$_POST['fields']['new_role'] . " WHERE `role_id` = $role_id";
				$this->_Parent->Database->query($sql);

				redirect(extension_members::baseURL() . 'edit/' . $role_id . '/moved/');				
			
			elseif(isset($_POST['action']['save'])):

				$fields = $_POST['fields'];

				$permissions = $fields['permissions'];
				$name = trim($fields['name']);
				$page_access = $fields['page_access'];

				if(strlen($name) == 0){
					$this->_errors['name'] = 'This is a required field';
					return;
				}

				elseif(strtolower($existing->name()) != strtolower($name) && $this->_driver->roleExists($name)){
					$this->_errors['name'] = 'A role with the name <code>' . $name . '</code> already exists.';
					return;
				}

				$sql = "UPDATE `tbl_members_roles` SET 
							`name` = '".addslashes($name)."',
							`email_subject` = ".(strlen(trim($fields['email_subject'])) > 0 ? "'".addslashes($fields['email_subject'])."'" : 'NULL').",
							`email_body` = ".(strlen(trim($fields['email_body'])) > 0 ? "'".addslashes($fields['email_body'])."'" : 'NULL')."
						WHERE `id` = ".$existing->id()." LIMIT 1";
							
				$this->_Parent->Database->query($sql);
				
				$sql = "DELETE FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = ".$existing->id();
				$this->_Parent->Database->query($sql);
				
				if(is_array($page_access) && !empty($page_access)){
					foreach($page_access as $page_id) 
						$this->_Parent->Database->query("INSERT INTO `tbl_members_roles_forbidden_pages` VALUES (NULL, ".$existing->id().", $page_id)");

				}

				$sql = "DELETE FROM `tbl_members_roles_event_permissions` WHERE `role_id` = ".$existing->id();
				$this->_Parent->Database->query($sql);

				if(is_array($permissions) && !empty($permissions)){
					
					$sql = "INSERT INTO `tbl_members_roles_event_permissions` VALUES ";

					foreach($permissions as $event_handle => $p){
						foreach($p as $action => $allow)
							$sql .= "(NULL,  $role_id, '$event_handle', '$action', '$allow'),";
					}

					$this->_Parent->Database->query(trim($sql, ','));
				}

				redirect(extension_members::baseURL() . 'edit/' . $role_id . '/saved/');
				
			endif;

		}

		function view(){

			if(!$role_id = $this->_context[0]) redirect(extension_members::baseURL());
					
			if(!$existing = $this->_driver->fetchRole($role_id, true))
				$this->_Parent->customError(E_USER_ERROR, 'Role not found', 'The role you requested to edit does not exist.', false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));
			
			
			if(isset($this->_context[1])){
				switch($this->_context[1]){
					
					case 'saved':
						$this->pageAlert('{1} updated successfully. <a href="{2}">Create another?</a>', AdministrationPage::PAGE_ALERT_NOTICE, array('Role', extension_members::baseURL()));
						break;
						
					case 'created':
						$this->pageAlert('{1} created successfully. <a href="{2}">Create another?</a>', AdministrationPage::PAGE_ALERT_NOTICE, array('Role', extension_members::baseURL()));
						break;

					case 'moved':
						$this->pageAlert('All members have been successfully moved to new role.');
						break;
					
				}
			}
			
			$this->_Parent->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 70);

			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));

			if($formHasErrors) $this->pageAlert('An error occurred while processing this form. <a href="#error">See below for details.</a>', AdministrationPage::PAGE_ALERT_ERROR);

			$this->setPageType('form');	
			
			$this->setTitle('Symphony &ndash; Member Roles &ndash; ' . $existing->name());
			$this->appendSubheading($existing->name());

			$fields = array();

			if(isset($_POST['fields'])){
				$fields = $_POST['fields'];
			}
			
			else{
				
				$fields['name'] = $existing->name();
				$fields['permissions'] = $existing->eventPermissions();
				$fields['page_access'] = $existing->forbiddenPages();
				
				$fields['email_subject'] = $existing->email_subject();
				$fields['email_body'] = $existing->email_body();
				
			}
			

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', 'Essentials'));


			$label = Widget::Label('Name');
			$label->appendChild(Widget::Input('fields[name]', General::sanitize($fields['name'])));

			if(isset($this->_errors['name'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['name']));
			else $fieldset->appendChild($label);

			$this->Form->appendChild($fieldset);				

			$EventManager = new EventManager($this->_Parent);
			$events = $EventManager->listAll();

			if(is_array($events) && !empty($events)){
				foreach($events as $handle => $e){
					
					$show_in_role_permissions = 
						(method_exists("event{$handle}", 'showInRolePermissions') && call_user_func(array("event{$handle}", 'showInRolePermissions')) === true 
							? true 
							: false
						);
					
					if(!$e['can_parse'] && !$show_in_role_permissions) unset($events[$handle]);
				}
			}

			if(is_array($events) && !empty($events)){

				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings type-file');
				$fieldset->appendChild(new XMLElement('legend', 'Event Level Permissions'));	
				
				$aTableHead = array(
					array('Event', 'col'),
					array('Add', 'col'),
					array('Edit', 'col'),
					array('Edit Own *', 'col'),					
					array('Delete', 'col'),
					array('Delete Own *', 'col'),					
				);	

				$aTableBody = array();

				foreach($events as $event_handle => $event){
				
					$permissions = $fields['permissions'][$event_handle];
				
					## Setup each cell
					$td1 = Widget::TableData($event['name']);
					
					$td2 = Widget::TableData(Widget::Input('fields[permissions][' . $event_handle .'][add]', 'yes', 'checkbox', (isset($permissions['add']) ? array('checked' => 'checked') : NULL)));

					$td3 = Widget::TableData(Widget::Input('fields[permissions][' . $event_handle .'][edit]', 'yes', 'checkbox', (isset($permissions['edit']) ? array('checked' => 'checked') : NULL)));
					$td4 = Widget::TableData(Widget::Input('fields[permissions][' . $event_handle .'][edit_own]', 'yes', 'checkbox', (isset($permissions['edit_own']) ? array('checked' => 'checked') : NULL)));
							
					$td5 = Widget::TableData(Widget::Input('fields[permissions][' . $event_handle .'][delete]', 'yes', 'checkbox', (isset($permissions['delete']) ? array('checked' => 'checked') : NULL)));
					$td6 = Widget::TableData(Widget::Input('fields[permissions][' . $event_handle .'][delete_own]', 'yes', 'checkbox', (isset($permissions['delete_own']) ? array('checked' => 'checked') : NULL)));
					
					## Add a row to the body array, assigning each cell to the row
					$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3, $td4, $td5, $td6));

				}
			

				$table = Widget::Table(
									Widget::TableHead($aTableHead), 
									NULL, 
									Widget::TableBody($aTableBody),
									'role-permissions'
							);
					
										
				$fieldset->appendChild($table);		
				
				$fieldset->appendChild(new XMLElement('p', '* <em>Does not apply if global edit/delete is allowed</em>', array('class' => 'help')));		
				$this->Form->appendChild($fieldset);
			}


			####
			# Delegate: MemberRolePermissionFieldsetsEdit
			# Description: Add custom fieldsets to the role page
			$ExtensionManager = new ExtensionManager($this->_Parent);
			$ExtensionManager->notifyMembers('MemberRolePermissionFieldsetsEdit', '/extension/members/edit/', array('form' => &$this->Form, 'permissions' => $fields['permissions']));
			#####


			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', 'Page Level Permissions'));

			$pages = $this->_Parent->Database->fetch("SELECT * FROM `tbl_pages` " . ($this->_context[0] == 'edit' ? "WHERE `id` != '$page_id' " : '') . "ORDER BY `title` ASC");

			$label = Widget::Label('Deny Access');

			$options = array();
			if(is_array($pages) && !empty($pages)){
				foreach($pages as $page){
					$options[] = array($page['id'], in_array($page['id'], $fields['page_access']), '/' . $this->_Parent->resolvePagePath($page['id'])); //$page['title']);
				}
			}

			$label->appendChild(Widget::Select('fields[page_access][]', $options, array('multiple' => 'multiple')));		
			$fieldset->appendChild($label);				
			$this->Form->appendChild($fieldset);
		
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', 'Operations'));	

			if($role_id == 1) $fieldset->appendChild(new XMLElement('p', 'The default role cannot be deleted', array('class' => 'help')));

			$aTableBody = array();

			$roles = $this->_driver->fetchRoles();
			$options = array();
			
			foreach($roles as $role){
				if($role_id == $role->id()) continue;
				$options[] = array($role->id(), false, $role->name());
			}
						
			## Setup each cell
			$td1 = Widget::TableData('Move');
			$td2 = Widget::TableData(Widget::Select('fields[new_role]', $options));
			$td3 = Widget::TableData(Widget::Input('action[move]', 'Move', 'submit', array('class' => 'confirm')));

			## Add a row to the body array, assigning each cell to the row
			$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3));
			
			if($role_id != 1):
				## Setup each cell
				$td1 = Widget::TableData('Move and Delete');
				$td2 = Widget::TableData(Widget::Select('fields[replacement_role]', $options));
				$td3 = Widget::TableData(Widget::Input('action[delete]', 'Delete', 'submit', array('class' => 'confirm')));

				## Add a row to the body array, assigning each cell to the row
				$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3));		

			endif;
			
			$table = Widget::Table(
								NULL, 
								NULL, 
								Widget::TableBody($aTableBody),
								NULL, 'role-operations'
						);

			$table->setAttributeArray(array('cellspacing' => '0', 'cellpadding' => '0'));

			$fieldset->appendChild($table);
		
			$this->Form->appendChild($fieldset);			


			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', 'Email Template'));
			
			$fieldset->appendChild(new XMLElement('p', 'When adding a member, they will receive an email based on the template you specify. <br /><br />Leave everything blank if you do not wish for new members in this group to receive an email.', array('class' => 'help')));
			
			
			$label = Widget::Label('Subject');
			$label->appendChild(Widget::Input('fields[email_subject]', General::sanitize($fields['email_subject'])));

			if(isset($this->_errors['email_subject'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['email_subject']));
			else $fieldset->appendChild($label);			
	
			
			$label = Widget::Label('Body');
			$label->appendChild(Widget::Textarea('fields[email_body]', '25', '50', General::sanitize($fields['email_body'])));
			$fieldset->appendChild((isset($this->_errors['email_body']) ? $this->wrapFormElementWithError($label, $this->_errors['email_body']) : $label));					

			$fieldset->appendChild(new XMLElement('p', 'You can add dynamic elements to the email by using <code>{$field-name}</code> syntax, where <code>field-name</code> corresponds to the fields of the new member.', array('class' => 'help')));

			$this->Form->appendChild($fieldset);
			
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', 'Save Changes', 'submit', array('accesskey' => 's')));
			$this->Form->appendChild($div);			

		}
	}

?>