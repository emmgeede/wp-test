<?php

namespace ACPT\Core\CQRS\Command;

use ACPT\Core\Models\Form\FormFieldModel;
use ACPT\Core\Models\Validation\ValidationRuleModel;
use ACPT\Core\Repository\FormRepository;
use ACPT\Core\Repository\MetaRepository;
use ACPT\Utils\PHP\Arrays;

class SaveFormFieldsCommand implements CommandInterface, LogFormatInterface
{
	/**
	 * @var string
	 */
	private string $id;

	/**
	 * @var array
	 */
	private array $data;

	/**
	 * SaveFormFieldsCommand constructor.
	 *
	 * @param string $id
	 * @param array $data
	 */
	public function __construct($id, array $data)
	{
		$this->id = $id;
		$this->data = $data;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function execute(): string
	{
		$formModel = FormRepository::getById($this->id);

		if($formModel === null){
			throw new \Exception("Form with id ".$this->id." does not exists");
		}

		$formModel->resetFields();

		$fields = array_filter($this->data, function ($f){
		    return $f['parentId'] === null and $f['parentTab'] === null;
        });

		if(!empty($fields)){
            $fields = Arrays::reindex($fields);
        }

		foreach ($fields as $fieldIndex => $field){
            $fieldModel = $this->hydrateFormField($field, $fieldIndex);
			$formModel->addField($fieldModel);
		}

		FormRepository::save($formModel);

        do_action("acpt/form_fields/save", $this, $formModel);

		return $formModel->getId();
	}

    /**
     * @param                     $field
     * @param                     $fieldIndex
     * @param FormFieldModel|null $parentFieldModel
     *
     * @return FormFieldModel
     * @throws \Exception
     */
	private function hydrateFormField($field, $fieldIndex, ?FormFieldModel $parentFieldModel = null)
    {
        $metaFieldModel = null;
        if(isset($field['metaFieldId']) and $field['metaFieldId'] !== null){
            $metaFieldModel = MetaRepository::getMetaFieldById($field['metaFieldId']);
        }

        $isRequired = $field['required'] ?? $field['is_required'] ?? $field['isRequired'];

        $fieldModel = FormFieldModel::hydrateFromArray([
                'id' => $field['id'],
                'metaField' => $metaFieldModel,
                'group' => $field['group'],
                'key' => $field['key'],
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'description' => $field['description'],
                'isRequired' => (bool)$isRequired,
                'extra' => $field['extra'],
                'settings' => $field['settings'],
                'sort' => ($fieldIndex+1),
        ]);

        if($field['parentTab']){
            $fieldModel->setParentTabIndex($field['parentTab']);
        }

        if($parentFieldModel !== null){
            $fieldModel->setParentField($parentFieldModel);
        }

        // rules
        if(isset($field['rules']) and !empty($field['rules'])){
            foreach ($field['rules'] as $ruleIndex => $rule){

                $validationRuleModel = ValidationRuleModel::hydrateFromArray([
                        'id' => $rule['id'],
                        'condition' => $rule['condition'],
                        'value' => $rule['value'],
                        'message' => $rule['message'],
                        'sort' => ($ruleIndex+1),
                ]);

                $fieldModel->addValidationRule($validationRuleModel);
            }
        }

        // children
        $children = array_filter($this->data, function ($c) use ($field) {
            return $c['parentId'] === $field['key'];
        });

        if(!empty($children)){
            $children = Arrays::reindex($children);
        }

        foreach ($children as $childIndex => $child){
            $childFieldModel = $this->hydrateFormField($child, $childIndex, $fieldModel);
            $fieldModel->addChild($childFieldModel, $child['parentTab'] ?? null, );
        }

        return $fieldModel;
    }

    /**
     * @inheritDoc
     */
    public function logFormat(): array
    {
        return [
            "class"  => SaveFormFieldsCommand::class,
            'data' => $this->data
        ];
    }
}