<?php

namespace ACPT\Utils\Wordpress\Email;

use ACPT\Utils\Wordpress\Email\Template\AbstractEmailTemplate;
use ACPT\Utils\Wordpress\Email\Template\BlankEmailTemplate;

class Envelope
{
    /**
     * @var string
     */
    private $from;

    /**
     * @var string|array
     */
    private $to;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var EnvelopeBody
     */
    private EnvelopeBody $body;

    /**
     * @var array
     */
    private array $cc = [];

    /**
     * @var array
     */
    private array $bcc = [];

    /**
     * @var null
     */
    private $replyTo = null;

    /**
     * @var array
     */
    private array $attachments = [];

    /**
     * @var array
     */
    private array $embeds = [];

    /**
     * @var array
     */
    private array $headers = [];

    /**
     * @var ?AbstractEmailTemplate
     */
    private ?AbstractEmailTemplate $template = null;

    /**
     * @param $from
     * @param $to
     * @param $subject
     * @param $body
     */
    public function __construct($from, $to, $subject, EnvelopeBody $body)
    {
        $this->from = $from;
        $this->setTo($to);
        $this->subject = $subject;
        $this->body = $body;
        $this->setTemplate(new BlankEmailTemplate());
    }

    /**
     * @param $to
     * @return void
     */
    private function setTo($to)
    {
        if(is_string($to)){
            $to = [$to];
        }

        if(!is_array($to)){
            throw new \InvalidArgumentException('To must be a string or an array');
        }

        $this->to = $to;
    }

    /**
     * @param array $cc
     * @return void
     */
    public function setCc($cc): void
    {
        if(empty($cc)){
            return;
        }

        if(is_string($cc)){
            $cc = [$cc];
        }

        if(!is_array($cc)){
            throw new \InvalidArgumentException('Cc must be a string or an array');
        }

        $this->cc = $cc;
    }

    /**
     * @param array $bcc
     * @return void
     */
    public function setBcc($bcc): void
    {
        if(empty($bcc)){
            return;
        }

        if(is_string($bcc)){
            $bcc = [$bcc];
        }

        if(!is_array($bcc)){
            throw new \InvalidArgumentException('Bcc must be a string or an array');
        }

        $this->bcc = $bcc;
    }

    /**
     * @param null $replyTo
     */
    public function setReplyTo($replyTo): void
    {
        $this->replyTo = $replyTo;
    }

    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function setEmbeds(array $embeds): void
    {
        $this->embeds = $embeds;
    }

    public function setHeader(string $header): void
    {
        $this->headers[] = $header;
    }

    /**
     * @param AbstractEmailTemplate $template
     * @return void
     */
    public function setTemplate(AbstractEmailTemplate $template): void
    {
        $this->template = $template;
    }

    /**
     * @param bool $html
     * @return bool
     */
    public function send(?bool $html = true): bool
    {
        if($html === true){
            $this->setHeader('Content-Type: text/html; charset=UTF-8');
        }

        $this->setHeader('From: '. $this->from);

        if($this->replyTo){
            $this->setHeader('Reply-To: '. $this->replyTo);
        }

        foreach($this->to as $email){
            $this->setHeader('To: '. $email);
        }

        foreach($this->cc as $email){
            $this->setHeader('Cc: '. $email);
        }

        foreach($this->bcc as $email){
            $this->setHeader('Bcc: '. $email);
        }

        $emailSent = wp_mail(
            $this->to,
            $this->subject,
            $this->template->render($this->subject, $this->body->body, $this->body->header, $this->body->footer),
            $this->headers,
            $this->attachments,
            $this->embeds
        );

        do_action("acpt/send_email", $emailSent, $this);

        return $emailSent;
    }

    public function toArray(): array
    {
        return [
            'headers' => $this->headers,
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'body' => $this->template->render($this->subject, $this->body->body, $this->body->header, $this->body->footer),
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'replyTo' => $this->replyTo,
            'template' => $this->template->getName(),
            'attachments' => $this->attachments,
            'embeds' => $this->embeds,
        ];
    }
}