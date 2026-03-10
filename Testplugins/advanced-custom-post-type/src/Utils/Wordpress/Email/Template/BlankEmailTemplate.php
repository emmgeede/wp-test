<?php

namespace ACPT\Utils\Wordpress\Email\Template;

class BlankEmailTemplate extends AbstractEmailTemplate
{
    public function getName(): string
    {
        return "blank";
    }

    /**
     * @return string
     */
    public function skeleton(): string
    {
        return '<!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="UTF-8">
              <title>{{title}}</title>
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body>
                {{header}}
                {{body}}
                {{footer}}
            </body>
            </html>';
    }
}
