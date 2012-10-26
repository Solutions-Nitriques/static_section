<?php

	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	Class extension_static_section extends Extension{

		private $_callback;
		private $_static;
		private $_section;
		private $_limit = 1;
		private $_count = 0;

		public function __construct($args){
			$this->_callback = Administration::instance()->getPageCallback();
			$this->_section = $this->getSection();
			$this->_static = $this->isStaticSection();
			$this->_limit = $this->getSectionLimit();
			$this->_count = $this->getNumberOfEntriesInSection();
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'InitaliseAdminPageHead',
					'callback' => 'initaliseAdminPageHead'
				),
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'AddSectionElements',
					'callback' => 'addSectionSettings'
				),
				array(
					'page'		=> '/blueprints/sections/',
					'delegate'	=> 'SectionPreCreate',
					'callback'	=> 'saveSectionSettings'
				),
				array(
					'page'		=> '/blueprints/sections/',
					'delegate'	=> 'SectionPreEdit',
					'callback'	=> 'saveSectionSettings'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'AdminPagePreGenerate',
					'callback'	=> 'adminPagePreGenerate'
				)
			);
		}


	/*-------------------------------------------------------------------------
		Delegates
	-------------------------------------------------------------------------*/

		public function initaliseAdminPageHead($context) {
			$this->redirectRules($context);
			$this->addJavascriptFile();
		}

		public function addSectionSettings($context) {
			// Get current setting
			$setting = array();
			if($context['meta']['static'] == 'yes') {
				$setting = array('checked' => 'checked');
			}
			
			$limit_value = $context['meta']['static_limit'] ? $context['meta']['static_limit'] : '1';
			
			// Prepare setting UI
			$label = new XMLElement('label');
			$checkbox = Widget::Input('meta[static]', 
									NULL, 
									'checkbox', 
									array_merge($setting, array('value' => 'yes'))); 
			
			
			$textbox = Widget::Input('meta[static_limit]', $limit_value, 'text', array('style'=>'width:30px')); 
			
			$br = new XMLElement('br');
			$br->setSelfClosingTag(true);
			
			$label->appendChild($checkbox);
			
			$label->appendChild(new XMLElement('span', ' ' . __('Make this section static (i.e. a single entry section)')));
			$label->appendChild($br);
			$label->appendChild(new XMLElement('span', __('You can set a maximum number of entries too')));
			$label->appendChild($textbox);
			
			// Find context
			$children = $context['form']->getChildren();
			foreach( $children as $element ) {
				// Only work with Fieldset, improves compatibility with other extensions
				if($element->getName() !== 'fieldset') continue;

				$group = $element->getChildren();
				$column = $group[1]->getChildren();

				// Append setting
				$column[0]->appendChild($label);

				break;
			}
		}

		public function saveSectionSettings($context) {
			if(!$context['meta']['static']) {
				$context['meta']['static'] = 'no';
			}
			if (!$context['meta']['static_limit']) {
				$context['meta']['static_limit'] = 1;
			}
		}

		public function adminPagePreGenerate($context){
			if ( $this->_static &&  $this->isLimitReached()) {
				// add a css class in order to get the js do its job
				Administration::instance()->Page->Html->addClass('no-new');
			}
		}
		
	/*-------------------------------------------------------------------------
		Public Helpers
	-------------------------------------------------------------------------*/
		
		/**
		 * 
		 * Method that returns <code>true</code> if we currently are in 
		 * a static section in the Backend and if this section is static; <code>false</code> otherwise
		 * @return boolean
		 */
		public function isStaticSection(){
			if( $this->_section != null && $this->_callback['driver'] == 'publish' && is_array($this->_callback['context']) ) {
				return ($this->_section->get('static') == 'yes');
			}
			return false;
		}
		
		
		/**
		 * 
		 * Method that returns true if the maximum number of entries is reach
		 * @return boolean
		 */
		public function isLimitReached() {
			return $this->_count >= $this->_limit;
		}
		
		
		/**
		 *
		 * Method that returns the actual limit
		 * @return int
		 */
		public gunction getLimit() {
			return $this->_limit;
		}
		
		
	/*-------------------------------------------------------------------------
		Private Helpers
	-------------------------------------------------------------------------*/
		
		public function redirectRules($context){
			if ($this->_static && $this->_limit == 1) {
				// we must redirect than
				$section_handle = $this->_section->get('handle');
				$entry = $this->getLastPosition();
				$params = $this->getConcatenatedParams();
		
				// no entry found... redirect to new
				if ($this->_callback['context']['entry_id'] != $entry || $this->_callback['context']['page'] == 'index'){
					redirect(SYMPHONY_URL . "/publish/{$section_handle}/edit/{$entry}/{$params}");
				}
		
				if (!$entry && $this->_callback['context']['page'] != 'new'){
					redirect(SYMPHONY_URL . "/publish/{$section_handle}/new/{$params}");
				}
		
				// some entries are there redirect to edit page
				if ($this->_callback['context']['entry_id'] != $entry || $this->_callback['context']['page'] == 'index'){
					redirect(SYMPHONY_URL . "/publish/{$section_handle}/edit/{$entry}/{$params}");
				}
			}
		}
		
		private function addJavascriptFile() {
			Administration::instance()->Page->addScriptToHead(
					URL . '/extensions/static_section/assets/publish.static_section.js',
					time(),
					false
			);
		}

		private function hideCreateNewButton($xmlRoot) {
			$children = $xmlRoot->getChildren();
			foreach ($children as $child) {
				var_dump($child->getName() . ' ' . $child->getNumberOfChildren());
				if ($child->getName() == 'a' && $child->getAttribute('class') == 'create button') {
					$child->addClass('invisible');
				} else if ($child->getNumberOfChildren() > 0) {
					$this->hideCreateNewButton($child);
				}
			}
		}
		
		private function getSection(){
			$sm = new SectionManager($this->_Parent);
			$section_id = $sm->fetchIDFromHandle($this->_callback['context']['section_handle']);
			
			$section = $sm->fetch($section_id);
			
			if( is_object($section) && $section instanceof Section ){
				return $section;
			}
			return null;
		}

		private function getLastPosition(){
			$em = new EntryManager($this->_Parent);

			$em->setFetchSortingDirection('DESC');
			$entry = $em->fetch(NULL, $this->_section->get('id'), 1);

			if (is_array($entry) && !empty($entry)){
				$entry = end($entry);
				return $entry->get('id');
			}
		}

		private function getConcatenatedParams(){
			if (count($_GET) > 2) {
				$params = "?";
			}

			foreach($_GET as $key => $value){
				if (in_array($key, array('symphony-page', 'mode'))) continue;

				$params .= "{$key}={$value}";
				if (next($_GET)) {
					$params .= '&';
				}
			}

			return $params;
		}
		
		private function isInSection() {
			return $this->_callback['driver'] == 'publish' && is_array($this->_callback['context']);
		}
		
		private function getSectionLimit() {
			if ($this->isInSection()){
				return $this->_section->get('static_limit');
			}
			return -1; // no section found...
		}
		
		private function getNumberOfEntriesInSection() {
			if ($this->isInSection()){
			
				$em = new EntryManager($this->_Parent);
				
				return $em->fetchCount($this->_section->get('id'));
			}
			
			return -1; // no entries
		}
		
	/*-------------------------------------------------------------------------
		Installation
	-------------------------------------------------------------------------*/

		public function install(){
			return $this->install_Pre1_7_0() && $this->install_1_7_0();
		}
		
		private function install_Pre1_7_0() {
			return Symphony::Database()->query("
				ALTER TABLE `tbl_sections` 
					ADD `static` enum('yes','no') NOT NULL DEFAULT 'no' AFTER `hidden`
			");
		}
		
		private function install_1_7_0() {
			return Symphony::Database()->query("
					ALTER TABLE `tbl_sections` 
						ADD `static_limit` int(11) NOT NULL DEFAULT 1 AFTER `static`
				");
		}
		
		public function update($previousVersion) {
			if (version_compare($previousVersion, '1.7.0', '<')) {
				// install new column that appear in the 1.7.0 version
				return $this->install_1_7_0();
			}	
			return true;
		}

		public function uninstall(){
			return Symphony::Database()->query("
				ALTER TABLE `tbl_sections` 
					DROP `static`, 
					DROP `static_limit`
			");
		}

	}
