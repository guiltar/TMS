<?php

/**
 * Model for add-ons.
 *
 * @package XenForo_AddOns
 */
class TMS_Model_AddOn extends XFCP_TMS_Model_AddOn
{

	/**
	 * Imports all the add-on associated XML into the DB and rebuilds the
	 * caches.
	 *
	 * @param SimpleXMLElement $xml Root node that contains all of the "data" nodes below
	 * @param string $addOnId Add-on to import for
	 */
	public function importAddOnExtraDataFromXml(SimpleXMLElement $xml, $addOnId)
	{
		parent::importAddOnExtraDataFromXml($xml, $addOnId);

		$this->getModelFromCache('TMS_Model_Modification')->importModificationsAddOnXml($xml->template_modifications, $addOnId);
	}

	/**
	 * Gets the XML data for the specified add-on.
	 *
	 * @param array $addOn Add-on info
	 *
	 * @return DOMDocument
	 */
	public function getAddOnXml(array $addOn)
	{
		$document = parent::getAddOnXml($addOn);
		$rootNode = $document->documentElement;
		$addOnId = $addOn['addon_id'];

		$dataNode = $rootNode->appendChild($document->createElement('template_modifications'));
		$this->getModelFromCache('TMS_Model_Modification')->appendModificationsAddOnXml($dataNode, $addOnId);

		return $document;
	}

	public function deleteAddOnMasterData($addOnId)
	{
		parent::deleteAddOnMasterData($addOnId);

		if ($addOnId != 'TMS') {
			$this->getModelFromCache('TMS_Model_Modification')->deleteModificationsForAddOn($addOnId);
		}
	}

}