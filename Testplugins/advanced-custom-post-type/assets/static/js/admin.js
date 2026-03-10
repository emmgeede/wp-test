import {waitForElement, fetchLanguages} from "./_admin_helpers.js";
import {handleRelationalFieldsEvents, initRelationalFields} from "./_admin_relational.js";
import {initSortable} from "./_admin_sortable.js";
import {handleRepeaterFieldsEvents} from "./_admin_repeater.js";
import {handleFlexibleFieldsEvents} from "./_admin_flexible.js";
import {handleListFieldsEvents} from "./_admin_list.js";
import {handleFileFieldsEvents} from "./_admin_file.js";
import {handleIconFieldsEvents} from "./_admin_iconpicker.js";
import {handleColorFieldsEvents, initColorPicker} from "./_admin_colorpicker.js";
import {handleDateFieldsEvents, initDateRangePicker} from "./_admin_datepicker.js";
import {runWooCommerceFixes} from "./_admin_woocommerce.js";
import {initEditorFields} from "./_admin_editor.js";
import {applyVisibilityFromLocalStorage, handleGroupedElementEvents, initBarcode, initCodeMirror, initCountrySelect, initEmbed, initIdGenerators, initImageSlider, initIntlTelInput, initQRCodeGenerator, initSelectize, initTextarea, runMiscFunctions} from "./_admin_misc.js";

var $ = jQuery.noConflict();

/**
 * Main admin JS
 */
jQuery(function ($) {

    /**
     * ===================================================================
     * FETCH LANGUAGES
     * ===================================================================
     */

    /**
     * Fetch translations
     */
    fetchLanguages()
        .then((response) => response.json())
        .then((translations) => {
            document.adminjs = {
                translations: translations
            };
            document.dispatchEvent(new Event("fetchLanguages"));
        })
        .catch((err) => {
            console.error("Something went wrong!", err);
        });

    /**
     * ===================================================================
     * RELATION SELECTOR SECTION
     * ===================================================================
     */

    handleRelationalFieldsEvents();

    /**
     * ===================================================================
     * REPEATER ELEMENTS HANDLING
     * ===================================================================
     */

    handleRepeaterFieldsEvents();

    /**
     * ===================================================================
     * FLEXIBLE ELEMENTS HANDLING
     * ===================================================================
     */

    handleFlexibleFieldsEvents();

    /**
     * ===================================================================
     * LIST ELEMENTS HANDLING
     * ===================================================================
     */

    handleListFieldsEvents();

    /**
     * ===================================================================
     * FILE FIELD HANDLING
     * ===================================================================
     */

    handleFileFieldsEvents();

    /**
     * ===================================================================
     * COLOR PICKER
     * ===================================================================
     */

    handleColorFieldsEvents();

    /**
     * ===================================================================
     * ICON PICKER
     * ===================================================================
     */

    handleIconFieldsEvents();

    /**
     * ===================================================================
     * DATE PICKER
     * ===================================================================
     */

    handleDateFieldsEvents();

    /**
     * ===================================================================
     * MISCELLANEA
     * ===================================================================
     */

    runMiscFunctions();
    runWooCommerceFixes();

    /**
     * ===================================================================
     * INIT
     * ===================================================================
     */

    /**
     * Init the dependencies
     *
     * @param settings
     */
    function init(settings = {}) {

        const selectize = settings && typeof settings.selectize === 'boolean' ? settings.selectize : true;
        const wrapper = settings && settings.wrapper ? settings.wrapper : null;

        initEditorFields(null, wrapper);
        initSelectize(null, wrapper, selectize);
        initCodeMirror(null, wrapper);
        initColorPicker(null, wrapper);
        initSortable();
        initDateRangePicker("daterange", null, wrapper);
        initDateRangePicker("date", null, wrapper);
        initDateRangePicker("datetime", null, wrapper);
        initDateRangePicker("time", null, wrapper);
        initIntlTelInput(null, wrapper);
        initCountrySelect(null, wrapper);
        initQRCodeGenerator(null, wrapper);
        initBarcode(null, wrapper);
        initEmbed(null, wrapper);
        initTextarea(null, wrapper);
        initImageSlider(null, wrapper);
        initRelationalFields(null, wrapper);
        initIdGenerators(null, wrapper);
        applyVisibilityFromLocalStorage();
        handleGroupedElementEvents();
    }

    init({});

    /**
     * This function waits to attachments-wrapper is populated and then initialize the application.
     *
     * The init() function is triggered ONLY on .compat-attachment-fields fields
     */
    function initTheAppWhenAttachmentsAreLoaded() {
        waitForElement(".attachments-wrapper > ul > li", function(){
            $(".attachments-wrapper > ul > li").on("click", function(){

                const postId = $(this).data("id");

                init({
                    wrapper: ".compat-attachment-fields",
                    postId: postId,
                    selectize: false
                });
            });
        });
    }

    // Init the dependencies on the Media Library page
    if(pagenow === "upload"){
        initTheAppWhenAttachmentsAreLoaded();
    }

    // Init the dependencies if load more button is pressed
    $(".attachments-wrapper button.load-more").on("click", function(){
        initTheAppWhenAttachmentsAreLoaded();
    });

    // Init the dependencies when open the Media upload popup
    if (wp.media) {
        wp.media.view.Modal.prototype.on('open', function(data) {
            initTheAppWhenAttachmentsAreLoaded();
        });
    }
});