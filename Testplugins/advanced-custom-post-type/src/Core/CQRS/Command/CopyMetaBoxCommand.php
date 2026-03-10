<?php

namespace ACPT\Core\CQRS\Command;

use ACPT\Core\Repository\MetaRepository;
use ACPT\Core\Validators\ArgumentsArrayValidator;
use ACPT\Includes\ACPT_DB;
use ACPT\Utils\PHP\Arrays;

class CopyMetaBoxCommand extends AbstractCopyCommand implements CommandInterface
{
	/**
	 * @var string
	 */
	private $boxId;

	/**
	 * @var
	 */
	private $targetGroupId;

	/**
	 * @var bool
	 */
	private $delete;

    /**
     * @var int|null
     */
    private $position;

	/**
	 * CopyMetaBoxCommand constructor.
	 *
	 * @param $data
	 */
	public function __construct($data)
	{
		parent::__construct();
		$validationRules = [
			'boxId' => [
				'required' => true,
				'type' => 'string',
			],
			'targetGroupId' => [
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

		$this->boxId = $data['boxId'];
		$this->targetGroupId = $data['targetGroupId'];
		$this->delete = $data['delete'] ? $data['delete'] : null;
		$this->position = isset($data['position']) ? (int)$data['position'] : null;
	}

	/**
	 * @return mixed|void
	 * @throws \Exception
	 */
	public function execute()
	{
		$targetMetaGroup = MetaRepository::get([
			'id' => $this->targetGroupId
		]);

		if(empty($targetMetaGroup)){
			throw new \Exception("Group id not found");
		}

		$metaBox = MetaRepository::getMetaBoxById($this->boxId);

		if(empty($metaBox)){
			throw new \Exception("Box id not found");
		}

		$duplicatedMetaBox = $this->copyBox($metaBox, $targetMetaGroup[0]);
        $duplicatedMetaBox->changeSort(Arrays::count($targetMetaGroup[0]->getBoxes())+1);

        if($this->position !== null){
            $duplicatedMetaBox->changeSort($this->position+1);

            foreach ($targetMetaGroup[0]->getBoxes() as $sort => $box){
                $newSort = ($sort > $this->position) ? $sort+1 : $sort;
                $box->changeSort($newSort);
                MetaRepository::saveMetaBox($box);
            }
        }

		ACPT_DB::startTransaction();

		MetaRepository::saveMetaBox($duplicatedMetaBox);

		if($this->delete){
			MetaRepository::deleteMetaBoxById([
				'id' => $this->boxId,
			]);
		}

		ACPT_DB::commitTransaction();
	}
}