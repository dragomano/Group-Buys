<?php

global $user_info, $smcFunc;

if (file_exists(dirname(__FILE__) . '/SSI.php') && ! defined('SMF')) {
	require_once(dirname(__FILE__) . '/SSI.php');
} elseif(! defined('SMF')) {
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');
}

if ((SMF == 'SSI') && ! $user_info['is_admin']) {
	die('Admin privileges required.');
}

$tables[] = [
	'name'    => 'group_buys_items',
	'columns' => [
		[
			'name'     => 'id',
			'type'     => 'int',
			'size'     => 10,
			'unsigned' => true,
			'auto'     => true,
		],
		[
			'name'     => 'topic_id',
			'type'     => 'mediumint',
			'size'     => 8,
			'unsigned' => true,
		],
		[
			'name'    => 'currency',
			'type'    => 'char',
			'size'    => 3,
			'default' => 'RUB',
			'null'    => false,
		],
		[
			'name' => 'requisites',
			'type' => 'text',
			'null' => false,
		]
	],
	'indexes' => [
		[
			'type'    => 'primary',
			'columns' => ['id'],
		],
	],
];

$tables[] = [
	'name'    => 'group_buys_members',
	'columns' => [
		[
			'name'     => 'member_id',
			'type'     => 'mediumint',
			'size'     => 8,
			'unsigned' => true,
		],
		[
			'name'     => 'topic_id',
			'type'     => 'mediumint',
			'size'     => 8,
			'unsigned' => true,
		],
	],
	'indexes' => [
		[
			'type'    => 'primary',
			'columns' => ['member_id', 'topic_id'],
		],
	],
];

db_extend('packages');
db_extend('extra');

foreach ($tables as $table) {
	$smcFunc['db_create_table']('{db_prefix}' . $table['name'], $table['columns'], $table['indexes'], [], 'update');

	if (isset($table['default'])) {
		$smcFunc['db_insert']('ignore', '{db_prefix}' . $table['name'], $table['default']['columns'], $table['default']['values'], $table['default']['keys']);
	}
}

$result = $smcFunc['db_query']('', "SHOW COLUMNS FROM {db_prefix}group_buys_items LIKE 'price'", []);
if (! $smcFunc['db_num_rows']($result)) {
	$smcFunc['db_query']('', "ALTER TABLE {db_prefix}group_buys_items ADD price decimal(10,2) unsigned NOT NULL DEFAULT 0.00");
}

$result = $smcFunc['db_query']('', "SHOW COLUMNS FROM {db_prefix}group_buys_members LIKE 'date'", []);
if (! $smcFunc['db_num_rows']($result)) {
	$smcFunc['db_query']('', "ALTER TABLE {db_prefix}group_buys_members ADD date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
}

if (SMF == 'SSI') {
	echo 'Database changes are complete! Please wait...';
}
