/**
 * Directive of dropdown App menu for item in RB listing page
 */
rciSpaApp.directive('itemDropdownApp', function($timeout){
    return {
        scope: {
            appMenu:"=appMenu",
            item:"=currItem"
        },
        restrict: 'E',
        replace: true,
        require: '^RBCtrl',
        controller: function($scope){
            $scope.slectedApp = '';
            $scope.bindSelectedApp = function(app){
                if ($scope.item.CategoryApp == app.App.MachineName) {
                    $scope.slectedApp = app.App.AbbrName;
                }
            }
            $scope.updateCategoryMenu = function(appMName, appAName){
                $scope.item.CategoryApp = appMName;
                $scope.item.Reference = '';
                $scope.slectedApp = appAName;
                $scope.$parent.loadCategory($scope.item);
            }
        },
        template: '<div class="btn-group">\
                <button class="btn">{{slectedApp}}&nbsp;</button>\
                <button class="btn dropdown-toggle" data-toggle="dropdown">\
                    <span class="caret"></span>\
                </button>\
                <ul class="dropdown-menu limit-width" role="menu" aria-labelledby="dropdownMenu">\
                    <li><a tabindex="-1" href="" ng-repeat="app in appMenu" ng-click="updateCategoryMenu(app.App.MachineName, app.App.AbbrName);" ng-init="bindSelectedApp(app)">{{app.App.AbbrName}}</a></li>\
                </ul>\
            </div>'
    }
});

/**
 * Directive of dropdown Category menu for item in RB listing page
 */
rciSpaApp.directive('itemDropdownCat', function($timeout){
    return {
        scope: {
            catMenu:"=catMenu",
            item:"=currItem"
        },
        restrict: 'E',
        replace: true,
        require:'^RBCtrl',
        controller: function($scope){
            $scope.slectedCat = '';
            if ($scope.item.CategoryApp){
                $scope.$parent.loadCategory($scope.item);
            }
            $scope.bindSelectedCat = function(cat){
                if ($scope.item.CategoryID == cat.CategoryID) {
                    $scope.slectedCat = cat.Name;
                }
            }
            $scope.performSelectCat = function(cat){
                $scope.slectedCat = cat.Name;
                if ($scope.item.CategoryID != cat.CategoryID) {
                    $scope.item.CategoryID = cat.CategoryID;
                    $scope.item.CategorizeStatus = 2;

                    if ($scope.item.CategoryApp != 'travel_expense') {
                        $scope.$parent.updateItemCategory($scope.item);
                    }
                }
            }
        },
        template: '<div class="btn-group">\
                <button class="btn" ng-model="slectedCat">{{slectedCat}}&nbsp;</button>\
                <button class="btn dropdown-toggle" data-toggle="dropdown">\
                    <span class="caret"></span>\
                </button>\
                <ul class="dropdown-menu limit-width" role="menu" aria-labelledby="dropdownMenu">\
                    <li class="dropdown-submenu" ng-class="{\'no-submenu\': catGrandpa.Childs.length==0 }" ng-repeat="catGrandpa in catMenu">\
                        <a tabindex="-1" href="" ng-click="performSelectCat(catGrandpa)" ng-init="bindSelectedCat(catGrandpa)">{{catGrandpa.Name}}</a>\
                        <ul class="dropdown-menu">\
                            <li class="dropdown-submenu pull-left" ng-class="{\'no-submenu\': catParent.Childs.length==0 }" ng-repeat="catParent in catGrandpa.Childs">\
                                <a tabindex="-1" href="" ng-click="performSelectCat(catParent)" ng-init="bindSelectedCat(catParent)">{{catParent.Name}}</a>\
                                <ul class="dropdown-menu">\
                                    <li><a tabindex="-1" href=""  ng-repeat="catChild in catParent.Childs" ng-click="performSelectCat(catChild)" ng-init="bindSelectedCat(catChild)">{{catChild.Name}}</a></li>\
                                </ul>\
                            </li>\
                        </ul>\
                    </li>\
                </ul>\
            </div>'
    }
});

/**
 * Directive of dropdown trip list for item in RB listing page
 */
rciSpaApp.directive('tripDropdown', function($timeout, Restangular){
    return {
        scope: {
            item: '=ngModel',
            from: '=',
            to: '=',
            type: '=',
            app: '=',
            screen: '@'
        },
        restrict: 'E',
        replace: false,
        controller: function($scope){
            $scope.trips = [];
            $scope.noTrip = false;

            $scope.assignItemToTrip = function(trip) {
                $scope.item.Reference = trip.Reference;
                $scope.item.TripID = trip.TripID;
                $scope.$parent.$parent.$parent.$parent.userChangedContent = true;

                if ($scope.screen != 'receipt-detail') {
                    $scope.$parent.updateItemCategory($scope.item);
                }
            }

            $scope.getTripInPeriod = function(from, to, type) {
                var params = {
                    from: from,
                    to: to,
                    type: type,
                    dropdown: true
                }

                Restangular.one('trips').getList('', params).then(function(response) {
                    $scope.trips = response;
                    if ($scope.trips.length) {
                        angular.forEach($scope.trips, function(v, k) {
                            if (v.StartDate) {
                                v.StartDate = new Date(v.StartDate);
                            }
                        });
                    } else {
                        $scope.noTrip = true;
                    }

                }, function(response) {
                    if (response.status !== 200) {
                        console.log(response.data.message);
                    }
                });
            }
        },
        template: '<a href="" ng-click="item.Reference = \'\'" ng-show="item.Reference">{{ item.Reference }}</a>\
            <div class="btn-group" style="position: inherit" ng-show="app == \'travel_expense\' && !item.Reference">\
                <button class="btn">Trip</button>\
                <button class="btn dropdown-toggle" id="rd-trip-list-toggle" data-toggle="dropdown" ng-click="getTripInPeriod(from, to, type)">\
                    <span class="caret"></span>\
                </button>\
                <ul id="rd-trip-category" class="dropdown-menu list-trip-name rd-trip-category">\
                    <li ng-show="trips.length == 0 && ! noTrip"><a href="">Loading, please wait...</a></li>\
                    <li ng-show="trips.length == 0 &&noTrip"><div style="padding-left: 20px">Could not find any trips</div></li>\
                    <li class="dropdown" ng-repeat="trip in trips">\
                        <a tabindex="-1" href="" ng-click="assignItemToTrip(trip)" style="padding-left: 5px;">\
                            <div class="pull-left text-ellipsis" style="width: 100px">{{ trip.Reference }}</div>\
                            <div class="pull-left text-ellipsis has-tooltip" style="width: 110px" data-toggle="tooltip" data-placement="right" title="{{ trip.Name }}">{{ trip.Name }}</div>\
                            <div class="clearfix"></div>\
                        </a>\
                    </li>\
                </ul>\
            </div>',
      link: function (scope, element, attrs) {
        scope.$watch('item.CategoryID', function (n, o, scp) {
          //if (n > 0 && n != o && scp.app == 'travel_expense' && !scope.item.Reference) {
          if (n > 0 && n != o && scp.app == 'travel_expense') {
            if (scope.item.IsJoined == 0 || typeof scope.item.ItemID == "undefined") {
            }
          }
        });

        element.bind('click', function(){

          var rdTripList = $('.rd-trip-category');

          //remove left right position of trip list popup
          rdTripList.removeAttr('style');

          //Set height of trip list popup
          $('.list-trip-name').css('max-height', $('#rd-items-table-wrapper .table-scroll').height() + 10);

          //get offset of trip list popup button to right line
          var tripListRightOffset = $(window).width() - $("#rd-items-table .period_col").offset().left;

          //if offset > trip list width
          if(tripListRightOffset > rdTripList.width()){
            rdTripList.css('right', tripListRightOffset - 250);
          }else{
            rdTripList.css('left', tripListRightOffset + 50);
          }
        });
      }
    }
});

rciSpaApp.directive('openRd', function($timeout, $rootScope) {
    return {
        restrict: 'A',
        link: function(scope, element, attrs) {
            element.bind('click', function(e) {
                jQuery('#loading-indicator').css('display', 'block');
                var attrObj = scope.$eval(attrs.openRd);
                //attrObj = JSON.stringify(attrObj);
                $timeout(function() {
                    $rootScope.inAppScreen = "RECEIPT_DETAIL";
                    jQuery('#rb-receipt-list .app-table-child tbody').removeClass('clicked');
                    jQuery(element).parents('tbody').addClass('clicked');

                    jQuery('#receipt-detail-wrapper').css('display', 'block');
                    jQuery('#top-header').addClass('hide').removeClass('show');
                    jQuery('#sidebar-right').addClass('hide').removeClass('show');

                    if (scope.openFromApp) {
                        if (scope.openFromApp == 'personal_expense') {
                            var wrapper = 'personal-expense-wrapper';
                            scope.$emit('LOAD_RECEIPT_DETAIL', attrObj.ReceiptID, attrObj.ItemID, wrapper, attrObj.VerifyStatus, scope.categoryInfo);
                        }
                        else if (scope.openFromApp == 'education_expense') {
                            var wrapper = 'education-expense-wrapper';
                            scope.$emit('LOAD_RECEIPT_DETAIL', attrObj.ReceiptID, attrObj.ItemID, wrapper, attrObj.VerifyStatus, scope.categoryInfo);
                        }
                        else if (scope.openFromApp == 'business_expense') {
                            var wrapper = 'business-expense-wrapper';
                            scope.$emit('LOAD_RECEIPT_DETAIL', attrObj.ReceiptID, attrObj.ItemID, wrapper, attrObj.VerifyStatus, scope.categoryInfo);
                        }
                        else if (scope.openFromApp == 'personal_assets') {
                            var wrapper = 'personal-assets-wrapper';
                            scope.$emit('LOAD_RECEIPT_DETAIL', attrObj.ReceiptID, attrObj.ItemID, wrapper, attrObj.VerifyStatus, scope.categoryInfo);
                        }
                        else if (scope.openFromApp == 'business_assets') {
                            var wrapper = 'business-assets-wrapper';
                            scope.$emit('LOAD_RECEIPT_DETAIL', attrObj.ReceiptID, attrObj.ItemID, wrapper, attrObj.VerifyStatus, scope.categoryInfo);
                        }
                        else if (scope.openFromApp == 'travel_expense' && typeof(scope.tripInfo) !== 'undefined') {
                            var wrapper = 'trip-detail-wrapper';
                            if (attrObj.ReceiptID == 0) {
                                attrObj.ItemID = 0;
                                attrObj.VerifyStatus = 0;
                            }

                            scope.$emit('LOAD_RECEIPT_DETAIL', attrObj.ReceiptID, attrObj.ItemID, wrapper, attrObj.VerifyStatus, scope.categoryInfo, scope.tripInfo);
                        }

                        delete scope.openFromApp;
                    } else {
                        if (angular.isDefined(attrObj.OpenFrom)) {
                            jQuery('#' + attrObj.OpenFrom).css('display', 'none');
                        }
                        scope.$emit('LOAD_RECEIPT_DETAIL', attrObj.ReceiptID, attrObj.ItemID, attrObj.OpenFrom, attrObj.VerifyStatus);
                    }

                });

              $rootScope.$apply();
            });
        }
    }
});

rciSpaApp.directive('outResetFilter', function($timeout) {
    return {
        restrict: 'A',
        link: function(scope, element, attrs) {
            $('body').bind('click', function() {
                scope.tmpFilterType = angular.copy(scope.filterType);
                scope.tmpAllDate = angular.copy(scope.allDate);
                scope.tmpDateFrom = angular.copy(scope.dateFrom);
                scope.tmpDateTo = angular.copy(scope.dateTo);
                scope.filtering = false;

                $timeout(function() {
                    if (! $('.filter-submit').hasClass('filtering')) {
                        return false;
                    }

                    $('.date-range-box').removeClass('show-datepicker');
                    $('.date-wrapper select').val('').unbind('mousedown');
                });
            });

            $('.filterdate, #trip-filter, #report-filter').bind('click', function(e) {
                e.stopPropagation();
            });
        }
    }
});
