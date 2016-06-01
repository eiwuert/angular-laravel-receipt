/**
 * Loading Indicator
 * 
 * @author Maikel Daloo
 * @date 12th March 2013
 * 
 * Creates a new module and intercepts all ajax requests.
 * Every time a request is sent, we display the loading message and increment
 * the enable_counter variable. Then when requests complete (whether success or error)
 * we increment the disable_counter variable and we only hide the loading message
 * when the enable/disable counters are equal.
 * 
 * @example
 * All that is required to get this working is to inject this module into your main
 * module. E.g.
 *     var app = angular.module('my-app', ['LoadingIndicator']);
 * Then the script will look for the element specified in the LoadingIndicatorHandler object
 * and show/hide it.
 */
var module = angular.module('LoadingIndicator', ['LocalStorageModule']);

module.config(['$httpProvider', '$locationProvider', function($httpProvider, $locationProvider) {
    var interceptor = ['$q', 'LoadingIndicatorHandler', 'localStorageService', '$location', function($q, LoadingIndicatorHandler, localStorageService, $location) {
        return function(promise) {
            LoadingIndicatorHandler.enable();
            return promise.then(
                function( response ) {
                    LoadingIndicatorHandler.disable();

                    return response;
                },
                function( response ) {
                    LoadingIndicatorHandler.disable();

                    // Authentication is failed, should redirect to home page with login form ready
                    if (response.status === 401) {
                        if (localStorageService.isSupported()) {
							var user = localStorageService.get('userData');
							if (user) {
								alert('Someone else logged in to this account from another device. You are forced to sign out now.');
								localStorageService.remove('userData');
                                window.onbeforeunload = function() {}
                                window.document.location.reload();
							}
                        }

                        if ($location.path() != '/' && $location.path() != '/profile/reset-password') {
                            $location.url("/#login");
                            window.document.location.reload();
                        }
                    }

                    // Reject the reponse so that angular isn't waiting for a response.
                    return $q.reject( response );
                }
            );
        };
    }];
    
    $httpProvider.responseInterceptors.push(interceptor);
}]);

/**
 * LoadingIndicatorHandler object to show a loading animation while we load the next page or wait
 * for a request to finish.
 */
module.factory('LoadingIndicatorHandler', function()
{
    // The element we want to show/hide.
    var $element = $('#loading-indicators'); // Change to #loading-indicator to work as normal
    
    return {
        // Counters to keep track of how many requests are sent and to know
        // when to hide the loading element.
        enable_count: 0,
        disable_count: 0,
        
        /**
         * Fade the blocker in to block the screen.
         *
         * @return {void}
         */
        enable: function() {
            this.enable_count++;
            
            if ( $element.length ) {
                // Generate a random number to show advertising. All images in index.html#loading-indicator
                var totalAd = $element.find('img').length;
                var eleShow = 1;
                if (totalAd) {
                    eleShow = Math.floor(Math.random() * totalAd) + 1;
                }

                $element.find('img').removeClass('show').addClass('hide');
                $element.find('#advert-' + eleShow).removeClass('hide').addClass('show');
                $element.show();
            }
        },
        
        /**
         * Fade the blocker out to unblock the screen.
         *
         * @return {void}
         */
        disable: function() {
            this.disable_count++;
            
            if ( this.enable_count == this.disable_count ) {
                if ( $element.length ) $element.hide();
            }
        }
    }
});