var app = angular.module( 'rciSpaApp.InlineEdit', [] );

app.directive( 'inlineEdit', function($timeout, $sanitize, $filter) {
    return {
        restrict: 'E',
        scope: {
            value      : '=',
            disabled   : '=',
            oid        : '=',
            field      : '@',
            quickSave  : '@',
            type       : '@',
            additionClass : '@',
            maxlength  : '@',
            maxWidth   : '@',
            editStatus : '=',
            format     : '@'
        },
        template:
            '<a ng-bind="value | dynamicFilter:format" class="{{additionClass}}" tooltip="{{value}}"></a>' +
            '<input ng-model="value" class="input-inline-edit" maxlength="{{maxlength}}">' +
            '<div ng-show="editStatus==\'checking\'" class="inline-status-mask mask-check">Checking...</div>' +
            '<div ng-show="editStatus==\'saving\'" class="inline-status-mask">Saving...</div>',
        link: function ( $scope, element, attrs ) {
            // Let's get a reference to the span & input element, as we'll want to reference it.
            var spanElement = angular.element( element.children()[0] );
            var inputElement = angular.element( element.children()[1] );
            var oldValue;

            if (angular.isDefined(attrs.placeholder)) {
                inputElement.attr('placeholder', attrs.placeholder);
            }

            // This directive should have a set class so we can style it.
            element.addClass( 'edit-in-place' );

            //Set the max width in case of limiting text ellipsis
            if (angular.isDefined($scope.maxWidth)) {
                spanElement.css('max-width', $scope.maxWidth + 'px');
            }

            // Initially, we're not editing.
            $scope.editing = false;

            // ng-click handler to activate edit-in-place
            $scope.edit = function () {
                oldValue = angular.copy($scope.value) || "";
                if ($scope.disabled) {
                    return false;
                }

                $scope.editing = true;

                // We control display through a class on the directive itself. See the CSS.
                element.addClass( 'active' );

                // And we must focus the element.
                // `angular.element()` provides a chainable array, like jQuery so to access a native DOM function,
                // we have to reference the first element in the array.
                inputElement[0].focus();
            };

            $scope.updateAmount = function() {
                if ($scope.type != 'Claim' && $scope.type != 'Approve') {
                    return false;
                }

                if (!parseFloat($scope.value)) return false;
                if (parseFloat($scope.value) < 0) return false;

                $timeout(function() {
                    if ($scope.type == 'Claim') {
                        $scope.$parent.report.Claimed = 0;
                        angular.forEach($scope.$parent.report.Trips, function(trip, k) {
                            trip.Claimed = 0;
                            if (trip.Items.length) {
                                angular.forEach(trip.Items, function(item, k) {
                                    if (item.IsClaimed) {
                                        trip.Claimed += parseFloat(item.Claimed);
                                    }
                                });
                            }
                            $scope.$parent.report.Claimed += trip.Claimed;
                        });
                    } else if ($scope.type == 'Approve') {
                        $scope.$parent.report.Approved = 0;
                        angular.forEach($scope.$parent.report.Trips, function(trip, k) {
                            trip.Approved = 0;
                               if (trip.Items.length) {
                                angular.forEach(trip.Items, function(item, k) {
                                    if (item.IsApproved) {
                                       trip.Approved += parseFloat(item.Approved);
                                    }
                                });
                            }
                            $scope.$parent.report.Approved += trip.Approved;
                        });
                    }
                });
            };

            // When we leave the input, we're done editing.
            inputElement.bind( 'blur', function() {
                if ($scope.value == "") $timeout(function(){ $scope.value = oldValue; });

                //Escape html and add filter

                var needSanitize = true;
                if (angular.isDefined($scope.format)) {
                    var flt = $scope.format.split(":");
                    if (flt[0]=='number') {
                        $timeout(function(){
                            $scope.value = Math.abs(parseFloat($scope.value)) || oldValue;
                            needSanitize = false;
                        })
                    }
                }
                $timeout(function(){
                    if (needSanitize) $scope.value = $sanitize($scope.value);
                });

                //Update amount of claim/approve fields in view
                $timeout(function(){
                    $scope.updateAmount();
                    $scope.editing = false;
                    element.removeClass( 'active' );
                });

                $timeout(function(){
                    if (angular.isDefined($scope.quickSave) && oldValue != $scope.value) {
                        element.addClass('_edited');
                        if ($scope.type == 'Claim' || $scope.type == 'Approve') {
                            if ($scope.type == 'Claim') {
                                $scope.$parent.$parent.quickSaveForClaimedValue();
                            } else {
                                $scope.$parent.$parent.quickSaveForApprovedValue();
                            }

                        } else if (typeof $scope.$parent.quickSave == 'function') {
                            $scope.$parent.quickSave($scope.oid, $scope.field, $scope.value, oldValue);
                        } else if (typeof $scope.$parent.$parent.quickSave == 'function') {
                            $scope.$parent.$parent.quickSave($scope.oid, $scope.field, $scope.value, oldValue);
                        }
                    }
                }, 200);

                $('span.limit-title a').ellipsis();
                $('.trip-detail-title h4.limit-title span').html('Trip Expense for ' + $('span.limit-title a').html());

                $('#report-title a').ellipsis();
                $('.report-detail-title h4.limit-title').html('Travel Report for ' + $('#report-title a').html());
            });

            spanElement.bind('click', function() {
                $scope.edit();
            });

            //Watch to remove edit status mask when item is saved
            $scope.$watchCollection('editStatus', function(newVal, oldVal){
                if (!newVal) {
                    element.removeClass('_edited');
                }
            });
            
            if (angular.isDefined($scope.format)) {
                var flt = $scope.format.split(":");
                if (flt[0]=='number') {
                    inputElement.bind('keyup change input paste',function(e){
                        var $this = $(this);
                        var val = $this.val();
                        var valLength = val.length;
                        var maxCount = 13;

                        if($(this).val().indexOf('.')!=-1){
                            return;
                        } else if ($(this).val().indexOf('.')==-1){
                            if(valLength>maxCount){
                                $this.val($this.val().substring(0,maxCount));
                            }
                        }
                    });
                }
            }
        }
    };
});

//Almost like InlineEdit, but we use this specifically for number input
app.directive( 'numberInlineEdit', function($timeout) {
    return {
        restrict: 'E',
        scope: {
            value: '=',
            display: '=',
            disabled: '=',
            oid: '=',
            field: '@',
            quickSave: '@',
            additionClass: '@',
            maxlength: '@',
            type: '@'
        },
        template: '<a ng-bind="display" class="{{additionClass}}" tooltip="{{display}}"></a>\
            <input ng-model="value" class="input-inline-edit" ng-keydown="validateNumber($event)" ng-change="updateLeg()" maxlength="{{maxlength}}">',
        link: function ( $scope, element, attrs ) {
            // Let's get a reference to the span & input element, as we'll want to reference it.
            var spanElement = angular.element( element.children()[0] );
            var inputElement = angular.element( element.children()[1] );
            var oldValue;

            // This directive should have a set class so we can style it.
            element.addClass('edit-in-place').addClass('number');

            // Initially, we're not editing.
            $scope.editing = false;

            // ng-click handler to activate edit-in-place
            $scope.edit = function () {
                oldValue = angular.copy($scope.value);

                if ($scope.disabled) {
                    return false;
                }

                $scope.editing = true;

                // We control display through a class on the directive itself. See the CSS.
                element.addClass( 'active' );

                // And we must focus the element.
                // `angular.element()` provides a chainable array, like jQuery so to access a native DOM function,
                // we have to reference the first element in the array.
                inputElement[0].focus();
            };

            $scope.validateNumber = function($event) {
                var code = $event.keyCode ? $event.keyCode : $event.which;
                var functional = false;
                // allow key numbers, 0 to 9, Backspace, Tab, Enter, Delete, and left/right arrows
                if((code >= 48 && code <= 57) || (code >= 96 && code <= 105) ||
                    (code ==  8) || (code ==  9) || (code == 13) || (code == 46) || (code == 37) || (code == 39)) {
                        functional = true;
                }

                if (! functional) {
                    $event.preventDefault();
                    $event.stopPropagation();
                }
            };

            $scope.updateLeg = function() {
                if ($scope.type != 'leg') {
                    return false;
                }

                $scope.display = 'Leg ' + $scope.value;
                $scope.$parent.currentTrip.LegInName = '- Leg ' + $scope.value;
            }


            // When we leave the input, we're done editing.
            inputElement.bind( 'blur', function() {
                $scope.editing = false;

                if (angular.isDefined($scope.quickSave) && oldValue != $scope.value) {
                    if (typeof $scope.$parent.quickSave == 'function') {
                        $scope.$parent.quickSave($scope.oid, $scope.field, $scope.value, oldValue);
                    } else if (typeof $scope.$parent.$parent.quickSave == 'function') {
                        $scope.$parent.$parent.quickSave($scope.oid, $scope.field, $scope.value, oldValue);
                    }
                }

                element.removeClass( 'active' );
            });

            spanElement.bind('click', function() {
                $scope.edit();
            });
        }
    };
});