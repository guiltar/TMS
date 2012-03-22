<?php

/**
 * General template compiling class. This takes a string (template) and converts it
 * into PHP code. This code represents the full statements.
 *
 * Most methods are public so as to be usable by the tag/function handlers. Externally,
 * {@link compile()} is the primary method to use for basic compilation.
 *
 * @package XenForo_Template
 */
class TMS_Template_Compiler extends XenForo_Template_Compiler
{
	protected static $_modificationCache = array();

	/**
	 * Lex and parse the template into segments for final compilation.
	 *
	 * @return array Parsed segments
	 */
	public function modifyAndParse($title = '', $styleId = 0, $templateId = 0)
	{
		$conditions = array(
			'template_title' => $title,
			'active' => 1,
			'addon_active' => 1
		);

		$mods = XenForo_Model::create('TMS_Model_Modification')->getEffectiveModificationListForStyle($styleId, $conditions);

		$modsData = array();
		$parsed = null;
		$templateOriginal = $this->_text;

		$cacheRecordKey = $templateId . ':' . implode(',', array_keys($mods));
		if ($templateId && isset(self::$_modificationCache[$cacheRecordKey])) {
			return self::$_modificationCache[$cacheRecordKey];
		}


		foreach ($mods as $key => $mod)
		{
			$modsData[$key]['apply_count'] = 0;
			$modsData[$key]['title'] = $mod['title'];
		}

		$tms = XenForo_Model::create('XenForo_Model_AddOn')->getAddOnById('TMS');

		if (!empty($mods) && $tms['active']) {
			try
			{
				foreach ($mods as &$mod)
				{
					$mod['apply_count'] = 0;
					switch ($mod['modification_type'])
					{
						case 'str_replace':
							$this->_text = str_ireplace($mod['search_value'], $mod['replace_value'], $this->_text, $mod['apply_count']);
							break;
						case 'preg_replace':
							$this->_text = preg_replace($mod['search_value'], $mod['replace_value'], $this->_text, -1, $mod['apply_count']);
							break;
						case 'callback':
							call_user_func_array(array($mod['callback_class'], $mod['callback_method']), array(&$this->_text, &$mod['apply_count'], $styleId));
							break;
					}
				}
				//die(Zend_Debug::dump($this->_text));
				$parsed = $this->lexAndParse();
				$this->setFollowExternal(false);
				$this->compileParsed($parsed, $title, 0, 0);

				foreach ($mods as $key => $modification)
				{
					$modsData[$key]['apply_count'] = $modification['apply_count'];
				}
			}
			catch (XenForo_Template_Compiler_Exception $e)
			{
				$parsed = null;
				$this->_text = $templateOriginal;
			}
		}

		$cacheRecordKey = $templateId . ':' . implode(',', array_keys($mods));
		$result = array('template_final' => $this->_text, 'template_parsed' => $parsed, 'template_modifications' => $modsData);
		self::$_modificationCache[$cacheRecordKey] = $result;

		return $result;
	}

}