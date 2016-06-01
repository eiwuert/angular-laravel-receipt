/**
 * Directive of Date picker form in RB list
 */
rciSpaApp.directive('rbDatepicker', function() {
    return function(scope, element, attrs) {
        $(element).bind('mousedown', function(e) {
            e.preventDefault();
        });

        $(element).bind('click', function() {
            scope.showDatepicker = scope.showDatepicker === true ? false : true;
            if (scope.showDatepicker) {
                if (! $('.date-range-box .date-from').hasClass('.hasDatepicker')) {
                    $('.date-range-box .date-from').datepicker({
                        changeMonth: true,
                        changeYear: true,
                        dateFormat: 'yy-mm-dd',
                        yearRange: "2000:2025",
                        altField: '.temp-item-from',
                        onChangeMonthYear: function(y, m, i) {
                            var d = i.selectedDay;
                            $(this).datepicker('setDate', new Date(y, m - 1, d));
                            scope.dateFrom = $('.temp-item-from').val();
                            if (new Date(scope.dateFrom) > new Date($('.temp-item-to').val())) {
                                $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                            }

                            scope.dateFromDisplay = new Date(scope.dateFrom).toString();
                        },
                        onSelect: function() {
                            scope.dateFrom = $('.temp-item-from').val();
                            if (new Date(scope.dateFrom) > new Date($('.temp-item-to').val())) {
                                $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                            }

                            scope.dateFromDisplay = new Date(scope.dateFrom).toString();
                        }
                    });
                    $('.date-range-box .date-from').datepicker('setDate', new Date(scope.dateFrom));
                }

                if (! $('.date-range-box .date-to').hasClass('.hasDatepicker')) {
                    $('.date-range-box .date-to').datepicker({
                        changeMonth: true,
                        changeYear: true,
                        dateFormat: 'yy-mm-dd',
                        yearRange: "2000:2025",
                        altField: '.temp-item-to',
                        onChangeMonthYear: function(y, m, i) {
                            var d = i.selectedDay;
                            $(this).datepicker('setDate', new Date(y, m - 1, d));
                            scope.dateTo = $('.temp-item-to').val();
                            if (new Date(scope.dateTo) < new Date($('.temp-item-from').val())) {
                                $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                            }

                            scope.dateToDisplay = new Date(scope.dateTo).toString();
                        },
                        onSelect: function() {
                            scope.dateTo = $('.temp-item-to').val();
                            if (new Date(scope.dateTo) < new Date($('.temp-item-from').val())) {
                                $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                            }

                            scope.dateToDisplay = new Date(scope.dateTo).toString();
                        }
                    });
                    $('.date-range-box .date-to').datepicker('setDate', new Date(scope.dateTo));
                }

                $('.box-range-ok, .box-range-cancel').bind('click', function() {
                    if ($(this).hasClass('box-range-ok') && new Date(scope.dateFrom) > new Date(scope.dateTo)) {
                        $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                        return false;
                    } else {
                        scope.showDatepicker = false;
                        $('.date-range-box').removeClass('show-datepicker');
                    }
                });

                $('.date-range-box').addClass('show-datepicker');
            } else {
                $('.date-range-box').removeClass('show-datepicker');
            }
        });
    }
});

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
                    angular.forEach($scope.trips, function(v, k) {
                        if (v.StartDate) {
                            v.StartDate = new Date(v.StartDate);
                        }
                    });
                }, function(response) {
                    if (response.status !== 200) {
                        console.log(response.data.message);
                    }
                });
            }
        },
        template: '<a href="" ng-click="item.Reference = \'\'" ng-show="item.Reference">{{ item.Reference }}</a>\
            <div class="btn-group" ng-show="app == \'travel_expense\' && !item.Reference">\
                <button class="btn">Assign</button>\
                <button class="btn dropdown-toggle" data-toggle="dropdown" ng-click="getTripInPeriod(from, to, type)">\
                    <span class="caret"></span>\
                </button>\
                <ul class="dropdown-menu">\
                    <li ng-show="trips.length == 0"><a href="">Loading, please wait...</a></li>\
                    <li class="dropdown" ng-repeat="trip in trips">\
                        <a tabindex="-1" href="" ng-click="assignItemToTrip(trip)">\
                            <div class="pull-left">{{ trip.Reference }}</div>\
                            <div class="pull-right">{{ trip.StartDate | onlyDate }}</div>\
                            <div class="clearfix"></div>\
                        </a>\
                    </li>\
                </ul>\
            </div>',
        link: function(scope, element, attrs) {
            scope.$watch('item.CategoryID', function(n, o, scp) {
                if (n > 0 && n != o && scp.app == 'travel_expense' && !scope.item.Reference) {
                    element.tooltip('show');
                }
            });
        }
    }
});

rciSpaApp.directive('rciCalendarInline', function($timeout) {
    return {
        restrict: 'A',
        scope: {item: '=ngModel'},
        link: function(scope, element, attrs) {
            element.datepicker({
                dateFormat: 'yy-mm-dd',
                defaultDate: scope.item,
                changeMonth: true,
                changeYear: true,
                yearRange: "2000:2025",
                onChangeMonthYear: function(y, m, i) {
                    $timeout(function(){
                        scope.item = y + '-' + ('0' + m).slice(-2) + '-' + '01';
                    });
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
                $timeout(function() {
                    jQuery('#receipt-detail-wrapper').css('display', 'block');
                    jQuery('#top-header').addClass('hide').removeClass('show');
                    jQuery('#sidebar-right').addClass('hide').removeClass('show');
                    jQuery('#receiptbox-wrapper').css('display', 'none');
                    scope.$emit('LOAD_RECEIPT_DETAIL', attrObj.ReceiptID, attrObj.ItemID);
                });
            });
        }
    }
});
