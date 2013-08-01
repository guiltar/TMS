<?php

class TMS_ControllerAdmin_TemplateModification extends XFCP_TMS_ControllerAdmin_TemplateModification
{

	public function actionIndex()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Style', 'template-modifications');
	}

	protected function _getModificationAddEditResponse(array $modification)
	{
		/* @var $response XenForo_ControllerResponse_View */
		$response = parent::_getModificationAddEditResponse($modification);

		$response->params['style'] = $this->_getStyleModel()->getStyleById($modification['style_id'], true);

		if($modification['style_id'])
		{
			$response->params['addOnOptions'] = array();
			$response->params['addOnSelected'] = '';
		}

		return $response;
	}

	/**
	 * Displays a form to add a new template mod.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getModificationAddEditResponse(array(
			'action' => 'str_replace',
			'execution_order' => 10,
			'enabled' => 1,
			'style_id' => $this->_input->filterSingle('style_id', XenForo_Input::UINT)
		));
	}

	/**
	 * Inserts a new template mod or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		/* @var $response XenForo_ControllerResponse_Redirect */
		$response = parent::actionSave();

		if($response instanceof XenForo_ControllerResponse_Redirect)
		{
			$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
			$style = $this->_getStyleModel()->getStyleById($styleId, true);

			$hash = ($pos = strpos($response->redirectTarget, '#')) ? substr($response->redirectTarget, $pos) : '';

			$response->redirectTarget = XenForo_Link::buildAdminLink('styles/'.$this->_routePrefix, $style) . $hash;
		}

		return $response;
	}

	protected function _modifyModificationDwData(array &$dwData, $modificationId)
	{
		$dwData['style_id'] = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		parent::_modifyModificationDwData($dwData, $modificationId);
	}

	public function actionDelete()
	{
		/* @var $response XenForo_ControllerResponse_Redirect */
		$response = parent::actionDelete();

		if($response instanceof XenForo_ControllerResponse_Redirect)
		{
			$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
			$style = $this->_getStyleModel()->getStyleById($styleId, true);

			$response->redirectTarget = XenForo_Link::buildAdminLink('styles/'.$this->_routePrefix, $style);
		}

		return $response;
	}

	public function actionTest()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		XenForo_Application::set('tms_style_id', $styleId);

		return parent::actionTest();
	}


	protected function _getTestContent(XenForo_DataWriter_TemplateModificationAbstract $dw)
	{
		$template = $this->_getTemplateModel()->getEffectiveTemplateByTitle($dw->get('template'), $dw->get('style_id'));
		return ($template ? $template['template'] : false);
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