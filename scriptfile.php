<?php
/**
 * @package     MenuAry
 *
 * @copyright   Copyright (C) www.gruz.org.ua All rights reversed.
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */



// No direct access to this file
defined('_JEXEC') or die('Restricted access');
if (!class_exists('ScriptAry')) { include dirname(__FILE__).'/scriptary.php';}

/**
 * Script file
 */
class plgsystemMenuaryInstallerScript extends ScriptAry {
	/**
	 * method to install the component
	 *
	 * @return void
	 */
	function install($parent) {
		// $parent is the class calling this method
		//$parent->getParent()->setRedirectURL('index.php?option=com_helloworld');
	}

	/**
	 * method to uninstall the component
	 *
	 * @return void
	 */
	function uninstall($parent) {
		// $parent is the class calling this method
		//echo '<p>' . JText::_('COM_HELLOWORLD_UNINSTALL_TEXT') . '</p>';
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent) {
		// $parent is the class calling this method
		//echo '<p>' . JText::_('COM_HELLOWORLD_UPDATE_TEXT') . '</p>';
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent) {
		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)
		//echo '<p>' . JText::_('COM_HELLOWORLD_PREFLIGHT_' . $type . '_TEXT') . '</p>';
	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @return void
	 */
	function postflight($type, $parent) {

		if ($type != 'uninstall') {
			$this->installExtensions($parent);
		}
		if ($type == 'install') {
			$db = JFactory::getDbo();

			// Unpublish native SEF
			$query = $db->getQuery(true);
			// Fields to update.
			$fields = array(
				$db->quoteName('enabled').'='.$db->Quote('1')
			);
			// Conditions for which records should be updated.
			$conditions = array(
				$db->quoteName('type').'='.$db->Quote('plugin'),
				$db->quoteName('folder').'='.$db->Quote('ajax'),
				$db->quoteName('element').'='.$db->Quote('ajaxhelpary')
			);
			$query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
			$db->setQuery($query);
			$db->execute();
		}

		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)
		//echo '<p>' . JText::_('COM_HELLOWORLD_POSTFLIGHT_' . $type . '_TEXT') . '</p>';
		if (!empty($this->messages)) {
			echo '<ul><li>'.implode('</li><li>',$this->messages).'</li></ul>';
		}
	}
	private function installExtensions ($parent) {
		jimport('joomla.filesystem.folder');
		jimport('joomla.installer.installer');

		JLoader::register('LanguagesModelInstalled', JPATH_ADMINISTRATOR.'/components/com_languages/models/installed.php');
		$lang = new LanguagesModelInstalled();
		$current_languages = $lang ->getData();
		$locales = array();
		foreach($current_languages as $lang) {
			$locales[]=$lang->language;
		}

		$extpath = dirname(__FILE__).'/extensions';
		if (!is_dir($extpath)) {
			return;
		}
		$folders = JFolder::folders ($extpath);
		foreach ($folders as $folder) {
			$folder_temp = explode('_',$folder,2);
			if (isset ($folder_temp[1])) {
				if (!in_array($folder_temp[0],$locales)) {
					continue;
				}
			}

			$installer = new JInstaller();
			if ($installer->install($extpath.'/'.$folder)) {
				$manifest = $installer->getManifest();
				$this->messages[] = JText::sprintf('COM_INSTALLER_INSTALL_SUCCESS','<b style="color:#0055BB;">['.$manifest->name.']<span style="color:green;">').'</span></b>';
			}
			else {
				$this->messages[] = '<span style="color:red;">'.$folder . ' '.JText::_('JERROR_AN_ERROR_HAS_OCCURRED') . '</span>';
			}
		}
	}

}
?>

