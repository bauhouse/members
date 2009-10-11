<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');
	
	Class contentExtensionMembersNew extends AdministrationPage{

		private $_driver;

		function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle('Symphony &ndash; Member Roles &ndash; Untitled');
			
			$ExtensionManager = new ExtensionManager($parent);
			$this->_driver = $ExtensionManager->create('members');
			
		}
		
		function action(){
			
			if(isset($_POST['action']['save'])){

				$fields = $_POST['fields'];

				$permissions = $fields['permissions'];
				$name = trim($fields['name']);
				$page_access = $fields['page_access'];
				
				if(strlen($name) == 0){
					$this->_errors['name'] = 'This is a required field';
					return;
				}
				
				elseif($this->_driver->roleExists($name)){
					$this->_errors['name'] = 'A role with the name <code>' . $name . '</code> already exists.';
					return;
				}
								

				$sql = "INSERT INTO `tbl_members_roles` VALUES (NULL, 
												'$name', 
												".(strlen(trim($fields['email_subject'])) > 0 ? "'".addslashes($fields['email_subject'])."'" : 'NULL').", 
												".(strlen(trim($fields['email_body'])) > 0 ? "'".addslashes($fields['email_body'])."'" : 'NULL').")";
							
				$this->_Parent->Database->query($sql);
				
				$role_id = $this->_Parent->Database->getInsertID();
				
				if(is_array($page_access) && !empty($page_access)){
					foreach($page_access as $page_id) 
						$this->_Parent->Database->query("INSERT INTO `tbl_members_roles_forbidden_pages` VALUES (NULL, $role_id, $page_id, 'yes')");

				}
				
				if(is_array($permissions) && !empty($permissions)){
					
					$sql = "INSERT INTO `tbl_members_roles_event_permissions` VALUES ";
					
					foreach($permissions as $event_handle => $p){
						foreach($p as $action => $allow)
							$sql .= "(NULL,  $role_id, '$event_handle', '$action', '$allow'),";
					}
					
					$this->_Parent->Database->query(trim($sql, ','));
				}
				
				redirect(extension_members::baseURL() . 'edit/' . $role_id . '/created/');
			}

		}
		
		function view(){
			
			$this->_Parent->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 70);

			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));
			
			if($formHasErrors) $this->pageAlert('An error occurred while processing this form. <a href="#error">See below for details.</a>', AdministrationPage::PAGE_ALERT_ERROR);
			
			$this->setPageType('form');	

			$this->appendSubheading('Untitled');
		
			$fields = array();
			
			if(isset($_POST['fields'])){
				$fields = $_POST['fields'];
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
			# Delegate: MemberRolePermissionFieldsetsNew
			# Description: Add custom fieldsets to the role page
			$ExtensionManager = new ExtensionManager($this->_Parent);
			$ExtensionManager->notifyMembers('MemberRolePermissionFieldsetsNew', '/extension/members/new/', array('form' => &$this->Form, 'permissions' => $fields['permissions']));
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
			$div->appendChild(Widget::Input('action[save]', 'Create', 'submit', array('accesskey' => 's')));
	
			$this->Form->appendChild($div);			

		}
	}
	
