<?php


class TMS_DataWriter_TemplateModification extends XFCP_TMS_DataWriter_TemplateModification
{

	/**
	 * Gets the fields that are defined for the table. See parent for explanation.
	 *
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = parent::_getFields();
		$fields[$this->_modTableName]['style_id'] = array('type' => self::TYPE_UINT, 'default' => 0);
		return $fields;
	}

	/**
	 * Post-save handler.
	 */
	protected function _postSave()
	{
		$existingMaster = $this->_getTemplateModel()->getTemplateInStyleByTitle($this->get('template'));
		$existingEffective = $this->_getTemplateModel()->getEffectiveTemplateByTitle($this->get('template'), $this->get('style_id'));

		if($existingEffective['map_style_id'] != $existingEffective['style_id'])
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');

			if($existingEffective)
			{
				// only change the style ID of a newly inserted template
				$writer->set('style_id', $this->get('style_id'));
				$writer->set('addon_id', $existingEffective['addon_id']);
				$writer->set('title', $existingEffective['title']);
				$writer->set('template', "<xen:comment>tms</xen:comment>\n" . $existingEffective['template']);
			}

			$writer->preSave();
			$writer->save();
		}

		parent::_postSave();
	}
}