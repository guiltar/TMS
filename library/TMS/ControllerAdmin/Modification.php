<?php

class TMS_ControllerAdmin_Modification extends XenForo_ControllerAdmin_StyleAbstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('option');
		parent::_preDispatch($action);
	}

	/**
	 * Template index. This is a list of templates, so redirect this to a
	 * style-specific list.
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionIndex()
	{
		$styleModel = $this->_getStyleModel();

		$styleId = $styleModel->getStyleIdFromCookie();

		$style = $this->_getStyleModel()->getStyleById($styleId, true);
		if (!$style)
		{
			$style = $this->_getStyleModel()->getStyleById(XenForo_Application::get('options')->defaultStyleId);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildAdminLink('styles/template-modifications', $style)
		);
	}

	/**
	 * Helper to get the template modification add/edit form controller response.
	 *
	 * @param array $modification
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getModificationAddEditResponse(array $modification, $inputStyleId)
	{
		$addOnModel = $this->_getAddOnModel();
		$styleModel = $this->_getStyleModel();
		$templateModel = $this->_getTemplateModel();

		if ($modification['style_id'] != $inputStyleId) {
			// actually adding a "copy" of this template in this style
			$modification['modification_id'] = 0;
			$modification['style_id'] = $inputStyleId;
		}

		$viewParams = array(
			'modification' => $modification,
			'style' => $this->_getStyleModel()->getStyleById($inputStyleId, true),
			'masterStyle' => $styleModel->showMasterStyle() ? $styleModel->getStyleById(0, true) : array(),
			'styles' => $styleModel->getAllStylesAsFlattenedTree($styleModel->showMasterStyle() ? 1 : 0),

			'addOnOptions' => ($modification['style_id'] == 0 ? $addOnModel->getAddOnOptionsListIfAvailable(true, false) : array()),
			'addOnSelected' => (isset($modification['addon_id']) ? $modification['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('TMS_ViewAdmin_Modification_Edit', 'tms_modification_edit', $viewParams);
	}

	/**
	 * Displays a form to add a template modification.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$input = $this->_input->filter(array(
			'style_id' => XenForo_Input::UINT
		));

		$modification = array(
			'modification_id' => 0,
			'style_id' => $input['style_id']
		);

		return $this->_getModificationAddEditResponse($modification, $input['style_id']);
	}

	/**
	 * Displays a form to edit a template modification.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$input = $this->_input->filter(array(
			'modification_id' => XenForo_Input::UINT,
			'style_id' => XenForo_Input::UINT
		));

		$modification = $this->_getModificationOrError($input['modification_id']);

		if (!$this->_input->inRequest('style_id')) {
			// default to editing in the specified style
			$input['style_id'] = $modification['style_id'];
		}

		if ($input['style_id'] != $modification['style_id']) {
			$specificModification = $this->_getModificationModel()->getModificationInStyleByTitle($modification['title'], $input['style_id']);
			if ($specificModification) {
				$modification = $specificModification;
			}
		}

		$modification['search_value'] = $this->_getStylePropertyModel()->replacePropertiesInTemplateForEditor(
			$modification['search_value'], $input['style_id']
		);

		$modification['replace_value'] = $this->_getStylePropertyModel()->replacePropertiesInTemplateForEditor(
			$modification['replace_value'], $input['style_id']
		);


		return $this->_getModificationAddEditResponse($modification, $input['style_id']);
	}

	/**
	 * Updates an existing template modification or inserts a new one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING)) {
			// user clicked delete
			return $this->responseReroute('TMS_ControllerAdmin_Modification', 'deleteConfirm');
		}

		$data = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'template_title' => XenForo_Input::STRING,
			'style_id' => XenForo_Input::UINT,
			'description' => XenForo_Input::STRING,
			'execute_order' => XenForo_Input::UINT,
			'modification_type' => XenForo_Input::STRING,
			'search_value' => array(XenForo_Input::STRING, 'noTrim' => true),
			'replace_value' => array(XenForo_Input::STRING, 'noTrim' => true),
			'addon_id' => XenForo_Input::STRING
		));

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle($data['style_id'])
		);

		$propertyChangesSearch = $propertyModel->translateEditorPropertiesToArray(
			$data['search_value'], $data['search_value'], $properties
		);
		$propertyChangesReplace = $propertyModel->translateEditorPropertiesToArray(
			$data['replace_value'], $data['replace_value'], $properties
		);

		$writer = XenForo_DataWriter::create('TMS_DataWriter_Modification');
		if ($modificationId = $this->_input->filterSingle('modification_id', XenForo_Input::UINT)) {
			$writer->setExistingData($modificationId);
		}

		$writer->bulkSet($data);

		if ($writer->hasChanges() || $writer->get('style_id') > 0) {
			$writer->updateVersionId();
		}

		$writer->save();

		$propertyModel->saveStylePropertiesInStyleFromTemplate($data['style_id'], $propertyChangesReplace, $properties);

		$style = $this->_getStyleModel()->getStyleByid($writer->get('style_id'), true);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('template-modifications', null, array('style_id' => $style['style_id'])) . $this->getLastHash($writer->get('title'))
		);
	}

	public function actionDelete()
	{
		$modificationId = $this->_input->filterSingle('modification_id', XenForo_Input::UINT);
		$modification = $this->_getModificationOrError($modificationId);

		if ($this->isConfirmedPost()) // delete the modification
		{
			$writer = XenForo_DataWriter::create('TMS_DataWriter_Modification');
			$writer->setExistingData($modificationId);

			$writer->delete();

			$style = $this->_getStyleModel()->getStyleByid($writer->get('style_id'), true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('template-modifications', null, array('style_id' => $style['style_id']))
			);
		}
		else // show a delete confirmation dialog
		{
			$viewParams = array(
				'modification' => $modification,
				'style' => $this->_getStyleModel()->getStyleById($modification['style_id']),
			);

			return $this->responseView('TMS_ViewAdmin_Modification_Delete', 'tms_modification_delete', $viewParams);
		}
	}

	// legacy
	public function actionDeleteConfirm()
	{
		return $this->actionDelete();
	}


	public function actionToggle()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		return $this->_getToggleResponse(
			$this->_getModificationModel()->getEffectiveModificationListForStyle($styleId),
			'TMS_DataWriter_Modification',
			'template-modifications'
		);
	}

	/**
	 * Gets a valid template modification or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getModificationOrError($id)
	{
		$info = $this->_getModificationModel()->getModificationById($id);
		if (!$info) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('tms_requested_modification_not_found'), 404));
		}

		return $info;
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
	 * Get the add-on model.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
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

	/**
	 * @return  XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
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