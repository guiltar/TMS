<?php

/**
 * Data writer for code event listeners.
 *
 * @package XenForo_CodeEvents
 */
class TMS_DataWriter_Modification extends XenForo_DataWriter
{
	/**
	 * Option that controls whether a full compile is performed when the modification
	 * is modified.
	 *
	 * @var string
	 */
	const OPTION_FULL_COMPILE = 'fullCompile';

	/**
	 * Option that controls whether a test compile will be performed when setting
	 * the value of a modification.
	 *
	 * @var string
	 */
	const OPTION_TEST_COMPILE = 'testCompile';

	/**
	 * If false, duplicate checking is disabled. An error will occur on dupes. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_CHECK_DUPLICATE = 'checkDuplicate';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'tms_requested_modification_not_found';

	/**
	 * Gets the fields that are defined for the table. See parent for explanation.
	 *
	 * @return array
	 */
	protected function _getFields()
	{
		return array(
			'tms_modification' => array(
				'modification_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'title' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
					'verification' => array('$this', '_verifyPrepareTitle'), 'requiredError' => 'please_enter_valid_title'),
				'template_title' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 75),
				'style_id' => array('type' => self::TYPE_UINT, 'required' => true),

				'execute_order' => array('type' => self::TYPE_UINT, 'default' => 10),
				'description' => array('type' => self::TYPE_STRING, 'default' => ''),

				'modification_type' => array('type' => self::TYPE_STRING, 'default' => 'str_replace', 'allowedValues' => array('str_replace', 'preg_replace', 'callback')),

				'search_value' => array('type' => self::TYPE_STRING, 'default' => '', 'noTrim' => true),
				'replace_value' => array('type' => self::TYPE_STRING, 'default' => '', 'noTrim' => true),

				'callback_class' => array('type' => self::TYPE_STRING, 'maxLength' => 75, 'default' => ''),
				'callback_method' => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => ''),

				'addon_id' => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
				'version_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'version_string' => array('type' => self::TYPE_STRING, 'maxLength' => 30, 'default' => ''),
				'active' => array('type' => self::TYPE_UINT, 'allowedValues' => array(0, 1), 'default' => 1),
			)
		);
	}

	/**
	 * Gets the actual existing data out of data that was passed in. See parent for explanation.
	 *
	 * @param mixed
	 *
	 * @return array|false
	 */
	protected function _getExistingData($data)
	{
		if ($id = $this->_getExistingPrimaryKey($data, 'modification_id')) {
			return array('tms_modification' => $this->_getModificationModel()->getModificationById($id));
		}
		else if (isset($data['title'], $data['style_id'])) {
			$title = $data['title'];
			$styleId = $data['style_id'];
		}
		else if (isset($data[0], $data[1])) {
			$title = $data[0];
			$styleId = $data[1];
		}
		else
		{
			return false;
		}
		return array('tms_modification' => $this->_getModificationModel()->getModificationInStyleByTitle($title, $styleId));
	}

	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'modification_id = ' . $this->_db->quote($this->getExisting('modification_id'));
	}

	protected function _getDefaultOptions()
	{
		$options = array(
			self::OPTION_FULL_COMPILE => (boolean)XenForo_Application::getOptions()->get('tmsFullCompile'),
			self::OPTION_TEST_COMPILE => true,
			self::OPTION_CHECK_DUPLICATE => true
		);

		return $options;
	}

	protected function _verifyPrepareTitle(&$title)
	{
		$title = trim($title);
		if (preg_match('/[^a-zA-Z0-9_ \.]/', $title)) {
			$this->error(new XenForo_Phrase('tms_please_enter_title_using_only_alphanumeric_dot_space'), 'title');
			return false;
		}

		return true;
	}

	protected function _preSave()
	{
		switch ($this->get('modification_type'))
		{
			case 'str_replace':
				if (!$this->get('search_value')) {
					$this->error(new XenForo_Phrase('tms_please_enter_valid_search_string'), 'search_string');
				}
				break;
			case 'preg_replace':
				try
				{
					preg_replace($this->get('search_value'), $this->get('replace_value'), '');
				}
				catch (XenForo_Exception $e)
				{
					$this->error(new XenForo_Phrase('tms_please_enter_valid_search_pattern'), 'search_pattern');
				}
				break;
			case 'callback':
				$class = $this->get('callback_class');
				$method = $this->get('callback_method');

				if (!XenForo_Application::autoload($class) || !method_exists($class, $method)) {
					$this->error(new XenForo_Phrase('please_enter_valid_callback_method'), 'callback_method');
				}
				break;
		}

		if ($this->getOption(self::OPTION_CHECK_DUPLICATE)) {
			if ($this->isInsert() || $this->get('title') != $this->getExisting('title')) {
				$titleConflict = $this->_getModificationModel()->getModificationInStyleByTitle($this->getNew('title'), $this->get('style_id'));
				if ($titleConflict) {
					$this->error(new XenForo_Phrase('tms_modification_titles_must_be_unique'), 'title');
				}
			}
		}
	}

	/**
	 * Post-save handler.
	 */
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_FULL_COMPILE)) {
			XenForo_Template_Compiler::removeTemplateFromCache($this->get('template_title'));
			XenForo_Template_Compiler::removeTemplateFromCache($this->getExisting('template_title'));

			$this->_recompileAssociatedTemplates();

			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		}
		else
		{
			// mark effective templates as compilation needed by setting NULL template modified
			$templateTitles = array($this->get('template_title'), $this->getExisting('template_title'));
			$styleIds = $this->_getStyleModel()->getAllChildStyleIds($this->get('style_id'));
			$styleIds[] = $this->get('style_id');

			$db = $this->_db;
			$db->update(
				'xf_template_map',
				array('template_final' => null, 'template_modifications' => null),
				'style_id IN (' . $db->quote($styleIds) . ') AND title IN (' . $db->quote($templateTitles) . ')'
			);
		}
	}

	/**
	 * Recompiles the changed modification and any modifications that include it.
	 */
	protected function _recompileAssociatedTemplates()
	{
		$templateTitles = array($this->get('template_title'), $this->getExisting('template_title'));
		$templateModel = $this->_getTemplateModel();

		// keyed by template_map_id
		$templates = $templateModel->getNamedTemplatesInStyleTreeWithChildren($templateTitles, $this->get('style_id'));
		$compiledMapIds = $templateModel->compileMappedTemplatesInStyleTree(array_keys($templates));
		$templateModel->compileMappedTemplatesInStyleTree($templateModel->getIncludingTemplateMapIds($compiledMapIds));
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		if ($this->getOption(self::OPTION_FULL_COMPILE)) {
			XenForo_Template_Compiler::removeTemplateFromCache($this->get('template_title'));
			XenForo_Template_Compiler::removeTemplateFromCache($this->getExisting('template_title'));

			$this->_recompileAssociatedTemplates();

			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		}
	}

	/**
	 * Gets the template model object.
	 *
	 * @return XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}

	/**
	 * Gets the modification model object.
	 *
	 * @return TMS_Model_Modification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('TMS_Model_Modification');
	}

	/**
	 * Lazy load the style model object.
	 *
	 * @return  XenForo_Model_Style
	 */
	protected function _getStyleModel()
	{
		return $this->getModelFromCache('XenForo_Model_Style');
	}
}