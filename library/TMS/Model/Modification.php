<?php

/**
 * Model to work with code events.
 *
 * @package XenForo_CodeEvents
 */
class TMS_Model_Modification extends XenForo_Model
{

	public function getAllModificationsInStyle($styleId)
	{
		return $this->fetchAllKeyed('
   			SELECT modification.*, addon.title AS addonTitle
   			FROM tms_modification as modification
   			LEFT JOIN xf_template AS template ON
			    (template.title = modification.template_title AND template.style_id = modification.style_id)
   			LEFT JOIN xf_addon AS addon ON
   				(addon.addon_id = modification.addon_id)
   			WHERE modification.style_id = ?
   			ORDER BY modification.template_title, modification.title
   		', 'modification_id', $styleId);
	}

	public function getEffectiveModificationListForStyle($styleId, array $conditions = array(), $fetchOptions = array())
	{
		$whereClause = $this->prepareModificationConditions($styleId, $conditions, $fetchOptions);

		// select all modifications satisfying conditions
		$mods = $this->fetchAllKeyed('
   			SELECT modification.*,addon.title AS addonTitle,
   				IF(modification.style_id = 0, \'default\', IF(modification.style_id = ?, \'custom\', \'inherited\')) AS modification_state,
   				IF(modification.style_id = ? , 1, 0) AS canDelete,
   				IF(modification.addon_id, addon.active, 1) AS addon_active
   			FROM tms_modification AS modification
   			LEFT JOIN xf_addon AS addon ON
   				(addon.addon_id = modification.addon_id)
   			WHERE ' . $whereClause . '
   			ORDER BY modification.template_title, modification.execute_order
   		', 'modification_id', array($styleId, $styleId));


		// now looking for the most closed parent modification for each title
		$style = $this->_getStyleModel()->getStyleById($styleId);
		$parentsStyleIds = explode(',', $style['parent_list']);
		$modsGrouppedByTitile = array();
		$templateTitles = array();

		foreach ($mods as $mod)
		{
			$modsGrouppedByTitile[$mod['title']][$mod['style_id']] = $mod;
			$templateTitles[] = $mod['template_title'];
		}

		$effectiveTemplates = $this->_getTemplateModel()->getEffectiveTemplatesByTitles($templateTitles, $styleId);
		$effectiveMods = array();

		foreach ($modsGrouppedByTitile as $title => $modsTitled)
		{
			foreach ($parentsStyleIds as $parentStyleId)
			{
				$parentStyleId = (int)$parentStyleId;
				if (isset($modsTitled[$parentStyleId])) {
					// add mapped templates info
					$effectiveMod = &$modsTitled[$parentStyleId];
					if(!empty($effectiveTemplates[$effectiveMod['template_title']]))
					{
						$effectiveTemplate = $effectiveTemplates[$effectiveMod['template_title']];
						$effectiveMod['template_id'] = $effectiveTemplate['template_id'];
						$effectiveMod['template_final'] = $effectiveTemplate['template_final'];
						$effectiveMod['template_modifications'] = $effectiveTemplate['template_modifications'];
					}
					else
					{
						$effectiveMod['template_id'] = 0;
						$effectiveMod['template_final'] = null;
						$effectiveMod['template_modifications'] = null;
					}

					$effectiveMods[$modsTitled[$parentStyleId]['modification_id']] = $effectiveMod;
					break;
				}
			}
		}

		return $effectiveMods;
	}

	public function prepareModificationConditions($styleId, array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['title'])) {
			if (is_array($conditions['title'])) {
				$sqlConditions[] = 'modification.title LIKE ' . XenForo_Db::quoteLike($conditions['title'][0], $conditions['title'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'modification.title LIKE ' . XenForo_Db::quoteLike($conditions['title'], 'lr', $db);
			}
		}

		if (!empty($conditions['template']))
		{
			if (is_array($conditions['template']))
			{
				$sqlConditions[] = 'modification.replace_value LIKE ' . XenForo_Db::quoteLike($conditions['template'][0], $conditions['phrase_text'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'modification.replace_value LIKE ' . XenForo_Db::quoteLike($conditions['template'], 'lr', $db);
			}
		}

		if (!empty($conditions['template_title'])) {
			if (is_array($conditions['template_title'])) {
				$sqlConditions[] = 'modification.template_title IN ' . $db->quote($conditions['template_title']);
			}
			else
			{
				$sqlConditions[] = 'modification.template_title = ' . $db->quote($conditions['template_title']);
			}
		}

		if (!empty($conditions['modification_state'])) {
			$stateIf = 'IF(modification.style_id = 0, \'default\', IF(modification.style_id ='.$db->quote($styleId).', \'custom\', \'inherited\'))';
			if (is_array($conditions['modification_state']))
			{
				$sqlConditions[] = $stateIf . ' IN (' . $db->quote($conditions['modification_state']) . ')';
			}
			else
			{
				$sqlConditions[] = $stateIf . ' = ' . $db->quote($conditions['modification_state']);
			}
		}

		if (!empty($conditions['addon_active'])) {
			$sqlConditions[] = ' addon.active = 1 OR modification.addon_id=\'\'';
		}

		if (!empty($conditions['active'])) {
			$sqlConditions[] = ' modification.active = 1';
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Gets an array of all modifications, ordered by their title and execution order,
	 * keyed by modification_id
	 *
	 * @return array
	 */
	public function getAllModifications()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM tms_modification
			ORDER BY title, execute_order
		', 'modification_id');
	}

	/**
	 * Gets the specified modification.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getModificationById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT modification.*,
			addon.title AS addonTitle
			FROM tms_modification AS modification
   			LEFT JOIN xf_addon AS addon ON
   				(addon.addon_id = modification.addon_id)
			WHERE modification_id = ?
		', $id);
	}

	/**
	 * Returns the modifications specified by template IDs
	 *
	 * @param array $templateIds
	 *
	 * @return array
	 */
	public function getModificationsByIds(array $modificationIds)
	{
		return $this->fetchAllKeyed('
			SELECT modification.*,
			addon.title AS addonTitle
			FROM tms_modification AS modification
   			LEFT JOIN xf_addon AS addon ON
   				(addon.addon_id = modification.addon_id)
  				WHERE modification_id IN(' . $this->_getDb()->quote($modificationIds) . ')
  		', 'modification_id');
	}

	/**
	 * Fetches a modification from a particular style based on its title.
	 * Note that if a version of the requested modification does not exist
	 * in the specified style, nothing will be returned.
	 *
	 * @param string Title
	 * @param integer Style ID (defaults to master style)
	 *
	 * @return array
	 */
	public function getModificationInStyleByTitle($title, $styleId = 0)
	{
		return $this->_getDb()->fetchRow('
			SELECT modification.*,
			addon.title AS addonTitle
			FROM tms_modification AS modification
   			LEFT JOIN xf_addon AS addon ON
   				(addon.addon_id = modification.addon_id)
            WHERE modification.title = ?
                AND modification.style_id = ?
      		', array($title, $styleId));
	}

	/**
	 * Fetches modifications from a particular style based on their titles.
	 * Note that if a version of the requested modification does not exist
	 * in the specified style, nothing will be returned for it.
	 *
	 * @param array $titles List of titles
	 * @param integer $styleId Style ID (defaults to master style)
	 *
	 * @return array Format: [title] => info
	 */
	public function getModificationsInStyleByTitles(array $titles, $styleId = 0)
	{
		if (!$titles) {
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT modification.*,
			addon.title AS addonTitle
			FROM tms_modification AS modification
   			LEFT JOIN xf_addon AS addon ON
   				(addon.addon_id = modification.addon_id)
  			WHERE modification.title IN (' . $this->_getDb()->quote($titles) . ')
  				AND style_id = ?
  		', 'title', $styleId);
	}

	/**
	 * Gets all modifications for the specified add-on in ID and execute order.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [event listener id] => info
	 */
	public function getModificationsByAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT modification.*,template_map.template_map_id
			FROM tms_modification AS modification
			LEFT JOIN xf_template_map AS template_map ON
   			(template_map.title = modification.template_title AND template_map.style_id = modification.style_id)
			WHERE addon_id = ?
			ORDER BY modification_id, execute_order
		', 'modification_id', $addOnId);
	}


	/**
	 * TODO: Outdated modifications
	 *
	 * @return array
	 */
	public function getOutdatedModifications()
	{
		return $this->fetchAllKeyed('
   			SELECT modification.modification_id, modification.title, modification.style_id,
   				modification.addon_id, modification.version_id, modification.version_string,
   				master.version_string AS master_version_string
   			FROM tms_modification AS modification
   			INNER JOIN tms_modification AS master ON (master.title = modification.title AND master.style_id = 0)
   			INNER JOIN xf_style AS style ON (style.style_id = modification.style_id)
   			WHERE modification.style_id > 0
   				AND master.version_id > modification.version_id
   		', 'modification_id');
	}

	/**
	 * Returns all the modifications that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [title] => info
	 */
	public function getMasterModificationsInAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
  			SELECT *
  			FROM tms_modification
  			WHERE addon_id = ?
  				AND style_id = 0
  			ORDER BY title ASC
  		', 'title', $addOnId);
	}


	/**
	 * Deletes the modifications that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteModificationsForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$db->delete('tms_modification', 'style_id = 0 AND addon_id = ' . $db->quote($addOnId));
	}

	public function deleteModificationsInStyle($styleId)
	{
		$db = $this->_getDb();

		$db->delete('tms_modification', 'style_id = ' . $db->quote($styleId));
	}

	/**
	 * Imports the add-on modifications XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the data
	 * @param string $addOnId Add-on to import for
	 * @param integer $maxExecution Maximum run time in seconds
	 * @param integer $offset Number of elements to skip
	 *
	 * @return boolean|integer True on completion; false if the XML isn't correct; integer otherwise with new offset value
	 */
	public function importModificationsAddOnXml(SimpleXMLElement $xml, $addOnId, $maxExecution = 0, $offset = 0)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$startTime = microtime(true);

		if ($offset == 0) {
			$this->deleteModificationsForAddOn($addOnId);
		}

		$modifications = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->modification);

		$titles = array();
		$current = 0;
		foreach ($modifications AS $modification)
		{
			$current++;
			if ($current <= $offset) {
				continue;
			}

			$titles[] = (string)$modification['title'];
		}

		$existingModifications = $this->getModificationsInStyleByTitles($titles, 0);

		$current = 0;
		$restartOffset = false;
		foreach ($modifications AS $modification)
		{
			$current++;
			if ($current <= $offset) {
				continue;
			}

			$modificationName = (string)$modification['title'];

			$dw = XenForo_DataWriter::create('TMS_DataWriter_Modification');
			if (isset($existingModifications[$modificationName])) {
				$dw->setExistingData($existingModifications[$modificationName], true);
			}
			$dw->setOption(TMS_DataWriter_Modification::OPTION_FULL_COMPILE, false);
			$dw->setOption(TMS_DataWriter_Modification::OPTION_TEST_COMPILE, false);
			$dw->setOption(TMS_DataWriter_Modification::OPTION_CHECK_DUPLICATE, false);
			$dw->bulkSet(array(
				'style_id' => 0,
				'title' => $modificationName,
				'template_title' => (string)$modification['template_title'],
				'execute_order' => (int)$modification['execute_order'],
				'modification_type' => (string)$modification['modification_type'],
				'callback_class' => (string)$modification['callback_class'],
				'callback_method' => (string)$modification['callback_method'],
				'search_value' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($modification->search_value),
				'replace_value' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($modification->replace_value),
				'addon_id' => $addOnId,
				'description' => (string)$modification['description'],
				'version_id' => (int)$modification['version_id'],
				'version_string' => (string)$modification['version_string'],
				'active' => (int)$modification['active'],
			));
			$dw->save();

			if ($maxExecution && (microtime(true) - $startTime) > $maxExecution) {
				$restartOffset = $current;
				break;
			}
		}

		XenForo_Db::commit($db);

		return ($restartOffset ? $restartOffset : true);
	}

	/**
	 * Imports modifications into a given style. Note that this assumes the style is already empty.
	 * It does not check for conflicts.
	 *
	 * @param SimpleXMLElement $xml
	 * @param integer $styleId
	 */
	public function importModificationsStyleXml(SimpleXMLElement $xml, $styleId)
	{
		$db = $this->_getDb();

		if ($xml->modification === null) {
			return;
		}

		XenForo_Db::beginTransaction($db);

		foreach ($xml->modification AS $modification)
		{
			$modificationName = (string)$modification['title'];

			$dw = XenForo_DataWriter::create('TMS_DataWriter_Modification');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->bulkSet(array(
				'style_id' => $styleId,
				'title' => $modificationName,
				'template_title' => (string)$modification['template_title'],
				'execute_order' => (int)$modification['execute_order'],
				'modification_type' => (string)$modification['modification_type'],
				'callback_class' => (string)$modification['callback_class'],
				'callback_method' => (string)$modification['callback_method'],
				'search_value' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($modification->search_value),
				'replace_value' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($modification->replace_value),
				'addon_id' => $modification['addon_id'],
				'description' => (string)$modification['description'],
				'version_id' => (int)$modification['version_id'],
				'version_string' => (string)$modification['version_string'],
				'active' => (int)$modification['active'],
			));
			$dw->save(); //die(Zend_Debug::dump($modificationName));
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Appends the add-on modification XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendModificationsAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		$modifications = $this->getMasterModificationsInAddOn($addOnId);
		foreach ($modifications AS $modification)
		{
			$modificationNode = $document->createElement('modification');
			$modificationNode->setAttribute('title', $modification['title']);
			$modificationNode->setAttribute('template_title', $modification['template_title']);
			$modificationNode->setAttribute('execute_order', $modification['execute_order']);
			$modificationNode->setAttribute('modification_type', $modification['modification_type']);
			$modificationNode->setAttribute('callback_class', $modification['callback_class']);
			$modificationNode->setAttribute('callback_method', $modification['callback_method']);
			$modificationNode->setAttribute('description', $modification['description']);
			$modificationNode->setAttribute('version_id', $modification['version_id']);
			$modificationNode->setAttribute('version_string', $modification['version_string']);
			$modificationNode->setAttribute('active', $modification['active']);

			$findNode = $document->createElement('search_value');
			$findNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $modification['search_value']));
			$modificationNode->appendChild($findNode);

			$replaceNode = $document->createElement('replace_value');
			$replaceNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $modification['replace_value']));
			$modificationNode->appendChild($replaceNode);

			$rootNode->appendChild($modificationNode);
		}
	}

	/**
	 * Appends the modification XML for modification in the specified style.
	 *
	 * @param DOMElement $rootNode
	 * @param integer $styleId
	 */
	public function appendModificationsStyleXml(DOMElement $rootNode, $styleId)
	{
		$document = $rootNode->ownerDocument;

		$modifications = $this->getAllModificationsInStyle($styleId);
		foreach ($modifications AS $modification)
		{
			$modificationNode = $document->createElement('modification');
			$modificationNode->setAttribute('title', $modification['title']);
			$modificationNode->setAttribute('template_title', $modification['template_title']);
			$modificationNode->setAttribute('execute_order', $modification['execute_order']);
			$modificationNode->setAttribute('modification_type', $modification['modification_type']);
			$modificationNode->setAttribute('callback_class', $modification['callback_class']);
			$modificationNode->setAttribute('callback_method', $modification['callback_method']);
			$modificationNode->setAttribute('description', $modification['description']);
			$modificationNode->setAttribute('version_id', $modification['version_id']);
			$modificationNode->setAttribute('version_string', $modification['version_string']);
			$modificationNode->setAttribute('active', $modification['active']);

			$findNode = $document->createElement('search_value');
			$findNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $modification['search_value']));
			$modificationNode->appendChild($findNode);

			$replaceNode = $document->createElement('replace_value');
			$replaceNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $modification['replace_value']));
			$modificationNode->appendChild($replaceNode);

			$rootNode->appendChild($modificationNode);
		}
	}

	/**
	 * Gets the modifications development XML.
	 *
	 * @return DOMDocument
	 */
	public function getModificationsDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('tms_mods');
		$document->appendChild($rootNode);

		$this->appendTemplatesAddOnXml($rootNode, 'XenForo');

		return $document;
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