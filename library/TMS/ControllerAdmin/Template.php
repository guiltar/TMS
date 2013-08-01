<?php

class TMS_ControllerAdmin_Template extends XFCP_TMS_ControllerAdmin_Template
{
	public function actionViewModifications()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		if ($templateId)
		{
			$template = $this->_getTemplateOrError($templateId);
			$styleId = $template['style_id'];
		}
		else
		{
			$title = $this->_input->filterSingle('title', XenForo_Input::STRING);
			$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

			$template = $this->_getTemplateModel()->getEffectiveTemplateByTitle($title, $styleId);
			if (!$template)
			{
				return $this->responseError(new XenForo_Phrase('requested_template_not_found'), 404);
			}
		}

		XenForo_Application::set('tms_style_id', $template['style_id']);

		/** @var $modificationModel XenForo_Model_TemplateModification */
		$modificationModel = $this->getModelFromCache('XenForo_Model_TemplateModification');
		$newTemplate = $modificationModel->applyModificationsToTemplate($template['title'], $template['template']);

		XenForo_Application::set('tms_style_id', null);

		$diff = new XenForo_Diff();
		$diffs = $diff->findDifferences($template['template'], $newTemplate);

		$viewParams = array(
			'template' => $template,
			'newTemplate' => $newTemplate,
			'diffs' => $diffs,
			'styleId' => $styleId,
			'canManuallyApply' => $styleId > 0,
		);

		return $this->responseView('XenForo_ViewAdmin_Template_ViewModifications', 'template_view_modifications', $viewParams);
	}

	public function actionApplyModifications()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		$template = $this->_getTemplateOrError($templateId);

		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		if (!$this->isConfirmedPost() || !$styleId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildAdminLink('templates/edit', $template, array('style_id' => $styleId))
			);
		}

		if ($template['style_id'] != $styleId)
		{
			$styleTemplate = $this->_getTemplateModel()->getTemplateInStyleByTitle($template['title'], $styleId);
			if ($styleTemplate)
			{
				$template = $styleTemplate;
			}
		}

		XenForo_Application::set('tms_style_id', $template['style_id']);

		/** @var $modificationModel XenForo_Model_TemplateModification */
		$modificationModel = $this->getModelFromCache('XenForo_Model_TemplateModification');
		$newTemplate = $modificationModel->applyModificationsToTemplate($template['title'], $template['template']);

		XenForo_Application::set('tms_style_id', null);

		if ($template['style_id'] == $styleId)
		{
			// updating
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($template);
		}
		else
		{
			// create new template
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
			$dw->bulkSet(array(
				'title' => $template['title'],
				'style_id' => $styleId,
				'addon_id' => $template['addon_id']
			));
		}

		$dw->set('disable_modifications', 1);
		$dw->set('template', $newTemplate);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildAdminLink('templates/edit', $template, array('style_id' => $styleId))
		);
	}

	/*public function getModelFromCache($class)
	{
		if(XenForo_Application::isRegistered('tms_set_style_id'))
		{
			$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
			$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		}

		return parent::getModelFromCache($class);
	}*/

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
	 * Lazy load the template model object.
	 *
	 * @return  XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}
}