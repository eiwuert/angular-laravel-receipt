var selectionTmpValue;

(function($) {
    jQuery('#nav-container a').tooltip();

    //The jQuery method to resize height of the web in order to never show the browser screen scrollbar
    /*
    $.fn.resizeHeight = function(screen) {
        var windowHeight = $(window).height();
        var containerMinHeight = parseInt($(this).css('min-height'));
		
		var offset;
        if (screen == 'receipt_detail') {
            offset = 50;
        } else {
			if ($(this).parents('.app-rb').length) {
				offset = 280;
			}
			if ($(this).parents('.app-pe').length) {
				offset = 282;
			}
			if ($(this).parents('.app-te.tl').length) {
				offset = 277;
			}
			if ($(this).parents('.app-te.td').length) {
				offset = 347;
			}
			if ($(this).parents('.app-te.rl').length) {
				offset = 277;
			}
            if ($(this).parents('.app-analytic').length) {
				offset = 134;
			}
            if ($(this).parents('.app-box.app-te.rd').length) {
                offset = 335;
            }
		}
		
        var containerHeight = windowHeight - offset;
        if (containerMinHeight > containerHeight) {
            return false;
        }

        $(this).css('height', containerHeight);

        if (screen == 'receipt_detail') {
            $('#rd-ori-content').css('height', containerHeight - 65);
            $('#raw-text').css('height', containerHeight - 147);
            // We set padding-bottom for #originalManualReceipt > div so we have to decrease 200px
            $('#originalManualReceipt > div').css('height', containerHeight - 39 - 200);
            $('#rd-container .child-div-515 > div').css('height', containerHeight - 38 - 200);
            $(this).find('.table-scroll').css('height', containerHeight - 425);
        }
        else {
            var contentHeight = $(window).height() - 154;
            if ($('#sidebar-right').height() > contentHeight) {
                $('#sidebar-right img:last-child').hide();
            } else if ($('#sidebar-right').height() + 250 <= contentHeight) {
                $('#sidebar-right img:last-child').show();
            }
        }
    }
    */

    /**
     * jQuery plugin to build content and show the Boostrap modal which we customized
     */
    $.showMessageBox = function(options) {
        var defaults = {
            // Content which we defined here will be appended to the modal body
            content : '',
            // We have three types of message modal box:
            // + 'alert': to show warning / error messages. With this type, we'll have only one button: OK.
            // This is the default type.
            // + 'confirm': to ask before doing actions like archiving or deleting receipts. This type provides
            // us two buttons: Yes, No
            // + 'full-confirm': similar to 'confirm', except that we will have 3 buttons (Yes, No, Cancel)
            type: 'alert',
            labelOk: 'OK',
            labelYes: 'Yes',
            labelNo: 'No',
            labelCancel: 'Cancel',
            onYesAction: function() {},
            onNoAction: function() {},
            onCancelAction: function() {}
        };

        var options = $.extend(defaults, options);

        if ($('#messageBox').length) {
            var that = $('#messageBox');
            that.find('.modal-body').html(options.content);
            if (options.type == 'alert') {
                that.find('.modal-footer').html('<button class="btn-alert" data-dismiss="modal" aria-hidden="true">' + options.labelOk + '</button> ');
            } else if (options.type == 'confirm') {
                that.find('.modal-footer').html('<button class="btn-confirm-yes">' + options.labelYes + '</button> <button class="btn-confirm-no" data-dismiss="modal" aria-hidden="true">' + options.labelNo + '</button>');
            } else if (options.type == 'full-confirm') {
                that.find('.modal-footer').html('<button class="btn-confirm-yes">' + options.labelYes + '</button> <button class="btn-confirm-no">' + options.labelNo + '</button><button class="btn-confirm-cancel" data-dismiss="modal" aria-hidden="true">' + options.labelCancel + '</button>');
            }

            that.modal({show: true, backdrop: 'static'});

            var btnYes = that.find('.btn-confirm-yes');
            if (btnYes.length) {
                btnYes.unbind().bind('click', function() {
                    options.onYesAction();
                    that.modal('hide');
                });
            }

            var btnNo = that.find('.btn-confirm-no');
            if (btnNo.length) {
                btnNo.unbind().bind('click', function() {
                    options.onNoAction();
                    that.modal('hide');
                });
            }
        }
    }

    /**
     * jQuery plugin to make text overflow without using CSS3 property 'text-overflow'
     * Copied from http://dl.dropboxusercontent.com/u/534786/jquery.text-overflow.js
     */
    $.fn.ellipsis = function(enableUpdating, elementWidth) {
        var s = document.documentElement.style;
        return this.each(function() {
            var el = $(this);
            if(el.css("overflow") == "hidden") {
                var originalText = el.html();
                if (typeof elementWidth == 'undefined') {
                    var w = parseInt(el.width() * 0.7);
                } else {
                    var w = elementWidth;
                }

                var t = $(this.cloneNode(true)).hide().css({
                    'position': 'absolute',
                    'width': 'auto',
                    'overflow': 'visible',
                    'max-width': 'inherit',
                    'min-width': 0
                });
                el.after(t);

                var text = originalText;
                while(text.length > 0 && t.width() > w) {
                    text = text.substr(0, text.length - 1);
                    t.html(text + "...");
                }
                el.html(t.html());

                t.remove();

                if(enableUpdating == true) {
                    var oldW = el.width();
                    setInterval(function() {
                        if(el.width() != oldW) {
                            oldW = el.width();
                            el.html(originalText);
                            el.ellipsis();
                        }
                    }, 200);
                }
            }
        });
    };

    /**
     * Event listener for selecting text
     */
    addEventListener("mouseup", function(){
        var selection = window.getSelection();

        if (selection.isCollapsed) return;

        selectionTmpValue = selection.toString();
    }, false);
} (jQuery));

function setBackgroundCover() {
    var homeFooterHeight = 36;
    var homeHeaderHeight = 38;

    if (jQuery('#home-footer').height()) {
        homeFooterHeight = jQuery('#home-footer').height();
    }
    if (jQuery('#home-header').height()) {
        homeHeaderHeight = jQuery('#home-header').height();
    }

    if (jQuery('#user-profile-form').is(':visible') && jQuery(window).height() < 768) {
        var homeContainerHeight = 780;
    } else {
        var homeContainerHeight = jQuery(window).height() - (homeHeaderHeight + homeFooterHeight);
    }

    jQuery('#home-container').height(homeContainerHeight);

    // Call setBackgrounCover() again until system set height of #home-container successfully
    if (!jQuery('#home-container').height()) {
        setTimeout(function() {
            setBackgroundCover();
        }, 500);
        return;
    }

    if (jQuery(window).height() > 779 || jQuery(window).width() > 1555) {
        jQuery('body').addClass('background-cover');
    } else {
        jQuery('body').removeClass('background-cover');
    }
}

function resizeMenu()
{
    if ($('#menu').width() == 0) {
        return;
    }

    //get width of an element (all is equal)
    var elementWidth = $('#nav-payments li').width();
    //get margin right of an element (all is equal except the last of each navigation)
    var elementMarginRight = parseInt($('#nav-payments li').css('marginRight'));

    //Calculate the payments navigation width
    var navPaymentsWidth = elementWidth * $('#nav-payments li').length;
    navPaymentsWidth += elementMarginRight * ($('#nav-payments li').length - 1);
    navPaymentsWidth += parseInt($('#nav-payments').css('marginRight'));    

    //get the number of elements totally
    var appCount = $('#nav-expense-management li').length - 1;
    //get the number of elements which is currently displayed
    var appCurrentDisplayCount = $('#nav-expense-management li:not(.lower)').length - 1;
    //get the number of elements which now need to be displayed properly in the screen
    var appDisplayCount = Math.floor(($('#menu').width() - navPaymentsWidth - elementWidth) / (elementWidth + elementMarginRight));

    //MAGIC
    if (appDisplayCount >= appCurrentDisplayCount) {
        if (appDisplayCount >= (appCount)) {
            $('.menu-more').css('display', 'none');
        } else if (! $('.menu-more').is(':visible')) {
            $('.menu-more').css('display', 'inline-block');
        }

        for (var i = appCurrentDisplayCount + 1; i <= appDisplayCount; i++) {
            $('.menu-e' + i).appendTo('ul#nav-expense-management').removeClass('lower');
        }
        $('.menu-more').appendTo('ul#nav-expense-management');
    } else {
        if (! $('.menu-more').is(':visible')) {
            $('.menu-more').css('display', 'inline-block');
        }

        for (var i = appCurrentDisplayCount; i >= appDisplayCount + 1; i--) {
            $('.menu-e' + i).prependTo('.menu-more ul').addClass('lower');
        }
    }

    //MAGIC part 2
    if (! $('.menu-more').is(':visible')) {
        $('li#nav-expense-title').css('width', (appDisplayCount - 1) * (elementWidth + elementMarginRight));
    } else {
        $('li#nav-expense-title').css('width', appDisplayCount * (elementWidth + elementMarginRight));
    }
}

function getItemStatus(status) {
    switch (status) {
        case 1:
            return 'Auto-categorized';
        case 2:
            return 'User categorized';
        default:
            return 'Not categorized';
    }
}

function setCheckAllItems(isChecked, childs) {
    for (var i in childs) {
        childs[i].IsChecked = isChecked;
    }
}

function formatDateOrTime(dateIsoStr, type)
{
    var dateArr = dateIsoStr.replace('T', ' ').split(' ');

    if (type == 'time') {
        var timeArr = dateArr[1].split(':');
        var ampm = 'AM';
        if (timeArr[0] > 12) {
            timeArr[0] = timeArr[0] - 12;
            ampm = 'PM';
        } else if (timeArr[0] == 12) {
            ampm = 'PM';
        } else if (timeArr[0] == 0) {
            timeArr[0] == 12;
        }

        return timeArr[0] + ':' + timeArr[1] + ' ' + ampm;
    }
    if (type == 'date') {
        var dateObjArr = new Date(dateArr[0] + 'T' + dateArr[1]).toDateString().split(' ');
        return dateObjArr[2] + '-' + dateObjArr[1] + '-' + dateObjArr[3];
    }
}

function deviceHasGetUserMedia() {
    // Note: Opera is unprefixed.
    return !!(navigator.getUserMedia || navigator.webkitGetUserMedia ||
        navigator.mozGetUserMedia || navigator.msGetUserMedia);
}
function deviceIsMobileDevice() {
    if((navigator.userAgent.match(/iPhone/i)) ||
        (navigator.userAgent.match(/iPad/i)) ||
        (navigator.userAgent.match(/iPod/i)) ||
        (navigator.userAgent.match(/Android/i))) {
        return true;
    }

    return false;
}

function truncateTextInRB(){
//    var w = $('.app-box.app-rb .app-table th.col-rec').width();
//    console.log(w);
//    var offset = $('.app-box.app-rb .app-table td.col-rec .home-currency-rb').width() + 60;
//    offset = 30;
//    $('.app-box.app-rb .app-table tr.level-cat td.col-rec .text-ellipsis').width(w - offset);
//    $('.app-box.app-rb .app-table tr.level-item td.col-rec .text-ellipsis').width(w - offset -22);
}

//$(window).resize(function() {
//    truncateTextInRB();
//
//});

function setCookie(c_name,value,exdays)
{
    var exdate=new Date();
    exdate.setDate(exdate.getDate() + exdays);
    var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
    document.cookie=c_name + "=" + c_value;
}

function getCookie(c_name)
{
    var c_value = document.cookie;
    var c_start = c_value.indexOf(" " + c_name + "=");
    if (c_start == -1) {
        c_start = c_value.indexOf(c_name + "=");
    }
    if (c_start == -1) {
        c_value = null;
    } else {
        c_start = c_value.indexOf("=", c_start) + 1;
        var c_end = c_value.indexOf(";", c_start);
        if (c_end == -1) {
            c_end = c_value.length;
        }
        c_value = unescape(c_value.substring(c_start,c_end));
    }
    return c_value;
}