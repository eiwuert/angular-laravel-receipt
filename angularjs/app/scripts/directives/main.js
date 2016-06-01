rciSpaApp.directive('tooltip', function() {
    return {
        restrict: 'A',
        link: function(scope, element, attrs) {
            $(element).mouseenter(function(){
                if(checkOverflow(this)){
                    element.attr('title', attrs.tooltip);
                    element.removeAttr('tooltip');
                }
            })
        }
    }
});

function checkOverflow(el)
{
    var curOverflow = el.style.overflow;
    if ( !curOverflow || curOverflow === "visible" )
        el.style.overflow = "hidden";

    var isOverflowing = el.clientWidth < el.scrollWidth
        || el.clientHeight < el.scrollHeight;

    el.style.overflow = curOverflow;

    return isOverflowing;
}

rciSpaApp.directive('responsiveMenu', function() {
    return function(scope, element, attrs) {
        resizeMenu();
        $(window).resize(function() {
            resizeMenu();
        });
    }
});

rciSpaApp.directive('switchDashboard', function($location,$rootScope) {
    return{
        restrict: 'A',
        link: function(scope, element, attrs) {
            element.bind('click', function(e) {
                $rootScope.openDashboard('dashbroad');
//                $rootScope.$parent.openDashboard();
            });
        }
    }
});
rciSpaApp.directive('showPage', function($location, $rootScope, $timeout) {
    return {
        scope: {
            layout: '@',
            activeClass: '@',
            selectedItem: '@',
            pageName: '@'
        },
        controller: function ($scope) {
            $scope.triggerOpenPage = function (page) {
                $rootScope.$emit('RB_CLEAR_BACK_TO_APP');
                    switch (page) {
                        case 'dashboard-wrapper':
                          $rootScope.inAppScreen = 'DASHBOARD';
                        break;
                        case 'receiptbox-wrapper':
                            $rootScope.inAppScreen = 'RECEIPT_BOX';
                            break;
                        case 'personal-expense-wrapper':
                            $rootScope.inAppScreen = "PERSONAL_EXPENSE";
                            break;
                        case 'education-expense-wrapper':
                            $rootScope.inAppScreen = 'EDUCATION_EXPENSE';
                            break;
                        case 'business-expense-wrapper':
                            $rootScope.inAppScreen = 'BUSINESS_EXPENSE';
                            break;
                        case 'personal-assets-wrapper':
                            $rootScope.inAppScreen = 'PERSONAL_ASSETS';
                            break;
                        case 'business-assets-wrapper':
                            $rootScope.inAppScreen = 'BUSSINESS_ASSETS';
                            break;
                        case 'trip-list-wrapper':
                            $rootScope.inAppScreen = 'TRIP_LIST';
                            truncateTripListTableText();
                            //$('#menu-travel-expense').click();
                            break;
                        case 'report-list-wrapper':
                            $rootScope.inAppScreen = 'REPORT_LIST';
                            truncateReportListTableText();
                            setTimeout(function () {
                                $('#notify-report').addClass('in-screen-report');
                            }, 500);
                          break;
                        case 'approver-list-wrapper':
                            $rootScope.inAppScreen = 'APPROVER_LIST';
                            truncateReportListTableText('ta');
                            setTimeout(function () {
                                $('#notify-approver').addClass('in-screen-report');
                            }, 500);
                            break;
                        }
              if(!$rootScope.$$phase) { $rootScope.$apply() }

            }
        },
        link: function(scope, element, attrs) {
            element.bind('click', function(e) {
                $('#rb-back-to-app').removeClass('show').addClass('hide');
                jQuery('#nav-container li a').removeClass('green');
                $('#nav-container li a').removeClass('aqua');
                $('#nav-container li a').removeClass('blue');

                scope.selectedItem = scope.selectedItem ? scope.selectedItem : this;
                if (scope.activeClass == "green") {
                    jQuery(scope.selectedItem).addClass(scope.activeClass);
                } if (scope.activeClass == "blue") {
                    jQuery(scope.selectedItem).addClass('blue');
                } if (scope.activeClass == "aqua") {
                    jQuery(scope.selectedItem).addClass('aqua');
                }

                // Set referrer target
                var referrerId = jQuery('.page-app:visible').prop('id') || 'home-page-wrapper';
                jQuery('#backHistory').attr('back-button', referrerId);

                jQuery('.page-app').hide();
                jQuery('#ngview-wrapper').hide();
                jQuery('#home-page-wrapper').hide();
                jQuery('#' + attrs.showPage).show();
                if (attrs.showPage == 'receiptbox-wrapper') {
                    jQuery('#menu-receiptbox').addClass('green');
                }

                if (attrs.showPage == '404-wrapper') {
                    jQuery('#app-name-placeholder').html(scope.pageName);
                    jQuery('#container').addClass('landing-wrapper');
                    jQuery('#sidebar-right').removeClass('show').addClass('hide');
                } else {
                    if (scope.layout == 'landing') {
                        jQuery('#container').addClass('landing-wrapper');
                        jQuery('#top-header').removeClass('show').addClass('hide');

                        if (attrs.showPage == 'home-page-wrapper') {
                            jQuery('#sidebar-right').removeClass('show').addClass('hide');
                            jQuery('body').addClass('front');
                            jQuery('html').addClass('landing');
                        } else {
                            jQuery('body').removeClass('front');
                            jQuery('html').removeClass('landing');
                        }
                    } else {
                        jQuery('#container').removeClass('landing-wrapper');
                        jQuery('#top-header').removeClass('hide').addClass('show');
                        jQuery('#sidebar-right').removeClass('hide').addClass('show');
                        jQuery('body').removeClass('front').removeClass('profile');
                        jQuery('html').removeClass('landing');
                    }
                }
                scope.triggerOpenPage(attrs.showPage);
            });
        }
    }
});

rciSpaApp.directive('backButton', function() {
    return function(scope, element, attrs) {
        $(element).on('click', function(e) {
            var id = $(this).attr('back-button');

            var referrerId = $('.page-app:visible').prop('id') || 'home-page-wrapper';
            $('#backHistory').attr('back-button', referrerId);

            $('#nav-container li a').removeClass('green').removeClass('aqua');
            $('.page-app').hide();
            $('#ngview-wrapper').hide();
            $('#home-page-wrapper').hide();
            $('#' + id).show();

            switch (id) {
                case 'receiptbox-wrapper':
                    $('#menu-receiptbox').addClass('green');
                    break;

                case 'personal-expense-wrapper':
                    $('#menu-personal-expense').addClass('aqua');
                    break;

                case 'education-expense-wrapper':
                    $('#menu-education-expense').addClass('aqua');
                    break;
                case 'business-expense-wrapper':
                    $('#menu-business-expense').addClass('aqua');
                    break;
                case 'personal-assets-wrapper':
                    $('#menu-personal-assets').addClass('aqua');
                    break;
                case 'business-assets-wrapper':
                    $('#menu-business-assets').addClass('aqua');
                    break;
                case 'trip-list-wrapper':
                case 'report-list-wrapper':
                    $('#menu-travel-expense').addClass('aqua');
                    break;
            }

            if (id == 'home-page-wrapper') {
                $('#container').addClass('landing-wrapper');
                $('#top-header').removeClass('show').addClass('hide');
                $('#sidebar-right').removeClass('show').addClass('hide');
                $('body').addClass('front');
            } else {
                $('body').removeClass('front');
            }
        });
    }
});

/**
 * Get OS browser scrollbar's width
 *
 * @returns int
 */
function getScrollBarWidth () {
    var inner = document.createElement('p');
    inner.style.width = "100%";
    inner.style.height = "200px";

    var outer = document.createElement('div');
    outer.style.position = "absolute";
    outer.style.top = "0px";
    outer.style.left = "0px";
    outer.style.visibility = "hidden";
    outer.style.width = "200px";
    outer.style.height = "150px";
    outer.style.overflow = "hidden";
    outer.appendChild (inner);

    document.body.appendChild (outer);
    var w1 = inner.offsetWidth;
    outer.style.overflow = 'scroll';
    var w2 = inner.offsetWidth;
    if (w1 == w2) w2 = outer.clientWidth;

    document.body.removeChild (outer);

    return (w1 - w2);
};

/**
 * Set buffer cell for table's threads to have equal width with scrollbar
 */
rciSpaApp.directive('fluidTable', function() {
    return function(){
        var w = getScrollBarWidth();
        $('.app-table th.col-non').width(w);
    }
});

/**
 * Directive for dealing with table scrollbar
 * Align table header and table child columns by adding a buffer column in header: col-non
 */
rciSpaApp.directive('fluidTableChild', function($timeout) {
    return function(){
        var w = getScrollBarWidth();
        $('.app-table .col-non').width(w);

        //In case browser has no scrollbar
        if (w == 0) {
            $timeout(function(){
                if ($('.app-table .col-non').length) {
                    $('.app-table .col-non').remove();
                    $('.app-table th:last-child .th').addClass('lastth');

                    //Fix GUI bugs
                    $('.app-table th:last-child .th').css({
                        'margin-right' : '-2px'
                    });
                    $('.app-table th:last-child').css('padding-right', '2px');
                }
            })
        }
    }
});

//Display the sign up modal
rciSpaApp.directive('showRegisterForm', function() {
    return function(scope, element, attrs) {
        $('#btn-close-register-form').bind('click', function() {
            $('#user-register-modal').fadeOut();
        });
        $(element).bind('click', function(e) {
//            e.stopPropagation();
            $('#user-register-modal').css('display', 'block');
            $('#user-login-modal').css('display', 'none');
            $('#user-forgot-password-modal').css('display', 'none');
        });
//        $('#user-register-modal').bind('click', function(e) {
//            e.stopPropagation();
//        });
    }
});

//Display the login modal
rciSpaApp.directive('showLoginForm', function(localStorageService) {
    return function(scope, element, attrs) {
        /*$('#btn-close-login-form').bind('click', function() {
            $('#user-login-modal').fadeOut();
        });*/
        $("#btn-close-sign-in").bind('click',function(){
            $("#user-login-modal").hide();
            $('#full-screen').hide();

        });
        /*
        var lastLoginAccount;
        var useRememberMe = false;
        if (localStorageService.isSupported()) {
            lastLoginAccount = localStorageService.cookie.get('lastLoginAccount');
            lastLoginAccount = angular.fromJson(lastLoginAccount);
            useRememberMe    = localStorageService.cookie.get('rememberMe');
        }
        */

        $(element).bind('click', function(e) {
//            e.stopPropagation();
            $('#user-login-modal').css('display', 'block');
            $('#user-login-modal .alert-error').hide();

            /*
            if (angular.isObject(lastLoginAccount)) {
                $('#user-login-modal #inputLoginEmail').val(lastLoginAccount.email).focus();
                $('#user-login-modal #inputLoginEmail').trigger('input');
            }

            if (useRememberMe == 'true') {
                $('#user-login-modal #inputLoginPassword').val(lastLoginAccount.password);
                $('#user-login-modal #inputLoginPassword').trigger('input');
                //$('#user-login-modal #remember-me').trigger('click');
                $('#user-login-modal #remember-me').prop('checked', 'checked');
            }
            */

            $('#user-register-modal').css('display', 'none');
            $('#user-forgot-password-modal').css('display', 'none');
        });
//        $('#user-login-modal').bind('click', function(e) {
//            e.stopPropagation();
//        });
    }
});

//Display the forgot password modal
rciSpaApp.directive('showForgotPasswordForm', function() {
    return function(scope, element, attrs) {

        $('#btn-close-forgot-form').bind('click', function() {
            $('#user-forgot-password-modal').fadeOut();
        });
        $("#btn-close-reset-password").bind('click',function(){
            $("#user-forgot-password-modal").hide();
            $('#full-screen').hide();
        });
        $(element).bind('click', function(e) {
//            e.stopPropagation();
            var w = $(window).width();
            var h = $(window).height();
            var x = 100;
            var y = w - $('#user-login-modal').width() - 63;
            $('#user-forgot-password-modal').css({
                'position':'fixed',
                'top': x,
                'left': y
            });
            $('#user-forgot-password-modal').css('display', 'block');
            $('#user-login-modal').css('display', 'none');
            $('#user-register-modal').css('display', 'none');
        });
//        $('#user-forgot-password-modal').bind('click', function(e) {
//            e.stopPropagation();
//        });
    }
});

rciSpaApp.directive('posterCarousel', function(){
    return {
        restrict: 'E',
        scope: {posters:'=posters'},
        replace: true,
        template: '<div id="posterModal" class="modal fade poster-popup">' +
            '<div id="posterCarousel" class="carousel">' +
                '<div class="pp-nav-left"><a href="#posterCarousel" data-slide="prev" class="app-icon btn-previous"></a></div>' +
                '<div class="carousel-inner">' +
                    '<div class="item {{poster.bgClass}}" ng-class="{\'active\':$index==0}" ng-repeat="poster in posters">' +
                        '<div class="modal-body-title">' +
                            '<div class="title">' +
                                '<div class="pull-left"><span class="utmaltergothic" ng-class="{\'white\':poster.textColor==\'white\'}">{{poster.app}}</span></div>' +
                                '<div class="pull-right"><i class="app-icon {{poster.iconClass}}"></i></div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<div class="pp-content">' +
                                '<div class="body">' +
                                    '<div class="{{poster.textColor}}" ng-bind-html-unsafe="poster.content"></div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="pp-nav-right"><a href="#posterCarousel" data-slide="next" class="app-icon btn-next"></a></div>' +
            '</div>' +
            '</div>'
    }
});

rciSpaApp.directive('openPagePopup', function() {
    return function(scope, element, attrs) {
        element.bind('click', function(e) {
            if (typeof attrs.page == 'undefined') {
                return false;
            }

            //do not close the login form or register form when we click on the link
            e.stopPropagation();
            var modal = $('#pageModal').modal({show: false}).on('shown', function() {
                $(this).add('.modal-backdrop').bind('click', function(e) {
                    //do not close the login form or register form when we click on the backdrop
                    e.stopPropagation();
                });
            });
            $('#pageModal .modal-body').load('views/' + attrs.page + '.html', function() {
                if (attrs.page == 'privacy') {
                    $('#pageModal .modal-header h3').text('ReceiptClub Privacy Policy');
                }

                if (attrs.page == 'terms') {
                    $('#pageModal .modal-header h3').text('Receiptclub Inc. Terms of Service');
                }

                if (attrs.page == 'about') {

                }

                modal.modal('show');
            });
        });
    }
});

rciSpaApp.directive('appResizeHeight', function() {
    return function(scope, element, attrs) {
        $(element).resizeHeight();
        $(window).resize(function() {
            $(element).resizeHeight();
        });
        $('#nav-container a').bind('click', function() {
            $(element).resizeHeight();
        });
    }
});
rciSpaApp.directive('appResizeHeightWithHeader', function() {
    return function(scope, element, attrs) {
        $(element).resizeHeight(attrs.screen);
        $(window).resize(function() {
            $(element).resizeHeight(attrs.screen);
        });
        $('#nav-container a').bind('click', function() {
            $(element).resizeHeight(attrs.screen);
        });
    }
});
rciSpaApp.directive('appResetPrefix', function() {
    return function (scope, elem, attrs) {
        elem.bind('click', function () {
                scope.$parent.resetPrefix();
        });
    }
});
rciSpaApp.directive('appShowHeader', function() {
    return function(scope, element, attrs) {
        scope.$parent.showHeader = false;
    }
});
rciSpaApp.directive('appMenuHeader', function() {
    return {
        restrict: "A",
        link: function(scope, element, attrs, ngModel) {
            var opts = scope.$eval(attrs.appMenuHeader);
            $scope = scope.$parent;
            element.bind('click', function(e) {
                $scope.tmpDefaultScreen = angular.copy(2);
            });
        }
    }
});

rciSpaApp.directive('uiBlur', function () {
    return function (scope, elem, attrs) {
        elem.bind('blur', function () {
            scope.$apply(attrs.uiBlur);
        });
    }
});

/**
 * Directive to enable shift select for a checkbox list as mail list of gmail
 * Currently disable
 */
/*
rciSpaApp.directive('shiftSelect', function(){
    return {
        restrict : 'A',
        scope : {
            list : '=shiftSelect'
        },
        link : function(scope) {
            var targets = '';
            for (var i=0; i<scope.list.length; i++) {
                if (i>0) targets += ',';
                targets += 'list[' + i + '].IsChecked';

                scope.$watch('list[' + i + '].IsChecked', function(newValue, oldValue){
                    console.log(newValue);
                    console.log(i);
                });
            }


        }
    }
});
*/
