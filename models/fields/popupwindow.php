<?php
/**
 * @package     Menuary
 *
 * @copyright   Copyright (C) www.gruz.org.ua All rights reversed.
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

jimport('joomla.form.formfield');

class JFormFieldPopupWindow extends JFormField {

	protected $type = 'PopupWindow';

	public function getLabel() {
		 return;
	}
	public function getInput() {
		$db = JFactory::getDBO();
		//CHECK THE TABLE EXISTS
		$query = "SELECT `id` FROM `#__menuary` LIMIT 1";
		$db->setQuery($query);
		$db->loadAssoc();
		if($db->getErrorNum()) echo "<p style='top: 0; position: absolute; clear: both;background: #fcc;color:#f00;padding: 5px;'>".JText::_('PLG_MENUARY_NOT_INSTALLED')."</p>";


		$text_node = "
			<div id='blackout'></div>
			<div id='menuary_popup'>
				<div id='menuary_content'>
					".JText::_('PLG_MENUARY_POPUP_TEXT')."
				</div>
			</div>";
		$text_node = json_encode($text_node);
		$script = "
		window.addEvent('domready', function() {
				var _body = document.getElementsByTagName('body') [0];
				var _div = document.createElement('div');
				_div.innerHTML = ".$text_node.";
				_body.appendChild(_div);
				document.getElementById('menuary_popup').style.display = 'none';
				document.menuary_var_lang = Array();
				document.menuary_var_lang['PLG_MENUARY_MENU_NAME_DUPLICATED_JS'] = ".json_encode(JText::_('PLG_MENUARY_MENU_NAME_DUPLICATED_JS'))."
		});
		";
		$doc = JFactory::getDocument();
		$doc->addStyleSheet(JURI::root().'/plugins/system/menuary/css/styles.css');
		$doc->addScriptDeclaration($script);
		$doc->addScript(JURI::root().'/plugins/system/menuary/js/popup.js?time='.microtime());

	}

}

