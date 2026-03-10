<?php

namespace ACPT\Core\Repository;

use ACPT\Core\Models\Form\FormFieldModel;
use ACPT\Core\Models\Form\FormMetadataModel;
use ACPT\Core\Models\Form\FormModel;
use ACPT\Core\Models\Form\FormSubmissionModel;
use ACPT\Core\Models\Validation\ValidationRuleModel;
use ACPT\Core\ValueObjects\FormSubmissionDatumObject;
use ACPT\Core\ValueObjects\FormSubmissionErrorObject;
use ACPT\Includes\ACPT_DB;
use ACPT\Utils\Cache\RepeaterFieldCache;
use ACPT\Utils\PHP\Arrays;

class FormRepository extends AbstractRepository
{
	/**
	 * @return int
	 */
	public static function count(): int
	{
		$baseQuery = "
            SELECT 
                count(id) as count
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM)."`
            WHERE 1 = 1
            ";

		$results = ACPT_DB::getResults($baseQuery);

		return (int)$results[0]->count;
	}

	/**
	 * @param $id
	 *
	 * @throws \Exception
	 */
	public static function delete($id)
	{
		ACPT_DB::executeQueryOrThrowException("DELETE FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM)."` WHERE id = %s;", [$id]);
		ACPT_DB::executeQueryOrThrowException("DELETE FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_METADATA)."` WHERE form_id = %s;", [$id]);
		ACPT_DB::executeQueryOrThrowException("DELETE FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` WHERE form_id = %s;", [$id]);
		ACPT_DB::executeQueryOrThrowException("DELETE FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_SUBMISSION)."` WHERE form_id = %s;", [$id]);
		ACPT_DB::invalidateCacheTag(self::class);
        ACPT_DB::invalidateCacheTag(RepeaterFieldCache::class);
	}

	/**
	 * @param $args
	 *
	 * @return FormModel[]
	 * @throws \Exception
	 */
	public static function get($args): array
	{
		$mandatoryKeys = [
			'id' => [
				'required' => false,
				'type' => 'integer|string',
			],
			'formName' => [
				'required' => false,
				'type' => 'string',
			],
			'page' => [
				'required' => false,
				'type' => 'integer|string',
			],
			'perPage' => [
				'required' => false,
				'type' => 'integer|string',
			],
			'sortedBy' => [
				'required' => false,
				'type' => 'string',
			],
			'lazy' => [
				'required' => false,
				'type' => 'boolean',
			],
		];

		self::validateArgs($mandatoryKeys, $args);

		$id = isset($args['id']) ? $args['id'] : null;
		$formName = isset($args['formName']) ? $args['formName'] : null;
		$lazy = isset($args['lazy']) ? $args['lazy'] : false;
		$page = isset($args['page']) ? $args['page'] : false;
		$perPage = isset($args['perPage']) ? $args['perPage'] : null;

        $cachedId = "form_get";

        if($id){
            $cachedId .= "_".$id;
        }

        if($formName){
            $cachedId .= "_".$formName;
        }

        if($lazy){
            $cachedId .= "_lazy";
        }

        if($page){
            $cachedId .= "_".$page;
        }

        if($perPage){
            $cachedId .= "_".$perPage;
        }

        $fromCache = self::fromCache($cachedId);

        if($fromCache !== null){
            return $fromCache;
        }

		$formQueryArgs = [];
		$formQuery = "
	        SELECT 
                f.id, 
                f.form_name as name,
                f.label,
                f.form_action as `action`,
                f.form_key as `key`
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM)."` f
            WHERE 1 = 1
	    ";

		if($id !== null){
			$formQuery .= " AND f.id = %s";
			$formQueryArgs[] = $id;
		}

		if($formName !== null){
			$formQuery .= " AND f.form_name = %s";
			$formQueryArgs[] = $formName;
		}

		$formQuery .= ' GROUP BY f.id ORDER BY f.form_name ASC';

		if(isset($page) and isset($perPage)){
			$formQuery .= " LIMIT ".$perPage." OFFSET " . ($perPage * ($page - 1));
		}

		$forms = ACPT_DB::getResults($formQuery, $formQueryArgs);
		$formModels = [];

		foreach ($forms as $form){
			$formModels[] = self::hydrateForm($form);
		}

        self::saveInCache($cachedId, $formModels);

		return $formModels;
	}

    /**
     * @return string[]
     */
    public static function getNames()
    {
        $names = [];
        $query = "
	        SELECT 
                f.id, 
                f.form_name as name
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM)."` f
	    ";

        $elements = ACPT_DB::getResults($query, []);

        foreach ($elements as $element){
            $names[] = $element->name;
        }

        return $names;
    }

    /**
     * @return string[]
     */
    public static function getFieldNames()
    {
        $names = [];
        $query = "
	        SELECT 
                f.id, 
                f.field_name as name
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` f
	    ";

        $elements = ACPT_DB::getResults($query, []);

        foreach ($elements as $element){
            $names[] = $element->name;
        }

        return $names;
    }

	/**
	 * @param $key
	 *
	 * @return FormModel|null
	 * @throws \Exception
	 */
	public static function getByKey($key): ?FormModel
	{
		$formQuery = "
	        SELECT 
                f.id, 
                f.form_name as name,
                f.label,
                f.form_action as `action`,
                f.form_key as `key`
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM)."` f
            WHERE form_key = %s
	    ";

		$forms = ACPT_DB::getResults($formQuery, [$key]);

		if(Arrays::count($forms) !== 1){
			return null;
		}

		return self::hydrateForm($forms[0]);
	}

	/**
	 * @param $id
	 *
	 * @return FormModel|null
	 * @throws \Exception
	 */
	public static function getById($id): ?FormModel
	{
		$formQuery = "
	        SELECT 
                f.id, 
                f.form_name as name,
                f.label,
                f.form_action as `action`,
                f.form_key as `key`
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM)."` f
            WHERE id = %s
	    ";

		$forms = ACPT_DB::getResults($formQuery, [$id]);

		if(Arrays::count($forms) !== 1){
			return null;
		}

		return self::hydrateForm($forms[0]);
	}

	/**
	 * @param $form
	 *
	 * @return FormModel
	 * @throws \Exception
	 */
	private static function hydrateForm($form)
	{
		$formModel = FormModel::hydrateFromArray([
			'id'       => $form->id,
			'name'     => $form->name,
			'label'    => $form->label,
			'action'   => $form->action,
			'key'      => $form->key,
		]);

		$metadataQuery = "
		        SELECT 
	                m.id, 
	                m.meta_key as `key`,
	                m.meta_value as `value`
	            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_METADATA)."` m
	            WHERE form_id = %s
		    ";

		$metaData = ACPT_DB::getResults($metadataQuery, [$form->id]);

		foreach ($metaData as $metaDatum){
			$metaDataModel = FormMetadataModel::hydrateFromArray([
				'id'       => $metaDatum->id,
				'formId'   => $form->id,
				'value'    => $metaDatum->value,
				'key'      => $metaDatum->key,
			]);

			$formModel->addMetadata($metaDataModel);
		}

		$fieldsQuery = "
	        SELECT 
                f.id, 
                f.parent_id,
                f.parent_tab,
                f.field_group as `group`, 
                f.field_name as `name`,
                f.field_label as `label`,
                f.field_key as `key`,
                f.field_type as `type`,
                f.meta_field_id, 
                f.description,
                f.extra,
                f.settings,
                f.required,
                f.sort
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` f
            WHERE (parent_id IS NULL or parent_id = '') and form_id = %s 
            ORDER BY f.sort
	    ";

		$fields = ACPT_DB::getResults($fieldsQuery, [$form->id]);
		foreach ($fields as $field){
			$fieldModel = self::hydrateFormField($field);
			$formModel->addField($fieldModel);
		}

        $submissions = self::getSubmissions($form->id);
        foreach ($submissions as $submission){
            $formModel->addSubmission($submission);
        }

		return $formModel;
	}

    /**
     * @param                     $field
     * @param FormFieldModel|null $parent
     *
     * @return FormFieldModel
     * @throws \Exception
     */
	private static function hydrateFormField($field, ?FormFieldModel $parent = null)
	{
		$metaFieldModel = null;
		if($field->meta_field_id !== null){
            $metaFieldModel = MetaRepository::getMetaFieldById($field->meta_field_id);
		}

		$fieldModel = FormFieldModel::hydrateFromArray([
			'id' => @$field->id,
			'metaField' => $metaFieldModel,
			'key' => @$field->key,
			'group' => @$field->group,
			'name' => @$field->name,
			'label' => @$field->label,
			'type' => @$field->type,
			'description' => @$field->description,
			'isRequired' => (bool)@$field->required,
			'extra' => unserialize(@$field->extra),
			'settings' => unserialize(@$field->settings),
			'sort' => @$field->sort,
		]);

		if($parent !== null){
            $fieldModel->setParentField($parent);
        }

		if($field->parent_tab !== null){
            $fieldModel->setParentTabIndex($field->parent_tab);
        }

		// children
        $children = ACPT_DB::getResults("
            SELECT 
                f.id, 
                f.parent_id,
                f.parent_tab,
                f.field_group as `group`, 
                f.field_name as `name`,
                f.field_label as `label`,
                f.field_key as `key`,
                f.field_type as `type`,
                f.meta_field_id, 
                f.description,
                f.extra,
                f.settings,
                f.required,
                f.sort
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` f
            WHERE parent_id = %s 
            ORDER BY f.sort
        ;", [$field->id]);

        foreach ($children as $tabChildren){
            $tabChildModel = self::hydrateFormField($tabChildren, $fieldModel);
            $fieldModel->addChild($tabChildModel, $tabChildren->parent_tab);
        }

		// validation rules
		$validationRules = ACPT_DB::getResults("
            SELECT
                v.id,
                v.rule_condition as `condition`,
                v.rule_value as `value`,
                v.message as `message`
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_VALIDATION_RULE)."` v
            JOIN `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_VALIDATION_RULE_FORM_FIELD_PIVOT)."` vp ON vp.rule_id = v.id
            WHERE vp.field_id = %s
            ORDER BY v.sort
        ;", [$field->id]);

		foreach ($validationRules as $ruleIndex => $validationRule){
			$validationRuleModel = ValidationRuleModel::hydrateFromArray([
				'id' => $validationRule->id,
				'condition' => $validationRule->condition,
				'value' => $validationRule->value,
				'message' => $validationRule->message,
				'sort' => ($ruleIndex+1),
			]);

			$fieldModel->addValidationRule($validationRuleModel);
		}

		return $fieldModel;
	}

	/**
	 * @param FormModel $formModel
	 *
	 * @throws \Exception
	 */
	public static function save(FormModel $formModel): void
	{
		ACPT_DB::startTransaction();

		try {
			$fieldIds = [];
			$validationRuleIds = [];

			$sql = "
	            INSERT INTO `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM)."` 
	            (`id`,
	            `form_name`,
	            `label`,
	            `form_action`,
	            `form_key`
	            ) VALUES (
	                %s,
	                %s,
	                %s,
	                %s,
	                %s
	            ) ON DUPLICATE KEY UPDATE 
	                `form_name` = %s,
	                `label` = %s,
	                `form_action` = %s,
	                `form_key` = %s
	        ;";

			ACPT_DB::executeQueryOrThrowException($sql, [
				$formModel->getId(),
				$formModel->getName(),
				$formModel->getLabel(),
				$formModel->getAction(),
				$formModel->getKey(),
				$formModel->getName(),
				$formModel->getLabel(),
				$formModel->getAction(),
				$formModel->getKey()
			]);

			ACPT_DB::executeQueryOrThrowException("DELETE FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_METADATA)."` WHERE form_id = %s;", [$formModel->getId()]);

			// metadata
			foreach ($formModel->getMeta() as $metadataModel){

				$sql = "
		            INSERT INTO `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_METADATA)."` 
		            (`id`,
		            `form_id`,
		            `meta_key`,
		            `meta_value`
		            ) VALUES (
		                %s,
		                %s,
		                %s,
		                %s
		            ) ON DUPLICATE KEY UPDATE 
		                `form_id` = %s,
		                `meta_key` = %s,
		                `meta_value` = %s
		        ;";

				ACPT_DB::executeQueryOrThrowException($sql, [
					$metadataModel->getId(),
					$formModel->getId(),
					$metadataModel->getKey(),
					$metadataModel->getValue(),
					$formModel->getId(),
					$metadataModel->getKey(),
					$metadataModel->getValue(),
				]);
			}

			// fields
			foreach ($formModel->getFields() as $fieldIndex => $fieldModel){
                self::saveMetaField($formModel, $fieldModel, $fieldIndex, null, $fieldIds, $validationRuleIds);
			}

			self::removeOrphans($formModel->getId(), $fieldIds);
			self::removeOrphanValidationRules($validationRuleIds);

		} catch (\Exception $exception){
            do_action("acpt/error", $exception);
			ACPT_DB::rollbackTransaction();
		}

		ACPT_DB::commitTransaction();
		ACPT_DB::invalidateCacheTag(self::class);
		ACPT_DB::invalidateCacheTag(RepeaterFieldCache::class);
	}

    /**
     * @param FormModel           $formModel
     * @param FormFieldModel      $fieldModel
     * @param                     $fieldIndex
     * @param FormFieldModel|null $parentFieldModel
     * @param array               $fieldIds
     * @param array               $validationRuleIds
     *
     * @throws \Exception
     */
	private static function saveMetaField(FormModel $formModel, FormFieldModel $fieldModel, $fieldIndex, ?FormFieldModel $parentFieldModel = null, &$fieldIds = [], &$validationRuleIds = [])
    {
        $isRequired = ($fieldModel->isRequired()) ? '1' : '0';
        $metaFormId = ($fieldModel->getMetaField() !== null) ? $fieldModel->getMetaField()->getId() : null;

        if($parentFieldModel !== null){
            $sql = "
                INSERT INTO `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` 
                    (`id`,
                    `form_id`,
                    `meta_field_id`,
                    `field_group`,
                    `field_name`,
                    `field_label`,
                    `field_key`,
                    `field_type`,
                    `description`,
                    `extra`,
                    `settings`,
                    `required`,
                    `sort`,
                    `parent_id`,
                    `parent_tab`
                ) VALUES (
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %d,
                    %s,
                    %d
                ) ON DUPLICATE KEY UPDATE 
                    `form_id` = %s,
                    `meta_field_id` = %s,
                    `field_group` = %s,
                    `field_name` = %s,
                    `field_label` = %s,
                    `field_key` = %s,
                    `field_type` = %s,
                    `description` = %s,
                    `extra` = %s,
                    `settings` = %s,
                    `required` = %s,
                    `sort` = %d,
                    `parent_id` = %s,
                    `parent_tab` = %d
            ;";

            $args = [
                $fieldModel->getId(),
                $formModel->getId(),
                $metaFormId,
                $fieldModel->getGroup(),
                $fieldModel->getName(),
                $fieldModel->getLabel(),
                $fieldModel->getKey(),
                $fieldModel->getType(),
                $fieldModel->getDescription(),
                serialize($fieldModel->getExtra()),
                serialize($fieldModel->getSettings()),
                $isRequired,
                ($fieldIndex+1),
                ($parentFieldModel !== null) ? $parentFieldModel->getId() : null,
                $fieldModel->getParentTabIndex() ? (int)$fieldModel->getParentTabIndex() : null,
                $formModel->getId(),
                $metaFormId,
                $fieldModel->getGroup(),
                $fieldModel->getName(),
                $fieldModel->getLabel(),
                $fieldModel->getKey(),
                $fieldModel->getType(),
                $fieldModel->getDescription(),
                serialize($fieldModel->getExtra()),
                serialize($fieldModel->getSettings()),
                $isRequired,
                ($fieldIndex+1),
                ($parentFieldModel !== null) ? $parentFieldModel->getId() : null,
                $fieldModel->getParentTabIndex() ? (int)$fieldModel->getParentTabIndex() : null,
            ];

        } else {
            $sql = "
                INSERT INTO `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` 
                    (`id`,
                    `form_id`,
                    `meta_field_id`,
                    `field_group`,
                    `field_name`,
                    `field_label`,
                    `field_key`,
                    `field_type`,
                    `description`,
                    `extra`,
                    `settings`,
                    `required`,
                    `sort`
                ) VALUES (
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %d
                ) ON DUPLICATE KEY UPDATE 
                    `form_id` = %s,
                    `meta_field_id` = %s,
                    `field_group` = %s,
                    `field_name` = %s,
                    `field_label` = %s,
                    `field_key` = %s,
                    `field_type` = %s,
                    `description` = %s,
                    `extra` = %s,
                    `settings` = %s,
                    `required` = %s,
                    `sort` = %d
            ;";

            $args = [
                    $fieldModel->getId(),
                    $formModel->getId(),
                    $metaFormId,
                    $fieldModel->getGroup(),
                    $fieldModel->getName(),
                    $fieldModel->getLabel(),
                    $fieldModel->getKey(),
                    $fieldModel->getType(),
                    $fieldModel->getDescription(),
                    serialize($fieldModel->getExtra()),
                    serialize($fieldModel->getSettings()),
                    $isRequired,
                    ($fieldIndex+1),
                    $formModel->getId(),
                    $metaFormId,
                    $fieldModel->getGroup(),
                    $fieldModel->getName(),
                    $fieldModel->getLabel(),
                    $fieldModel->getKey(),
                    $fieldModel->getType(),
                    $fieldModel->getDescription(),
                    serialize($fieldModel->getExtra()),
                    serialize($fieldModel->getSettings()),
                    $isRequired,
                    ($fieldIndex+1),
            ];
        }

        ACPT_DB::executeQueryOrThrowException($sql, $args);

        // children
        foreach ($fieldModel->getChildren() as $childIndex => $childModel){
            self::saveMetaField($formModel, $childModel, $childIndex, $fieldModel, $fieldIds, $validationRuleIds);
        }

        // validation rules
        foreach ($fieldModel->getValidationRules() as $ruleIndex => $ruleModel){
            $sql = "
			        INSERT INTO `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_VALIDATION_RULE)."` 
			        (
			            `id`,
			            `rule_condition`,
                        `rule_value`,
                        `message`,
                        `sort`
			        ) VALUES (
			            %s,
			            %s,
			            %s,
			            %s,
			            %d
			        ) ON DUPLICATE KEY UPDATE 
                        `rule_condition` = %s,
                        `rule_value` = %s,
                        `message` = %s,
                        `sort` = %d
			    ";

            ACPT_DB::executeQueryOrThrowException($sql, [
                    $ruleModel->getId(),
                    $ruleModel->getCondition(),
                    $ruleModel->getValue(),
                    $ruleModel->getMessage(),
                    ($ruleIndex+1),
                    $ruleModel->getCondition(),
                    $ruleModel->getValue(),
                    $ruleModel->getMessage(),
                    ($ruleIndex+1)
            ]);

            $sql = "
				        INSERT INTO `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_VALIDATION_RULE_FORM_FIELD_PIVOT)."` 
				        (
				            `field_id`,
	                        `rule_id`
				        ) VALUES (
				            %s,
				            %s
				        ) ON DUPLICATE KEY UPDATE 
	                        `field_id` = %s,
	                        `rule_id` = %s
				    ";

            ACPT_DB::executeQueryOrThrowException($sql, [
                    $fieldModel->getId(),
                    $ruleModel->getId(),
                    $fieldModel->getId(),
                    $ruleModel->getId(),
            ]);

            $validationRuleIds[] = $ruleModel->getId();
        }

        $fieldIds[] = $fieldModel->getId();
    }

	/**
	 * @param $formId
	 * @param $ids
	 *
	 * @throws \Exception
	 */
	private static function removeOrphans($formId, $ids)
	{
		$deleteValidationRulesQuery = "
	    	DELETE f
			FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_FIELD)."` f
			WHERE form_id = %s
	    ";

		$deleteValidationRulesQuery .= " AND f.id NOT IN ('".implode("','",$ids)."')";
		$deleteValidationRulesQuery .= ";";

		ACPT_DB::executeQueryOrThrowException($deleteValidationRulesQuery, [$formId]);
	}

	/**
	 * @param $ids
	 *
	 * @throws \Exception
	 */
	private static function removeOrphanValidationRules($ids)
	{
		$deleteValidationRulesQuery = "
	    	DELETE r
			FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_VALIDATION_RULE)."` r
			WHERE 1=1
	    ";

		$deleteValidationRulesQuery .= " AND r.id NOT IN ('".implode("','",$ids)."')";
		$deleteValidationRulesQuery .= ";";

		ACPT_DB::executeQueryOrThrowException($deleteValidationRulesQuery);

		$deleteValidationRulesQuery = "
	    	DELETE r
			FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_VALIDATION_RULE_FORM_FIELD_PIVOT)."` r
			WHERE 1=1
	    ";

		$deleteValidationRulesQuery .= " AND r.rule_id NOT IN ('".implode("','",$ids)."')";
		$deleteValidationRulesQuery .= ";";

		ACPT_DB::executeQueryOrThrowException($deleteValidationRulesQuery);
	}

	/**
	 * @param FormSubmissionModel $submissionModel
	 *
	 * @throws \Exception
	 */
	public static function saveSubmission(FormSubmissionModel $submissionModel)
	{
		$sql = "
            INSERT INTO `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_SUBMISSION)."` 
	            (`id`,
	            `form_id`,
	            `form_action`,
	            `callback`,
	            `ip`,
	            `uid`,
	            `browser`,
	            `form_data`,
	            `errors`,
	            `created_at`
	            ) VALUES (
	                %s,
	                %s,
	                %s,
	                %s,
	                %s,
	                %d,
	                %s,
	                %s,
	                %s,
	                %s
	            ) ON DUPLICATE KEY UPDATE 
	                `form_id` = %s,
	                `form_action` = %s,
	                `callback` = %s,
					`ip` = %s,
					`uid` = %d,
					`browser` = %s,
					`form_data` = %s,
					`errors` = %s,
					`created_at` = %s
	        ;";

		ACPT_DB::executeQueryOrThrowException($sql, [
			$submissionModel->getId(),
			$submissionModel->getFormId(),
			$submissionModel->getAction(),
			$submissionModel->getCallback(),
			$submissionModel->getIp() ?? "0.0.0.0",
			$submissionModel->getUid(),
			json_encode($submissionModel->getBrowser()),
			json_encode($submissionModel->getData()),
			json_encode($submissionModel->getErrors()),
			$submissionModel->getCreatedAt()->format("Y-m-d H:i:s"),
			$submissionModel->getFormId(),
			$submissionModel->getAction(),
			$submissionModel->getCallback(),
			$submissionModel->getIp() ?? "0.0.0.0",
			$submissionModel->getUid(),
			json_encode($submissionModel->getBrowser()),
			json_encode($submissionModel->getData()),
			json_encode($submissionModel->getErrors()),
			$submissionModel->getCreatedAt()->format("Y-m-d H:i:s")
		]);

		ACPT_DB::invalidateCacheTag(self::class);
        ACPT_DB::invalidateCacheTag(RepeaterFieldCache::class);
	}

	/**
	 * @param string $formId
	 * @param null $page
	 * @param null $perPage
	 * @param null $dateFrom
	 * @param null $dateTo
	 * @param null $action
	 *
	 * @return FormSubmissionModel[]
	 * @throws \Exception
	 */
	public static function getSubmissions($formId, $page = null, $perPage = null, $dateFrom = null, $dateTo = null, $action = null): array
	{
		$submissions = [];

		$params = [$formId];

		$sql = "
            SELECT
                s.id,
                f.form_name,
                f.label,
	            s.form_id,
	            s.form_action as `action`,
	            s.callback,
	            s.ip,
	            s.uid,
	            s.browser,
	            s.form_data as `data`,
	            s.errors,
	            s.created_at
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_SUBMISSION)."` s
            JOIN  `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM)."` f
            WHERE form_id = %s
		";

        if($dateFrom){
            $sql .= " AND s.created_at >= %s";
            $params[] = $dateFrom;
        }

        if($dateTo){
            $sql .= " AND s.created_at <= %s";
            $params[] = $dateTo;
        }

		if($action){
            $sql .= " AND s.form_action = %s";
            $params[] = $action;
        }

		$sql .= " ORDER BY created_at DESC";

		if($page and $perPage){
            $sql .= " LIMIT ".$perPage." OFFSET " . ($perPage * ($page - 1));
        }

		$sql .= ";";

		$submissionsRecords = ACPT_DB::getResults($sql, $params);
		foreach ($submissionsRecords as $submissionRecord){
			$formSubmissionModel = FormSubmissionModel::hydrateFromArray([
				'id' => $submissionRecord->id,
				'formId' => $submissionRecord->form_id,
				'action' => $submissionRecord->action,
				'callback' => $submissionRecord->callback,
				'uid' => $submissionRecord->uid,
				'createdAt' => new \DateTime($submissionRecord->created_at),
			]);

			$formSubmissionModel->setBrowser(json_decode($submissionRecord->browser, true));

			foreach (json_decode($submissionRecord->errors, true) as $error){
				$formSubmissionError = new FormSubmissionErrorObject($error['key'], $error['error']);
				$formSubmissionModel->addError($formSubmissionError);
			}

			$formSubmissionModel->setIp($submissionRecord->ip);
			$formSubmissionModel->setFormName($submissionRecord->form_name);
			$formSubmissionModel->setFormLabel($submissionRecord->label);

			$data = json_decode($submissionRecord->data, true);

			foreach ($data as $datum){
				if(
					isset($datum['name']) and
					isset($datum['type']) and
					isset($datum['value'])
				){
					$datumObject = new FormSubmissionDatumObject($datum['name'], $datum['type'], $datum['value']);
					$formSubmissionModel->addDatum($datumObject);
				}
			}

			$submissions[] = $formSubmissionModel;
		}

		return $submissions;
	}

    /**
     * @param      $formId
     * @param null $dateFrom
     * @param null $dateTo
     * @param null $action
     *
     * @return int
     */
	public static function getSubmissionsCount($formId, $dateFrom = null, $dateTo = null, $action = null)
	{
		$baseQuery = "
            SELECT 
                count(id) as count
            FROM `".ACPT_DB::prefixedTableName(ACPT_DB::TABLE_FORM_SUBMISSION)."`
            WHERE form_id = %s
            ";

        $params = [$formId];

        if($dateFrom){
            $baseQuery .= " AND created_at >= %s";
            $params[] = $dateFrom;
        }

        if($dateTo){
            $baseQuery .= " AND created_at <= %s";
            $params[] = $dateTo;
        }

        if($action){
            $baseQuery .= " AND form_action = %s";
            $params[] = $action;
        }

		$results = ACPT_DB::getResults($baseQuery, $params);

		return (int)$results[0]->count;
	}
}