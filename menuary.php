<?php
/* -------------------------------------------
Component: plg_MenuAry
Author: AryGroup
Email: arygroup@gmail.com
Copyright: Copyright (C) 2012 AryGroup. All Rights Reserved.
License: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
---------------------------------------------*/
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
jimport('joomla.plugin.plugin');
//jimport( 'joomla.html.parameter' );
jimport( 'gjfields.helper.plugin' );
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

if (!class_exists('JPluginGJFields')) {
	if (!JFactory::getApplication()->isAdmin()) {return true;}
	JFactory::getApplication()->enqueueMessage('Strange, but missing GJFields library for <span style="color:black;">'.__FILE__.'</span><br> The library should be installed together with the extension... Anyway, reinstall it: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>', 'error');
} else if (!method_exists('JPluginGJFields', '_preparePluginHasBeenSavedOrAppliedFlag') ) {
	if (!JFactory::getApplication()->isAdmin()) {return true;}
	JFactory::getApplication()->enqueueMessage('Install the latest GJFields plugin version <span style="color:black;">'.__FILE__.'</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>', 'error');
}
else {
class plgSystemMenuary extends JPluginGJFields  {

	function __construct(&$subject, $config) {
		parent::__construct($subject, $config);
		$this->_preparePluginHasBeenSavedOrAppliedFlag ();
	}

	private function _prepareParams() {
		if ($this->_forceGoOut()) { return; }

		$this->getGroupParams('{menugroup');// Get variable fields params parsed in a nice way, stored to $this->pparams

		$menu_names_used = array(); // Used to check duplicated menu names
		$this->ruleUniqIDs = array();

		// Create menues for each enabled and non-duplicated group of rules
		foreach ($this->pparams as $k=>$group_of_rules) {
			$this->ruleUniqIDs[] = $group_of_rules['__ruleUniqID'];
			if ($group_of_rules['ruleEnabled'] != 1) {
				unset($this->pparams[$k]);
				continue;
			}
			$this->pparams[$k]['articles_number'] = (int) $this->pparams[$k]['articles_number'];

			if ($group_of_rules['target'] == 'root') {
				//$this->pparams[$k]['menuname_alias'] = 'menuary-'.JFilterOutput::stringURLSafe($group_of_rules['menuname']);
				$this->pparams[$k]['menuname_alias'] = JFilterOutput::stringURLSafe($group_of_rules['menuname']);
				$this->pparams[$k]['menuname_alias'] = JString::substr($this->pparams[$k]['menuname_alias'],0,23);
			}
			else {
				$row = JTable::getInstance('menu');
				$row->load($group_of_rules['target_menu_item']);
				$this->pparams[$k]['menuname_alias'] = $row->menutype ;
			}
			$group_of_rules['menuname_alias'] = $this->pparams[$k]['menuname_alias'];

			if ($group_of_rules['target'] == 'root' && in_array($group_of_rules['menuname'],$menu_names_used)) {
				$app= JFactory::getApplication();
				$messages = $app->getMessageQueue();
				$msg = JText::sprintf('PLG_MENUARY_MENU_NAME_DUPLICATED',$group_of_rules['menuname']. '('.$group_of_rules['menuname_alias'].')',$group_of_rules['{menugroup'][0],$k+1);
				$msg_exists = false;
				foreach ($messages as $i=>$v) {
					if ($v['message'] == $msg) {
						$msg_exists = true;
					}
				}
				if (!$msg_exists) {
					JFactory::getApplication()->enqueueMessage($msg, 'warning');
				}
				unset($this->pparams[$k]);
			}
			else {
				if ($group_of_rules['target'] == 'root') {
					$menu_names_used[] = $group_of_rules['menuname'];
				}
				$db = JFactory::getDBO();
				//GET ALL THE CATEGORY DATA
				//$query = "SELECT `id`,`parent_id`,`title`,`alias`,`published`,`access`,`language` FROM `#__categories` WHERE `extension` = 'com_content' ORDER BY `lft` ASC";
				$query = $db->getQuery(true);
				$query
					//->select(array('id','parent_id','title','alias','published','access','language', 'level','lft','rgt'))
					->select(array('id','parent_id','title','alias','published','access','language', 'level'))
					->from('`#__categories`')
					->where('`extension` = "com_content"')
					//->order($this->pparams[$k]['category_order'])
					;
				if (!empty($this->pparams[$k]['categories'])) {
					$query
						->where('`id` IN('.implode(',',$this->pparams[$k]['categories']).')');
				}

				$db->setQuery($query);

				$categories = $db->loadAssocList('id');

				$category_table = JTable::getInstance( 'category' );
				$this->pparams[$k]['categories_to_menu'] = array();
				foreach ($categories as $i=>$cat) {
					if (!isset($this->pparams[$k]['categories_to_menu'][$i])) {
						//$this->pparams[$k]['categories_to_menu'][$i] = $cat;
						$children_cats = $category_table->getTree($i);
						$children_cats[0]->hide_category = false;
						if ($this->pparams[$k]['hide_top_category'] == '1') {
							$children_cats[0]->hide_category = true;
						}
						foreach ($children_cats as $j=>$child_cat) {
							if (!isset($child_cat->hide_category)) { $child_cat->hide_category = false; }
							$this->pparams[$k]['categories_to_menu'][$child_cat->id] = $child_cat;
							unset($children_cats[$j]);
						}
					}
					unset($categories[$i]);
				}
				unset ($category_table);
			}

		}
	}

	/**
	 * Adds a badge to the menu items created and contorlled by this plugin
	 *
	 * The fuction is taken from Ganty particle
	 *
	 * @author Gruz <arygroup@gmail.com>
	 */
	function _addMenuaryBadge() {
		$document = JFactory::getDocument();
		$type   = $document->getType();

		$app = JFactory::getApplication();
		$option = $app->input->getString('option');
		$view   = $app->input->getString('view');
		$task   = $app->input->getString('task');

		if (in_array($option, array('com_menus')) && ($view == 'items') && !$task && $type == 'html') {
			//$body = preg_replace_callback('/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU', array($this, '_appendHtml'), $app->getBody());
			//$app->setBody($body);
		}



		if (($option == 'com_menus') && ($view == 'items') && $type == 'html') {
			$this->_prepareParams();
			$ruleUniqIDs = array();
			$db	= JFactory::getDBO();
			foreach ($this->pparams as $k=>$params) {
				$ruleUniqIDs[] = $db->q($params['__ruleUniqID']);
			}
			if (empty($ruleUniqIDs)) {return;}
			$query = $db->getQuery(true);
			$query->select('itemid');
			$query->from('#__menuary');


			$query->where('ruleUniqID IN ('.implode(',',$ruleUniqIDs).')');

			$db->setQuery($query);
			$this->menuitems = $db->loadColumn();
			if (sizeof($this->menuitems) > 0) {

				$body = $app->getBody();
				$title = 'MenuAry';
				$body = preg_replace_callback('/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU', function($matches) use ($title) {
					return $this->_appendHtml($matches, $title);
				}, $body);
				$app->setBody($body);
			}
		}
	}

	/**
	 * @param array $matches
	 * @return string
	 */
	private function _appendHtml(array $matches, $content = 'MenuAry')
	{
		$html = $matches[0];
		if (strpos($matches[2], 'task=item.edit')) {
			$uri = new JUri($matches[2]);
			$id = (int) $uri->getVar('id');

			if ($id && in_array($uri->getVar('option'), array('com_menus')) && (in_array($id, $this->menuitems))) {
				$html = $matches[1] . $uri . $matches[3] . $matches[4] . $matches[5];
				$html .= ' <span   onMouseOver="this.style.color=\'#00F\'" onMouseOut="this.style.color=\'#000\'" class="hasTip icon-power-cord" style="    cursor: help;"  title="'.JText::_('PLG_MENUARY_MENUITEM_TOOLTIP').'"></span>';
			}
		}
		return $html;

	}


	private function _forceGoOut ($context = null) {
		if (JFactory::getUser()->guest) {return true;}
		if (!JFactory::getApplication()->isAdmin()) {return true;}
		$jinput = JFactory::getApplication()->input;
		if ($jinput->get('option',null) == 'com_dump') { return true; }
		if ($jinput->get('option',null) == 'com_jce') { return true; }
		if (!empty($context) && !in_array($context, array('com_content.article','com_categories.category'))) { return true; }

		return false;

	}


	function _object_to_array($obj) {
		 if(is_object($obj)) $obj = (array) $obj;
		 if(is_array($obj)) {
			  $new = array();
			  foreach($obj as $key => $val) {
					if ('categories_to_menu' === $key) {
						//$new[$key] = (array)$val;
						$temp = new stdClass;
						foreach ($val as $g=>$h) {
							$temp->$g = $h;
						}
						$new[$key] = (array)$temp;
					} else {
						$new[$key] = $this->_object_to_array($val);
					}
			  }
		 }
		 else $new = $obj;
		 return $new;
	}

	 function _serialize () {
		$obj = new stdClass;
		foreach($this as $k=>$v) {
			if (!in_array($k,array('params','_subject' ))) {
				$obj->$k = $v;
			}
		}
		 JFile::write($this->ajaxTmpFile,base64_encode(serialize($obj)));
	 }
	 function _unserialize () {
		$tmp_file_contents = JFile::read($this->ajaxTmpFile);
		if( $obj = unserialize(base64_decode($tmp_file_contents)) ) {
			foreach ($obj as $k=>$v) {
				$this->$k = $v;
			}
		}
		$this->ajaxMessages = array();
//dump ($this,'this unserialized');
	 }
	 function _unserialize_DEL () {
		$tmp_file_contents = JFile::read($this->ajaxTmpFile);
		if( $obj = json_decode(base64_decode($tmp_file_contents),true) ) {
			foreach ($obj as $k=>$v) {
				if (in_array($k,array('pparams', 'ajaxCall', 'categories_to_menu','category_helper','categories_to_menu_current','category_id_level_tie'))) {
					$v = $this->_object_to_array($v);
				}
				$this->$k = $v;
			}
		}
		$this->ajaxMessages = array();
	 }

	 function _ajaxRun ($uniq) {
		$this->ajaxTmpFile = JFactory::getApplication()->getCfg('tmp_path').'/'.$this->plg_full_name.'_'.$uniq;
		$jinput = JFactory::getApplication()->input;
		if ($jinput->get('restart',0) == '1') { JFile::delete($this->ajaxTmpFile); }

		if (!file_exists($this->ajaxTmpFile)) {
			$this->_prepareParams();
			$this->ajaxCall = array();
			$this->ajaxCall['runlevel'] = 'doAll';
			$this->ajaxCall['stage'] = 'start';
			$files = JFolder::files(JFactory::getApplication()->getCfg('tmp_path'),$this->plg_full_name.'_*',false,true);
			JFile::delete($files);
		}
		if(file_exists($this->ajaxTmpFile)) { // Not the first run
			$this->_unserialize();
		}
		$this->isAjax = true;
		$this->timeStart = time(true);
//dump ($this->ajaxCall,'$this->ajaxCall in');
		$this->ajaxCall = $this->_doAll();
		if ($this->ajaxCall['runlevel'] !== '##done##') {
			$this->_serialize();
//dump ($this->ajaxCall,'$this->ajaxCall out');
			return '';
		}
		else {
			JFile::delete($this->ajaxTmpFile);
			return $this->ajaxCall['runlevel'];
		}
	 }

	/*
	 * Some $this->ajaxCall convention.
	 * $this->ajaxCall
	 * 	runlevel - flag to got to needed function when ajax repeating calls. So allow to know what places to skip.
	 * 		false - not ajax call
	 * 		doAll
	 * 		_regenerateMenu
	 * 		doAllCategories
	 * 		doAllArticles
	 * 		##done## - completed
	 * 	stage - flag to skip uneeded parts inside a runlevel (i.e. do not find and sort categories each time)
	 * 	current_category_id - self explaining
	 *
	 * */


	function _doAll() {
		if (empty($this->pparams)) {return;}
//dump ($this->ajaxCall,'$this->ajaxCall');
		if ($this->isAjax) {
			if ($this->ajaxCall['runlevel'] != 'doAll') { goto RunParametersCycle;}
			//if ($this->ajaxCall['stage'] == 'params_initialized') { goto RunParametersCycle;}
			if ($this->ajaxCall['stage'] == 'param_handling') { goto RunParametersCycle;}
			if ($this->ajaxCall['stage'] == 'all_categories_done') { goto all_categories_done;}
		}
		$this->_logMsg(JText::_('PLG_MENUARY_AJAX_INITIALIZING'));
		$this->_removeOrphans();

		//INCREASE AVAILABLE MEMORY JUST IN CASE
		$this->_setSystemLimits();

		foreach ($this->pparams as $k=>$v) { // Create menuary menus for the enabled group of rules
			$this->_checkMenuExistsAndCreateIfNeeded($this->pparams[$k]);
		}
		$this->rebuild = false;

		$this->_logMsg('...'.JText::_('PLG_MENUARY_AJAX_DONE'),true);
		if ($this->isAjax) {
			$this->ajaxCall['runlevel'] = 'doAll';
			$this->ajaxCall['stage'] = 'param_handling';
			if($this->_ajaxReturn()) {return $this->ajaxCall; }
		}
		//REGENERATE MENU IF REQUESTED
RunParametersCycle:
		foreach ($this->pparams as $k=>$params) {
			// I set the flag is after regenerating menu rebuild is a must
			// If updating a a menu rebuild may not be needed.  But to determine if lft-rgt-level changed we have to load the menu entry before storeing the updated one.
			// This would make us to load doznes of menu items to check and maybe still rebuild.  So I arrived at a decision to rebuild when regenerating/updating a menu
			// anyway. The flag is needed to prevent ->_saveMenuItem from loading menu items for each menu item updated.
			$this->rebuild = true;
			if ($this->isAjax) {
				if (!empty($this->ajaxCall['param_key'])) {
					if ($k != $this->ajaxCall['param_key'] ) {continue;}
				}
				$this->ajaxCall['param_key'] = $k;
			}
			if ($this->isAjax && $this->ajaxCall['runlevel'] == 'doAll') {
				$this->_logMsg('<br>'.JText::_('PLG_MENUARY_AJAX_RUNNING_RULE_START').' <b>'.$params['{menugroup'][0].'</b> ('.$params['__ruleUniqID'].')');
				unset($this->ajaxCall['current_category_id']);
				unset($this->ajaxCall['current_article_id']);
			}
			else if (!$this->isAjax){
				$this->_logMsg('<br>'.JText::_('PLG_MENUARY_AJAX_RUNNING_RULE_START').' <b>'.$params['{menugroup'][0].'</b> ('.$params['__ruleUniqID'].')');
			}
			if((int)$params['regeneratemenu'] != 0 ) { // If the rule groups is set not to regenarate menu, then go away
				if ($params['articles_number'] > 0) {
					$params['regeneratemenu'] = 2;
				}
				if (!$this->isAjax) {
					$this->_regenerateMenu ($params);
				}
				else {
					if ($this->ajaxCall['runlevel'] == 'doAll') {
						$this->ajaxCall['runlevel'] = 'regenerateMenu' ;
					}
					$ajaxRes = $this->_regenerateMenu ($this->pparams[$k]);
					if (!empty($ajaxRes)) {
						if($this->_ajaxReturn()) {return $ajaxRes; }
						else { $this->ajaxCall = $ajaxRes;}
					}
					else { $this->ajaxCall['runlevel'] = 'doAll'; }
				}
			}
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_RUNNING_RULE_END').' '.$params['{menugroup'][0].'('.$params['__ruleUniqID'].')');
			if ($this->isAjax && $this->ajaxCall['runlevel'] == 'doAll') {
				$next_key = $this->_getNextKey($array = $this->pparams , $current_key = $k);
				//$next_key = next(array_keys($this->pparams));

				if ($next_key && $k != $next_key) { // If there is a next item, then return ajax
					$this->ajaxCall['runlevel'] = 'doAll';
					$this->ajaxCall['stage'] = 'param_handling';
					$this->ajaxCall['param_key'] = $next_key;
					if($this->_ajaxReturn()) {return $this->ajaxCall; }
				}
			}
		}
		if ($this->isAjax) {
			$this->ajaxCall['runlevel'] = 'doAll';
			$this->ajaxCall['stage'] = 'all_categories_done';
			if($this->_ajaxReturn()) {return $this->ajaxCall; }
		}

all_categories_done:
		if ($this->isAjax && empty($this->ajaxCall['start_rebuild'])) {
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_STARTING_REBUILD').'...');
			$this->ajaxCall['runlevel'] = 'doAll';
			$this->ajaxCall['stage'] = 'all_categories_done';
			$this->ajaxCall['start_rebuild'] = 'start';
			return $this->ajaxCall;
		} else if (!$this->isAjax) {
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_STARTING_REBUILD').'...');
		}
		if ($this->rebuild) {
			$table = JTable::getInstance('menu');
			if (!$table->rebuild()) {
				JError::raiseError(500, 'MenuAry: '.$table->getError());
			}
		}
		$this->_logMsg(JText::_('PLG_MENUARY_AJAX_DONE'),true);
		$this->_logMsg('<br><b>'.JText::_('PLG_MENUARY_FINISHED').'</b>');
		if ($this->isAjax) {
			return array ('runlevel'=>'##done##');
		}
	}

	private function  _regenerateMenu (&$params) {
//dump ($this->ajaxCall,'$this->ajaxCall');
		if ($this->isAjax ) {
			if ($this->ajaxCall['runlevel'] == 'doAllCategories') { goto doAllCategories;}
			if ($this->ajaxCall['runlevel'] == 'doAllArticles') { goto doAllArticles;}
			if ($this->ajaxCall['runlevel'] == 'regenerateMenu') {
				$this->ajaxCall['runlevel'] = 'doAllCategories';
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_START'),false,2);
			}
			//~ if (isset($this->ajaxCall['categoriesDone']) && $this->ajaxCall['categoriesDone'] === false) {
			//~ }
			//~ else if (isset($this->ajaxCall['articlesDone']) && $this->ajaxCall['categoriesDone'] === false) {
				//~ $this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_START'),false,2);
			//~ }
		}
		else {
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_START'),false,2);
		}
doAllCategories:
		$ajaxRes = $this->_doAllCategories($params);

		if ($this->isAjax) {
			if (!empty($ajaxRes)) {
				if($this->_ajaxReturn()) {return $ajaxRes; }
				else { $this->ajaxCall = $ajaxRes;}
			}
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_END'),false,2);
			$this->ajaxCall['runlevel'] = 'doAllArticles';
			if($this->_ajaxReturn()) {return $this->ajaxCall; }
		} else {
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_END'),false,2);
		}

doAllArticles:
		if ($params['show_articles'] == "1") {
			if ($this->isAjax && empty ($this->ajaxCall['current_article_id'])) {
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_START'),false,2);
			}
			else if(!$this->isAjax){
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_START'),false,2);
			}

			$ajaxRes = $this->_doAllArticles($params);
			//$this->_msg(JText::_('PLG_MENUARY_MENU_REGENERATED').' <b>'.$params['{menugroup'][0].'</b> (unique id: '.$params['__ruleUniqID'].')');

			if ($this->isAjax) {
				if (!empty($ajaxRes)) {
					if($this->_ajaxReturn()) {return $ajaxRes; }
					else { $this->ajaxCall = $ajaxRes;}
				}
			}
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_END'),false,2);
		}
	}

	/**
	 * Gets all joomla categories and creates appropriate menu items for all categories
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	array	$params	The options of the plugin (one group of rules)
	 * @return	void
	 */
	private function _doAllCategories(&$params) {
		if ($this->isAjax ) {
			if (empty($this->ajaxCall['current_category_id'])) {
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_PREPARATIONS').'...',false,3);
			}
			else {
				goto CategoriesCycle;
			}
		}
		else {
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_PREPARATIONS').'...',false,3);
		}

		$jinput = JFactory::getApplication()->input;
		$db = JFactory::getDBO();
		// If we have to regenerate a whole menu, then remove
		// the existing one from both - #__menu and #__menuary tables


		if ($params['regeneratemenu'] == 2) {
				//Build the query
				$query = 'DELETE t1.* FROM #__menu AS t1
								LEFT JOIN #__menuary AS t2
								ON t1.id = t2.itemid
								WHERE t2.ruleUniqID ='.$db->quote($params['__ruleUniqID']);

				$db->setQuery($query);
				$db->query();
				$this->_removeOrphans();
		}

		$this->categories_to_menu_current = $params['categories_to_menu'];

		// Sort categories {
		if ($params['com_categories.category_order'] != 'default') {
			$params['category_order_exploded'] = explode (' ', $params['com_categories.category_order']);
			//if ($params['category_order_exploded'][0] == 'order') {break;} // If we use ordering, we don't sort additionally
			// So below we can order oby title, is other options do not need sorting
			$categories_to_menu_ordered = array();
			foreach ($this->categories_to_menu_current as $k=>$v) { // Build a convinient tree of categories for later convinient use
				$categories_to_menu_ordered[$v->parent_id][$k] = $v; // here is not ordered yet, but the array is used to store ordered categories
			}

			foreach ($categories_to_menu_ordered as $parent_id=>$children) {
				if ($params['category_order_exploded'][0] == 'title') {
					usort($children, function($a, $b) {  return strcmp($a->title, $b->title); }); // Sort menu items by title
				}
				if ($params['category_order_exploded'][1] == 'desc') {
					$children = array_reverse($children);
				}
				$categories_to_menu_ordered[$parent_id] = $children;
			}
			$this->categories_to_menu_current  = array();
			foreach ($categories_to_menu_ordered as $parent_id=>$sorted_categories) {
				foreach ($sorted_categories as $category) {
					$this->categories_to_menu_current[$category->id] = $category;
				}
			}
			unset($categories_to_menu_ordered);
			$already_sorted = true;
		}
		// Sort categories }
		$this->category_helper = array ();
		$this->category_id_level_tie = array();//Reference between category id and category level for easier access

		if ($this->isAjax) {
		}
		$this->catCounter = 0;
		$this->_logMsg(JText::_('PLG_MENUARY_AJAX_DONE'),true);
		$this->_logMsg(JText::_('PLG_MENUARY_ELEMENS_NUMBER_TO_BE_HANDLED').': '.count ($this->categories_to_menu_current),false,3);

CategoriesCycle:

//~ $counter = 0;
		while (true) {
//~ $counter++;
//~ if ($counter>100) {echo 'BAD'.PHP_EOL;break; }
			foreach($this->categories_to_menu_current as $catid=>$category) {
//~ if ($counter>95) {echo 'BAD in'.PHP_EOL;break; }
				if ($this->isAjax && !empty($this->ajaxCall['current_category_id'] )) { // Skip till the needed category to continue
					if ($catid != $this->ajaxCall['current_category_id'] ) {continue;}
				}
				while (true) { // Reset category level. I don't remember quite well, but it seems we reset category->level to make it relative.
					if (isset($this->categories_to_menu_current[$category->parent_id])) { // Find the top level parent category
						$catid = $category->parent_id;
						$category = $this->categories_to_menu_current[$category->parent_id];
						continue;
					}
					else { // if there is no a parent category for the current category
						if (isset($this->category_id_level_tie[$category->parent_id])) {
							$category->level = $this->category_id_level_tie[$category->parent_id]+1;
						}
						else {
							$category->level = 1;
						}
						$this->category_id_level_tie[$catid] = $category->level;
					}
					$this->category_helper[$catid]['level'] = $category->level;
					break;
				}
				if ($category->hide_category ) { // If rule is set to hide the top category, then go out. The rule is updated with the ->category_helper array at this point
					unset($this->categories_to_menu_current[$catid]);
					$this->_logMsg(JText::_('PLG_MENUARY_AJAX_MENUITEM_SKIPPED').': '.$category->title.' ( catid = '.$category->id.' )',false,3);
					if ($this->isAjax) {
					}
					//if (empty($this->categories_to_menu_current)) { break; }
					continue;
				}
				//SET THE MENU ID & SAVE IN ASSETS TABLE
				$Itemid = $this->_getMenuItemId('com_categories.category', $catid,$params);
				$isNew = false;
				if($Itemid == 0) { // It has to be a new item, then prepare an empty record for it in #__menu and get menu Itemid
					$isNew = true;
					$Itemid = $this->_insertMenuItem('com_categories.category', $catid,$params);
				}

				$this->category_helper[$catid]['itemid'] = $Itemid;

				//GET THE PARENT CATEGORY MENU ID
				$category->parent_menu_item_id = $this->_getMenuItemId('com_categories.category',$category->parent_id,$params);
				if($category->parent_menu_item_id == '0') {
					if($params['target']=='root' ) {
						$category->parent_menu_item_id = $this->_getRoot();
					}
					else {
						$category->parent_menu_item_id = $params['target_menu_item'];
					}
				}

				//CHECK ALIAS
				$category->alias = $this->_checkAliasAndMakeOriginalIfNeeded($category->alias, $Itemid, $category->parent_menu_item_id);

				if ($params['category_link_type'] !== 'none') {
					$category->link = $this->_getCategoryLink($category,$params);
					$component = 'component';
				} else {
					$category->link = '';
					$component = 'heading';
				}

				//BUILD THE DATA ARRAY
				$array = array(
					'menutype' => $params['menuname_alias'],
					'title' => $category->title,
					'alias' => $category->alias,
					'link' => $category->link,
					'type' => $component,
					'published' => $category->published,
					'parent_id' => $category->parent_menu_item_id,
					'level' =>  $category->level,
					'component_id' => 22,
					'access' =>  $category->access,
					'language' =>  $category->language,
					'id' => $Itemid,
					'contentid' => $category->id
				);

				$this->_saveMenuItem($context='com_categories.category' /*values:category|article*/, $array /*array of data to be stored*/, $params /*current rule params*/);

				if ($isNew) {
					$txt = JText::_('PLG_MENUARY_AJAX_MENUITEM_ADDED');
				} else {
					$txt = JText::_('PLG_MENUARY_AJAX_MENUITEM_UPDATED');
				}
				$this->catCounter++;
				$this->_logMsg($txt.': ['.$this->catCounter.'] '.$category->title.' ( catid = '.$category->id.' ; Itemid = '.$Itemid.' )',false,4);
				if ($this->isAjax ) {
					$next_key = $this->_getNextKey($array = $this->categories_to_menu_current , $current_key = $catid);

					if ($params['com_categories.category_order'] == 'default' && $next_key === false) {
						reset($this->categories_to_menu_current);
						$current = current($this->categories_to_menu_current);
						$next_key = $current->id;
					}

					 //~ $next_key = next(array_keys($this->categories_to_menu_current));
					unset($this->categories_to_menu_current[$catid]);
					if ($next_key && $catid != $next_key ) {
						$this->ajaxCall['runlevel'] = 'doAllCategories';
						$this->ajaxCall['current_category_id'] = $next_key;
						if ($this->_ajaxReturn()) {	return $this->ajaxCall; }
					}
				} else {
					unset($this->categories_to_menu_current[$catid]);
				}
			}

			if (empty($this->categories_to_menu_current) ) {
				break;
			}

		}
		$params['categories_used'] = $this->category_helper;
		if ($this->isAjax ) {
			return null;
		}
	}


	private function _doAllArticles(&$params) {
		//DON'T BOTHER RUNNING IF NO ARTICLES ARE TO BE INCLUDED
		if((int)$params['show_articles'] == '0') return false;

		if ($this->isAjax ) {
			if (empty($this->ajaxCall['current_article_id'])) {
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_PREPARATIONS').'...',false,3);
			}
			else {
				goto ArticlesCycle;
			}
		}
		else {
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_PREPARATIONS').'...',false,3);
		}

		//GET ALL THE ARTICLES
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		//$query = "SELECT `id`,`title`,`alias`, `access`, `state`, `catid`, `language` FROM `#__content`";
		$query
			->select(array('id','title','alias', 'access', 'state', 'catid', 'language', 'created'))
			->from('`#__content`');
		$used_catids = array_keys($params['categories_used']);
		if (!empty($used_catids)) {
			$query
				->where('`catid` IN('.implode(',',$used_catids).')');
		}
		if ($params['com_content.article_order'] !== 'default') {
			$query->order($params['com_content.article_order']);
		}
		if ($params['articles_number'] > 0 ) {
			$query->where('`state` = '.$db->q('1'));
			$query->setLimit($params['articles_number']);
		}
//dumpMessage (str_replace('#__',$db->getPrefix(),(string)$query));
		$db->setQuery($query);
		$this->articles_current = $db->loadAssocList('id');
		if ($this->isAjax) {
			$this->articles_current_keys = array_keys($this->articles_current);
		}
		$this->articleCounter = 0;
		$this->_logMsg(JText::_('PLG_MENUARY_AJAX_DONE'),true);
		$this->_logMsg(JText::_('PLG_MENUARY_ELEMENS_NUMBER_TO_BE_HANDLED').': '.count ($this->articles_current),false,3);

ArticlesCycle:

		foreach($this->articles_current as $articleId=>$article) {
			if ($this->isAjax && !empty($this->ajaxCall['current_article_id'] )) { // Skip till the needed category to continue
				if ($articleId != $this->ajaxCall['current_article_id'] ) {continue;}
			}
			//SET THE MENU ID & SAVE IN ASSETS TABLE
			$Itemid = $this->_getMenuItemId('com_content.article', $article['id'],$params);
			$isNew = false;
			if($Itemid == 0) {
				$isNew = true;
				$Itemid = $this->_insertMenuItem('com_content.article', $article['id'],$params);
			}

			//GET THE PARENT CATEGORY DATA
			$cat = $params['categories_used'][$article['catid']];
			//GET THE PARENT CATEGORY MENU ID
			//$article['parent_menuid'] = $this->_getMenuItemId(0,$article['catid']);
			//if($article['parent_menuid'] == '0') $article['parent_menuid'] = $this->_getRoot();
			$article['parent_menuid'] = '0';
			if(isset($cat['itemid'])) { // This would work quicker then else
				$article['parent_menuid'] = $cat['itemid'];
			}
			else { // Old way keep just in case, would work longer then the first IF option
				$article['parent_menuid'] = $this->_getMenuItemId('com_categories.category',$article['catid'],$params);
			}
			if($article['parent_menuid'] == '0')  {
				if($params['target']=='root' ) {
					$article['parent_menuid'] = $this->_getRoot();
				}
				else {
					$article['parent_menuid'] = $params['target_menu_item'];
				}
			}

			$article['level'] = $cat['level']+1;
			$article['link'] = 'index.php?option=com_content&view=article&id='.$article['id'];

			//CHECK ALIAS
			if (!isset($cat['itemid'])) {
				$cat['itemid'] = 1;
			}
			$article['alias'] = $this->_checkAliasAndMakeOriginalIfNeeded($article['alias'], $Itemid, $article['parent_menuid']);

			//BUILD THE DATA ARRAY
			$array = array(
				'id' => $Itemid,
				'menutype' => $params['menuname_alias'],
				'title' => $article['title'],
				'alias' => $article['alias'],
				'link' => $article['link'],
				'type' => 'component',
				'published' => $article['state'],
				'state' => $article['state'],
				'parent_id' => $article['parent_menuid'],
				'level' => $article['level'],
				'component_id' => 22,
				'access' => $article['access'],
				'language' => $article['language'],
				'contentid' => $article['id']
			);

			//$this->_setRebuildFlag($array,$Itemid);

			//UPDATE THE MENU TABLE
			// If regenerating a menu, the items are ordered before saving menu item. So I make it temporary 'default'
			// to prevent ->_saveMenuItem for trying to order items itself
			$tmp = $params['com_content.article_order'];
			$params['com_content.article_order'] = 'default';

			$this->_saveMenuItem($context='com_content.article' /*values:com_categories.category|com_content.article*/, $array /*array of data to be stored*/, $params /*current rule params*/);
			$params['com_content.article_order'] = $tmp;


			if ($isNew) {
				$txt = JText::_('PLG_MENUARY_AJAX_MENUITEM_ADDED');
			} else {
				$txt = JText::_('PLG_MENUARY_AJAX_MENUITEM_UPDATED');
			}
			$this->articleCounter++;
			$this->_logMsg($txt.': ['.$this->articleCounter.'] '.$article['title'].' ( article id  = '.$article['id'].' ; Itemid = '.$Itemid.' )',false,4);
			if ($this->isAjax ) {

				$next_key = $this->_getNextKey($array = $this->articles_current , $current_key = $articleId);

				//~ // I have to get next key in such a way, because next() fails.
				//~ $getNext = false;
				//~ $next_key = false;
				//~ foreach ($this->articles_current_keys as $k=>$v) {
					//~ if ($v == $articleId) { $getNext = true; continue; }
					//~ if ($getNext) {
						//~ $next_key = $v;
						//~ break;
					//~ }
				//~ }
				//unset($this->articles_current[$n]);
				if ($next_key && $articleId != $next_key ) {
					$this->ajaxCall['runlevel'] = 'doAllArticles';
					$this->ajaxCall['current_article_id'] = $next_key;
					if ($this->_ajaxReturn()) {	return $this->ajaxCall; }
				}
			}

			//~ $query = "UPDATE `#__menu` SET ";
			//~ foreach($array as $x=>$y) {
				//~ $query .= $db->quoteName($x).' = '.$db->quote($y);
				//~ if($x !== 'language') $query .= ',';
			//~ }
			//~ $query .= ' WHERE '.$db->quoteName('id').' = '.$db->quote($Itemid).' LIMIT 1';
			//~ $db->setQuery($query);
			//~ $db->query();
		}
		return null;
	}


	function _getNextKey ($array,$current_key) {

		$keys = array_keys($array);
		// I have to get next key in such a way, because next() fails.
		$getNext = false;
		$next_key = false;
		foreach ($keys as $k=>$v) {
			if ($v == $current_key) { $getNext = true; continue; }
			if ($getNext) {
				$next_key = $v;
				break;
			}
		}
		return $next_key;
	}

	function _logMsg ($text, $inline = false, $prepend = 0) {

		if ($prepend>0) {
			$text = str_repeat('&nbsp;',$prepend).$text;
		}
		if ($inline) {
			//echo $text;
		} else {
			$text =  '<br/>'.$text;
		}
		if ($this->isAjax) {
			echo $text;
			flush();
		}
		else {
			if (!isset($this->messages)) {
				$this->messages = array();
			}
			$this->messages[] = $text;
		}
	}

	function _ajaxReturn () {
		if ($this->paramGet( 'debug' ) && $this->paramGet( 'step' ) >0 ) {
			static $ajaxReturn =0 ;
			$ajaxReturn++;
			if ($ajaxReturn>=$this->paramGet( 'step' )) { return true; }
			return false;
		}

		$time_now = time(true);
		$execution_time = ($time_now - $this->timeStart);
		$max_execution_time = ini_get('max_execution_time') - 5;
		//if ($execution_time>$max_execution_time) {
		if ($execution_time>1) {
			return true;
		}
		else {
			return false;
		}

	}


	function onBeforeRender () {
//dumpMessage ('onBeforeRender');
		$jinput = JFactory::getApplication()->input;
		if ($jinput->get('option',null) == 'com_dump') { return true; }
		if ($this->checkIfNowIsCurrentPluginEditWindow()) {
			$isAjax = $this->paramGet( 'ajax' );
			if ($isAjax) {
				if (!$this->checkIfAPluginPublished ($plugin_group = 'ajax',$plugin_name = 'ajaxhelpary',$show_message = true)) {
					$isAjax = false;
					$doc = JFactory::getDocument();
					$doc->addScriptDeclaration('var force_no_ajax = true;');
				}
			}
		}

		$this->_addBehaviorToolTipIfNeeded();
		if ($this->_forceGoOut()) { return; }

		if (!$this->pluginHasBeenSavedOrApplied) { return; }
		$this->_prepareParams(); // So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled
		if (empty($this->pparams)) {return;}


		if (!isset($isAjax)) { $isAjax = $this->paramGet( 'ajax' ); }
		if ($isAjax) {
			$ajax_place = '';
			$url_ajax_plugin = JRoute::_(JURI::base().'?option=com_ajax&plugin=ajaxhelpary&format=raw&'.JSession::getFormToken().'=1&plg_name='.$this->plg_name.'&plg_type='.$this->plg_type.'&function=_ajaxRun&uniq='.uniqid());
			if ($this->paramGet( 'debug' )) {
				$url_ajax_plugin .= '&debug=1';
				$ajax_place .= $url_ajax_plugin;
				$ajax_place = '<a id="clear" class="btn btn-error">Clear</a>'.$ajax_place;
				$ajax_place = '<a id="continue" class="btn btn-warning">Continue</a>'.$ajax_place;
			}
			$ajax_place .= '<b>'.JText::_('PLG_MENUARY_WAIT_PLEASE').'</b><br><div id="'.$this->plg_full_name.'" >'.'</div>';
			$this->_msg($ajax_place,'notice');
			$doc = JFactory::getDocument();
			$doc->addScriptDeclaration('var menuary_ajax_url = "'.$url_ajax_plugin.'";');
			$doc->addScriptDeclaration('var ajax_helpary_message = "'.JText::_('PLG_MENUARY_AJAX_ERROR').'";');
			$path_to_assets = str_replace (JPATH_ROOT,'',dirname(__FILE__));
			$doc->addScript($path_to_assets.'/js/ajax.js?time='.time());
			$doc->addStyleSheet($path_to_assets.'/css/styles.css');
			if ($this->paramGet( 'debug' )) {
				$doc->addScriptDeclaration('var menuarydebug = true;');
			}

		} else {
			$this->isAjax = false;
			$this->_doAll();
			if (!empty($this->messages)) { $this->_msg (implode(PHP_EOL,$this->messages),'notice'); }

		}

	}

	function onAfterRender () {
//dumpMessage ('onAfterRender');
		$this->_addMenuaryBadge(); // Add icons to the menu page to show menu items generated by MenuAry and still tied to MenuAry
	}
	function _addBehaviorToolTipIfNeeded() {
		$document = JFactory::getDocument();
		$type   = $document->getType();

		$app = JFactory::getApplication();
		$option = $app->input->getString('option');
		$view   = $app->input->getString('view');
		$task   = $app->input->getString('task');

		if (in_array($option, array('com_menus')) && ($view == 'items') && !$task && $type == 'html') {
			JHTML::_('behavior.tooltip');
		}
	}



	/**
	 * Save a menu item
	 *
	 * Here how adding a menu item works.
	 * I get an empty menu table instance $table = JTable::getInstance('menu');
	 * Next I determine where my current menu item to add - to the end of the menu items, or to prepend menu items, it's stored in  $order
	 * I set where the future menu item should be added $table->setLocation( $array('parent_id'), $order );
	 * Note, I set the parent Id of the curren menu item as the 1st parameter and last-child or first-child as the second parameter
	 * After that I bind $array data to the $table object and save
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$context		Possible values: com_categories.category or com_content.article
	 * @param	array		$array	Object to be stored to the JTableMenu #__menu
	 * @param	array		$params	Current group of rules
	 * @return	void
	 */
	private function _saveMenuItem($context='com_categories.category' /*values:com_categories.category|com_content.article*/, $array /*array of data to be stored*/, &$params /*current rule params*/){
		$table = JTable::getInstance('menu');
		/*##mygruz20160104043733 {
		It was:
		$table->bind( $array );
		$table->check();
		if (!$table->store($array)) {
		// TODO Замінити три рядки нижче на $table->save 20160104035452 {
		// }
		It became:*/
		/*##mygruz20160104043733 } */
		// Here I must save it twice. I don't know why, but when saving once for a new item, it refuses to save at least parent_id.
		if ($array['id'] == 0 || !isset($array['id'])) {
			if (!$table->save($array)) {
				JError::raiseError(500, 'MenuAry: '.$table->getError());
			}
			$array['id'] = $table->id;
		}
		else if ($this->rebuild !== true ){ // Here I store menu item info before save to compare with the saved item and to determine if rebuild is needed. Rebuild it time consuming, so I try not to run it in vain
			$table->load ($array['id']);
			$lft = $table->lft;
			$rgt = $table->rgt;
			$level = $table->level;
		}
		// Specify where to insert the new node.
		if ($params[$context.'_order'] != 'default') {
			$order_param_exploded = explode (' ', $params[$context.'_order']);
			if (in_array($order_param_exploded[0] ,array ('title', 'created', 'modified', 'publish_up', 'publish_down'))) {
				$tree = $table->getTree($array['parent_id']);
				$current_menuitems_is_already_among_menu_items = false;
				foreach ($tree as $k=>$v) {
					if ($v->id == $array['id']) {
						$current_menuitems_is_already_among_menu_items = true;
						break;
					}
				}
				$needed_level = $tree[0]->level+1;
				if ($current_menuitems_is_already_among_menu_items === false ) {
					$object = 	(object) $array;
					$object->link = 'id='.$object->contentid;
					$object->level = $needed_level;
					$tree[] = $object;
				}
				unset($tree[0]);
				foreach ($tree as $k=>$menuitem) {
					if ($needed_level != $menuitem->level) {
						unset($tree[$k]);
						continue;
					}
					if ($array['id'] == $menuitem->id) { // Menu getTree() contains old title, while we have new in the $array. So need to use the new one to later sort menu items
						$menuitem->title = $array['title'];
					}
					if ($order_param_exploded[0] != 'title') {
						parse_str($menuitem->link, $get_array);
						$menuitem->contentid = $get_array['id'];
						$db = JFactory::getDBO();
						$query = $db->getQuery(true);
						$query
							->select(array($order_param_exploded[0],'catid'))
							->from('`#__content`')
							->where('`id` = '.$db->q($get_array['id']));
						$db->setQuery($query);
						$res = $db->loadAssoc();
						$menuitem->{$order_param_exploded[0]} = $res[$order_param_exploded[0]];
						$menuitem->catid = $res['catid'];

						if ($params['articles_number'] > 0 && $context == 'com_content.article') {
							$query = $db->getQuery(true);
							$query
								->select('id')
								->from('`#__menuary`');
							$query->where($db->quoteName('ruleUniqID')." = ".$db->quote($params['__ruleUniqID']));
							$query->where($db->quoteName('context')." = ".$db->quote($context));
							$query->where($db->quoteName('Itemid')." = ".$db->quote($menuitem->id));

							$db->setQuery($query);
							$res = $db->loadResult();
							if (!empty($res)) {
								$menuitem->isMenuAryMenuItem = $res;
							}
						}

					}
					$menuitem->sortField = $menuitem->{$order_param_exploded[0]};
					$tree[$k] = $menuitem;
				}
				usort($tree, function($a, $b) {  return strcmp($a->sortField, $b->sortField); }); // Sort menu items by title

				if ($order_param_exploded[1] == 'desc') {
					$tree = array_reverse($tree);
				}
				if ($params['articles_number'] > 0 && $context == 'com_content.article' ) {
					$counter = 0;
					foreach ($tree as $k=>$menuitem) {
						if (!empty($menuitem->isMenuAryMenuItem)) {
							$counter++;
							if ($counter>$params['articles_number']) {
								$object = new stdClass;
								$object->id = $menuitem->contentid;
								$object->catid = $menuitem->catid;
								$this->onContentBeforeDelete($context, $object);
							}
						}
					}
				}
				foreach ($tree as $k=>$menuitem) {
					if ($array['id'] == $menuitem->id) {
						$key = $k-1;
						if ($key<0) {
							$table->setLocation( $array['parent_id'], 'first-child' );

						} else {
							$table->setLocation( $tree[$key]->id, 'after' );
						}
						break;
					}
				}
			}
			else {
				$order = 'last-child';
				if ($order_param_exploded[1] == 'desc') {
					$order = 'first-child';
				}
				$table->setLocation( $array['parent_id'], $order );
			}
		} else {
			$order = 'last-child';
			$table->setLocation( $array['parent_id'], $order );
		}
		if (!$table->save($array)) {
			JError::raiseError(500, 'MenuAry: '.$table->getError());
		}
		else {
			// get acontent associations
			while (true) {
				if ($array['language'] == '*') { break;}
				$associations = $this->_getAssociations($context,$array['contentid']);
				if ($associations === false ) { break; }

				// find menu items according to content associations
				$menuItemAssociations = array($array['language']=>$table->id);
				foreach ($associations as $lang=>$contentid) {
					$Itemid = $this->_getMenuItemId($context,$contentid,$params, $ignoreRuleUniqId = true);
					if ($Itemid == 0) { continue;}
					$menuItemAssociations[$lang] = $Itemid;

				}

				//if (count($menuItemAssociations)<2) { break;}
				// Deleting old association for these items
				$db = JFactory::getDBO();
				$query = $db->getQuery(true)
					->delete('#__associations')
					->where('context=' . $db->quote('com_menus.item'))
					->where('id IN (' . implode(',', $menuItemAssociations) . ')');
				$db->setQuery($query);
				try {
					$db->execute();
				}
				catch (RuntimeException $e) {
					$this->setError($e->getMessage());
				}

				if (count($menuItemAssociations)<2) { break;}
				// Adding new association for these items
				$key = md5(json_encode($menuItemAssociations));
				$query->clear()
					->insert('#__associations');

				foreach ($menuItemAssociations as $id)	{
					$query->values(((int) $id) . ',' . $db->quote('com_menus.item') . ',' . $db->quote($key));
				}

				$db->setQuery($query);
				try	{
					$db->execute();
				}
				catch (RuntimeException $e)	{
					$this->setError($e->getMessage());
				}
				break;
			}


		}
		if (isset ($lft) && ($table->lft ==  $lft && $table->rgt == $rgt && $table->level == $level)) {
			$this->rebuild = false;
		}
		else {
			$this->rebuild = true;
		}
		$Itemid = (int) $table->id;
		unset($table);

		return $Itemid;
	}

	private function _getAssociationsContext ($context) {
		//'com_content.article','com_categories.category'
		$associationsContext = explode ('.',$context);
		return $associationsContext[0].'.item';
	}



	private function _checkMenuExistsAndCreateIfNeeded($params) {
		if ($this->_forceGoOut()) { return; }
		$jinput = JFactory::getApplication()->input;

		if ($params['target']!='root' ) { return true; }

		$db = JFactory::getDBO();
		$query = "SELECT `id` FROM `#__menu_types` WHERE `menutype` = ".$db->quote($params['menuname_alias'])." LIMIT 1";

		$db->setQuery($query);
		$res = $db->loadAssoc();
		if(!isset($res['id'])) {
			$query = "INSERT INTO ".$db->quoteName('#__menu_types')
				." SET "
				.$db->quoteName('menutype')." = ".$db->quote($params['menuname_alias']).","
				.$db->quoteName('description')." = ".$db->quote($params['menuname']).","
				.$db->quoteName('title')."=".$db->quote($params['menuname']);
			$db->setQuery($query);
			$db->query();
			//$_SESSION[$this->plg_full_name]['regen'] = '1';
			//$_SESSION[$this->plg_full_name]['regen_menu'][$params['menuname_alias']] = '1';
		}
		return true;
	}

	/**
	 * Get menu Item id for the passed category or article id
	 *
	 * Searches #__menuary for the $id of an article or a category ($context).
	 * If found - returns the Itemid, no - returns zero which means we don't have
	 * such a menu item yet.
	 * It's very hard to get this information from #__menu, is it doesn't store
	 * an article or a category id separately.
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$context	com_content.article or com_categories.category
	 * @param	int	$id	category or article id
	 * @return	int			element id from #__menu which is the Itemid in joomla URLs
	 */
	private function _getMenuItemId($context = 'com_categories.category', $id = 0, $params, $ignoreRuleUniqId = false) {  // $id is the category or article ID
		if((int)$id === 0) return 0;
		// Get a db connection.
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);

		$query->select($db->quoteName('itemid'));
		$query->from('#__menuary AS t1')
				->join('LEFT',
					'#__menu AS t2
					ON t1.itemid = t2.id
					');
		if (!$ignoreRuleUniqId) {
			$query->where($db->quoteName('t1.ruleUniqID')." = ".$db->quote($params['__ruleUniqID']));
		}
		$query->where($db->quoteName('t1.context')." = ".$db->quote($context));
		if ($params['target']=='root' ) {
			$query->where($db->quoteName('t2.menutype')." = ".$db->quote($params['menuname_alias']));
		}
		if($id > 0) {
			$query->where($db->quoteName('content_id')." = ".$db->quote($id));
		}
		//$query->order('ordering ASC');
		// Reset the query using our newly populated query object.

		$db->setQuery($query);

		// Load the results as a list of stdClass objects.
		$results = $db->loadAssoc();

		if (isset($results['itemid'])) {
			return $results['itemid'];
		}
		return 0;
	}

	private function _getRoot() {
		//RETURN THE BASE ROOT MENU ITEM ID
		$menu_table = JTable::getInstance( 'menu' );
		$menu_root_id = $menu_table->getRootId();
		return $menu_root_id;
	}

	/**
	 * Creates a placeholder for a menu item in #__menu and records the asset row to #__menuary
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	int	$context	com_categories.category or com_content.article
	 * @param	int	$id	category or article id
	 * @param	object	$params	rules params
	 * @return	int			Itemid of the inserted element
	 */
	private function _insertMenuItem($context='com_categories.category', $id=0, $params) {  // $id is the category or article ID
		$id = (int)$id;

		//CREATE TEMPORARY MENU ITEM PLACEHOLDER
		$data = array('id' => '', 'menutype' => $params['menuname_alias'], 'alias'=>microtime(), 'title'=>time());
		$row = JTable::getInstance('menu');
		if (!$row->save($data)) {
				  JError::raiseError(500, 'MenuAry: '.$row->getError());
		}

		$tempid = (int) $row->id;

		//SAVE TO THE MENUARY MENU REL TABLE
		$db = JFactory::getDBO();
		$query = "INSERT INTO ".$db->quoteName('#__menuary')." SET ".
			$db->quoteName('itemid')." = ".$db->quote($tempid).", ".
			$db->quoteName('content_id')." = ".$db->quote($id).", ".
			$db->quoteName('ruleUniqID')." = ".$db->quote($params['__ruleUniqID']).", ".
			$db->quoteName('context')." = ".$db->quote($context);
		$db->setquery($query);
		$db->query();

		$this->rebuild = true;
		//RETURN THE NEW ID
		return $tempid;
	}


	/**
	 * Checks if passed alias already exsists for current menu and menu level
	 *
	 * If there is no such alias for current menu and level, then it returns
	 * the passed alias, otherwise it modifies the alias to make it unique
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$alias		aritcle ot category alias
	 * @param	int		$id			article or category menu_itemId in #__menu, may be zero which means a new menu item
	 * @param	int		$parent_id	id of the parent menu
	 * @return	void			Nothing
	 */
	private function _checkAliasAndMakeOriginalIfNeeded($alias, $id, $parent_id) {
		$db = JFactory::getDBO();

		//QUICK LOOP TO CHECK IF ALIAS EXISTS - MAYBE REPLACE WITH DO...WHILE
		$origalias = $alias;
		$i  = 0;
		while(true) {
			$i++;
			$query = "SELECT ".$db->quoteName('id')." FROM #__menu WHERE ".
				$db->quoteName('alias')." = ".$db->quote($alias)." AND ".
				$db->quoteName('parent_id')." = ".$db->quote($parent_id)." AND ".
				$db->quoteName('id')." != ".$db->quote($id)." LIMIT 1";
			$db->setQuery($query);
			$row = $db->loadColumn();
			if (!isset($row[0])) return $alias;
			else $alias = $origalias.'-'.$i;
		}
	}


	private function _setSystemLimits() {
		if(!isset($this->mem)) {
			//WE'RE GOING TO RUN - SET A HIGHER TIME LIMIT & MEMORY LIMIT JUST IN CASE
			if(!ini_get('safe_mode')){
				set_time_limit(60*30); //30 MINUTES
				ini_set('memory_limit', '256M');
			}
			$this->mem = 1;
		}
	}

	/**
	 * Cleans up #__menu_menuary just in case there are orphans
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @return	void
	 */
	private function _removeOrphans () {
		$db = JFactory::getDBO();
		//Build the query
		$query = 'DELETE t1.* FROM #__menuary AS t1
				LEFT JOIN #__menu AS t2 ON t1.itemid = t2.id
				LEFT JOIN #__categories AS t3 ON t1.content_id = t3.id
				LEFT JOIN #__content AS t4 ON t1.content_id = t4.id
				WHERE
					t2.id IS NULL
					OR
					(t3.id IS NULL AND t1.context = '.$db->q('com_categories.category').')
					OR
					(t4.id IS NULL AND t1.context = '.$db->q('com_content.article').')
				';
		if (!empty($this->ruleUniqIDs)) {
			$ruleUniqIDs = array ();
			foreach ($this->ruleUniqIDs as $k=>$v) {
				$ruleUniqIDs[] = $db->q($v);
			}
			$ruleUniqIDs = implode (',',$ruleUniqIDs);
			$query .= ' OR t1.ruleUniqID NOT IN ('.$ruleUniqIDs.')';
		}
		/* This query cannot be implemented using modern joomla engine
		$query
				->select("; DELETE t1.*") // use fake select to fool joomla database driver
				//->append('t1.* FROM #__menuary AS t1')
				->from('#__menuary AS t1')
				->join('LEFT',
					'#__menu AS t2
					ON t1.itemid = t2.id
						')
				->where('t2.menutype ='.$db->quote($params['menuname_alias']));
		*/
		$db->setQuery($query);
		$db->query();

		// Remove orphan language associations
		$query = 'DELETE t1.* FROM #__associations AS t1
		LEFT JOIN #__menu as t2
		ON t1.id = t2.id
		WHERE t1.context = '.$db->q('com_menus.item').'
		AND t2.id IS NULL';
		$db->setQuery($query);
		$db->query();
	}

	/**
	 * Returns category link depending on global, category and/or plugin settings
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	obect	$category	Category object loaded usually from DB
	 * @param	array	$params		Current group of rules
	 * @return	text			Link to category
	 */
	function _getCategoryLink($category,$params) {
		if ($params['category_link_type'] == '_:default') {
			$parameter = new JRegistry;
			$parameter->loadString($category->params, 'JSON');
			jimport('joomla.application.component.helper');
			$this->global_category_layout = JComponentHelper::getParams('com_content')->get('category_layout');// can be _:default or _:blog. _:default means list
			$link_type = $parameter->get('category_layout',$this->global_category_layout);
		}
		else {
			$link_type = $params['category_link_type'];
		}
		//PREPARE LINK
		if ($link_type != '_:blog') {
			$category->link = 'index.php?option=com_content&view=category&id='.$category->id;
		}
		else {
			$category->link = 'index.php?option=com_content&view=category&layout=blog&id='.$category->id;
		}
		return $category->link;
	}





	/**
	 * Check if the current article or the category is amonng the categories selected in the ParamsGroup
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @return	bool			true if the content element belongs to a category which has to handled by the current ParamsGroup
	 */
	private function _hasToBeHandledByTheParamsGroup($contentElement,$context,$params) {
		if ($context == 'com_categories.category' && in_array($contentElement->id,array_keys($params['categories_to_menu'])) ) {
			return true;
		}
		elseif ($context == 'com_content.article' ) {
			if (in_array($contentElement->catid,array_keys($params['categories_to_menu'])))  {
				return true;
			}
		}
		return false;
	}


	public function onContentBeforeDelete($context, $contentElement) 	{
//dump ($contentElement,$context.' | onContentAfterDelete' );
		if ($this->_forceGoOut($context)) { return; }

		$this->_prepareParams(); // So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled
		foreach ($this->pparams as $key=>$params) {
			if (!$this->_hasToBeHandledByTheParamsGroup($contentElement,$context,$params)) { continue; }
			$db = JFactory::getDBO();
			$query = $db->getQuery(true);
			$query
				->select('itemid')
				->from('`#__menuary`')
				->where('`context` = '.$db->quote($context))
				->where('`content_id` = '.$db->quote($contentElement->id))
				->where('`ruleUniqID` = '.$db->quote($params['__ruleUniqID']))
				;
			$db->setQuery($query);
			$res= $db->loadColumn();
			if (!empty($res)) {
				foreach ($res as $k=>$v) {
					$table = JTable::getInstance( 'menu' );
					$table->delete($v);
				}
				$this->_removeOrphans();
				if (!$table->rebuild()) {
					JError::raiseError(500, 'MenuAry: '.$table->getError());
				}
				$msg = JText::_('PLG_MENUARY_MENUITEM_DELETED');
				$msgtype = 'notice';
				$this->_msg($msg.'. <b>Itemid</b>: '.implode(',',$res),$msgtype);
				if ($params['articles_number'] > 0 && $context == 'com_content.article') {
					if ($params['show_articles'] == "1") {
						$this->_regenerateMenu ($params);
					}
				} else {
					if (!$table->rebuild()) {
						JError::raiseError(500, 'MenuAry: '.$table->getError());
					}
				}
			}
		}
		if (!empty($this->messages)) { $this->_msg (implode(PHP_EOL,$this->messages),'notice'); }
	}

	public function onContentChangeState($context, $ids, $state)	{
		if ($this->_forceGoOut($context)) { return; }
//dump ($ids,$context.' | '.$state .' | onContentChangeState' );

		$ids = (array) $ids; // just in case
		$this->_prepareParams(); // So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled

		$rebuild = false;
		foreach ($this->pparams as $k=>$params) {
			$db = JFactory::getDBO();
			$query = $db->getQuery(true);
			$query
				->select('itemid')
				->from('`#__menuary`')
				->where('`context` = '.$db->quote($context))
				->where('`content_id` IN (\''.implode("','",$ids).'\')')
				;
			$query->where('`ruleUniqID` = '.$db->q($params['__ruleUniqID']));
			$db->setQuery($query);
			$res= $db->loadColumn();

			if (count($res) > 0) {
				$table = JTable::getInstance( 'menu' );
				$table->publish ($res,$state);
				$rebuild = true;

				$this->_msg(JText::_('PLG_MENUARY_MENUITEM_STATE_UPDATED').': '.count($res));
				if ($params['articles_number'] > 0 && $context == 'com_content.article' ) {
					if ($params['show_articles'] == "1") {
						$params['regeneratemenu'] = 2;
						$this->_regenerateMenu ($params);
					}
				}
			}
		}
		if ($rebuild) {
			$table = JTable::getInstance( 'menu' );
			if (!$table->rebuild()) {
				JError::raiseError(500, 'MenuAry: '.$table->getError());
			}
		}
		if (!empty($this->messages)) { $this->_msg (implode(PHP_EOL,$this->messages),'notice'); }
	}


	/**
	 * Name or short description
	 *
	 * Full description (multiline)
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	type	$name	Description
	 * @return	type			Description
	 */
	function _getAssociations($context,$id) {
		$context = explode ('.',$context);
//'com_content.article','com_categories.category'
		$extension = $context[0];
		$context = $extension.'.item';
		$tablename = '#__'.end(explode ('_',$extension));

		// This would work, but gives an array with extra info
		//$associations = JLanguageAssociations::getAssociations($extension, $tablename, $context, $id);
//~ SELECT
	//~ t2.id, t3.language
//~ FROM `fyo97_associations` as t1
//~ INNER JOIN `fyo97_associations` as t2
//~ ON t1.key = t2.key
//~ INNER JOIN `fyo97_content` as t3
//~ ON t3.id = t2.id
//~ WHERE
//~ t1.context = 'com_content.item'
//~ AND t2.context = 'com_content.item'
//~ AND t1.id = 250 AND t2.id != 250
				$db = JFactory::getDBO();
				$query = $db->getQuery(true);
				$query
					//->select(array('id','parent_id','title','alias','published','access','language', 'level','lft','rgt'))
					->select(array('t2.id', 't3.language'))
					->from($db->qn('#__associations','t1'))
					 ->join('INNER', $db->quoteName('#__associations', 't2') . ' ON (' . $db->quoteName('t1.key') . ' = ' . $db->quoteName('t2.key') . ')')
					->join('INNER', $db->quoteName($tablename, 't3') . ' ON (' . $db->quoteName('t3.id') . ' = ' . $db->quoteName('t2.id') . ')')
					->where($db->qn('t1.context') .' = '. $db->q($context))
					->where($db->qn('t2.context') .' = '. $db->q($context))
					->where($db->qn('t1.id') .' = '. $db->q($id))
					->where($db->qn('t2.id') .' != '. $db->q($id))
					;
				$db->setQuery($query);
		$associations = $db->loadAssocList('language','id');
		if (!empty($associations)) { return $associations; }
		return false;
	}

	public function onContentBeforeSave($context, $article, $isNew) {
		$this->previous_article = JTable::getInstance('content');
		$this->previous_article->load($article->id);
		$this->previous_state = $this->previous_article->state;
	}

	public function onContentAfterSave($context, $contentElement, $isNew)	{
		// 'com_content.article'
		if ($this->_forceGoOut($context)) { return; }
//dump ($contentElement,$context.' | onContentAfterSave' );

		$this->rebuild = false;
		$itemIdsToBeClearedFromMenuAry = array();
		$this->_prepareParams(); // So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled
		$this->_removeOrphans();

		foreach ($this->pparams as $key=>$params) {
			$Itemid = $this->_getMenuItemId($context, $contentElement->id,$params);

			if (!$this->_hasToBeHandledByTheParamsGroup($contentElement,$context,$params)) {
				if ($Itemid != 0) {
					$obj = new stdClass;
					$obj->Itemid = $Itemid;
					$obj->context = $context;
					$obj->content_id = $contentElement->id;
					$obj->ruleUniqID = $params['__ruleUniqID'];
					$obj->paramkey = $key;
					$this->itemIdsToBeClearedFromMenuAry[] = $obj;
				}
				continue;
			}
			if ($Itemid == 0) { $isNew = true; }
			if ($isNew) { $this->rebuild = true; }
			// find parent Itemid for the current $contentElement
			$parent_Itemid = $params['target_menu_item'];
			$doNothing = false;
			switch ($context) {
				case 'com_content.article':
					$current_contentElement = $contentElement;
					$parent_category_id = $current_contentElement->catid;
					break;
				case 'com_categories.category':
					$current_contentElement = $params['categories_to_menu'][$contentElement->id]; // get menuary category object
					$current_contentElement->state = $current_contentElement->published;
					if ($current_contentElement->hide_category) { $doNothing = true; break; } // If current category ha
					$parent_category_id = $current_contentElement->parent_id;
					break;
				default :
					break;
			}
			if ($doNothing) { continue; }
			$current_contentElement->context = $context;
			$current_contentElement->Itemid = $Itemid;
			$path_to_be_created = array($current_contentElement); // We handle the situation which should not happen normally. Is there is an article/category which has to be added or updated, but it's parent category is not yet added as a menu item. Normally all parent categories should be added when saving the plugin.
			while (!empty($params['categories_to_menu'][$parent_category_id])) {
				$current_contentElement = $params['categories_to_menu'][$parent_category_id];
				$current_contentElement->context = 'com_categories.category';
				if ($current_contentElement->hide_category) { break; }
				$Itemid = $this->_getMenuItemId($current_contentElement->context, $current_contentElement->id,$params);
				$current_contentElement->Itemid = $Itemid;
				if ($Itemid == 0) {
					$parent_category_id = $current_contentElement->parent_id;
					$path_to_be_created[] = $current_contentElement;
				}
				else {
					$path_to_be_created[count($path_to_be_created)-1]->parent_Itemid = $Itemid;
					break;
				}
			}

			$path_to_be_created = array_reverse($path_to_be_created);
			foreach ($path_to_be_created as $key=>$path_object) {

				if ($params['articles_number'] > 0 && $path_object->context == 'com_content.article' ) {
					if ($isNew && $path_object->state != 1) { continue; } // If $params['articles_number'] > 0 and a non-published article is added, then don't care about the menu
					if ($this->previous_state != $path_object->state && ($path_object->state == 1 | $this->previous_state == 1)) {
						if ($params['show_articles'] == "1") {
							$params['regeneratemenu'] = 2;
							$this->_regenerateMenu ($params);
							continue;
						}
					}
				}
				if (!empty($path_object->parent_Itemid) && $path_object->parent_Itemid != 0)  {
					$parent_Itemid = $path_object->parent_Itemid;
				}
				$path_object->alias = $this->_checkAliasAndMakeOriginalIfNeeded($path_object->alias, $path_object->Itemid, $parent_Itemid);

				switch ($path_object->context) {
					case 'com_content.article':
						$path_object->link = 'index.php?option=com_content&view=article&id='.$path_object->id;
						break;
					case 'com_categories.category':
						$path_object->link = $this->_getCategoryLink($path_object,$params);
						break;
					default :
						break;
				}
				//BUILD THE DATA ARRAY
				$array = array(
					'menutype' => $params['menuname_alias'],
					'title' => $path_object->title,
					'alias' => $path_object->alias,
					'link' => $path_object->link,
					'type' => 'component',
					'published' => $path_object->state,
					'state' => $path_object->state,
					'parent_id' => $parent_Itemid,
					//'level' =>  isset($path_object->level)?$path_object->level:'',
					'component_id' => 22,
					'access' =>  $path_object->access,
					'language' =>  $path_object->language,
					'contentid' =>  $path_object->id,
					'id'=>$path_object->Itemid

				);

				$parent_Itemid = $this->_saveMenuItem($path_object->context /*values:category|article*/, $array /*array of data to be stored*/, $params /*current rule params*/);
				if ($isNew ) {
					$this->_createMenuAryRecord($context = $context, $Itemid = $parent_Itemid, $content_id = $path_object->id,$params );
				}
				if ($isNew ) {
					$msg = JText::_('PLG_MENUARY_MENUITEM_CREATED');
					$msgtype = 'notice';
				} else {
					$msg = JText::_('PLG_MENUARY_MENUITEM_UPDATED');
					$msgtype = 'message';
				}
				$this->_msg($msg.'. <b>Itemid</b>: '.$parent_Itemid,$msgtype);

			}
		}
		if (!empty($this->itemIdsToBeClearedFromMenuAry)) {
			foreach ($this->itemIdsToBeClearedFromMenuAry as $k=>$v) {
				$this->_removeMenuAndAryRecord($v);
				$menu_table = JTable::getInstance( 'menu' );
				$menu_table->delete ($v->Itemid); // It's strange, but is passing an array of values, then it removed ALL menu items.
				$this->_msg(JText::_('PLG_MENUARY_MENUITEM_DELETED').'. <b>Itemid</b>: '.$v->Itemid,'notice');

				// Regenerate menu if the items which is saved is NO MORE handled by the plugin
				$params = $this->pparams[$obj->paramkey];
				if ($params['articles_number'] > 0 && $context == 'com_content.article') {
					if ($params['show_articles'] == "1") {
						$this->_regenerateMenu ($params);
					}
				}

			}
		}
		if ($this->rebuild) {
			$table = JTable::getInstance('menu');
			if (!$table->rebuild()) {
				JError::raiseError(500, 'MenuAry: '.$table->getError());
			}
			$this->_removeOrphans();
		}
		if (!empty($this->messages)) { $this->_msg (implode(PHP_EOL,$this->messages),'notice'); }

	}

	private function _removeMenuAndAryRecord ($obj) {
		$db = JFactory::getDBO();
		$query = 'DELETE FROM #__menuary
			WHERE Itemid ='.$db->quote($obj->Itemid).
				' AND content_id ='.$db->quote($obj->content_id).
				' AND context ='.$db->quote($obj->context).
				' AND ruleUniqID ='.$db->quote($obj->ruleUniqID);
		$db->setQuery($query);
		$db->query();
	}

	private function _msg ($msg,$type = 'message') {

		JFactory::getApplication()->enqueueMessage('<small>'.JText::_('PLG_MENUARY').': '.$msg.'</small>', $type);
	}

	/**
	 * Created a menuary record for the curent article or a category
	 *
	 * Stories a row with the content elemtn (article or category) id and corresponding menu Itemid
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	type	$name	Description
	 * @return	type			Description
	 */
	function _createMenuAryRecord($context, $Itemid, $content_id,$params  ) {
		//SAVE TO THE MENUARY MENU REL TABLE
		$db = JFactory::getDBO();
		$query = "INSERT INTO ".$db->quoteName('#__menuary').
			" SET ".
			$db->quoteName('itemid')." = ".$db->quote($Itemid).", ".
			$db->quoteName('content_id')." = ".$db->quote($content_id).", ".
			$db->quoteName('context')." = ".$db->quote($context).','.
			$db->quoteName('ruleUniqID')." = ".$db->quote($params['__ruleUniqID']);
		$db->setquery($query);
		$db->query();
		$this->rebuild = true;
	}

	/**
	 * Enable joomla tooltips for menu view
	 *
	 * @author Gruz <arygroup@gmail.com>
	 */
	//~ function onBeforeRender() {
		//~ $document = JFactory::getDocument();
		//~ $type   = $document->getType();
//~
		//~ $app = JFactory::getApplication();
		//~ $option = $app->input->getString('option');
		//~ $view   = $app->input->getString('view');
		//~ $task   = $app->input->getString('task');
//~
		//~ if (in_array($option, array('com_menus')) && ($view == 'items') && !$task && $type == 'html') {
			//~ JHTML::_('behavior.tooltip');
		//~ }
	//~ }



}
}
