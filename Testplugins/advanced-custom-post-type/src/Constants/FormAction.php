<?php

namespace ACPT\Constants;

class FormAction
{
	const NONE = 'NONE';
	const EMAIL = 'EMAIL';
	const PHP = 'PHP';
	const AJAX = 'AJAX';
	const CUSTOM = 'CUSTOM';
	const FILL_META = 'FILL_META';

	const ALLOWED_VALUES = [
		self::NONE,
		self::EMAIL,
		self::PHP,
		self::AJAX,
		self::CUSTOM,
		self::FILL_META,
	];
}