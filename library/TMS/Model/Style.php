<?php

/**
 * Model for styles
 *
 * @package XenForo_Styles
 */
class TMS_Model_Style extends XFCP_TMS_Model_Style
{
	/**
	 * Gets the XML representation of a style, including customized templates and properties.
	 *
	 * @param array $style
	 *
	 * @return DOMDocument
	 */
	public function getStyleXml(array $style)
	{
		$document = parent::getStyleXml($style);

		if(!XenForo_Application::isRegistered('tmsIndependentExport'))
		{
			$rootNode = $document->documentElement;
			$dataNode = $rootNode->appendChild($document->createElement('template_modifications'));
			$this->getModelFromCache('TMS_Model_Modification')->appendModificationsStyleXml($dataNode, $style['style_id']);
		}

		return $document;
	}

	/**
	 * Imports a style XML file.
	 *
	 * @param SimpleXMLElement $document
	 * @param integer $parentStyleId If not overwriting, the ID of the parent style
	 * @param integer $overwriteStyleId If non-0, parent style is ignored
	 *
	 * @return array List of cache rebuilders to run
	 */
	public function importStyleXml(SimpleXMLElement $document, $parentStyleId = 0, $overwriteStyleId = 0)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$return = parent::importStyleXml($document, $parentStyleId, $overwriteStyleId);

		/* @var $modificationModel TMS_Model_Modification */
		$modificationModel = $this->getModelFromCache('TMS_Model_Modification');

		if ($overwriteStyleId) {
			$modificationModel->deleteModificationsInStyle($overwriteStyleId);
			$targetStyleId = $overwriteStyleId;
		}
		elseif (XenForo_Application::isRegistered('insertedStyleId'))
		{
			$targetStyleId = XenForo_Application::get('insertedStyleId');
		}
		else
		{
			$targetStyleId = 0;
		}

		if ($targetStyleId) {
			$modificationModel->importModificationsStyleXml($document->template_modifications, $targetStyleId);
		}

		XenForo_Db::commit($db);

		return $return;
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
}