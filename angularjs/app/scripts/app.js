'use strict';

/**
 * Variable contain all ReceiptID for next and previous receipt
 * @type {Array}
 */
var rbIdsArr = [];
var intervalIdArr = [];
var indexDbServer;


var rciSpaApp = angular.module('rciSpaApp', ['restangular', 'rciSpaApp.utilityFilters', 'ui.date', 'ngSanitize',
    'rciSpaApp.MerchantAutocomplete', 'pasvaz.bindonce', 'LoadingIndicator', 'LocalStorageModule', 'rciSpaApp.InlineEdit', 'vcRecaptcha'])
    .config(function ($routeProvider, $locationProvider, RestangularProvider) {
        RestangularProvider.setBaseUrl(API_URL);
        $locationProvider.html5Mode(false).hashPrefix('!');
        $routeProvider
            .when('/', {
                currentPath: '/',
                layout: 'landing',
                requireLogin: false
            })
            .when('/dashboard', {
                currentPath: '/dashboard',
                layout: 'default',
                requireLogin: true
            }).when('/personal-expense', {
                currentPath: '/personal-expense',
                layout: 'default',
                requireLogin: true
            }).when('/business-expense', {
                currentPath: '/business-expense',
                layout: 'default',
                requireLogin: true
            }).when('/education-expense', {
                currentPath: '/education-expense',
                layout: 'default',
                requireLogin: true
            }).when('/personal-assets', {
                currentPath: '/personal-assets',
                layout: 'default',
                requireLogin: true
            }).when('/business-assets', {
                currentPath: '/business-assets',
                layout: 'default',
                requireLogin: true
            })
            .when('/receiptbox', {
                currentPath: '/receiptbox',
                layout: 'default',
                requireLogin: true
            })
          .when('/receiptbox/receipt-detail', {
                currentPath: '/receiptbox/receipt-detail',
                layout: 'default',
                requireLogin: true
            })
            .when('/travel-expense', {
              currentPath: '/travel-expense',
              layout: 'default',
              requireLogin: true
            })
            .when('/travel-expense/trip-list', {
                currentPath: '/travel-expense',
                layout: 'default',
                requireLogin: true
            })
            .when('/travel-expense/report-list', {
                currentPath: '/travel-expense',
                layout: 'default',
                requireLogin: true
            })
            .when('/trip-detail/:tripId', {
                currentPath: '/travel-expense',
                layout: 'default',
                requireLogin: true
            })
            .when('/travel-approver', {
                currentPath: '/travel-approver',
                layout: 'default',
                requireLogin: true
            })
            .when('/travel-approver/report-list', {
                currentPath: '/travel-approver',
                layout: 'default',
                requireLogin: true
            })
            .when('/travel-approver/:reportId', {
                currentPath: '/travel-approver',
                layout: 'default',
                requireLogin: true
            })
            .when('/create-report', {
                currentPath: '/travel-expense',
                layout: 'default',
                requireLogin: true
            })
            .when('/report-detail/:reportId', {
                currentPath: '/travel-expense',
                layout: 'default',
                requireLogin: true
            })
            .when('/profile', {
                templateUrl: 'views/profile.html',
                controller: 'ProfileCtrl',
                currentPath: '/profile',
                layout: 'landing',
                requireLogin: true
            })
            .when('/profile/:action/:token/:userid', {
                templateUrl: 'views/profile.html',
                controller: 'ProfileCtrl',
                currentPath: '/profile',
                layout: 'landing',
                requireLogin: false
            })
            .when('/profile/:action/:token', {
                templateUrl: 'views/profile.html',
                controller: 'ProfileCtrl',
                currentPath: '/profile',
                layout: 'landing',
                requireLogin: false
            })
            .when('/contact', {
                templateUrl: 'views/contact.html',
                controller: 'ContactCtrl',
                currentPath: '/contact',
                layout: 'landing',
                requireLogin: false
            })
            .when('/register-success', {
                templateUrl: 'views/register-success.html',
                controller: 'HomeCtrl',
                currentPath: '/register-success',
                layout: 'landing',
                requireLogin: false
            })
            .when('/personal-expense/analytic', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/personal-expense/analytic/:dateFrom', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/personal-expense/analytic/:dateFrom/:dateTo', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/business-expense/analytic/:dateFrom', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/business-expense/analytic/:dateFrom/:dateTo', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/business-expense/analytic', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/education-expense/analytic/:dateFrom', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/education-expense/analytic/:dateFrom/:dateTo', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/personal-assets/analytic', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/personal-assets/analytic/:dateFrom', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/personal-assets/analytic/:dateFrom/:dateTo', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/business-assets/analytic', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/business-assets/analytic/:dateFrom', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/business-assets/analytic/:dateFrom/:dateTo', {
                templateUrl: 'views/analytic.html',
                controller: 'AnalyticCtrl',
                currentPath: '/analytic',
                layout: 'landing',
                requireLogin: true
            })
            .when('/settings', {
                templateUrl: 'views/settings.html',
                controller: 'SettingsCtrl',
                currentPath: '/settings',
                layout: 'landing',
                requireLogin: true
            })
            .when('/terms-privacy', {
                templateUrl: 'views/terms-privacy.html',
                controller: 'HomeCtrl',
                currentPath: '/terms-privacy',
                layout: 'landing',
                requireLogin: false
            })
            .when('/maintenance', {
                templateUrl: 'views/maintenance.html',
                controller: 'HomeCtrl',
                currentPath: '/maintenance',
                layout: 'landing',
                requireLogin: false
            })
            .otherwise({
                currentPath: '/',
                layout: 'landing',
                requireLogin: false
            });
    }).run(function ($rootScope, $location, Restangular, $timeout, localStorageService, $http,restAngularService, AwsS3Sdk, MaintainService, $templateCache) {
        timezoneJS.timezone.zoneFileBasePath = 'components/timezone-js/src/tzdata-latest';
        timezoneJS.timezone.init();

        db.open({
            server: 'rc-app',
            version: 2,
            schema: {
                userReceipts: {
                    key: { keyPath: 'id', autoIncrement: true},
                    indexes: {
                        ReceiptID: {},
                        IsOpened: {},
                        VerifyStatus: {},
                        IsArchived: {},
                        ReceiptType: {},
                        PurchaseTime: {},
                        CreatedTime: {},
                        DigitalTotal: {}
                    }
                }
            }
        }).done(function (s) {
            indexDbServer = s;
            $rootScope.$broadcast('DB_INIT_SUCCESS');
        });

        /**
         * Options for ui-date directive
         *
         * @type {{changeYear: boolean, changeMonth: boolean, yearRange: string, dateFormat: string}}
         */
        $rootScope.expensePeriodOptions = {
            changeYear: true,
            changeMonth: true,
            yearRange: '1970:+5',
            dateFormat: 'd-M-yy'
        };

        $rootScope.loggedInUser = {IsLogged: 0};

        //Check if user is kept to logging in or not (clear storage in case of login expired)
        if (localStorageService.isSupported()) {
            var localUserData = localStorageService.get('userData');
            var loginExpired = parseInt(localStorageService.get('userLoginExpired'));
            var now = new Date().getTime();

            if (!loginExpired || (loginExpired && now < loginExpired)) {
                if (localUserData) {
                    $rootScope.loggedInUser = JSON.parse(localUserData);
                    $rootScope.loggedInUser.IsLogged = 1;
                }
            } else if (loginExpired && now > loginExpired) {
                localStorageService.clearAll();
            }
        }

        $rootScope.ocrUploaderUrl = OCR_URL;

        $rootScope.receiptStatus = [
            {VerifyStatus: 0, Name: 'New Receipt'},
            {VerifyStatus: 1, Name: 'Awaiting Validation'},
            {VerifyStatus: 2, Name: 'Validated'},
            {VerifyStatus: 3, Name: 'Unrecognized'},
            {VerifyStatus: 4, Name: 'Modified'}
        ];

        //Use a default app to auto-initialize app for items which is not categorized or new item lines
        $rootScope.defaultApp = 'personal_expense';
        $rootScope.defaultAppAbbr = 'PE';

        $rootScope.$on('$routeChangeSuccess', function (event, currentRoute, previousRoute) {
            // Generate launching application soon
            if ('/404' === currentRoute.currentPath) {
                var tmpArr = $location.path().substr(1).split('-');
                tmpArr[0] = tmpArr[0].charAt(0).toUpperCase() + tmpArr[0].slice(1);

                // App name without dash
                if (tmpArr.length > 1) {
                    tmpArr[1] = tmpArr[1].charAt(0).toUpperCase() + tmpArr[1].slice(1);
                }

                $rootScope.applicationName = tmpArr.join('');
            }

            $rootScope.activePath = currentRoute.currentPath;
            $rootScope.layout = currentRoute.layout;

            jQuery('.page-app').hide();

            jQuery('#ngview-wrapper').show();

            if (typeof currentRoute.currentPath !== 'undefined') {

                if (currentRoute.currentPath == '/') {
                    currentRoute.layout = 'landing';
                    jQuery('#sidebar-right').removeClass('show').addClass('hide');
                }

                if (currentRoute.currentPath == '/receiptbox') {
                    jQuery('#menu-receiptbox').addClass('green');
                }
                if(currentRoute.currentPath == '/travel-expense'){
                    if($location.path() == '/travel-expense/trip-list'){
                        $('#trip-list-wrapper').show();
                        $rootScope.menuActiveColor('trip-list-wrapper');
                    }else if($location.path() == '/travel-expense/report-list'){
                        $('#report-list-wrapper').show();
                        $rootScope.menuActiveColor('report-list-wrapper');
                    }
                } else if (currentRoute.currentPath == '/travel-approver'){
                    if($location.path() == '/travel-approver/report-list'){
                        $('#approver-list-wrapper').show();
                        $rootScope.menuActiveColor('temp-travel-approver');
                    }
                }
                else if (currentRoute.currentPath == '/receiptbox/receipt-detail'){
                  if($location.path() == '/receiptbox/receipt-detail'){
                    $('#receipt-detail-wrapper').show();
                  }
                }else {
                  jQuery('#' + currentRoute.currentPath.substr(1) + '-wrapper').show();
                  $rootScope.menuActiveColor(currentRoute.currentPath.substr(1) + '-wrapper');
                }
            }

            if (currentRoute.layout == 'landing') {
                jQuery('#container').addClass('landing-wrapper');
                jQuery('#top-header').removeClass('show').addClass('hide');
                jQuery('#sidebar-right').removeClass('show').addClass('hide');
            } else {
                jQuery('#container').removeClass('landing-wrapper');
                jQuery('#sidebar-right').removeClass('hide').addClass('show');
                jQuery('#top-header').removeClass('hide').addClass('show');
            }
            setBackgroundCover();
        });
        $rootScope.maintenanceTime = 0;

        $rootScope.menuActiveColor = function(id){
          $('#nav-container li a').removeClass('green').removeClass('aqua');
          switch (id) {
            case 'receiptbox-wrapper':
              $('#menu-receiptbox').addClass('green');
              $rootScope.inAppScreen = 'RECEIPT_BOX';
              break;

            case 'personal-expense-wrapper':
              $('#menu-personal-expense').addClass('aqua');
              $rootScope.inAppScreen = "PERSONAL_EXPENSE";
              break;

            case 'education-expense-wrapper':
              $('#menu-education-expense').addClass('aqua');
              $rootScope.inAppScreen = 'EDUCATION_EXPENSE';
              break;
            case 'business-expense-wrapper':
              $('#menu-business-expense').addClass('aqua');
              $rootScope.inAppScreen = 'BUSINESS_EXPENSE';
              break;
            case 'personal-assets-wrapper':
              $('#menu-personal-assets').addClass('aqua');
              $rootScope.inAppScreen = 'PERSONAL_ASSETS';
              break;
            case 'business-assets-wrapper':
              $('#menu-business-assets').addClass('aqua');
              $rootScope.inAppScreen = 'BUSSINESS_ASSETS';
              break;
            case 'trip-list-wrapper':
              $rootScope.inAppScreen = 'TRIP_LIST';
              $('#menu-travel-expense').addClass('aqua');
                  break;
            case 'report-list-wrapper':
              $('#menu-travel-expense').addClass('aqua');
              $rootScope.inAppScreen = 'REPORT_LIST';
              break;
            case 'approver-list-wrapper':
              $('#menu-travel-approver').addClass('aqua');
              $rootScope.inAppScreen = 'APPROVER_LIST';
              break;
            case 'temp-travel-approver':
                $('#menu-travel-approver').addClass('aqua');
                $rootScope.inAppScreen = 'REPORT_LIST';
                break;
            case 'dashboard-wrapper':
              $('#dashboard-wrapper').addClass('aqua');
              $rootScope.inAppScreen = 'DASHBOARD';
              break;
          }
        }

        //Get maintenance status
        MaintainService.getMaintenanceStatus();

        // register listener to watch for route changes
        // this event will fire every time the route changes
        $rootScope.$on("$routeChangeStart", function (event, nextRoute, currentRoute) {
            if (angular.isDefined(nextRoute.$$route) && nextRoute.$$route.currentPath != '/') {
                jQuery('#home-page-wrapper').hide();
            } else {
                jQuery('#home-page-wrapper').show();
            }

            //ask the service to check if the user is in fact logged in
            if (nextRoute.requireLogin && !$rootScope.loggedInUser.Token) {
                // no logged user, we should be going to the home page with login form ready
                $location.url("/#login");
            }
        });

        //var socket;

        //.. watch change on location.path()
        $rootScope.location = $location;
        $rootScope.$watch('location.path()', function (path) {

            //.. path to logout?
            if (path == '/logout' || ((path == '/maintenance') && ($rootScope.loggedInUser.IsLogged == 1))) {
                Restangular.one('users/logout').get({}, {
                    "AUTH_TOKEN": $rootScope.loggedInUser.Token
                }).then(function (auth) {
                    $rootScope.firstLoadComplete = false;

                    // Clear all interval ID
                    for (var intervalId in intervalIdArr) {
                        //console.debug('clearInterval(%d)', intervalIdArr[intervalId]);
                        window.clearInterval(intervalIdArr[intervalId]);
                    }

                    if (localStorageService.isSupported()) {
                        localStorageService.clearAll();
                    }

                    $rootScope.loggedInUser = {};

                    jQuery('#nav-container li a').removeClass('green');
                    jQuery('#nav-container li a').removeClass('aqua');

                    $location.path('/');
                }, function (response) {
                    if (response.status !== 200) {
                        console.log(response.data.message);
                    }
                });
                $location.path('/');
            }
        });

    $rootScope.changeRoute = function (route) {
        $location.path(route);
    }

    $rootScope.lastGetNewRc = (new Date()).getTime();
        $rootScope.closeUploadArea = function () {
          $('.message-upload-custom .totalFileUpload').hide();
          $('.wrap-progess-upload-area').fadeOut();
        }

        $rootScope.inAppScreen = '';

        $rootScope.openUploadArea = function () {
          $('.message-upload-custom .totalFileUpload').show();
          $('.wrap-progess-upload-area').fadeIn();
        }

        $rootScope.$watch('inAppScreen', function(newValue, oldValue){
          if(newValue == "RECEIPT_BOX" && $rootScope.keepUploadArea){
            $rootScope.openUploadArea();
          }else{
            $rootScope.closeUploadArea();
          }
        });

    $rootScope.$watch('loggedInUser', function (newValue, oldValue, scope) {
      if (newValue.hasOwnProperty('UserID')) {
        jQuery('#one-time-loading-indicator').show();
        Restangular.setDefaultHeaders({
          'AUTH-TOKEN': $rootScope.loggedInUser.Token
        });

        //test web socket
        $rootScope.sock = new SockJS(PUSH_SERVER_URL + '/service');
        $rootScope.sock.onopen = function (e) {
          //on Open
        };
        $rootScope.sock.onmessage = function (e) {
          console.log(e);
          //on message
          var data = JSON.parse(e.data);

          if (data.event == 'handshake') {
            $rootScope.socketIdentifier = data.identifier;

            //create parameter for PushGun/sock-session
            var parameterSockSession = {
              authenticationToken: $rootScope.loggedInUser.Token,
              connectionIdentifier: $rootScope.socketIdentifier
            };

            //call API sock-session
            var sockSessionUrl = PUSH_SERVER_URL + '/sock-session';
            var request = new XMLHttpRequest();
            request.open("POST", sockSessionUrl);
            request.onreadystatechange = function () {
              //Callback.
            };
            request.setRequestHeader("Content-Type", "application/json");
            request.send(JSON.stringify(parameterSockSession));
          }

          if (data.event == 'file-uploaded') {
            $rootScope.fileUploadCallback(data.name, 'success');
          } else if (data.event == 'file-failed') {

            $rootScope.fileUploadCallback(data.name, 'failed');

          } else if (data.event == 'file-processed') {
            $rootScope.receiveReceiptsCallback(data.content);

          }
          if (data.event == 'reportSubmit') {
            var res = data.content.split("$:");
            $rootScope.$broadcast('REPORT_EVENT_REALTIME', 'submit', res[0], res[1]);
          }

          if (data.event == 'reportApproved') {
            var res = data.content.split("$:");
            $rootScope.$broadcast('REPORT_EVENT_REALTIME', 'approve', res[0], res[1]);
          }

          if (data.event == 'reportRejected') {
            var res = data.content.split("$:");
            $rootScope.$broadcast('REPORT_EVENT_REALTIME', 'reject', res[0], res[1]);
          }
        };
        $rootScope.sock.onclose = function (e) {
          //on Close
        };

        Restangular.one('categories').getList().then(function (response) {
          $rootScope.categories = response;
          angular.forEach($rootScope.categories, function (catV, catK) {
            catV.preparedCatList = prepareCategoryTree(catV.Categories);
          });
          $rootScope.loadedModule++;
        });

        if(!$rootScope.$$phase) $rootScope.$apply();
          $rootScope.$emit('GET_LIST_RECEIPTS_ID');

      }
    });

        $rootScope.receiveReceiptsCallback = function(receiptData){

            /*
            * Call RB to add new receipt to receipt list
            */

          var newreceiptData = jQuery.parseJSON(receiptData);
            $rootScope.$broadcast('RB_RECEIVE_RECEIPTS',  newreceiptData);
        }

        //Create function to call receipt box each file uploaded
        $rootScope.fileUploadCallback = function(name, status){
            /*
            * param name Name of file images upload.
            * param status To check status file upload.
            * */

             $rootScope.$broadcast('FILE_UPLOAD_STATUS', name, status);
        }

        // Listener for show one-time pre-loading process box
        $rootScope.loadedModule = 0;
        $rootScope.loadedModulePercent = '10%';
        $rootScope.showPreLoading = true;
        $rootScope.isLoadingAnalytic = false;

        $rootScope.$watch('isLoadingAnalytic', function (newValue, oldValue, rootScope) {
            if(newValue) {
                $("body").addClass("loading");
            }
        });

        /*
         * Check if OCR Server is running
         */
        $rootScope.ocrStatus = false;
        $rootScope.checkOCRStatus = function(callback){
            Restangular.one('receipt-images').customGET('server-status').then(function (response) {
                var rs = (!!+response.message) ? true : false;

                if (typeof callback != 'undefined' && callback) {
                    callback(rs);
                }
            });
        };

        $rootScope.checkOCRStatus(function(res){
            $rootScope.ocrStatus = res;
        });

        jQuery('#one-time-loading-indicator').hide();

        /**
         * Watch the $loadedModule to show/hide one-time pre-loading box
         */

        $rootScope.$watch("loadedModule", function (newValue, oldValue) {
            if (newValue == oldValue || !$rootScope.loggedInUser.UserID || $rootScope.loadedModulePercent == '100%') return;

            /**
             * 1. getPEList
             * 2. getBEList
             * 3. getEEList
             * 4. getPAList
             * 5. getBAList
             * 6. loadCategories
             * 7. getTripList
             * 8. getReportList
             * 9. getReceiptList
             * 10. UPDATE_PE_LOCAL_STORAGE (?)
             * 11. load currencies list (?)
             */
            var totalModule = 10; //6
            jQuery('#one-time-loading-indicator').show();
            var percent = Math.ceil($rootScope.loadedModule / totalModule * 100);
            var basePercent = 10;
            $timeout(function(){
                $rootScope.loadedModulePercent = ((percent < basePercent) ? basePercent : percent) + '%';
            });

            if ($rootScope.loadedModule >= totalModule && !$rootScope.firstLoadComplete) {
                $rootScope.loadedModulePercent = '100%';
                //$rootScope.showPreLoading = false;
                $timeout(function () {
                    jQuery('#one-time-loading-indicator').hide();
                    //Resize the menu to be fitted
                    resizeMenu();

                    var notActive = $('#nav-container a.not-active');
                    notActive.on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var isShowing = $(this).data('isShowing');
                        if (isShowing !== 'true') {
                            notActive.not(this).each(function () {
                                $(this).data('isShowing', 'false');
                            }).tooltip('hide');
                            $(this).data('isShowing', 'true');
                            $(this).tooltip('show');
                        } else {
                            notActive.each(function () {
                                $(this).data('isShowing', 'false');
                            });
                            $(this).tooltip('hide');
                        }
                    }).tooltip({
                        animation: true,
                        trigger: 'manual'
                    });
                    $('body').on('click', function () {
                        $('#nav-container a.not-active').tooltip('hide');
                        $('#nav-container a.not-active').each(function () {
                            $(this).data('isShowing', 'false');
                        });
                    });
                    jQuery('#rb-receipt-list td.col-typ span, #rb-receipt-list td.col-app span, #rb-receipt-list td.col-exp span').tooltip();

                    $rootScope.firstLoadComplete = true;

                    MaintainService.checkUpcomingMaintenance();
                }, 800);

                //Set up Aws configurations
                AwsS3Sdk.setBuckets($rootScope.loggedInUser.BucketList);
                renewAwsToken();
            }
        });

        function displaySlider(sliderId, from) {
            $(".main-slide").trigger('slideTo')
            $(".main-slide").trigger("destroy");
            $(sliderId).carouFredSel({
                circular    : false,
                infinite    : false,
                scroll      : {
                    fx          : "crossfade"
                },
                auto: {
                    timeoutDuration: 4000,
                    duration: 1000,
                    play: false
                },
                items       : {
                    visible     : 1,
                    start: 0
                },
                prev: {
                    button: '.prev-button',
                    key: 37
                },
                next: {
                    button: '.next-button',
                    key: 39
                },
                pagination  : ".slider-paging"
            });

            if (from == 'TakeTourBtn') {
                if (sliderId == '#tutorial-slider-receiptbox .main-slide') {
                    $('#showPEone').parent('p').hide();
                    $('#showPEtwo').parent('p').hide();
                } else if (sliderId == '#tutorial-slider-travelexpense .main-slide') {
                    $('#showTEone').parent('p').hide();
                }
            }
        }

        $rootScope.showRBFirstTime = true;
        $rootScope.showPEFirstTime = true;
        $rootScope.showTEFirstTime = true;
        $rootScope.showBEFirstTime = true;
        $rootScope.showEEFirstTime = true;
        $rootScope.showPAFirstTime = true;
        $rootScope.showBAFirstTime = true;

        $rootScope.showSlideGuide = function (id, from) {
            var a = '#' + id;
            var b = a + " " + ".main-slide";
            var kind = '';
            if (a == '#tutorial-slider-receiptbox') {
                kind = 'rb';
            } else if (a == '#tutorial-slider-personalexpense') {
                kind = 'pe'
            } else if (a == '#tutorial-slider-travelexpense') {
                kind = 'te';
            } else if (a == '#tutorial-slider-businessexpense') {
                kind = 'be'
            } else if (a == '#tutorial-slider-educationexpense') {
                kind = 'ee'
            } else if (a == '#tutorial-slider-personalassets') {
                kind = 'pa'
            } else if (a == '#tutorial-slider-businessassets') {
                kind = 'ba'
            }

            Restangular.one('users').customGET('check-show-guide', {kind:kind}).then(function(response) {
                var showTour = response.ShowGuide;
                if (showTour == 1) {
                    if ((kind == 'rb' && $rootScope.showRBFirstTime) || (kind == 'pe' && $rootScope.showPEFirstTime) || (kind == 'te' && $rootScope.showTEFirstTime) ||
                        (kind == 'be' && $rootScope.showBEFirstTime) || (kind == 'ee' && $rootScope.showEEFirstTime) || (kind == 'pa' && $rootScope.showPAFirstTime) ||
                        (kind == 'ba' && $rootScope.showBAFirstTime)) {
                        $.showMessageBoxPopup({
                            content: '<p class="content-tour">Would you like to take a tour?</p>',
                            type: 'tour',
                            boxTitle: 'TAKE A TOUR',
                            boxTitleClass: '',
                            onYesTourAction: function() {
                                if (kind == 'rb') { $rootScope.showRBFirstTime = false; }
                                if (kind == 'pe') { $rootScope.showPEFirstTime = false; }
                                if (kind == 'te') { $rootScope.showTEFirstTime = false; }
                                if (kind == 'be') { $rootScope.showBEFirstTime = false; }
                                if (kind == 'ee') { $rootScope.showEEFirstTime = false; }
                                if (kind == 'pa') { $rootScope.showPAFirstTime = false; }
                                if (kind == 'ba') { $rootScope.showBAFirstTime = false; }

                                $(a).css({'opacity': 1, 'z-index': 100, 'display': 'table'});
                                // caroufredsel slide
                                displaySlider(b);
                                if ($('#showGuideCheckbox').is(":checked")) {
                                    // Update database
                                    $timeout(function() {
                                        Restangular.one('users').customPUT({kind:kind,value:0}, 'update-show-guide').then(function(resData) {

                                        }, function(response) {
                                            console.log(response.status);
                                        });
                                    })
                                }
                            },
                            onNotNowAction: function() {
                                if (kind == 'rb') { $rootScope.showRBFirstTime = false; }
                                if (kind == 'pe') { $rootScope.showPEFirstTime = false; }
                                if (kind == 'te') { $rootScope.showTEFirstTime = false; }
                                if (kind == 'be') { $rootScope.showBEFirstTime = false; }
                                if (kind == 'ee') { $rootScope.showEEFirstTime = false; }
                                if (kind == 'pa') { $rootScope.showPAFirstTime = false; }
                                if (kind == 'ba') { $rootScope.showBAFirstTime = false; }

                                $(a).css({'opacity': '0', 'z-index': '-100'});
                                if ($('#showGuideCheckbox').is(":checked")) {
                                    // Update database
                                    $timeout(function() {
                                        Restangular.one('users').customPUT({kind:kind,value:0}, 'update-show-guide').then(function(resData) {

                                        }, function(response) {
                                            console.log(response.status);
                                        });
                                    })
                                }
                            }
                        });
                        $('#showGuideCheckbox').parent().parent().parent().on('hide', function() {
                            if (kind == 'rb') { $rootScope.showRBFirstTime = false; }
                                if (kind == 'pe') { $rootScope.showPEFirstTime = false; }
                                if (kind == 'te') { $rootScope.showTEFirstTime = false; }
                                if (kind == 'be') { $rootScope.showBEFirstTime = false; }
                                if (kind == 'ee') { $rootScope.showEEFirstTime = false; }
                                if (kind == 'pa') { $rootScope.showPAFirstTime = false; }
                                if (kind == 'ba') { $rootScope.showBAFirstTime = false; }

                                $(a).css({'opacity': '0', 'z-index': '-100'});
                                if ($('#showGuideCheckbox').is(":checked")) {
                                    // Update database
                                    $timeout(function() {
                                        Restangular.one('users').customPUT({kind:kind,value:0}, 'update-show-guide').then(function(resData) {

                                        }, function(response) {
                                            console.log(response.status);
                                        });
                                    })
                                }
                        });
                    } else {
                        if (from == 'TakeTourBtn') {
                            $(a).css({'opacity': 1, 'z-index': 100, 'display': 'table'});
                            displaySlider(b, from);
                        } else {
                            $(a).css({'opacity': '0', 'z-index': '-100'});
                        }
                    }
                } else {
                    if (from == 'TakeTourBtn') {
                        $(a).css({'opacity': 1, 'z-index': 100, 'display': 'table'});
                        displaySlider(b, from);
                    } else {
                        $(a).css({'opacity': '0', 'z-index': '-100'});
                    }
                }
            }, function(response) {
                console.log(response.status);
            });

            $('.close-slide').click(function(){
                $(a).css({'opacity': '0', 'z-index': '-100'});
            })
            $('.safari .app-box').width($(window).width() - $('#container .sidebar-ad').outerWidth() - 20);
        }

        /**
         * Navigators
         */

        $rootScope.openDashboard = function(tabNav){
            $rootScope.$broadcast('OPEN_DASHBOARD', tabNav);
            $('.safari .app-box').width($(window).width() - $('#container .sidebar-ad').outerWidth() - 20);
        }
        $rootScope.openAppFromDashboard = function(app,ctravel){
            $rootScope.$broadcast('OPEN_APPFROMDASHBOARD', app,ctravel);
        }
        $rootScope.openCreateTripForm = function () {
            $rootScope.$broadcast('OPEN_CREATE_TRIP');
        }
        $rootScope.openCreateReportForm = function () {
            $rootScope.$broadcast('OPEN_CREATE_REPORT');
        }

        /**
         * Load Merchant auto complete widget
         */
        $rootScope.reloadMerchantAC = function(eleID) {
            if (!jQuery(eleID).attr('ui-merchant-autocomplete')) {
                return false;
            }
            Restangular.one('merchants').getList().then(function(merchants) {
                $rootScope.merchantAC = merchants;
                var nameList = [];
                angular.forEach($rootScope.merchantAC, function (mc, k) {
                    nameList.push(mc.Name + (mc.Address? ' - ' + mc.Address : ''));
                })
                $(eleID).autocomplete( "option", "source", nameList);
            });
        }

      /**
        * Trigger to get all receipt
        */
      $rootScope.$on('GET_LIST_RECEIPTS_ID', function (e) {
        var parameter = {
          api   : 'receipts',
          param : {
            route : 'list-receipts-id'
          }
        }

        /**
         * @service customGetApi
         * parameter
         *  @api: api to get data
         *  @param:
         *    @route: route to get
         */
        restAngularService.customGetApi(parameter).then(function(response){
          $rootScope.idReceiptsList = response;
        },
        function(response){
          console.debug(response);
        });
      });

        /**
         * Prepare ticket for AWS service usages
         */
        var renewAwsToken = function () {
            //Prepare s3 service configugration
            Restangular.all('attachments').customGET('s3-upload-ticket').then(function(response){
                AwsS3Sdk.setConfig(response);
            });
        };

        //Automatically renew the AWS ticket before token of 1hr expired
        setInterval(function(){
            renewAwsToken();
        }, 3000000);

        /**
         * Show more receipt and item
         */
        $rootScope.displayShowMore = function(itemData) {
            if (angular.isDefined(itemData.Items)) {
                jQuery('#modalBoxReceipt').modal('show');
            } else {
                jQuery('#modalBoxItem').modal('show');
            }
            $rootScope.moreBoxData = itemData;

        }
        $rootScope.quickSaveShowMore = function(itemData) {
            if (angular.isDefined(itemData.Items)) {
                //Save receipt
                Restangular.one('receipts').customPUT(itemData, 'quick-save').then(function (response) {

                }, function (response) {
                    $scope.responseMessage = response.data.message;
                    $scope.responseCode = response.status;
                });
            } else {
                //Save items
            }
        }
        var firstTimeOpenAds = true;
        /**
         * Load amazon ads for receipt detail screen
         */
        $rootScope.createRDAdBlock = function () {
            if(firstTimeOpenAds) {
                var ad = $('#amz-ad-block').children();
                $('#rd-ad-block').append(ad);
                firstTimeOpenAds = false;
            }
        };

        /**
         * Load countries list for global usage
         */
        Restangular.one('users').customGET('country-list').then(function(response) {
            $rootScope.countries = response;
        });

    /**
     * @flag Set is page reload
     * @type {boolean}
     */
    $rootScope.isPageReload = false;

    /**
     * When user refresh the browser we should set default path to receipt box
     * @param event
     */
        $rootScope.$watch('isPageReload', function(newVal, oldVal){
          if($rootScope.loggedInUser.IsLogged){
            $location.path('/receiptbox');
          }
        })

    /**
     * @event Trigger event if user reload the page
     */
        $(window).bind('beforeunload',function(){
          $rootScope.isPageReload = !$rootScope.isPageReload;
        });
    }

);

function rearrangeCategories(categories) {
    var arrangedArray = [];
    var len, lenD1, tmp;
    angular.forEach(categories, function (cat, k) {
        len = arrangedArray.length;
        tmp = cat;  //clone data to keep original data
        if (cat.Depth == 0) {
            tmp.Childs = [];
            arrangedArray.push(tmp);
        } else if (cat.Depth == 1) {
            tmp.Childs = [];
            arrangedArray[len - 1].Childs.push(tmp);
        } else if (cat.Depth == 2) {
            lenD1 = arrangedArray[len - 1].Childs.length;
            arrangedArray[len - 1].Childs[lenD1 - 1].Childs.push(tmp);
        }
    });

    return arrangedArray;
}

function prepareCategoryTree(categories) {
    var tmp, catGroup = [], len = 0, parentKey;
    //empty the tree first because it is a shared variable
    var categoryTree = [];
    angular.forEach(categories, function (cat, k) {
        tmp = angular.copy(cat);  //clone data to keep original data
        tmp.check = false;
        tmp.parentCollapse = true;
        tmp.masterCollapse = true;
        tmp.collapseDisabled = false;
        tmp.expand = false;
        tmp.CategoryName = cat.Name;
        tmp.Type = 'category';
        tmp.class = 'cat-lv' + (cat.Depth + 1);
        tmp.current = false;
        if (cat.Depth == 0) {
            //Push the cat group to category tree, each group is a branch
            if (catGroup.length > 0) {
                if (catGroup[len - 1].Depth == 1) {
                    catGroup[len - 1].collapseDisabled = true;
                }

                categoryTree.push(catGroup);
            }

            //Empty the cat group, so we will start to add a new branch
            catGroup = [];
            len = 0;
            tmp.display = true;
            tmp.parentCollapse = false;
            tmp.masterCollapse = false;
            catGroup.push(tmp);
            len++;
        } else if (cat.Depth == 1) {
            if (catGroup[len - 1].Depth == 1) {
                catGroup[len - 1].collapseDisabled = true;
            }

            tmp.parentCollapse = false;
            tmp.display = true;
            tmp.index = len;
            catGroup.push(tmp);
            len++;
        } else if (cat.Depth == 2) {
            tmp.display = true;
            tmp.index = len;
            tmp.collapseDisabled = true;
            catGroup.push(tmp);
            len++;
        }
    });

    //Add the last catGroup to array
    if (catGroup.length > 0) {
        if (catGroup[len - 1].Depth == 1) {
            catGroup[len - 1].collapseDisabled = true;
        }

        categoryTree.push(catGroup);
    }

    return categoryTree;
}
