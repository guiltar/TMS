<?php

/**
 * Data writer for styles.
 *
 * @package XenForo_Style
 */
class TMS_DataWriter_Style extends XFCP_TMS_DataWriter_Style
{
	/**
	 * Internal post-save handler
	 */
	protected function _postSave()
	{
		if ($this->isInsert())
		{
			XenForo_Application::set('insertedStyleId', $this->get('style_id'));
		}

		parent::_postSave();
	}

	/**
	 * Internal post-delete handler.
	 */
	protected function _postDelete()
	{
		$db = $this->_db;
		$styleId = $this->get('style_id');
		$db->delete('tms_modification', 'style_id = ' . $db->quote($styleId));

		parent::_postDelete();
	}
}