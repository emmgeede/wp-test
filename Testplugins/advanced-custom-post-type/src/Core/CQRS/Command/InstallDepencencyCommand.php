<?php

namespace ACPT\Core\CQRS\Command;

use ACPT\Constants\FormatterFormat;
use ACPT\Core\Repository\ImportRepository;
use ACPT\Utils\Data\Formatter\Formatter;
use ACPT\Utils\Wordpress\Translator;

class InstallDepencencyCommand implements CommandInterface
{
    private $dependency;

    public function __construct($dependency)
    {
        $this->dependency = $dependency;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function execute()
    {
        $downloadUrl = null;
        $deleteFile = true;

        switch ($this->dependency) {
            case "predis":
                $downloadUrl = "https://github.com/predis/predis/archive/refs/tags/v2.4.0.zip";
                break;
        }

        if(empty($downloadUrl)){
            throw new \Exception("Unknown dependency and download url");
        }

        $zipFile = ACPT_PLUGIN_DIR_PATH . "/dependencies/" . $this->dependency . "/" . $this->dependency . ".zip";

        if(!is_dir(ACPT_PLUGIN_DIR_PATH . "/dependencies/")) {
            mkdir(ACPT_PLUGIN_DIR_PATH . "/dependencies/");

            if(!is_dir(ACPT_PLUGIN_DIR_PATH . "/dependencies/")) {
                throw new \Exception("Can't create directory " . ACPT_PLUGIN_DIR_PATH . "/dependencies/");
            }
        }

        if(!is_dir(ACPT_PLUGIN_DIR_PATH . "/dependencies/". $this->dependency . "/")) {
            mkdir(ACPT_PLUGIN_DIR_PATH . "/dependencies/". $this->dependency . "/");

            if(!is_dir(ACPT_PLUGIN_DIR_PATH . "/dependencies/". $this->dependency . "/")) {
                throw new \Exception("Can't create directory " . ACPT_PLUGIN_DIR_PATH . "/dependencies/". $this->dependency . "/");
            }
        }

        if(!is_file($zipFile)) {
            touch($zipFile);
        }

        $write = file_put_contents($zipFile, fopen($downloadUrl, 'r'));

        if(!$write){
            throw new \Exception("Failed to write to $downloadUrl");
        }

        $path = pathinfo(realpath($zipFile), PATHINFO_DIRNAME);

        $zip = new \ZipArchive;
        $res = $zip->open($zipFile);

        if ($res === true) {
            $zip->extractTo($path);
            $zip->close();

            if ($deleteFile) {
                unlink($zipFile);
            }

            return true;
        }

        throw new \Exception("Failed to extract zip file");
    }
}