<?php

class TMS_ControllerAdmin_Template extends XFCP_TMS_ControllerAdmin_Template
{
	/**
	 * Helper to get the template add/edit form controller response.
	 *
	 * @param array $template
	 * @param integer $inputStyleId The style this template is being edited in
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getTemplateAddEditResponse(array $template, $inputStyleId)
	{
		/* @var $response XenForo_ControllerResponse_View */
		$response = parent::_getTemplateAddEditResponse($template, $inputStyleId);
		if ($response instanceof XenForo_ControllerResponse_View && !empty($template['title'])) {
			/* @var $modHelper TMS_ControllerHelper_Modification*/
			$modHelper = $this->getHelper('TMS_ControllerHelper_Modification');
			$response->params = array_merge($response->params, $modHelper->getModifications($inputStyleId, $template['title']));
		}

		return $response;
	}


	public function actionCompare()
	{
		$input = $this->_input->filter(array(
			'template_id' => XenForo_Input::UINT,
			'style_id' => XenForo_Input::UINT,
			'title' => XenForo_Input::STRING,
			'preview' => XenForo_Input::UINT,
		));

		$templateModel = $this->_getTemplateModel();

		if (!$this->_input->inRequest('title')) {
			$template = $templateModel->getTemplateById($input['template_id']);
			$input['title'] = !empty($template['title']) ? $template['title'] : '';
		}

		TMS_Model_Template::$fetchFinalTemplate = 1;
		$template = $templateModel->getEffectiveTemplateByTitle($input['title'], $input['style_id']);

		$style = $this->_getStyleModel()->getStyleById($input['style_id'], true);

		if (!$style) {
			return $this->responseError(new XenForo_Phrase('requested_style_not_found'), 404);
		}
		elseif (!$template)
		{
			return $this->responseError(new XenForo_Phrase('requested_template_not_found'), 404);
		}

		if(is_null($template['template_final']))
		{
			$compiler = new TMS_Template_Compiler($template['template']);
			$modified = $compiler->modifyAndParse($input['title'], $input['style_id']);
			$template['template_final'] = $modified['template_final'];
			$input['preview']= 1;
		}

		$template['template'] = $this->_getStylePropertyModel()->replacePropertiesInTemplateForEditor($template['template'], $input['style_id']);
		$template['template_final'] = $this->_getStylePropertyModel()->replacePropertiesInTemplateForEditor($template['template_final'], $input['style_id']);

		$diff = new Diff_Compare(explode("\n", $template['template']), explode("\n", $template['template_final']));
		$renderer = new Diff_Renderer_Html_SideBySide;

		$viewParams = array(
			'preview' => $input['preview'],
			'compare' => $diff->Render($renderer),
			'template' => $template,
			'style' => $style,
		);

		$containerParams = array('containerTemplate' => 'PAGE_CONTAINER_SIMPLE');

		return $this->responseView('TMS_ViewAdmin_TemplateModification_Compare', 'tms_template_compare', $viewParams, $containerParams);
	}

	public function actionText()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		$title = $this->_input->filterSingle('template_title', XenForo_Input::STRING);
		$template = $this->_getTemplateModel()->getEffectiveTemplateByTitle($title, $styleId);
		$template['template'] = $this->_getStylePropertyModel()->replacePropertiesInTemplateForEditor($template['template'], $styleId);

		return $this->responseView('TMS_ViewAdmin_Template_Text', '', array(
			'template' => $template
		));
	}

	public function actionSearchTitle()
	{
		$q = $this->_input->filterSingle('q', XenForo_Input::STRING);
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		if ($q !== '')
		{
			$users = $this->_getTemplateModel()->getEffectiveTemplateListForStyle(
				$styleId,
				array('title' => array($q , 'r')),
				array('limit' => 10)
			);
		}
		else
		{
			$users = array();
		}

		$viewParams = array(
			'templates' => $users
		);

		return $this->responseView(
			'TMS_ViewAdmin_Template_SearchTitle',
			'',
			$viewParams
		);
	}

	/**
	 * Gets the modification model.
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
	 * Lazy load the template model object.
	 *
	 * @return  XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}
}