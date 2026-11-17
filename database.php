<?php

global $user_info, $smcFunc, $db_prefix;

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
		[
			'name'     => 'date',
			'type'     => 'int',
			'size'     => 10,
			'unsigned' => true,
			'default'  => 0,
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
	$smcFunc['db_create_table']('{db_prefix}' . $table['name'], $table['columns'], $table['indexes'], [], 'ignore');
}

// The abstract API (db_create_table / db_add_column) cannot express decimal(10,2):
// both backends null out non-scalar sizes, which turns it into decimal(10,0).
// The raw DDL below is identical for MySQL and PostgreSQL.
// Note: {db_prefix} must be expanded in PHP — db_query skips replacements with security_override.
if (! in_array('price', $smcFunc['db_list_columns']('{db_prefix}group_buys_items'))) {
	$smcFunc['db_query']('', '
		ALTER TABLE ' . str_replace('{db_prefix}', $db_prefix, '{db_prefix}group_buys_items') . '
		ADD COLUMN price decimal(10,2) NOT NULL DEFAULT 0',
		[
			'security_override' => true,
		]
	);
}

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

if (SMF == 'SSI') {
	echo 'Database changes are complete! Please wait...';
}
