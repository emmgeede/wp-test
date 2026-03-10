<?php

namespace ACPT\Utils\Wordpress\Email\Template;

abstract class AbstractEmailTemplate
{
    /**
     * The template name
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * The template skeleton
     *
     * @return string
     */
    abstract public function skeleton(): string;

    /**
     * @param string $subject
     * @param string $body
     * @param ?string $header
     * @param ?string $footer
     * @return string
     */
    public function render($subject, $body, $header = null, $footer = null): string
    {
        return html_entity_decode(str_replace(
            [
                '{{header}}',
                '{{body}}',
                '{{title}}',
                '{{footer}}',
            ],[
                $header,
                $this->body($body),
                $subject,
                $footer,
            ],
            $this->skeleton()
        ));
    }

    /**
     * @param $body
     * @return string
     */
    private function body($body)
    {
        return do_shortcode(wpautop(html_entity_decode($body)));
    }
}