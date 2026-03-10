<?php

namespace ACPT\Core\API\V1\Controllers;

use ACPT\Core\CQRS\Command\HandleFormSubmissionCommand;
use ACPT\Core\Helper\Strings;
use ACPT\Core\Models\Form\FormModel;
use ACPT\Core\Repository\FormRepository;

class FormSubmissionController extends AbstractController
{
    public function submit(\WP_REST_Request $request)
    {
        try {
            $key = $request['key'];
            $formModel = FormRepository::getByKey($key);

            if($formModel === null) {
                throw new \Exception('Form not found');
            }

            if(
                isset($_POST['acpt_form_submission']) and
                $_POST['acpt_form_submission'] == 1 and
                $_POST['acpt_form_id'] === $formModel->getId()
            ){
                // verify X_WP_Nonce
                $nonce = $request->get_header('X_WP_Nonce');

                if (!wp_verify_nonce($nonce, 'wp_rest')) {
                    return new \Exception('Invalid nonce', 403);
                }

                $postId = (!empty($_POST['acpt_form_post_id'])) ? filter_var($_POST['acpt_form_post_id'], FILTER_SANITIZE_NUMBER_INT) :  null;
                $termId = (!empty($_POST['acpt_form_term_id'])) ? filter_var($_POST['acpt_form_term_id'], FILTER_SANITIZE_NUMBER_INT) :  null;
                $userId = (!empty($_POST['acpt_form_user_id'])) ? filter_var($_POST['acpt_form_post_id'], FILTER_SANITIZE_NUMBER_INT) :  null;

                unset($_POST['acpt_form_submission']);
                unset($_POST['acpt_form_id']);
                unset($_POST['acpt_form_post_id']);
                unset($_POST['acpt_form_term_id']);
                unset($_POST['acpt_form_user_id']);
                unset($_POST['_wp_http_referer']);

                $command = new HandleFormSubmissionCommand($formModel, $postId, $termId, $userId, $_POST, $_FILES);
                $submission = $command->execute();

                if(!empty($submission['errors'])){
                    return $this->jsonResponse([
                        'outcome' => 'An error(s) occurred',
                        'errors' => $submission['errors'],
                        'redirectTo' => '',
                        'redirectTimeout' => null,
                    ], 400);
                }

                $outcomeMessage = ($formModel->getMetaDatum('outcome_message') !== null) ? $formModel->getMetaDatum('outcome_message')->getValue() : "The form was successfully submitted. Redirect in 4 seconds...";
                $redirectTimeout = ($formModel->getMetaDatum('redirect_timeout') !== null) ? $formModel->getMetaDatum('redirect_timeout')->getValue() : 4;

                return $this->jsonResponse([
                    'outcome' => $outcomeMessage,
                    'errors' => [],
                    'redirectTo' => $this->redirectUrl($formModel),
                    'redirectTimeout' => $redirectTimeout
                ]);
            }

            throw new \Exception('Form not submitted');

        } catch (\Exception $exception){

            $errCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;

            do_action("acpt/error", $exception);

            return $this->jsonResponse([
                'outcome' => 'ko',
                'errors' => [$exception->getMessage()],
                'redirectTo' => null,
                'redirectTimeout' => null,
            ], $errCode);
        }
    }

    /**
     * @param FormModel $formModel
     * @return string
     */
    private function redirectUrl(FormModel $formModel)
    {
        $savedRedirectTo = $formModel->getMetaDatum('redirect_to');
        $savedRedirectToType = $formModel->getMetaDatum('redirect_to_type');
        $savedRedirectToTypeValue = (!empty($savedRedirectToType)) ? $savedRedirectToType->getValue() : "url";
        $redirectTo = (!empty($savedRedirectTo)) ? $savedRedirectTo->getValue() : $_POST['_wp_http_referer'];

        if(is_serialized($redirectTo)){
            $redirectTo = unserialize($redirectTo);
        }

        if(is_array($redirectTo)){
            $redirectTo = $redirectTo[0];
        }

        // if redirectTo is empty, redirect to the referer page
        if(empty($redirectTo)){
            return $_SERVER['HTTP_REFERER'];
        }

        switch ($savedRedirectToTypeValue){
            default:
            case "url":
                $theURLContainsSiteUrl = Strings::contains(site_url(), $redirectTo);

                // In case of external redirect, like https://google.com,
                // flush the cache and then redirect
                if(!$theURLContainsSiteUrl){
                    return $redirectTo;
                }

                $redirectTo = str_replace(site_url(), "", $redirectTo);

                return esc_url(site_url($redirectTo));

            case "post":
                return get_the_permalink((int)$redirectTo);

            case "post_archive":
                return get_post_type_archive_link( $redirectTo );

            case "tax_archive":

                $term = get_term((int)$redirectTo);

                if($term instanceof \WP_Term){
                    return get_term_link($redirectTo, $term->taxonomy);
                }

                return "";
        }
    }
}
