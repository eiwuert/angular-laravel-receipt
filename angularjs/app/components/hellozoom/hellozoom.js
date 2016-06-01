/**
 * Only support to display entire image
 * @author Vietnh
 */
(function ($) {

    $.fn.helloZoom = function (options) {

        var defaults = {
            wrapperId: null,
            event: 'click', //Support two event: hover or click
            positionLeft: 5,
            positionTop: 8,
            title: '',
            closeText: '',
            closeOnHeader: false, //Two areas: entire header (header) or close button (close)
            closeButtons: null, //We can set other elements that when it is clicked, we need to close the zoom
            draggable: true,
            printable: false,
            printButton: 'OriginalPrint',
            mailable: true,
            mailButton: 'OriginalMail',
            savable: true,
            saveButton: 'OriginalSave',
            type: 'image',
            imageWidth: 364,
            extraClass: '',
            headerClass: '',
            closeButtonOnCenter: true
        };
        var options = $.extend(defaults, options);

        return this.each(function () {

            var zoomButton = $(this);
            var wrapper = $('#' + options.wrapperId);
            if (options.type == 'image') {
                var image = wrapper.find('img');
            }

            function stop_link(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function open_zoom() {
                if (options.type == 'image') {
                    //image.fadeTo(0, 0.5).addClass('in-the-zoom');
                    image.addClass('in-the-zoom');
                    var url  = wrapper.attr('href');
                }

                //Avoid to open zoom area multiple times
                if (!$('.HelloZoomArea').length) {
                    //Append the zoom area
                    $('body').append('<div class="HelloZoomArea ' + options.extraClass + '"></div>');
                    var zoomArea = $('.HelloZoomArea');

                    //append the zoom header
                    var zoomHeaderHtml = '';
                    //Add print button to header if options.printable = true
                    if (options.printable) {
                        zoomHeaderHtml += '<div class="HelloZoomPrint"></div>';
                    }

                    //Add mail button to header if options.mailable = true
                    if (options.mailable) {
                        zoomHeaderHtml += '<div class="HelloZoomMail"></div>';
                    }

                    //Add save button to header if options.savable = true
                    if (options.savable) {
                        zoomHeaderHtml += '<div class="HelloZoomDownload"></div>';
                    }

                    //Add title and close button to header
                    if (options.title) {
                    	zoomHeaderHtml += options.title;
                    } else if (options.closeButtonOnCenter) {
                    	zoomHeaderHtml += '';
                    }

                    if (options.type == 'image') {
                        zoomHeaderHtml += '';//
                    }
                    if (options.type == 'html') {
                        zoomHeaderHtml += '<div class="HelloZoomXClose"></div>';//
                    }

                    zoomHeaderHtml = '<div class="HelloZoomHeader HellozoomHtmlIn ' + options.headerClass + '">' + zoomHeaderHtml + '</div>';
                    zoomArea.append(zoomHeaderHtml);

                    //append the zoomed image
                    if (options.type == 'image') {
                        zoomArea.append('<div detect-position-image class="HelloZoomImageWrapper"><img class="HelloZoomImage zoom-in-hv" src="' + url + '"/></div>');
                    }
                    if (options.type == 'html') {
                        zoomArea.append('<div class="HelloZoomContent">' + $('#' + options.wrapperId).html() + '</div>');
                    }

                    //Set left and top position of the zoom area
                    if (options.type == 'image') {
                        var imagePosition = image.position();
                        if (! options.positionLeft) {
                            options.positionLeft = imagePosition.left;
                        }
                        if (! options.positionTop) {
                            options.positionTop = imagePosition.top;
                        }
                    }

                    //set position styles and show zoom area
                    zoomArea.css({
                        left: options.positionLeft,
                        top: options.positionTop
                    }).fadeIn(200);

                    $('.HelloZoomImageWrapper, .HelloZoomContent').css({
                        height: $(window).height() - 55//Vietnh (19/8/2013): fix height of zoom box by screen height
                    });
                    $('.HelloZoomContent .raw-text').css('height', 'auto');

                    //Set draggable (need jquery UI)
                    if (options.draggable) {
                        zoomArea.draggable({
                            axis: 'x',
                            containment: 'body'
                        });
                    }

                    if (options.closeOnHeader) {
                        var closeClass = '.HelloZoomHeader';
                    } else {
                        var closeClass = '.HelloZoomClose, .HelloZoomXClose, .HellozoomHtmlIn';
                    }

                    $(closeClass).click(function () {
                        $.goodbyeZoom();
                    });
                    $(".img-zoom-out").click(function(){
                        $.goodbyeZoom();
                    });

                    $(options.closeButtons).click(function () {
                        $.goodbyeZoom({restore: false});
                    });

                    if (options.printable) {
                        $('.HelloZoomPrint').click(function () {
                            image.receiptPrint();
                        });
                    }

                    if (options.savable) {
                        $('.HelloZoomDownload').click(function () {
                            $.receiptSave(image);
                        });
                    }
					
					$('.HelloZoomImage').click(function () {
							$.goodbyeZoom();
					});
                }
            }

            wrapper.bind('click', stop_link);

            if (options.event == 'hover') {
                zoomButton.hover(open_zoom);
            }
            if (options.event == 'click') {
                zoomButton.bind('click', open_zoom);
            }

            $('.icon-download').click(function() {
                $.receiptSave(image);
            });

            $(window).resize(function() {
                $('.HelloZoomImageWrapper, .HelloZoomContent').css({
                    height: $(window).height() - 60//Vietnh (19/8/2013): fix height of zoom box by screen height
                });
            });
        });
    }

    $.goodbyeZoom = function (options) {
        //Animate the small image to normal
        jQuery('.in-the-zoom').each(function () {
            jQuery(this).animate({opacity: 1}, 200).removeClass('in-the-zoom');
        });
        //Remove the zoom image
        jQuery('.HelloZoomArea').each(function () {
            jQuery(this).remove();
        });
    }

    $.fn.receiptPrint = function() {
//        var src = $(this).attr('src').replace('styles/receipt/public/', '');
        var src = $(this).attr('src');
        //We will need to get real size of the image to unscale it later
        var real_height, real_width;
        $('<img/>').attr('src', src).load(function () {
            real_width = this.width;
            real_height = this.height;
        });

        var opener = window.open(src, 'mywindow');
        opener.focus();
        opener.onload = function () {
            var img = opener.document.getElementsByTagName('img')[0];
            img.width = real_width;
            img.height = real_height;
            opener.print();
            opener.close();
        }
        return false;
    }

    $.receiptSave = function (image) {
        //Unbind the beforeunload event
        window.onbeforeunload = null;
        //window.location.href = API_URL + '/receipts/download-image?fileName=' + image.attr('src').split('/').pop();
        window.location.href = image.prop('src');

        //Re-bind the beforeunload event
        setTimeout(function() {
            window.onbeforeunload = function() {
                //The message is displayed well in Chrome, but in Firefox we cannot override the default message
                return 'ReceiptClub says:';
            };
        }, 500);
    }

}(jQuery));


