/**
 * This file contains all calendars / datepicker directives
 */

/**
 * Directive of Date picker form in RB list
 */
rciSpaApp.directive('rbDatepicker', function($timeout) {
    return function(scope, element, attrs) {
        var eId = attrs.elementId;
        var doQuickFilter = (attrs.quickFilter)?true:false;
        var pickerMode = attrs.pickerMode;
        if (!eId) return;

        var dropdown = $(element).parents('.dropdown-menu');

        $(element).bind('click', function() {
            $('#' + eId + ' .date-range-box').addClass('show-picker');
        });

        //QuyPV 20141119: Temporary solution for moving filters setting to manage locally in controllers
        if (eId == 'trip-filter' || eId == 'report-filter') {
            scope.tmpDateFrom = scope.filters.dateFrom;
            scope.tmpDateTo   = scope.filters.dateTo;
            scope.tmpFilterType = scope.filters.type;
        }

        if (! $('#' + eId + ' .date-range-box .date-from').hasClass('.hasDatepicker')) {
            var tmpDate = new Date(scope.tmpDateTo);
            var maxYear = tmpDate.getFullYear();

            $('#' + eId + ' .date-range-box .date-from').datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                yearRange: "2000:" + maxYear,
                altField: '.temp-item-from',
                onChangeMonthYear: function(y, m, i) {
                    var d = i.selectedDay;
                    $(this).datepicker('setDate', new Date(y, m - 1, d));
                    scope.tmpDateFrom = $('.temp-item-from').val();
                    //Take the first Day of Month only if picker mode is 'month'
                    if (pickerMode == 'month') {
                        var strFirstDay = "";
                        var strM = (m<10)?'0'+m:''+m;
                        strFirstDay += y + '-' + strM + '-' + '01';
                        scope.tmpDateFrom = strFirstDay;
                    }
                    if (new Date(scope.tmpDateFrom) > new Date($('.temp-item-to').val())) {
                        $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                    }
                },
                onSelect: function(selectedDate) {
                    scope.tmpDateFrom = selectedDate;
                    if (new Date(scope.tmpDateFrom) > new Date($('.temp-item-to').val())) {
                        $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                    }
                }
            });
            $('#' + eId + ' .date-range-box .date-from').datepicker('setDate', new Date(scope.tmpDateFrom));
        }

        if (! $('#' + eId + ' .date-range-box .date-to').hasClass('.hasDatepicker')) {
            $('#' + eId + ' .date-range-box .date-to').datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                yearRange: "2000:" + maxYear,
                altField: '.temp-item-to',
                onChangeMonthYear: function(y, m, i) {
                    var d = i.selectedDay;
                    $(this).datepicker('setDate', new Date(y, m - 1, d));
                    scope.tmpDateTo = $('.temp-item-to').val();
                    //Take the last Day of Month only if picker mode is 'month'
                    if (pickerMode == 'month') {
                        var strLastDay = "";
                        var strM = (m<10)?'0'+m:''+m;
                        var objDateTo = new Date(y, m - 1, d);
                        var lastDate = new Date(objDateTo.getFullYear(), objDateTo.getMonth() + 1, 0).getDate();
                        strLastDay += y + '-' + strM + '-' + lastDate;
                        scope.tmpDateTo = strLastDay;
                    }
                    if (new Date(scope.tmpDateTo) < new Date($('.temp-item-from').val())) {
                        $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                    }
                },
                onSelect: function(selectedDate) {
                    scope.tmpDateTo = selectedDate;
                    if (new Date(scope.tmpDateTo) < new Date($('.temp-item-from').val())) {
                        $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                    }
                }
            });
            $('#' + eId + ' .date-range-box .date-to').datepicker('setDate', new Date(scope.tmpDateTo));
        }

        $('#' + eId + ' .box-range-ok').bind('click', function() {
            if (new Date(scope.tmpDateFrom) > new Date(scope.tmpDateTo)) {
                $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
                return false;
            } else {
                scope.tmpAllDate = false;
                scope.dateFromDisplay = new Date(scope.tmpDateFrom).toString();
                scope.dateToDisplay = new Date(scope.tmpDateTo).toString();

                //Run corresponding filter function by screen
                $timeout(function() {
                    if (doQuickFilter) {
                        switch (eId) {
                            case 'rb-filter':
                                scope.filterReceipts();
                                break;
                            case 'report-filter':
                            case'trip-filter':
                                scope.setFilters({
                                    type     : scope.tmpFilterType,
                                    dateFrom : scope.tmpDateFrom,
                                    dateTo   : scope.tmpDateTo,
                                    allDate  : (scope.tmpAllDate) ? 1 : 0
                                });

                                scope.applyFilters();
                                break;
                        }
                    }
                    $('.date-range-box').removeClass('show-picker');
                });
            }
        });
        $('#' + eId + ' .box-range-cancel').bind('click', function() {
            $('.date-range-box').removeClass('show-picker');
        });

        dropdown.find('.all-dates').bind('click', function() {
            $('.date-range-box').removeClass('show-picker');
            scope.tmpAllDate = true;

            //QuyPV 20141119: Temporary solution for moving filters setting to manage locally in controllers
            if (eId == 'trip-filter' || eId == 'report-filter') {
                scope.filters.allDate = 1;
            }

            scope.$apply();
        });
    }
});

rciSpaApp.directive('selectDateOption', function() {
    return function(scope, element, attrs) {
        $(element).bind('click', function(e) {
            e.stopPropagation();
            $(element).parent().find('.dropdown-menu').toggle();
        });
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
                maxDate: new Date(),
                onChangeMonthYear: function(y, m, i) {
                    $timeout(function(){
                        if (scope.$parent.resetFilter) {
                            scope.$parent.resetFilter = false;
                            return false;
                        }

                        scope.$parent.filtering = true;
                        scope.item = y + '-' + ('0' + m).slice(-2) + '-' + '01';
                    });
                }
            });
        }
    }
});

rciSpaApp.directive('rciCalendar', function($timeout) {
    return {
        restrict: 'A',
        link: function(scope, element, attrs) {
            var attrObj = scope.$eval(attrs.rciCalendar);
            var oldValue;
            if (typeof attrObj !== 'object') {
                attrObj = {
                    updateNgModelWhenChangeMonthYear: false,
                    combined: false
                };
            }
            var maxDate = new Date(2025, 12, 31);
            if (angular.isDefined(attrObj.limitMonthYear) && attrObj.limitMonthYear){
                var curDate = new Date();
                maxDate =  new Date(curDate.getFullYear(), curDate.getMonth() +1, -1);
            }

            var userChangeContent = function(){
                $timeout(function(){
                    scope.$parent.$parent.userChangedContent = true;
                });
            }

            element.datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                maxDate: maxDate,
                showOn: 'focus',
                onSelect: function(selectedDate) {
                    $timeout(function() {
                        element.trigger('input');
                    }, 0);
                },
                onChangeMonthYear: function(year, month) {
                    if (attrObj.updateNgModelWhenChangeMonthYear) {
                        month = (month < 10) ? ("0" + month) : month;

                        $timeout(function() {
                            element.val(year + '-' + month + '-01T00:00:00.000Z');
                            element.trigger('input');
                        }, 0);
                        userChangeContent();
                    }
                },
                onClose: function(selectedDate) {
                    var oid = scope.$eval(attrs.oid);
                    var field = attrs.field;
                    if (typeof oid !== 'undefined' && typeof field !== 'undefined') {
                        var value = selectedDate;
                        if (oldValue != value) {
                            if (typeof scope.quickSave == 'function') {
                                scope.quickSave(oid, field, value, oldValue);
                            } else if (typeof scope.$parent.quickSave == 'function') {
                                scope.$parent.quickSave(oid, field, value, oldValue);
                            }
                        }
                    }

                    element.focus();
                },
                beforeShow: function(input) {
                    if ($(input).hasClass('item-expense-period') && ! $('#ui-datepicker-div').hasClass('dp-expense-period')) {
                        $('#ui-datepicker-div').addClass('dp-expense-period');
                    } else if (! $(input).hasClass('item-expense-period')) {
                        $('#ui-datepicker-div').removeClass('dp-expense-period');
                    }
                }
            });

            element.click(function() {
                oldValue = element.val();
                element.datepicker("show");
            });
        }
    }
});

rciSpaApp.directive('rciDatetime', function() {
    return {
        restrict: 'A',
        scope: {
            purchaseTime: '=purchaseTime'
        },
        link: function(scope, element, attrs) {
            var rciSelectedDateTime;
            var isCancelButtonClicked;

            function addCancelButton(input) {
                setTimeout(function () {
                    if ($('.dp-custom-cancel').length) {
                        return false;
                    }

                    var buttonPane = $(input).datepicker("widget").find(".ui-datepicker-buttonpane");

                    var btn = $('<button class="ui-state-default ui-corner-all dp-custom-cancel" type="button">Cancel</button>');
                    btn.unbind("click").bind("click", function () {
                        isCancelButtonClicked = true;
                        $('#purchase_date').datepicker('hide');
                    });

                    btn.prependTo(buttonPane);
                }, 1);
            };

            element.bind('click', function() {
                if (scope.IsReported) {
                    return false;
                }

                isCancelButtonClicked = false;
                jQuery('#purchase_date').datetimepicker({
                    dateFormat: $.datepicker.ISO_8601,
                    changeMonth: true,
                    changeYear: true,
                    timeFormat: "hh:mm:ss.l",
                    showSecond: false,
                    showMinute: false,
                    showHour: false,
                    showTime: false,
                    showOn: 'focus',
                    separator: 'T',
                    timeSuffix: 'Z',
                    showMillisec: false,
                    showButtonPanel: true,
                    yearRange: "1999:2050",
                    maxDate: new Date(),
                    closeText: "OK",
                    onSelect: function(selectedDateTime, inst) {
                        addCancelButton(inst.input);

                        rciSelectedDateTime = selectedDateTime;
                    },
                    onChangeMonthYear: function(year, month, inst) {
                        $(this).datetimepicker('setDate', new Date(year, month - 1, inst.selectedDay));
                        rciSelectedDateTime = inst.lastVal;

                        //Re-add the Cancel button
                        addCancelButton(inst.input);
                    },
                    onClose: function(selectedDateTime) {
                        // Rebind the event to close datepicker when user clicks out of the datepicker
                        // We need to do this action for other datepickers in our app
                        $(document).bind('mousedown', $.datepicker._checkExternalClick);

                        //Remove class when closing the datepicker, so it will not be added to other datepickers
                        $('#ui-datepicker-div').removeClass('dp-purchase-time');

                        if (rciSelectedDateTime && ! isCancelButtonClicked) {
                            $('#tmp_purchase_date').val(rciSelectedDateTime);
                            scope.$apply(function() {
                                scope.purchaseTime = rciSelectedDateTime;
                            });
                        }
                    },
                    beforeShow: function(input) {
                        //Add custom class dp-purchase-time to the datepicker so we can use our own styles
                        $('#ui-datepicker-div').removeClass('dp-expense-period').addClass('dp-purchase-time');

                        //Add the Cancel button
                        addCancelButton(input);
                    }
                }).focus();

                // Unbind the event to close datepicker when user clicks out of the datepicker
                $(document).off('mousedown', $.datepicker._checkExternalClick);
            });
        }
    }
});
