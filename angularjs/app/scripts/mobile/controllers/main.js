'use strict';
rciSpaApp.controller('HomeCtrl', function($scope, $rootScope, $location, Restangular, $timeout, localStorageService){
    $timeout(function(){
        centerLoginBox();
    })
    $(window).resize(function(){
        centerLoginBox();
    })

    $scope.registerUserObj = {};

    $scope.registerUser = function() {
        jQuery('#loading-indicator').css('display', 'block');
        Restangular.all('users/register').post($scope.registerUserObj).then(function(registerUser) {
            $location.path('/register-success');
            jQuery('#loading-indicator').css('display', 'none');
        }, function(response) {
            if (response.status === 500) {
                $('#user-register-modal .alert-error').fadeIn().html(response.data.message.join('<br>'));
            } else {
                $('#user-register-modal .alert-error').hide();
            }
            jQuery('#loading-indicator').css('display', 'none');
        });
    }

    $scope.authenticate = function() {
        jQuery('#loading-indicator').css('display', 'block');
        Restangular.all('users/auth').post($scope.auth).then(function(auth) {
            $rootScope.loadedModule = 0;
            $rootScope.loadedModulePercent = 0;
            $rootScope.showPreLoading = true;

            $rootScope.loggedInUser = auth;
            $rootScope.loggedInUser.IsLogged = 1;

            // Store user information to local storage
            if (localStorageService.isSupported()) {
                localStorageService.clearAll();
                localStorageService.add('userData', angular.toJson(auth));
                localStorageService.cookie.add('lastLoginEmail', $rootScope.loggedInUser.Email);
            }

            $rootScope.loadedModule++;
            $location.path('/receiptbox');
            jQuery('#loading-indicator').css('display', 'none');
        }, function(response) {
            if (response.status === 500) {
                $('#user-login-modal .alert-error').fadeIn().html(response.data.message);
            } else {
                $('#user-login-modal .alert-error').hide();
            }
            jQuery('#loading-indicator').css('display', 'none');
        });
    }

    $scope.resetPassword = function(email) {
        jQuery('#loading-indicator').css('display', 'block');
        Restangular.all('users/request-password').post({Email: email}).then(function(response) {
            $scope.responseStatus = 204;
            $scope.responseMessage = 'Further instructions have been sent to your e-mail address.';
            jQuery('#loading-indicator').css('display', 'none');
        }, function(response) {
            if (response.status === 500) {
                $scope.responseStatus = 500;
                $scope.responseMessage = response.data.message.join('<br>');
            }
            jQuery('#loading-indicator').css('display', 'none');
        });
    }
});

function centerLoginBox() {
    var h = $('#section1 .wrapper-default').height();
    var wh = window.innerHeight;
    var pt = 0.5*(wh - h);
    $('#section1 .wrapper-default').css('padding-top', pt + 'px');
}
$(html).css('overflow','scroll');