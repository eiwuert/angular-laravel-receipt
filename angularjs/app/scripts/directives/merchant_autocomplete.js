"use strict";
angular.module('rciSpaApp.MerchantAutocomplete', []).directive('uiMerchantAutocomplete', function($timeout, Restangular, $rootScope) {
    return function(scope, iElement, iAttrs) {
        //Merchant suggestion list
        $rootScope.merchantAC = {};
        var nameList = [];

        Restangular.one('merchants').getList().then(function(merchants) {
            $rootScope.merchantAC = merchants;
            angular.forEach($rootScope.merchantAC, function(mc, k){
                nameList.push(mc.Name + (mc.Address? ' - ' + mc.Address : ''));
            })
            iElement.autocomplete({
                minLength: 1,
                source: function(req, responseFn) {
                    var re = $.ui.autocomplete.escapeRegex(req.term);
                    var matcher = new RegExp( "^" + re, "i" );
                    var a = $.grep( nameList, function(item,index){
                        return matcher.test(item);
                    });
                    responseFn( a );
                },
                select: function(event, ui) {
                    $timeout(function() {
                        iElement.trigger('input');
                        var val        = ui.item.value;
                        var scorePos   = val.indexOf(" - ");
                        var valName    = (scorePos>=0)? val.substring(0, scorePos) : val;
                        var valAddress = (scorePos>=0)? val.substring(scorePos + 3) : null;

                        for (var i=0; i<$rootScope.merchantAC.length; i++) {
                            if ($rootScope.merchantAC[i].Name == valName && $rootScope.merchantAC[i].Address == valAddress) {
                                $rootScope.$broadcast('RD_CHANGE_MERCHANT', $rootScope.merchantAC[i]);
                                break;
                            }
                        }
                    });
                }
            });
        }, function(response) {
            console.log(response);
        });
}
});