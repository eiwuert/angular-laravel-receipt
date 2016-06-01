'use strict';

rciSpaApp.controller('HomeCtrl', function($scope, $rootScope, $location, Restangular,  openExchange, $timeout, localStorageService){

    $scope.registerUserObj = {CurrencyCode: 'USD'};
    $scope.currencies = openExchange.getCurrenciesWithAbbrName();
    $scope.registerUserObj.CountryName = ($scope.registerUserObj.CountryName)?$scope.registerUserObj.CountryName:'US';
    //$scope.countries = openExchange.getCountries();
    var tz = jstz.determine();
    $scope.registerUserObj.Timezone = tz.name();

    /*$scope.getGeoCurrency = function() {
        Restangular.one('users').customGET('geo-currency').then(function(response) {
            $scope.registerUserObj.CurrencyCode = response.currencyCode;
        }, function(response) {
            console.log(response.data.message);
        });
    }*/

    //Call this method when this controller is being loaded
    //$scope.getGeoCurrency();

    $scope.successActivated = function() {
        //window.document.location.reload();
        $location.url('/receiptbox');
    }

    $scope.backHomePage = function() {
        //window.document.location.reload();
        $location.url('/');
    }

    $scope.alreadyActivated = function() {

        if($rootScope.loggedInUser.IsLogged != 1) {
            var w = $(window).width();
            var h = $(window).height();
            var postionLogin = 63;
            if ($(window).width() <= 592){
                postionLogin = 28;
            } else {
                postionLogin = 63;
            }
            var x = 100;
            var y = w - $('#user-login-modal').width() - postionLogin;
            $timeout(function() {
                $('#user-login-modal').css({
                    'position':'fixed',
                    'top': x,
                    'left': y,
                    'display': 'block'
                });
            });
        }
        $location.url('/');
    }
    $scope.resetPrefix = function(){
        $timeout(function(){
            $location.path('/');
        });
    }
    $scope.backToSignupHome = function() {
        if($rootScope.loggedInUser.IsLogged != 1) {
            $location.url('/');
        }

        $timeout(function () {
            $('#user-login-modal').hide();
            $('body,html').animate({
                scrollTop: $('.sign-up-form').position().top -100
            }, 800);
        }, 500);
    }

    $scope.openTermsConditions = function(openTerms) {
//        if($rootScope.loggedInUser.IsLogged){
//            $location.path('/dashboard');
//            $timeout(function(){
//                $('#db_terms_condition').click();
//            },500);
//        }else
//        {
            $location.path('/terms-privacy');
            if(openTerms == true) {
                $timeout(function () {
                    $("li#db_terms_condition").click();
                    $("li#db_terms_condition").addClass('active');
                }, 200);

            } else {
                $timeout(function () {
                    $("li#db_privacy_policy").click();
                    $("li#db_privacy_policy").addClass('active');
                }, 200);

            }
//        }

    }

    $scope.registerUser = function() {
        //jQuery('#loading-indicator').css('display', 'block');
        $('#full-screen-opacity').show();
        $('#loading-signup').show();

        Restangular.all('users/register').post($scope.registerUserObj).then(function(registerUser) {
            $('body,html').animate({
                scrollTop: 0
            }, 10);
            var w = $(window).width();
            var h = $(window).height();
            var x = h/2 - $('#user-register-success-form').height()/2;
            var y = w/2 - $('#user-register-success-form').width()/2;
            $('#user-register-success-form').css({
                'display':'block',
                'position':'fixed',
                'top': x,
                'left': y
            });
            //jQuery('#loading-indicator').css('display', 'none');
            $('#loading-signup').hide();

            //reset form if register success
            $scope.registerUserObj = {CurrencyCode: 'USD'};
            $scope.currencies = openExchange.getCurrenciesWithAbbrName();
            $scope.registerUserObj.CountryName = ($scope.registerUserObj.CountryName)?$scope.registerUserObj.CountryName:'US';
            //$scope.countries = openExchange.getCountries();
            var tz = jstz.determine();
            $scope.registerUserObj.Timezone = tz.name();
            $scope.registerUserObj.FirstName = '';
            $scope.registerUserObj.LastName = '';
            $scope.registerUserObj.Email = '';
            $scope.registerUserObj.Password = '';
            $scope.registerUserObj.PasswordConfirm = '';
            $('#field_terms').click();

            //hide all errors before
            $('#frm-user-register-modal .alert-error').hide();
        }, function(response) {
            if (response.status === 500) {
                $('#frm-user-register-modal .alert-error').fadeIn().html(response.data.message.join('<br>'));
            } else {
                $('#frm-user-register-modal .alert-error').hide();
            }
            //jQuery('#loading-indicator').css('display', 'none');
            $('#full-screen-opacity').hide();
            $('#loading-signup').hide();
        });
    }

    $scope.authenticate = function() {
        jQuery('#loading-indicator').css('display', 'none');
        $scope.auth.Password = $('#inputLoginPassword').val();
        Restangular.all('users/auth').post($scope.auth).then(function(auth) {
            jQuery('#one-time-loading-indicator').show();
            //Small trick to make browser ask for password saving by submit a legal hidden form
            var hiddenForm = jQuery('#ifrUpload').contents();
            hiddenForm.find('#ifrNameField').val($scope.auth.Email);
            hiddenForm.find('#ifrPasswordField').val($scope.auth.Password);
            hiddenForm.find('#ifrSubmitBtn').click();

            $rootScope.loadedModule = 0;
            $rootScope.loadedModulePercent = 10 + '%';
            $rootScope.showPreLoading = true;

            $rootScope.loggedInUser = auth;
            $rootScope.loggedInUser.IsLogged = 1;
            $rootScope.loggedInUser.FirstUserName = angular.copy($rootScope.loggedInUser.FirstName);
            $rootScope.loggedInUser.LastUserName = angular.copy($rootScope.loggedInUser.LastName);

            // Store user information to local storage
            if (localStorageService.isSupported()) {
                localStorageService.clearAll();
                localStorageService.add('userData', angular.toJson(auth));

                $scope.auth.KeepLogin = $('#user-login-modal #remember-me').prop('checked');
                if (!$scope.auth.KeepLogin) {
                    var loginExpired = (new Date().getTime()) + 7200*1000; //+ 3600*2;
                    localStorageService.add('userLoginExpired', loginExpired);
                }

                //hide form login
                $('#full-screen').hide();
                $("#user-login-modal").hide();

            }
            //$rootScope.loadedModule++;
            $location.path('/dashboard');
            jQuery('#loading-indicator, #user-login-modal').css('display', 'none');
        }, function(response) {
            if (response.status === 500) {
                $('#user-login-modal .alert-error').fadeIn().html(response.data.message);
                $('#full-screen').show();
            } else {
                $('#user-login-modal .alert-error').hide();
            }
            jQuery('#loading-indicator').css('display', 'none');
        });
    }
    $scope.backtoDashboard = function(){
        $location.path('/dashboard');
        $('#menu-dashboard').click();
    }
    $scope.resetPassword = function(email) {
        //jQuery('#loading-indicator').css('display', 'block');
        jQuery('img#loading').css({'-webkit-animation':'rotation 1.0s infinite linear'},
            {'-moz-animation':'rotation 1.0s infinite linear'},
            {'-o-animation':'rotation 1.0s infinite linear'},
            {'animation':'rotation 1.0s infinite linear'});
        Restangular.all('users/request-password').post({Email: email}).then(function(response) {
            $scope.responseStatus = 204;
            $scope.responseMessage = 'Further instructions have been sent to your e-mail address.';
            //jQuery('#loading-indicator').css('display', 'none');
            jQuery('img#loading').css({'-webkit-animation':'none'},
            {'-moz-animation':'none'},
            {'-o-animation':'none'},
            {'animation':'none'});
        }, function(response) {
            if (response.status === 500) {
                $scope.responseStatus = 500;
                $scope.responseMessage = response.data.message.join('<br>');
            }
            //jQuery('#loading-indicator').css('display', 'none');
            jQuery('img#loading').css({'-webkit-animation':'none'},
            {'-moz-animation':'none'},
            {'-o-animation':'none'},
            {'animation':'none'});
        });
    }


    /* Trigger modal popup for slide show */
    $scope.triggerPosterPopup = function(index){
        var $myModal = $('#posterModal').modal({show: false});
        var $myCarousel = $('#posterCarousel').carousel({'interval': false});

        $myModal.modal('show');
        $myCarousel.carousel(index);
    }

    $scope.openPagePopup = function(page) {
        var modal = $('#pageModal').modal({show: false});
        $('#pageModal .modal-body').load('views/privacy.html', function() {
            $('#pageModal .modal-header h3').text('ReceiptClub Privacy Policy');
            modal.modal('show');
        });
    }

    $('#enter_head_home').bind('click', function() {
        $timeout(function() {
            $('.app-rb .app-table-child-wrapper').resizeHeight();
            resizeMenu();
        });
    });

    $('#go-to-app').bind('click', function() {
        $timeout(function() {
            $('.app-rb .app-table-child-wrapper').resizeHeight();
            resizeMenu();
        });
    });

});

rciSpaApp.controller('ContactCtrl', function($scope, $timeout, Restangular){
    setBackgroundCover();
    $scope.sender = {};
    $scope.isSubmit = false;
    $scope.isError= false;
    $scope.sendMessage = function(sender) {
        Restangular.all('contact').post(sender).then(function (response) {
            $scope.isSubmit = true;
            $scope.isError = false;
            //Reset form
            $scope.sender = {};

            $timeout(function () {
                // Loadind done here - Show message for 2 more seconds.
                $timeout(function () {
                    $scope.isSubmit = false;
                }, 2000);
            }, 2000);
        },
        function(response) {
            if (response.status === 500) {
                $scope.isError = true;
                //$scope.responseStatus = 500;
                $scope.responseMessage = response.data.message[0];
                //console.log($scope.responseMessage);
                $timeout(function () {
                    // Loadind done here - Show message for 2 more seconds.
                    $timeout(function () {
                        $scope.isError = false;
                    }, 5000);
                }, 5000);
            }
            //console.log(response.data.message);
        });
    }
});

rciSpaApp.controller('ProfileCtrl', function($scope, $rootScope, $timeout, openExchange, Restangular, $routeParams, localStorageService, $location) {
    setBackgroundCover();
    $scope.isError = false;
    $scope.action = 'profile';
    $scope.Birthdate = '';
    $scope.responseMessage = '';
    $scope.CurrencyCode = '';
    //setting input date of birth
    var d = new Date();
    var curr_year = d.getFullYear();
    $('#inputDateBirth').datepicker({
        changeMonth: true,
        changeYear: true,
        dateFormat: 'yy-mm-dd',
        yearRange:'1900:'+ curr_year,
        maxDate: '-1d',
        //altField: '.temp-item-from',
        onChangeMonthYear: function(y, m, i) {
        },
        onSelect: function(selectedDate) {
            $scope.Birthdate = selectedDate;
            $rootScope.loggedInUser.Birthdate = $scope.Birthdate;
        }
    });
    if (typeof $routeParams.action !== 'undefined') {
        $scope.action = $routeParams.action;
    }

    $scope.responseStatus = 204;

    $scope.successDeleteAccount = function() {
        $location.path('/logout');
        $('#full-screen-opacity-dashboard').hide();
        $('#loading-signup-dashboard').hide();
    }

    if ($routeParams.action == 'activate') {
        $('#full-screen-opacity').show();
        Restangular.one('users').customGET('check-activate', {token: $routeParams.token, userid: $routeParams.userid}).then(function(resData) {
            $scope.responseStatus = 204;
        }, function(response) {
            if (response.status === 500) {
                $scope.responseStatus = 500;
                $scope.responseMessage = response.data.message;
            } else if (response.status == 301) {
                $scope.responseStatus = 301;
            } else if (response.status == 404) {
                $scope.responseStatus = 404;
            }
        });
    } else if ($routeParams.action == 'reset-password') {
        $('#full-screen-opacity').show();
        Restangular.one('users').customGET('check-reset-password', {token: $routeParams.token}).then(function(resData) {
            $scope.responseStatus = 204;
        }, function(response) {
            if (response.status === 500) {
                $scope.responseStatus = 500;
                $scope.responseMessage = response.data.message;
            } else if (response.status == 404) {
                $scope.responseStatus = 404;
            }
        });
    } else if ($routeParams.action == 'change-email') {
        Restangular.one('users').customGET('change-email', {token: $routeParams.token}).then(function(resData) {
            $scope.responseStatus = 204;
            $rootScope.loggedInUser = resData;
            $rootScope.loggedInUser.IsLogged = 1;
            $rootScope.loggedInUser.FirstUserName = angular.copy($rootScope.loggedInUser.FirstName);
            $rootScope.loggedInUser.LastUserName = angular.copy($rootScope.loggedInUser.LastName);
        }, function(response) {
            if (response.status === 500) {
                $scope.responseStatus = 500;
                $scope.responseMessage = response.data.message;
            }
        });
    } else if ($routeParams.action == 'delete-account') {
        $('#full-screen-opacity').show();
    }

    $scope.userProfile = $rootScope.loggedInUser;
    $scope.userProfile.CountryName = ($scope.userProfile.CountryName)?$scope.userProfile.CountryName:'US';
    $scope.userProfile.CountryNameCompany = ($scope.userProfile.CountryNameCompany)?$scope.userProfile.CountryNameCompany:'US';
    $scope.userProfile.Password = '';
    $scope.userProfile.Birthdate = $rootScope.loggedInUser.Birthdate;
    $scope.applications = [
      {name:'PersonalExpense'},
      {name:'TravelExpense'},
    ];
    $scope.relationships = [
      {name:'Spouse'},
    ];
    $scope.isSubmit = false;

    $scope.resendActivation = function() {
        $scope.isSuccessMail = false;
        $('#loading-signup').show();
        $('#full-screen-opacity').css({ 'z-index':'500' });
        Restangular.one('users').customPUT({token: $routeParams.token, userid: $routeParams.userid}, 'resend-activation').then(function(resData) {
            $('#loading-signup').hide();
            $('#full-screen-opacity').css({ 'z-index':'50' });
            $scope.isSuccessMail = true;
            $scope.responseMessage = "Resend activation successfully!";
            $timeout(function() {
                $location.path('/');
            }, 1000);
        }, function(response) {
            $('#loading-signup').hide();
            $('#full-screen-opacity').css({ 'z-index':'50' });
            if (response.status === 500 || response.status === 401) {
                $scope.responseMessage = response.data.message;
            }
        });
    }

    $scope.setNewPass = function(NewPassword, NewPasswordConfirm) {
        $scope.isError = false;
        $scope.isSuccess = false;
        $('#loading-signup').show();
        $('#full-screen-opacity').css({ 'z-index':'500' });
        Restangular.one('users').customPUT({token: $routeParams.token, password: NewPassword, passwordconfirm: NewPasswordConfirm}, 'set-new-password').then(function(resData) {
            $rootScope.loggedInUser = resData;
            $rootScope.loggedInUser.IsResetPassword = true;
            $rootScope.loggedInUser.IsLogged = 1;
            $rootScope.loggedInUser.AutoArchive = $rootScope.loggedInUser.AutoArchive.toString();
            $rootScope.loggedInUser.FirstUserName = angular.copy($rootScope.loggedInUser.FirstName);
            $rootScope.loggedInUser.LastUserName = angular.copy($rootScope.loggedInUser.LastName);

            // Store user information to local storage
            if (localStorageService.isSupported()) {
                localStorageService.clearAll();
                localStorageService.add('userData', angular.toJson(resData));
            }
            $scope.isSuccess = true;
            $scope.responseMessage = 'Reset password successfully!';
            $('input#enter-password-reset').hide();
            $('input#submit-password-reset').hide();
            $('input#enter-password-confirm-reset').hide();

            $timeout(function() {
                $location.path('/receiptbox');
            }, 1000);
            $('#loading-signup').hide();
            $('#full-screen-opacity').css({ 'z-index':'50' });
        }, function(response) {
            $('#loading-signup').hide();
            $('#full-screen-opacity').css({ 'z-index':'50' });
            if (response.status === 500) {
                $scope.responseMessage = response.data.message;
                $scope.isError = true;
            }
        });
    }
    $scope.updateCurrency = function(CurrencyCode) {
        $scope.isError = false;
        $scope.isSuccess = false;
        $('#loading-signup').show();
        $('#full-screen-opacity').css({ 'z-index':'500' });
        Restangular.one('users').customPUT({token: $routeParams.token, userid: $routeParams.userid, currencty: CurrencyCode}, 'update-currency').then(function(resData) {
            Restangular.one('users').customGET('activate', {token: $routeParams.token}).then(function(resData) {
                $rootScope.loggedInUser = resData;
                $rootScope.loggedInUser.IsLogged = 1;
                $rootScope.loggedInUser.FirstUserName = angular.copy($rootScope.loggedInUser.FirstName);
                $rootScope.loggedInUser.LastUserName = angular.copy($rootScope.loggedInUser.LastName);

                $rootScope.loggedInUser.AutoArchive = $rootScope.loggedInUser.AutoArchive.toString();
                // Store user information to local storage
                if (localStorageService.isSupported()) {
                    localStorageService.clearAll();
                    localStorageService.add('userData', angular.toJson(resData));
                }
                $scope.isSuccess = true;
                $scope.responseMessage = 'Update currency and activate successfully!';
                $timeout(function() {
                    $('#user-activate-fail-modal2').hide();
                    $('#user-activate-fail-modal').show();
                }, 2000);
                $('#loading-signup').hide();
                $('#full-screen-opacity').css({ 'z-index':'50' });
            }, function(response) {
                $('#loading-signup').hide();
                $('#full-screen-opacity').css({ 'z-index':'50' });
                if (response.status === 500) {
                    $scope.responseMessage = response.data.message;
                }
            });
        }, function(response) {
            $('#loading-signup').hide();
            $('#full-screen-opacity').css({ 'z-index':'50' });
            if (response.status === 500 || response.status === 401) {
                //$scope.responseStatus = response.status;
                $scope.responseMessage = response.data.message;
                $scope.isError = true;
            }
        });

    }

    $scope.updateProfile = function(user) {
        user.Birthdate = $rootScope.loggedInUser.Birthdate;
        Restangular.one('users').customPUT(user).then(function(resData) {
            $rootScope.loggedInUser.FirstUserName = angular.copy(user.FirstName);
            $rootScope.loggedInUser.LastUserName = angular.copy(user.LastName);

            // Store user information to local storage
            if (localStorageService.isSupported()) {
                localStorageService.clearAll();
                localStorageService.add('userData', angular.toJson($rootScope.loggedInUser));
            }
            $scope.isSubmit = true;

            // Loading done here - Show message for 2 more seconds.
            $timeout(function() {
                $scope.isSubmit = false;
            }, 2000);
        }, function(response) {
            if (response.status === 500 || response.status === 401) {
                $scope.responseMessage = response.data.message[0];
                $scope.isError = true;

                // Loading done here - Show message for 2 more seconds.
                $timeout(function() {
                    $scope.isError = false;
                }, 2000);
            }
        });

    }
    //$scope.countries = openExchange.getCountries();
    //console.log($scope.countries);
});

rciSpaApp.controller('SettingsCtrl', ['$scope', '$rootScope', '$timeout' ,'Restangular', 'openExchange', 'localStorageService', '$location','vcRecaptchaService', function($scope, $rootScope, $timeout, Restangular, openExchange, localStorageService, $location, vcRecaptchaService) {

    setBackgroundCover();
    $scope.timezones = [];
    $scope.isSubmit = false;
    $scope.registerUserObj = {CurrencyCode: 'USD'};
    $scope.recaptcha_challenge_field = $('#recaptcha_challenge_field').val();
    $scope.recaptcha_response_field = $('#recaptcha_response_field').val();
    //$scope.userSetting
    $scope.feedbackmessage = "";

    $scope.business_expense_value = true;
    $scope.business_assets_value = true;
    $scope.travel_expense_value = true;
    $scope.quick_expense_value = false;
    $scope.invoice_expense_value = true;
    $scope.inventory_spend_value = true;

    $scope.receipt_box_value = true;
    $scope.bank_accounts_value = true;
    $scope.credit_cards_value = true;

    Restangular.one('users').customGET('timezone-list').then(function(response) {
        $scope.timezones = response;
    });
    $scope.saveSetting = function(data) {
        data.Timezone = $('select[name="Timezone"]').val();
        Restangular.one('users').customPUT(data, 'settings').then(function(response) {
//            console.log(data);
            // Store user information to local storage
            if (localStorageService.isSupported()) {

                localStorageService.add('userData', angular.toJson($rootScope.loggedInUser));
            }
            //$location.path('/');
            $scope.isSubmit = true;

            // Loadind done here - Show message for 2 more seconds.
            $timeout(function() {
                $scope.isSubmit = false;
            }, 2000);
        });
    };

    $scope.deleteAccount = function(data) {
        $scope.isError = false;
        var sendObject = {
            Password : $rootScope.loggedInUser.SettingPassWord,
            PasswordConfirm : $rootScope.loggedInUser.RetypeSettingPassWord
        };

        Restangular.one('users').customPUT(sendObject,'verify-password',{},{"AUTH_TOKEN": $rootScope.loggedInUser.Token}).then(function(response) {
            $.showMessageBoxPopup({
                content: '<p style="font-size: 16px;">Are you sure you want to delete your ReceiptClub account?</p>',
                type: 'confirm',
                boxTitle: 'DELETE ACCOUNT',
                boxTitleClass: '',
                onYesAction: function() {
                    $timeout(function() {
                        $('#full-screen-opacity-dashboard').show();
                        $('#loading-signup-dashboard').show();

                        Restangular.one('users').remove({"token": $rootScope.loggedInUser.Token, "FeedbackMessage" : $scope.feedbackmessage}).then(function(response) {
                            $location.path('/profile/delete-account/' + response.token);
                            $timeout(function() {
                                $('#full-screen-opacity').show();
                                $('#loading-signup').hide();
                            }, 1000);
                        }, function(response) {
                                $('#full-screen-opacity').hide();
                                $('#loading-signup').hide();
                            console.log(response.status);
                        });
                    });
                }
            });
        }, function(response) {
            $scope.isError = true;
            $scope.responseMessage = response.data['message'];
            $timeout(function() {
                $scope.isError = false;
            }, 3000);
        });
    };

    $scope.currencies = openExchange.getCurrenciesWithAbbrName();
    $scope.currencyName = '';
    $.each($scope.currencies, function( index, value ) {
        if($rootScope.loggedInUser.CurrencyCode == index) {
            $scope.currencyName = value;
        }
    });

    $scope.autoArchives = openExchange.getAutoArchives();
}]);
