export class WpImagePicker {

    /**
     * Constructs an instance with customizable options for selecting and uploading media files.
     *
     * @param {Object} options - An optional configuration object.
     * @param {string} [options.button='Add image'] - The text for the selection button.
     * @param {string} [options.title='Select image'] - The title of the media selection window.
     * @param {Array<string>} [options.mimeTypes=['image']] - The allowed MIME types, e.g., 'image', 'audio', 'image/jpg'.
     * @param {number|null} [options.minSizeMB=null] - The minimum file size in megabytes.
     * @param {number|null} [options.maxSizeMB=null] - The maximum file size in megabytes.
     * @param {number|null} [options.minWidth=null] - The minimum width of the media file in pixels.
     * @param {number|null} [options.maxWidth=null] - The maximum width of the media file in pixels.
     * @param {number|null} [options.minHeight=null] - The minimum height of the media file in pixels.
     * @param {number|null} [options.maxHeight=null] - The maximum height of the media file in pixels.
     * @param {boolean} [options.multiple=false] - Whether multiple file selection is allowed.
     * @param {Function} [options.onOpen=() => {}] - A callback invoked when the media selection window is opened.
     * @param {Function} [options.onSelect=() => {}] - A callback invoked when media is successfully selected.
     * @param {Function} [options.onError=(msg) => alert(msg)] - A callback invoked when an error occurs. Receives an error message as its argument.
     * @return {void}
     */
    constructor(options = {}) {
        this.options = {
            button: 'Add image',
            title: 'Select image',
            mimeTypes: ['image'], // 'image', 'audio', 'image/jpg' etc...
            minSizeMB: null,
            maxSizeMB: null,
            minWidth: null,
            maxWidth: null,
            minHeight: null,
            maxHeight: null,
            multiple: false,
            onOpen: () => {},
            onSelect: () => {},
            onError: (msg) => alert(msg),
            ...options
        };

        const buttonLabel = () => {
            let label = this.options.button;

            if(this.options.minSizeMB || this.options.maxSizeMB){
                label += " (";
            }

            if(this.options.minSizeMB){
                label += `min ${this.options.minSizeMB}MB`;

                if(this.options.maxSizeMB){
                    label += " / ";
                }
            }

            if(this.options.maxSizeMB){
                label += `max ${this.options.maxSizeMB}MB`;
            }

            if(this.options.minSizeMB || this.options.maxSizeMB){
                label += ")";
            }

            return label;
        };

        this.frame = wp.media({
            title: this.options.title,
            library: {
                type: this.options.mimeTypes
            },
            multiple: this.options.multiple,
            button: {
                text: buttonLabel()
            },
        });

        this.frame.on('select', () => this.handleSelect());
    }

    /**
     * Handles the selection of media items from the WordPress media library.
     * This method supports both single and multiple selections, based on the specified options.
     * Validates the selected media items and triggers appropriate callbacks for selection or errors.
     *
     * @return {void} This method does not return a value. It executes the necessary logic for handling media selection and invokes callbacks defined in the options.
     */
    handleSelect() {

        if (!wp || !wp.media) {
            this.options.onError(
                `The media gallery is not available. You must admin_enqueue this function: wp_enqueue_media()`
            );
            return;
        }

        // Multiple selection
        if(this.options.multiple === true || this.options.multiple === 'add'){

            let attachments = [];

            this.frame.state().get( 'selection' ).map(
                function ( attachment ) {
                    attachments.push(attachment.toJSON());
                }
            );

            if(attachments.length === 0){
                return;
            }

            try {
                attachments.map(attachment => {
                    this.validate(attachment);
                });

                this.options.onSelect(attachments);
            } catch (e){
                this.options.onError(e.message);
            }

        // Single selection
        } else {
            const attachment = this.frame
                .state()
                .get('selection')
                .first()
                .toJSON();

            try {
                this.validate(attachment);
                this.options.onSelect(attachment);
            } catch (e){
                this.options.onError(e.message);
            }
        }
    }

    /**
     * Opens the current frame and initializes the selection state.
     * Registers an event listener for the 'open' event to trigger the onOpen callback.
     *
     * @return {void} This method does not return a value.
     */
    open()
    {
        this.frame.open();

        const selection = this.frame
            .state()
            .get('selection');

        this.frame.on('open', () => this.options.onOpen(selection));
    }

    /**
     * Validates the provided attachment against the specified size and dimension constraints.
     * Throws an error if any validation rule is violated.
     *
     * @param {Object} attachment The attachment object to validate.
     * @param {string} attachment.name The name of the attachment.
     * @param {number} attachment.filesizeInBytes The size of the attachment in bytes.
     * @param {number} [attachment.width] The width of the attachment (e.g., image) in pixels.
     * @param {number} [attachment.height] The height of the attachment (e.g., image) in pixels.
     * @return {void} No value is returned; an error is thrown if validation fails.
     */
    validate(attachment)
    {
        // minSizeMB
        if (this.options.minSizeMB) {
            const minBytes = this.options.minSizeMB * 1024 * 1024;
            if (!attachment.filesizeInBytes || attachment.filesizeInBytes < minBytes) {
                const message = `The file size of ${attachment.name} must be greater than ${this.options.minSizeMB}MB`;
                throw new Error(message);
            }
        }

        // maxSizeMB
        if (this.options.maxSizeMB) {
            const maxBytes = this.options.maxSizeMB * 1024 * 1024;
            if (!attachment.filesizeInBytes || attachment.filesizeInBytes > maxBytes) {
                const message = `The file size of ${attachment.name} must be less than ${this.options.maxSizeMB}MB`;
                throw new Error(message);
            }
        }

        // min width
        if (this.options.minWidth) {
            if (!attachment.width || attachment.width < this.options.minWidth) {
                const message = `The image width of ${attachment.name} must be greater than ${this.options.minWidth}px`;
                throw new Error(message);
            }
        }

        // max width
        if (this.options.maxWidth) {
            if (!attachment.width || attachment.width > this.options.maxWidth) {
                const message = `The image width of ${attachment.name} must be less than ${this.options.maxWidth}px`;
                throw new Error(message);
            }
        }

        // min height
        if (this.options.minHeight) {
            if (!attachment.height || attachment.height > this.options.minHeight) {
                const message = `The image height of ${attachment.name} must be greater than ${this.options.minHeight}px`;
                throw new Error(message);
            }
        }

        // max height
        if (this.options.maxHeight) {
            if (!attachment.height || attachment.height > this.options.maxHeight) {
                const message = `The image height of ${attachment.name} must be less than ${this.options.maxHeight}px`;
                throw new Error(message);
            }
        }
    }
}
