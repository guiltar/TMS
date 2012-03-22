<?php

class TMS_ViewAdmin_Template_Text extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		return array(
			'template' => (string)$this->_params['template']['template']
		);
	}
}