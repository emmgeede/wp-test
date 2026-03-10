import {useTranslation} from "./_admin_commons.js";
import {WpImagePicker} from "./_admin_media.js";

var $ = jQuery.noConflict();

export const handleFileFieldsEvents = () => {

    /**
     * File label button
     */
    $('body').on('click', '.edit-file-label', function(e) {
        const $this = $( this );
        e.preventDefault();
        const parentDiv = $this.parent("div");

        $this.prev("input").removeClass('hidden');
        $this.addClass('hidden');
        $this.next('.save-file-label').removeClass('hidden');
        parentDiv.find("a.acpt-text-truncate").addClass('hidden');
    });

    /**
     * Save file
     */
    $('body').on('click', '.save-file-label', function(e) {
        const $this = $( this );
        e.preventDefault();
        const parentDiv = $this.parent("div");
        const inputValue =  parentDiv.find("input").val();

        $this.prev('a').prev("input").addClass('hidden');
        $this.addClass('hidden');
        $this.prev('.edit-file-label').removeClass('hidden');
        parentDiv.find("a.acpt-text-truncate").text(inputValue);
        parentDiv.find("a.acpt-text-truncate").removeClass('hidden');
    });

    /**
     * Single file delete
     */
    $('body').on('click', '.file-delete-btn', function(e) {
        const $this = $( this );
        e.preventDefault();

        const target = $this.data('target-id');
        const parentDiv = $this.parent('div');
        const input = parentDiv.find("input");
        const preview = parentDiv.prev( '.file-preview' );

        input.val('');

        if(input.length > 0 && input[1]){
            input[1].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
        }

        preview.html( '<span>No file selected</span>' );
    });

    /**
     * Upload file button
     */
    $('body').on('click', '.upload-file-btn', function(e) {

        const $this = $( this );
        const id = $this.data("id");
        const accepts = $this.data("accepts");
        const maxSize = $this.data("max-size");
        const minSize = $this.data("min-size");
        const minWidth = $this.data("min-width");
        const maxWidth = $this.data("max-width");
        const minHeight = $this.data("min-height");
        const maxHeight = $this.data("max-height");
        const targetId = $this.data("target-id");
        const hideLabel = $this.data("hide-label");
        const labelName = $this.data("label-name");
        const input = $this.prev( 'input' );
        const inputId = input.prev( 'input' );
        const errors = $( "#file-errors-"+id );
        const parentDiv = $this.parent('div');
        e.preventDefault();

        const picker = new WpImagePicker({
            title: useTranslation('Upload a File'),
            mimeTypes: accepts.replace("document", "application").split(","),
            minSizeMB: minSize,
            maxSizeMB: maxSize,
            minWidth: minWidth,
            maxWidth: maxWidth,
            maxHeight: maxHeight,
            minHeight: minHeight,
            button: useTranslation('Select a File'),
            onSelect: (file) => {
                const file_url = file.url;
                const file_id = file.id;
                const file_name = file.filename;

                inputId.val(file_id);
                input.val( file_url );

                if(input.length > 0){
                    input[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
                }

                let filePreview = `<a title="${file_url}" class="acpt-text-truncate" target="_blank" href="${file_url}">${file_name}</a>`;

                if(hideLabel === 0){
                    filePreview += `<input id="${targetId}" name="${labelName}" type="text" class="hidden file-label-input" value="${file_name}" placeholder="${useTranslation('Enter download text link')}">`;
                    filePreview += `<a href="#" data-target-id="${targetId}" class="edit-file-label" title="${useTranslation('Edit label')}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18px" height="18px" viewBox="0 0 24 24"><path fill="currentColor" d="M4 21a1 1 0 0 0 .24 0l4-1a1 1 0 0 0 .47-.26L21 7.41a2 2 0 0 0 0-2.82L19.42 3a2 2 0 0 0-2.83 0L4.3 15.29a1.06 1.06 0 0 0-.27.47l-1 4A1 1 0 0 0 3.76 21A1 1 0 0 0 4 21M18 4.41L19.59 6L18 7.59L16.42 6zM5.91 16.51L15 7.41L16.59 9l-9.1 9.1l-2.11.52z"/></svg>
                        </a>`;
                            filePreview += `<a href="#" data-target-id="${targetId}" class="save-file-label hidden" title="${useTranslation('Save label')}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18px" height="18px" viewBox="0 0 24 24"><path fill="currentColor" d="m10 15.586l-3.293-3.293l-1.414 1.414L10 18.414l9.707-9.707l-1.414-1.414z"/></svg>
                        </a>`;
                }

                parentDiv.prev( '.file-preview' ).html( filePreview);
            },
            onError: (msg) => {
                console.error(msg);
                alert(msg);
            },
            onOpen: (selection) => {
                let attachment = wp.media.attachment(inputId.val());
                selection.add(attachment);
            }
        });

        picker.open();

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Delete all images button
     */
    $('body').on('click', '.upload-delete-btn', function(e) {

        e.preventDefault();
        e.stopPropagation();

        const $this = $( this );
        const target = $this.data('target-id');
        const isVideo = $this.hasClass("delete-video-btn");
        const isAudio = $this.hasClass("delete-audio-btn");

        /**
         *
         * @return {string}
         */
        const message = () => {
            if(isAudio){
                return "No audio selected";
            }

            if(isVideo){
                return "No video selected";
            }

            return "No image selected";
        };

        $('#'+target).val('');
        $('#'+target+'_copy').val('');


        if($('#'+target).length > 0){
            $('#'+target)[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
        }

        if($('#'+target+'_copy').length > 0){
            $('#'+target+'_copy')[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
        }

        $('#'+target+'_attachment_id').val('');
        $('#'+target+'[attachment_id]').val('');

        $this.prev('.button').prev( '.inputs-wrapper' ).html('');
        $this.parent('div').prev( '.image-preview' ).html( `<span class="placeholder">${useTranslation(message())}</span>` );
        $this.parent('div').prev( '.audio-preview' ).html( `<span class="placeholder">${useTranslation(message())}</span>` );
        $this.parent('div').prev( '.playlist-preview' ).html( `<span class="placeholder">${useTranslation(message())}</span>` );

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Single audio file upload
     */
    $('body').on('click', '.upload-audio-btn', function(e) {
        const $this = $( this );
        const minSize = $this.data("min-size");
        const maxSize = $this.data("max-size");
        const input = $this.prev( 'input' );
        const inputId = input.prev( 'input' );
        const parentDiv = $this.parent('div');

        e.preventDefault();
        e.stopPropagation();

        const picker = new WpImagePicker({
            title: useTranslation('Upload an Audio file'),
            mimeTypes: ['audio'],
            minSizeMB: minSize,
            maxSizeMB: maxSize,
            button: useTranslation('Add audio file'),
            onSelect: (audio) => {
                const audio_url = audio.url;
                const audio_id = audio.id;
                const audio_title = audio.title ? audio.title : 'Unknown title';
                const audio_album = audio.meta.album ? audio.meta.album : 'Unknown album';
                const audio_artist = audio.meta.artist ? audio.meta.artist : 'Unknown artist';

                inputId.val(audio_id);
                input.val(audio_url);

                if(input.length > 0){
                    input[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
                }

                parentDiv.prev( '.audio-preview' ).html( `
                    <div class="audio">
                         <div class="acpt-audio-meta-wrapper">
                            <div class="acpt-audio-meta">
                                <h5 class="title">${audio_title}</h5>
                                <div class="meta">
                                    <span class="artist">${audio_artist}</span>
                                    <span class="album">- ${audio_album}</span>
                                </div>
                            </div>
                         </div>
                        <audio data-id="${audio_id}" class="acpt-audio-player" controls src="${audio_url}"></audio>
                    </div>` );
            },
            onError: (msg) => {
                console.error(msg);
                alert(msg);
            },
            onOpen: (selection) => {
                if(inputId.val() !== ''){
                    let attachment = wp.media.attachment(inputId.val());
                    selection.add(attachment);
                }
            }
        });

        picker.open();

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Playlist
     */
    $('body').on('click', '.upload-playlist-btn', function(e) {
        const $this = $( this );
        const minSize = $this.data("min-size");
        const maxSize = $this.data("max-size");
        const $inputWrapper = $this.prev( '.inputs-wrapper' );
        const $inputIds = $inputWrapper.prev( 'input' ).prev( 'input' );
        const $parentIndex = $this.data('parent-index');
        const $target = $inputWrapper.data('target');
        const $targetCopy = $inputWrapper.data('target-copy');
        const $placeholder = $('#'+$target+'_copy');
        e.preventDefault();
        e.stopPropagation();

        const picker = new WpImagePicker({
            title: useTranslation('Upload audio files'),
            mimeTypes: ['audio'],
            minSizeMB: minSize,
            maxSizeMB: maxSize,
            multiple: "add",
            button: useTranslation('Add audio files to the playlist'),
            onSelect: (audios) => {
                let audioIds = [];
                let audioUrls = [];
                let audioTitles = [];
                let audioArtists = [];
                let audioAlbums = [];

                audios.map((audio)=>{
                    audioIds.push(audio.id);
                    audioUrls.push(audio.url);
                    audioTitles.push(audio.title ? audio.title : 'Unknown title');
                    audioArtists.push(audio.meta.artist ? audio.meta.artist : 'Unknown artist');
                    audioAlbums.push(audio.meta.album ? audio.meta.album : 'Unknown album');
                });

                const audiosUrls = [];
                $inputWrapper.html('');

                audioUrls.map((audioUrl, index) => {

                    const targetToReplace = ($targetCopy) ? $targetCopy : $target;

                    $inputWrapper.append('<input name="'+targetToReplace+'[]" type="hidden" data-index="'+index+'" value="'+audioUrl+'">');
                    audiosUrls.push(audioUrl);
                });

                let preview = '';

                if(audiosUrls.length > 0){
                    $this.next('button').removeClass('hidden');
                }

                audiosUrls.map((audioUrl, index)=> {
                    const title = audioTitles[index];
                    const artist = audioArtists[index];
                    const album = audioAlbums[index];

                    preview += `<div class="audio" data-index="${index}" draggable="true">
                    <div class="handle">
                        .<br/>.<br/>.
                    </div>
                    <div class="acpt-audio-meta-wrapper">
                        <div class="acpt-audio-meta">
                            <h5 class="title">${title}</h5>
                            <div class="meta">
                                <span class="artist">${artist}</span>
                                <span class="album">- ${album}</span>
                            </div>
                        </div>
                        <audio data-id="${audioIds[index]}" class="acpt-audio-player" controls src="${audioUrl}"></audio>
                    </div>
                    <a class="delete-playlist-audio-btn" data-index="${index}" data-parent-index="${$parentIndex}" href="#" title="${useTranslation("Delete")}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                            <path d="M5 20a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8h2V6h-4V4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v2H3v2h2zM9 4h6v2H9zM8 8h9v12H7V8z"></path><path d="M9 10h2v8H9zm4 0h2v8h-2z"></path>
                        </svg>
                    </a>
                </div>`;
                });

                $this.parent('div').prev( '.playlist-preview' ).html( preview );
                $placeholder.val(audiosUrls.join(','));
                $placeholder[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
                $inputIds.val(audioIds.join(','));
            },
            onError: (msg) => {
                console.error(msg);
                alert(msg);
            },
            onOpen: (selection) => {
                if($inputIds.val() !== ''){
                    let attachments = [];
                    $inputIds.val().split(',').forEach((id)=>{
                        attachments.push(wp.media.attachment(id));
                    });

                    selection.add(attachments);
                }
            }
        });

        picker.open();

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Delete single playlist item
     */
    $('body').on('click', '.delete-playlist-audio-btn', function(e) {
        const $this = $( this );
        e.preventDefault();
        e.stopPropagation();

        const $index = $this.data('index');
        const $parentIndex = $this.data('parent-index');
        const $audio = $this.parent();
        const $audioWrapper = $audio.parent();
        const $target = $audioWrapper.data('target');

        let $inputIds;
        if($('#'+$target+'_attachment_id').length > 0){
            $inputIds = $('#'+$target+'_attachment_id');
        } else {
            $inputIds = $("#"+$target+"\\[attachment_id\\]\\["+$parentIndex+"\\]");
        }

        const $placeholder = $('#'+$target+'_copy');
        const $inputWrapper = $placeholder.next( '.inputs-wrapper' );

        // update input readonly
        const $saveValues = $placeholder.val().split(',');
        $saveValues.splice($index, 1);
        $placeholder.val($saveValues.join(','));

        if($placeholder.length > 0){
            $placeholder[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
        }

        // update input hidden
        $inputWrapper.children('input').each(function () {
            const $childIndex = $(this).data('index');

            if($childIndex === $index){
                $(this).remove();
            }
        });

        // update ids
        const $newInputIdsArray = [];
        const $inputIdsArray = $inputIds.val().split(",");

        $inputIdsArray.forEach((id, index)=>{
            if(index !== $index){
                $newInputIdsArray.push(id);
            }
        });

        $inputIds.val($newInputIdsArray.join(","));

        // delete this audio
        $audio.remove();

        if($audioWrapper.find(".audio").length === 0){
            $audioWrapper.html( `<span class="placeholder">${useTranslation("No file selected")}</span>` );
        }
    });

    /**
     * Slider image upload
     */
    $('body').on('click', '.upload-image-slider-btn', function(e) {
        const $this = $( this );
        const accepts = $this.data("accepts");
        const minSize = $this.data("min-size");
        const maxSize = $this.data("max-size");
        const minWidth = $this.data("min-width");
        const maxWidth = $this.data("max-width");
        const minHeight = $this.data("min-height");
        const maxHeight = $this.data("max-height");
        const $target = $this.data("target-id");
        const $index = $this.data("index");
        const $parentIndex = $this.data('parent-index');
        const $input = $this.prev("input");
        const $placeholder = $('#'+$target+'_copy');
        const $slider = $('#'+$target+"_slider");
        const defaultPercent = $slider.data("default-percent") ?? 50;
        const parentDiv = $this.parent("div");

        $slider[0].style.setProperty('--position', `${defaultPercent}%`);

        let $inputIds;

        if($('#'+$target+'_attachment_id').length > 0){
            $inputIds = $('#'+$target+'_attachment_id');
        } else {
            $inputIds = $("#"+$target+"\\[attachment_id\\]\\["+$parentIndex+"\\]");
        }

        e.preventDefault();
        e.stopPropagation();

        const picker = new WpImagePicker({
            title: useTranslation('Upload an Image'),
            mimeTypes: accepts.split(",") || ['image'],
            minSizeMB: minSize,
            maxSizeMB: maxSize,
            minWidth: minWidth,
            maxWidth: maxWidth,
            maxHeight: maxHeight,
            minHeight: minHeight,
            onSelect: (image) => {
                const image_url = image.url;
                const image_id = image.id;
                const image_name = image.name;

                $input.val(image_url);

                let savedInputIds = $inputIds.val();
                let savedInput = $placeholder.val();

                let newInputIds;
                let newInput;

                if(savedInputIds === '' && savedInput === ''){

                    let i = [null, null];
                    let ids = [null, null];

                    ids[$index] = image_id;
                    i[$index] = image_url;

                    newInputIds = ids.join(",");
                    newInput = i.join(",");
                } else {

                    let i = savedInput.split(',');
                    let ids = savedInputIds.split(',');

                    if(!i[0]){
                        i[0] = null;
                    }

                    if(!i[1]){
                        i[1] = null;
                    }

                    if(!ids[0]){
                        ids[0] = null;
                    }

                    if(!ids[1]){
                        ids[1] = null;
                    }

                    ids[$index] = image_id;
                    i[$index] = image_url;

                    // dynamic preview
                    if(i[0] !== null && i[1] !== null){
                        $slider.html(`
                    <div class="image-container">
                        <img
                            class="image-before slider-image"
                            src="${i[0]}"
                            alt=""
                        />
                       <img
                            class="image-after slider-image"
                             src="${i[1]}"
                            alt=""
                          />
                    </div>
                    <input type="range" min="0" max="100" value="${defaultPercent}" class="slider">
                    <div class="slider-line" aria-hidden="true"></div>
                    <div class="slider-button" aria-hidden="true">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="30"
                            height="30"
                            fill="currentColor"
                            viewBox="0 0 256 256"
                          >
                            <rect width="256" height="256" fill="none"></rect>
                            <line
                              x1="128"
                              y1="40"
                              x2="128"
                              y2="216"
                              fill="none"
                              stroke="currentColor"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="16"
                            ></line>
                            <line
                              x1="96"
                              y1="128"
                              x2="16"
                              y2="128"
                              fill="none"
                              stroke="currentColor"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="16"
                            ></line>
                            <polyline
                              points="48 160 16 128 48 96"
                              fill="none"
                              stroke="currentColor"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="16"
                            ></polyline>
                            <line
                              x1="160"
                              y1="128"
                              x2="240"
                              y2="128"
                              fill="none"
                              stroke="currentColor"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="16"
                            ></line>
                            <polyline
                              points="208 96 240 128 208 160"
                              fill="none"
                              stroke="currentColor"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="16"
                            ></polyline>
                          </svg>
                        </div>
                    </div>
                `);
                    }

                    newInputIds = ids.join(",");
                    newInput = i.join(",");
                }

                $inputIds.val(newInputIds);
                $placeholder.val(newInput);

                if($placeholder.length > 0){
                    $placeholder[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
                }

                parentDiv.prev( '.image-preview' ).html( '<div class="image"><img data-id"'+image_id+'" src="'+image_url+'" alt="'+image_name+'"/></div>' );
            },
            onError: (msg) => {
                console.error(msg);
                alert(msg);
            },
            onOpen: (selection) => {
                if($inputIds.val() !== ''){
                    let attachment = wp.media.attachment($inputIds.val());
                    selection.add(attachment);
                }
            }
        });

        picker.open();

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Single slider image delete
     */
    $('body').on('click', '.upload-delete-slider-btn', function(e) {

        e.preventDefault();
        e.stopPropagation();

        const $this = $( this );
        const $target = $this.data('target-id');
        const $index = $this.data("index");
        const $parentIndex = $this.data('parent-index');
        const $input = $this.prev('a').prev("input");
        const $placeholder = $('#'+$target+'_copy');
        const $slider = $('#'+$target+"_slider");
        const parentDiv = $this.parent("div");

        let $inputIds;

        if($('#'+$target+'_attachment_id').length > 0){
            $inputIds = $('#'+$target+'_attachment_id');
        } else {
            $inputIds = $("#"+$target+"\\[attachment_id\\]\\["+$parentIndex+"\\]");
        }

        let savedInputIds = $inputIds.val();
        let savedInput = $placeholder.val();

        let newInputIds;
        let newInput;

        let i = savedInput.split(',');
        let ids = savedInputIds.split(',');

        if(!i[0]){
            i[0] = null;
        }

        if(!i[1]){
            i[1] = null;
        }

        if(!ids[0]){
            ids[0] = null;
        }

        if(!ids[1]){
            ids[1] = null;
        }

        ids[$index] = null;
        i[$index] = null;

        if(i[0] === null && i[1] === null){
            newInputIds = '';
            newInput = '';
        } else {
            newInputIds = ids.join(",");
            newInput = i.join(",");
        }

        $inputIds.val(newInputIds);
        $input.val(newInput);
        $input.val('');

        parentDiv.prev( '.image-preview' ).html(`<div class="image"><span class="placeholder">${$index === 1 ? "Right" : "Left"} image</span></div>`);
        $slider.html(`<span class='placeholder'>Upload the left and the right images to see the slider</span>`);

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Single image upload
     */
    $('body').on('click', '.upload-image-btn', function(e) {
        const $this = $( this );
        const accepts = $this.data("accepts");
        const minSize = $this.data("min-size");
        const maxSize = $this.data("max-size");
        const minWidth = $this.data("min-width");
        const maxWidth = $this.data("max-width");
        const minHeight = $this.data("min-height");
        const maxHeight = $this.data("max-height");
        const input = $this.prev( 'input' );
        const inputId = input.prev( 'input' );
        const parentDiv = $this.parent('div');

        e.preventDefault();
        e.stopPropagation();

        const picker = new WpImagePicker({
            title: useTranslation('Upload an Image'),
            mimeTypes: accepts.split(",") || ['image'],
            minSizeMB: minSize,
            maxSizeMB: maxSize,
            minWidth: minWidth,
            maxWidth: maxWidth,
            maxHeight: maxHeight,
            minHeight: minHeight,
            onSelect: (image) => {
                const image_url = image.url;
                const image_id = image.id;
                const image_name = image.name;

                inputId.val(image_id);
                input.val(image_url);

                if(input.length > 0){
                    input[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
                }

                parentDiv.prev( '.image-preview' ).html( '<div class="image"><img data-id"'+image_id+'" src="'+image_url+'" alt="'+image_name+'"/></div>' );
            },
            onError: (msg) => {
                console.error(msg);
                alert(msg);
            },
            onOpen: (selection) => {
                if(inputId.val() !== ''){
                    let attachment = wp.media.attachment(inputId.val());
                    selection.add(attachment);
                }
            }
        });

        picker.open();

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Upload video button
     */
    $('body').on('click', '.upload-video-btn', function(e) {
        const $this = $( this );
        const minSize = $this.data("min-size");
        const maxSize = $this.data("max-size");
        const input = $this.prev( 'input' );
        const inputId = input.prev( 'input' );
        const parentDiv = $this.parent('div');

        e.preventDefault();
        e.stopPropagation();

        const picker = new WpImagePicker({
            title: useTranslation('Upload a Video'),
            mimeTypes: ['video'],
            minSizeMB: minSize,
            maxSizeMB: maxSize,
            onSelect: (video) => {
                const video_url = video.url;
                const video_id = video.id;

                inputId.val(video_id);
                input.val(video_url);

                if(input.length > 0){
                    input[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
                }

                parentDiv.prev( '.image-preview' ).html( '<div class="image"><video controls><source src="'+video_url+'" type="video/mp4"></video></div>' );
            },
            onError: (msg) => {
                console.error(msg);
                alert(msg);
            },
            onOpen: (selection) => {
                if(inputId.val() !== ''){
                    let attachment = wp.media.attachment(inputId.val());
                    selection.add(attachment);
                }
            }
        });

        picker.open();

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Gallery upload
     */
    $('body').on('click', '.upload-gallery-btn', function(e) {
        const $this = $( this );
        const accepts = $this.data("accepts");
        const minSize = $this.data("min-size");
        const maxSize = $this.data("max-size");
        const minWidth = $this.data("min-width");
        const maxWidth = $this.data("max-width");
        const minHeight = $this.data("min-height");
        const maxHeight = $this.data("max-height");
        const $parentIndex = $this.data('parent-index');
        const $inputWrapper = $this.prev( '.inputs-wrapper' );
        const $inputIds = $inputWrapper.prev( 'input' ).prev( 'input' );
        const $target = $inputWrapper.data('target');
        const $targetCopy = $inputWrapper.data('target-copy');
        const $placeholder = $('#'+$target+'_copy');
        e.preventDefault();
        e.stopPropagation();

        const picker = new WpImagePicker({
            title: useTranslation('Select images'),
            mimeTypes: accepts.split(",") || ['image'],
            minSizeMB: minSize,
            maxSizeMB: maxSize,
            minWidth: minWidth,
            maxWidth: maxWidth,
            maxHeight: maxHeight,
            minHeight: minHeight,
            button: useTranslation('Add images to the gallery'),
            multiple: "add",
            onSelect: (images) => {
                const imageIds = [];
                const imageUrls = [];
                const imageNames = [];

                images.map(
                    function ( attachment ) {
                        if(attachment.url){
                            imageIds.push(attachment.id);
                            imageUrls.push(attachment.url);
                            imageNames.push(attachment.name);
                        }
                    } );

                const imagesUrls = [];
                $inputWrapper.html('');

                imageUrls.map((imageUrl, index) => {

                    const targetToReplace = ($targetCopy) ? $targetCopy : $target;

                    $inputWrapper.append('<input name="'+targetToReplace+'[]" type="hidden" data-index="'+index+'" value="'+imageUrl+'">');
                    imagesUrls.push(imageUrl);
                });

                let preview = '';

                if(imageUrls.length > 0){
                    $this.next('button').removeClass('hidden');
                }

                imageUrls.map((imageUrl, index)=> {
                    preview += `
                    <div class="image" data-index="${index}">
                        <img data-id"${imageIds[index]}" src="${imageUrl}" alt="${imageNames[index]}"/>
                        <a class="delete-gallery-img-btn" data-index="${index}" data-parent-index="${$parentIndex}" href="#" title="${useTranslation("Delete")}">x</a>
                    </div>
                `;
                });

                $this.parent('div').prev( '.image-preview' ).html( preview );
                $placeholder.val(imagesUrls.join(','));

                if($placeholder.length > 0){
                    $placeholder[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
                }

                $inputIds.val(imageIds.join(','));
            },
            onError: (msg) => {
                console.error(msg);
                alert(msg);
            },
            onOpen: (selection) => {
                if($inputIds.val() !== ''){
                    let attachments = [];
                    $inputIds.val().split(',').forEach((id)=>{
                        attachments.push(wp.media.attachment(id));
                    });

                    selection.add(attachments);
                }
            }
        });

        picker.open();

        const evt = new Event("acpt_media_added");
        document.dispatchEvent(evt);
    });

    /**
     * Delete single gallery item
     */
    $('body').on('click', '.delete-gallery-img-btn', function(e) {
        const $this = $( this );
        e.preventDefault();
        e.stopPropagation();

        const $index = $this.data('index');
        const $parentIndex = $this.data('parent-index');
        const $image = $this.parent();
        const $imageWrapper = $image.parent();
        const $target = $imageWrapper.data('target');

        let $inputIds;

        if($('#'+$target+'_attachment_id').length > 0){
            $inputIds = $('#'+$target+'_attachment_id');
        } else {
            $inputIds = $("#"+$target+"\\[attachment_id\\]\\["+$parentIndex+"\\]");
        }

        const $placeholder = $('#'+$target+'_copy');
        const $inputWrapper = $placeholder.next( '.inputs-wrapper' );

        // update input readonly
        const $saveValues = $placeholder.val().split(',');
        $saveValues.splice($index, 1);
        $placeholder.val($saveValues.join(','));

        if($placeholder.length > 0){
            $placeholder[0].dispatchEvent(new Event("change")); // dispatch change Event for ACPTConditionalRules
        }

        // update input hidden
        $inputWrapper.children('input').each(function () {
            const $childIndex = $(this).data('index');

            if($childIndex === $index){
                $(this).remove();
            }
        });

        // update ids
        const $newInputIdsArray = [];
        const $inputIdsArray = $inputIds.val().split(",");

        $inputIdsArray.forEach((id, index)=>{
            if(index !== $index){
                $newInputIdsArray.push(id);
            }
        });

        $inputIds.val($newInputIdsArray.join(","));

        // delete this image
        $image.remove();

        if($imageWrapper.find(".image").length === 0){
            $imageWrapper.html( `<span class="placeholder">${useTranslation("No image selected")}</span>` );
        }
    });
};
