<?php

namespace ACPT\Core\CQRS\Command;

use ACPT\Core\Repository\MetaRepository;
use ACPT\Core\Validators\ArgumentsArrayValidator;
use ACPT\Includes\ACPT_DB;
use ACPT\Utils\PHP\Arrays;

class CopyMetaBlockCommand extends AbstractCopyCommand implements CommandInterface
{
	/**
	 * @var mixed
	 */
	private $targetFieldId;

	/**
	 * @var mixed
	 */
	private $blockId;

	/**
	 * @var mixed|null
	 */
	private $delete;

    /**
     * @var int|null
     */
    private ?int $position;

    /**
	 * CopyMetaBlockCommand constructor.
	 *
	 * @param $data
	 */
	public function __construct($data)
	{
		parent::__construct();
		$validationRules = [
			'targetFieldId' => [
				'required' => true,
				'type' => 'string',
			],
			'blockId' => [
				'required' => true,
				'type' => 'string',
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

		$this->targetFieldId = $data['targetFieldId'];
		$this->blockId       = $data['blockId'];
		$this->delete        = $data['delete'] ? $data['delete'] : null;
        $this->position      = isset($data['position']) ? (int)$data['position'] : null;
	}

	/**
	 * @return mixed|void
	 * @throws \Exception
	 */
	public function execute()
	{
		$blockModel = MetaRepository::getMetaBlockById($this->blockId);

		if(empty($blockModel)){
			throw new \Exception("Meta block id not found");
		}

		$targetFieldModel = MetaRepository::getMetaFieldById($this->targetFieldId);

		if($targetFieldModel === null){
			throw new \Exception("Target field not found");
		}

        ACPT_DB::startTransaction();

		$duplicatedMetaBlock = $this->copyBlock($blockModel, $targetFieldModel);
        $duplicatedMetaBlock->changeSort(Arrays::count($targetFieldModel->getBlocks())+1);

        if($this->position !== null){
            $duplicatedMetaBlock->changeSort($this->position+1);

            foreach ($targetFieldModel->getBlocks() as $sort => $block){
                $newSort = ($sort > $this->position) ? $sort+1 : $sort;
                $block->changeSort($newSort);
                MetaRepository::saveMetaBlock($block);
            }
        }

		MetaRepository::saveMetaBlock($duplicatedMetaBlock);

		if($this->delete){
			MetaRepository::deleteMetaBlock($this->blockId);
		}

		ACPT_DB::commitTransaction();
	}
}