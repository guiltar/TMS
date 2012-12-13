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
		if (XenForo_Application::$versionId < 1010270) {
			throw new XenForo_Exception(new XenForo_Phrase('tms_requires_minimum_xenforo_version', array('version' => '1.1.2')));
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

	protected function _installVersion1()
	{
		$db = $this->_getDb();

		$db->query("
            ALTER TABLE xf_template ADD template_modified MEDIUMTEXT NULL  COMMENT 'TMS' AFTER template ,
            ADD template_modifications MEDIUMBLOB NULL COMMENT 'TMS' AFTER template_modified
        ");

		$db->query("
            CREATE TABLE tms_modification (
            modification_id int( 10 ) unsigned NOT NULL AUTO_INCREMENT ,
            title varchar( 25 ) NOT NULL ,
            style_id int( 10 ) unsigned NOT NULL default '0',
            template_title varchar( 25 ) NOT NULL ,
            execute_order int( 10 ) unsigned NOT NULL ,
            description text NOT NULL ,
            search_string MEDIUMTEXT NULL ,
            replace_string MEDIUMTEXT NULL ,
            addon_id varchar( 25 ) NOT NULL ,
            version_id int( 10 ) unsigned NOT NULL default '0',
            version_string varchar( 30 ) NOT NULL ,
            active tinyint( 3 ) unsigned NOT NULL default '1',
            PRIMARY KEY ( modification_id ) ,
            UNIQUE KEY title ( title , style_id )
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8;
        ");
	}

	protected function _installVersion2()
	{
		$db = $this->_getDb();

		$db->query("
            ALTER TABLE tms_modification
            CHANGE search_string search_value MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
            CHANGE replace_string replace_value MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
            CHANGE title title VARCHAR( 75 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
            ADD modification_type VARCHAR( 20 ) NOT NULL DEFAULT 'str_replace' AFTER description ,
            ADD callback_class VARCHAR( 75 ) NOT NULL DEFAULT '' AFTER modification_type ,
            ADD callback_method VARCHAR( 50 ) NOT NULL DEFAULT '' AFTER callback_class
        ");

		$db->query("
            ALTER TABLE xf_template
            DROP template_modified,
            DROP template_modifications
        ");

		$db->query("
           ALTER TABLE xf_template_map ADD template_final MEDIUMTEXT NULL  COMMENT 'TMS',
           ADD template_modifications MEDIUMBLOB NULL COMMENT 'TMS' AFTER template_final
        ");
	}

	protected function _installVersion3()
	{
		$db = $this->_getDb();

		$db->query("
            ALTER TABLE tms_modification
            CHANGE template_title template_title VARCHAR( 75 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
        ");
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

		$db->query("
		    ALTER TABLE xf_template_map
		    DROP template_final,
		    DROP template_modifications
        ");

		$db->query("DROP TABLE tms_modification");
	}
}