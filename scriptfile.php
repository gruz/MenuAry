<?php
/**
 * @package     MenuAry
 *
 * @copyright   Copyright (C) www.gruz.org.ua All rights reversed.
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

// No direct access to this file
defined('_JEXEC') or die;

if (!class_exists('ScriptAry'))
{
	include dirname(__FILE__) . '/scriptary.php';
}

/**
 * Script file
 */
class PlgsystemMenuaryInstallerScript extends ScriptAry {

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @return void
	 */
	function postflight($type, $parent)
	{
		$manifest = $parent->getParent()->getManifest();

		if ($type != 'uninstall' && !$this->_installAllowed($manifest))
		{
			return false;
		}

		// Remove AjaxHelpAry
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('extension_id', 'name', 'params', 'element'));
		$query->from('#__extensions');
		$query->where($db->quoteName('element') . ' = ' . $db->quote('ajaxhelpary'));
		$query->where($db->quoteName('folder') . ' = ' . $db->quote('ajax'));
		$db->setQuery($query);
		$row = $db->loadAssoc();

		if (!empty($row))
		{
			$installer = new JInstaller();
			$res = $installer->uninstall('plugin', $row['extension_id']);

			if ($res)
			{
				$msg = '<b style="color:green">' . JText::sprintf('COM_INSTALLER_UNINSTALL_SUCCESS', $row['name']) . '</b>';
			}
			else
			{
				$msg = '<b style="color:red">' . JText::sprintf('COM_INSTALLER_UNINSTALL_ERROR', $row['name']) . '</b>';
			}

			$this->messages[] = $msg;
		}

		parent::postflight($type, $parent, $publishPlugin = true);
	}
}
?>

