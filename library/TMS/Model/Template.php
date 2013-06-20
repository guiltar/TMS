<?php

/**
 *  Model for templates
 *
 * 'template_parsed' in methods below is only used for compilation.
 *
 * @package XenForo_Templates
 */
class TMS_Model_Template extends XFCP_TMS_Model_Template
{
	public static $modifyTemplate = false;
	public static $fetchFinalTemplate = false;

	public function getAllTemplatesInStyle($styleId, $basicData = false)
	{
		$templates = parent::getAllTemplatesInStyle($styleId, $basicData);

		if(XenForo_Application::isRegistered('tmsIndependentExport')){
			$templateTitles = array();
			foreach($templates as $template) $templateTitles[] = $template['title'];

			$modifications = $this->_getTmsModModel()->getAllModificationsInStyle($styleId);
			foreach($modifications as $modification) $templateTitles[] = $modification['template_title'];

			$templates = $this->getEffectiveTemplatesByTitles($templateTitles, $styleId);
			foreach($templates as &$template) $template['template'] = $template['template_final'];
		}

		return $templates;
	}

	/**
	 * Gets the effective template in a style by its title. This includes all
	 * template information and the map ID.
	 *
	 * @param string $title
	 * @param integer $styleId
	 *
	 * @return array|false Effective template info.
	 */
	public function getEffectiveTemplateByTitle($title, $styleId)
	{
		$template = parent::getEffectiveTemplateByTitle($title, $styleId);

		if(!$template)
		{
			return $template;
		}

		if (self::$modifyTemplate)
		{
			$compiler = new TMS_Template_Compiler($template['template']);
			$modified = $compiler->modifyAndParse($title, $styleId, $template['template_id']);
			$template['template_final'] = $modified['template_final'];
			$template['template_modifications'] = serialize($modified['template_modifications']);
			if (!is_null($modified['template_parsed'])) {
				$template['template_parsed'] = serialize($modified['template_parsed']);
			}
		}
		elseif (self::$fetchFinalTemplate)
		{
			$template += $this->getTemplateFinalByTitle($title, $styleId);
		}
		else
		{
			$template['template_final'] = null;
			$template['template_modifications'] = null;
		}

		return $template;
	}

	public function getTemplateFinalByTitle($title, $styleId)
	{
		return $this->_getDb()->fetchRow('
            SELECT template_map.template_final, template_map.template_modifications
            FROM xf_template_map AS template_map
            WHERE template_map.title = ? AND template_map.style_id = ?
        ', array($title, $styleId));
	}

	public function getNamedTemplatesInStyleTreeWithChildren(array $titles, $styleId = 0)
	{
		if (!$titles) {
			return array();
		}

		$styleIds = $this->_getStyleModel()->getAllChildStyleIds($styleId);
		$styleIds[] = $styleId;

		return $this->fetchAllKeyed('
            SELECT template.*, template_map.template_map_id
            FROM xf_template AS template
            INNER JOIN xf_template_map AS template_map ON
				(template.template_id = template_map.template_id)
            WHERE template.title IN (' . $this->_getDb()->quote($titles) . ')
                AND template_map.style_id  IN (' . $this->_getDb()->quote($styleIds) . ')
        ', 'template_map_id');
	}

	public function getEffectiveTemplatesToRebuild()
	{
		return $this->_getDb()->fetchAll('
            SELECT template_map.template_map_id,
                template_map.style_id AS map_style_id,
                template.*
            FROM xf_template_map AS template_map
            INNER JOIN xf_template AS template ON
                (template_map.template_id = template.template_id)
            WHERE ISNULL(template_map.template_final)
            ORDER BY template_map.title
        ');
	}

	public function compileAllTemplates($maxExecution = 0, $startStyle = 0, $startTemplate = 0)
	{
		self::$modifyTemplate = true;

		if (!XenForo_Application::getOptions()->get('tmsSafeRebuild')) {
			return parent::compileAllTemplates($maxExecution, $startStyle, $startTemplate);
		}

		$db = $this->_getDb();

		$styles = $this->getModelFromCache('XenForo_Model_Style')->getAllStyles();
		$styleIds = array_merge(array(0), array_keys($styles));
		sort($styleIds);

		$lastStyle = 0;
		$startTime = microtime(true);
		$complete = true;

		XenForo_Db::beginTransaction($db);

		if ($startStyle == 0 && $startTemplate == 0) {
			$db->query('DELETE FROM xf_template_compiled');
			if (XenForo_Application::get('options')->templateFiles) {
				XenForo_Template_FileHandler::delete(null, null, null);
			}
		}

		$lastTemplate = 0;

		$templates = $this->getEffectiveTemplatesToRebuild();
		foreach ($templates AS $key => $template)
		{


			$this->compileAndInsertParsedTemplate(
				$template['template_map_id'],
				unserialize($template['template_parsed']),
				$template['title'],
				isset($template['map_style_id']) ? $template['map_style_id'] : $template['style_id']
			);

			if ($maxExecution && (microtime(true) - $startTime) > $maxExecution) {
				$complete = false;
				break;
			}
		}

		if ($complete) {
			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		}

		XenForo_Db::commit($db);

		if ($complete) {
			return true;
		}
		else
		{
			return array($lastStyle, $lastTemplate + 1);
		}
	}

	public function compileAndInsertParsedTemplate($templateMapId, $parsedTemplate, $title, $compileStyleId, $doDbWrite = null)
	{
		self::$modifyTemplate = self::$modifyTemplate || XenForo_Application::getOptions()->get('tmsFullCompile');

		$template = $this->getEffectiveTemplateByTitle($title, $compileStyleId);
		parent::compileAndInsertParsedTemplate($templateMapId, unserialize($template['template_parsed']), $title, $compileStyleId, $doDbWrite);

		$this->_db->update(
			'xf_template_map',
			array('template_final' => $template['template_final'], 'template_modifications' => $template['template_modifications']),
			'style_id = ' . $this->_db->quote($compileStyleId) . ' AND title = ' . $this->_db->quote($title)
		);
	}

	/**
	 * Gets the add-on model object.
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
	 * Gets the modification model object.
	 *
	 * @return TMS_Model_Modification
	 */
	protected function _getTmsModModel()
	{
		return $this->getModelFromCache('TMS_Model_Modification');
	}

	public function fetchAllKeyed($sql, $key, $bind = array(), $nullPrefix = '')
	{
		if (strpos($sql, 'AS template_map') !== false) {
			$sql = str_replace('SELECT ', 'SELECT template_map.template_final, template_map.template_modifications, ', $sql);
		}

		if (strpos($sql, 'AS map') !== false) {
			$sql = str_replace('SELECT ', 'SELECT map.template_final, map.template_modifications, ', $sql);
		}

		return parent::fetchAllKeyed($sql, $key, $bind, $nullPrefix);
	}

}