/**
 * @copyright	Copyleft (C) All rights reversed.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

window.addEvent('domready', function() {
	Joomla.submitbutton = function(task) {
		var isAjax = jQuery('#jform_params_ajax').val();
		if (typeof force_no_ajax === 'undefined'  || !force_no_ajax) {
		} else {isAjax = 0;}
		if((task == 'plugin.save' || task == 'plugin.apply') && isAjax == '0') {
			var flag = false;
			var enabled_plugin = $('jform_enabled');
			if (enabled_plugin.tagName == 'FIELDSET') {
				enabled_plugin = $('jform_enabled').getElements('label[for=jform_enabled0]');
				if (enabled_plugin.hasClass('active') == 'true') {
					flag = true;
				}
			}
			else { // J2.5
				if (enabled_plugin && enabled_plugin.value === '1')  {
					flag = true;
				}
			}
			if (flag) {
				var enabled_rules = document.getElementsByName('jform[params][{menugroup][ruleEnabled][]');
				var rules = $$('div.variablegroup__menugroup');
				// Remove disabled rules

				var menu_names_used = Array();
				for (var k = 0; k <rules.length; k++)	{
					//var enabled_rules = rules[k].getElements('select[name=jform[params][{menugroup][ruleEnabled][]]');
					var rule_enabled = rules[k].getElements('select[name="jform[params][{menugroup][ruleEnabled][]"]');
					//var enabled_rules = rules[k].getElements('input[name=jform\[params\]\[{menugroup\]\[{menugroup\]]');
					if (rule_enabled[0].value == '0') {
						rules.splice(k,1);
						continue;
					}

					var regen = rules[k].getElements('select[name="jform[params][{menugroup][regeneratemenu][]"]');
					if (regen[0].value !== '0') {
						window.regenerate_menuary = '1';
						document.getElementById('menuary_popup').style.display = 'block';
						document.getElementById('blackout').style.display = 'block';
					}

					var target = rules[k].getElements('select[name="jform[params][{menugroup][target][]"]');
					if (target[0].value != 'root') {

						continue;
					}
					var menu_name = rules[k].getElements('input[name="jform[params][{menugroup][menuname][]"]');

					if (menu_names_used[menu_name[0].value] !== true ) {
						menu_names_used[menu_name[0].value] = true;
					}
					else {
						alert(document.menuary_var_lang['PLG_MENUARY_MENU_NAME_DUPLICATED_JS']);
						menu_name0[k].style.color = "Red";
						menu_name0[k].style.border = "2px solid red";
						return false;
					}
					//rules.splice(k,1);
				}

			}
		}

		if (task == 'plugin.cancel' || document.formvalidator.isValid(document.id('style-form'))) {
			Joomla.submitform(task, document.getElementById('style-form'));
		}
	}
});
