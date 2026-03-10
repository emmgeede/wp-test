<?php

namespace ACPT\Includes;

use ACPT\Core\Models\Settings\SettingsModel;
use ACPT\Utils\Settings\Settings;

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://acpt.io
 * @since      1.0.0
 *
 * @package    advanced-custom-post-type
 * @subpackage advanced-custom-post-type/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    advanced-custom-post-type
 * @subpackage advanced-custom-post-type/includes
 * @author     Mauro Cassani <maurocassani1978@gmail.com>
 */
class ACPT_Internalization
{
    const FALLBACK_LANGUAGE = 'en_US';

	/**
	 * @return string
	 */
    private function getLocale()
    {
    	try {
            $syncLanguage = Settings::get(SettingsModel::LANGUAGE_SYNC, true);

            if($syncLanguage){
                return get_locale() ?? self::FALLBACK_LANGUAGE;
            }

		    return Settings::get(SettingsModel::LANGUAGE, self::FALLBACK_LANGUAGE);
	    } catch (\Exception $exception){
		    return self::FALLBACK_LANGUAGE;
	    }
    }

	/**
	 * Run localisation
	 */
    public function run()
    {
        $defaultLanguage = self::FALLBACK_LANGUAGE;
        $moFile = file_exists( $this->moFilePath($this->getLocale()) ) ? $this->moFilePath($this->getLocale()) : $this->moFilePath($defaultLanguage);

        // Needed to load menu pages translations
        load_textdomain( ACPT_PLUGIN_NAME, $moFile);

        add_action( 'plugins_loaded', function () use($moFile) {
		    unload_textdomain( ACPT_PLUGIN_NAME, false);
		    load_textdomain( ACPT_PLUGIN_NAME, $moFile);
	    } );
    }

    /**
     * Constructs the file path to the .mo file for a given language.
     *
     * @param string $lang The language code used to determine the .mo file path.
     * @return string The full path to the .mo file for the specified language.
     */
    private function moFilePath($lang)
    {
        return ACPT_PLUGIN_DIR_PATH . '/i18n/languages/'.$lang.'.mo';
    }
}