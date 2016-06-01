rciSpaApp.directive('showMoreItem', function ($compile) {
    return {
        restrict: "E",
        replace: true,
        controller: function($scope) {
        },
        template: '<div ng-include="\'views/_show-more-item.html?20140918\'"></div>'
    };
});

rciSpaApp.directive('showMoreReceipt', function ($compile) {
    return {
        restrict: "E",
        replace: true,
        controller: function($scope) {
        },
        template: '<div ng-include="\'views/_show-more-receipt.html?20140918\'"></div>'
    };
});
