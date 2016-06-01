rciSpaApp.controller('TripListCtrl', ['$scope', '$timeout', '$rootScope', '$location', 'Restangular', '$filter', function($scope, $timeout, $rootScope, $location, Restangular, $filter){
    /**
     * Variable to determine all trip is checked
     * @type boolean
     */
    $scope.isCheckAll = false;

    /**
     * Variable to toggle check all box
     * @type boolean
     */
    $scope.allowCheckAll = true;

    /**
     * Variable contain the type of trip when user filter
     *
     * @type {string}
     */
//    $scope.filterType = 'all';

    try {
        var date = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
    } catch (err) {
        var date = new Date();
    }

    $rootScope.inAppScreen == 'TRIP_LIST';

    $scope.dateFrom = (date.getFullYear() - 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + '01';
    $scope.dateTo   = (date.getFullYear() + 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + '01';
//    $scope.allDate  = true;

    /**
     * Variable contains pagination data
     *
     * @type object
     */
    $scope.page = {
        limit       : 20,
        indexFrom   : 1,
        indexTo     : 20,
        totalByType : 0,
        isLastPage  : false,
        forceLoad   : true,
        isWorking   : false
    };

    /**
     * Variable contains filters setting
     *
     * @type object
     */
    $scope.filters = {
        type      : 'all',
        name      : '',
        allDate   : 1,
        dateFrom  : (date.getFullYear() - 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + '01',
        dateTo    : (date.getFullYear() + 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + '01'
    };

    /**
     * Variable contains array of filter type data (name and total count)
     *
     * @type array
     */
    $scope.filterTypeList = [
        {name: 'All Trips', code: 'all', totalTrip: 0, filterCount: 0},
        {name: 'Past Trips', code: 'past', totalTrip: 0, filterCount: 0},
        {name: 'Current Trips', code: 'current', totalTrip: 0, filterCount: 0},
        {name: 'Upcoming Trips', code: 'upcoming', totalTrip: 0, filterCount: 0},
        {name: 'Reported Trips', code: 'reported', totalTrip: 0, filterCount: 0}
    ];
    angular.forEach ($scope.filterTypeList, function(type, k) {
        if (type.code == $scope.filters.type) {
            $scope.filters.name = type.name;
        }
    });

    /**
     * Variables to display start date in date filter
     *
     * @type array
     */
    $scope.dateFromDisplay = new Date($scope.filters.dateFrom).toString();

    /**
     * Variables to display end date in date filter
     *
     * @type array
     */
    $scope.dateToDisplay   = new Date($scope.filters.dateTo).toString();

    /**
     * Function to trigger filter by type
     * Allow filter object and string
     *
     * @param   ftype   object/string    filterTypeList element or type in string
     */
    $scope.selectFilterType = function (ftype) {
        if (typeof ftype == 'string') {
            for (var i=0; i<$scope.filterTypeList.length; i++) {
                if (ftype == $scope.filterTypeList[i].code) {
                    ftype = $scope.filterTypeList[i];
                    break;
                }
            }
        } else if (typeof ftype != 'object') {
            return false;
        }

        $scope.filters.name      = ftype.name;
        $scope.filters.type      = ftype.code;
        $scope.filters.allDate   = 1;

        $timeout(function(){
            $scope.applyFilters({skipCount:true});
        });
    };

    /**
     * Function to trigger filter by date
     *
     */
    $scope.applyAllDate = function () {
        $scope.filters.allDate   = 1;

        $timeout(function(){
            $scope.applyFilters();
        });
    };

    /**
     * Function to process check all/uncheck all trip/report checkbox
     */
    $scope.checkAll = function (items) {
        $scope.isCheckAll = !$scope.isCheckAll;

        angular.forEach (items, function (v, k) {
            if (!v.IsSubmitted) v.IsChecked = $scope.isCheckAll;
        });
    };

    /**
     * Function to set check box status
     *
     * @param object element
     */
    $scope.toggleCheckboxStatus = function (element) {
        $timeout(function() {
            // Set new checked status
            element.IsChecked = !element.IsChecked;
        });
    };

    /**
     * Event to open form to create new trip
     *
     */
    $scope.$on('OPEN_CREATE_TRIP', function (e, showTripList) {
        // User open Create Trip Form in eTrip Detail Page. We should hide Trip Detail Page & show Trip List Page in order
        // reuse the Create Trip Form
        if (jQuery('#trip-list-wrapper').css('display') == 'none') {
            jQuery('#trip-list-wrapper').show();
            jQuery('#trip-detail-wrapper').hide();
            jQuery('#report-list-wrapper').hide();
            jQuery('#report-detail-wrapper').hide();

            truncateTripListTableText();
        }

        $scope.responseMessage = [];
        $scope.trip = {};

        jQuery('#createTripBox').modal('show');
    });

    /**
     * Watch to update trip reference by start date
     *
     * If start date is not empty, don't set reference value
     */
    $scope.$watch('trip.StartDate', function (newval, oldval, scope) {
        //if (newval && typeof $scope.trip.Reference === 'undefined') {
        if (newval != oldval && /^\d{4}-\d{2}-\d{2}$/.test(newval) && (!$scope.trip.Reference || /^(T\d{8})$/.test($scope.trip.Reference) === true)) {
            $scope.trip.Reference = 'T' + newval.replace(/-/g, '');
        }
    });

    /**
     * Function to count trip total of all filter types
     *
     */
    $scope.getTripTotal = function () {
        var params = {type: ''};

        if (!$scope.filters.allDate) {
            params.dateFrom = $scope.filters.dateFrom;
            params.dateTo   = $scope.filters.dateTo;
        }

        angular.forEach($scope.filterTypeList, function(t, k){
            params.type += ((k>0) ? ',' : '') + t.code;
        });

        Restangular.one('trips').customGET('count', params).then(function(response) {
            angular.forEach(response, function(r, kr){
                angular.forEach($scope.filterTypeList, function(t, kt){
                    if (t.code == r.type) {
                        t.totalTrip     = r.count;
                        t.filterCount   = r.filterCount;
                    }
                });
            });
        });
    };

    /**
     * Function to create new trip via New Trip box
     *
     * @param object trip
     */
    $scope.save = function (trip) {
        if (trip.hasOwnProperty('Tags')) {
            trip.Tags = trip.Tags.split(',');
        }

        Restangular.all('trips').post(trip).then(function(response) {
            if (trip.StartDate) {
                //trip.StartDate = new Date(trip.StartDate);
                trip.StartDate += 'T00:00:00.000Z';
            }

            if (trip.EndDate) {
                //trip.EndDate = new Date(trip.EndDate);
                trip.EndDate += 'T00:00:00.000Z';
            }

            $scope.trip = {};
            $scope.responseMessage = [];

            // get latest trip list
            $scope.$emit('LOAD_TRIP_LIST');

            jQuery('#createTripBox').modal('hide');
            $timeout(function() {
                truncateTripListTableText();
            })
        }, function(response) {
            $scope.responseMessage = response.data.message;
        });
    };

   /*
    * Function to Print Trip and show dialog preview print
    *
    */
    $scope.printTrip = function () {
        $('.print-report-wrap').show();

        var trips = [];
        angular.forEach($scope.tripList, function(v, k) {
            if (v.IsChecked == true || v.IsChecked == 1) {
                trips.push(v.TripID);
            }

        });

        if (!trips.length) {
            $('.print-report-wrap').hide();
            $.showMessageBox({content: 'Please select a trip to print.'});
            return;
        }

        if (trips.length > 1) {
            $('.print-report-wrap').hide();
            $.showMessageBox({content: 'Please select only one trip.'});
            return;
        }

        $scope.selectPrintAction('all');
    };

   /*
    * Function to Export Report To PDF
    *
    */
    $scope.exportPDF = function () {
        $('.print-download').show();
        window.location.href = API_URL + '/trips/download-pdf?filePath=' + $scope.UrlToDownload;

        $timeout(function () {
            $('.print-download').hide();
            $('.print-report-wrap').hide();
        },1500);
    };

   /*
    * Selection action to generate trip print
    */
    $scope.selectPrintAction = function (action) {
        $scope.isGeneratingReport = false;
        $scope.UrlToDownload = $scope.urlReport = "";
        $('.loadding-pdf').show();
        $('.box-pdf-viewer object').hide();
        $scope.printAction = action;
        var trips = [];

        angular.forEach($scope.tripList, function(v, k) {
            if (v.IsChecked == true || v.IsChecked == 1) {
                trips.push(v.TripID);
            }
        });

        Restangular
            .one('trips').customGET('print?tripID=' + trips[0] + '&itemType=' + $scope.printAction)
            .then(function (response) {
                $timeout(function(){
                    if (response.FilePath) {
                        $scope.urlReport = API_URL + '/files/' + response.FilePath;
                        $scope.urlReport = CLIENT_URL + '/components/pdfJS/web/viewer.html?file=' + $scope.urlReport;
                        $scope.UrlToDownload = response.FilePath;
                    }

                    $('.loadding-pdf').hide();
                    $scope.isGeneratingReport = true;
                    $('.box-pdf-viewer object').show();
                });
        });
    }

    /**
     * Function to load Trip List with filers and pagination options
     *
     * @param   array   options    Filers and pagination options
     */
    $scope.getTripList = function (options)
    {
        if (typeof options == 'undefined') {
            options = $scope.getCurrentFilters();
        }

        if (!options.allDate && new Date(options.dateFrom) > new Date(options.dateTo)) {
            $.showMessageBox({
                content: 'End Date must be equal or greater than Start Date'
            });

            return false;
        }

        resetShiftClick();

        $scope.page.limit = Math.ceil($('#te-trip-list').height()/25 - 1);
        var queryFrom;
        if (typeof options.queryFrom == 'undefined') {
            if ($scope.page.queryFrom <= 1) {
                $scope.page.forceLoad = true;
                $scope.paginateFirst();
                return;
            }
            else if ($scope.page.isLastPage) {
                $scope.page.forceLoad = true;
                $scope.paginateLast();
                return;
            }
            else {
                $scope.paginateStand();
                return;
            }
        } else {
            queryFrom = options.queryFrom;
        }

        //var dateFrom = $filter('date')(new Date($scope.dateFromDisplay), 'yyyy-MM-dd');
        //var dateTo   = $filter('date')(new Date($scope.dateToDisplay), 'yyyy-MM-dd');

        var params = {
            allDate      : options.allDate,
            type         : options.type,
            from         : options.dateFrom,
            to           : options.dateTo,
            queryFrom    : queryFrom,
            queryStep    : $scope.page.limit
        };

        Restangular.one('trips').getList('', params).then(function(response) {
            $scope.tripList = response;

            if (angular.isDefined(options.callback)) {
                options.callback(response.length);
            }

            $('.app-table-child-wrapper').resizeHeight();

            $rootScope.loadedModule++;
            $timeout(function() {
                truncateTripListTableText();
            });

        }, function(response) {
            $rootScope.loadedModule++;
            if (response.status !== 200) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            }
        });
    };

    /**
     * Function to load Trip Detail screen
     *
     * @param    tripId   int   ID of trip
     */
    $scope.loadTripDetail = function(tripId) {
        $scope.$emit('LOAD_TRIP_DETAIL', tripId, $scope.filters.type, $scope.dateFromDisplay, $scope.dateToDisplay);
    };

    /**
     * Delete selected trip items
     *
     */
    $scope.deleteSelectedTrip = function() {
        var items = [];
        angular.forEach($scope.tripList, function(v, k) {
            if (v.IsChecked == true || v.IsChecked == 1) {
                items.push(v.TripID);
            }
        });

        if (!items.length) {
            $.showMessageBox({content: 'Please select trip(s) to delete.', boxTitle: 'DELETE TRIP(S)', boxTitleClass: ''});
            return;
        }

        $.showMessageBox({
            content: '<p style="font-size: 16px;">Are you sure you want to delete this trip?</p>' +
                '<p style="font-size: 16px;">Please note, the receipt/invoice and items belonging to this trip will not be deleted from your ReceiptBox.</p>',
            boxTitle: 'DELETE TRIP(S)',
            boxTitleClass: '',
            type: 'confirm',
            onYesAction: function() {
                $timeout(function() {
                    Restangular.one('trips').remove({TripIDs: items.join(',')}).then(function(response) {
                        $scope.$emit('LOAD_TRIP_LIST');
                        $rootScope.$broadcast('RELOAD_RECEIPT_LIST', true);
                        $rootScope.$emit('LOAD_REPORT_LIST');
                    }, function(response) {
                        $.showMessageBox({content: response.data.message.join('<br/>')});
                    })
                })
            }
        });
    };

    /**
     * Event for loading trip list globally
     *
     */
    $rootScope.$on('LOAD_TRIP_LIST', function(even) {
        $scope.getTripList();
    });

    /**
     * Function to trigger loading REPORT DETAIL screen
     *
     */
    $scope.loadReportDetail = function(reportId) {
        $scope.$emit('LOAD_REPORT_DETAIL', reportId, 'all', $scope.dateFromDisplay, $scope.dateToDisplay);
    };

    /**
     * Event to open form to create new trip
     *
     */
    $rootScope.$on('TRIP_GET_CURRENT_LIST', function (e, callback) {
        if (typeof callback != 'undefined') {
            callback(angular.copy($scope.tripList));
        }
    });

    /**
     * Function to trigger quick save for inline-edit cells
     *
     */
    $scope.quickSave = function(id, field, value, oldValue) {
        //Select current editing trip and bind edit status
        $timeout(function(){
            $scope.editStatus = 'saving';
        });

        Restangular.one('trips').customPUT({'TripID': id, 'Field': field, 'Value' : value}, 'quick-save').then(function(response) {
            $scope.editStatus = '';
            if (field == 'StartDate' || field == 'Reference') {
                for (var i = 0; i < $scope.tripList.length; i++) {
                    if ($scope.tripList[i].TripID == id) {
                        $scope.tripList[i].State = response.State;
                        $scope.tripList[i].Reference = response.Reference;
                        break;
                    }
                }
            }
        }, function(response) {
            $scope.editStatus = '';
            $.showMessageBox({content: response.data.message.join('<br/>')});
            //Reset the value to its old value
            for (var i = 0; i < $scope.tripList.length; i++) {
                if ($scope.tripList[i].TripID == id) {
                    $scope.tripList[i][field] = oldValue;
                    break;
                }
            }
        });
    };

    /**
     * Return Total trip by type
     *
     * @param     ftype    String   Filter type
     * @returns   int
     */
    $scope.getTotalByType = function (ftype, withFilter) {
        if (typeof withFilter == 'undefined') withFilter = false;

        for (var i=0; i<$scope.filterTypeList.length; i++) {
            if ($scope.filterTypeList[i].code == ftype) {
                return (withFilter) ?
                    $scope.filterTypeList[i].filterCount :
                    $scope.filterTypeList[i].totalTrip;
            }
        }

        return 0;
    };

    /**
     * Function to update filter settings
     *
     */
    $scope.setFilters = function (changes) {
        if (typeof changes == 'undefined') changes = {};
        if (typeof changes.allDate != 'undefined')  $scope.filters.allDate = changes.allDate;
        if (typeof changes.type != 'undefined')     $scope.filters.type = changes.type;
        if (typeof changes.dateFrom != 'undefined') $scope.filters.dateFrom = changes.dateFrom;
        if (typeof changes.dateTo != 'undefined')   $scope.filters.dateTo = changes.dateTo;

    };

    /**
     * Function to get current filter settings (date and type filters)
     *
     * @returns   object   Settings in object
     */
    $scope.getCurrentFilters = function () {
        return {
            allDate  : $scope.filters.allDate,
            type     : $scope.filters.type,
            dateFrom : $scope.filters.dateFrom,
            dateTo   : $scope.filters.dateTo
        }
    };

    /**
     * Pagination for trips
     * Go to First page button
     *
     * @param   extOpts  array   External option for filters
     */
    $scope.paginateFirst = function (extOpts) {
        if (!$scope.page.forceLoad && $scope.page.indexFrom <=1 || $scope.page.isWorking) {
            return;
        }

        $scope.page.isWorking = true;
        if (typeof extOpts == 'undefined') extOpts = {};

        var indexFrom = 1;
        var queryOpts = {
            queryFrom : indexFrom,
            callback  : function(queried) {
                var indexTo             = indexFrom + queried - 1;
                var total               = $scope.getTotalByType(filters.type, filters.allDate?false:true);
                $scope.page.indexFrom   = indexFrom;
                $scope.page.indexTo     = indexTo;
                $scope.page.totalByType = total;
                $scope.page.isLastPage  = (indexTo == total) ? true : false;
                $scope.page.forceLoad   = false;
                $scope.page.isWorking   = false;
            }
        };

        var filters = $scope.getCurrentFilters();
        jQuery.extend(queryOpts, filters, extOpts);

        //default is re-count reports
        if (typeof extOpts.skipCount == 'undefined' || !extOpts.skipCount) {
            $scope.getTripTotal();
        }

        $timeout(function(){
            $scope.getTripList(queryOpts);
        });
    };

    /**
     * Pagination for trips
     * Previous page button
     *
     * @param   extOpts  array   External option for filters
     */
    $scope.paginatePrevious = function (extOpts) {
        if (!$scope.page.forceLoad && $scope.page.indexFrom <=1 || $scope.page.isWorking) {
            return;
        }

        $scope.page.isWorking = true;
        if (typeof extOpts == 'undefined') extOpts = {};

        var indexFrom = $scope.page.indexFrom - $scope.page.limit;
        indexFrom     = (indexFrom > 1) ? indexFrom : 1;
        var queryOpts = {
            queryFrom    : indexFrom,
            queryForward : 0,
            callback     : function(queried) {
                var indexTo             = indexFrom + queried - 1;
                var total               = $scope.getTotalByType(filters.type, filters.allDate?false:true);
                $scope.page.indexFrom   = indexFrom;
                $scope.page.indexTo     = indexTo;
                $scope.page.totalByType = total;
                $scope.page.isLastPage  = (indexTo == total) ? true : false;
                $scope.page.forceLoad   = false;
                $scope.page.isWorking   = false;
            }
        };

        var filters = $scope.getCurrentFilters();
        jQuery.extend(queryOpts, filters, extOpts);

        //default is SKIP re-count report totals
        if (typeof extOpts.skipCount != 'undefined' && !extOpts.skipCount) {
            $scope.getTripTotal();
        }

        $timeout(function() {
            $scope.getTripList(queryOpts);
        });
    };

    /**
     * Pagination for trips
     * Next page button
     *
     * @param   extOpts  array   External option for filters
     */
    $scope.paginateNext = function (extOpts) {
        if (!$scope.page.forceLoad && $scope.page.isLastPage || $scope.page.isWorking) {
            return;
        }

        $scope.page.isWorking = true;
        if (typeof extOpts == 'undefined') extOpts = {};

        var indexFrom = $scope.page.indexTo + 1;
        var queryOpts = {
            queryFrom: indexFrom,
            queryForward: 1,
            callback: function (queried) {
                var indexTo             = indexFrom + queried - 1;
                var total               = $scope.getTotalByType(filters.type, filters.allDate?false:true);
                $scope.page.indexFrom   = indexFrom;
                $scope.page.indexTo     = indexTo;
                $scope.page.totalByType = total;
                $scope.page.isLastPage  = (indexTo == total) ? true : false;
                $scope.page.forceLoad   = false;
                $scope.page.isWorking   = false;
            }
        };

        var filters = $scope.getCurrentFilters();
        jQuery.extend(queryOpts, filters, extOpts);

        //default is SKIP re-count report totals
        if (typeof extOpts.skipCount != 'undefined' && !extOpts.skipCount) {
            $scope.getTripTotal();
        }

        $timeout(function() {
            $scope.getTripList(queryOpts);
        });
    };

    /**
     * Pagination for trips
     * Go to Last page button
     *
     * @param   extOpts  array   External option for filters
     */
    $scope.paginateLast = function (extOpts) {
        if (!$scope.page.forceLoad && $scope.page.isLastPage || $scope.page.isWorking) {
            return;
        }

        $scope.page.isWorking = true;
        if (typeof extOpts == 'undefined') extOpts = {};

        var queryOpts ={
            queryFrom : 'last',
            callback  : function(queried) {
                var total               = $scope.getTotalByType(filters.type, filters.allDate?false:true);
                $scope.page.indexFrom   = total - queried + 1;
                $scope.page.indexTo     = total;
                $scope.page.totalByType = total;
                $scope.page.isLastPage  = true;
                $scope.page.forceLoad   = false;
                $scope.page.isWorking   = false;
            }
        };

        var filters = $scope.getCurrentFilters();
        jQuery.extend(queryOpts, filters, extOpts);

        //default is re-count report totals
        if (typeof extOpts.skipCount == 'undefined' || !extOpts.skipCount) {
            $scope.getTripTotal();
        }

        $timeout(function() {
            $scope.getTripList(queryOpts);
        });
    };

    /**
     * Pagination for trips. Refresh current page
     *
     * @param   extOpts  array   External option for filters
     */
    $scope.paginateStand = function (extOpts) {
        if (!$scope.page.forceLoad && $scope.page.isLastPage || $scope.page.isWorking) {
            return;
        }

        $scope.page.isWorking = true;
        if (typeof extOpts == 'undefined') extOpts = {};

        var indexFrom = $scope.page.indexFrom;
        var queryOpts ={
            queryFrom : indexFrom,
            callback  : function(queried) {
                var indexTo             = indexFrom + queried - 1;
                var total               = $scope.getTotalByType(filters.type, filters.allDate?false:true);
                $scope.page.indexFrom   = indexFrom;
                $scope.page.indexTo     = indexTo;
                $scope.page.totalByType = total;
                $scope.page.isLastPage  = (indexTo == total) ? true : false;
                $scope.page.forceLoad   = false;
                $scope.page.isWorking   = false;
            }
        };

        var filters = $scope.getCurrentFilters();
        jQuery.extend(queryOpts, filters, extOpts);

        //default is re-count report totals
        if (typeof extOpts.skipCount == 'undefined' || !extOpts.skipCount) {
            $scope.getTripTotal();
        }

        $timeout(function() {
            $scope.getTripList(queryOpts);
        });
    };

    /**
     * Function to trigger get new trips queried by selected filters
     *
     */
    $scope.applyFilters = function () {
        $scope.page.forceLoad = true;
        $scope.paginateFirst();
    };


    //Initialization functions
    //$scope.getTripTotal();
    $timeout(function() {
        $scope.paginateFirst();
    });

}]);

/*
 * filter trip object by trip code.
 */
function filter_trip_type_by_code(query, obj)
{
    var new_obj={};
    for(var i in obj){
        var emp_st=obj[i].code;
        if(emp_st==query) { new_obj=obj[i]; }
    }
    return new_obj;
}
