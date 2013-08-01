<?php


class TMS_Listener
{
	public static function loadClassModel($class, array &$extend)
	{
		if ($class == 'XenForo_Model_Style') {
			$extend[] = 'TMS_Model_Style';
		}

		if ($class == 'XenForo_Model_Template') {
			$extend[] = 'TMS_Model_Template';
		}

		if ($class == 'XenForo_Model_TemplateModification') {
			$extend[] = 'TMS_Model_TemplateModification';
		}
	}

	public static function loadClassController($class, array &$extend)
	{
		if ($class == 'XenForo_ControllerAdmin_Style') {
			$extend[] = 'TMS_ControllerAdmin_Style';
		}

		if ($class == 'XenForo_ControllerAdmin_Template') {
			$extend[] = 'TMS_ControllerAdmin_Template';
		}

		if ($class == 'XenForo_ControllerAdmin_TemplateModification') {
			$extend[] = 'TMS_ControllerAdmin_TemplateModification';
		}
	}

	public static function loadClassDataWriter($class, array &$extend)
	{
		if ($class == 'XenForo_DataWriter_Style') {
			$extend[] = 'TMS_DataWriter_Style';
		}

		if ($class == 'XenForo_DataWriter_Template') {
			$extend[] = 'TMS_DataWriter_Template';
		}

		if ($class == 'XenForo_DataWriter_TemplateModification') {
			$extend[] = 'TMS_DataWriter_TemplateModification';
		}
	}
}