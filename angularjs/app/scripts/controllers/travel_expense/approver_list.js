rciSpaApp.controller('ApproverListCtrl', ['$scope', '$timeout', '$rootScope', '$location', 'Restangular', '$filter',
        '$templateCache', '$compile', function($scope, $timeout, $rootScope, $location, Restangular, $filter,
        $templateCache, $compile){
    /**
     * Variable to get today date
     */
    try {
        var date = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
    } catch (err) {
        var date = new Date();
    }

    /**
     * Variable to set default date range filter
     * @type string
     */
    $scope.dateFrom = (date.getFullYear() - 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + '01';
    $scope.dateTo   = (date.getFullYear() + 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + '01';

    $rootScope.inAppScreen == 'REPORT_LIST';

    /**
     * Variable to store list of report
     * @type array
     */
    $scope.reportList = [];

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
        role      : 'approver',
        name      : '',
        allDate   : 1,
        dateFrom  : (date.getFullYear() - 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + '01',
        dateTo    : (date.getFullYear() + 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + '01'
    };

    //Use temporary filter variables before 'Go' button is clicked
/*
    $scope.tmpFilterType = angular.copy($scope.filterType);
    $scope.tmpAllDate = angular.copy($scope.allDate);
    $scope.tmpDateFrom = angular.copy($scope.dateFrom);
    $scope.tmpDateTo = angular.copy($scope.dateTo);
*/

    /**
     * Variable contains array of filter type data (name and total count)
     *
     * @type array
     */
    $scope.filterTypeList = [
        {name: 'All Reports', code: 'all', totalReport:0, filterCount: 0},
        {name: 'Pending Reports', code: 'pending',totalReport:0, filterCount: 0},
        {name: 'Approved Reports', code: 'approved',totalReport:0, filterCount: 0},
        {name: 'Rejected Reports', code: 'rejected',totalReport:0, filterCount: 0}
    ];
    angular.forEach($scope.filterTypeList, function(type, k){
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
    $scope.checkAll = function(items) {
        $scope.isCheckAll = !$scope.isCheckAll;

        angular.forEach(items, function(v, k) {
            v.IsChecked = $scope.isCheckAll;
        });
    };

    /**
     * Function to set check box status
     *
     * @param object element
     */
    $scope.toggleCheckboxStatus = function(element) {
        $timeout(function() {
            // Set new checked status
            element.IsChecked = !element.IsChecked;
        });
    };

    /**
     * Function to count report total of all filter types
     *
     */
    $scope.getReportTotal = function(){
        var params = {
            role : $scope.filters.role,
            type: ''
        };

        if (!$scope.filters.allDate) {
            params.dateFrom = $scope.filters.dateFrom;
            params.dateTo   = $scope.filters.dateTo;
        }

        angular.forEach($scope.filterTypeList, function(r, k){
            params.type += ((k>0) ? ',' : '') + r.code;
        });

        Restangular.one('reports').customGET('count', params).then(function(response) {
            angular.forEach(response, function(res, k1){
                angular.forEach($scope.filterTypeList, function(r, k2){
                    if (r.code == res.type) {
                        r.totalReport = res.count;
                        r.filterCount = res.filterCount;
                    }
                });
            });

            //Update dashboard
            $rootScope.$broadcast('DB_UPDATE_COUNT', 'travel', $scope.filterTypeList);
        });
    };

    /**
     * Event to open travel expense from dashboard
     *
     */
    $rootScope.$on('OPEN_APPFROMDASHBOARD', function (even, app,ctravel) {
        if(app == 'approverList'){
            $('#menu-travel-approver').click();
            if(ctravel){
                $scope.selectFilterType(ctravel);
                //$scope.getReportList(ctravel.code,true);
            }
        }
    });

    /**
     * Function to receive new reports
     *
     */
    $scope.receiveReports = function() {
        $('#report-receive-button .app-icon.receive').toggleClass('animated-receive');
        $scope.selectFilterType('all');

        //TODO: this function is originally running in a callback function. Temporary use timeout for hotfix
        $timeout(function(){
            $('#report-receive-button .app-icon.receive').toggleClass('animated-receive');
        }, 500);
    };

    /**
     * Event for push message report
     *
     */
    $scope.$on('REPORT_EVENT_REALTIME', function(e, type, reportId, message) {
        var reload = function (reloadTrip) {
            setTimeout(function(){
                $scope.receiveReports();
                $scope.getReportList();

                if (typeof reloadTrip != 'undefined' && reloadTrip) {
                    $rootScope.$broadcast('LOAD_TRIP_LIST');
                }
            },500);
        };

        if (type == 'submit') {
            if ($scope.filters.type == 'pending' || $scope.filters.type == 'all')
                reload();

            if(message){
                var viewDetail = '<span>'+message+'&nbsp;</span>'+
                    '<span class="underline bold curs-hand" ng-click="loadReportDetail('+reportId+')">Show</span>';
                var spanEle = $compile(viewDetail)($scope);
                $('#notify-approver').show().empty().html(spanEle);
            }
        }
    });

    /**
     * Function to load Report List with filers and pagination options
     *
     * @param   object   options   Filers and pagination options
     */
    $scope.getReportList = function(options) {
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

        $scope.page.limit = Math.ceil($('#te-trip-list').height()/24 - 1);

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

        var params = {
            allDate      : options.allDate,
            role         : options.role,
            type         : options.type,
            from         : options.dateFrom,
            to           : options.dateTo,
            queryFrom    : queryFrom,
            queryStep    : $scope.page.limit
        };

        Restangular.one('reports').getList('', params).then(function(response) {
            //Hide truncating work (which cause email column shaking) before it is done
            $('#te-approver-list').css('visibility', 'hidden');

            $scope.reportList = response;
            angular.forEach($scope.reportList, function(report) {
                report.IsSubmitted = parseInt(report.IsSubmitted);
                report.IsArchived = parseInt(report.IsArchived);
            });

            if (angular.isDefined(options.callback)) {
                options.callback(response.length);
            }

            $('.app-table-child-wrapper').resizeHeight();

            $rootScope.loadedModule++;
            $timeout(function() {
                truncateReportListTableText('ta');
            });

            //Hide truncating work (which cause email column layout shaking) before it is done
            $timeout(function() {
                $('#te-approver-list').css('visibility', 'visible');
            }, 100);

            //TODO: add corresponding callback to pagination functions
//            if (callback) {
//                callback(response);
//            }
        }, function(response) {
            $rootScope.loadedModule++;
            if (response.status !== 200) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            }
        });
    };

    /**
     * Function to trigger quick save for inline-edit cells
     *
     */
    $scope.quickSave = function(id, field, value, oldValue) {
        //Select current editing report and bind edit status
        $timeout (function(){
            $scope.editStatus = 'saving';
        });

        Restangular.one('reports').customPUT({'ReportID': id, 'Field': field, 'Value' : value}, 'quick-save').then(function(response) {
            $scope.editStatus = '';
            if (field == 'Date' || field == 'Reference') {
                for (var i = 0; i < $scope.reportList.length; i++) {
                    if ($scope.reportList[i].ReportID == id) {
                        $scope.reportList[i].Reference = response.Reference;
                        $scope.reportList[i].Status = response.Status;
                        break;
                    }
                }
            }
        }, function(response) {
            $scope.editStatus = '';
            $.showMessageBox({content: response.data.message.join('<br/>')});
            //Reset the value to its old value
            for (var i = 0; i < $scope.reportList.length; i++) {
                if ($scope.reportList[i].ReportID == id) {
                    $scope.reportList[i][field] = oldValue;
                    break;
                }
            }
        });
        //$scope.getReportTotal();
    };

    /**
     * Function to approve multiple reports
     *
     */
    $scope.approveReports = function() {
        var items = [];
        angular.forEach($scope.reportList, function(v, k) {
            if (v.IsChecked == true || v.IsChecked == 1) {
                items.push(v.ReportID);
            }
        });

        if (!items.length) {
            $.showMessageBox({content: 'Please select report(s) to approve.'});
            return;
        }

        Restangular.one('reports').customPUT({"ReportIDs": items}, 'approve').then(function(response) {
            $.showMessageBox({content: 'The report(s) is approved.'});
            //$scope.getReportTotal();
            $scope.getReportList();
        }, function(response) {
            $.showMessageBox({content: response.data.message.join('<br/>')});
        });
    };

    /**
     * Function to reject multiple reports
     *
     */
    $scope.rejectReports = function() {
        var items = [];
        angular.forEach($scope.reportList, function(v, k) {
            if (v.IsChecked == true || v.IsChecked == 1) {
                items.push(v.ReportID);
            }
        });

        if (!items.length) {
            $.showMessageBox({content: 'Please select report(s) to reject.'});
            return;
        }

        Restangular.one('reports').customPUT({"ReportIDs": items, "IsApproved": 2}, 'approve').then(function(response) {
            $.showMessageBox({content: 'The report(s) is rejected.'});
            $scope.getReportList();
        }, function(response) {
            $.showMessageBox({content: response.data.message.join('<br/>')});
        });
    };

    /*
    * Function to print Report
    *
    */
    $scope.printReport = function(){
        $templateCache.removeAll();
        $('.print-report-wrap').show();
        $('.box-select-reporttype p input:radio#all').prop('checked',true);
        var reports = [];
        angular.forEach($scope.reportList, function(v, k) {
            if (v.IsChecked == true || v.IsChecked == 1) {
                reports.push(v.ReportID);
            }
        });

        if (!reports.length) {
            $('.print-report-wrap').hide();
            $.showMessageBox({content: 'Please select a report to print.'});
            return;
        }

        if (reports.length > 1) {
            $('.print-report-wrap').hide();
            $.showMessageBox({content: 'Please select only one report.'});
            return;
        }
        $templateCache.removeAll();
        $scope.selectPrintAction('all');
    };

    /*
    * Export Report To PDF
    * */
    $scope.exportPDF = function() {
        $('.print-download').show();
        window.location.href = API_URL + '/reports/download-pdf?filePath=' + $scope.UrlToDownload;
        $timeout(function(){
            $('.print-download').hide();
            $('.print-report-wrap').hide();
        },1500);
    };

     /**
     * Selection action to generation report print
     */
    $scope.selectPrintAction = function (action) {
        $scope.isGeneratingReport = false;
        $scope.UrlToDownload = $scope.urlReporat = "";
        $('.loadding-pdf').show();
        $('.box-pdf-viewer object').hide();
        $scope.printAction = action;
        var reports = [];
        angular.forEach($scope.reportList, function(v, k) {
            if (v.IsChecked == true || v.IsChecked == 1) {
                reports.push(v.ReportID);
            }
        });
        Restangular.one('reports').customGET('print?reportID=' + reports[0] + '&itemType=' + $scope.printAction).then(function (response) {
            $timeout(function(){
                if (response.FilePath) {
                    $scope.urlReport =   API_URL + '/files/' + response.FilePath;
                    $scope.urlReport = CLIENT_URL + '/components/pdfJS/web/viewer.html?file=' + $scope.urlReport;
                    $scope.UrlToDownload = response.FilePath;
                }
                $('.loadding-pdf').hide();
                $scope.isGeneratingReport = true;
                $('.box-pdf-viewer object').show();
            });
        });
    };

    /**
     * Event to reload Report list
     */
    $rootScope.$on('LOAD_APPROVER_LIST', function(even) {
        $scope.getReportList();
    });

    /**
     * Function to load Report Detail screen
     *
     * @param   reportId   int   ID of report
     */
    $scope.loadReportDetail = function(reportId) {
        $scope.$emit('LOAD_TA_DETAIL', reportId, $scope.filterType, $scope.dateFromDisplay, $scope.dateToDisplay);
    };

    /**
     * Return Total report by type
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
                    $scope.filterTypeList[i].totalReport;
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
            role     : $scope.filters.role,
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

                //$scope.bindQuickHovers();
            }
        };

        var filters = $scope.getCurrentFilters();
        jQuery.extend(queryOpts, filters, extOpts);

        //default is re-count reports
        if (typeof extOpts.skipCount == 'undefined' || !extOpts.skipCount) {
            $scope.getReportTotal();
        }

        $timeout(function(){
            $scope.getReportList(queryOpts);
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
            $scope.getReportTotal();
        }

        $timeout(function() {
            $scope.getReportList(queryOpts);
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
            $scope.getReportTotal();
        }

        $timeout(function() {
            $scope.getReportList(queryOpts);
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
            $scope.getReportTotal();
        }

        $timeout(function() {
            $scope.getReportList(queryOpts);
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
            $scope.getReportTotal();
        }

        $timeout(function() {
            $scope.getReportList(queryOpts);
        });
    };

    /**
     * Function to trigger get new trips queried by selected filters
     *
     * @param   extOpts  array   External option for filters
     */
    $scope.applyFilters = function (extOpts) {
        $scope.page.forceLoad = true;

        if (typeof extOpts != 'undefined') $scope.paginateFirst(extOpts);
        else $scope.paginateFirst();
    };

    /*
     * Initialization functions
     */
    //$scope.getReportTotal();
    $timeout(function() {
        $scope.paginateFirst();
        //$scope.getReportList(undefined, undefined, undefined, 1);
    });
    $('.cancel-report-btn').on('click',function(){
        $('.print-report-wrap').hide();
    });
    $('.print-report-wrap .pop-up-close-window').on('click', function(e) {
        if (e.target !== this) return;
        $('.print-report-wrap').hide();
    });
    $('.print-report-wrap').on('click', function(e) {
        if (e.target !== this) return;
        $('.print-report-wrap').hide();
    });

    /**
     * Function to display the quick action of each report in list by hovering lines
     *
     */
    $scope.bindQuickHovers = function () {
        //Bind event mouseenter to the span element & show popover
        $('.app-table.report-list').on('mouseenter', 'tr .col-name > a', function (e) {
            e.stopPropagation();
            var spanElement = $(this);
            var reportObject = jQuery.parseJSON(spanElement.attr("data-report"));

            if (reportObject.ReportType == 'Pending Reports') {
                var html = '<div class="cat-add-expense-popover custom-popover">\
                    <ul class="unstyled inline" style="margin: 0 0 1px 0;">\
                        <li><button class="btn-mini btn-approve-report">Approve</button></li>\
                        <li>or</li>\
                        <li><button class="btn-mini btn-reject-report">Reject</button></li>\
                    </ul>\
                </div>'
            } else {
                return;
            }

            $(this).popover({
                html: true,
                placement: 'right',
                content: html,
                container: spanElement.parent()
            });

            spanElement.popover('show');

            $('.btn-approve-report').on('click', function () {
                $.showMessageBox({
                    boxTitle: 'APPROVE REPORT',
                    content: 'Are you sure you want to approve this report?<br>' + reportObject.Title,
                    type: 'confirm',
                    onYesAction: function() {
                        $timeout(function() {
                            var items = [];
                            angular.forEach($scope.reportList, function(v, k) {
                                if (v.ReportID == reportObject.ReportID) {
                                    items.push(v.ReportID);
                                }
                            });

                            if (!items.length) {
                                $.showMessageBox({content: 'Please select report to approve.'});
                                return;
                            }

                            Restangular.one('reports').customPUT({"ReportIDs": items}, 'approve').then(function(response) {
                                $.showMessageBox({content: 'The report is approved.'});
                                $scope.receiveReports();
                                $scope.getReportList();
                            }, function(response) {
                                $.showMessageBox({content: response.data.message.join('<br/>')});
                            });
                        });
                    }
                });
            });
            $('.btn-reject-report').on('click', function () {
                $.showMessageBox({
                    boxTitle: 'REJECT REPORT',
                    content: 'Are you sure you want to reject this report?<br>' + reportObject.Title,
                    type: 'confirm',
                    onYesAction: function() {
                        $timeout(function() {
                            var items = [];
                            angular.forEach($scope.reportList, function(v, k) {
                                if (v.ReportID == reportObject.ReportID) {
                                    items.push(v.ReportID);
                                }
                            });

                            if (!items.length) {
                                $.showMessageBox({content: 'Please select report to reject.'});
                                return;
                            }

                            Restangular.one('reports').customPUT({"ReportIDs": items, "IsApproved": 2}, 'approve').then(function(response) {
                                $.showMessageBox({content: 'The report is rejected.'});
                                $scope.receiveReports();
                                $scope.getReportList();
                            }, function(response) {
                                $.showMessageBox({content: response.data.message.join('<br/>')});
                            });
                        });
                    }
                });
            });

            var difWidth = spanElement.width() - spanElement.parent().width();
            var curLeft = parseInt(spanElement.parent().find('.popover').css('left'));
            spanElement.parent().find('.popover').css('left', (curLeft-difWidth)/1.7 + 'px');


            //Bind event mouseleave to this element, which is the parent of both the span element and the popover
            $(spanElement).parent().bind('mouseleave', function (e) {
                e.stopPropagation();
                spanElement.popover('hide');
            });
        });
    }

}]);
