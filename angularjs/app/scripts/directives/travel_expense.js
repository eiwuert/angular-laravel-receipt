    rciSpaApp.directive('tripState', function($timeout) {
    return {
        restrict: 'E',
        replace: true,
        scope: { value: '=' },
        template: '<a href="" data-toggle="tooltip" data-placement="right" data-original-title="{{ value | reportStatus }}">{{value | firstChar | uppercase}}</a>',
        link: function(scope, element, attrs) {
            element.tooltip();
        }
    }
});
    rciSpaApp.directive('tripStateFilter', function($timeout) {
      return {
        restrict: 'E',
        replace: true,
        scope: { value: '=' },
        template: '<a href="" data-toggle="tooltip" data-placement="right" data-original-title="{{ value | reportStatus }}">{{value | reportStatus | firstChar | uppercase}}</a>',
        link: function(scope, element, attrs) {
          element.tooltip();
        }
      }
    });

rciSpaApp.directive('tripReducedHeader', function($timeout) {
    return {
        restrict: 'E',
        replace: true,
        scope: {
            description: '@',
            value: '@'
        },
        template: '<a href="" data-toggle="tooltip" data-placement="right" data-original-title="{{description}}">{{ value }}</a>',
        link: function(scope, element, attrs) {
            element.tooltip({html: true});
        }
    }
});

rciSpaApp.directive('addToReport', function($timeout, $rootScope) {
    return {
        restrict: 'E',
        scope: {},
        template: '<a ng-class="{\'a-disable\': $parent.currentTrip.ReportStatus == \'Rejected\' || $parent.currentTrip.ReportStatus == \'Draft\'}" class="btn add-items add-report-btn" href=""><span class="add-trip-report-text">Add Trip to Report</span></a>',
        link: function(scope, element, attrs) {
            $(element).popover({
                html: true,
                placement: 'bottom',
                content: '<div id="add-to-report-wrapper">\
                    <div class="popover add-to-report-popover custom-popover">\
                        <ul class="unstyled">\
                            <li><button class="btn btn-success btn-new-report">Auto-generate Report from Trip</button></li>\
                            <li>or</li>\
                            <li><button class="btn btn-success btn-existing-report">Add Trip to Existing Report</button></li>\
                        </ul>\
                    </div>\
                    <div class="arrow"></div></div>'
            }).click(function(e) {
                    if (scope.$parent.currentTrip.Report) {
                        $(this).popover('hide').removeClass('clicked');
                        $.showMessageBox({content: 'This Trip is added to another Report already.'});
                        return false;
                    }

                    e.stopPropagation();
                    if ($(this).hasClass('clicked')) {
                        $(this).popover('hide').removeClass('clicked');
                    } else {
                        $(this).popover('show').addClass('clicked');
                    }

                    var that = $(this);
                    //When clicking outside of the popover, close it
                    $('body').on('click', function() {
                        if (that.hasClass('clicked')) {
                            that.popover('hide').removeClass('clicked');
                        }
                    });
                    $('.add-to-report-popover').on('click', function(e) {
                        e.stopPropagation();
                    });

                    $('.btn-new-report').on('click', function() {
                        $rootScope.tripForNewReport = scope.$parent.currentTrip;
                        var today = new Date();
                        var m = today.getMonth() + 1;
                        var d = today.getDate();
                        today = today.getFullYear() + '-' + (m <= 9 ? '0' + m : m) + '-' + (d <= 9 ? '0' + d : d);

                        $rootScope.reportFromTripData = {
                            Title : scope.$parent.currentTrip.Name,
                            Date: today
                        };
                        $('#reportFromTripTrigger').click();
                    });

                    $('.btn-existing-report').on('click', function() {
                        $timeout(function() {
                            scope.$parent.openAddToReportForm();
                        });
                    });
                });
        }
    }
});
