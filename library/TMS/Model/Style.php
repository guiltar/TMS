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
			$dataNode = $rootNode->appendChild($document->createElement('public_template_modifications'));
			$this->_getModificationModel()->appendModificationStyleXml($dataNode, $style['style_id']);
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

		parent::importStyleXml($document, $parentStyleId, $overwriteStyleId);

		if ($overwriteStyleId)
		{
			$this->_getModificationModel()->deleteModificationsForStyle($overwriteStyleId);
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

		if($targetStyleId)
		{
			$this->_getModificationModel()->importModificationStyleXml($document->public_template_modifications, $targetStyleId);

			XenForo_Application::defer('Atomic',
				array('simple' => array('TemplateReparse', 'Template')),
				'templateRebuild', true
			);
		}

		XenForo_Db::commit($db);
	}

	/**
	 * @return XenForo_Model_TemplateModification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('XenForo_Model_TemplateModification');
	}
}