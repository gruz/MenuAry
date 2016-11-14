<?php
/**
 * Description
 *
 * @package    MenuAry
 *
 * @author     Gruz <arygroup@gmail.com>
 * @copyright  Copyleft (є) 2016 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

// ~ jimport( 'joomla.html.parameter' );
jimport('gjfields.helper.plugin');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

$latest_gjfields_needed_version = '1.1.24';
$error_msg = 'Install the latest GJFields plugin version <span style="color:black;">'
	. __FILE__ . '</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';

$isOk = true;

while (true)
{
	$isOk = false;

	if (!class_exists('JPluginGJFields'))
	{
		$error_msg = 'Strange, but missing GJFields library for <span style="color:black;">'
			. __FILE__ . '</span><br> The library should be installed together with the extension... Anyway, reinstall it:
			<a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
		break;
	}

	$gjfields_version = file_get_contents(JPATH_ROOT . '/libraries/gjfields/gjfields.xml');
	preg_match('~<version>(.*)</version>~Ui', $gjfields_version, $gjfields_version);
	$gjfields_version = $gjfields_version[1];

	if (version_compare($gjfields_version, $latest_gjfields_needed_version, '<'))
	{
		break;
	}

	$isOk = true;
	break;
}

if (!$isOk)
{
	JFactory::getApplication()->enqueueMessage($error_msg, 'error');
}
else
{
/**
 * Automatic menu generation class
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class PlgSystemMenuary extends JPluginGJFields
				{
	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config    An optional associative array of configuration settings.
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->_preparePluginHasBeenSavedOrAppliedFlag();
	}

	/**
	 * Geat plugin parameters from DB and parses them for later usage in the plugin
	 *
	 * @return   type  Description
	 */
	private function _prepareParams()
	{
		if ($this->_forceGoOut())
		{
			return;
		}

		// Get variable fields params parsed in a nice way, stored to $this->pparams
		$this->getGroupParams('{menugroup');

		// Used to check duplicated menu names
		$menu_names_used = array();
		$this->ruleUniqIDs = array();

		// Create menues for each enabled and non-duplicated group of rules
		foreach ($this->pparams as $k => $group_of_rules)
		{
			$this->ruleUniqIDs[] = $group_of_rules['__ruleUniqID'];

			if ($group_of_rules['ruleEnabled'] != 1)
			{
				unset($this->pparams[$k]);
				continue;
			}

			$this->pparams[$k]['articles_number'] = (int) $this->pparams[$k]['articles_number'];

			if ($group_of_rules['target'] == 'root')
			{
				// $this->pparams[$k]['menuname_alias'] = 'menuary-'.JFilterOutput::stringURLSafe($group_of_rules['menuname']);

				$this->pparams[$k]['menuname_alias'] = JFilterOutput::stringURLSafe($group_of_rules['menuname']);
				$this->pparams[$k]['menuname_alias'] = JString::substr($this->pparams[$k]['menuname_alias'], 0, 23);
			}
			else
			{
				$row = JTable::getInstance('menu');
				$row->load($group_of_rules['target_menu_item']);
				$this->pparams[$k]['menuname_alias'] = $row->menutype;
			}

			$group_of_rules['menuname_alias'] = $this->pparams[$k]['menuname_alias'];

			if ($group_of_rules['target'] == 'root' && in_array($group_of_rules['menuname'], $menu_names_used))
			{
				$app = JFactory::getApplication();
				$messages = $app->getMessageQueue();

				$msg = JText::sprintf(
					'PLG_MENUARY_MENU_NAME_DUPLICATED',
					$group_of_rules['menuname'] . '(' . $group_of_rules['menuname_alias'] . ')',
					$group_of_rules['{menugroup'][0],
					$k + 1
				);

				$msg_exists = false;

				foreach ($messages as $i => $v)
				{
					if ($v['message'] == $msg)
					{
						$msg_exists = true;
					}
				}

				if (!$msg_exists)
				{
					JFactory::getApplication()->enqueueMessage($msg, 'warning');
				}

				unset($this->pparams[$k]);
			}
			else
			{
				if ($group_of_rules['target'] == 'root')
				{
					$menu_names_used[] = $group_of_rules['menuname'];
				}

				$db = JFactory::getDBO();

				// GET ALL THE CATEGORY DATA

				// $query = "SELECT `id`,`parent_id`,`title`,`alias`,`published`,`access`,`language`
				// FROM `#__categories` WHERE `extension` = 'com_content' ORDER BY `lft` ASC";

				$query = $db->getQuery(true);
				$query
					// ->select(array('id','parent_id','title','alias','published','access','language', 'level','lft','rgt'))
					->select(array('id','parent_id','title','alias','published','access','language', 'level'))
					->from('`#__categories`')
					->where('`extension` = "com_content"');

					// ->order($this->pparams[$k]['category_order'])

				if (!empty($this->pparams[$k]['categories']))
				{
					$query
						->where('`id` IN(' . implode(',', $this->pparams[$k]['categories']) . ')');
				}

				if (!empty($this->pparams[$k]['categories_exclude']))
				{
					$query
						->where('`id` NOT IN(' . implode(',', $this->pparams[$k]['categories_exclude']) . ')');
				}

				$db->setQuery($query);

				$categories = $db->loadAssocList('id');
				$category_table = JTable::getInstance('category');
				$this->pparams[$k]['categories_to_menu'] = array();

				foreach ($categories as $i => $cat)
				{
					if (!isset($this->pparams[$k]['categories_to_menu'][$i]))
					{
						// $this->pparams[$k]['categories_to_menu'][$i] = $cat;
						$children_cats = $category_table->getTree($i);

						if (!empty($this->pparams[$k]['categories_exclude']))
						{
							foreach ($children_cats as $cc => $categoryObject)
							{
								if (in_array($categoryObject->id, $this->pparams[$k]['categories_exclude']))
								{
									unset($children_cats[$cc]);
								}
							}
						}

						$children_cats[0]->hide_category = false;

						if ($this->pparams[$k]['hide_top_category'] == '1')
						{
							$children_cats[0]->hide_category = true;
						}

						foreach ($children_cats as $j => $child_cat)
						{
							if (!isset($child_cat->hide_category))
							{
								$child_cat->hide_category = false;
							}

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
	 * The fuction is taken from a Ganty particle
	 *
	 * @return  void
	 */
	public function _addMenuaryBadge()
	{
		$document = JFactory::getDocument();
		$type   = $document->getType();

		$app = JFactory::getApplication();
		$option = $app->input->getString('option');
		$view   = $app->input->getString('view');
		$task   = $app->input->getString('task');

		if (in_array($option, array('com_menus')) && ($view == 'items') && !$task && $type == 'html')
		{
			// $body = preg_replace_callback('/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU', array($this, '_appendHtml'), $app->getBody());
			// $app->setBody($body);
		}

		if (($option == 'com_menus') && ($view == 'items') && $type == 'html')
		{
			$this->_prepareParams();
			$ruleUniqIDs = array();
			$db	= JFactory::getDBO();

			foreach ($this->pparams as $k => $params)
			{
				$ruleUniqIDs[] = $db->q($params['__ruleUniqID']);
			}

			if (empty($ruleUniqIDs))
			{
				return;
			}

			$query = $db->getQuery(true);
			$query->select('itemid');
			$query->from('#__menuary');

			$query->where('ruleUniqID IN (' . implode(',', $ruleUniqIDs) . ')');

			$db->setQuery($query);
			$this->menuitems = $db->loadColumn();

			if (sizeof($this->menuitems) > 0)
			{
				$body = $app->getBody();
				$title = 'MenuAry';
				$body = preg_replace_callback(
					'/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU',
					function($matches) use ($title)
					{
						return $this->_appendHtml($matches, $title);
					},
					$body
				);

				$app->setBody($body);
			}
		}
	}

	/**
	 * Appends HTML to menu items
	 *
	 * @param   array   $matches  Don't remember
	 * @param   string  $content  Don't remember
	 *
	 * @return   string  Html with MenuAry badges inserted
	 */
	private function _appendHtml(array $matches, $content = 'MenuAry')
	{
		$html = $matches[0];

		if (strpos($matches[2], 'task=item.edit'))
		{
			$uri = new JUri($matches[2]);
			$id = (int) $uri->getVar('id');

			if ($id && in_array($uri->getVar('option'), array('com_menus')) && (in_array($id, $this->menuitems)))
			{
				$html = $matches[1] . $uri . $matches[3] . $matches[4] . $matches[5];
				$html .= '
					<span
						onMouseOver="this.style.color=\'#00F\'"
						onMouseOut="this.style.color=\'#000\'"
						class="hasTip icon-power-cord" style="
						cursor: help;"
						title="' . JText::_('PLG_MENUARY_MENUITEM_TOOLTIP') . '"></span>';
			}
		}

		return $html;
	}

	/**
	 * Determine if has to be run at the current page
	 *
	 * @param   string  $context  Contexts which force to go out
	 *
	 * @return   bool
	 */
	private function _forceGoOut ($context = null)
	{
		if (JFactory::getUser()->guest)
		{
			return true;
		}

		if (!JFactory::getApplication()->isAdmin())
		{
			return true;
		}

		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump')
		{
			return true;
		}

		if ($jinput->get('option', null) == 'com_jce')
		{
			return true;
		}

		if (!empty($context) && !in_array($context, array('com_content.article','com_categories.category')))
		{
			return true;
		}

		return false;
	}

	/**
	 * TODO Use core Joomla ArrayHelper function instead
	 *
	 * @param   object  $obj  Object
	 *
	 * @return  array
	 */
	public function _object_to_array($obj)
	{
		if (is_object($obj))
		{
				$obj = (array) $obj;
		}

		if (is_array($obj))
		{
			$new = array();

			foreach ($obj as $key => $val)
			{
				if ('categories_to_menu' === $key)
				{
					// $new[$key] = (array)$val;
					$temp = new stdClass;

					foreach ($val as $g => $h)
					{
						$temp->$g = $h;
					}

					$new[$key] = (array) $temp;
				}
				else
				{
					$new[$key] = $this->_object_to_array($val);
				}
			}
		}
		else
		{
			$new = $obj;
		}

		return $new;
	}

	/**
	 * Serialize
	 *
	 * Writes to file don't remember what
	 *
	 * @return   void
	 */
	public function _serialize ()
	{
		$obj = new stdClass;

		foreach ($this as $k => $v)
		{
			if (!in_array($k, array('params','_subject' )))
			{
				$obj->$k = $v;
			}
		}

		$content = base64_encode(serialize($obj));
		JFile::write($this->ajaxTmpFile, $content);
	}

	/**
	 * Unserialies from afile
	 *
	 * @return   void
	 */
	public function _unserialize ()
	{
		$tmp_file_contents = JFile::read($this->ajaxTmpFile);

		if ($obj = unserialize(base64_decode($tmp_file_contents)))
		{
			foreach ($obj as $k => $v)
			{
				$this->$k = $v;
			}
		}

		$this->ajaxMessages = array();
	}

	/**
	 * Runs main ajax part
	 *
	 * @return   void
	 */
	public function onAjaxMenuAryRun ()
	{
		$jinput = JFactory::getApplication()->input;

		$uniq = $jinput->get('uniq', null);

		$this->ajaxTmpFile = JFactory::getApplication()->getCfg('tmp_path') . '/' . $this->plg_full_name . '_' . $uniq;
		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('restart', 0) == '1')
		{
			JFile::delete($this->ajaxTmpFile);
		}

		if (!file_exists($this->ajaxTmpFile))
		{
			$this->_prepareParams();
			$this->ajaxCall = array();
			$this->ajaxCall['runlevel'] = 'doAll';
			$this->ajaxCall['stage'] = 'start';
			$files = JFolder::files(JFactory::getApplication()->getCfg('tmp_path'), $this->plg_full_name . '_*', false, true);
			JFile::delete($files);
		}

		// Not the first run
		if (file_exists($this->ajaxTmpFile))
		{
			$this->_unserialize();
		}

		$this->isAjax = true;
		$this->timeStart = time(true);

		$this->ajaxCall = $this->_doAll();

		if ($this->ajaxCall['runlevel'] !== '##done##')
		{
			$this->_serialize();

			return '';
		}
		else
		{
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

	/**
	 * Does all the job
	 *
	 * @return   void
	 */
	public function _doAll()
	{
		if (empty($this->pparams))
		{
			return;
		}

		if ($this->isAjax)
		{
			if ($this->ajaxCall['runlevel'] != 'doAll')
			{
				goto RunParametersCycle;
			}

			// ~ if ($this->ajaxCall['stage'] == 'params_initialized') { goto RunParametersCycle;}
			if ($this->ajaxCall['stage'] == 'param_handling')
			{
				goto RunParametersCycle;
			}

			if ($this->ajaxCall['stage'] == 'all_categories_done')
			{
				goto all_categories_done;
			}
		}

		$this->_logMsg(JText::_('PLG_MENUARY_AJAX_INITIALIZING'));
		$this->_removeOrphans();

		// INCREASE AVAILABLE MEMORY JUST IN CASE
		$this->_setSystemLimits();

		// Create menuary menus for the enabled group of rules
		foreach ($this->pparams as $k => $v)
		{
			$this->_checkMenuExistsAndCreateIfNeeded($this->pparams[$k]);
		}

		$this->rebuild = false;

		$this->_logMsg('...' . JText::_('PLG_MENUARY_AJAX_DONE'), true);

		if ($this->isAjax)
		{
			$this->ajaxCall['runlevel'] = 'doAll';
			$this->ajaxCall['stage'] = 'param_handling';

			if ($this->_ajaxReturn())
			{
				return $this->ajaxCall;
			}
		}

		// REGENERATE MENU IF REQUESTED
RunParametersCycle:

		foreach ($this->pparams as $k => $params)
		{
			/*
			 * I set the flag is after regenerating menu rebuild is a must
			 *
			 * If updating a a menu rebuild may not be needed.
			 * But to determine if lft-rgt-level changed we have to load the menu entry before storeing the updated one.
			 *
			 * This would make us to load doznes of menu items to check and maybe still rebuild.
			 * So I arrived at a decision to rebuild when regenerating/updating a menu anyway.
			 * The flag is needed to prevent ->_saveMenuItem from loading menu items for each menu item updated.
			 */
			$this->rebuild = true;

			if ($this->isAjax)
			{
				if (!empty($this->ajaxCall['param_key']))
				{
					if ($k != $this->ajaxCall['param_key'] )
					{
						continue;
					}
				}

				$this->ajaxCall['param_key'] = $k;
			}

			if ($this->isAjax && $this->ajaxCall['runlevel'] == 'doAll')
			{
				$this->_logMsg(
					'<br>' . JText::_('PLG_MENUARY_AJAX_RUNNING_RULE_START')
					. ' <b>' . $params['{menugroup'][0] . '</b> (' . $params['__ruleUniqID'] . ')');
				unset($this->ajaxCall['current_category_id']);
				unset($this->ajaxCall['current_article_id']);
			}
			elseif (!$this->isAjax)
			{
				$this->_logMsg('<br>' . JText::_('PLG_MENUARY_AJAX_RUNNING_RULE_START')
				. ' <b>' . $params['{menugroup'][0] . '</b> (' . $params['__ruleUniqID'] . ')');
			}

			// If the rule groups is set not to regenarate menu, then go away
			if ((int) $params['regeneratemenu'] != 0 )
			{
				if ($params['articles_number'] > 0)
				{
					$params['regeneratemenu'] = 2;
				}

				if (!$this->isAjax)
				{
					$this->_regenerateMenu($params);
				}
				else
				{
					if ($this->ajaxCall['runlevel'] == 'doAll')
					{
						$this->ajaxCall['runlevel'] = 'regenerateMenu';
					}

					$ajaxRes = $this->_regenerateMenu($this->pparams[$k]);

					if (!empty($ajaxRes))
					{
						if ($this->_ajaxReturn())
						{
							return $ajaxRes;
						}
						else
						{
							$this->ajaxCall = $ajaxRes;
						}
					}
					else
					{
						$this->ajaxCall['runlevel'] = 'doAll';
					}
				}
			}

			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_RUNNING_RULE_END') . ' ' . $params['{menugroup'][0] . '(' . $params['__ruleUniqID'] . ')');

			if ($this->isAjax && $this->ajaxCall['runlevel'] == 'doAll')
			{
				$next_key = $this->_getNextKey($array = $this->pparams, $current_key = $k);

				// If there is a next item, then return ajax
				if ($next_key && $k != $next_key)
				{
					$this->ajaxCall['runlevel'] = 'doAll';
					$this->ajaxCall['stage'] = 'param_handling';
					$this->ajaxCall['param_key'] = $next_key;

					if ($this->_ajaxReturn())
					{
						return $this->ajaxCall;
					}
				}
			}
		}

		if ($this->isAjax)
		{
			$this->ajaxCall['runlevel'] = 'doAll';
			$this->ajaxCall['stage'] = 'all_categories_done';

			if ($this->_ajaxReturn())
			{
				return $this->ajaxCall;
			}
		}

all_categories_done:

		if ($this->isAjax && empty($this->ajaxCall['start_rebuild']))
		{
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_STARTING_REBUILD') . '...');
			$this->ajaxCall['runlevel'] = 'doAll';
			$this->ajaxCall['stage'] = 'all_categories_done';
			$this->ajaxCall['start_rebuild'] = 'start';

			return $this->ajaxCall;
		}
		elseif (!$this->isAjax)
		{
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_STARTING_REBUILD') . '...');
		}

		if ($this->rebuild)
		{
			$table = JTable::getInstance('menu');

			if (!$table->rebuild())
			{
				JError::raiseError(500, 'MenuAry: ' . $table->getError());
			}
		}

		$this->_logMsg(JText::_('PLG_MENUARY_AJAX_DONE'), true);
		$this->_logMsg('<br><b>' . JText::_('PLG_MENUARY_FINISHED') . '</b>');

		if ($this->isAjax)
		{
			return array ('runlevel' => '##done##');
		}
	}

	/**
	 * Regenarate menu
	 *
	 * @param   object  &$params  Menu params
	 *
	 * @return   void
	 */
	private function _regenerateMenu(&$params)
	{
		if ($this->isAjax )
		{
			if ($this->ajaxCall['runlevel'] == 'doAllCategories')
			{
				goto doAllCategories;
			}

			if ($this->ajaxCall['runlevel'] == 'doAllArticles')
			{
				goto doAllArticles;
			}

			if ($this->ajaxCall['runlevel'] == 'regenerateMenu')
			{
				$this->ajaxCall['runlevel'] = 'doAllCategories';
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_START'), false, 2);
			}
			/*
			if (isset($this->ajaxCall['categoriesDone']) && $this->ajaxCall['categoriesDone'] === false) {
			}
			elseif (isset($this->ajaxCall['articlesDone']) && $this->ajaxCall['categoriesDone'] === false) {
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_START'), false, 2);
			}
			*/
		}
		else
		{
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_START'), false, 2);
		}

doAllCategories:
		$ajaxRes = $this->_doAllCategories($params);

		if ($this->isAjax)
		{
			if (!empty($ajaxRes))
			{
				if ($this->_ajaxReturn())
				{
					return $ajaxRes;
				}
				else
				{
					$this->ajaxCall = $ajaxRes;
				}
			}

			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_END'), false, 2);
			$this->ajaxCall['runlevel'] = 'doAllArticles';

			if ($this->_ajaxReturn())
			{
				return $this->ajaxCall;
			}
		}
		else
		{
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_END'), false, 2);
		}

doAllArticles:
		if ($params['show_articles'] == "1")
		{
			if ($this->isAjax && empty ($this->ajaxCall['current_article_id']))
			{
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_START'), false, 2);
			}
			elseif(!$this->isAjax)
			{
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_START'), false, 2);
			}

			$ajaxRes = $this->_doAllArticles($params);

			// $this->_msg(JText::_('PLG_MENUARY_MENU_REGENERATED').' <b>'.$params['{menugroup'][0].'</b> (unique id: '.$params['__ruleUniqID'] . ')');

			if ($this->isAjax)
			{
				if (!empty($ajaxRes))
				{
					if ($this->_ajaxReturn())
					{
						return $ajaxRes;
					}
					else
					{
						$this->ajaxCall = $ajaxRes;
					}
				}
			}

			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_END'), false, 2);
		}
	}

	/**
	 * Gets all joomla categories and creates appropriate menu items for all categories
	 *
	 * @param   object  &$params  The options of the plugin (one group of rules)
	 *
	 * @return   void
	 */
	private function _doAllCategories(&$params)
	{
		if ($this->isAjax )
		{
			if (empty($this->ajaxCall['current_category_id']))
			{
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_PREPARATIONS') . '...', false, 3);
			}
			else
			{
				goto CategoriesCycle;
			}
		}
		else
		{
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_CATEGORIES_PREPARATIONS') . '...', false, 3);
		}

		$jinput = JFactory::getApplication()->input;
		$db = JFactory::getDBO();

		// If we have to regenerate a whole menu, then remove
		// the existing one from both - #__menu and #__menuary tables

		if ($params['regeneratemenu'] == 2)
		{
				// Build the query
				$query = 'DELETE t1.* FROM #__menu AS t1
								LEFT JOIN #__menuary AS t2
								ON t1.id = t2.itemid
								WHERE t2.ruleUniqID =' . $db->quote($params['__ruleUniqID']);

				$db->setQuery($query);
				$db->query();
				$this->_removeOrphans();
		}

		$this->categories_to_menu_current = $params['categories_to_menu'];

		// Sort categories {
		if ($params['com_categories.category_order'] != 'default')
		{
			$params['category_order_exploded'] = explode(' ', $params['com_categories.category_order']);

			// If ($params['category_order_exploded'][0] == 'order') {break;} // If we use ordering, we don't sort additionally
			// So below we can order oby title, is other options do not need sorting
			$categories_to_menu_ordered = array();

			// Build a convinient tree of categories for later convinient use
			foreach ($this->categories_to_menu_current as $k => $v)
			{
				// Here is not ordered yet, but the array is used to store ordered categories
				$categories_to_menu_ordered[$v->parent_id][$k] = $v;
			}

			foreach ($categories_to_menu_ordered as $parent_id => $children)
			{
				if ($params['category_order_exploded'][0] == 'title')
				{
					// Sort menu items by title
					usort(
						$children,
						function($a, $b)
						{
							return strcmp($a->title, $b->title);
						}
					);
				}

				if ($params['category_order_exploded'][1] == 'desc')
				{
					$children = array_reverse($children);
				}

				$categories_to_menu_ordered[$parent_id] = $children;
			}

			$this->categories_to_menu_current  = array();

			foreach ($categories_to_menu_ordered as $parent_id => $sorted_categories)
			{
				foreach ($sorted_categories as $category)
				{
					$this->categories_to_menu_current[$category->id] = $category;
				}
			}

			unset($categories_to_menu_ordered);
			$already_sorted = true;
		}

		// Sort categories }

		$this->category_helper = array();

		// Reference between category id and category level for easier access
		$this->category_id_level_tie = array();

		if ($this->isAjax)
		{
			// Do nothing
		}

		$this->catCounter = 0;
		$this->_logMsg(JText::_('PLG_MENUARY_AJAX_DONE'), true);
		$this->_logMsg(JText::_('PLG_MENUARY_ELEMENS_NUMBER_TO_BE_HANDLED') . ': ' . count($this->categories_to_menu_current), false, 3);

CategoriesCycle:

// ~ $counter = 0;
		while (true)
		{
// ~ $counter++;
// ~ if ($counter>100) {echo 'BAD'.PHP_EOL;break; }
			foreach ($this->categories_to_menu_current as $catid => $category)
			{
// ~ if ($counter>95) {echo 'BAD in'.PHP_EOL;break; }

				// Skip till the needed category to continue
				if ($this->isAjax && !empty($this->ajaxCall['current_category_id'] ))
				{
					if ($catid != $this->ajaxCall['current_category_id'] )
					{
						continue;
					}
				}

				// Reset category level. I don't remember quite well, but it seems we reset category->level to make it relative.
				while (true)
				{
					// Find the top level parent category
					if (isset($this->categories_to_menu_current[$category->parent_id]))
					{
						$catid = $category->parent_id;
						$category = $this->categories_to_menu_current[$category->parent_id];
						continue;
					}
					// If there is no a parent category for the current category
					else
					{
						if (isset($this->category_id_level_tie[$category->parent_id]))
						{
							$category->level = $this->category_id_level_tie[$category->parent_id] + 1;
						}
						else
						{
							$category->level = 1;
						}

						$this->category_id_level_tie[$catid] = $category->level;
					}

					$this->category_helper[$catid]['level'] = $category->level;

					break;
				}

				// If rule is set to hide the top category, then go out. The rule is updated with the ->category_helper array at this point
				if ($category->hide_category )
				{
					unset($this->categories_to_menu_current[$catid]);
					$this->_logMsg(JText::_('PLG_MENUARY_AJAX_MENUITEM_SKIPPED') . ': ' . $category->title . ' ( catid = ' . $category->id . ' )', false, 3);

					if ($this->isAjax)
					{
						// Do nothing
					}

					// If (empty($this->categories_to_menu_current)) { break; }
					continue;
				}

				// SET THE MENU ID & SAVE IN ASSETS TABLE
				$Itemid = $this->_getMenuItemId('com_categories.category', $catid, $params);
				$isNew = false;

				// It has to be a new item, then prepare an empty record for it in #__menu and get menu Itemid
				if ($Itemid == 0)
				{
					$isNew = true;
					$Itemid = $this->_insertMenuItem('com_categories.category', $catid, $params);
				}

				$this->category_helper[$catid]['itemid'] = $Itemid;

				// GET THE PARENT CATEGORY MENU ID
				$category->parent_menu_item_id = $this->_getMenuItemId('com_categories.category', $category->parent_id, $params);

				if ($category->parent_menu_item_id == '0')
				{
					if ($params['target'] == 'root' )
					{
						$category->parent_menu_item_id = $this->_getRoot();
					}
					else
					{
						$category->parent_menu_item_id = $params['target_menu_item'];
					}
				}

				// CHECK ALIAS
				$category->alias = $this->_checkAliasAndMakeOriginalIfNeeded($category->alias, $Itemid, $category->parent_menu_item_id);

				if ($params['category_link_type'] !== 'none')
				{
					$category->link = $this->_getCategoryLink($category, $params);
					$component = 'component';
				}
				else
				{
					$category->link = '';
					$component = 'heading';
				}

				// BUILD THE DATA ARRAY
				$array = array(
					'menutype' => $params['menuname_alias'],
					'title' => $category->title,
					'alias' => $category->alias,
					'link' => $category->link,
					'type' => $component,
					'published' => $category->published,
					'parent_id' => $category->parent_menu_item_id,
					'level' => $category->level,
					'component_id' => 22,
					'access' => $category->access,
					'language' => $category->language,
					'id' => $Itemid,
					'contentid' => $category->id
				);

				$this->_saveMenuItem(
					$context = 'com_categories.category' /*values:category|article*/,
					$array /*array of data to be stored*/,
					$params /*current rule params*/
				);

				if ($isNew)
				{
					$txt = JText::_('PLG_MENUARY_AJAX_MENUITEM_ADDED');
				}
				else
				{
					$txt = JText::_('PLG_MENUARY_AJAX_MENUITEM_UPDATED');
				}

				$this->catCounter++;

				$this->_logMsg(
					$txt . ': [' . $this->catCounter . '] ' . $category->title . ' ( catid = ' . $category->id
						. ' ; Itemid = ' . $Itemid . ' ; alias: ' . $category->alias . ' )',
					false, 4
				);

				if ($this->isAjax )
				{
					$next_key = $this->_getNextKey($array = $this->categories_to_menu_current, $current_key = $catid);

					if ($params['com_categories.category_order'] == 'default' && $next_key === false)
					{
						reset($this->categories_to_menu_current);
						$current = current($this->categories_to_menu_current);
						$next_key = $current->id;
					}

					// ~ $next_key = next(array_keys($this->categories_to_menu_current));
					unset($this->categories_to_menu_current[$catid]);

					if ($next_key && $catid != $next_key )
					{
						$this->ajaxCall['runlevel'] = 'doAllCategories';
						$this->ajaxCall['current_category_id'] = $next_key;

						if ($this->_ajaxReturn())
						{
							return $this->ajaxCall;
						}
					}
				}
				else
				{
					unset($this->categories_to_menu_current[$catid]);
				}
			}

			if (empty($this->categories_to_menu_current) )
			{
				break;
			}
		}

		$params['categories_used'] = $this->category_helper;

		if ($this->isAjax )
		{
			return null;
		}
	}

	/**
	 * Regenarate menu for articles
	 *
	 * @param   object  &$params  Menu params
	 *
	 * @return   void
	 */
	private function _doAllArticles(&$params)
	{
		// DON'T BOTHER RUNNING IF NO ARTICLES ARE TO BE INCLUDED
		if ((int) $params['show_articles'] == '0')
		{
			return false;
		}

		if ($this->isAjax )
		{
			if (empty($this->ajaxCall['current_article_id']))
			{
				$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_PREPARATIONS') . '...', false, 3);
			}
			else
			{
				goto ArticlesCycle;
			}
		}
		else
		{
			$this->_logMsg(JText::_('PLG_MENUARY_AJAX_ARTICLES_PREPARATIONS') . '...', false, 3);
		}

		// GET ALL THE ARTICLES
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);

		// $query = "SELECT `id`,`title`,`alias`, `access`, `state`, `catid`, `language` FROM `#__content`";
		$query
			->select(array('id','title','alias', 'access', 'state', 'catid', 'language', 'created'))
			->from('`#__content`');

		$used_catids = array_keys($params['categories_used']);

		if (!empty($used_catids))
		{
			$query
				->where('`catid` IN(' . implode(',', $used_catids) . ')');
		}

		if ($params['com_content.article_order'] !== 'default')
		{
			$query->order($params['com_content.article_order']);
		}

		if ($params['articles_number'] > 0 )
		{
			$query->where('`state` = ' . $db->q('1'));
			$query->setLimit($params['articles_number']);
		}

		$db->setQuery($query);
		$this->articles_current = $db->loadAssocList('id');

		if ($this->isAjax)
		{
			$this->articles_current_keys = array_keys($this->articles_current);
		}

		$this->articleCounter = 0;
		$this->_logMsg(JText::_('PLG_MENUARY_AJAX_DONE'), true);
		$this->_logMsg(JText::_('PLG_MENUARY_ELEMENS_NUMBER_TO_BE_HANDLED') . ': ' . count($this->articles_current), false, 3);

ArticlesCycle:

		foreach ($this->articles_current as $articleId => $article)
		{
			// Skip till the needed category to continue
			if ($this->isAjax && !empty($this->ajaxCall['current_article_id'] ))
			{
				if ($articleId != $this->ajaxCall['current_article_id'] )
				{
					continue;
				}
			}

			// SET THE MENU ID & SAVE IN ASSETS TABLE
			$Itemid = $this->_getMenuItemId('com_content.article', $article['id'], $params);
			$isNew = false;

			if ($Itemid == 0)
			{
				$isNew = true;
				$Itemid = $this->_insertMenuItem('com_content.article', $article['id'], $params);
			}

			// GET THE PARENT CATEGORY DATA
			$cat = $params['categories_used'][$article['catid']];

			// GET THE PARENT CATEGORY MENU ID

			// ~ $article['parent_menuid'] = $this->_getMenuItemId(0,$article['catid']);
			// ~ if($article['parent_menuid'] == '0') $article['parent_menuid'] = $this->_getRoot();

			$article['parent_menuid'] = '0';

			// This would work quicker then else
			if (isset($cat['itemid']))
			{
				$article['parent_menuid'] = $cat['itemid'];
			}
			// Old way keep just in case, would work longer then the first IF option
			else
			{
				$article['parent_menuid'] = $this->_getMenuItemId('com_categories.category', $article['catid'], $params);
			}

			if ($article['parent_menuid'] == '0')
			{
				if ($params['target'] == 'root')
				{
					$article['parent_menuid'] = $this->_getRoot();
				}
				else
				{
					$article['parent_menuid'] = $params['target_menu_item'];
				}
			}

			$article['level'] = $cat['level'] + 1;
			$article['link'] = 'index.php?option=com_content&view=article&id=' . $article['id'];

			// CHECK ALIAS
			if (!isset($cat['itemid']))
			{
				$cat['itemid'] = 1;
			}

			$article['alias'] = $this->_checkAliasAndMakeOriginalIfNeeded($article['alias'], $Itemid, $article['parent_menuid']);

			// BUILD THE DATA ARRAY
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
				'contentid' => $article['id'],
			);

			// $this->_setRebuildFlag($array,$Itemid);

			// UPDATE THE MENU TABLE

			// If regenerating a menu, the items are ordered before saving menu item. So I make it temporary 'default'
			// to prevent ->_saveMenuItem for trying to order items itself
			$tmp = $params['com_content.article_order'];
			$params['com_content.article_order'] = 'default';

			$this->_saveMenuItem(
				$context = 'com_content.article' /*values:com_categories.category|com_content.article*/,
				$array /*array of data to be stored*/,
				$params /*current rule params*/
			);

			$params['com_content.article_order'] = $tmp;

			if ($isNew)
			{
				$txt = JText::_('PLG_MENUARY_AJAX_MENUITEM_ADDED');
			}
			else
			{
				$txt = JText::_('PLG_MENUARY_AJAX_MENUITEM_UPDATED');
			}

			$this->articleCounter++;

			$this->_logMsg(
				$txt . ': [' . $this->articleCounter . '] ' . $article['title'] . ' ( article id  = ' . $article['id']
					. ' ; Itemid = ' . $Itemid . ' ; alias: ' . $array['alias'] . ' )',
				false, 4
			);

			if ($this->isAjax )
			{
				$next_key = $this->_getNextKey($array = $this->articles_current, $current_key = $articleId);

				// I have to get next key in such a way, because next() fails.

				/*
				$getNext = false;
				$next_key = false;
				foreach ($this->articles_current_keys as $k => $v) {
					if ($v == $articleId) { $getNext = true; continue; }
					if ($getNext) {
						$next_key = $v;
						break;
					}
				}
				*/

				// ~ unset($this->articles_current[$n]);

				if ($next_key && $articleId != $next_key )
				{
					$this->ajaxCall['runlevel'] = 'doAllArticles';
					$this->ajaxCall['current_article_id'] = $next_key;

					if ($this->_ajaxReturn())
					{
						return $this->ajaxCall;
					}
				}
			}

			/*
			$query = "UPDATE `#__menu` SET ";
			foreach ($array as $x => $y) {
				$query .= $db->quoteName($x).' = ' . $db->quote($y);
				if($x !== 'language') $query .= ',';
			}
			$query .= ' WHERE ' . $db->quoteName('id').' = ' . $db->quote($Itemid).' LIMIT 1';
			$db->setQuery($query);
			$db->query();
			*/
		}

		return null;
	}

	/**
	 * Don't remember
	 *
	 * @param   array   $array        Description
	 * @param   string  $current_key  Description
	 *
	 * @return   string
	 */
	public function _getNextKey ($array, $current_key)
	{
		$keys = array_keys($array);

		// I have to get next key in such a way, because next() fails.
		$getNext = false;
		$next_key = false;

		foreach ($keys as $k => $v)
		{
			if ($v == $current_key)
			{
				$getNext = true;

				continue;
			}

			if ($getNext)
			{
				$next_key = $v;
				break;
			}
		}

		return $next_key;
	}

	/**
	 * Log message
	 *
	 * @param   string  $text     Text to be logged
	 * @param   bool    $inline   If to log inline
	 * @param   int     $prepend  Number of spaces to prepend before the text
	 *
	 * @return   void
	 */
	public function _logMsg ($text, $inline = false, $prepend = 0)
	{
		if ($prepend > 0)
		{
			$text = str_repeat('&nbsp;', $prepend) . $text;
		}

		if ($inline)
		{
			// ~ echo $text;
		}
		else
		{
			$text = '<br/>' . $text;
		}

		if ($this->isAjax)
		{
			echo $text;
			flush();
		}
		else
		{
			if (!isset($this->messages))
			{
				$this->messages = array();
			}

			$this->messages[] = $text;
		}
	}

	/**
	 * Desc
	 *
	 * @return   void
	 */
	public function _ajaxReturn()
	{
		if ($this->paramGet('debug') && $this->paramGet('step') > 0 )
		{
			static $ajaxReturn = 0;
			$ajaxReturn++;

			if ($ajaxReturn >= $this->paramGet('step'))
			{
				return true;
			}

			return false;
		}

		$time_now = time(true);

		$execution_time = ($time_now - $this->timeStart);

		$max_execution_time = ini_get('max_execution_time') - 5;

		// ~ if ($execution_time>$max_execution_time) {
		if ($execution_time > 1)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Before render
	 *
	 * @return   void
	 */
	public function onBeforeRender()
	{
		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump')
		{
			return true;
		}

		if ($this->checkIfNowIsCurrentPluginEditWindow())
		{
			$isAjax = $this->paramGet('ajax');
		}

		$this->_addBehaviorToolTipIfNeeded();

		if ($this->_forceGoOut())
		{
			return;
		}

		if (!$this->pluginHasBeenSavedOrApplied)
		{
			return;
		}

		// So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled
		$this->_prepareParams();

		if (empty($this->pparams))
		{
			return;
		}

		if (!isset($isAjax))
		{
			$isAjax = $this->paramGet('ajax');
		}

		if ($isAjax)
		{
			$ajax_place = '';
			/*
			$url_ajax_plugin = JRoute::_(
				JURI::base() . '?option=com_ajax&plugin=ajaxhelpary&format=raw&' . JSession::getFormToken() . '=1&plg_name='
					. $this->plg_name . '&plg_type=' . $this->plg_type . '&function=_ajaxRun&uniq=' . uniqid()
				);
			*/

			$url_ajax_plugin = JRoute::_(
						JURI::base()
						. '?option=com_ajax&format=raw'
						. '&group=' . $this->plg_type
						. '&plugin=menuAryRun'
						. '&' . JSession::getFormToken() . '=1'
						. '&uniq=' . uniqid()
				);

			if ($this->paramGet('debug'))
			{
				$url_ajax_plugin .= '&debug=1';
				$ajax_place .= $url_ajax_plugin;
				$ajax_place = '<a id="clear" class="btn btn-error">Clear</a>' . $ajax_place;
				$ajax_place = '<a id="continue" class="btn btn-warning">Continue</a>' . $ajax_place;
			}

			$ajax_place .= '<b>' . JText::_('PLG_MENUARY_WAIT_PLEASE') . '</b><br><div id="' . $this->plg_full_name . '" ></div>';
			$this->_msg($ajax_place, 'notice');
			$doc = JFactory::getDocument();

			/*
			$doc->addScriptDeclaration('var menuary_ajax_url = "' . $url_ajax_plugin . '";');
			$doc->addScriptDeclaration('var ajax_helpary_message = "' . JText::_('PLG_MENUARY_AJAX_ERROR') . '";');
			*/

			$doc->addScriptOptions('menuary', array('ajax_url' => $url_ajax_plugin));
			$doc->addScriptOptions('menuary', array('ajax_message' => JText::_('PLG_MENUARY_AJAX_ERROR')));

			$path_to_assets = str_replace(JPATH_ROOT, '', dirname(__FILE__));
			$doc->addScript($path_to_assets . '/js/ajax.js?time=' . time());
			$doc->addStyleSheet($path_to_assets . '/css/styles.css');

			if ($this->paramGet('debug'))
			{
				$doc->addScriptOptions('menuary', array('debug' => true));
			}
		}
		else
		{
			$this->isAjax = false;
			$this->_doAll();

			if (!empty($this->messages))
			{
				$this->_msg(implode(PHP_EOL, $this->messages), 'notice');
			}
		}
	}

	/**
	 * Adds a menu bage at menu edit pages
	 *
	 * @return   void
	 */
	public function onAfterRender ()
	{
		// Add icons to the menu page to show menu items generated by MenuAry and still tied to MenuAry
		$this->_addMenuaryBadge();
	}

	/**
	 * Add tooltip behavior if needed
	 *
	 * Full description (multiline)
	 *
	 * @return   type  Description
	 */
	public function _addBehaviorToolTipIfNeeded()
	{
		if (!JFactory::getApplication()->isAdmin())
		{
			return;
		}

		$document = JFactory::getDocument();
		$type   = $document->getType();

		$app = JFactory::getApplication();
		$option = $app->input->getString('option');
		$view   = $app->input->getString('view');
		$task   = $app->input->getString('task');

		if (in_array($option, array('com_menus')) && ($view == 'items') && !$task && $type == 'html')
		{
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
	 * @param   string  $context  Possible values: com_categories.category or com_content.article
	 * @param   array   $array    Object to be stored to the JTableMenu #__menu
	 * @param   array   &$params  Current group of rules
	 * @param   bool    $isNew    Wether we update or create a new article/category
	 *
	 * @return	void
	 */
	private function _saveMenuItem(
		$context /*values:com_categories.category|com_content.article*/,
		$array /*array of data to be stored*/,
		&$params /*current rule params*/,
		$isNew = true /* wether we update or create a new article/category */
		)
	{
		if (empty($context))
		{
			$context = 'com_categories.category';
		}

		$table = JTable::getInstance('menu');

		/*##mygruz20160104043733 {
		It was:
		$table->bind( $array );
		$table->check();
		if (!$table->store($array)) {
		* // TODO Замінити три рядки нижче на $table->save 20160104035452 {
		* // }
		It became:*/
		/*##mygruz20160104043733 } */

		$app = JFactory::getApplication();

		// Here I must save it twice. I don't know why, but when saving once for a new item, it refuses to save at least parent_id.
		if ($array['id'] == 0 || !isset($array['id']))
		{
			if (!$table->save($array))
			{
				$app->enqueueMessage('MenuAry: ' . $table->getError() . '<br/>', 'error');
			}

			$array['id'] = $table->id;
		}
		// Here I store menu item info before save to compare with the saved item and to determine
		// if rebuild is needed. Rebuild it time consuming, so I try not to run it in vain
		elseif ($this->rebuild !== true )
		{
			$table->load($array['id']);
			$lft = $table->lft;
			$rgt = $table->rgt;
			$level = $table->level;
		}

		// Specify where to insert the new node.
		if ($params[$context . '_order'] != 'default')
		{
			$order_param_exploded = explode(' ', $params[$context . '_order']);

			if (in_array($order_param_exploded[0], array ('title', 'created', 'modified', 'publish_up', 'publish_down')))
			{
				$tree = $table->getTree($array['parent_id']);
				$current_menuitems_is_already_among_menu_items = false;

				foreach ($tree as $k => $v)
				{
					if ($v->id == $array['id'])
					{
						$current_menuitems_is_already_among_menu_items = true;
						break;
					}
				}

				$needed_level = $tree[0]->level + 1;

				if ($current_menuitems_is_already_among_menu_items === false )
				{
					$object = (object) $array;
					$object->link = 'id=' . $object->contentid;
					$object->level = $needed_level;
					$tree[] = $object;
				}

				unset($tree[0]);

				foreach ($tree as $k => $menuitem)
				{
					if ($needed_level != $menuitem->level)
					{
						unset($tree[$k]);
						continue;
					}

					// Menu getTree() contains old title, while we have new in the $array.
					// So need to use the new one to later sort menu items
					if ($array['id'] == $menuitem->id)
					{
						$menuitem->title = $array['title'];
					}

					if ($order_param_exploded[0] != 'title')
					{
						parse_str($menuitem->link, $get_array);
						$menuitem->contentid = $get_array['id'];
						$db = JFactory::getDBO();
						$query = $db->getQuery(true);
						$query
							->select(array($order_param_exploded[0], 'catid'))
							->from('`#__content`')
							->where('`id` = ' . $db->q($get_array['id']));
						$db->setQuery($query);
						$res = $db->loadAssoc();
						$menuitem->{$order_param_exploded[0]} = $res[$order_param_exploded[0]];
						$menuitem->catid = $res['catid'];

						if ($params['articles_number'] > 0 && $context == 'com_content.article')
						{
							$query = $db->getQuery(true);
							$query
								->select('id')
								->from('`#__menuary`');
							$query->where($db->quoteName('ruleUniqID') . " = " . $db->quote($params['__ruleUniqID']));
							$query->where($db->quoteName('context') . " = " . $db->quote($context));
							$query->where($db->quoteName('Itemid') . " = " . $db->quote($menuitem->id));

							$db->setQuery($query);
							$res = $db->loadResult();

							if (!empty($res))
							{
								$menuitem->isMenuAryMenuItem = $res;
							}
						}
					}

					$menuitem->sortField = $menuitem->{$order_param_exploded[0]};
					$tree[$k] = $menuitem;
				}

				// Sort menu items by title
				usort(
					$tree,
					function($a, $b) {
						return strcmp($a->sortField, $b->sortField);
					}
				);

				if ($order_param_exploded[1] == 'desc')
				{
					$tree = array_reverse($tree);
				}

				if ($params['articles_number'] > 0 && $context == 'com_content.article' )
				{
					$counter = 0;

					foreach ($tree as $k => $menuitem)
					{
						if (!empty($menuitem->isMenuAryMenuItem))
						{
							$counter++;

							if ($counter > $params['articles_number'])
							{
								$object = new stdClass;
								$object->id = $menuitem->contentid;
								$object->catid = $menuitem->catid;
								$this->onContentBeforeDelete($context, $object);
							}
						}
					}
				}

				foreach ($tree as $k => $menuitem)
				{
					if ($array['id'] == $menuitem->id)
					{
						$key = $k - 1;

						if ($key < 0)
						{
							$table->setLocation($array['parent_id'], 'first-child');
						}
						else
						{
							$table->setLocation($tree[$key]->id, 'after');
						}

						break;
					}
				}
			}
			else
			{
				if ($isNew)
				{
					$order = 'last-child';

					if ($order_param_exploded[1] == 'desc')
					{
						$order = 'first-child';
					}

					$table->setLocation($array['parent_id'], $order);
				}
			}
		}
		else
		{
			if ($isNew)
			{
				$order = 'last-child';
				$table->setLocation($array['parent_id'], $order);
			}
		}

		if (!$table->save($array))
		{
			$app->enqueueMessage('MenuAry: ' . $table->getError() . '<br/>', 'error');
		}
		else
		{
			// Get acontent associations
			while (true)
			{
				if ($array['language'] == '*')
				{
					break;
				}

				$associations = $this->_getAssociations($context, $array['contentid']);

				if ($associations === false )
				{
					break;
				}

				// Find menu items according to content associations
				$menuItemAssociations = array($array['language'] => $table->id);

				foreach ($associations as $lang => $contentid)
				{
					$Itemid = $this->_getMenuItemId($context, $contentid, $params, $ignoreRuleUniqId = true);

					if ($Itemid == 0)
					{
						continue;
					}

					$menuItemAssociations[$lang] = $Itemid;
				}

				// ~ if (count($menuItemAssociations)<2) { break;}

				// Deleting old association for these items
				$db = JFactory::getDBO();
				$query = $db->getQuery(true)
					->delete('#__associations')
					->where('context=' . $db->quote('com_menus.item'))
					->where('id IN (' . implode(',', $menuItemAssociations) . ')');
				$db->setQuery($query);

				try
				{
					$db->execute();
				}
				catch (RuntimeException $e)
				{
					$this->setError($e->getMessage());
				}

				if (count($menuItemAssociations) < 2)
				{
					break;
				}

				// Adding new association for these items
				$key = md5(json_encode($menuItemAssociations));
				$query->clear()
					->insert('#__associations');

				foreach ($menuItemAssociations as $id)
				{
					$query->values(((int) $id) . ',' . $db->quote('com_menus.item') . ',' . $db->quote($key));
				}

				$db->setQuery($query);

				try
				{
					$db->execute();
				}
				catch (RuntimeException $e)
				{
					$this->setError($e->getMessage());
				}

				break;
			}
		}

		if (isset($lft) && ($table->lft == $lft && $table->rgt == $rgt && $table->level == $level))
		{
			$this->rebuild = false;
		}
		else
		{
			$this->rebuild = true;
		}

		$Itemid = (int) $table->id;

		unset($table);

		return $Itemid;
	}

	/**
	 * Desc
	 *
	 * @param   string  $context  Description
	 *
	 * @return   type  Description
	 */
	private function _getAssociationsContext ($context)
	{
		// 'com_content.article','com_categories.category'
		$associationsContext = explode('.', $context);

		return $associationsContext[0] . '.item';
	}

	/**
	 * TODO Name or short description
	 *
	 * @param   object  $params  Description
	 *
	 * @return   type  Description
	 */
	private function _checkMenuExistsAndCreateIfNeeded($params)
	{
		if ($this->_forceGoOut())
		{
			return;
		}

		$jinput = JFactory::getApplication()->input;

		if ($params['target'] != 'root' )
		{
			return true;
		}

		$db = JFactory::getDBO();
		$query = "SELECT `id` FROM `#__menu_types` WHERE `menutype` = " . $db->quote($params['menuname_alias']) . " LIMIT 1";

		$db->setQuery($query);
		$res = $db->loadAssoc();

		if (!isset($res['id']))
		{
			$query = "INSERT INTO " . $db->quoteName('#__menu_types')
				. " SET "
				. $db->quoteName('menutype') . " = " . $db->quote($params['menuname_alias']) . ","
				. $db->quoteName('description') . " = " . $db->quote($params['menuname']) . ","
				. $db->quoteName('title') . "=" . $db->quote($params['menuname']);
			$db->setQuery($query);
			$db->query();

			// $_SESSION[$this->plg_full_name]['regen'] = '1';
			// $_SESSION[$this->plg_full_name]['regen_menu'][$params['menuname_alias']] = '1';
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
	 * @param   string  $context           com_content.article or com_categories.category
	 * @param   int     $id                category or article id
	 * @param   object  $params            Desc
	 * @param   boold   $ignoreRuleUniqId  Desc
	 *
	 * @return  int  Element id from #__menu which is the Itemid in joomla URLs
	 */
	private function _getMenuItemId($context, $id, $params, $ignoreRuleUniqId = false)
	{
		if ((int) $id === 0)
		{
			return 0;
		}

		if (empty($context))
		{
			$context = 'com_categories.category';
		}

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

		if (!$ignoreRuleUniqId)
		{
			$query->where($db->quoteName('t1.ruleUniqID') . " = " . $db->quote($params['__ruleUniqID']));
		}

		$query->where($db->quoteName('t1.context') . " = " . $db->quote($context));

		if ($params['target'] == 'root' )
		{
			$query->where($db->quoteName('t2.menutype') . " = " . $db->quote($params['menuname_alias']));
		}

		if ($id > 0)
		{
			$query->where($db->quoteName('content_id') . " = " . $db->quote($id));
		}

		// $query->order('ordering ASC');
		// Reset the query using our newly populated query object.
		$db->setQuery($query);

		// Load the results as a list of stdClass objects.
		$results = $db->loadAssoc();

		if (isset($results['itemid']))
		{
			return $results['itemid'];
		}

		return 0;
	}

	/**
	 * RETURN THE BASE ROOT MENU ITEM ID
	 *
	 * @return   void
	 */
	private function _getRoot()
	{
		$menu_table = JTable::getInstance('menu');
		$menu_root_id = $menu_table->getRootId();

		return $menu_root_id;
	}

	/**
	 * Creates a placeholder for a menu item in #__menu and records the asset row to #__menuary
	 *
	 * @param   int     $context  com_categories.category or com_content.article
	 * @param   int     $id       category or article id
	 * @param   object  $params   rules params
	 *
	 * @return  int  Itemid of the inserted element
	 */
	private function _insertMenuItem($context, $id, $params)
	{
		$id = (int) $id;

		if (empty($context))
		{
			$context = 'com_categories.category';
		}

		// CREATE TEMPORARY MENU ITEM PLACEHOLDER
		$data = array('id' => '', 'menutype' => $params['menuname_alias'], 'alias' => microtime(), 'title' => time());

		$row = JTable::getInstance('menu');

		if (!$row->save($data))
		{
			JError::raiseError(500, 'MenuAry: ' . $row->getError());
		}

		$tempid = (int) $row->id;

		// SAVE TO THE MENUARY MENU REL TABLE
		$db = JFactory::getDBO();

		$query = "INSERT INTO " . $db->quoteName('#__menuary') . " SET "
			. $db->quoteName('itemid') . " = " . $db->quote($tempid) . ", "
			. $db->quoteName('content_id') . " = " . $db->quote($id) . ", "
			. $db->quoteName('ruleUniqID') . " = " . $db->quote($params['__ruleUniqID']) . ", "
			. $db->quoteName('context') . " = " . $db->quote($context);

		$db->setquery($query);
		$db->query();

		$this->rebuild = true;

		// RETURN THE NEW ID
		return $tempid;
	}

	/**
	 * Checks if passed alias already exsists for current menu and menu level
	 *
	 * If there is no such alias for current menu and level, then it returns
	 * the passed alias, otherwise it modifies the alias to make it unique
	 *
	 * @param   string  $alias      aritcle ot category alias
	 * @param   int     $id         article or category menu_itemId in #__menu, may be zero which means a new menu item
	 * @param   int     $parent_id  id of the parent menu
	 *
	 * @return void
	 */
	private function _checkAliasAndMakeOriginalIfNeeded($alias, $id, $parent_id)
	{
		$db = JFactory::getDBO();

		// QUICK LOOP TO CHECK IF ALIAS EXISTS - MAYBE REPLACE WITH DO...WHILE
		$origalias = $alias;
		$i  = 0;

		while (true)
		{
			$i++;
			$query = "SELECT " . $db->quoteName('id') . " FROM #__menu WHERE "
				. $db->quoteName('alias') . " = " . $db->quote($alias) . " AND "
				. $db->quoteName('parent_id') . " = " . $db->quote($parent_id) . " AND "
				. $db->quoteName('id') . " != " . $db->quote($id) . " LIMIT 1";

			$db->setQuery($query);
			$row = $db->loadColumn();

			if (!isset($row[0]))
			{
				return $alias;
			}
			else
			{
				$alias = $origalias . '-' . $i;
			}
		}
	}

	/**
	 * Try overriding system limits
	 *
	 * @return   void
	 */
	private function _setSystemLimits()
	{
		if (!isset($this->mem))
		{
			// WE'RE GOING TO RUN - SET A HIGHER TIME LIMIT & MEMORY LIMIT JUST IN CASE
			if (!ini_get('safe_mode'))
			{
				// 30 MINUTES
				set_time_limit(60 * 30);
				ini_set('memory_limit', '256M');
			}

			$this->mem = 1;
		}
	}

	/**
	 * Cleans up #__menu_menuary just in case there are orphans
	 *
	 * @return  void
	 */
	private function _removeOrphans ()
	{
		$db = JFactory::getDBO();

		// Build the query
		$query = 'DELETE t1.* FROM #__menuary AS t1
				LEFT JOIN #__menu AS t2 ON t1.itemid = t2.id
				LEFT JOIN #__categories AS t3 ON t1.content_id = t3.id
				LEFT JOIN #__content AS t4 ON t1.content_id = t4.id
				WHERE
					t2.id IS NULL
					OR
					(t3.id IS NULL AND t1.context = ' . $db->q('com_categories.category') . ')
					OR
					(t4.id IS NULL AND t1.context = ' . $db->q('com_content.article') . ')
				';

		if (!empty($this->ruleUniqIDs))
		{
			$ruleUniqIDs = array ();

			foreach ($this->ruleUniqIDs as $k => $v)
			{
				$ruleUniqIDs[] = $db->q($v);
			}

			$ruleUniqIDs = implode(',', $ruleUniqIDs);
			$query .= ' OR t1.ruleUniqID NOT IN (' . $ruleUniqIDs . ')';
		}

		/* This query cannot be implemented using modern joomla engine
		$query
				->select("; DELETE t1.*") // use fake select to fool joomla database driver
				* // ->append('t1.* FROM #__menuary AS t1')
				->from('#__menuary AS t1')
				->join('LEFT',
					'#__menu AS t2
					ON t1.itemid = t2.id
						')
				->where('t2.menutype =' . $db->quote($params['menuname_alias']));
		*/

		$db->setQuery($query);
		$db->query();

		// Remove orphan language associations
		$query = 'DELETE t1.* FROM #__associations AS t1
		LEFT JOIN #__menu as t2
		ON t1.id = t2.id
		WHERE t1.context = ' . $db->q('com_menus.item') . '
		AND t2.id IS NULL';

		$db->setQuery($query);
		$db->query();
	}

	/**
	 * Returns category link depending on global, category and/or plugin settings
	 *
	 * @param   obect  $category  Category object loaded usually from DB
	 * @param   array  $params    Current group of rules
	 *
	 * @return  text   Link to category
	 */
	public function _getCategoryLink($category, $params)
	{
		if ($params['category_link_type'] == '_:default')
		{
			$parameter = new JRegistry;
			$parameter->loadString($category->params, 'JSON');
			jimport('joomla.application.component.helper');

			// Can be _:default or _:blog. _:default means list
			$this->global_category_layout = JComponentHelper::getParams('com_content')->get('category_layout');
			$link_type = $parameter->get('category_layout', $this->global_category_layout);
		}
		else
		{
			$link_type = $params['category_link_type'];
		}

		// PREPARE LINK
		if ($link_type != '_:blog')
		{
			$category->link = 'index.php?option=com_content&view=category&id=' . $category->id;
		}
		else
		{
			$category->link = 'index.php?option=com_content&view=category&layout=blog&id=' . $category->id;
		}

		return $category->link;
	}

	/**
	 * Check if the current article or the category is amonng the categories selected in the ParamsGroup
	 *
	 * @param   object  $contentElement  Desc
	 * @param   string  $context         Context
	 * @param   type    $params          Desc
	 *
	 * @return  bool  true if the content element belongs to a category which has to handled by the current ParamsGroup
	 */
	private function _hasToBeHandledByTheParamsGroup($contentElement, $context, $params)
	{
		if ($context == 'com_categories.category' && in_array($contentElement->id, array_keys($params['categories_to_menu'])))
		{
			return true;
		}
		elseif ($context == 'com_content.article' )
		{
			if (in_array($contentElement->catid, array_keys($params['categories_to_menu'])))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * TODO Name or short description
	 *
	 * @param   string  $context         Context
	 * @param   object  $contentElement  Content element
	 *
	 * @return   type  Description
	 */
	public function onContentBeforeDelete($context, $contentElement)
	{
		if ($this->_forceGoOut($context))
		{
			return;
		}

		// So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled
		$this->_prepareParams();

		foreach ($this->pparams as $key => $params)
		{
			if (!$this->_hasToBeHandledByTheParamsGroup($contentElement, $context, $params))
			{
				continue;
			}

			$db = JFactory::getDBO();
			$query = $db->getQuery(true);
			$query
				->select('itemid')
				->from('`#__menuary`')
				->where('`context` = ' . $db->quote($context))
				->where('`content_id` = ' . $db->quote($contentElement->id))
				->where('`ruleUniqID` = ' . $db->quote($params['__ruleUniqID']));
			$db->setQuery($query);
			$res = $db->loadColumn();

			if (!empty($res))
			{
				foreach ($res as $k => $v)
				{
					$table = JTable::getInstance('menu');
					$table->delete($v);
				}

				$this->_removeOrphans();

				if (!$table->rebuild())
				{
					JError::raiseError(500, 'MenuAry: ' . $table->getError());
				}

				$msg = JText::_('PLG_MENUARY_MENUITEM_DELETED');
				$msgtype = 'notice';
				$this->_msg($msg . '. <b>Itemid</b>: ' . implode(',', $res), $msgtype);

				if ($params['articles_number'] > 0 && $context == 'com_content.article')
				{
					if ($params['show_articles'] == "1")
					{
						$this->_regenerateMenu($params);
					}
				}
				else
				{
					if (!$table->rebuild())
					{
						JError::raiseError(500, 'MenuAry: ' . $table->getError());
					}
				}
			}
		}

		if (!empty($this->messages))
		{
			$msg = implode(PHP_EOL, $this->messages);
			$this->_msg($msg, 'notice');
		}
	}

	/**
	 * This is a custom event not introduced by Joomla
	 *
	 * Is used in conjunction with overriding com_content and com_categories
	 * Reflect menuary menu items order accoridin to the source (article/category) order
	 *
	 * @param   string  $context  Context
	 * @param   array   $ids      Array of content ids
	 * @param   array   $order    Array of orders of content ids
	 *
	 * @return  void
	 */
	public function onContentReorder($context, $ids, $order)
	{
		$allowed_contexts = array('com_content.article', 'com_categories.category');

		if (!in_array($context, $allowed_contexts))
		{
			return;
		}

		// So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled
		$this->_prepareParams();

		$itemIds = array();
		$menuItemOrdering = array();

		foreach ($this->pparams as $k => $params)
		{
			// This makes sense only for menus which use joomla ordering
			switch ($context)
			{
				case 'com_content.article':
					if (!in_array($params['com_content.article_order'], array('ordering asc', 'ordering desc')))
					{
						return;
					}
					break;
				case 'com_categories.category':
					if (!in_array($params['com_categories.category_order'], array('order asc', 'order desc')))
					{
						return;
					}
					break;
				default :
					continue;
					break;
			}

			$db = JFactory::getDBO();
			$query = $db->getQuery(true);
			$query
				->select(array('itemid', 'content_id'))
				->from('`#__menuary`')
				->where('`context` = ' . $db->quote($context))
				->where('`content_id` IN (\'' . implode("','", $ids) . '\')');

			$query->where('`ruleUniqID` = ' . $db->q($params['__ruleUniqID']));
			$db->setQuery($query);

			$res = $db->loadAssocList('content_id');

			foreach ($ids as $k => $content_id)
			{
				if (!isset($res[$content_id]))
				{
					continue;
				}

				$itemId = $res[$content_id]['itemid'];

				$orderType = explode(' ', $params['com_content.article_order']);
				$orderType = end($orderType);

				if ($orderType == 'desc')
				{
					array_unshift($itemIds, $itemId);
				}
				else
				{
					$itemIds[] = $itemId;
				}

				$menuItemOrdering[] = $order[$k];
			}
		}

		if (!empty($itemIds))
		{
			$table = JTable::getInstance('menu');

			$table->saveorder($itemIds, $menuItemOrdering);
		}
	}

	/**
	 * Run plugin on change article state from article list.
	 *
	 * @param   string   $context  The context for the content passed to the plugin.
	 * @param   array    $ids      A list of primary key ids of the content that has changed state.
	 * @param   integer  $state    The value of the state that the content has been changed to.
	 *
	 * @return  boolean
	 */
	public function onContentChangeState($context, $ids, $state)
	{
		if ($this->_forceGoOut($context))
		{
			return;
		}

		// Just in case
		$ids = (array) $ids;

		// So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled
		$this->_prepareParams();

		$rebuild = false;

		foreach ($this->pparams as $k => $params)
		{
			$db = JFactory::getDBO();
			$query = $db->getQuery(true);
			$query
				->select('itemid')
				->from('`#__menuary`')
				->where('`context` = ' . $db->quote($context))
				->where('`content_id` IN (\'' . implode("','", $ids) . '\')');

			$query->where('`ruleUniqID` = ' . $db->q($params['__ruleUniqID']));
			$db->setQuery($query);
			$res = $db->loadColumn();

			if (count($res) > 0)
			{
				$table = JTable::getInstance('menu');
				$table->publish($res, $state);
				$rebuild = true;

				$this->_msg(JText::_('PLG_MENUARY_MENUITEM_STATE_UPDATED') . ': ' . count($res));

				if ($params['articles_number'] > 0 && $context == 'com_content.article' )
				{
					if ($params['show_articles'] == "1")
					{
						$params['regeneratemenu'] = 2;
						$this->_regenerateMenu($params);
					}
				}
			}
		}

		if ($rebuild)
		{
			$table = JTable::getInstance('menu');

			if (!$table->rebuild())
			{
				JError::raiseError(500, 'MenuAry: ' . $table->getError());
			}
		}

		if (!empty($this->messages))
		{
			$msg = implode(PHP_EOL, $this->messages);
			$this->_msg($msg, 'notice');
		}
	}

	/**
	 * TODO Name or short description
	 *
	 * @param   string  $context  Description
	 * @param   int     $id       Description
	 *
	 * @return   type  Description
	 */
	public function _getAssociations($context,$id)
	{
		$context = explode('.', $context);
		$extension = $context[0];
		$context = $extension . '.item';
		$tablename = '#__' . end(explode('_', $extension));

		// This would work, but gives an array with extra info
		// $associations = JLanguageAssociations::getAssociations($extension, $tablename, $context, $id);

		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query
			// ->select(array('id','parent_id','title','alias','published','access','language', 'level','lft','rgt'))
			->select(array('t2.id', 't3.language'))
			->from($db->qn('#__associations', 't1'))
			->join('INNER', $db->quoteName('#__associations', 't2') . ' ON (' . $db->quoteName('t1.key') . ' = ' . $db->quoteName('t2.key') . ')')
			->join('INNER', $db->quoteName($tablename, 't3') . ' ON (' . $db->quoteName('t3.id') . ' = ' . $db->quoteName('t2.id') . ')')
			->where($db->qn('t1.context') . ' = ' . $db->q($context))
			->where($db->qn('t2.context') . ' = ' . $db->q($context))
			->where($db->qn('t1.id') . ' = ' . $db->q($id))
			->where($db->qn('t2.id') . ' != ' . $db->q($id));
		$db->setQuery($query);

		$associations = $db->loadAssocList('language', 'id');

		if (!empty($associations))
		{
			return $associations;
		}

		return false;
	}

	/**
	 * TODO Name or short description
	 *
	 * Full description (multiline)
	 *
	 * @param   string  $context  Description
	 * @param   object  $article  Content item object, e.g. Joomla article
	 * @param   bool    $isNew    If article is new
	 *
	 * @return   type  Description
	 */
	public function onContentBeforeSave($context, $article, $isNew)
	{
		switch ($context)
		{
			case 'com_categories.category':
				$this->previous_object = JTable::getInstance('category');
				break;

			case 'com_content.article':
				$this->previous_object = JTable::getInstance('content');
			break;
		}

		if (isset($this->previous_object))
		{
			$this->previous_object->load($article->id);
			$this->previous_state = $this->previous_object->state;
		}
	}

	/**
	 * Desc
	 *
	 * @param   string  $context         Context
	 * @param   object  $contentElement  Content item object, e.g. Joomla article
	 * @param   bool    $isNew           If article is new
	 *
	 * @return   type  Description
	 */
	public function onContentAfterSave($context, $contentElement, $isNew)
	{
		if ($this->_forceGoOut($context))
		{
			return;
		}

		$this->rebuild = false;
		$itemIdsToBeClearedFromMenuAry = array();

		// So in $this->pparams only enabled rules are present, so later I don't care about if the current rule enabled
		$this->_prepareParams();
		$this->_removeOrphans();

		foreach ($this->pparams as $key => $params)
		{
			$Itemid = $this->_getMenuItemId($context, $contentElement->id, $params);

			if (!$this->_hasToBeHandledByTheParamsGroup($contentElement, $context, $params))
			{
				if ($Itemid != 0)
				{
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

			if ($Itemid == 0)
			{
				$isNew = true;
			}

			if ($isNew)
			{
				$this->rebuild = true;
			}

			// Find parent Itemid for the current $contentElement
			if ($params['target'] == 'menuitem')
			{
				$parent_Itemid = $params['target_menu_item'];
			}
			else
			{
				$parent_Itemid = 1;
			}

			$doNothing = false;

			switch ($context)
			{
				case 'com_content.article':
					if ($params['show_articles'])
					{
						$current_contentElement = $contentElement;
						$parent_category_id = $current_contentElement->catid;
					}
					else
					{
						$doNothing = true;
					}
					break;
				case 'com_categories.category':

					// Get menuary category object
					$current_contentElement = $params['categories_to_menu'][$contentElement->id];
					$current_contentElement->state = $current_contentElement->published;

					// If current category ha
					if ($current_contentElement->hide_category)
					{
						$doNothing = true;
						break;
					}

					$parent_category_id = $current_contentElement->parent_id;
					break;
				default :
					break;
			}

			if ($doNothing)
			{
				continue;
			}

			$current_contentElement->context = $context;
			$current_contentElement->Itemid = $Itemid;

			/*  We handle the situation which should not happen normally.
			 * Is there is an article/category which has to be added or updated,
			 * but it's parent category is not yet added as a menu item.
			 * Normally all parent categories should be added when saving the plugin.
			 */
			$path_to_be_created = array($current_contentElement);

			while (!empty($params['categories_to_menu'][$parent_category_id]))
			{
				$current_contentElement = $params['categories_to_menu'][$parent_category_id];
				$current_contentElement->context = 'com_categories.category';

				if ($current_contentElement->hide_category)
				{
					break;
				}

				$Itemid = $this->_getMenuItemId($current_contentElement->context, $current_contentElement->id, $params);

				$current_contentElement->Itemid = $Itemid;

				if ($Itemid == 0)
				{
					$parent_category_id = $current_contentElement->parent_id;
					$path_to_be_created[] = $current_contentElement;
				}
				else
				{
					$path_to_be_created[count($path_to_be_created) - 1]->parent_Itemid = $Itemid;
					break;
				}
			}

			$path_to_be_created = array_reverse($path_to_be_created);

			foreach ($path_to_be_created as $key => $path_object)
			{
				if ($path_object->context == 'com_content.article' && $params['articles_number'] > 0 )
				{
					// If $params['articles_number'] > 0 and a non-published article is added, then don't care about the menu
					if ($isNew && $path_object->state != 1)
					{
						continue;
					}

					if ($this->previous_state != $path_object->state && ($path_object->state == 1 | $this->previous_state == 1))
					{
						if ($params['show_articles'] == "1")
						{
							$params['regeneratemenu'] = 2;
							$this->_regenerateMenu($params);

							continue;
						}
					}
				}

				if (!empty($path_object->parent_Itemid) && $path_object->parent_Itemid != 0)
				{
					$parent_Itemid = $path_object->parent_Itemid;
				}

				$path_object->alias = $this->_checkAliasAndMakeOriginalIfNeeded($path_object->alias, $path_object->Itemid, $parent_Itemid);

				switch ($path_object->context)
				{
					case 'com_content.article':
						$path_object->link = 'index.php?option=com_content&view=article&id=' . $path_object->id;
						break;
					case 'com_categories.category':
						$path_object->link = $this->_getCategoryLink($path_object, $params);
						break;
					default :
						break;
				}

				// BUILD THE DATA ARRAY
				$array = array(
					'menutype' => $params['menuname_alias'],
					'title' => $path_object->title,
					'alias' => $path_object->alias,
					'link' => $path_object->link,
					'type' => 'component',
					'published' => $path_object->state,
					'state' => $path_object->state,
					'parent_id' => $parent_Itemid,
					// 'level' => isset($path_object->level)?$path_object->level:'',
					'component_id' => 22,
					'access' => $path_object->access,
					'language' => $path_object->language,
					'contentid' => $path_object->id,
					'id' => $path_object->Itemid
				);

				$parent_Itemid = $this->_saveMenuItem(
					$path_object->context /*values:category|article*/,
					$array /*array of data to be stored*/,
					$params /*current rule params*/,
					$isNew
				);

				if ($isNew )
				{
					$this->_createMenuAryRecord($context = $context, $Itemid = $parent_Itemid, $content_id = $path_object->id, $params);
				}

				if ($isNew )
				{
					$msg = JText::_('PLG_MENUARY_MENUITEM_CREATED');
					$msgtype = 'notice';
				}
				else
				{
					$msg = JText::_('PLG_MENUARY_MENUITEM_UPDATED');
					$msgtype = 'message';
				}

				$this->_msg($msg . '. <b>Itemid</b>: ' . $parent_Itemid, $msgtype);
			}
		}

		if (!empty($this->itemIdsToBeClearedFromMenuAry))
		{
			foreach ($this->itemIdsToBeClearedFromMenuAry as $k => $v)
			{
				$this->_removeMenuAndAryRecord($v);
				$menu_table = JTable::getInstance('menu');

				// It's strange, but is passing an array of values, then it removed ALL menu items.
				$menu_table->delete($v->Itemid);

				$this->_msg(JText::_('PLG_MENUARY_MENUITEM_DELETED') . '. <b>Itemid</b>: ' . $v->Itemid, 'notice');

				// Regenerate menu if the items which is saved is NO MORE handled by the plugin
				$params = $this->pparams[$obj->paramkey];

				if ($params['articles_number'] > 0 && $context == 'com_content.article')
				{
					if ($params['show_articles'] == "1")
					{
						$this->_regenerateMenu($params);
					}
				}
			}
		}

		if ($this->rebuild)
		{
			$table = JTable::getInstance('menu');

			if (!$table->rebuild())
			{
				JError::raiseError(500, 'MenuAry: ' . $table->getError());
			}

			$this->_removeOrphans();
		}

		if (!empty($this->messages))
		{
			$this->_msg(implode(PHP_EOL, $this->messages), 'notice');
		}
	}

	/**
	 * TODO Name or short description
	 *
	 * @param   object  $obj  Description
	 *
	 * @return   void
	 */
	private function _removeMenuAndAryRecord ($obj)
	{
		$db = JFactory::getDBO();
		$query = 'DELETE FROM #__menuary
			WHERE Itemid =' . $db->quote($obj->Itemid)
				. ' AND content_id =' . $db->quote($obj->content_id)
				. ' AND context =' . $db->quote($obj->context)
				. ' AND ruleUniqID =' . $db->quote($obj->ruleUniqID);
		$db->setQuery($query);
		$db->query();
	}

	/**
	 * TODO Name or short description
	 *
	 * @param   string  $msg   Description
	 * @param   string  $type  Description
	 *
	 * @return   type  Description
	 */
	private function _msg($msg, $type = 'message')
	{
		JFactory::getApplication()->enqueueMessage('<small>' . JText::_('PLG_MENUARY') . ': ' . $msg . '</small>', $type);
	}

	/**
	 * Created a menuary record for the curent article or a category
	 *
	 * Stories a row with the content elemtn (article or category) id and corresponding menu Itemid
	 *
	 * @param   string  $context     Description
	 * @param   int     $Itemid      Description
	 * @param   int     $content_id  Description
	 * @param   type    $params      Description
	 *
	 * @return  type  Description
	 */
	public function _createMenuAryRecord($context, $Itemid, $content_id, $params  )
	{
		// SAVE TO THE MENUARY MENU REL TABLE
		$db = JFactory::getDBO();
		$query = "INSERT INTO " . $db->quoteName('#__menuary')
			. " SET "
			. $db->quoteName('itemid') . " = " . $db->quote($Itemid) . ", "
			. $db->quoteName('content_id') . " = " . $db->quote($content_id) . ", "
			. $db->quoteName('context') . " = " . $db->quote($context) . ','
			. $db->quoteName('ruleUniqID') . " = " . $db->quote($params['__ruleUniqID']);

		$db->setquery($query);
		$db->query();
		$this->rebuild = true;
	}

	/**
	 * The entry point of the plugin
	 *
	 * @return   void
	 */
	public function onAfterInitialise()
	{
		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump')
		{
			return;
		}

		$option = $this->_getOption('option');

		$allowed_options = array('com_content', 'com_categories');

		if (!empty($option) && !in_array($option, $allowed_options))
		{
			return;
		}

		$task = $this->_getOption('task');
		$task = explode('.', $task);
		$task = end($task);

		$allowed_tasks = array('saveOrderAjax');

		if (!empty($task) && !in_array($task, $allowed_tasks))
		{
			return;
		}

		if (!defined('JPATH_SOURCEMENUARY_COMPONENT'))
		{
			// Constants to replace JPATH_COMPONENT, JPATH_COMPONENT_SITE and JPATH_COMPONENT_ADMINISTRATOR
			define('JPATH_SOURCEMENUARY_COMPONENT', JPATH_BASE . '/components/' . $option);
			define('JPATH_SOURCEMENUARY_COMPONENT_SITE', JPATH_SITE . '/components/' . $option);
			define('JPATH_SOURCEMENUARY_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/' . $option);
		}

		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		$this->_autoOverride();
	}

	/**
	 * Get's current $option as it's not defined at onAfterInitialize
	 *
	 * @param   string  $var  Variable to get from the URL
	 *
	 * @return  string   Option, i.e. com_content
	 */
	public function _getOption($var = 'option')
	{
		$jinput = JFactory::getApplication()->input;
		$var = $jinput->get($var, null);

		if (empty($var) && JFactory::getApplication()->isAdmin())
		{
			$app = JFactory::getApplication();
			$router = $app->getRouter();
			$uri     = clone JUri::getInstance();

			return $uri->getVar($var);
		}

		return $var;
	}

	/**
	 * Trims the last ?> closing tag
	 *
	 * @param   string  $bufferContent  PHP code
	 *
	 * @return   string  Ready for eval code
	 */
	public function _trimEndClodingTag($bufferContent)
	{
		$bufferContent = explode('?>', $bufferContent);

		$last = end($bufferContent);

		if (JString::trim($last) == '')
		{
			array_pop($bufferContent);
		}

		$bufferContent = implode('?>', $bufferContent);

		return $bufferContent;
	}

	/**
	 * Looks for code folder in three places and overrides if possible
	 *
	 * @return   void
	 */
	private function _autoOverride()
	{
		if (JFactory::getApplication()->input->get('option', null) == 'com_dump')
		{
			return;
		}

		$option = $this->_getOption();

		// Application name
		// $applicationName = JFactory::getApplication()->getName();

		// Template name
		// ~ $template = JFactory::getApplication()->getTemplate();

		$includePath = array(JPATH_ROOT . '/plugins/' . $this->plg_type . '/' . $this->plg_name . '/code');

		$files_to_override = array();

		foreach ($includePath as $k => $codefolder)
		{
			if (JFolder::exists($codefolder))
			{
				$files = str_replace($codefolder, '', JFolder::files($codefolder, '.php', true, true));
				$files = array_fill_keys($files, $codefolder);
				$files_to_override = array_merge($files_to_override, $files);
			}
		}

		// Change order to load libraries at first

		/*
		$tmp_arr = array();

		if (isset($files_to_override['/libraries/joomla/form/fields/text.php']) && isset($files_to_override['/libraries/joomla/form/field.php']))
		{
			$fflls = array('/libraries/joomla/form/field.php', '/libraries/joomla/form/fields/text.php');

			foreach ($fflls as $ffll)
			{
				$tmp_arr[$ffll] = $files_to_override[$ffll];
				unset($files_to_override[$ffll]);
			}
		}

		foreach ($files_to_override as $fileToOverride => $overriderFolder)
		{
			if (strpos($fileToOverride, '/libraries/') === 0)
			{
				$tmp_arr[$fileToOverride] = $overriderFolder;
				unset($files_to_override[$fileToOverride]);
			}
		}

		$files_to_override = array_merge($tmp_arr, $files_to_override);
		unset ($tmp_arr);
		*/

		if (empty($files_to_override))
		{
			return;
		}

		// Check scope condition
		$scope = '';

		if (JFactory::getApplication()->isAdmin())
		{
			$scope = 'administrator';
		}

		// Do not override wrong scope for components
		foreach ($files_to_override as $fileToOverride => $overriderFolder)
		{
			if (JFactory::getApplication()->isAdmin())
			{
				if (strpos($fileToOverride, '/com_') === 0)
				{
					unset($files_to_override[$fileToOverride]);
				}
			}
			else
			{
				if (strpos($fileToOverride, '/administrator/com_') === 0)
				{
					unset($files_to_override[$fileToOverride]);
				}
			}
		}

		// Loading override files
		foreach ($files_to_override as $fileToOverride => $overriderFolder)
		{
			if (JFile::exists(JPATH_ROOT . $fileToOverride))
			{
				$originalFilePath = JPATH_ROOT . $fileToOverride;
			}
			elseif (strpos($fileToOverride, '/com_') === 0 && JFile::exists(JPATH_ROOT . '/components' . $fileToOverride))
			{
				$originalFilePath = JPATH_ROOT . '/components' . $fileToOverride;
			}
			else
			{
				JLog::add("Can see an overrider file ($overriderFolder" . "$fileToOverride) , but cannot find what to override", JLog::INFO, $this->plg_name);
				continue;
			}

			// Do not run override if current option and the default path option are different
			// Avoid loading classes when not needed
			preg_match('~.*/(com_[^/]*)/.*~Ui', $originalFilePath, $matches);

			if (!empty($matches[1]) && $matches[1] != $option )
			{
				continue;
			}

			// Include the original code and replace class name add a Default on
			$bufferFile = file_get_contents($originalFilePath);

			if (strpos($originalFilePath, '/controllers/') !== false )
			{
				$temp = explode('/controllers/', $originalFilePath);
				require_once $temp[0] . '/controller.php';
			}

			// Detect if source file use some constants
			preg_match_all('/JPATH_COMPONENT(_SITE|_ADMINISTRATOR)|JPATH_COMPONENT/i', $bufferFile, $definesSource);

			$overriderFilePath = $overriderFolder . $fileToOverride;
			$bufferOverrideFile = file_get_contents($overriderFilePath);

			// Detect if override file use some constants
			preg_match_all('/JPATH_COMPONENT(_SITE|_ADMINISTRATOR)|JPATH_COMPONENT/i', $bufferOverrideFile, $definesSourceOverride);

			// Append "Default" to the class name (ex. ClassNameDefault). We insert the new class name into the original regex match to get
			$rx = '/class *[a-z0-9]* *(extends|{|\n)/i';

			preg_match($rx, $bufferFile, $classes);

			if (empty($classes))
			{
				$rx = '/class *[a-z0-9]*/i';
				preg_match($rx, $bufferFile, $classes);
			}

			$parts = explode(' ', $classes[0]);

			$originalClass = $parts[1];

			$replaceClass = trim($originalClass) . 'Default';

			if (count($definesSourceOverride[0]))
			{
				$error = 'Plugin MenuAry:: Your override file use constants, please replace code constants<br />JPATH_COMPONENT -> JPATH_SOURCEMENUARY_COMPONENT,'
					. '<br />JPATH_COMPONENT_SITE -> JPATH_SOURCEMENUARY_COMPONENT_SITE and<br />'
					. 'JPATH_COMPONENT_ADMINISTRATOR -> JPATH_SOURCEMENUARY_COMPONENT_ADMINISTRATOR';
				throw new Exception(str_replace('<br />', PHP_EOL, $error), 500);

				// JFactory::getApplication()->enqueueMessage($error, 'error');
			}
			else
			{
				// Replace original class name by default
				$bufferContent = str_replace($originalClass, $replaceClass, $bufferFile);

				// Replace JPATH_COMPONENT constants if found, because we are loading before define these constants
				if (count($definesSource[0]))
				{
					$bufferContent = preg_replace(
						array('/JPATH_COMPONENT/','/JPATH_COMPONENT_SITE/','/JPATH_COMPONENT_ADMINISTRATOR/'),
						array('JPATH_SOURCEMENUARY_COMPONENT','JPATH_SOURCEMENUARY_COMPONENT_SITE','JPATH_SOURCEMENUARY_COMPONENT_ADMINISTRATOR'),
						$bufferContent
					);
				}

				// Change private methods to protected methods
				if ($this->params->get('changePrivate', 0))
				{
					$bufferContent = preg_replace('/private *function/i', 'protected function', $bufferContent);
				}

				// Finally we can load the base class
				$bufferContent = $this->_trimEndClodingTag($bufferContent);
				eval('?>' . $bufferContent . PHP_EOL . '?>');

				require $overriderFilePath;

				if ($this->paramGet('bruteMode') == 1 )
				{
					JFile::move($originalFilePath, $originalFilePath . '1');
					file_put_contents($originalFilePath, '');
					require $originalFilePath;
					JFile::move($originalFilePath . '1', $originalFilePath);
				}
			}
		}
	}
}
}
