<?php

namespace ACPT\Core\CQRS\Command;

use ACPT\Constants\ExtraFields;
use ACPT\Constants\FormAction;
use ACPT\Constants\MetaTypes;
use ACPT\Constants\TaxonomyField;
use ACPT\Core\Helper\Strings;
use ACPT\Core\Models\Form\FormFieldModel;
use ACPT\Core\Models\Form\FormModel;
use ACPT\Core\Models\Form\FormSubmissionModel;
use ACPT\Core\Models\Meta\MetaFieldModel;
use ACPT\Core\Models\Settings\SettingsModel;
use ACPT\Core\Models\Validation\ValidationRuleModel;
use ACPT\Core\Repository\FormRepository;
use ACPT\Core\Repository\MetaRepository;
use ACPT\Core\ValueObjects\FormSubmissionDatumObject;
use ACPT\Core\ValueObjects\FormSubmissionErrorObject;
use ACPT\Core\ValueObjects\FormSubmissionLimitObject;
use ACPT\Utils\Checker\ValidationRulesChecker;
use ACPT\Utils\Data\Meta;
use ACPT\Utils\Data\Sanitizer;
use ACPT\Utils\PHP\Arrays;
use ACPT\src\Utils\PHP\PHPEval\PhpEval;
use ACPT\Utils\PHP\Twig;
use ACPT\Utils\Settings\Settings;
use ACPT\Utils\Wordpress\Email\EmailTemplateFactory;
use ACPT\Utils\Wordpress\Email\Envelope;
use ACPT\Utils\Wordpress\Email\EnvelopeBody;
use ACPT\Utils\Wordpress\Files;
use ACPT\Utils\Wordpress\Nonce;
use ACPT\Utils\Wordpress\WPAttachment;

class HandleFormSubmissionCommand implements CommandInterface, LogFormatInterface
{
	/**
	 * @var FormModel
	 */
	private FormModel $formModel;

	/**
	 * @var array
	 */
	private $data;

    /**
     * @var
     */
    private $file;

	/**
	 * @var array
	 */
	private $errors = [];

	/**
	 * @var null
	 */
	private $postId;

	/**
	 * @var null
	 */
	private $userId;

	/**
	 * @var null
	 */
	private $termId;

    /**
     * HandleFormSubmissionCommand constructor.
     * @param FormModel $formModel
     * @param null $postId
     * @param null $termId
     * @param null $userId
     * @param array $data
     * @param array $files
     * @throws \Exception
     */
	public function __construct(
		FormModel $formModel,
		$postId = null,
		$termId = null,
		$userId = null,
		array $data = [],
		array $files = []
	)
	{
		$this->formModel = $formModel;
		$this->data = $this->sanitizeData($data, $files);
		$this->postId = $postId;
		$this->termId = $termId;
		$this->userId = $userId;

		$this->checkTheNonce($data[$this->formModel->getId()]);

		// for PHP handling
		$_POST = $this->data;

		do_action('acpt/save_form', $this, $formModel);
	}

    private function addError($id, $message)
    {
        $this->errors[] = [
            "id" => $id,
            "message" => $message,
        ];
    }

    /**
     * @param $data
     * @param $files
     *
     * @return mixed
     * @throws \Exception
     */
	private function sanitizeData(array $data = [], array $files = [])
	{
		$sanitized = [];

		foreach ($this->formModel->getFields() as $fieldModel){
			$this->recursiveSanitizeFieldValue($fieldModel, $data, $files, $sanitized);
		}

		// captcha
		if(isset($data['g-recaptcha-response'])){
			$sanitized['g-recaptcha-response'] = [
				'type' => FormFieldModel::CAPTCHA_TYPE,
				'value' => Sanitizer::sanitizeRawData(FormFieldModel::CAPTCHA_TYPE, $data['g-recaptcha-response']),
			];
		}

		// turnstile
		if(isset($data['cf-turnstile-response'])){
			$sanitized['cf-turnstile-response'] = [
				'type' => FormFieldModel::TURNSTILE_TYPE,
				'value' => Sanitizer::sanitizeRawData(FormFieldModel::TURNSTILE_TYPE, $data['cf-turnstile-response']),
			];
		}

		return $sanitized;
	}

    /**
     * @param FormFieldModel $fieldModel
     * @param                $data
     * @param                $files
     * @param                $sanitized
     */
	private function recursiveSanitizeFieldValue( FormFieldModel $fieldModel, $data, $files, &$sanitized)
    {
        $sanitized[] = $this->sanitizeFieldValue($fieldModel, $data, $files);

        foreach ($fieldModel->getChildren() as $childFieldModel){
            $this->recursiveSanitizeFieldValue($childFieldModel, $data, $files, $sanitized);
        }
    }

    /**
     * @param FormFieldModel $fieldModel
     * @param                $data
     * @param                $files
     *
     * @return array
     */
	private function sanitizeFieldValue(FormFieldModel $fieldModel, $data, $files)
    {
        // file handling
        $type = $data[$fieldModel->getName().'_type'];
        $key = $data[$fieldModel->getName().'_key'];
        $value = null;
        $extra = [];
        $allowHtml = false;
        $allowDangerousContent = false;

        if($this->isARepeaterField($type)){
            $this->handleRepeaterField($fieldModel, $data, $files, $value, $extra);
        } elseif($this->isAFileField($type)){
            $this->handleUploadFile($fieldModel, $data, $files, $value, $extra);
        } else {
            if(isset($data[$fieldModel->getName()])){
                $rawData = $data[$fieldModel->getName()];

                if($fieldModel->getMetaField() !== null){

                    $fieldType = $fieldModel->getMetaField()->getType();

                    // Textual fields
                    if($fieldModel->getType() === MetaFieldModel::TEXT_TYPE or $fieldType === MetaFieldModel::TEXTAREA_TYPE){
                        $allowHtml = $fieldModel->getMetaField()->getAdvancedOption("allow_html") ?? false;
                    }

                    // HTML fields
                    if($fieldType === MetaFieldModel::HTML_TYPE){
                        $allowHtml = true;
                        $allowDangerousContent = $fieldModel->getMetaField()->getAdvancedOption("allow_dangerous_content") ?? false;
                    }
                }

                $value = Sanitizer::sanitizeRawData($type, $rawData, $allowHtml, $allowDangerousContent);
            }
        }

        foreach (ExtraFields::ALLOWED_VALUES as $e){
            if(isset($data[$fieldModel->getName().'_'.$e]) and !isset($extra[$e])){
                $extra[$e] = $data[$fieldModel->getName().'_'.$e];
            }
        }

        return [
            'id' => $fieldModel->getId(),
            'type' => $type,
            'key' => $key,
            'value' => $value,
            'name' => $fieldModel->getName(),
            'required' => $fieldModel->isRequired(),
            'isMeta' => $fieldModel->isACPTMetaField(),
            'isPost' => $fieldModel->isWordPressPostField(),
            'isTerm' => $fieldModel->isWordPressTermField(),
            'isUser' => $fieldModel->isWordPressUserField(),
            'extra' => $extra,
            'allowHtml' => $allowHtml,
            'allowDangerousContent' => $allowDangerousContent,
        ];
    }

    /**
     * @param $type
     *
     * @return bool
     */
    private function isAFileField($type): bool
    {
        $fileTypes = [
            FormFieldModel::WORDPRESS_POST_THUMBNAIL,
            FormFieldModel::FILE_TYPE,
            MetaFieldModel::IMAGE_TYPE,
            MetaFieldModel::IMAGE_SLIDER_TYPE,
            MetaFieldModel::VIDEO_TYPE,
            MetaFieldModel::AUDIO_MULTI_TYPE,
            MetaFieldModel::AUDIO_TYPE,
            MetaFieldModel::GALLERY_TYPE,
        ];

        return in_array($type, $fileTypes);
    }

    /**
     * @param $type
     *
     * @return bool
     */
    private function isAMultipleFileField($type): bool
    {
        $fileTypes = [
            MetaFieldModel::AUDIO_MULTI_TYPE,
            MetaFieldModel::IMAGE_SLIDER_TYPE,
            MetaFieldModel::GALLERY_TYPE,
        ];

        return in_array($type, $fileTypes);
    }

    /**
     * @param $type
     *
     * @return bool
     */
    private function isARepeaterField($type): bool
    {
        $fileTypes = [
            FormFieldModel::REPEATER_TYPE,
        ];

        return in_array($type, $fileTypes);
    }

    /**
     * @param FormFieldModel $fieldModel
     * @param $data
     * @param $files
     * @param null $value
     * @param array $extra
     */
    private function handleRepeaterField(FormFieldModel $fieldModel, $data, $files, &$value = null, &$extra = [])
    {
        $rawFiles = $files[$fieldModel->getName()];
        $rawData = $data[$fieldModel->getName()];

        if(!empty($rawFiles) and is_array($rawFiles)){
            foreach ($rawFiles['tmp_name'] as $fieldName => $rawFile){

                $urls = [];

                foreach ($rawFile as $i => $f){
                    $tmpFile = $f["value"];
                    $size = $rawFiles['size'][$fieldName][$i]['value'];
                    $filename = $rawFiles['name'][$fieldName][$i]['value'];

                    if(!empty($tmpFile)){

                        // gallery/audio playlist
                        if(is_array($tmpFile)){
                            for ($k = 0; $k < Arrays::count($tmpFile); $k++){

                                if(!empty($tmpFile[$k])){
                                    $this->checkFileSize( $size[$k], $fieldModel);
                                    $this->checkImageDimension($tmpFile[$k], $fieldModel);
                                    $this->checkImageMimeType($tmpFile[$k], $fieldModel);
                                    $file = Files::uploadFile($tmpFile[$k], $filename[$k]);

                                    if($file === false){
                                        $this->addError($fieldName, "File upload failed");
                                    }

                                    $urls[$fieldName][$i]['value'][$k] = $file['url'];
                                    $urls[$fieldName][$i]['attachment_id'][$k] = $file['attachmentId'];
                                }
                            }
                        } else {
                            $this->checkFileSize($size, $fieldModel);
                            $this->checkImageDimension($tmpFile, $fieldModel);
                            $this->checkImageMimeType($tmpFile, $fieldModel);
                            $file = Files::uploadFile($tmpFile, $filename);

                            if($file === false){
                                $this->addError($fieldName, "File upload failed");
                            }

                            $urls[$fieldName][$i]['value'] = $file['url'];
                            $urls[$fieldName][$i]['attachment_id'] = $file['attachmentId'];
                        }
                    }
                }

                $value = $urls;
            }
        }

        if(!empty($rawData) and is_array($rawData)){
            foreach ($rawData as $fieldName => $rawDatum){
                foreach ($rawDatum as $i => $d){
                    $rawData = $d["value"];

                    if(empty($value[$fieldName][$i]['value'])){
                        $newValue = Sanitizer::sanitizeRawData($d["type"], $rawData);

                        if(!empty($newValue) and [0 => ""] !== $newValue){
                            $value[$fieldName][$i]['value'] = $newValue;
                        }
                    }

                    // Extra info
                    foreach (ExtraFields::ALLOWED_VALUES as $e){
                        if(isset($d[$e]) and !isset($value[$fieldName][$i][$e])){
                            $value[$fieldName][$i][$e] = $d[$e];
                        }
                    }

                    // Fix attachment_id
                    if($d["type"] === FormFieldModel::FILE_TYPE and empty($value[$fieldName][$i]['attachment_id'])){
                        $attachment = WPAttachment::fromUrl($value[$fieldName][$i]['value']);
                        $value[$fieldName][$i]['attachment_id'] = $attachment->getId();
                    }
                }
            }
        }

        // sort $value
        if(is_array($value)){
            foreach (array_keys($value) as $key){
                ksort($value[$key]);
                $value[$key] = Arrays::reindex($value[$key]);

                foreach ($value[$key] as $index => $nestedField){
                    if(is_array($value[$key][$index]['value'])){
                        ksort($value[$key][$index]['value']);
                        $value[$key][$index]['value'] = Arrays::reindex($value[$key][$index]['value']);
                    }
                }
            }
        }
    }

    /**
     * @param FormFieldModel $fieldModel
     * @param $data
     * @param $files
     * @param null $value
     * @param array $extra
     */
	private function handleUploadFile(FormFieldModel $fieldModel, $data, $files, &$value = null, &$extra = [])
    {
        $rawFile = $files[$fieldModel->getName()];
        $rawData = $data[$fieldModel->getName()];

        // Check image slider field (only 2 images are allowed)
        if($fieldModel->getMetaField() !== null and $fieldModel->getMetaField()->getType() === MetaFieldModel::IMAGE_SLIDER_TYPE){
            if(Arrays::count($rawFile['tmp_name']) > 2){
                $this->addError($fieldModel->getId(), "A maximum of 2 files can be uploaded");
                return;
            }
        }

        // This means that the user didn't send any file and/or some gallery items were deleted
        if($this->filesAreEmpty($rawFile)){

            if(is_array($rawData)){
                $newValues = explode(",",$rawData[0]);
                $value = ($newValues === [0 => ""]) ? [] : explode(",",$rawData[0]);
            } else {
                $value = $rawData;
            }
        }

        // Uploaded file from the user
        elseif(!empty($rawFile) and !empty($rawFile['tmp_name'])){

            // gallery/playlist fields
            if(is_array($rawFile['tmp_name'])){

                $urls = [];
                $ids = [];

                foreach ($rawFile['tmp_name'] as $index => $tmpFile){
                    if(!empty($tmpFile)){
                        $this->checkFileSize($rawFile['size'][$index], $fieldModel);
                        $this->checkImageDimension($tmpFile, $fieldModel);
                        $this->checkImageMimeType($tmpFile, $fieldModel);
                        $file = Files::uploadFile($tmpFile, $rawFile['name'][$index]);

                        if($file === false){
                            $this->addError($fieldModel->getId(), "File upload failed");
                        }

                        $urls[] = $file['url'];
                        $ids[] = $file['attachmentId'];
                    }
                }

                $value = $urls;
                $extra['attachment_id'] = implode(",", $ids);

            } else {
                if(!empty($rawFile['tmp_name'])){

                    $this->checkFileSize($rawFile['size'], $fieldModel);
                    $this->checkImageDimension($rawFile['tmp_name'], $fieldModel);
                    $this->checkImageMimeType($rawFile['tmp_name'], $fieldModel);
                    $file = Files::uploadFile($rawFile['tmp_name'], $rawFile['name']);

                    if($file === false){
                        $this->addError($fieldModel->getId(), "File upload failed");
                    }

                    $value = $file['url'];
                    $extra['attachment_id'] = $file['attachmentId'];
                }
            }
        }
    }

    /**
     * @param $rawFile
     * @return bool
     */
    private function filesAreEmpty($rawFile)
    {
        if(is_array($rawFile['tmp_name'])){
            return empty($rawFile['tmp_name'][0]);
        }

        return empty($rawFile['tmp_name']);
    }


    private function checkImageMimeType($tmpFile, FormFieldModel $fieldModel)
    {
        if($fieldModel->getMetaField() === null){
            return;
        }

        try {
            $fieldModel->getMetaField()->validateFileMimeType($tmpFile);
        } catch (\Exception $exception){
            $this->addError($fieldModel->getId(), $exception->getMessage());
        }
    }

    private function checkImageDimension($tmpFile, FormFieldModel $fieldModel)
    {
        if($fieldModel->getMetaField() === null){
            return;
        }

        try {
            $fieldModel->getMetaField()->validateImageDimension($tmpFile);
        } catch (\Exception $exception){
            $this->addError($fieldModel->getId(), $exception->getMessage());
        }
    }

	/**
	 * @param $size
	 * @param FormFieldModel $fieldModel
	 */
	private function checkFileSize($size, FormFieldModel $fieldModel)
	{
        // validate against validation rules
		foreach ($fieldModel->getValidationRules() as $rule){

			switch ($rule->getCondition()){
				case ValidationRuleModel::MAX_SIZE:
					if($size > Strings::convertToBytes($rule->getValue())){
						$this->addError($fieldModel->getId(), str_replace("{{v}}", $rule->getValue(), $rule->getMessage()));
					}
					break;

				case ValidationRuleModel::MIN_SIZE:
					if($size < Strings::convertToBytes($rule->getValue())){
						$this->addError($fieldModel->getId(), str_replace("{{v}}", $rule->getValue(), $rule->getMessage()));
					}
			}
		}

        if($fieldModel->getMetaField() === null){
            return;
        }

        try {
            $fieldModel->getMetaField()->validateFileSize($size);
        } catch (\Exception $exception){
            $this->addError($fieldModel->getId(), $exception->getMessage());
        }
	}

	/**
	 * @inheritDoc
	 * @throws \Exception
	 */
	public function execute()
	{
		if($this->isValid()){
			$this->checkCaptcha();
		}

		unset($this->data['g-recaptcha-response']);
		unset($this->data['cf-turnstile-response']);

		if($this->isValid()){
			$this->validate();
		}

		if($this->isValid()){
            $this->doAction();
            $this->sendMail();
			$this->saveFormSubmission();
		}

		return [
			'success' => $this->isValid(),
			'errors' => $this->errors
		];
	}

	/**
	 * @return bool
	 */
	private function isValid()
	{
		return empty($this->errors);
	}

	/**
	 * Verify nonce value
	 *
	 * @param $nonce
	 */
	private function checkTheNonce($nonce)
	{
		if(!Nonce::verify($nonce)){
            $this->addError($this->formModel->getId(), 'Nonce not valid');
		}
	}

	/**
	 * @see https://suleymanozcan.medium.com/how-to-use-cloudflare-turnstile-with-vanilla-php-e409e5addb14
	 * @see https://codeforgeek.com/google-recaptcha-tutorial/
	 * @throws \Exception
	 */
	private function checkCaptcha()
	{
		foreach ($this->formModel->getFields() as $fieldModel){

			switch ($fieldModel->getType()){

				// Google ReCaptcha
				case FormFieldModel::CAPTCHA_TYPE:

					$googleRecaptchaSiteKey = (isset($fieldModel->getExtra()['googleSiteKey']) and !empty($fieldModel->getExtra()['googleSiteKey'])) ? $fieldModel->getExtra()['googleSiteKey'] :  (Settings::get(SettingsModel::GOOGLE_RECAPTCHA_SITE_KEY));
					$googleRecaptchaSecretKey = (isset($fieldModel->getExtra()['googleSecretKey']) and !empty($fieldModel->getExtra()['googleSecretKey'])) ? $fieldModel->getExtra()['googleSecretKey'] : (Settings::get(SettingsModel::GOOGLE_RECAPTCHA_SECRET_KEY));

					if(empty($googleRecaptchaSiteKey) or empty($googleRecaptchaSecretKey)){
						$this->addError($fieldModel->getId(), 'ReCaptcha key and secret are not set');

						return;
					}

					if(!isset($this->data['g-recaptcha-response'])){
						$this->addError($fieldModel->getId(), 'ReCaptcha response not found');

						return;
					}

					$secretKey = $googleRecaptchaSecretKey;
					$captcha = $this->data['g-recaptcha-response']['value'];

					$url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secretKey) .  '&response=' . urlencode($captcha);
					$response = file_get_contents($url);
					$responseKeys = json_decode($response,true);

					if(!$responseKeys["success"]){
						$this->addError($fieldModel->getId(), 'ReCaptcha validation failed');
					}

					return;

				// Cloudflare turnstile
				case FormFieldModel::TURNSTILE_TYPE:

					$cloudflareTurnstileSiteKey = (isset($fieldModel->getExtra()['turnstileSiteKey']) and !empty($fieldModel->getExtra()['turnstileSiteKey'])) ? $fieldModel->getExtra()['turnstileSiteKey'] :  (Settings::get(SettingsModel::CLOUDFLARE_TURNSTILE_SITE_KEY));
					$cloudflareTurnstileSecretKey = (isset($fieldModel->getExtra()['turnstileSecretKey']) and !empty($fieldModel->getExtra()['turnstileSecretKey'])) ? $fieldModel->getExtra()['turnstileSecretKey'] : (Settings::get(SettingsModel::CLOUDFLARE_TURNSTILE_SECRET_KEY));

					if(empty($cloudflareTurnstileSiteKey) or empty($cloudflareTurnstileSecretKey)){
						$this->addError($fieldModel->getId(), 'Cloudflare Turnstile key and secret are not set');

						return;
					}

					if(!isset($this->data['cf-turnstile-response'])){
						$this->addError($fieldModel->getId(), 'Cloudflare Turnstile response not found');

						return;
					}

					$secretKey         = $cloudflareTurnstileSecretKey;
					$turnstileResponse = $this->data['cf-turnstile-response']['value'];
					$url               = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
					$post_fields       = "secret=$secretKey&response=$turnstileResponse";

					$ch = curl_init($url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
					$response = curl_exec($ch);
					curl_close($ch);

					$responseData = json_decode($response);

					if ($responseData->success != 1) {
						$this->addError($fieldModel->getId(), 'Cloudflare Turnstile validation failed');
					}

					return;
			}
		}
	}

    /**
     * BE validation
     * @throws \Exception
     */
	private function validate()
	{
		foreach ($this->formModel->getFields() as $fieldModel){
            try {
                $value = array_filter($this->data, function ($item) use($fieldModel){
                    return $fieldModel->getName() === $item['name'];
                });

                if(empty($value)){
                    return;
                }

                $value = array_values($value)[0]['value'];

                if(
                    $fieldModel->isRequired() and
                    $fieldModel->isTextualField() and
                    empty($value)
                ){
                    throw new \Exception("The field is required");
                }

                if(!empty($fieldModel->getValidationRules())){
                    if($fieldModel->isTextualField()){
                        $validationRulesChecker = new ValidationRulesChecker($value, $fieldModel->getValidationRules());
                        $validationRulesChecker->validate();

                        if(!$validationRulesChecker->isValid()){

                            $errors = [];

                            foreach ($validationRulesChecker->getErrors() as $error){
                                $errors[] = $error;
                            }

                            foreach ($errors as $error){
                                $this->addError($fieldModel->getId(), $error) ;
                            }

                            return;
                        }
                    }
                }

			    $fieldModel->validateAgainstMaxAndMin($value);
            } catch (\Exception $exception){
                do_action("acpt/error", $exception);
                $this->addError($fieldModel->getId(), $exception->getMessage());

                return;
            }
		}

        $this->checkSubmissionLimit();
	}

    /**
     * @throws \Exception
     */
	private function checkSubmissionLimit()
    {
        $submissionLimits = ( $this->formModel->getMetaDatum('submission_limits') !== null) ? $this->formModel->getMetaDatum('submission_limits')->getFormattedValue() : [];

        if(empty($submissionLimits)){
            return false;
        }

        foreach ($submissionLimits as $submissionLimit){
            $submissionLimitObject = FormSubmissionLimitObject::fromArray($submissionLimit);

            if($submissionLimitObject->hasRuleMatched()){
                if($this->formModel->getSubmissionsCount($submissionLimitObject) >= $submissionLimitObject->getValue()){
                    $this->addError($this->formModel->getId(), 'Submission limit reached');

                    return false;
                }
            }
        }

        return true;
    }

	/**
	 * Executes the form action
	 */
	private function doAction()
	{
		switch ($this->formModel->getAction()){
			case FormAction::AJAX:
				// do nothing here
				break;

			case FormAction::PHP:
				$action = $this->formModel->getMetaDatum('php_action');

				if($action !== null){
					PhpEval::getInstance()->evaluate($action->getValue());
				}

				break;

			case FormAction::FILL_META:
				$this->handleFillMetaAction();
				break;
		}
	}

	/**
	 * Handle fill meta action
	 */
	private function handleFillMetaAction()
	{
		$locationFind = ( $this->formModel->getMetaDatum('fill_meta_location_find') !== null) ? $this->formModel->getMetaDatum('fill_meta_location_find')->getValue() : null;
		$locationBelong = ( $this->formModel->getMetaDatum('fill_meta_location_belong') !== null) ? $this->formModel->getMetaDatum('fill_meta_location_belong')->getValue() : null;
		$locationItem = ( $this->formModel->getMetaDatum('fill_meta_location_item') !== null) ? $this->formModel->getMetaDatum('fill_meta_location_item')->getValue() : null;
		$locationStatus = ( $this->formModel->getMetaDatum('fill_meta_location_status') !== null) ? $this->formModel->getMetaDatum('fill_meta_location_status')->getValue() : null;

		if($this->postId !== null){
			$locationItem = (int)$this->postId;
		} elseif($this->termId !== null){
			$locationItem = (int)$this->termId;
		} elseif($this->userId !== null){
			$locationItem = (int)$this->userId;
		}

		// update is not always allowed
		if($locationItem > 0){

			if($locationBelong === MetaTypes::CUSTOM_POST_TYPE){

				if(empty(get_post($locationItem))){
					$this->addError($this->formModel->getId(), 'Page object is empty');

					return;
				}

				$postType = get_post_type($locationItem);

				if($locationFind !== $postType){
					$this->addError($this->formModel->getId(), 'This page does not belongs to ' . $locationFind);

					return;
				}
			}

			if($locationBelong === MetaTypes::TAXONOMY){
				if(!get_term($locationItem) instanceof \WP_Term){
					$this->addError($this->formModel->getId(), 'Term object is empty');

					return;
				}
			}
		}

		$postThumbnailId     = null;
        $postDataArray       = [];
        $termDataArray       = [];
        $userDataArray       = [];

		if($locationBelong === MetaTypes::CUSTOM_POST_TYPE){
			$locationItem = ($locationItem !== null and $locationItem !== '') ? (int)$locationItem : get_post()->ID;
            $postDataArray['ID'] = $locationItem;
		}

		if($locationBelong === MetaTypes::TAXONOMY){
			$locationItem = ($locationItem !== null and $locationItem !== '') ? (int)$locationItem : get_queried_object()->term_id;
            $termDataArray['ID'] = $locationItem;
		}

		if($locationBelong === MetaTypes::USER){
			$locationItem = ($locationItem !== null and $locationItem !== '') ? (int)$locationItem : wp_get_current_user()->ID;
            $userDataArray['ID'] = $locationItem;
		}

		foreach ($this->data as $datum){
			if($datum['isPost']){
				$this->addPostData($postDataArray, $datum);

				// thumbnail ID
				if($datum['type'] === FormFieldModel::WORDPRESS_POST_THUMBNAIL and isset($datum['extra']['attachment_id'])){
					$postThumbnailId = $datum['extra']['attachment_id'];
				}
			}

			if($datum['isTerm']){
				$termDataArray = array_merge($termDataArray, $this->addTermData($datum));
			}

			if($datum['isUser']){
				$userDataArray = array_merge($userDataArray, $this->addUserData($datum));
			}
		}

		// save posts
		if(!empty($postDataArray) and $locationItem !== null and $locationFind !== null){
			$postDataArray['post_type'] = $locationFind;

			if(!empty($locationStatus)){
                $postDataArray['post_status'] = $locationStatus;
            }

			$locationItem = $this->savePost($postDataArray);

			if($locationItem == 0 or $locationItem instanceof \WP_Error){
                $this->addError($this->formModel->getId(), "There was an error during the saving of " . $locationFind . " post");
            }

			// save thumbnail
			if($postThumbnailId !== null and is_numeric($locationItem) and $locationItem != 0){
				set_post_thumbnail( (int)$locationItem, (int)$postThumbnailId );
			}
		}

		// save terms
		if(!empty($termDataArray) and $locationItem !== null and $locationFind !== null){
			if(isset($termDataArray['name'])){
				$locationItem = $this->saveTerm($locationFind, $termDataArray);

                if($locationItem == 0 or $locationItem instanceof \WP_Error){
                    $this->addError($this->formModel->getId(), "There was an error during the saving of " . $locationFind . " term");
                }
			}
		}

		// save users
		if(!empty($userDataArray) and $locationItem !== null){
			$locationItem = $this->saveUser($userDataArray);

            if($locationItem == 0 or $locationItem instanceof \WP_Error){
                $this->addError($this->formModel->getId(), "There was an error during the saving of the user");
            }
		}

		foreach ($this->data as $datum){
			if($datum['isMeta']){
				$this->saveMetaDatum($datum, $locationFind, $locationBelong, $locationItem);
			}
		}
	}

	/**
	 * @param array $datum
	 *
	 * @return array
	 */
	private function addPostData(&$postDataArray, $datum = [])
	{
		if(!empty($datum['value'])){
			switch ($datum['type']){
				case FormFieldModel::WORDPRESS_POST_TITLE:
                    $postDataArray['post_title'] = $datum['value'];
                    break;

				case FormFieldModel::WORDPRESS_POST_CONTENT:
					$postDataArray['post_content'] = $datum['value'];
                    break;

				case FormFieldModel::WORDPRESS_POST_EXCERPT:
					$postDataArray['post_excerpt'] = $datum['value'];
                    break;

				case FormFieldModel::WORDPRESS_POST_AUTHOR:
					$postDataArray['post_author'] = $datum['value'];
                    break;

				case FormFieldModel::WORDPRESS_POST_DATE:
					$postDataArray['post_date'] = $datum['value'];
                    break;

				case FormFieldModel::WORDPRESS_POST_TAXONOMIES:

					// handle saving single or multiple terms
					if(is_array($datum['value'])){

						$taxInputValue = [];

						foreach ($datum['value'] as $item){
							$value = explode(TaxonomyField::SEPARATOR, $item);

							if(Arrays::count($value) === 2){
								$taxonomy = (string)$value[0];
								$termId = (int)$value[1];

								if(!isset($taxInputValue[$taxonomy])){
									$taxInputValue[$taxonomy] = [];
								}

                                $postDataArray['tax_input'][$taxonomy][] = $termId;
							}
						}

					} else {
						$value = explode(TaxonomyField::SEPARATOR, $datum['value']);

						if(Arrays::count($value) === 2){
							$taxonomy = (string)$value[0];
							$termId = (int)$value[1];

                            $postDataArray['tax_input'][$taxonomy][] = $termId;
						}
					}

                    break;
			}
		} else {

            // reset all taxonomies
            if($datum['type'] === FormFieldModel::WORDPRESS_POST_TAXONOMIES){

                $data = [];
                $postType = get_post_type($this->postId);
                $taxonomies = get_object_taxonomies($postType);

                foreach ($taxonomies as $taxonomy){
                    $postDataArray['tax_input'][$taxonomy] = [];
                }
            }
        }
	}

	/**
	 * @param array $datum
	 *
	 * @return array
	 */
	private function addTermData($datum = [])
	{
		if(!empty($datum['value'])){
			switch ($datum['type']){
				case FormFieldModel::WORDPRESS_TERM_NAME:
					return [
						'name' => $datum['value'],
					];

				case FormFieldModel::WORDPRESS_TERM_DESCRIPTION:
					return [
						'description' => $datum['value'],
					];

				case FormFieldModel::WORDPRESS_TERM_SLUG:
					return [
						'slug' => $datum['value'],
					];
			}
		}

		return [];
	}

	/**
	 * @param array $datum
	 *
	 * @return array
	 */
	private function addUserData($datum = [])
	{
		if(!empty($datum['value'])){
			switch ($datum['type']){
				case FormFieldModel::WORDPRESS_USER_EMAIL:
					return [
						'user_email' => $datum['value'],
					];

				case FormFieldModel::WORDPRESS_USER_PASSWORD:
					return [
						'user_pass' => $datum['value'],
					];

				case FormFieldModel::WORDPRESS_USER_USERNAME:
					return [
						'user_login' => $datum['value'],
					];

				case FormFieldModel::WORDPRESS_USER_FIRST_NAME:
					return [
						'first_name' => $datum['value'],
					];

				case FormFieldModel::WORDPRESS_USER_LAST_NAME:
					return [
						'last_name' => $datum['value'],
					];

				case FormFieldModel::WORDPRESS_USER_BIO:
					return [
						'description' => $datum['value'],
					];
			}
		}
	}

	/**
	 * @param array $data
	 *
	 * @return int|\WP_Error
	 */
	private function savePost($data = [])
	{
		if($data['ID'] > 0){
			return wp_update_post($data);
		}

		// if no title is provided, create one
		if(!isset($data['post_title'])){
            $data['post_title'] = 'No title';
        }

		return wp_insert_post($data);
	}

	/**
	 * @param array $data
	 *
	 * @return int|\WP_Error
	 */
	private function saveUser($data = [])
	{
		if($data['ID'] > 0){
			return wp_update_user($data);
		}

		if(!isset($data['user_login'])){
			if(isset($data['first_name'])){
				$data['user_login'] = Strings::generateUsername($data['first_name']);
			} else {
				$data['user_login'] = Strings::randomString();
			}
		}

		return wp_insert_user($data);
	}

	/**
	 * @param $taxonomy
	 * @param array $data
	 *
	 * @return mixed
	 */
	private function saveTerm($taxonomy, $data = [])
	{
		$array = [];

		if(isset($data['description'])){
			$array['description'] = $data['description'];
		}

		if(isset($data['slug'])){
			$array['slug'] = $data['slug'];
		}

		if($data['ID'] > 0){

			if(isset($data['name'])){
				$array['name'] = $data['name'];
			}

			$term = wp_update_term( $data['ID'], $taxonomy, $array);

			return $term['term_id'];
		}

		$term = wp_insert_term(
			$data['name'],
			$taxonomy,
			$array
		);

		return $term['term_id'];
	}

	/**
	 * @param array $datum
	 * @param null $locationFind
	 * @param null $locationBelong
	 * @param null $locationItem
	 */
	private function saveMetaDatum($datum = [], $locationFind = null, $locationBelong = null, $locationItem = null)
	{
        $rawValue = $datum['value'] ?? null;
        $allowHtml = $datum['allowHtml'] ?? false;
        $allowDangerousContent = $datum['allowDangerousContent'] ?? false;
		$elementId = ($locationBelong === MetaTypes::OPTION_PAGE) ? $datum['name'] : $locationItem;

		// check if the value is different than the saved one
		$savedValue = Meta::fetch($elementId, $locationBelong, $datum['name']);
		$newValue = Sanitizer::sanitizeRawData($datum['type'], $rawValue, $allowHtml, $allowDangerousContent);

		// Repeater
        if($datum['type'] === MetaFieldModel::REPEATER_TYPE){
            foreach ($newValue as $key => $value){
                $newValue[$key] = Arrays::reindex($value);
            }
        }

        // Password
        if($datum['type'] === MetaFieldModel::PASSWORD_TYPE and isset($datum['extra']['id'])){
            $fieldId = $datum['extra']['id'];

            try {
                $fieldModel = MetaRepository::getMetaFieldById($fieldId);
                $algo = $fieldModel->getAdvancedOption("algorithm") ?? PASSWORD_DEFAULT;

                switch ($algo){
                    default:
                    case "PASSWORD_DEFAULT":
                        $newValue = password_hash($newValue, PASSWORD_DEFAULT);
                        break;
                    case "PASSWORD_BCRYPT":
                        $newValue = password_hash($newValue, PASSWORD_BCRYPT);
                        break;
                    case "PASSWORD_ARGON2I":
                        $newValue = password_hash($newValue, PASSWORD_ARGON2I);
                        break;
                    case "PASSWORD_ARGON2ID":
                        $newValue = password_hash($newValue, PASSWORD_ARGON2ID);
                        break;
                }
            } catch (\Exception $exception){
                do_action("acpt/error", $exception);
            }
        }

        // Relational
        if($datum['type'] === MetaFieldModel::POST_TYPE and isset($datum['extra']['id'])){
            $fieldId = $datum['extra']['id'];

            try {
                $fieldModel = MetaRepository::getMetaFieldById($fieldId);
                $command = new HandleRelationsCommand($fieldModel, $rawValue, $elementId, $locationBelong);
                $command->execute();
            } catch (\Exception $exception){
                do_action("acpt/error", $exception);
            }

            return;
        }

        // is a button or another field to ignore
        if(empty($savedValue) and empty($newValue) and empty($rawValue)){
            return;
        }

        // save the new value only if is different from saved value
        if($savedValue !== $newValue){
            if(
                Meta::save(
                    $elementId,
                    $locationBelong,
                    $datum['name'],
                    $newValue
                ) === false
            ){
                $this->addError($datum['id'], "Error saving field " . $datum['name']);
            }
        }

		foreach ($datum['extra'] as $key => $value){
			Meta::save(
				$elementId,
				$locationBelong,
				$datum['name'].'_'.$key,
				Sanitizer::sanitizeRawData(MetaFieldModel::TEXT_TYPE, $value )
			);
		}
	}

	/**
	 * Save form submission
	 */
	private function saveFormSubmission()
	{
		$currentUser = wp_get_current_user();

		try {
			$formSubmission = FormSubmissionModel::hydrateFromArray([
				'formId' => $this->formModel->getId(),
				'action' => $this->formModel->getAction(),
				'callback' => $this->saveFormSubmissionCallback(),
				'uid' => ($currentUser->ID > 0 ? $currentUser->ID : null),
                'createdAt' => new \DateTime(),
			]);

			foreach ($this->data as $datum){
				if(
					isset($datum['name']) and
					isset($datum['type']) and
					isset($datum['value'])
				){
					$datumObject = new FormSubmissionDatumObject($datum['name'], $datum['type'], $datum['value']);
					$formSubmission->addDatum($datumObject);
				}
			}

			if(!empty($this->errors)){
				foreach($this->errors as $id => $err){
					if(is_array($err)){
						foreach ($err as $key => $errorMessage){
							if(is_string($errorMessage)){
								$error = new FormSubmissionErrorObject($id, $errorMessage);
								$formSubmission->addError($error);
							}
						}
					} elseif(is_string($err)) {
						$error = new FormSubmissionErrorObject($id, $err);
						$formSubmission->addError($error);
					}
				}
			}

			FormRepository::saveSubmission($formSubmission);
		} catch (\Exception $exception){
            do_action("acpt/error", $exception);
			$this->addError($this->formModel->getId(), "Save form submission error: " . $exception->getMessage());
		}
	}

	/**
	 * @return string
	 */
	private function saveFormSubmissionCallback()
	{
		switch ($this->formModel->getAction()){
			case FormAction::AJAX:
				if($this->formModel->getMetaDatum('ajax_action') === null){
					return null;
				}

				return $this->formModel->getMetaDatum('ajax_action')->getValue();

			case FormAction::CUSTOM:
				if($this->formModel->getMetaDatum('custom_action') === null){
					return null;
				}

				return $this->formModel->getMetaDatum('custom_action')->getValue();

			case FormAction::PHP:
				if($this->formModel->getMetaDatum('php_action') === null){
					return null;
				}

				return $this->formModel->getMetaDatum('php_action')->getValue();

			case FormAction::FILL_META:

				if(!empty($this->postId)){
					return 'Updating Post with ID#'.$this->postId;
				}

				if(!empty($this->termId)){
					return 'Updating Term with ID#'.$this->termId;
				}

				if(!empty($this->userId)){
					return 'Updating User with ID#'.$this->userId;
				}

				$locationFind = ( $this->formModel->getMetaDatum('fill_meta_location_find') !== null) ? $this->formModel->getMetaDatum('fill_meta_location_find')->getValue() : null; // ---> holiday
				$locationBelong = ( $this->formModel->getMetaDatum('fill_meta_location_belong') !== null) ? $this->formModel->getMetaDatum('fill_meta_location_belong')->getValue() : null; // ---> customPostType

				if($locationBelong === MetaTypes::CUSTOM_POST_TYPE){
					return 'Creating new '.$locationFind.' post';
				}

				if($locationBelong === MetaTypes::TAXONOMY){
					return 'Creating new '.$locationFind.' term';
				}

				if($locationBelong === MetaTypes::USER){
					return 'Creating new User';
				}

				return '';
		}

		return '';
	}

    private function sendMail()
    {
        $emailSettings = $this->formModel->getMetaDatum('email_settings');
        $emailSettingsValues = $emailSettings !== null ? $emailSettings->getFormattedValue() : null;

        if(empty($emailSettingsValues)){
           return;
        }

        $from = $emailSettingsValues['from'] ?? null;
        $to = $emailSettingsValues['to'] ?? [];
        $cc = $emailSettingsValues['cc'] ?? [];
        $bcc = $emailSettingsValues['bcc'] ?? [];
        $replyTo = $emailSettingsValues['reply_to'] ?? null;
        $subject = $emailSettingsValues['subject'] ?? null;
        $body = $emailSettingsValues['body'] ?? "";
        $header = $emailSettingsValues['header'] ?? null;
        $footer = $emailSettingsValues['footer'] ?? null;
        $attachments = $emailSettingsValues['attachments'] ?? [];
        $template = EmailTemplateFactory::create($emailSettingsValues['template'] ?? null);

        if(empty($from)){
            return;
        }

        if(empty($to)){
            return;
        }

        if(empty($subject)){
            return;
        }

        $attributes = [];

        foreach ($this->data as $value){
            $attributes[$value['key']] = $value['value'];
        }

        $body = Twig::render($body, $attributes);
        $header = Twig::render($header, $attributes);
        $footer = Twig::render($footer, $attributes);

        $attachmentPaths = [];

        foreach ($attachments as $attachment){
            $object = array_filter($this->data, function($value) use ($attachment){
                return $value['key'] === $attachment;
            });

            if(!empty($object)){
                $object = Arrays::reindex($object);
                $attachmentIds = explode(",", $object[0]['extra']['attachment_id']) ?? [];

                if(!empty($attachmentIds)){
                    foreach ($attachmentIds as $attachmentId){
                        $attachment = WPAttachment::fromId((int)$attachmentId);

                        if(!$attachment->isEmpty()){
                            $attachmentPaths[] = $attachment->getPath();
                        }
                    }
                }
            }
        }

        $emailBody = new EnvelopeBody();
        $emailBody->body = $body;
        $emailBody->header = $header;
        $emailBody->footer = $footer;

        $email = new Envelope($from, $to, $subject, $emailBody);
        $email->setAttachments($attachmentPaths);
        $email->setBcc($bcc);
        $email->setCc($cc);
        $email->setReplyTo($replyTo);
        $email->setTemplate($template);
        $emailSent = $email->send();

        if(!$emailSent){
            $this->addError($this->formModel->getId(), "Sending email to ".implode($to)." failed");
        }
    }

    /**
     * @inheritDoc
     */
    public function logFormat(): array
    {
        $data = [];

        foreach ($this->data as $datum){
            $data[$datum['name']] = $datum['value'];
        }

        return [
            "class"  => HandleFormSubmissionCommand::class,
            "data"   => $data,
            "errors" => $this->errors,
            "postId" => $this->postId,
            "userId" => $this->userId,
            "termId" => $this->termId,
        ];
    }
}