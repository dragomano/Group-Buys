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
	public function hooks(): void
	{
		add_integration_function('integrate_load_theme', self::class . '::loadTheme#', false);
		add_integration_function('integrate_actions', self::class . '::actions#', false);
		add_integration_function('integrate_load_permissions', self::class . '::loadPermissions#', false);
		add_integration_function('integrate_display_buttons', self::class . '::displayButtons#', false);
		add_integration_function('integrate_prepare_display_context', self::class . '::prepareDisplayContext#', false);
		add_integration_function('integrate_profile_popup', self::class . '::profilePopup#', false);
		add_integration_function('integrate_profile_areas', self::class . '::profileAreas#', false);
	}

	public function loadTheme(): void
	{
		global $context, $settings;

		loadLanguage('GroupBuys/');

		$context['make_group_buys'] = allowedTo('make_group_buys');

		if ( ! empty($_REQUEST['topic']) || (! empty($context['current_action']) && $context['current_action'] === 'profile')) {
			$context['html_headers'] .= '
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-modal@0.9.1/jquery.modal.min.css" />
		<link rel="stylesheet" href="' . $settings['default_theme_url'] . '/css/group_buys.css" />';

			$context['insert_after_template'] .= '
		<script src="https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/jquery-modal@0.9.1/jquery.modal.min.js"></script>';
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
					(string) $_POST['currency'],
					$smcFunc['htmlspecialchars']($_POST['requisites'], ENT_QUOTES),
				],
				['id'],
			);
		}

		die;
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
					'currency'   => (string) $_POST['currency'],
					'requisites' => $smcFunc['htmlspecialchars']($_POST['requisites'], ENT_QUOTES),
				],
			);
		}

		die;
	}

	public function loadPermissions(array &$permissionGroups, array &$permissionList): void
	{
		global $context;

		$permissionGroups['membergroup']['simple']  = ['group_buys'];
		$permissionGroups['membergroup']['classic'] = ['group_buys'];

		$permissionList['membergroup']['make_group_buys'] = [false, 'group_buys', 'group_buys'];

		$context['non_guest_permissions'] = array_merge($context['non_guest_permissions'], ['make_group_buys']);
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

			$context['group_buys'] = [
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
			empty($context['group_buys']['id']) ? [
				'add_group_buy' => [
					'test'  => 'make_group_buys',
					'text'  => 'gb_add_group_buy',
					'image' => 'im_reply.gif',
					'lang'  => true,
					'url'   => '#group_buy',
				],
			] : [
				'edit_group_buy' => [
					'test'  => 'make_group_buys',
					'text'  => 'gb_edit_group_buy',
					'image' => 'im_reply.gif',
					'lang'  => true,
					'url'   => '#group_buy',
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
				SELECT m.member_id AS id, IFNULL(mem.member_name, 0) AS member_name
				FROM {db_prefix}group_buys_members AS m
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.member_id)
				WHERE m.topic_id = {int:current_topic}',
				[
					'current_topic' => $context['current_topic'],
				],
			);

			$members = [];
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$members[$row['id']] = $row['member_name'];
			}

			$smcFunc['db_free_result']($request);

			if (! empty($members)) {
				if ($output['is_message_author']) {
					$output['body'] .= '
				<div class="group_buy_list information smalltext">
					<div class="centertext">
						<h5>' . $txt['gb_group_buy_list'] . '</h5>
						<hr />
					</div>
					<ul class="columns">';

					foreach ($members as $id => $member)
						$output['body'] .= '
						<li><a href="' . $scripturl . '?action=profile;u=' . $id . '">' . $member . '</a></li>';

					$output['body'] .= '
					</ul>
				</div>';
				} else {
					$output['body'] .= '
				<hr />
				<div class="smalltext">' . $txt['gb_group_buy_list1'] . count($members) . '</div>';
				}
			}

			if (empty($output['is_message_author'])) {
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
				<input class="button_submit" type="button" value="' . $txt['gb_join_ready'] . '" disabled />';
					} else {
					$output['body'] .= '
				<hr />
				<a href="#join_modal" rel="modal:open" class="button_submit">' . $txt['gb_join_to_group_buy'] . '</a>
				<div id="join_modal" class="join_modal" style="display:none">
					<div class="modal-content">
						<div class="centertext">
							<h3>' . $txt['gb_group_buy_info'] . '</h3>
							<div class="information">' . strtr($txt['gb_group_buy_text'], ['{DATA}' => un_preparsecode($requisites), '{LINK}' => $boardurl, '{USER}' => $user_info['id']]) . '</div>
							<a href="#close" rel="modal:close" class="button_submit">' . $txt['gb_group_buy_ok'] . '</a>
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
			LIMIT {int:start}, {int:num}',
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
				SELECT topic_id, IFNULL(member_name, 0) AS member_name
				FROM {db_prefix}group_buys_members
					INNER JOIN {db_prefix}members ON (id_member = member_id)
				WHERE topic_id IN ({array_int:topics})',
				[
					'topics' => $topics,
				],
			);

			$context['group_buys_members'] = [];
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$context['group_buys_members'][$row['topic_id']][] = $row['member_name'];
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
							],
							[
								(int) $member['id_member'],
								(int) $_POST['topic'],
							],
							[],
						);
					}
				}
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

		$context['sub_template'] = 'profile';
		$context['page_title']   = $txt['gb_group_buys'] . ' - ' . $user_profile[$memID]['real_name'];
	}
}
