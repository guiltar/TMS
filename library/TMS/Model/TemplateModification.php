<?php

class TMS_Model_TemplateModification extends XFCP_TMS_Model_TemplateModification
{

	public function getAllModificationsInStyle($styleId)
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM {$this->_modTableName}
			WHERE style_id = ?
			ORDER BY template, execution_order
		", 'modification_id', $styleId);
	}

	public function applyTemplateModifications($template, array $modifications, &$status = array())
	{
		$parentStyleIds = $this->_getStyleModel()->getStyleBaseParentList(XenForo_Application::get('tms_style_id'));

		$parentStyleIds[] = XenForo_Application::get('tms_style_id');

		$statusSkipped = array();

		foreach($modifications as $id => $modification)
		{
			if(!in_array($modification['style_id'], $parentStyleIds))
			{
				unset($modifications[$id]);
				$statusSkipped[$id] = 'skipped_wrong_style';
			}
		}

		$template = parent::applyTemplateModifications($template, $modifications, $status);
		$status += $statusSkipped;

		return $template;
	}

	/**
	 * Gets all modifications that belong to the specified add-on,
	 * ordered by their modification keys.
	 *
	 * @param string $styleId
	 *
	 * @return array Format: [modification_key] => info
	 */
	public function getModificationsByStyleId($styleId)
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM {$this->_modTableName}
			WHERE style_id = ?
			ORDER BY modification_key
		", 'modification_key', $styleId);
	}

	/**
	 * Deletes the modifications that belong to the specified add-on.
	 *
	 * @param string $styleId
	 */
	public function deleteModificationsForStyle($styleId)
	{
		$db = $this->_getDb();
		$db->query("
			DELETE log FROM {$this->_logTableName} AS log
			INNER JOIN {$this->_modTableName} AS modification ON
				(log.modification_id = modification.modification_id AND modification.style_id = ?)
		", $styleId);
		$db->delete($this->_modTableName, 'style_id = ' . $db->quote($styleId));
	}

	/**
	 * Imports the modifications for an add-on.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the event data
	 * @param string $styleId Add-on to import for
	 */
	public function importModificationStyleXml(SimpleXMLElement $xml, $styleId)
	{
		$db = $this->_getDb();

		$styleMods = $this->getModificationsByStyleId($styleId);

		XenForo_Db::beginTransaction($db);
		$this->deleteModificationsForStyle($styleId);

		$xmlEntries = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->modification);

		$keys = array();
		foreach ($xmlEntries AS $entry)
		{
			$keys[] = (string)$entry['modification_key'];
		}

		$modifications = $this->getModificationsByKeys($keys);

		foreach ($xmlEntries AS $modification)
		{
			$key = (string)$modification['modification_key'];

			$dw = XenForo_DataWriter::create($this->_dataWriterName);
			if (isset($modifications[$key]))
			{
				$dw->setExistingData($modifications[$key]);
			}

			if (isset($styleMods[$key]))
			{
				$enabled = $styleMods[$key]['enabled'];
			}
			else
			{
				$enabled = (string)$modification['enabled'];
			}

			$dw->setOption(XenForo_DataWriter_TemplateModificationAbstract::OPTION_FULL_TEMPLATE_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_TemplateModificationAbstract::OPTION_REPARSE_TEMPLATE, false);
			$dw->bulkSet(array(
				'style_id' => $styleId,
				'template' => (string)$modification['template'],
				'modification_key' => $key,
				'description' => (string)$modification['description'],
				'execution_order' => (int)$modification['execution_order'],
				'enabled' => $enabled,
				'action' => (string)$modification['action'],
				'find' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($modification->find[0]),
				'replace' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($modification->replace[0])
			));
			$this->_addExtraToStyleXmlImportDw($dw, $modification);
			$dw->save();
		}

		XenForo_Db::commit($db);
	}

	protected function _addExtraToStyleXmlImportDw(XenForo_DataWriter_TemplateModificationAbstract $dw, SimpleXMLElement $modification)
	{

	}

	/**
	 * Appends the add-on template modification XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all prefix elements to
	 * @param string $styleId Add-on ID to be exported
	 */
	public function appendModificationStyleXml(DOMElement $rootNode, $styleId)
	{
		$modifications = $this->getModificationsByStyleId($styleId);

		$document = $rootNode->ownerDocument;

		foreach ($modifications AS $modification)
		{
			$modNode = $document->createElement('modification');
			$modNode->setAttribute('template', $modification['template']);
			$modNode->setAttribute('modification_key', $modification['modification_key']);
			$modNode->setAttribute('description', $modification['description']);
			$modNode->setAttribute('execution_order', $modification['execution_order']);
			$modNode->setAttribute('enabled', $modification['enabled']);
			$modNode->setAttribute('action', $modification['action']);

			$findNode = $document->createElement('find');
			$findNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $modification['find']));
			$modNode->appendChild($findNode);

			$replaceNode = $document->createElement('replace');
			$replaceNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $modification['replace']));
			$modNode->appendChild($replaceNode);

			$this->_modifyStyleXmlNode($modNode, $modification);

			$rootNode->appendChild($modNode);
		}
	}

	protected function _modifyStyleXmlNode(DOMElement &$modNode, array $modification)
	{
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
