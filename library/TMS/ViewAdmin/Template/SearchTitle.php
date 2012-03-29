<?php

class TMS_ViewAdmin_Template_SearchTitle extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		$results = array();
		foreach ($this->_params['templates'] AS $template)
		{
			$results[$template['title']]['username'] = $template['title'];
		}

		return array(
			'results' => $results
		);
	}
}