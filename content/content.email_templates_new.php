<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');

	Class contentExtensionMembersEmail_Templates_New extends AdministrationPage{

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle(__('Symphony &ndash; Email Templates &ndash; Untitled'));
		}

		public function action(){

			if(isset($_POST['action']['save'])){

				$fields = $_POST['fields'];

				$et = new EmailTemplate;
				$et->subject = $fields['subject'];
				$et->type = $fields['type'];
				$et->body = $fields['body'];

				if(isset($fields['roles']) && strlen(trim($fields['roles'])) > 0){
					$roles = preg_split('/\s*,\s*/i', $fields['roles'], -1, PREG_SPLIT_NO_EMPTY);
					foreach($roles as $r){
						$et->addRole(
							Role::getRoleIDFromName($r)
						);
					}
				}

				EmailTemplate::save($et);

				redirect(extension_members::baseURL() . 'email_templates_edit/' . $et->id . '/created/');
			}

		}

		public function view(){

			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 9125341);

			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));

			if($formHasErrors) $this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), AdministrationPage::PAGE_ALERT_ERROR);

			$this->setPageType('form');

			$this->appendSubheading(__('Untitled'));

			$fields = array();

			if(isset($_POST['fields'])){
				$fields = $_POST['fields'];
			}

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'primary');

			$label = Widget::Label(__('Subject'));
			$label->appendChild(Widget::Input('fields[subject]', General::sanitize($fields['subject'])));

			if(isset($this->_errors['subject'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['subject']));
			else $fieldset->appendChild($label);

			$label = Widget::Label(__('Body'));
			$label->appendChild(Widget::Textarea('fields[body]', 15, 75, General::sanitize($fields['body'])));

			if(isset($this->_errors['body'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['body']));
			else $fieldset->appendChild($label);

			$fieldset->appendChild(new XMLElement('p', __('Dynamic fields and parameters can be included in the subject or body of the email using the <code>{$param}</code> syntax. Please see the <a
href="http://github.com/symphony/members/blob/master/README.markdown">readme</a> for a complete list of available parameters.'), array('class' => 'help')));

			$this->Form->appendChild($fieldset);

			$sidebar = new XMLElement('fieldset');
			$sidebar->setAttribute('class', 'secondary');

			$label = Widget::Label(__('Type'));
			$options = array(
				array(NULL, false, NULL),
				array('reset-password', $fields['type'] == 'reset-password', __('Reset Password')),
				array('new-password', $fields['type'] == 'new-password', __('New Password')),
				array('activate-account', $fields['type'] == 'activate-account', __('Activate Account')),
				array('welcome', $fields['type'] == 'welcome', __('Welcome Email')),
			);
			$label->appendChild(Widget::Select('fields[type]', $options));

			if(isset($this->_errors['type'])) $sidebar->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['type']));
			else $sidebar->appendChild($label);


			$label = Widget::Label(__('Roles'));

			$label->appendChild(Widget::Input('fields[roles]', $fields['roles']));

			if(isset($this->_errors['roles'])) $sidebar->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['roles']));
			else $sidebar->appendChild($label);

			$roles = DatabaseUtilities::resultColumn(ASDCLoader::instance()->query(
				"SELECT `name` FROM `tbl_members_roles` ORDER BY `name` ASC"
			), 'name');

			if(is_array($roles) && !empty($roles)){
				$taglist = new XMLElement('ul');
				$taglist->setAttribute('class', 'tags');

				foreach($roles as $tag) $taglist->appendChild(new XMLElement('li', $tag));

				$sidebar->appendChild($taglist);
			}


			$this->Form->appendChild($sidebar);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Create'), 'submit', array('accesskey' => 's')));

			$this->Form->appendChild($div);

		}
	}

