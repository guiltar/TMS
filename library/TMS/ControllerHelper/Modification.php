<?php

class TMS_ControllerHelper_Modification extends XenForo_ControllerHelper_Abstract
{

	/**
	 * Displays a list of all template modifications, grouped by the add-on they belong to.
	 *
	 * @return array
	 */
	public function getModifications($inputStyleId, $conditions = array())
	{
		$modifications = array();

		foreach ($this->_getModificationModel()->getEffectiveModificationListForStyle($inputStyleId, $conditions) AS $modification)
		{
			$modifications[$modification['addon_id']][$modification['modification_id']] = $modification;
		}

		// get totals
		$totalModifications = 0;
		foreach ($modifications AS &$addOnModifications)
		{
			foreach ($addOnModifications as &$modification)
			{
				if ($modification['template_modifications'])
				{
					$modData = unserialize($modification['template_modifications']);

					$modification['apply_count'] = !empty($modData[$modification['modification_id']]['apply_count']) ?
						$modData[$modification['modification_id']]['apply_count'] : 0;

					if ($modification['active'] && ($modification['addon_active'] || $modification['addon_id'] == ''))
					{
						$modification['class'] = !$modification['apply_count'] ? 'NotApplied' :
							($modification['apply_count'] > 1 ? 'MultipleApplied' : 'OnceApplied');
					}
				}
				else
				{
					$modification['apply_count'] = null;
					$modification['class'] = 'Unknown';
				}
			}
			$totalModifications += count($addOnModifications);
		}


		if (isset($modifications[''])) {
			$customModifications = $modifications[''];
			unset($modifications['']);
		}
		else
		{
			$customModifications = array();
		}

		$viewParams = array(
			'addOns' => $this->_getAddOnModel()->getAllAddOns(),
			'modifications' => $modifications,
			'customModifications' => $customModifications,
			'totalModifications' => $totalModifications,
		);

		return $viewParams;
	}


	/**
	 * Gets the modification model.
	 *
	 * @return TMS_Model_Modification
	 */
	protected function _getModificationModel()
	{
		return $this->_controller->getModelFromCache('TMS_Model_Modification');
	}

	/**
	 * Get the add-on model.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_AddOn');
	}

	/**
	 * Lazy load the style model object.
	 *
	 * @return  XenForo_Model_Style
	 */
	protected function _getStyleModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_Style');
	}

	/**
	 * Lazy load the template model object.
	 *
	 * @return  XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_Template');
	}
}