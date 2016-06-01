var selectionTmpValue;

var start = null;
var end = null;

var resetShiftClick = function() {
    start = null;
    end = null;
};

function getParamFromUrl(url) {
    var vars = [], hash;
    var hashes = url.slice(url.indexOf('?') + 1).split('&');
    for(var i = 0; i < hashes.length; i++){
        hash = hashes[i].split('=');
        vars.push(hash[0]);
        vars[hash[0]] = hash[1];
    }
    return vars;

};

var checkboxOnClick = function(obj) {
    var isChecking = $(obj).hasClass('icon-checkbox-sqr');
    var tbody = $(obj).parent().closest('tbody');
    var index = tbody.index();
    var checkboxes = tbody.parent().find("tbody");

    if(event.shiftKey && start != null && end == null)
    {
        end = index;
        $.each(checkboxes, function(indexes, value)
        {
          console.log('abc');
            if (((indexes > Math.min(start, end) && indexes < Math.max(start, end)) || indexes == start) && (indexes != index))
            {
                var checkbox = $(value).find("i.checkboxshift");
                if (isChecking && checkbox.hasClass('icon-checkbox-sqr')) {
                    checkbox.click();

                }

                if ( !isChecking && checkbox.hasClass('icon-checkedbox-sqr')) {
                    checkbox.click();
                }
            }
        });

        end = null;
    }

    if (end == null) {
        start = index;
    }
};

var padNunber = function(number){
    return (number < 10) ? number = '0'+number : number;
}
(function($) {

    jQuery('#nav-container a').tooltip();


    //The jQuery method to resize height of the web in order to never show the browser screen scrollbar
    $.fn.resizeHeight = function(screen) {
       // console.log(screen);
        var windowHeight = $(window).height();
        var containerMinHeight = parseInt($(this).css('min-height'));

		var offset;
        if (screen == 'receipt_detail') {
            offset = 45;
        } else {
			if ($(this).parents('.app-rb').length) {
				offset = 284;
			}
			if ($(this).parents('.app-pe').length) {
				offset = 287;
			}
			if ($(this).parents('.app-te.tl').length) {
				offset = 277;
			}
			if ($(this).parents('.app-te.td').length) {
				offset = 343;
			}
			if ($(this).parents('.app-te.rl').length) {
				offset = 277;
			}
            if ($(this).parents('.app-analytic').length) {
				offset = 143;
			}
            if ($(this).parents('.app-box.app-te.rd').length) {
                offset = 343;
            }
            if($(this).parents('.app-db').length) {
                offset = 182;
            }
            if($(this).hasClass('form-setting')){
                offset = 260;
            }
		}

        var containerHeight = windowHeight - offset;
        if ($(this).prop('id') == 'rb-receipt-list') {
            containerHeight -= 10;
        }
        if ($(this).prop('id') == 'te-trip-list') {
            containerHeight -= 10;
        }
        if ($(this).prop('id') == 'te-report-list') {
            containerHeight -= 10;
        }

        if (screen == 'terms-condition') {
            containerHeight -= 50;
            if(windowHeight < 500) {
                return false;
            }
        }

        if (containerMinHeight > containerHeight) {
            return false;
        }
        $(this).css('height', containerHeight);

        if (screen == 'receipt_detail') {
            $('.email-receipt-container').css('height', containerHeight - 60);
            setTimeout(function(){
                $('.rd-ori-content').css('height', containerHeight - 60);
            },1000);
            $('.content-left-create-receipt').css('height', containerHeight - 55);
            // We set padding-bottom for #originalManualReceipt > div so we have to decrease 200px
            $('#originalManualReceipt > div').css('height', containerHeight - 39 - 200);
            $('#rd-container .child-div-515 > div').css('height', containerHeight - 268);
            $('#rd-container .invoice-receipt-wrapper.child-div-515 > div').css('height', containerHeight - 233);
            $('#rd-container .invoice-receipt-wrapper .raw-text').height(containerHeight - 110);
            $('.table-scroll').css('max-height',containerHeight - 530);
            $('.table-scroll').css('height','');

        }
        else {
            var distanceFromTop = 156;
            $('#sidebar-right').height($(window).height() - 106);
            $('#sidebar-right2').height($(window).height() - 126);
        }

    }

    /**
     * jQuery plugin to build content and show the Boostrap modal which we customized
     */
    $.showMessageBoxPopup = function(options) {
        var defaults = {
            // Content which we defined here will be appended to the modal body
            content : '',
            boxTitle: '',
            boxTitleClass: 'hide',
            boxClass: '',
            type: 'alert',
            labelOk: 'OK',
            labelYes: 'Yes',
            labelNo: 'No',
            labelCancel: 'Cancel',
            labelExtra: 'Extra button',
            labelYesTour: 'YES',
            labelNotNow: 'NOT NOW',
            onOKAction: function() {},
            onYesAction: function() {},
            onNoAction: function() {},
            onCancelAction: function() {},
            onExtraAction: function() {},
            onYesTourAction: function() {},
            onNotNowAction: function() {}
        };

        var options = $.extend(defaults, options);

        if ($('#messageBoxPopup').length) {
            var that = $('#messageBoxPopup');
            that.removeAttr('class').addClass('modal modal-rc hide');
            that.addClass(options.boxClass);
            that.find('.modal-header h3').addClass(options.boxTitleClass).html(options.boxTitle);
            that.find('.modal-body').html(options.content);
            switch (options.type) {
                case 'confirm':
                    that.find('.modal-footer').html('<button class="btn-confirm-yes">' + options.labelYes + '</button> <button class="btn-confirm-no" data-dismiss="modal" aria-hidden="true">' + options.labelNo + '</button>');
                    break;
                case 'full-confirm':
                    that.find('.modal-footer').html('<button class="btn-confirm-yes">' + options.labelYes + '</button> <button class="btn-confirm-no">' + options.labelNo + '</button><button class="btn-confirm-cancel" data-dismiss="modal" aria-hidden="true">' + options.labelCancel + '</button>');
                    break;
                case 'extra-confirm':
                    that.find('.modal-footer').html('<button class="btn-confirm-yes">' + options.labelYes + '</button> <button class="btn-confirm-extra">' + options.labelExtra + '</button> <button class="btn-confirm-no">' + options.labelNo + '</button><button class="btn-confirm-cancel" data-dismiss="modal" aria-hidden="true">' + options.labelCancel + '</button>');
                    break;
                case 'manual':
                    that.find('.modal-header button').remove();
                    that.find('.modal-footer').empty();
                    break;
                case 'tour':
                    that.find('.modal-header').html('<i class="icon-logo"></i><h3>'+ options.boxTitle +'</h3>');
                    that.find('.modal-footer').html('<button class="btn-confirm-yes-tour">' + options.labelYesTour + '</button> <button class="btn-confirm-not-now" data-dismiss="modal" aria-hidden="true">' + options.labelNotNow + '</button><label class="option"><input type="checkbox" id="showGuideCheckbox"><span>Don’t show this again</span></label><p class="tour-hint">(To see tour again, click on “Take a Tour” in the app header anytime)</p>');
                    that.find('.modal-footer').addClass("tour-footer");
                    break;
                default:
                    that.find('.modal-footer').html('<button class="btn-alert" data-dismiss="modal" aria-hidden="true">' + options.labelOk + '</button> ');
                    break;
            }

            that.modal({show: true, backdrop: 'static'});

            //Put message box to center vertically
            var messageBoxTop = Math.floor(($(window).height() - $('#messageBoxPopup').height()) / 2);
            that.animate({
                top: messageBoxTop
            });

            var btnYes = that.find('.btn-confirm-yes');
            if (btnYes.length) {
                btnYes.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onYesAction();
                });
            }

            var btnNo = that.find('.btn-confirm-no');
            if (btnNo.length) {
                btnNo.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onNoAction();
                });
            }

            var btnOK = that.find('.btn-alert');
            if (btnOK.length) {
                btnOK.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onOKAction();
                });
            }

            var btnExtra = that.find('.btn-confirm-extra');
            if (btnExtra.length) {
                btnExtra.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onExtraAction();
                });
            }

            var btnYesTour = that.find('.btn-confirm-yes-tour');
            if (btnYesTour.length) {
                btnYesTour.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onYesTourAction();
                });
            }

            var btnNotNow = that.find('.btn-confirm-not-now');
            if (btnNotNow.length) {
                btnNotNow.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onNotNowAction();
                });
            }
        }
    }

    /**
     * jQuery plugin to build content and show the Boostrap modal which we customized
     */
    $.showMessageBox = function(options) {
        var defaults = {
            // Content which we defined here will be appended to the modal body
            content : '',
            boxTitle: '',
            boxTitleClass: 'hide',
            boxClass: '',
            // We have five types of message modal box:
            // + 'alert': to show warning / error messages. With this type, we'll have only one button: OK.
            // This is the default type.
            // + 'confirm': to ask before doing actions like archiving or deleting receipts. This type provides
            // us two buttons: Yes, No
            // + 'full-confirm': similar to 'confirm', except that we will have 3 buttons (Yes, No, Cancel)
            // + 'extra-confirm': similar to 'full-confirm', except that we have 4 buttons
            // + 'manual': (Added 16/01/2014) This type will provide no buttons in both modal header and body.
            // We need to code to close the modal manually.
            type: 'alert',
            labelOk: 'OK',
            labelYes: 'Yes',
            labelNo: 'No',
            labelCancel: 'Cancel',
            labelExtra: 'Extra button',
            onYesAction: function() {},
            onNoAction: function() {},
            onCancelAction: function() {},
            onExtraAction: function() {}
        };

        var options = $.extend(defaults, options);

        if ($('#messageBox').length) {
            var that = $('#messageBox');
            that.removeAttr('class').addClass('modal modal-rc hide');
            that.addClass(options.boxClass);
            that.find('.modal-header h3').addClass(options.boxTitleClass).html(options.boxTitle);
            that.find('.modal-body').html(options.content);
            switch (options.type) {
                case 'confirm':
                    that.find('.modal-footer').html('<button class="btn-confirm-yes">' + options.labelYes + '</button> <button class="btn-confirm-no" data-dismiss="modal" aria-hidden="true">' + options.labelNo + '</button>');
                    break;
                case 'full-confirm':
                    that.find('.modal-footer').html('<button class="btn-confirm-yes">' + options.labelYes + '</button> <button class="btn-confirm-no">' + options.labelNo + '</button><button class="btn-confirm-cancel" data-dismiss="modal" aria-hidden="true">' + options.labelCancel + '</button>');
                    break;
                case 'extra-confirm':
                    that.find('.modal-footer').html('<button class="btn-confirm-yes">' + options.labelYes + '</button> <button class="btn-confirm-extra">' + options.labelExtra + '</button> <button class="btn-confirm-no">' + options.labelNo + '</button><button class="btn-confirm-cancel" data-dismiss="modal" aria-hidden="true">' + options.labelCancel + '</button>');
                    break;
                case 'manual':
                    that.find('.modal-header button').remove();
                    that.find('.modal-footer').empty();
                    break;
                default:
                    that.find('.modal-footer').html('<button class="btn-alert" data-dismiss="modal" aria-hidden="true">' + options.labelOk + '</button> ');
                    break;
            }

            that.modal({show: true, backdrop: 'static'});

            //Put message box to center vertically
            var messageBoxTop = Math.floor(($(window).height() - $('#messageBox').height()) / 2);
            that.animate({
                top: messageBoxTop
            });

            var btnYes = that.find('.btn-confirm-yes');
            if (btnYes.length) {
                btnYes.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onYesAction();
                });
            }

            var btnNo = that.find('.btn-confirm-no');
            if (btnNo.length) {
                btnNo.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onNoAction();
                });
            }

            var btnExtra = that.find('.btn-confirm-extra');
            if (btnExtra.length) {
                btnExtra.unbind().bind('click', function() {
                    that.modal('hide');
                    options.onExtraAction();
                });
            }
        }
    }

    /**
     * jQuery plugin to make text overflow without using CSS3 property 'text-overflow'
     * Copied from http://dl.dropboxusercontent.com/u/534786/jquery.text-overflow.js
     */
    $.fn.ellipsis = function() {
        var elementWidth, s = document.documentElement.style;
        var windowWidth = $(window).width();

        return this.each(function() {
            var that = $(this);

            if (that.parents('.have-leg').length) {
                if (windowWidth >= 1800) {
                    elementWidth = 370;
                } else if (windowWidth >= 1580 && windowWidth <= 1799) {
                    elementWidth = 220;
                } else if (windowWidth >= 1420 && windowWidth <= 1579) {
                    elementWidth = 180;
                } else if (windowWidth >= 1340 && windowWidth <= 1419) {
                    elementWidth = 155;
                } else if (windowWidth >= 1260 && windowWidth <= 1339) {
                    elementWidth = 120;
                } else {
                    elementWidth = 120;
                }
            } else if (that.parents('#td-trip-name').length) {
                if (windowWidth >= 1800) {
                    elementWidth = 400;
                } else if (windowWidth >= 1580 && windowWidth <= 1799) {
                    elementWidth = 250;
                } else if (windowWidth >= 1420 && windowWidth <= 1579) {
                    elementWidth = 220;
                } else if (windowWidth >= 1340 && windowWidth <= 1419) {
                    elementWidth = 190;
                } else if (windowWidth >= 1260 && windowWidth <= 1339) {
                    elementWidth = 160;
                } else {
                    elementWidth = 160;
                }
            } else {
                if (windowWidth >= 1800) {
                    elementWidth = 440;
                } else if (windowWidth >= 1580 && windowWidth <= 1799) {
                    elementWidth = 360;
                } else if (windowWidth >= 1420 && windowWidth <= 1579) {
                    elementWidth = 248;
                } else if (windowWidth >= 1340 && windowWidth <= 1419) {
                    elementWidth = 180;
                } else if (windowWidth >= 1260 && windowWidth <= 1339) {
                    elementWidth = 158;
                } else {
                    elementWidth = 128;
                }
            }

            if(that.css("overflow") == "hidden") {
                var text = that.attr('tooltip');
                if (typeof text == 'undefined') {
                    text = that.text();
                }
                that.html(text);

                var t = $(this.cloneNode(true)).hide().css({
                    'position': 'absolute',
                    'width': 'auto',
                    'overflow': 'visible',
                    'max-width': 'inherit',
                    'min-width': 0
                });
                that.after(t);

                while(text.length > 0 && t.width() > elementWidth) {
                    text = text.substr(0, text.length - 1);
                    t.html(text + "...");
                }

                that.html(t.html());
                t.remove();
            }
        });
    };

    $.fn.equalWidth = function(element) {
        var getterWidth = $(this).width();
        var setterWidth = $(element).width();

        $(this).width((getterWidth + setterWidth) / 2);
        $(element).width((getterWidth + setterWidth) / 2);
    }

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

//    if (jQuery('#user-profile-form').is(':visible') && jQuery(window).height() < 768) {
//        var homeContainerHeight = 780;
//    } else {
    var homeContainerHeight = jQuery(window).height() - (homeHeaderHeight + homeFooterHeight);
//    }

    var homeContainer = jQuery('#home-container');
    homeContainer.height(homeContainerHeight);
    if (homeContainer.find('.form-body-wrapper').length) {
        homeContainer.find('.form-body-wrapper').height(homeContainerHeight - 260);
        homeContainer.find('.form-body').height(homeContainerHeight - 270);
    }

    // Call setBackgrounCover() again until system set height of #home-container successfully
    if (!jQuery('#home-container').height()) {
        setTimeout(function() {
            setBackgroundCover();
        }, 500);
        return;
    }

//    if (jQuery(window).height() > 779 || jQuery(window).width() > 1555) {
//        jQuery('body').addClass('background-cover');
//    } else {
//        jQuery('body').removeClass('background-cover');
//    }
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

    //get the number of elements totally
    var appCount = $('#nav-expense-management li').length - 2;
    //get the number of elements which is currently displayed
    var appCurrentDisplayCount = $('#nav-expense-management li:visible:not(.lower)').length;
    if ($('.menu-more').is(':visible')) {
        appCurrentDisplayCount--;
    }
    //get the number of elements which now need to be displayed properly in the screen
    var appDisplayCount = Math.floor(($('#menu').width() - $('#nav-payments').width() - parseInt($('#nav-payments').css('marginRight')) - $('.menu-more').width() - $('#nav-home').width()) / (elementWidth + elementMarginRight));

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
            $('.menu-e' + i).prependTo('.menu-more-list').addClass('lower');
        }
    }

    //MAGIC part 2
    if (! $('.menu-more').is(':visible')) {
        $('li#nav-expense-title').css('width', $('#nav-expense-management').width()  - (elementWidth + elementMarginRight));
    } else {
        $('li#nav-expense-title').css('width', (appDisplayCount - 1)  * (elementWidth + elementMarginRight));
    }

    $(":not(.menu-more-list>a)").click(function (e) {
        $(".menu-more-list").css('left','');
        $(".menu-more-list").css('top','');
//        e.stopPropagation();
    });
    $(".menu-more-list").find('a').click(function(e) {
        $(".menu-more-list").css('left','-144px');
        $(".menu-more-list").css('top','37px');
//        e.stopPropagation();
    });
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

/**
 * Update width for some columns of table PE list (in both PE and TE apps)
 * For truncating long texts
 */
function truncatePETableText (currApp)
{
    var appClass;
    switch(currApp) {
        case 'pe':
            appClass = '.app-pe';
            //DISABLED by return;
            return;
        case 'te':
            appClass = '.app-te.td'
            break;
        default:
            appClass = '';
    }

    //CATEGORY column
    var cw = $('.app-box' + appClass + ' .app-table.tb-pe th.col-cat').width();
    var w = cw - 47;

    $('.app-box' + appClass + ' .app-table.tb-pe .app-table-child .cat-lv1 .col-cat .cat-name').width(w);
    $('.app-box' + appClass + ' .app-table.tb-pe .app-table-child .cat-lv2 .col-cat .cat-name').width(w - 18);
    $('.app-box' + appClass + ' .app-table.tb-pe .app-table-child .cat-lv2.item .col-cat .cat-name').width(w);
    $('.app-box' + appClass + ' .app-table.tb-pe .app-table-child .cat-lv3 .col-cat .cat-name').width(w - 36);
    $('.app-box' + appClass + ' .app-table.tb-pe .app-table-child .cat-lv3.item .col-cat .cat-name').width(w - 18);
    $('.app-box' + appClass + ' .app-table.tb-pe .app-table-child .cat-lv4.item .col-cat .cat-name').width(w - 36);

    $('.app-box' + appClass + ' .app-table.tb-pe .app-table-child td.col-cat .cat-name-item').width(cw-6);

    //ITEM column
    var iw = $('.app-box' + appClass + ' .app-table.tb-pe th.col-itm').width();
    if (iw) {
        $('.app-box' + appClass + ' .app-table.tb-pe td.col-itm .text-ellipsis').width(iw-10);
    }
}
/**
 * Update width for column category name of table RB listing
 * For truncating long receipt name
 */
function resizeReceiptNameWidth ()
{
    /*
    var rw = $('.app-box.app-rb .app-table th.col-rec').width();
    var w = rw - 60 - $('.app-box.app-rb .app-table td.col-rec .home-currency-rb').width();
    $('.app-box.app-rb .app-table .app-table-child td.col-rec .wrap .text-ellipsis').width(w);
    */
}
/**
 * Update width for some columns of table Trip list
 * For truncating long texts
 */
function truncateTripListTableText ()
{
    //TRIP NAME column
    var tw = $('.app-box.app-te.tl .app-table th.col-name').width();
    if ($('.app-box.app-te.tl .app-table td.col-name span').is(':visible')) {
        $('.app-box.app-te.tl .app-table td.col-name .text-ellipsis').width(tw-50);
    } else {
        $('.app-box.app-te.tl .app-table td.col-name .text-ellipsis').width(tw-10);
    }
}
/**
 * Update width for some columns of table Trip list
 * For truncating long texts
 */
function truncateTripDetailTableText ()
{
    //table Trip List - Equalize from and to columns
    var total = $('.app-box.app-te.td .app-table.tb-tl').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-name').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-stt').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-amo').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-sdat').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-edat').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-ref').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-rep').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-stt').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-clm').width()
                - $('.app-box.app-te.td .app-table.tb-tl .col-apv').width();
    var fw = total/2;
    $('.app-box.app-te.td .app-table.tb-tl .col-from').width(fw);
    $('.app-box.app-te.td .app-table.tb-tl .col-to').width(fw);

    //table Trip List - FROM column
    $('.app-box.app-te.td .app-table.tb-tl .col-from .text-ellipsis').width(fw - 4);
    //table Trip List - TO column
    $('.app-box.app-te.td .app-table.tb-tl .col-to .text-ellipsis').width(fw - 4);
}
/**
 * Update width for some columns of table Report list
 * For truncating long texts
 */
function truncateReportListTableText (app)
{
    if (typeof app == 'undefined') app = 'te';

    var ele;
    switch (app) {
        case 'te':
            ele = '#report-list-table'; break;
        case 'ta':
            ele = '#te-list-table'; break;
        default:
            return;
    }
}

/**
 * Update width for some columns of table Report detail in TravelApprover app
 * For truncating long texts
 */
function truncateReportDetailTableText (app, table)
{
    if (typeof table == 'undefined') table = 'all';
    if (typeof app == 'undefined') app = 'te';

    var ele;
    switch (app) {
        case 'te':
            ele = '#app-te'; break;
        case 'ta':
            ele = '#app-ta'; break;
        default:
            return;
    }

    if (table == 'report' || table == 'all') {
        //SUBMITTER column (table Report)
        var sw = $(ele + ' .app-table.tb-rl th.col-sub').width();
        $(ele + ' .app-table.tb-rl td.col-sub .text-ellipsis').width(sw-6);
        //APPROVER column (table Report)
        var aw = $(ele + ' .app-table.tb-rl th.col-apr').width();
        $(ele + ' .app-table.tb-rl td.col-apr .text-ellipsis').width(aw-6);
    }
    if (table == 'cat' || table == 'all') {
        //ITEM column (table Cat)
        var iw = $(ele + ' .app-table.tb-tl .tb-cat th.col-itm').width();
        //$('.app-box.app-te.rd .app-table.tb-tl .tb-cat td.col-itm .text-ellipsis').width(iw-4);
        //MERCHANT column (table Cat)
        var mw = $(ele + ' .app-table.tb-tl .tb-cat th.col-mrc').width(iw);
        $(ele + ' .app-table.tb-tl .tb-cat td.col-mrc .text-ellipsis').width(iw-4);
    }
}

/**
 * Trigger truncate functions on window resize
 */
$(window).resize(function() {
    //Update width for cat name column of pe table
    truncatePETableText('pe');
    truncatePETableText('te');
    //resizeReceiptNameWidth();
    truncateTripListTableText();
    truncateReportListTableText('te');
    //truncateReportDetailTableText('te');
    truncateReportListTableText('ta');
    truncateReportDetailTableText('ta');
});

/**
 * Calculate months between dates
 *
 * @param d1 Date object
 * @param d2 Date object
 * @returns {number}
 */
function getMonthDiff(d1, d2) {
    var months;
    months = (d2.getFullYear() - d1.getFullYear()) * 12;
    months -= d1.getMonth() + 1;
    months += d2.getMonth();
    return months <= 0 ? 0 : months;
}

function changeTitle($this){
    if($($this).find('b.caret').attr('class') == 'caret'){
        $($this).html('Coming Soon!<b class="caret"></b>');
    }else{
        $($this).html('Coming Soon!');
    }
    return false;
}
function revertTitle($this, $text){
    if($($this).find('b.caret').attr('class') == 'caret'){
        $($this).html($text+'<b class="caret"></b>');
    }else{
        $($this).html($text);
    }
}
/**
 * show message confirm Y/N
 */
function doConfirm(msg, yesFn, noFn) {
    var confirmBox = $("#confirmBox");
    confirmBox.find(".message").text(msg);
    confirmBox.find(".yes,.no").unbind().click(function () {
        confirmBox.hide();
    });
    confirmBox.find(".yes").click(yesFn);
    confirmBox.find(".no").click(noFn);
    confirmBox.show();
}

/**
 * Populate data in form
 */
function loadSerializedData(formId, data)
{

   // data = decodeURIComponent(data);
    var tmp = data.split('&'), dataObj = {};
    var str_tmp='';

// Bust apart the serialized data string into an obj
    for (var i = 0; i < tmp.length; i++)
    {
        var keyValPair = tmp[i].split('=');
        dataObj[keyValPair[0]] = keyValPair[1];
    }

// Loop thru form and assign each HTML tag the appropriate value
    $('#' + formId + ' :input').each(function(index, element) {
        if (dataObj[$(this).attr('name')]){
            str_tmp = dataObj[$(this).attr('name')].replace(/\+/g," ");
            $(this).val(decodeURIComponent(str_tmp));
        }
    });
}
/**
 * show pop confirm before leave page
 */
function showMessageConfirm(frm, msg, tmpFormValue, itemId){
    var tmpProfile = tmpFormValue;
    $('<div class="dia-confirm"></div>').appendTo('body')
        .html('<div style="font-size: 17px; font-family: Arial;">'+ msg +'</div>')
        .dialog({
            modal: true, title: 'Confirm message', zIndex: 10000, autoOpen: true,
            width: 'auto', resizable: false,
            buttons: {
                Yes: function () {
                    //frm = 'profile';
                    $('#'+ frm +'-save').trigger('click');
                    $(this).dialog("close");
                    //formValueProfile = null;
                    $('.db-boxs').hide();
                    $(itemId).show();
                    window.leavePage = true;
                },
                No: function () {
                    //frm = 'profile';
                    loadSerializedData(frm+'Form', tmpProfile);
                    $(this).dialog("close");
                    //formValueProfile = null;
                    $('.db-boxs').hide();
                    $(itemId).show();
                    window.leavePage = true;
                }
            }
        });
    $(".dia-confirm").css({
        "float": "left",
        "background": "#fff",
        "border-left": "7px solid #1f4da5",
        "border-right": "7px solid #1f4da5",
        "padding-top": "19px"
    })
    .parent().css("background","#1f4da5").find(".ui-widget-header").css({
        "border":"0",
        "display": "block",
        "background": "none",
        "height" : "20px"
    })
    .html(  '<div class="modal modal-rc" style="top:0; left: 0; position: initial;">' +
            '<i class="icon-logo"></i></div>' +
            '<h3 class="hide"></h3>')
    .parent().find(".ui-dialog-buttonpane").css({
        "border-left": "7px solid #1f4da5",
        "border-right": "7px solid #1f4da5",
        "border-bottom": "7px solid #1f4da5",
        "padding-bottom": "1em"
    })
    .find(".ui-dialog-buttonset").css({
        "float": "none",
        "text-align": "center"
    })
    .find("button").css({
        "padding": "1px 16px",
        "font-size": "14px"
    });
}

/**
 * An awesome array sorting function from stackoverflow
 */
function sortJsonArrayByKey(array, key, dir) {
    return array.sort(function(a, b) {
        var x = a[key]; var y = b[key];
        if (dir) {
            return ((x < y) ? ((x > y) ? 0 : 1) : -1);
        } else {
            return ((x < y) ? -1 : ((x > y) ? 1 : 0));
        }
    });
}

var showPopupAttachmentByEleId = function (ele, rowIndex, totalRow) {
    window.event.stopPropagation();
    $('.pov-list-attachment').hide();
    var targetID = $(ele).attr('id') + "-popover";

    if (typeof rowIndex != 'undefined') {
        var lineHeight     = 24;
        var popHeight      = $('#' + targetID).height();
        var topDistance    = lineHeight * (rowIndex - 1);
        var topOffset      = 0 - popHeight/2;
        var arrowTopOffset = popHeight/2 + 3;

        if (popHeight/2 > topDistance) {
            topOffset += popHeight/2 - topDistance;
            arrowTopOffset -= popHeight/2 - topDistance;
        }

        $('#' + targetID).css({top: topOffset + 'px'});
        $('#' + targetID + ' .arrow-side-right').css({top: arrowTopOffset + 'px'});
    }
    $('#' + targetID).show();

    $('body').bind('click', function(e) {
        $('.pov-list-attachment').fadeOut();
    });

}
var hidePopupAttachments = function () {
    $('.pov-list-attachment').css('display', 'none');
}

/**
 * Generate unique random string
 */
var guid = function(lightHash) {
    function s4() {
        return Math.floor((1 + Math.random()) * 0x10000)
                   .toString(16)
                   .substring(1);
    }

    //Return just a block of 4 hashcode if lightHash is true
    if (lightHash) return s4();

    return s4() + s4() + '-' + s4() + '-' + s4();
};

/**
 * Load amazon ads by javascript after all page's data is loaded
 * to avoid http request blocking cause slow loading of page
 */
function loadAmazonAds() {
  /* Load ads Asynchronously - prevent blocking other requests */
  //Small ad (portrait)
  var smallAd = '\<iframe src="https://rcm-na.amazon-adsystem.com/e/cm?t=recei05-20&o=1&p=29&l=ur1&category=gold&banner=1ZH2FARVZ9GEJCWZ26R2&f=ifr&lt1=_blank&linkID=MEIYABRI2EO7TU2X" width="120" height="600" scrolling="no" border="0" marginwidth="0" style="border:none;" frameborder="0"></iframe>';

  postscribe('#amz-ad-block', smallAd);
  postscribe('#sidebar-ad2', smallAd);

  //Big ad (square)
  var bigAd1 = '\<iframe src="https://rcm-na.amazon-adsystem.com/e/cm?t=recei05-20&o=1&p=12&l=ur1&category=big_events&banner=07CQ6VVKKYWQANVR9E02&f=ifr&linkID=TM7NOB3OCSCT4BR3&lt1=_blank" width="300" height="250" scrolling="no" border="0" marginwidth="0" style="border:none;" frameborder="0"></iframe>';
  var bigAd2 = '\<iframe src="https://rcm-na.amazon-adsystem.com/e/cm?t=recei05-20&o=1&p=12&l=ur1&category=cegghol13&banner=0BP0DFV0T1YX7XH9PC82&f=ifr&linkID=MN5NSW7W2B2O5LWP&lt1=_blank" width="300" height="250" scrolling="no" border="0" marginwidth="0" style="border:none;" frameborder="0"></iframe>';

  postscribe('#sidebar-ad', bigAd1);
  postscribe('#sidebar-ad-big2', bigAd2);
}

function Uint8ToBase64(u8a){
  var CHUNK_SZ = 0x8000;
  var c = [];
  for (var i=0; i < u8a.length; i+=CHUNK_SZ) {
    c.push(String.fromCharCode.apply(null, u8a.subarray(i, i+CHUNK_SZ)));
  }
  return c.join("");
}

