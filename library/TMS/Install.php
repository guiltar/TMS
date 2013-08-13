<?php

/**
 *
 */
class TMS_Install
{
	private static $_instance;

	protected $_db;

	public static final function getInstance()
	{
		if (!self::$_instance) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	/**
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected function _getDb()
	{
		if ($this->_db === null) {
			$this->_db = XenForo_Application::getDb();
		}

		return $this->_db;
	}

	public static function build($existingAddOn, $addOnData)
	{
		if (XenForo_Application::$versionId < 1020070) {
			throw new XenForo_Exception('This version of TMS requires XenForo 1.2 or higher.');
		}

		$startVersion = 1;
		$endVersion = $addOnData['version_id'];

		if ($existingAddOn) {
			$startVersion = $existingAddOn['version_id'] + 1;
		}

		$install = self::getInstance();

		$db = XenForo_Application::getDb();
		XenForo_Db::beginTransaction($db);

		for ($i = $startVersion; $i <= $endVersion; $i++)
		{
			$method = '_installVersion' . $i;

			if (method_exists($install, $method) === false) {
				continue;
			}

			$install->$method();
		}

		XenForo_Db::commit($db);
	}

	protected function _installVersion4()
	{
		$db = $this->_getDb();

		if(!$db->fetchRow('SHOW COLUMNS FROM xf_template_modification WHERE Field = ?', 'style_id'))
		{
			$db->query("
	            ALTER TABLE xf_template_modification
	            ADD style_id int( 10 ) unsigned NOT NULL default '0'
	        ");
		}
	}

	protected function _installVersion5()
	{
		$db = $this->_getDb();

		if($db->query("SHOW TABLES LIKE 'tms_modification'")->rowCount() > 0)
		{
			/* @var $modificationModel TMS_Model_TemplateModification */
			$modificationModel = XenForo_Model::create('XenForo_Model_TemplateModification');

			$tmsMods = $db->fetchAll("
				SELECT *
				FROM tms_modification
	        ");

			foreach($tmsMods as $tmsMod)
			{
				$mod = $modificationModel->getModificationByKey('tms_'.$tmsMod['title']);
				$modificationId = !empty($mod['modification_id']) ? $mod['modification_id'] : null;

				$dwData = array(
					'template' => $tmsMod['template_title'],
					'modification_key' => 'tms_'.$tmsMod['title'],
					'description' => $tmsMod['description'],
					'action' => $tmsMod['modification_type'],
					'find' => ($tmsMod['modification_type'] == 'callback') ? '#^.*$#si' : $tmsMod['search_value'],
					'replace' => ($tmsMod['modification_type'] == 'callback') ? $tmsMod['callback_class'].'::'.$tmsMod['callback_method'] : $tmsMod['replace_value'],
					'execution_order' => $tmsMod['execute_order'],
					'enabled' => $tmsMod['active'],
					'addon_id' => $tmsMod['addon_id'],
					'style_id' => $tmsMod['style_id'],
				);


				/* @var $dw XenForo_DataWriter_TemplateModification */
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_TemplateModification', XenForo_DataWriter::ERROR_SILENT);
				if ($modificationId)
				{
					$dw->setExistingData($modificationId);
					$dw->bulkSet($dwData);
				}
				else
				{
					$dw->bulkSet($dwData);
				}

				$dw->save();
			}
		}


		if($db->fetchRow('SHOW COLUMNS FROM xf_template_map WHERE Field = ?', 'template_final'))
			$db->query("ALTER TABLE xf_template_map DROP template_final");

		if($db->fetchRow('SHOW COLUMNS FROM xf_template_map WHERE Field = ?', 'template_modifications'))
			$db->query("ALTER TABLE xf_template_map DROP template_modifications");

		//$db->query("DROP TABLE tms_modification");
	}

	public static function destroy()
	{
		$lastUninstallStep = 3;

		$uninstall = self::getInstance();

		$db = XenForo_Application::getDb();
		XenForo_Db::beginTransaction($db);

		for ($i = 1; $i <= $lastUninstallStep; $i++)
		{
			$method = '_uninstallStep' . $i;

			if (method_exists($uninstall, $method) === false) {
				continue;
			}

			$uninstall->$method();
		}

		XenForo_Db::commit($db);
	}

	protected function _uninstallStep1()
	{
		$db = $this->_getDb();

		if($db->fetchRow('SHOW COLUMNS FROM xf_template_modification WHERE Field = ?', 'style_id'))
		{
			$db->delete('xf_template_modification', 'style_id > 0');

			$db->query("
			    ALTER TABLE xf_template_modification
			    DROP style_id
	        ");
		}
	}
}