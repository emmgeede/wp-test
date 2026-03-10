<?php

namespace ACPT\Core\Generators\CustomPostType;

use ACPT\Constants\BelongsTo;
use ACPT\Constants\Logic;
use ACPT\Constants\MetaTypes;
use ACPT\Core\Models\Meta\MetaGroupModel;
use ACPT\Core\Repository\MetaRepository;
use ACPT\Utils\Wordpress\WPUtils;

class CustomPostTypeMetaGroupsGenerator
{
	/**
	 * @var MetaGroupModel[]
	 */
	private $metaGroupModels = [];

    /**
     * @var string|null
     */
    private $postType;

    /**
     * CustomPostTypeMetaGroupsGenerator constructor.
     *
     * @param $postType
     * @param $metaGroupModels
     */
	public function __construct($postType, $metaGroupModels)
	{
		$this->metaGroupModels = $metaGroupModels;
        $this->postType = $postType;
    }

    /**
     * Generate meta boxes related to post types
     *
     * @param bool $render
     *
     * @return array
     */
	public function generate($render = true)
	{
		$groups = [];

		foreach ($this->metaGroupModels as $metaGroupModel){

			$cptBelongs = [];
			$otherAndConditions = false;
			$allowedConditions = [
				MetaTypes::MEDIA,
				MetaTypes::CUSTOM_POST_TYPE,
				BelongsTo::PARENT_POST_ID,
				BelongsTo::POST_ID,
				BelongsTo::POST_TEMPLATE,
				BelongsTo::POST_TAX,
				BelongsTo::POST_CAT,
			];

			foreach ($metaGroupModel->getBelongs() as $index => $belong){

				// allow only cpt belongs
				if(in_array($belong->getBelongsTo(), $allowedConditions)){
					$cptBelongs[] = $belong;
				} else {

					if($belong->getLogic() === Logic::AND){
						$otherAndConditions = true;
					}

					if(isset($metaGroupModel->getBelongs()[$index-1]) and $metaGroupModel->getBelongs()[$index-1]->getLogic() === Logic::AND ){
						$otherAndConditions = true;
					}
				}
			}

			if($otherAndConditions === false){
                $groups[] = $metaGroupModel;

                if($render and $this->postType !== 'attachment'){
                    $generator = new CustomPostTypeMetaGroupGenerator($metaGroupModel, $this->postType);
                    $generator->render();
                }
			}

            $this->renderForAttachments();
		}

		return $groups;
	}

    /**
     * Render meta fields for attachments
     *
     * @return array
     */
	private function renderForAttachments()
    {
        add_filter("attachment_fields_to_edit", function ($formFields, $post){

            $postId = $post->ID;
            $meta_groups = MetaRepository::get([]);

            foreach ($meta_groups as $meta_group){
                if($meta_group->isVisible([
                    'post_id' => $postId
                ])){
                    foreach ($meta_group->getBoxes() as $meta_box_model){
                        foreach ($meta_box_model->getFields() as $fieldModel){
                            $fieldGenerator = CustomPostTypeMetaBoxFieldGenerator::generate($fieldModel, $postId);

                            $formFields[$fieldModel->getDbName()] = array(
                                "label" => $fieldModel->getLabelOrName(),
                                "input" => "html",
                                "value" => get_post_meta($postId, $fieldModel->getDbName(), true),
                                "html" => $fieldGenerator->render()
                            );
                        }
                    }
                }
            }

            return $formFields;
        }, null, 2);

        add_filter( 'attachment_fields_to_save', function ($post, $attachment){

            $postId = $post['post_ID'] ?? $post['ID'];

            if(!empty($postId)){
                $metaGroups = MetaRepository::get([]);
                $metaGroups = array_filter($metaGroups, function (MetaGroupModel $metaGroup) use ($postId){
                    return $metaGroup->isVisible([
                            'post_id' => $postId
                    ]);
                });

                WPUtils::handleSavePost($postId, 'attachment', $metaGroups);
            }

            return $post;
        }, 10, 2 );
    }
}
