<?php

namespace ACPT\Core\CQRS\Command;

use ACPT\Constants\MetaTypes;
use ACPT\Core\Helper\Strings;
use ACPT\Core\Repository\MetaRepository;
use ACPT\Integrations\WooCommerce\ACPT_WooCommerce;

class SaveWooCommerceProductAttributeMetaCommand extends AbstractSaveMetaCommand implements CommandInterface
{
    /**
     * @var int
     */
    private $attributeId;

    /**
     * @var string
     */
    private $attributeName;

    /**
     * SaveWooCommerceProductAttributeMetaCommand constructor.
     *
     * @param       $attributeId
     * @param array $data
     */
    public function __construct($attributeId,  array $data = [])
    {
        parent::__construct($data);
        $this->attributeId = $attributeId;
        $this->attributeName = ACPT_WooCommerce::getWooCommerceProductAttributeName($attributeId);
    }

    /**
     * @return mixed|void
     * @throws \Exception
     */
    public function execute()
    {
        $metaGroups = MetaRepository::get([
            'belongsTo' => MetaTypes::TAXONOMY,
            'find' => $this->attributeName,
            'clonedFields' => true,
        ]);

        foreach ($metaGroups as $metaGroup){
            foreach ($metaGroup->getBoxes() as $boxModel) {
                foreach ($boxModel->getFields() as $fieldModel) {
                    if($this->hasField($fieldModel)){
                        $fieldModel->setBelongsToLabel(MetaTypes::OPTION_PAGE);
                        $fieldModel->setFindLabel($this->attributeName);
                        $fieldModel->setIsAWooCommerceProductAttribute(true);

                        $elementId = "wc_attribute_" . Strings::toDBFormat( $fieldModel->getBox()->getName() ) . '_' . Strings::toDBFormat($fieldModel->getName() ) . "_" . $this->attributeId;
                        $this->saveField($fieldModel, $elementId, MetaTypes::OPTION_PAGE);
                    }
                }
            }
        }
    }
}
