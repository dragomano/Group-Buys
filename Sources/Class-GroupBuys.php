<?php

/**
 * @package Group Buys
 * @link https://dragomano.ru/mods/group-buys
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2018-2026 Bugo
 * @license https://opensource.org/licenses/MIT The MIT License
 */

if (! defined('SMF'))
	die('No direct access...');

class GroupBuys
{
	private const DEFAULT_CURRENCIES = ['RUB', 'USD', 'EUR', 'UAH'];

	public function hooks(): void
	{
		add_integration_function('integrate_load_theme', self::class . '::loadTheme#', false);
		add_integration_function('integrate_actions', self::class . '::actions#', false);
		add_integration_function('integrate_load_permissions', self::class . '::loadPermissions#', false);
		add_integration_function('integrate_load_illegal_guest_permissions', self::class . '::illegalGuestPermissions#', false);
		add_integration_function('integrate_display_buttons', self::class . '::displayButtons#', false);
		add_integration_function('integrate_prepare_display_context', self::class . '::prepareDisplayContext#', false);
		add_integration_function('integrate_profile_popup', self::class . '::profilePopup#', false);
		add_integration_function('integrate_profile_areas', self::class . '::profileAreas#', false);
		add_integration_function('integrate_admin_areas', self::class . '::adminAreas#', false);
		add_integration_function('integrate_admin_search', self::class . '::adminSearch#', false);
		add_integration_function('integrate_modify_modifications', self::class . '::modifyModifications#', false);
	}

	public function loadTheme(): void
	{
		global $context, $settings;

		loadLanguage('GroupBuys/');

		$context['make_group_buys']       = allowedTo('make_group_buys');
		$context['group_buys_currencies'] = $this->currencies();

		if ( ! empty($_REQUEST['topic']) || (! empty($context['current_action']) && $context['current_action'] === 'profile')) {
			$context['html_headers'] .= '
		<link rel="stylesheet" href="' . $settings['default_theme_url'] . '/css/group_buys.css" />';

			// No external modal plugins: the windows are native popovers (Popover API)
			$context['insert_after_template'] .= '
		<script>
			document.addEventListener("click", function(e) {
				var trigger = e.target.closest("[data-popovertarget]");
				if (!trigger)
					return;
				var popover = document.getElementById(trigger.getAttribute("data-popovertarget"));
				if (popover && typeof popover.showPopover === "function") {
					e.preventDefault();
					popover.showPopover();
				}
			});

			document.querySelectorAll("form[name^=group_buy_form] [required]").forEach(function(field) {
				var msg = field.getAttribute("placeholder");
				if (!msg)
					return;

				field.setCustomValidity(msg);
				field.addEventListener("input", function() {
					this.setCustomValidity(this.value.trim() === "" ? msg : "");
				});
			});
		</script>';
		}
	}

	public function actions(array &$action_array): void
	{
		$action_array['group_buys'] = [false, [$this, 'subactions']];
	}

	public function subactions(): void
	{
		$subActions = [
			'add'  => [$this, 'addGroupBuy'],
			'edit' => [$this, 'editGroupBuy'],
		];

		if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']])) {
			call_user_func($subActions[$_REQUEST['sa']]);
		}
	}

	/**
	 * Settings area: Administration Center - Configuration - Modifications
	 */
	public function adminAreas(array &$admin_areas): void
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['group_buys'] = [$txt['gb_settings']];
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search): void
	{
		$settings_search[] = [[$this, 'settings'], 'area=modsettings;sa=group_buys'];
	}

	public function modifyModifications(array &$sub_actions): void
	{
		$sub_actions['group_buys'] = [$this, 'settings'];
	}

	public function settings(bool $return_config = false)
	{
		global $context, $txt, $scripturl;

		$context['page_title'] = $context['settings_title'] = $txt['gb_settings'];
		$context['post_url']   = $scripturl . '?action=admin;area=modsettings;save;sa=group_buys';

		$config_vars = [
			[
				'large_text',
				'gb_currencies',
				'subtext' => $txt['gb_currencies_desc'],
				'value'   => implode("\n", $this->currencies()),
				'rows'    => 6,
			],
			'',
			['title', 'gb_permissions', 'text_label' => $txt['membergroups_permissions']],
			['permissions', 'make_group_buys'],
			['permissions', 'view_group_buys'],
		];

		if ($return_config)
			return $config_vars;

		$context[$context['admin_menu_name']]['tab_data'] = [
			'title'       => $txt['gb_settings'],
			'description' => $txt['gb_settings_desc'],
		];

		if (isset($_GET['save'])) {
			checkSession();

			saveDBSettings($config_vars);
			$this->updateDefaultPermissions();

			redirectexit('action=admin;area=modsettings;sa=group_buys');
		}

		prepareDBSettingContext($config_vars);
	}

	public function loadPermissions(array &$permissionGroups, array &$permissionList): void
	{
		$permissionGroups['membergroup']['simple']  = ['group_buys'];
		$permissionGroups['membergroup']['classic'] = ['group_buys'];

		$permissionList['membergroup']['make_group_buys'] = [false, 'group_buys', 'group_buys'];
		$permissionList['membergroup']['view_group_buys'] = [false, 'group_buys', 'group_buys'];
	}

	public function illegalGuestPermissions(): void
	{
		global $context;

		$context['non_guest_permissions'] = array_merge($context['non_guest_permissions'], ['make_group_buys', 'view_group_buys']);
	}

	public function displayButtons(array &$normal_buttons): void
	{
		global $context, $user_info, $sourcedir, $smcFunc;

		if ($context['topic_starter_id'] != $user_info['id'] || ! allowedTo('make_group_buys'))
			return;

		require_once($sourcedir . '/Subs-Post.php');

		$request = $smcFunc['db_query']('', '
			SELECT id, price, currency, requisites
			FROM {db_prefix}group_buys_items
			WHERE topic_id = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => $context['current_topic'],
			],
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			censorText($row['requisites']);

			$context['group_buy'] = [
				'id'         => $row['id'],
				'price'      => $row['price'],
				'currency'   => $row['currency'],
				'requisites' => un_preparsecode($row['requisites']),
			];
		}

		$smcFunc['db_free_result']($request);

		$counter = isset($normal_buttons['reply']) ? 2 : 1;

		$normal_buttons = array_merge(
			array_slice($normal_buttons, 0, $counter, true),
			empty($context['group_buy']['id']) ? [
				'add_group_buy' => [
					'test'   => 'make_group_buys',
					'text'   => 'gb_add_group_buy',
					'image'  => 'im_reply.gif',
					'lang'   => true,
					'url'    => '#group_buy',
					'custom' => 'data-popovertarget="group_buy"',
				],
			] : [
				'edit_group_buy' => [
					'test'   => 'make_group_buys',
					'text'   => 'gb_edit_group_buy',
					'image'  => 'im_reply.gif',
					'lang'   => true,
					'url'    => '#group_buy',
					'custom' => 'data-popovertarget="group_buy"',
				],
			],
			array_slice($normal_buttons, $counter, null, true)
		);

		loadTemplate('GroupBuys');

		$context['template_layers'][] = 'display';
	}

	public function prepareDisplayContext(array &$output): void
	{
		global $context, $smcFunc, $sourcedir, $txt, $boardurl, $user_info, $scripturl;

		if ($output['id'] == $context['topic_first_message']) {
			$request = $smcFunc['db_query']('', '
				SELECT m.member_id AS id, COALESCE(mem.member_name, \'0\') AS member_name, m.date AS joined
				FROM {db_prefix}group_buys_members AS m
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.member_id)
				WHERE m.topic_id = {int:current_topic}',
				[
					'current_topic' => $context['current_topic'],
				],
			);

			$members = [];
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$members[$row['id']] = [
					'name' => $row['member_name'],
					'date' => empty($row['joined']) ? '' : timeformat($row['joined']),
				];
			}

			$smcFunc['db_free_result']($request);

			// The topic starter always sees the list of participants
			if (! empty($members) && $output['is_message_author']) {
				$output['body'] .= '
				<div class="group_buy_list information smalltext">
					<div class="centertext">
						<h5>' . $txt['gb_group_buy_list'] . '</h5>
						<hr />
					</div>
					<ul class="columns">';

				foreach ($members as $id => $member)
					$output['body'] .= '
						<li><a href="' . $scripturl . '?action=profile;u=' . $id . '">' . $member['name'] . '</a>'
						. ($member['date'] !== '' ? ' <span class="gb_join_date">(' . $member['date'] . ')</span>' : '')
						. '</li>';

				$output['body'] .= '
					</ul>
				</div>';
			}

			// Everyone else (including guests) can see group buy info only with the appropriate permission
			if (empty($output['is_message_author']) && allowedTo('view_group_buys')) {
				if (! empty($members)) {
					$output['body'] .= '
				<hr />
				<div class="smalltext">' . $txt['gb_group_buy_list1'] . count($members) . '</div>';
				}

				$request = $smcFunc['db_query']('', '
					SELECT requisites
					FROM {db_prefix}group_buys_items
					WHERE topic_id = {int:current_topic}',
					[
						'current_topic' => $context['current_topic'],
					],
				);

				[$requisites] = $smcFunc['db_fetch_row']($request);

				$smcFunc['db_free_result']($request);

				if (! empty($requisites)) {
					require_once($sourcedir . '/Subs-Post.php');

					if (isset($members[$user_info['id']])) {
						$output['body'] .= '
				<br />
				<input class="button" type="button" value="' . $txt['gb_join_ready'] . '" disabled />';
					} else {
					$output['body'] .= '
				<hr />
				<a href="#join_modal" data-popovertarget="join_modal" class="button">' . $txt['gb_join_to_group_buy'] . '</a>
				<div id="join_modal" class="join_modal modal" popover="auto">
					<div class="modal-content">
						<div class="centertext">
							<h3>' . $txt['gb_group_buy_info'] . '</h3>
							<div class="information">' . strtr($txt['gb_group_buy_text'], ['{DATA}' => un_preparsecode($requisites), '{LINK}' => $boardurl, '{USER}' => $user_info['id']]) . '</div>
							<button type="button" class="button" popovertarget="join_modal" popovertargetaction="hide">' . $txt['gb_group_buy_ok'] . '</button>
						</div>
					</div>
				</div>';
					}
				}
			}
		}
	}

	public function profilePopup(array &$profile_items): void
	{
		global $txt;

		$counter = 0;
		foreach ($profile_items as $item) {
			$counter++;

			if ($item['area'] === 'showdrafts')
				break;
		}

		$profile_items = array_merge(
			array_slice($profile_items, 0, $counter, true),
			[
				[
					'menu'  => 'info',
					'area'  => 'group_buys',
					'title' => $txt['gb_group_buys'],
				],
			],
			array_slice($profile_items, $counter, null, true)
		);
	}

	public function profileAreas(array &$profile_areas): void
	{
		global $txt, $context;

		$profile_areas['info']['areas']['group_buys'] = [
			'label'      => $txt['gb_group_buys'],
			'function'   => self::class . '::groupBuyProfile#',
			'icon'       => 'starters',
			'enabled'    => $context['user']['is_owner'],
			'permission' => [
				'own' => 'profile_view_own',
				'any' => 'profile_view_any',
			],
		];
	}

	public function groupBuyProfile(int $memID): void
	{
		global $context, $txt, $scripturl, $user_info, $user_profile, $smcFunc;

		$context['start']          = (int) $_REQUEST['start'];
		$context['current_member'] = $memID;

		loadLanguage('Modlog');

		$context[$context['profile_menu_name']]['tab_data'] = [
			'title'       => $txt['gb_group_buys'],
			'description' => '',
			'icon_class'  => 'main_icons starters icon',
		];

		$num = 10;

		$request = $smcFunc['db_query']('', 'SELECT COUNT(id) FROM {db_prefix}group_buys_items', []);

		[$total] = $smcFunc['db_fetch_row']($request);

		$smcFunc['db_free_result']($request);

		$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=group_buys', $context['start'], $total, $num);

		$start   = $context['start'];
		$reverse = $context['start'] > $num / 2;

		if ($reverse) {
			$num   = $total < $context['start'] + $num + 1 && $total > $context['start'] ? $total - $context['start'] : $num;
			$start = $total < $context['start'] + $num + 1 || $total < $context['start'] + $num ? 0 : $total - $context['start'] - $num;
		}

		$counter = $reverse ? $context['start'] + $num + 1 : $context['start'];

		$request = $smcFunc['db_query']('', '
			SELECT c.id, c.price, c.requisites, t.id_topic, m.subject, m.body, m.poster_time
			FROM {db_prefix}group_buys_items AS c
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = c.topic_id)
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_member_started = {int:current_user}
				ORDER BY m.poster_time ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT {int:num} OFFSET {int:start}',
			[
				'current_user' => $context['current_member'],
				'start'        => $start,
				'num'          => $num,
			],
		);

		$topics = [];
		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			censorText($row['subject']);

			$topics[] = $row['id_topic'];

			$context['items'][$counter += $reverse ? -1 : 1] = [
				'id'         => $row['id'],
				'counter'    => $counter,
				'alternate'  => $counter % 2,
				'topic'      => $row['id_topic'],
				'title'      => $row['subject'],
				'date'       => timeformat($row['poster_time']),
				'text'       => parse_bbc($row['body']),
				'requisites' => parse_bbc($row['requisites']),
			];
		}

		$smcFunc['db_free_result']($request);

		if (! empty($topics)) {
			$request = $smcFunc['db_query']('', '
				SELECT topic_id, id_member, COALESCE(member_name, \'0\') AS member_name
				FROM {db_prefix}group_buys_members
					INNER JOIN {db_prefix}members ON (id_member = member_id)
				WHERE topic_id IN ({array_int:topics})',
				[
					'topics' => $topics,
				],
			);

			$context['group_buys_members'] = [];
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$context['group_buys_members'][$row['topic_id']][$row['id_member']] = $row['member_name'];
			}

			$smcFunc['db_free_result']($request);
		}

		if ($reverse) {
			$context['items'] = array_reverse($context['items'], true);
		}

		$context['gb_can_manage'] = $context['current_member'] == $user_info['id'];

		if ($context['gb_can_manage']) {
			if (isset($_POST['members'])) {
				$members = explode(',', trim($_POST['members'], ' '));

				$request = $smcFunc['db_query']('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE real_name IN ({array_string:names})',
					[
						'names' => $members,
					],
				);

				while ($row = $smcFunc['db_fetch_assoc']($request)) {
					$ids[] = $row;
				}

				$smcFunc['db_free_result']($request);

				if (! empty($ids)) {
					foreach ($ids as $member) {
						$smcFunc['db_insert']('replace',
							'{db_prefix}group_buys_members',
							[
								'member_id' => 'int',
								'topic_id'  => 'int',
								'date'      => 'int',
							],
							[
								(int) $member['id_member'],
								(int) $_POST['topic'],
								time(),
							],
							['member_id', 'topic_id'],
						);
					}
				}
			}

			// Remove one participant from a group buy
			if (isset($_REQUEST['del_member'], $_REQUEST['del_topic'])) {
				checkSession('request');

				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}group_buys_members
					WHERE member_id = {int:member}
						AND topic_id = {int:topic}',
					[
						'member' => (int) $_REQUEST['del_member'],
						'topic'  => (int) $_REQUEST['del_topic'],
					],
				);

				$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

				redirectexit('action=profile;area=group_buys;start=' . $start . ';u=' . $memID);
			}

			if (isset($_REQUEST['del_item'])) {
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}group_buys_items
					WHERE topic_id = {int:topic}',
					[
						'topic' => (int) $_REQUEST['del_item'],
					],
				);

				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}group_buys_members
					WHERE topic_id = {int:topic}',
					[
						'topic' => (int) $_REQUEST['del_item'],
					],
				);

				$redirect = ! empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?action=profile;area=group_buys;u=' . $memID;

				if (isset($_REQUEST['profile'], $_REQUEST['start'], $_REQUEST['u'])) {
					redirectexit('action=profile;area=group_buys;start=' . $_REQUEST['start'] . ';u=' . $_REQUEST['u']);
				} else {
					redirectexit($redirect);
				}
			}
		}

		loadTemplate('GroupBuys');

		// The username autocomplete inside the add member windows
		loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');

		$context['sub_template'] = 'profile';
		$context['page_title']   = $txt['gb_group_buys'] . ' - ' . $user_profile[$memID]['real_name'];
	}

	/**
	 * When the "View group buys" permission is switched off for all regular groups
	 * in the settings above, grant it back to the regular members group,
	 * otherwise every non-author loses access to group buys at once.
	 */
	private function updateDefaultPermissions(): void
	{
		global $smcFunc, $sourcedir;

		if (isset($_POST['view_group_buys']) && ! empty(array_filter((array) $_POST['view_group_buys'])))
			return;

		$smcFunc['db_insert']('ignore',
			'{db_prefix}permissions',
			[
				'permission' => 'string',
				'id_group'   => 'int',
			],
			[
				['view_group_buys', 0],
			],
			['permission', 'id_group'],
		);

		require_once($sourcedir . '/ManagePermissions.php');

		updateChildPermissions([0]);
		updateSettings(['settings_updated' => time()]);
	}

	private function addGroupBuy(): void
	{
		global $sourcedir, $smcFunc;

		isAllowedTo('make_group_buys');

		if (empty($_POST))
			return;

		require_once($sourcedir . '/Subs-Editor.php');

		if (isset($_POST['requisites'])) {
			$_POST['requisites'] = un_htmlspecialchars($_POST['requisites']);

			censorText($_POST['requisites']);

			$smcFunc['db_insert']('',
				'{db_prefix}group_buys_items',
				[
					'topic_id'   => 'int',
					'price'      => 'float',
					'currency'   => 'string-3',
					'requisites' => 'string',
				],
				[
					(int) $_POST['topic'],
					(float) $_POST['price'],
					$this->filterCurrency($_POST['currency']),
					$smcFunc['htmlspecialchars']($_POST['requisites'], ENT_QUOTES),
				],
				['id'],
			);
		}

		exit;
	}

	private function editGroupBuy(): void
	{
		global $sourcedir, $smcFunc;

		isAllowedTo('make_group_buys');

		if (empty($_POST))
			return;

		require_once($sourcedir . '/Subs-Editor.php');

		if (isset($_POST['requisites'])) {
			$_POST['requisites'] = un_htmlspecialchars($_POST['requisites']);

			censorText($_POST['requisites']);

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}group_buys_items
				SET	price = {float:price}, currency = {string:currency}, requisites = {string:requisites}
				WHERE topic_id = {int:topic}',
				[
					'topic'      => (int) $_POST['topic'],
					'price'      => (float) $_POST['price'],
					'currency'   => $this->filterCurrency($_POST['currency']),
					'requisites' => $smcFunc['htmlspecialchars']($_POST['requisites'], ENT_QUOTES),
				],
			);
		}

		die;
	}

	/**
	 * Currencies available for selection, taken from the mod settings
	 * (one currency code per line). Defaults to RUB, USD, EUR and UAH.
	 *
	 * @return string[]
	 */
	private function currencies(): array
	{
		global $modSettings;

		$raw = $modSettings['gb_currencies'] ?? '';

		$currencies = [];
		foreach (preg_split('~[\r\n]+~', $raw) as $line) {
			$currencies[] = mb_substr(trim($line), 0, 3);
		}

		$currencies = array_values(array_unique(array_filter($currencies, 'strlen')));

		return ! empty($currencies) ? $currencies : self::DEFAULT_CURRENCIES;
	}

	/**
	 * Keep only a submitted currency code that exists in the settings.
	 * The DB column is char(3), so values are capped at three characters as well.
	 */
	private function filterCurrency(mixed $currency): string
	{
		$currency   = mb_substr(trim((string) $currency), 0, 3);
		$currencies = $this->currencies();

		return in_array($currency, $currencies, true) ? $currency : $currencies[0];
	}
}
