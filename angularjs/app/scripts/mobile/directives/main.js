rciSpaApp.directive('tooltip', function() {
    return {
        restrict: 'A',
        link: function(scope, element, attrs) {
            $(element).mouseenter(function(){
                if(checkOverflow(this)){
                    element.attr('title', attrs.tooltip)
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

rciSpaApp.directive('showPage', function() {
    return {
        scope: {
            layout: '@',
            activeClass: '@',
            selectedItem: '@',
            pageName: '@'
        },
        controller: function($scope, $rootScope) {
            $scope.triggerOpenPage = function(page) {
                switch (page) {
                    case 'receiptbox-wrapper':
                        truncateTextInRB();
                        break;
                    case 'personal-expense-wrapper':
//                        truncatePETableText('pe')
                        break;
                    case 'trip-list-wrapper':
//                        truncateTripListTableText();
                        break;
                    case 'report-list-wrapper':
//                        truncateReportListTableText();
                        break;
                }
            }
        },
        link: function(scope, element, attrs) {
            element.bind('click', function(e) {
                $('#rb-back-to-app').removeClass('show').addClass('hide');
                //jQuery('#nav-container li a').removeClass('green');
                //$('#nav-container li a').removeClass('aqua');

                scope.selectedItem = scope.selectedItem ? scope.selectedItem : this;
                if (scope.activeClass) {
                    jQuery(scope.selectedItem).addClass(scope.activeClass);
                } else {
                    //jQuery(scope.selectedItem).addClass('aqua');
                }

                jQuery('.page-app').hide();
                jQuery('#ngview-wrapper').hide();
                jQuery('#' + attrs.showPage).show();

                if (attrs.showPage == 'receiptbox-wrapper') {
                    //jQuery('#menu-receiptbox').addClass('green');
                }

                if (attrs.showPage == '404-wrapper') {
                    jQuery('#app-name-placeholder').html(scope.pageName);
                    jQuery('#container').addClass('landing-wrapper');
                    jQuery('#sidebar-right').removeClass('show').addClass('hide');
                } else {
                    if (scope.layout == 'landing') {
                        jQuery('#container').addClass('landing-wrapper');
                        jQuery('#top-header').removeClass('show').addClass('hide');
                    } else {
                        jQuery('#container').removeClass('landing-wrapper');
                        jQuery('#top-header').removeClass('hide').addClass('show');
                        jQuery('#sidebar-right').removeClass('hide').addClass('show');
                    }
                }
                scope.triggerOpenPage(attrs.showPage);
            });
        }
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
        $('body, #btn-close-register-form').bind('click', function() {
            $('#user-register-modal').fadeOut();
        });
        $(element).bind('click', function(e) {
            e.stopPropagation();
            $('#user-register-modal').css('display', 'block');
            $('#user-login-modal').css('display', 'none');
            $('#user-forgot-password-modal').css('display', 'none');
        });
        $('#user-register-modal').bind('click', function(e) {
            e.stopPropagation();
        });
    }
});

//Display the login modal
rciSpaApp.directive('showLoginForm', function(localStorageService) {
    return function(scope, element, attrs) {
        $('body, #btn-close-login-form').bind('click', function() {
            $('#user-login-modal').fadeOut();
        });

        var lastLoginEmail = '';
        if (localStorageService.isSupported()) {
            lastLoginEmail = localStorageService.cookie.get('lastLoginEmail');
        }

        $(element).bind('click', function(e) {
            e.stopPropagation();
            $('#user-login-modal').css('display', 'block');
            $('#user-login-modal #inputLoginEmail').val(lastLoginEmail).focus();
            $('#user-login-modal #inputLoginEmail').trigger('input');
            $('#user-register-modal').css('display', 'none');
            $('#user-forgot-password-modal').css('display', 'none');
        });
        $('#user-login-modal').bind('click', function(e) {
            e.stopPropagation();
        });
    }
});

//Display the forgot password modal
rciSpaApp.directive('showForgotPasswordForm', function() {
    return function(scope, element, attrs) {
        $('body, #btn-close-forgot-form').bind('click', function() {
            $('#user-forgot-password-modal').fadeOut();
        });
        $(element).bind('click', function(e) {
            e.stopPropagation();
            $('#user-forgot-password-modal').css('display', 'block');
            $('#user-login-modal').css('display', 'none');
            $('#user-register-modal').css('display', 'none');
        });
        $('#user-forgot-password-modal').bind('click', function(e) {
            e.stopPropagation();
        });
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
                                '<div class="float-left"><span class="utmaltergothic" ng-class="{\'white\':poster.textColor==\'white\'}">{{poster.app}}</span></div>' +
                                '<div class="float-right"><i class="app-icon {{poster.iconClass}}"></i></div>' +
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

