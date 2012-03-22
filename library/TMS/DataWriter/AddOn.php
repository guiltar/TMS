<?php

/**
 * Data writer for add-ons.
 *
 * @package XenForo_AddOns
 */
class TMS_DataWriter_AddOn extends XFCP_TMS_DataWriter_AddOn
{

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		parent::_postSave();

		if ($this->isUpdate() && $this->isChanged('addon_id')) {
			$db = $this->_db;
			$updateClause = 'addon_id = ' . $db->quote($this->getExisting('addon_id'));
			$updateValue = array('addon_id' => $this->get('addon_id'));

			$db->update('tms_modification', $updateValue, $updateClause);
		}

		if ($this->isUpdate() && $this->isChanged('active')) {
			if ($this->get('addon_id') == 'TMS') {
				$mods = $this->_getModificationModel()->getAllModifications();
			}
			else
			{
				$mods = $this->_getModificationModel()->getModificationsByAddOn($this->get('addon_id'));
			}
			$templateTitles = array('');

			foreach ($mods as $mod)
			{
				$templateTitles[] = $mod['template_title'];
			}

			if (XenForo_Application::getOptions()->get('tmsFullCompile')) {
				// keyed by template_map_id
				$templates = $this->_getTemplateModel()->getNamedTemplatesInStyleTreeWithChildren($templateTitles);
				$this->_getTemplateModel()->compileMappedTemplatesInStyleTree(array_keys($templates));
			}
			else
			{
				$db = $this->_db;
				$db->update(
					'xf_template_map',
					array('template_final' => null, 'template_modifications' => null),
					'title IN (' . $db->quote($templateTitles) . ')'
				);
			}
		}

	}


	/**
	 * Gets the template model.
	 *
	 * @return TMS_Model_Modification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('TMS_Model_Modification');
	}

	/**
	 * Lazy load the template model object.
	 *
	 * @return  XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}
}