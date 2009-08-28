<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	
	Class contentExtensionMembersPreferences extends AdministrationPage{

		private $_driver;

		function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle('Symphony &ndash; Member Roles &ndash; Preferences');
			$this->setPageType('form');
			
			$this->_driver = $parent->ExtensionManager->create('members');
			
		}

		function view(){
			
			$this->_Parent->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 70);

			$this->appendSubheading('Preferences');

		    $bIsWritable = true;
			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));

		    if(!is_writable(CONFIG)){
		        $this->pageAlert('The Symphony configuration file, <code>/manifest/config.php</code>, is not writable. You will not be able to save changes to preferences.', AdministrationPage::PAGE_ALERT_ERROR);
		        $bIsWritable = false;
		    }

			elseif($formHasErrors) $this->pageAlert('An error occurred while processing this form. <a href="#error">See below for details.</a>', AdministrationPage::PAGE_ALERT_ERROR);


			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			
			$group->appendChild(new XMLElement('legend', 'Essentials'));

			$p = new XMLElement('p', 'Must contain a <code>Member</code> type field. Will be used to validate login details.');
			$p->setAttribute('class', 'help');
			$group->appendChild($p);
		
			$div = new XMLElement('div', NULL, array('class' => 'group'));

			$section_list = $this->_Parent->Database->fetchCol('parent_section', "SELECT `parent_section` FROM `tbl_fields` WHERE `type` = 'member'");
			$sectionManager = new SectionManager($this->_Parent);
			
			$label = Widget::Label('Member Section');
					
			$options = array();

			foreach($section_list as $section_id){

				$section = $sectionManager->fetch($section_id);

				$options[] = array($section_id, (extension_members::memberSectionID('member_section', 'members') == $section_id), $section->get('name'));
			}
			
			$label->appendChild(Widget::Select('fields[member_section]', $options));
			$div->appendChild($label);
			
			
			$label = Widget::Label('Email Address');
						
			$member_section_id = extension_members::memberSectionID();
			
			if(!empty($member_section_id)){
				
				$options = array(array('', false, ''));
				
				$sectionManager = new SectionManager($this->_Parent);
			    $section = $sectionManager->fetch($member_section_id);
			
				foreach($section->fetchFields() as $f){
					$options[] = array($f->get('id'), (Symphony::Configuration()->get('email_address_field_id', 'members') == $f->get('id')), $f->get('label'));
				}
			}
			
			else $options = array(array('', false, 'Must set Member section first'));
			
			$label->appendChild(Widget::Select('fields[email_address_field_id]', $options, (empty($member_section_id) ? array('disabled' => 'disabled') : NULL)));
			$div->appendChild($label);			
			
			$group->appendChild($div);
			
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));

			$label = Widget::Label('Timezone Offset Field');
						
			$member_section_id = extension_members::memberSectionID();
			
			if(!empty($member_section_id)){
				
				$options = array(array('', false, ''));
				
				$sectionManager = new SectionManager($this->_Parent);
			    $section = $sectionManager->fetch($member_section_id);
			
				foreach($section->fetchFields() as $f){
					$options[] = array($f->get('id'), (Symphony::Configuration()->get('timezone_offset_field_id', 'members') == $f->get('id')), $f->get('label'));
				}
			}
			
			else $options = array(array('', false, 'Must set Member section first'));
			
			$label->appendChild(Widget::Select('fields[timezone_offset_field_id]', $options, (empty($member_section_id) ? array('disabled' => 'disabled') : NULL)));
			$div->appendChild($label);			
						
			$group->appendChild($div);			
			
			$group->appendChild(new XMLElement('p', 'Used to dynamically set the timezone for displaying dates. Defaults to the Symphony configuration.', array('class' => 'help')));
						
			$this->Form->appendChild($group);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', 'Forgotten Password Email Template'));
			
			$fieldset->appendChild(new XMLElement('p', 'When a member triggers the forgotten password event, this is the email template used when providing them a login link.', array('class' => 'help')));
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			$label = Widget::Label('Subject');
			$label->appendChild(Widget::Input('fields[forgotten_pass_email_subject]', General::sanitize(stripslashes(Symphony::Configuration()->get('forgotten_pass_email_subject', 'members')))));

			if(isset($this->_errors['forgotten_pass_email_subject'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['forgotten_pass_email_subject']));
			else $fieldset->appendChild($label);			
			
			
			$label = Widget::Label('Body');
			$label->appendChild(Widget::Textarea('fields[forgotten_pass_email_body]', '25', '50', General::sanitize(stripslashes(Symphony::Configuration()->get('forgotten_pass_email_body', 'members')))));
			$fieldset->appendChild((isset($this->_errors['forgotten_pass_email_body']) ? $this->wrapFormElementWithError($label, $this->_errors['forgotten_pass_email_body']) : $label));					

			$fieldset->appendChild(new XMLElement('p', 'You can add dynamic elements to the email by using <code>{$field-name}</code> syntax, where <code>field-name</code> corresponds to the fields of the member, and <code>{$member-token}</code> which is the unique token allowing them to login E.G. <code>'.URL.'/login/{$member-token}/</code>. The page must have a param called <code>member-token</code>.', array('class' => 'help')));

			$this->Form->appendChild($fieldset);			


			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');

			$attr = array('accesskey' => 's');
			if(!$bIsWritable) $attr['disabled'] = 'disabled';
			$div->appendChild(Widget::Input('action[save]', 'Save Changes', 'submit', $attr));

			$this->Form->appendChild($div);	

		}

		function action(){

			##Do not proceed if the config file is read only
		    if(!is_writable(CONFIG)) redirect($this->_Parent->getCurrentPageURL());

			if(isset($_POST['action']['save'])){

				$settings = array_map('addslashes', $_POST['fields']);
				
				foreach($settings as $key => $value) Symphony::Configuration()->set($key, $value, 'members');

				$this->_Parent->saveConfig();

				redirect($this->_Parent->getCurrentPageURL());

			}
		}	
	}

