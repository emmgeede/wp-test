<?php

namespace ACPT\Core\CQRS\Command;

use ACPT\Core\Models\Meta\MetaFieldModel;
use ACPT\Core\Repository\MetaRepository;
use ACPT\Core\Validators\ArgumentsArrayValidator;
use ACPT\Includes\ACPT_DB;
use ACPT\Utils\PHP\Arrays;
use Exception;

class CopyMetaFieldCommand extends AbstractCopyCommand implements CommandInterface
{
	/**
	 * @var string
	 */
	private $fieldId;

	/**
	 * @var string
	 */
	private $targetEntityId;

	/**
	 * @var string
	 */
	private $targetEntityType;

	/**
	 * @var bool
	 */
	private $delete;

    /**
     * @var mixed|null
     */
    private mixed $position;

    /**
	 * CopyMetaFieldCommand constructor.
	 *
	 * @param $data
	 */
	public function __construct($data)
	{
		parent::__construct();
		$validationRules = [
			'fieldId' => [
				'required' => true,
				'type' => 'string',
			],
			'targetEntityId' => [
				'required' => true,
				'type' => 'string',
			],
			'targetEntityType' => [
				'required' => true,
				'type' => 'string',
				'enum' => [
					'box',
					'field',
					'block',
				]
			],
			'delete' => [
				'required' => false,
				'type' => 'boolean',
			],
			'position' => [
				'required' => false,
				'type' => 'string|integer',
			],
		];

		$validator = new ArgumentsArrayValidator();

		if(!$validator->validate($validationRules, $data)){
			throw new \InvalidArgumentException($validator->errorMessage());
		}

		$this->fieldId = $data['fieldId'];
		$this->targetEntityType = $data['targetEntityType'];
		$this->targetEntityId = $data['targetEntityId'];
		$this->delete = $data['delete'] ? $data['delete'] : null;
		$this->position = isset($data['position']) ? $data['position'] : null;
	}

	/**
	 * @return mixed|void
	 * @throws Exception
	 */
	public function execute()
	{
		$metaBoxField = MetaRepository::getMetaFieldById($this->fieldId);

		if(empty($metaBoxField)){
			throw new Exception('Meta field was not found. If you haven\'t saved the field yet, please SAVE it and then try to copy.');
		}

		switch ($this->targetEntityType){

			// copy a field inside a block
			case "block":
				$targetBlockModel = MetaRepository::getMetaBlockById($this->targetEntityId);

				if(empty($targetBlockModel)){
					throw new Exception('Target meta block was not found');
				}

				$duplicatedMetaField = $this->copyField($metaBoxField, $metaBoxField->getBox());
				$duplicatedMetaField->setBlockId($this->targetEntityId);
				$this->saveMetaField($duplicatedMetaField);

				break;

			// copy a field inside a nestable field
			case "field":
				$targetFieldModel = MetaRepository::getMetaFieldById($this->targetEntityId);

				if(empty($targetFieldModel)){
					throw new Exception('Target meta field was not found');
				}

				$duplicatedMetaField = $this->copyField($metaBoxField, $targetFieldModel->getBox());
                $duplicatedMetaField->changeSort(Arrays::count($targetFieldModel->getBox()->getFields())+1);

                if($this->position !== null){
                    $duplicatedMetaField->changeSort($this->position+1);

                    foreach ($targetFieldModel->getBox()->getFields() as $sort => $field){
                        $newSort = ($sort > $this->position) ? $sort+1 : $sort;
                        $field->changeSort($newSort);
                        MetaRepository::saveMetaBoxField($field);
                    }
                }

				switch ($targetFieldModel->getType()){
					case MetaFieldModel::FLEXIBLE_CONTENT_TYPE:
						$duplicatedMetaField->setBlockId($targetFieldModel->getBlockId());
						break;

					case MetaFieldModel::REPEATER_TYPE:
						$duplicatedMetaField->setParentId($targetFieldModel->getId());
						break;
				}

				$this->saveMetaField($duplicatedMetaField);

				break;

			// copy a field inside a box
			case "box":
				$targetBoxModel = MetaRepository::getMetaBoxById($this->targetEntityId);

				if(empty($targetBoxModel)){
					throw new Exception('Target meta box was not found');
				}

				$duplicatedMetaField = $this->copyField($metaBoxField, $targetBoxModel);
                $duplicatedMetaField->changeSort(Arrays::count($targetBoxModel->getFields())+1);

				if($duplicatedMetaField->hasParentBlock()){
					$duplicatedMetaField->setBlockId(null);
				}

                if($this->position !== null){
                    $duplicatedMetaField->changeSort($this->position+1);

                    foreach ($targetBoxModel->getFields() as $sort => $field){
                        $newSort = ($sort > $this->position) ? $sort+1 : $sort;
                        $field->changeSort($newSort);
                        MetaRepository::saveMetaBoxField($field);
                    }
                }

				$duplicatedMetaField->setBlockId(null);
				$duplicatedMetaField->setParentId(null);
				$this->saveMetaField($duplicatedMetaField);

				break;
		}
	}

	/**
	 * @param MetaFieldModel $fieldModel
	 *
	 * @throws Exception
	 */
	private function saveMetaField(MetaFieldModel $fieldModel)
	{
		ACPT_DB::startTransaction();

		MetaRepository::saveMetaBoxField($fieldModel);

		if($this->delete){
			MetaRepository::deleteMetaField($this->fieldId);
		}

		ACPT_DB::commitTransaction();
	}
}