<?php

namespace ACPT\Core\Generators\OptionPage;

use ACPT\Constants\MetaGroupDisplay;
use ACPT\Constants\MetaTypes;
use ACPT\Constants\Visibility;
use ACPT\Core\Generators\AbstractGenerator;
use ACPT\Core\Helper\Fields;
use ACPT\Core\Models\Meta\MetaGroupModel;
use ACPT\Core\Models\OptionPage\OptionPageModel;
use ACPT\Utils\Checker\BoxVisibilityChecker;
use ACPT\Utils\PHP\Cookie;

/**
 * *************************************************
 * OptionPageMetaBoxGenerator class
 * *************************************************
 *
 * @author Mauro Cassani
 * @link https://github.com/mauretto78/
 */
class OptionPageMetaGroupGenerator extends AbstractGenerator
{
	/**
	 * @var MetaGroupModel
	 */
	private MetaGroupModel $groupModel;

	/**
	 * @var OptionPageModel
	 */
	private OptionPageModel $optionPageModel;

	/**
	 * @var
	 */
	private $permissions;

	/**
	 * OptionPageMetaGroupGenerator constructor.
	 *
	 * @param MetaGroupModel $groupModel
	 * @param OptionPageModel $optionPageModel
	 * @param array $permissions
	 */
	public function __construct(MetaGroupModel $groupModel, OptionPageModel $optionPageModel, $permissions = [])
	{
		$this->groupModel = $groupModel;
		$this->optionPageModel = $optionPageModel;
		$this->permissions = $permissions;
	}

	/**
	 * @return string
	 */
	public function render()
	{
		if(empty($this->groupModel->getBoxes())){
			return null;
		}

		switch ($this->groupModel->getDisplay()){
			default:
			case MetaGroupDisplay::STANDARD:
				return $this->standardView();

			case MetaGroupDisplay::ACCORDION:
				return $this->accordion();

			case MetaGroupDisplay::VERTICAL_TABS:
				return $this->verticalTabs();

			case MetaGroupDisplay::HORIZONTAL_TABS:
				return $this->horizontalTabs();
		}
	}

	/**
	 * @return string
	 */
	private function standardView()
	{
		$return = '<div class="meta-box-sortables">';
		$return .= '<div class="metabox-holder">';

		foreach ($this->groupModel->getBoxes() as $boxModel){
		    $isVisible = BoxVisibilityChecker::check(Visibility::IS_BACKEND, $boxModel, $this->optionPageModel->getMenuSlug(), MetaTypes::OPTION_PAGE);

			if($isVisible and !empty($boxModel->getFields())){
				$boxGenerator = new OptionPageMetaBoxGenerator($boxModel, $this->optionPageModel->getMenuSlug(), $this->permissions);
				$return .= $boxGenerator->render();
			}
		}

		$return .= '</div>';
		$return .= '</div>';

		return $return;
	}

	/**
	 * @return string
	 */
	private function accordion()
	{
		$return = '<div class="acpt-metabox acpt-admin-accordion-wrapper" id="'.$this->groupModel->getId().'">';

		foreach ($this->groupModel->getBoxes() as $index => $metaBoxModel){

            $isVisible = BoxVisibilityChecker::check(Visibility::IS_BACKEND, $metaBoxModel, $this->optionPageModel->getMenuSlug(), MetaTypes::OPTION_PAGE);

            if($isVisible){
                $rows = $this->fieldRows($metaBoxModel->getFields());

                if(!empty($rows)){
                    $return .= '<div data-id="acpt_page_'.$this->optionPageModel->getMenuSlug().'_active_tab" class="acpt-admin-accordion-item '.$this->isActiveTab($metaBoxModel->getId(), $index).'" data-target="'.$metaBoxModel->getId().'">';
                    $return .= '<div class="acpt-admin-accordion-title">';
                    $return .= $this->metaBoxHeading($metaBoxModel);
                    $return .= '</div>';

                    $return .= '<div id="'.$metaBoxModel->getId().'" class="acpt-admin-accordion-content">';
                    $return .= '<div class="acpt-user-meta-box-wrapper" id="user-meta-box-'. $metaBoxModel->getId().'">';

                    foreach ($rows as $row){
                        $return .= "<div class='acpt-admin-meta-row ".($row['isVisible'] == 0 ? ' hidden' : '')."'>";

                        foreach ($row['fields'] as $field){
                            $return .= $field;
                        }

                        $return .= "</div>";
                    }

                    $return .= '</div>';
                    $return .= '</div>';
                    $return .= '</div>';
                }
            }
		}

		$return .= '</div>';

		return $return;
	}

	/**
	 * @return string
	 */
	private function horizontalTabs()
	{
		$return = '<div class="acpt-metabox acpt-admin-horizontal-tabs-wrapper" id="'.$this->groupModel->getId().'">';
		$return .= '<div class="acpt-admin-horizontal-tabs-btn-wrapper">';
		$return .= '<div class="acpt-admin-horizontal-tabs">';

		foreach ($this->groupModel->getBoxes() as $index => $metaBoxModel){

            $isVisible = BoxVisibilityChecker::check(Visibility::IS_BACKEND, $metaBoxModel, $this->optionPageModel->getMenuSlug(), MetaTypes::OPTION_PAGE);

            if($isVisible){
                $rows = $this->fieldRows($metaBoxModel->getFields());

                if(!empty($rows)){
                    $return .= '<div data-id="acpt_page_'.$this->optionPageModel->getMenuSlug().'_active_tab" class="acpt-admin-horizontal-tab '.$this->isActiveTab($metaBoxModel->getId(), $index).'" data-target="'.$metaBoxModel->getId().'">';
                    $return .= $metaBoxModel->getUiName();
                    $return .= '</div>';
                }
            }
		}

		$return .= '</div>';
		$return .= $this->metaBoxEditLinkButton($this->groupModel->getBoxById($this->activeTabId()) ?? $this->groupModel->getBoxes()[0]);
		$return .= '</div>';
		$return .= '<div class="acpt-admin-horizontal-panels">';

		foreach ($this->groupModel->getBoxes() as $index => $metaBoxModel){

            $isVisible = BoxVisibilityChecker::check(Visibility::IS_BACKEND, $metaBoxModel, $this->optionPageModel->getMenuSlug(), MetaTypes::OPTION_PAGE);

            if($isVisible){
                $rows = $this->fieldRows($metaBoxModel->getFields());

                if(!empty($rows)){
                    $return .= '<div id="'.$metaBoxModel->getId().'" class="acpt-admin-horizontal-panel '.$this->isActiveTab($metaBoxModel->getId(), $index).'">';

                    foreach ($rows as $row){
                        $return .= "<div class='acpt-admin-meta-row ".($row['isVisible'] == 0 ? ' hidden' : '')."'>";

                        foreach ($row['fields'] as $field){
                            $return .= $field;
                        }

                        $return .= "</div>";
                    }

                    $return .= '</div>';
                }
            }
		}

		$return .= '</div>';

		return $return;
	}

	/**
	 * @return string
	 */
	private function verticalTabs()
	{
        $return = '<div class="acpt-admin-vertical-tabs-btn-wrapper">';
        $return .= $this->metaBoxEditLinkButton($this->groupModel->getBoxById($this->activeTabId()) ?? $this->groupModel->getBoxes()[0]);
        $return .= '</div>';
		$return .= '<div class="acpt-metabox acpt-admin-vertical-tabs-wrapper" id="'.$this->groupModel->getId().'">';
		$return .= '<div class="acpt-admin-vertical-tabs">';

		foreach ($this->groupModel->getBoxes() as $index => $metaBoxModel){

            $isVisible = BoxVisibilityChecker::check(Visibility::IS_BACKEND, $metaBoxModel, $this->optionPageModel->getMenuSlug(), MetaTypes::OPTION_PAGE);

            if($isVisible){
                $rows = $this->fieldRows($metaBoxModel->getFields());

                if(!empty($rows)){
                    $return .= '<div data-id="acpt_page_'.$this->optionPageModel->getMenuSlug().'_active_tab" class="acpt-admin-vertical-tab '.$this->isActiveTab($metaBoxModel->getId(), $index).'" data-target="'.$metaBoxModel->getId().'">';
                    $return .= $metaBoxModel->getUiName();
                    $return .= '</div>';
                }
            }
		}

		$return .= '</div>';
		$return .= '<div class="acpt-admin-vertical-panels">';

		foreach ($this->groupModel->getBoxes() as $index => $metaBoxModel){

            $isVisible = BoxVisibilityChecker::check(Visibility::IS_BACKEND, $metaBoxModel, $this->optionPageModel->getMenuSlug(), MetaTypes::OPTION_PAGE);

            if($isVisible){
                $rows = $this->fieldRows($metaBoxModel->getFields());

                if(!empty($rows)){
                    $return .= '<div id="'.$metaBoxModel->getId().'" class="acpt-admin-vertical-panel '.$this->isActiveTab($metaBoxModel->getId(), $index).'">';

                    foreach ($rows as $row){
                        $return .= "<div class='acpt-admin-meta-row ".($row['isVisible'] == 0 ? ' hidden' : '')."'>";

                        foreach ($row['fields'] as $field){
                            $return .= $field;
                        }

                        $return .= "</div>";
                    }

                    $return .= '</div>';
                }
            }
		}

		$return .= '</div>';
		$return .= '</div>';

		return $return;
	}

	/**
	 * @param $fields
	 *
	 * @return array
	 */
	private function fieldRows($fields)
	{
		$rows = Fields::extractFieldRows($fields);
		$fieldRows = [];
		$visibleFieldsTotalCount = 0;

		// build the field rows array
		foreach ($rows as $index => $row){

			$visibleFieldsRowCount = 0;

			foreach ($row as $field){
				$fieldGenerator = new OptionPageMetaBoxFieldGenerator($field, $this->optionPageModel->getMenuSlug(), $this->optionPageModel->userPermissions());
				$optionPageField = $fieldGenerator->generate();

				if($optionPageField){
					if($optionPageField->isVisible()){
						$visibleFieldsTotalCount++;
						$visibleFieldsRowCount++;
					}

					$fieldRows[$index]['fields'][] = $optionPageField->render();
					$fieldRows[$index]['isVisible'] = $visibleFieldsRowCount;
				}
			}
		}

		return $fieldRows;
	}

    /**
     * @param $id
     * @param $index
     *
     * @return string
     */
	private function isActiveTab($id, $index)
    {
        $activeTab = $this->activeTabId();

        if($activeTab !== null){
            return ($activeTab === $id) ? 'active' : '';
        }

        return ($index === 0 ? 'active' : '');
    }

    /**
     * @return mixed|null
     */
    private function activeTabId()
    {
        $activeTabId = 'acpt_page_'.$this->optionPageModel->getMenuSlug().'_active_tab';

        return Cookie::get($activeTabId);
    }
}