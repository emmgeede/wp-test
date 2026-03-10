<?php

use ACPT\Includes\ACPT_DB;
use ACPT\Includes\ACPT_Schema_Migration;

class AddFormFieldParentFields extends ACPT_Schema_Migration
{
	/**
	 * @return array
	 */
	public function up(): array
	{
		$queries = [];

		if(!ACPT_DB::checkIfColumnExistsInTable(ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD), 'parent_id')){
			$queries[] = "ALTER TABLE `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` ADD `parent_id` VARCHAR(36) DEFAULT NULL";
		}

        if(!ACPT_DB::checkIfColumnExistsInTable(ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD), 'parent_tab')){
            $queries[] = "ALTER TABLE `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` ADD `parent_tab` INT(11) DEFAULT NULL";
        }

		return $queries;
	}

	/**
	 * @inheritDoc
	 */
	public function down(): array
	{
		return [
			"ALTER TABLE `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` DROP COLUMN `parent_id` ",
		];
	}

	/**
	 * @inheritDoc
	 */
	public function version(): string
	{
		return '2.0.45';
	}
}
