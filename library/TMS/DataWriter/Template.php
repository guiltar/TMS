<?php

/**
* Data writer for templates.
*
* @package XenForo_Template
*/
class TMS_DataWriter_Template extends XFCP_TMS_DataWriter_Template
{
	/**
	* Verification callback to prepare a template. This isn't actually a verifier;
	* it just automatically compiles the template.
	*
	* @param string $string Uncompiled template
	*
	* @return boolean
	*/
	protected function _verifyPrepareTemplate($template)
	{
		XenForo_Application::set('tms_style_id', $this->get('style_id'));

		$return = parent::_verifyPrepareTemplate($template);

		XenForo_Application::set('tms_style_id', null);

		return $return;
	}
}