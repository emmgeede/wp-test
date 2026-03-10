<?php

namespace ACPT\Utils\Checker;

use ACPT\Constants\MetaTypes;
use ACPT\Constants\Operator;
use ACPT\Constants\Visibility;
use ACPT\Core\Models\Meta\MetaBoxModel;
use ACPT\Utils\PHP\Arrays;
use ACPT\Utils\PHP\Logics;

class BoxVisibilityChecker
{
    /**
     * @param              $visibility
     * @param MetaBoxModel $metaBoxModel
     * @param null         $elementId
     * @param null         $context
     *
     * @return bool
     */
    public static function check(
        $visibility,
        MetaBoxModel $metaBoxModel,
        $elementId = null,
        $context = null
    )
    {
        if(!in_array($visibility, [
            Visibility::IS_BACKEND,
            Visibility::IS_FRONTEND
        ])){
            return true;
        }

        try {
            if($metaBoxModel === null or !$metaBoxModel->hasVisibilityConditions()){
                return true;
            }

            $visibilityConditions = $metaBoxModel->getVisibilityConditions();
            $logicBlocks = Logics::extractLogicBlocks($visibilityConditions, $visibility);

            if(empty($logicBlocks)){
                return true;
            }

            foreach ($logicBlocks as $logicBlocksConditions){
                if(self::returnTrueOrFalseForALogicBlock(
                    $elementId,
                    $metaBoxModel,
                    $logicBlocksConditions,
                    $context
                )){
                    return true;
                }
            }

            return false;
        } catch (\Exception $exception){

            do_action("acpt/error", $exception);

            return true;
        }
    }

    /**
     * @param              $elementId
     * @param MetaBoxModel $metaBoxFieldModel
     * @param array        $conditions
     * @param null         $context
     *
     * @return bool
     */
    private static function returnTrueOrFalseForALogicBlock(
        $elementId,
        MetaBoxModel $metaBoxFieldModel,
        array $conditions,
        $context = null
    )
    {
        $matches = 0;

        foreach ($conditions as $condition){
            $typeEnum = $condition->getType()['type'];
            $typeValue = $condition->getType()['value'];
            $operator = $condition->getOperator();
            $value = $condition->getValue();

            if($typeEnum === 'POST_STATUS'){
                $postStatus = get_post_status($elementId) ?? null;

                switch ($operator) {
                    case Operator::EQUALS:
                        if($value == $postStatus){
                            $matches++;
                        }
                        break;

                    case Operator::NOT_EQUALS:
                        if($value !== $postStatus){
                            $matches++;
                        }
                        break;

                    case Operator::IN:
                        $value = trim($value);
                        $value = explode(',', $value);

                        if(in_array($postStatus, $value)){
                            $matches++;
                        }
                        break;

                    case Operator::NOT_IN:
                        $value = trim($value);
                        $value = explode(',', $value);

                        if(!in_array($postStatus, $value)){
                            $matches++;
                        }
                        break;
                }
            }

            if(
                ($typeEnum === 'POST_ID' and $context === MetaTypes::CUSTOM_POST_TYPE) or
                ($typeEnum === 'TERM_ID' and $context === MetaTypes::TAXONOMY) or
                ($typeEnum === 'USER_ID' and $context === MetaTypes::USER) or
                ($typeEnum === 'OPTION_PAGE' and $context === MetaTypes::OPTION_PAGE)
            ){
                switch ($operator) {
                    case Operator::EQUALS:
                        if($value == $elementId){
                            $matches++;
                        }
                        break;

                    case Operator::NOT_EQUALS:
                        if($value !== $elementId){
                            $matches++;
                        }
                        break;

                    case Operator::IN:
                        $value = trim($value);
                        $value = explode(',', $value);

                        if(in_array($elementId, $value)){
                            $matches++;
                        }
                        break;

                    case Operator::NOT_IN:
                        $value = trim($value);
                        $value = explode(',', $value);

                        if(!in_array($elementId, $value)){
                            $matches++;
                        }
                        break;
                }
            }

            if($typeEnum === 'USER'){
                $currentUserId = get_current_user_id();

                if($currentUserId == 0){
                    return false;
                }

                switch ($operator) {
                    case Operator::EQUALS:
                        if($value == $currentUserId){
                            $matches++;
                        }
                        break;

                    case Operator::NOT_EQUALS:
                        if($value != $currentUserId){
                            $matches++;
                        }
                        break;

                    case Operator::IN:
                        $value = explode(',', (string)$value );
                        if(in_array($currentUserId, $value)){
                            $matches++;
                        }
                        break;

                    case Operator::NOT_IN:
                        $value = explode(',', (string)$value );
                        if(!in_array($currentUserId, $value)){
                            $matches++;
                        }
                        break;
                }
            }

            if($typeEnum === 'TAXONOMY'){

                $categories = wp_get_post_categories((int)$elementId);
                $taxonomies = wp_get_post_terms((int)$elementId, $typeValue);

                if(is_array($taxonomies)){
                    $allTerms = array_merge($categories, $taxonomies);
                    $termIds = [];

                    foreach ($allTerms as $term){
                        if(isset($term->term_id)){
                            $termIds[] = $term->term_id;
                        }
                    }

                    switch ($operator) {

                        case Operator::EQUALS:
                            $termIds = is_array($termIds) ? $termIds : [$termIds];

                            if([(int)$value] === $termIds){
                                $matches++;
                            }
                            break;

                        case Operator::NOT_EQUALS:
                            $termIds = is_array($termIds) ? $termIds : [$termIds];

                            if([(int)$value] !== $termIds){
                                $matches++;
                            }
                            break;

                        case Operator::IN:
                            $value = trim( (string)$value );
                            $value = explode(',', $value);
                            $termIds = is_array($termIds) ? $termIds : [$termIds];

                            $check = array_intersect($termIds, $value);

                            if(Arrays::count($check) > 0){
                                $matches++;
                            }
                            break;

                        case Operator::NOT_IN:
                            $value = trim( (string)$value );
                            $value = explode(',', $value);
                            $termIds = is_array($termIds) ? $termIds : [$termIds];

                            $check = array_intersect($termIds, $value);

                            if(empty($check)){
                                $matches++;
                            }
                            break;

                        case Operator::BLANK:
                            if(empty($termIds)){
                                $matches++;
                            }
                            break;


                        case Operator::NOT_BLANK:
                            if(!empty($termIds)){
                                $matches++;
                            }
                            break;
                    }
                }
            }
        }

        return $matches === Arrays::count($conditions);
    }
}