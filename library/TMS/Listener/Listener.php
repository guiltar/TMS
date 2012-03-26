<?php


class TMS_Listener_Listener
{
	public static function loadClassModel($class, array &$extend)
	{
		if ($class == 'XenForo_Model_AddOn') {
			$extend[] = 'TMS_Model_AddOn';
		}

		if ($class == 'XenForo_Model_Template') {
			$extend[] = 'TMS_Model_Template';
		}

		if ($class == 'XenForo_Model_Style') {
			$extend[] = 'TMS_Model_Style';
		}
	}

	public static function loadClassController($class, array &$extend)
	{
		if ($class == 'XenForo_ControllerAdmin_Template') {
			$extend[] = 'TMS_ControllerAdmin_Template';
		}

		if ($class == 'XenForo_ControllerAdmin_Style') {
			$extend[] = 'TMS_ControllerAdmin_Style';
		}
	}

	public static function loadClassDataWriter($class, array &$extend)
	{
		if ($class == 'XenForo_DataWriter_AddOn') {
			$extend[] = 'TMS_DataWriter_AddOn';
		}

		if ($class == 'XenForo_DataWriter_Style') {
			$extend[] = 'TMS_DataWriter_Style';
		}
	}

	public static function templatePostRender($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
	{
		if ($template instanceof XenForo_Template_Admin && $templateName == 'template_edit') {

			$params = $template->getParams();

			if(!empty($params['modifications']) || !empty($params['customModifications']))
			{
				$modsTemplate = $template->create('tms_modification_list_items', $params)->render();
				$compareButton = $template->create('tms_compare_button', $params)->render();
				$content = $content . $modsTemplate;
				$content = preg_replace('#<dl class="ctrlUnit submitUnit">\s*<dt>\s*</dt>\s*<dd>#s', '$0' . $compareButton, $content);
			}
		}

		if ($template instanceof XenForo_Template_Admin && $templateName == 'home' or $templateName == 'tms_modification_list') {

			/* @var $templateModel XenForo_Model_Template*/
			$templateModel = XenForo_Model::create('XenForo_Model_Template');
			$templatesToRebuild = $templateModel->getEffectiveTemplatesToRebuild();

			if (!empty($templatesToRebuild)) {
				$needRebuildNotice = $template->create('tms_need_rebuild_notice')->render();
				$content = $needRebuildNotice . $content;
			}
		}

		if ($template instanceof XenForo_Template_Admin && $templateName == 'style_list') {
			$content = preg_replace(
				'#(<a.*?)/templates(".*?>).*?(</a>)#',
				'$0$1/template-modifications$2'.new XenForo_Phrase('tms_modifications').'$3',
				$content);
		}

		if ($template instanceof XenForo_Template_Admin && $templateName == 'style_customized_components') {
			$customizedModificationsTemplate = $template->create('tms_customized_modifications', $template->getParams());
			$content = str_replace(
				'<ol class="FilterList Scrollable" id="CustomItems">',
				'<ol class="FilterList Scrollable" id="CustomItems">'.$customizedModificationsTemplate,
				$content);
		}

		if ($template instanceof XenForo_Template_Admin && $templateName == 'style_mass_revert') {

			$params = $template->getParams();
			$phraseParams = array(
				'style' =>  $params['style']['title'],
				'numTemplates' => count($params['templates']),
				'numModifications' => count($params['modifications']),
				'numProperties' => count($params['properties']),
			);
			$content = preg_replace(
				'#<p>.*</p>#s',
				'<p>'.new XenForo_Phrase('tms_please_confirm_reversion_of_customized_components_from_style', $phraseParams).'</p>',
				$content);

			$hidden = '';
			foreach($params['modifications'] as $mod)
			{
				$hidden .=  '<input type="hidden" value="'.$mod.'" name="modifications[]">'."\n";
			}
			$content = str_replace(
				'</form>',
				$hidden.'</form>',
				$content);
		}
	}

}