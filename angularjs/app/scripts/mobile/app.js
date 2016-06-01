'use strict';
// TODO: Should use angular constant for contain API URL
var ENVIRONMENT = 'production';
if (ENVIRONMENT == 'development') {
    var API_URL = 'http://192.168.1.81/rciv2/api/v1';
    var OCR_URL = 'http://192.168.1.124/rci382/receipt_processing/v2/upload.php';
} else {
    var API_URL = 'http://receiptclub.com/api/v1';
    var OCR_URL = 'http://54.214.253.116/v2/upload.php';
}

var rciSpaApp = angular.module('rciSpaApp', ['restangular', 'rciSpaApp.utilityFilters', 'ui.date',
        'pasvaz.bindonce', 'LoadingIndicator', 'LocalStorageModule', 'infinite-scroll'])
    .config(function($routeProvider, $locationProvider, RestangularProvider) {
        RestangularProvider.setBaseUrl(API_URL);
        $locationProvider.html5Mode(false).hashPrefix('!');
        $routeProvider.when('/', {
            templateUrl: 'views/mobile/home.html',
            controller: 'HomeCtrl',
            currentPath: '/',
            layout: 'landing',
            requireLogin: false
        })
        .when('/receiptbox', {
            //templateUrl: 'views/receipt-list.html',
            currentPath: '/receiptbox',
            layout: 'default',
            requireLogin: true
        })
       .otherwise({
            templateUrl: 'views/404.html',
            currentPath: '/404',
            layout: '404',
            requireLogin: false
        });
    }).run(function($rootScope, $location, Restangular, $timeout, localStorageService, $http) {
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

        if (localStorageService.isSupported()) {
            var localUserData = localStorageService.get('userData');
            if (localUserData) {
                $rootScope.loggedInUser = JSON.parse(localUserData);
                $rootScope.loggedInUser.IsLogged = 1;
            }
        }

        $rootScope.ocrUploaderUrl = OCR_URL;

        $rootScope.receiptStatus = [
            {VerifyStatus: 0, Name: 'New Receipt'},
            {VerifyStatus: 1, Name: 'Awaiting Verification'},
            {VerifyStatus: 2, Name: 'User verified'},
            {VerifyStatus: 3, Name: 'Unrecognized'}
        ]

        $rootScope.$on('$routeChangeSuccess', function (event, currentRoute, previousRoute) {
            jQuery('#nav-container a').tooltip();

            //Hide homepage when access apps
            if ('landing' != currentRoute.layout) {
                jQuery('#ngview-wrapper').hide();
            } else {
                jQuery('#ngview-wrapper').show();
            }

            // Generate launching application soon page
            if ('/404' === currentRoute.currentPath) {
                var tmpArr = $location.path().substr(1).split('-');
                tmpArr[0] = tmpArr[0].charAt(0).toUpperCase() + tmpArr[0].slice(1);

                // App name without dash
                if (tmpArr.length > 1){
                    tmpArr[1] = tmpArr[1].charAt(0).toUpperCase() + tmpArr[1].slice(1);
                }

                $rootScope.applicationName = tmpArr.join('');
            }

            $rootScope.activePath = currentRoute.currentPath;
            $rootScope.layout = currentRoute.layout;

            // Show login form by default
            if ($location.$$hash === 'login')  {
                $timeout(function() {$('#user-login-modal').css('display', 'block')}, 500);
            }

            jQuery('.page-app').hide();


            if (typeof currentRoute.currentPath !== 'undefined') {
                if (currentRoute.currentPath == '/') {
                    currentRoute.layout = 'landing';
                    jQuery('#sidebar-right').removeClass('show').addClass('hide');
                }

                if (currentRoute.currentPath == '/receiptbox') {
                    //jQuery('#menu-receiptbox').addClass('green');
                }
                jQuery('#' + currentRoute.currentPath.substr(1) + '-wrapper').show();
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

        // register listener to watch for route changes
        // this event will fire every time the route changes
        $rootScope.$on("$routeChangeStart", function (event, nextRoute, currentRoute) {
            //ask the service to check if the user is in fact logged in
            if (nextRoute.requireLogin && !$rootScope.loggedInUser.Token) {
                // no logged user, we should be going to the home page with login form ready
                $location.url("/#login");
            }
        });

        //.. watch change on location.path()
        $rootScope.location = $location;
        $rootScope.$watch('location.path()', function( path ) {
            //.. path to logout?
            if (path == '/logout') {
                Restangular.one('users/logout').get({}, {
                    "AUTH_TOKEN": $rootScope.loggedInUser.Token
                }).then(function(auth) {
					if (localStorageService.isSupported()) {
						localStorageService.clearAll();
					}

                    $rootScope.loggedInUser = {};
                    $location.path('/');
                }, function(response) {
                    if (response.status !== 200) {
                        console.log(response.data.message);
                    }
                });
                $location.path('/');
            }
        });

        $rootScope.$watch('loggedInUser', function(newValue, oldValue, scope) {
            if (newValue.hasOwnProperty('UserID')) {
                Restangular.setDefaultHeaders({
                    'AUTH_TOKEN': $rootScope.loggedInUser.Token
                });

                Restangular.one('categories').getList().then(function(response) {
                    $rootScope.categories = response;
                    angular.forEach($rootScope.categories, function(catV, catK){
                        catV.arrangedCatList = rearrangeCategories(catV.Categories);
                    });

                    $rootScope.loadedModule++;
                });
            }
        });
    }
  );

function rearrangeCategories (categories){
    var arrangedArray = [];
    var len, lenD1, tmp;
    angular.forEach(categories, function(cat, k){
        len = arrangedArray.length;
        tmp = cat;  //clone data to keep original data
        if (cat.Depth == 0 ) {
            tmp.Childs = [];
            arrangedArray.push(tmp);
        } else if (cat.Depth == 1 ) {
            tmp.Childs = [];
            arrangedArray[len-1].Childs.push(tmp);
        } else if (cat.Depth == 2 ) {
            lenD1 = arrangedArray[len-1].Childs.length;
            arrangedArray[len-1].Childs[lenD1-1].Childs.push(tmp);
        }
    });

    return arrangedArray;
}


