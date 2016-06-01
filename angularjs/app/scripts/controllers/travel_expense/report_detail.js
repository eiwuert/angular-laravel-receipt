rciSpaApp.controller('ReportDetailCtrl', ['$scope', '$timeout', '$rootScope', '$route', 'Restangular', '$location' , '$templateCache', function($scope, $timeout, $rootScope, $route, Restangular, $location, $templateCache){
    $scope.reportType = 'all';
    $scope.dateFrom;
    $scope.dateTo;
    $scope.memoEditable = false;

    $scope.checkMemoEditable = function() {
        if ($scope.report.IsSubmitter){
            $scope.memoEditable = ($scope.report.IsSubmitted)? (($scope.report.IsApproved==2)?true:false):true;
        } else {
            $scope.memoEditable = ($scope.report.IsSubmitted && $scope.report.IsApproved ==0)? true:false;
        }
    }

    $scope.$on('$routeChangeSuccess', function(event, currentRoute, previousRoute) {
        if (currentRoute.currentPath === '/travel-expense' && currentRoute.params.hasOwnProperty('reportId')) {
            $scope.reportId = parseInt(currentRoute.params.reportId);
        } else {
            $scope.reportId = 0;
        }
    });

    $rootScope.inAppScreen == 'REPORT_DETAIL';

    $scope.loadReportDetail = function(reportId) {
        $scope.$emit('LOAD_REPORT_DETAIL', reportId)
    }

    /**
     * Listen to load report detail
     */
    $rootScope.$on('LOAD_REPORT_DETAIL', function(event, reportId, reportType, dateFrom, dateTo, tripInfo) {
        jQuery('.page-app').hide();
        jQuery('#ngview-wrapper').hide();
        jQuery('#report-detail-wrapper').show();

        //Recalculate to make columns Submitter and Approver have an equal width
        $('#app-te .tb-rl .col-apr').equalWidth('#app-te .tb-rl .col-sub');
        $(window).resize(function() {
            $('#app-te .tb-rl .col-apr').equalWidth('#app-te .tb-rl .col-sub');
        });
        //Variable to parse into inline-edit directive in order to fix bug of text-ellipsis trigger limiting max length
        $scope.inlineEditMaxWidth = $('#app-te .tb-rl th.col-apr').width();

        try {
            var now = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
        } catch (err) {
            var now = new Date();
        }
        var defaultDate = now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
        var defaultRef = 'R' + now.getFullYear() + ('0' + (now.getMonth() + 1)).slice(-2) + ('0' + now.getDate()).slice(-2);

        if (angular.isDefined(reportType)) {
            $scope.reportType = reportType;
        }

        if (angular.isDefined(dateFrom)) {
            $scope.dateFrom = dateFrom;
        }

        if (angular.isDefined(dateTo)) {
            $scope.dateTo = dateTo;
        }

        //Use when we open the Create Report form from Trip Detail screen, so we can add the trip to a new report
        $scope.tripInfo = tripInfo;

        /**
         * Variable for report object
         * @type Object
         */
        $scope.report = {Amount: 0, Claimed: 0, Approved: 0, Date: defaultDate, IsSubmitted: 0, Reference: defaultRef, Attachments: [], ReportID: 0, DeletedFileIDs: [], RemovedTrips: [], SubmitterFirstName: null, SubmitterLastName: null};

        /**
         * Variable contain selected amount of trip. This variable is used in Add trip screen
         * @type {number}
         */
        $scope.selectedAmount = 0;

        /**
         * Store the report ID in $scope
         * @type integer
         */

        /**
         * Variable contain selected trip id. This variable is used in Add trip screen
         * @type {Array}
         */
        $scope.selectedTripIDs = [];

        if (!reportId) {
            jQuery('#report-title a').click();
            $('.report-detail-title h4').html('');

            if (angular.isDefined($scope.tripInfo)) {
                $scope.selectedTripIDs.push($scope.tripInfo.tripID);
                $scope.addToReport();
            }
        } else {
            $scope.reportId = reportId;

            Restangular.one('reports').get({reportID: $scope.reportId}).then(function(response) {
                $scope.report = response;
                $scope.report.Amount = 0;
                $scope.report.IsArchived = parseInt($scope.report.IsArchived);
                $scope.report.IsSubmitted = parseInt($scope.report.IsSubmitted);
                $scope.report.IsApproverEdited = parseInt($scope.report.IsApproverEdited);

                if (! $scope.report.IsApproverEdited && ! $scope.report.IsSubmitter) {
                    $scope.report.Approved = 0;
                    $scope.report.IsAllApproved = true;
                } else {
                    $scope.report.Approved = parseFloat($scope.report.Approved);
                }
                angular.forEach($scope.report.Trips, function(trip, k) {
                    trip.peCatCollapseStatus = false;

                    if (! $scope.report.IsApproverEdited && ! $scope.report.IsSubmitter) {
                        trip.IsApproved = true;
                        trip.Approved = 0;
                    }
                    trip.toggleItemIsApproved = trip.Approved;
                    trip.Amount = 0;
                    if (trip.Items.length) {
                        angular.forEach(trip.Items, function(item, k) {
                            item.collapse = false;
                            //item.ExpensePeriodFrom = new Date(item.ExpensePeriodFrom * 1000);

                            if (! $scope.report.IsApproverEdited && ! $scope.report.IsSubmitter) {
                                item.Approved = item.Claimed;
                                if (item.IsClaimed) {
                                    item.IsApproved = true;
                                    trip.Approved += parseFloat(item.Approved);
                                } else {
                                    item.IsApproved = false;
                                    trip.IsApproved = false;
                                    $scope.report.IsAllApproved = false;
                                }
                            }
                            trip.Amount += parseFloat(item.Amount);
                        });
                    }

                    if (! $scope.report.IsApproverEdited && ! $scope.report.IsSubmitter) {
                        $scope.report.Approved += trip.Approved;
                    }

                    $scope.report.Amount += parseFloat(trip.Amount);

//                if (trip.EndDate) {
//                    trip.EndDate = new Date(trip.EndDate);
//                }

//                $scope.report.Amount += parseFloat(trip.Amount);
                });

                $scope.report.DeletedFileIDs = [];
                $scope.report.RemovedTrips = [];

                $('#report-long-title').html('Travel Report for ' + $scope.report.Title);
                $timeout(function() {
                    //$('#report-title a').ellipsis();
                    $(window).resize(function() {
                        //$('#report-title a').ellipsis();
                    });
                });

                $scope.checkMemoEditable();

                $timeout(function() {
                    $scope.quickSaveForApprovedValue();
                });
            }, function(response) {
                if (response.status !== 200) {
                    $.showMessageBox({content: response.data.message.join('<br/>')});
                }
            });

            truncateReportDetailTableText('te', 'report');
        }
    });

    /**
     * Open popup for adding trip to report
     */
    $scope.openAddTripForm = function() {
        if (! $scope.report.IsSubmitter && $scope.report.ReportID) {
            return false;
        }

        $scope.responseMessage = [];
        $scope.trips = [];

        Restangular.one('trips?addTrip').getList().then(function(response) {
//            angular.forEach(response, function(v, k) {
//                if (v.StartDate) {
//                    v.StartDate = new Date(v.StartDate);
//                }
//
//                if (v.EndDate) {
//                    v.EndDate = new Date(v.EndDate);
//                }
//            });

            $scope.trips = response;
        }, function(response) {
            if (response.status !== 200) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            }
        });

        jQuery('#addTripBox').modal('show');
    }

    /**
     * Pick a trip to report
     * @param trip
     */
    $scope.pickTrip = function(trip) {
        if (trip.IsChecked) {
            $scope.selectedTripIDs.push(trip.TripID);
            $scope.selectedAmount += parseFloat(trip.Amount);
        } else {
            var tmp = [];

            angular.forEach($scope.selectedTripIDs, function(v, k) {
                if (v == trip.TripID) {
                    $scope.selectedAmount -= parseFloat(trip.Amount);
                } else {
                    tmp.push(v);
                }
            });

            $scope.selectedTripIDs = tmp;
        }
    }

    /**
     * Reset selected trip in Add trip to report popup
     */
    $scope.reset = function() {
        $scope.selectedTripIDs = [];
        $scope.selectedAmount = 0;

        angular.forEach($scope.trips, function(v, k) {
            v.IsChecked = false;
        });
    }

    /**
     * Get detail of selected trip items and append to report detail
     */
    $scope.addToReport = function() {
        var tripIDs = $scope.selectedTripIDs;
        if (! tripIDs.length) {
            $scope.responseMessage = ['You need to specified at least one trip.'];
        } else {
            Restangular.one('reports').customPUT({'ReportID': $scope.report.ReportID, 'Field': 'TripIDs', 'Value' : tripIDs}, 'quick-save')
                .then(function(response) {
                    //Reload report list
                    $scope.loadReportList(false);
                    //Reload trip list
                    $scope.$emit('LOAD_TRIP_LIST');
                    //Get items of trips to show
                    Restangular.one('trips').customGET('items', {tripIDs: $scope.selectedTripIDs.join(',')}).then(function(response) {
                        var tmp = [];
                        angular.forEach(response, function(v, k) {
                            var isExist = false;
                            if (angular.isDefined($scope.report.Trips) && $scope.report.Trips.length) {
                                for (var i in $scope.report.Trips) {
                                    if ($scope.report.Trips[i].TripID == v.TripID) {
                                        isExist = true;
                                        break;
                                    }
                                }
                            }

                            if (!isExist) {
                                tmp.push(v);
                            }
                        });

                        if (angular.isDefined($scope.report.Trips) && $scope.report.Trips.length) {
                            $scope.report.Trips = $scope.report.Trips.concat(tmp);
                        } else {
                            $scope.report.Trips = tmp;
                        }

                        $scope.report.IsAllApproved = 0;
                        $scope.report.IsClaimed = true;
                        $scope.report.Amount = 0;
                        $scope.report.Claimed = 0;
                        angular.forEach($scope.report.Trips, function(trip, k) {
                            var index = $scope.report.RemovedTrips.indexOf(trip.TripID);
                            if (index > -1) {
                                $scope.report.RemovedTrips.splice(index, 1);
                            }
                            trip.Claimed = 0;
                            if (trip.Items.length) {
                                angular.forEach(trip.Items, function(item, k) {
                                    //item.ExpensePeriodFrom = new Date(item.ExpensePeriodFrom * 1000);
                                    item.ReportMemos = [];
                                    item.IsClaimed = true;
                                    item.Claimed = item.Amount;
                                    trip.Claimed += parseFloat(item.Claimed);
                                });
                            }

                            trip.IsNew = 1;
                            trip.IsClaimed = true;
                            $scope.report.Amount += parseFloat(trip.Amount);
                            $scope.report.Claimed += parseFloat(trip.Claimed);
                        });

                        $scope.reset();
                        jQuery('#addTripBox').modal('hide');
                    }, function(response) {
                        if (response.status !== 200) {
                            $scope.responseMessage = response.message;
                        }
                    });
                }, function(response) {
                    $scope.responseMessage = response.message;
                });
        }
    }

    $rootScope.$on('POST_CREATE_TRIP', function(e, report, tripForNewReport) {
        $scope.loadReportDetail(report.ReportID);
        $scope.report = report;

        Restangular.one('trips').customPOST({'ReportID': report.ReportID, 'TripID' : tripForNewReport.TripID}, 'add-to-report').then(function(response) {
                //Get items of trips to show
                Restangular.one('trips').customGET('items', {tripIDs: tripForNewReport.TripID}).then(function(response) {
                    var tmp = [];
                    angular.forEach(response, function(v, k) {
                        var isExist = false;
                        if (angular.isDefined($scope.report.Trips) && $scope.report.Trips.length) {
                            for (var i in $scope.report.Trips) {
                                if ($scope.report.Trips[i].TripID == v.TripID) {
                                    isExist = true;
                                    break;
                                }
                            }
                        }

                        if (!isExist) {
                            tmp.push(v);
                        }
                    });

                    if (angular.isDefined($scope.report.Trips) && $scope.report.Trips.length) {
                        $scope.report.Trips = $scope.report.Trips.concat(tmp);
                    } else {
                        $scope.report.Trips = tmp;
                    }

                    $scope.report.IsAllApproved = 0;
                    $scope.report.IsClaimed = true;
                    $scope.report.Amount = 0;
                    $scope.report.Claimed = 0;
                    angular.forEach($scope.report.Trips, function(trip, k) {
                        var index = $scope.report.RemovedTrips.indexOf(trip.TripID);
                        if (index > -1) {
                            $scope.report.RemovedTrips.splice(index, 1);
                        }
                        trip.Claimed = 0;
                        if (trip.Items.length) {
                            angular.forEach(trip.Items, function(item, k) {
                                //item.ExpensePeriodFrom = new Date(item.ExpensePeriodFrom * 1000);
                                item.ReportMemos = [];
                                item.IsClaimed = true;
                                item.Claimed = item.Amount;
                                trip.Claimed += parseFloat(item.Claimed);
                            });
                        }

                        trip.IsNew = 1;
                        trip.IsClaimed = true;
                        $scope.report.Amount += parseFloat(trip.Amount);
                        $scope.report.Claimed += parseFloat(trip.Claimed);
                    });

                    $scope.reset();
                    $scope.quickSaveForClaimedValue();

                    //Reload trip list
                    $scope.$emit('LOAD_TRIP_LIST');
                }, function(response) {
                    if (response.status !== 200) {
                        $scope.responseMessage = response.message;
                    }
                });

                }, function(response) {
                    if (response.status !== 200) {
                        $.showMessageBox({content: response.data.message.join('<br/>')});
                    }
                });
    });

    /**
     * Remove selected trip from report
     */
    $scope.removeTrip = function() {
        if (! $scope.report.IsSubmitter) {
            return false;
        }

        var tmp = [];
        var removedTripsCount = 0;
        var oldRemovedTrips = angular.copy($scope.report.RemovedTrips);

        angular.forEach($scope.report.Trips, function(v, k) {
            if (v.IsChecked) {
                //Push to the removed trips array
                $scope.report.RemovedTrips.push(v.TripID);
                removedTripsCount++;
            }
        });

        if (! removedTripsCount) {
            $.showMessageBox({content: 'Please select trip(s) to remove.'});
        } else {
            Restangular.one('reports').customPUT({'ReportID': $scope.report.ReportID, 'Field': 'RemovedTrips', 'Value' : $scope.report.RemovedTrips}, 'quick-save')
                .then(function(response) {
                    angular.forEach($scope.report.Trips, function(v, k) {
                        if (!v.IsChecked) {
                            tmp.push(v);
                        } else {
                            $scope.report.Amount -= parseFloat(v.Amount);
                            if ($scope.report.Amount < 0) {
                                $scope.report.Amount = 0.00;
                            }
                            $scope.report.Claimed -= parseFloat(v.Claimed);
                            if ($scope.report.Claimed < 0) {
                                $scope.report.Claimed = 0.00;
                            }
                            $scope.report.Approved -= parseFloat(v.Approved);
                            if ($scope.report.Approved < 0) {
                                $scope.report.Approved = 0.00;
                            }
                        }
                    });

                    $scope.report.Trips = tmp;

                    $scope.loadReportList(false);
                    $scope.$emit('LOAD_TRIP_LIST');

                }, function(response) {
                    $.showMessageBox({content: response.data.message.join('<br/>')});
                    //Reset the value to its old value
                    $scope.report.RemovedTrips = oldRemovedTrips;
                });
        }
    }

    /**
     * Save report
     */
    $scope.save = function(report, submit) {
        if (! report.IsSubmitter && report.ReportID) {
            return false;
        }

        if (report.ReportID) {
            Restangular.one('reports').customPUT(report, '').then(function(response) {
                $scope.report.Status = response.Status;
                $scope.report.Reference = response.Reference;
                $scope.report.RemovedTrips = [];
                $scope.loadReportList(false);
                if (response.RefreshTripList) {
                    $scope.$emit('LOAD_TRIP_LIST');
                }
                if (typeof submit !== 'undefined') {
                    $.showMessageBox({content: 'The report is submitted.'});
                } else {
                    $.showMessageBox({content: 'The report is saved.'});
                }
            }, function(response) {
                $.showMessageBox({content: response.data.message});
                //$scope.responseMessage = response.data.message;
            });
        } else {
            Restangular.all('reports').post(report).then(function(response) {
                $scope.report.ReportID = response.ReportID;
                $scope.report.Status = response.Status;
                if (angular.isDefined($scope.tripInfo)) {
                    $scope.loadReportList(false);
                    $scope.$emit('LOAD_TRIP_LIST');
                    $scope.$emit('LOAD_TRIP_DETAIL', $scope.tripInfo.tripID, $scope.tripInfo.filterType, $scope.tripInfo.dateFrom, $scope.tripInfo.dateTo);
                    $('#report-detail-wrapper').hide();
                    $('#trip-detail-wrapper').show();
                } else {
                    $scope.loadReportList(true);
                }

            }, function(response) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            });
        }

    }

    $scope.quickSave = function(id, field, value, oldValue, successCallback, failCallback) {
        $timeout(function(){
            $scope.report.editStatus = 'saving';
        });

      //If submitter email match approver email we will throw error
      if($scope.report.SubmitterEmail === value){
        //Show message box to user
        $.showMessageBox({
          content: "Invalid email - Submitter cannot be Approver"
        });

        //set edit status
        $timeout(function(){
          $scope.report.editStatus = '';
        });

        //Update old approver email to current report item
        $scope.report.ApproverEmail = (oldValue) ? oldValue : '';
        return false;
      }

        Restangular.one('reports').customPUT({'ReportID': id, 'Field': field, 'Value' : value}, 'quick-save').then(function(response) {
            $scope.report.editStatus = '';
            $scope.report.Status = response.Status;
            $scope.report.Reference = response.Reference;
            $('#report-long-title').html('Travel Report for ' + $scope.report.Title);

            if (field == 'IsSubmitted') $.showMessageBox({content: 'The report is submitted.'});
            $scope.loadReportList(false);

            if (response.RefreshTripList) {
                $scope.$emit('LOAD_TRIP_LIST');
                $rootScope.$emit('RELOAD_RECEIPT_LIST', true);
            }

            if (typeof successCallback != 'undefined') successCallback();
        }, function(response) {
            $scope.report.editStatus = '';
            $.showMessageBox({content: response.data.message.join('<br/>')});
            //Reset the value to its old value
            $scope.report[field] = oldValue;
          //Call back
            if (typeof failCallback != 'undefined') failCallback();
        });
    }

    $scope.quickSaveForClaimedValue = function() {
        var putObj = {};
        putObj.IsClaimed = $scope.report.IsClaimed;
        putObj.Claimed = $scope.report.Claimed;
        putObj.Trips = [];
        if ($scope.report.Trips.length) {
            for (var i = 0; i < $scope.report.Trips.length; i++) {
                var putChildObj = {
                    TripID: $scope.report.Trips[i].TripID,
                    IsClaimed: $scope.report.Trips[i].IsClaimed,
                    Claimed: $scope.report.Trips[i].Claimed
                };

                putChildObj.Items = [];
                if ($scope.report.Trips[i].Items.length) {
                    for (var j = 0; j < $scope.report.Trips[i].Items.length; j++) {
                        putChildObj.Items.push({
                            ItemID: $scope.report.Trips[i].Items[j].ItemID,
                            IsClaimed: $scope.report.Trips[i].Items[j].IsClaimed,
                            Claimed: $scope.report.Trips[i].Items[j].Claimed
                        });
                    }
                }

                putObj.Trips.push(putChildObj);
            }
        }

        Restangular.one('reports').customPUT({'ReportID': $scope.report.ReportID, 'Field': 'Claimed', 'Value' : putObj}, 'quick-save')
            .then(function(response) {
                $scope.report.Status = response.Status;
                $scope.loadReportList(false);
                if (response.RefreshTripList) {
                    $scope.$emit('LOAD_TRIP_LIST');
                }
            }, function(response) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            });
    }

    $scope.quickSaveForApprovedValue = function() {
        var putObj = {};
        putObj.IsApproved = $scope.report.IsApproved;
        putObj.IsAllApproved = $scope.report.IsAllApproved;
        putObj.Approved = $scope.report.Approved;
        putObj.Trips = [];
        if ($scope.report.Trips.length) {
            for (var i = 0; i < $scope.report.Trips.length; i++) {
                var putChildObj = {
                    TripID: $scope.report.Trips[i].TripID,
                    IsApproved: $scope.report.Trips[i].IsApproved,
                    Approved: $scope.report.Trips[i].Approved
                };

                putChildObj.Items = [];
                if ($scope.report.Trips[i].Items.length) {
                    for (var j = 0; j < $scope.report.Trips[i].Items.length; j++) {
                        putChildObj.Items.push({
                            ItemID: $scope.report.Trips[i].Items[j].ItemID,
                            IsApproved: $scope.report.Trips[i].Items[j].IsApproved,
                            Approved: $scope.report.Trips[i].Items[j].Approved
                        });
                    }
                }

                putObj.Trips.push(putChildObj);
            }
        }

        Restangular.one('reports').customPUT({'ReportID': $scope.report.ReportID, 'Field': 'Approved', 'Value' : putObj}, 'quick-save')
            .then(function(response) {
                $scope.report.Status = response.Status;
                $scope.loadReportList(false);
                if (response.RefreshTripList) {
                    $scope.$emit('LOAD_TRIP_LIST');
                }
            }, function(response) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            });
    }

    /**
     * Save report
     */
    $scope.submit = function() {
        if (! $scope.report.IsSubmitter && $scope.report.ReportID) {
            return false;
        }
        var oldValue = {
            IsSubmitted : angular.copy($scope.report.IsSubmitted),
            IsApproved : angular.copy($scope.report.IsApproved),
            IsApproverEdited : angular.copy($scope.report.IsApproverEdited)
        };

        var successCallback = function () {
            $scope.report.IsSubmitted      = 1;
            $scope.report.IsApproved       = 0;
            $scope.report.IsApproverEdited = 0;
        };
        var failCallback = function () {
            $scope.report.IsApproved       = oldValue.IsApproved;
            $scope.report.IsApproverEdited = oldValue.IsApproverEdited;
        };

        $scope.quickSave($scope.report.ReportID, 'IsSubmitted', $scope.report.IsSubmitted, oldValue.IsSubmitted, successCallback, failCallback);

        $scope.checkMemoEditable();
}

    /**
     * Save report
     */
    $scope.approve = function() {
        if ($scope.report.IsSubmitter || ! $scope.report.ReportID) {
            return false;
        }

        if ($scope.report.ReportID) {
            $scope.report.IsApproved = 1;
            Restangular.one('reports').customPUT($scope.report, 'approve').then(function(response) {
                $scope.report.Status = response.Status;
                $scope.loadReportList(false);
                $.showMessageBox({content: 'The report is approved.'});
                $scope.checkMemoEditable();
            }, function(response) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
                $scope.report.IsApproved = 0;
                $scope.checkMemoEditable();
            });
        }
    }

    /**
     * Save report
     */
    $scope.reject = function() {
        if ($scope.report.IsSubmitter || ! $scope.report.ReportID) {
            return false;
        }

        if ($scope.report.ReportID) {
            $scope.report.IsApproved = 2;
            Restangular.one('reports').customPUT($scope.report, 'approve').then(function(response) {
                $scope.report.Status = response.Status;
                $scope.loadReportList(false);
                $.showMessageBox({content: 'The report is rejected.'});
                $scope.checkMemoEditable();
            }, function(response) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
                $scope.report.IsApproved = 0;
                $scope.checkMemoEditable();
            });
        }
    }

    /*
     * Print Report.
     * show dialog preview report
     * */
    $scope.printReport = function(){
        $scope.isGeneratingReport = false;
        $('.print-report-wrap').show();
      $timeout(function(){
        $('.report-details-all').click();
      });
    }
    /*
     * Export Report To PDF
     * */
    $scope.exportPDF = function() {
        $('.print-download').show();
        window.location.href = API_URL + '/reports/download-pdf?filePath=' +  $scope.UrlToDownload;
        $timeout(function(){
            $('.print-download').hide();
            $('.print-report-wrap').hide();
        },1000);
    }

    /**
     * Selection action to generation report
     */
    $scope.selectActionReport = function (action) {
        $scope.UrlToDownload = $scope.urlReport = "";
        $('.loadding-pdf').show();
        $('.box-pdf-viewer object').hide();
        $scope.printAction = action;
        Restangular.one('reports').customGET('print?reportID=' + $scope.reportId + '&itemType=' + $scope.printAction).then(function (response) {
            $timeout(function(){
                if(response.FilePath) {
                    $scope.urlReport =   API_URL + '/files/' + response.FilePath;
                    $scope.urlReport = CLIENT_URL + '/components/pdfJS/web/viewer.html?file=' + $scope.urlReport;
                    $scope.UrlToDownload = response.FilePath;
                }
                $scope.isGeneratingReport = true;
                $('.loadding-pdf').hide();
                $('.box-pdf-viewer object').show();
            });
        });
    }

    /**
     * Set all trip to be claimed
     */
    $scope.setClaimedForReport = function(report, status) {
        if (status) {
            report.IsClaimed = status;
        }

        angular.forEach(report.Trips, function(trip, k) {
            trip.IsClaimed = report.IsClaimed;
        });

        angular.forEach(report.Trips, function(trip, k) {
            $scope.setClaimedForTrip(trip, report, false);
        });

        $scope.quickSaveForClaimedValue();
    }

    /**
     * Set all items in a trip to be claimed
     */
    $scope.setClaimedForTrip = function(trip, report, caller) {
        report.Claimed -= trip.Claimed;
        trip.Claimed = 0;
        angular.forEach(trip.Items, function(v, k) {
            v.IsClaimed = trip.IsClaimed;
        });
//        if (!trip.IsClaimed) {
//            report.Claimed -= trip.Claimed;
//        }
        angular.forEach(trip.Items, function(v, k) {
            $scope.setClaimedForItem(v, trip, report, false, false);
        });

        $scope.setReportClaimedState(report);

        // If caller = false, this was called from $scope.setClaimedForReport so we do not need to call quickSaveForClaimedValue()
        // If caller = true, this was called directly from HTML, so we need to call quickSaveForClaimedValue()
        if (caller) $scope.quickSaveForClaimedValue();
    }

    $scope.setClaimedForItem = function(item, trip, report, updateReport, caller) {
        updateReport = typeof updateReport == 'undefined' ? true : updateReport;
        var price      = parseFloat(item.Claimed);
        trip.Claimed   = parseFloat(trip.Claimed);
        report.Claimed = parseFloat(report.Claimed);

        if (item.IsClaimed) {
            trip.Claimed += price;
            report.Claimed += price;
        } else {
            if (trip.Claimed - price >= 0) {
                trip.Claimed -= price;
            } else {
                trip.Claimed = 0;
            }
            if (updateReport) {
                if (report.Claimed - price >= 0) {
                    report.Claimed -= price;
                } else {
                    report.Claimed = 0;
                }
            }
        }

        $scope.setTripItemClaimedState(trip);
        $scope.setReportClaimedState(report);
        // If caller = false, this was called from setClaimedForTrip() so we do not need to call quickSaveForClaimedValue()
        // If caller = true, this was called directly from HTML, so we need to call quickSaveForClaimedValue()
        if (caller) $scope.quickSaveForClaimedValue();
    }

    /**
     * Get claimed state of trip. When user un-check all claim amount we will set state
     * of trip claim to be false
     *
     * @param trip
     * @returns {{allClaimed: boolean, allUnClaimed: boolean}}
     */
    $scope.setTripItemClaimedState = function(trip) {
//        var totalItem   = trip.Items.length;
        var itemUnclaimed = 0;

        angular.forEach(trip.Items, function(v, k) {
            if (!v.IsClaimed) {
                itemUnclaimed++;
            }
        });

        if (itemUnclaimed) {
            trip.IsClaimed = false;
        } else {
            trip.IsClaimed = true;
        }
    }

    $scope.setReportClaimedState = function(report) {
        var tripUnclaimed = 0;

        angular.forEach(report.Trips, function(v, k) {
            if (! v.IsClaimed) {
                tripUnclaimed++;
            }
        });

        if (tripUnclaimed) {
            report.IsClaimed = false;
        } else {
            report.IsClaimed = true;
        }
    }

    /**
     * Set all trip to be approved
     */
    $scope.setApprovedForReport = function(report, status) {
        if (status) {
            report.IsAllApproved = status;
        }

        report.Approved = 0;

        //Algorithm: Toggle status is decided by the first item
        var tmpIsApproved = false;
        scanItems :
        for (var i = 0; i < report.Trips.length; i++) {
            for (var k = 0; k < report.Trips[i].Items.length; k++) {
                if (report.Trips[i].Items[k].IsClaimed){
                    tmpIsApproved = !(report.Trips[i].Items[k].IsApproved);
                    break scanItems;
                }
            }
        }

        angular.forEach(report.Trips, function(trip, k) {
            trip.IsApproved = report.IsAllApproved;
            trip.toggleItemIsApproved = tmpIsApproved;
        });

        angular.forEach(report.Trips, function(trip, k) {
            $scope.setApprovedForTrip(trip, report, false);
        });

        $scope.quickSaveForApprovedValue();
    }

    /**
     * Set all items in a trip to be approved
     */
    $scope.setApprovedForTrip = function(trip, report, caller) {
        trip.Approved = 0;

        var statusScanned = false;
        angular.forEach(trip.Items, function(v, k) {
            //Hack: caller = true means function is called from view
            //and trip IsApproved toggle status is taken from the first (claimed) item status manually
            if (caller && v.IsClaimed && !statusScanned){
                trip.toggleItemIsApproved = !v.IsApproved;
                statusScanned = true;
            }

            v.IsApproved = trip.toggleItemIsApproved && v.IsClaimed;
        });

        angular.forEach(trip.Items, function(v, k) {
            $scope.setApprovedForItem(v, trip, report, true);
        });

        $scope.setReportApprovedState(report);

        // If caller = false, this was called from setApprovedForReport() so we do not need to call quickSaveForApprovedValue()
        // If caller = true, this was called directly from HTML, so we need to call quickSaveForApprovedValue()
        if (caller) $scope.quickSaveForApprovedValue();
    }

    $scope.setApprovedForItem = function(item, trip, report, updateReport, caller) {
        updateReport = typeof updateReport == 'undefined' ? true : updateReport;
        var price       = parseFloat(item.Approved);
        trip.Approved   = parseFloat(trip.Approved);
        report.Approved = parseFloat(report.Approved);

        if (item.IsApproved) {
            trip.Approved += price;
            report.Approved += price;
        } else {
            if (item.IsClaimed) {
                if (trip.Approved - price >= 0) {
                    trip.Approved -= price;
                } else {
                    trip.Approved = 0;
                }

                if (updateReport) {
                    if (report.Approved - price >= 0) {
                        report.Approved -= price;
                    } else {
                        report.Approved = 0;
                    }
                }
            }
        }

        $scope.setTripItemApprovedState(trip);
        $scope.setReportApprovedState(report);
        //$scope.$broadcast('UPDATE_REPORT_CLAIM');

        // If caller = false, this was called from setApprovedForTrip() so we do not need to call quickSaveForApprovedValue()
        // If caller = true, this was called directly from HTML, so we need to call quickSaveForApprovedValue()
        if (caller) $scope.quickSaveForApprovedValue();
    }

    /**
     * Get claimed state of trip. When user un-check all claim amount we will set state
     * of trip claim to be false
     *
     * @param trip
     * @returns {{allClaimed: boolean, allUnClaimed: boolean}}
     */
    $scope.setTripItemApprovedState = function(trip) {
//        var totalItem   = trip.Items.length;
        var itemUnapproved = 0;

        angular.forEach(trip.Items, function(v, k) {
            if (!v.IsApproved) {
                itemUnapproved++;
            }
        });

        if (itemUnapproved) {
            trip.IsApproved = false;
        } else {
            trip.IsApproved = true;
        }
    }

    $scope.setReportApprovedState = function(report) {
        var tripUnapproved = 0;

        angular.forEach(report.Trips, function(v, k) {
            if (! v.IsApproved) {
                tripUnapproved++;
            }
        });

        if (tripUnapproved) {
            report.IsAllApproved = false;
        } else {
            report.IsAllApproved = true;
        }
    }

    /**
     * Archive the report in report detail screen
     */
    $scope.archiveReport = function() {
        if (! $scope.report.ReportID) {
            return;
        }

        $.showMessageBox({
            content: ! $scope.report.IsArchived ? 'Do you want to archive this report?' : 'Do you want to unarchive this report?',
            type: 'confirm',
            onYesAction: function() {
                $timeout(function() {
                    Restangular.one('reports').customPUT({ReportIDs: $scope.report.ReportID, Archived: ! $scope.report.IsArchived}, 'archive')
                        .then(function(response) {
                            $scope.loadReportList(true);
                        }, function(response) {
                            $.showMessageBox({content: response.data.message.join('<br/>')});
                        });
                })
            }
        });
    }

    $scope.deleteReport = function() {
        //Does not allow deleting if report ID is not provided or report is not draft
        if (! $scope.report.ReportID || $scope.report.IsSubmitted) {
            return;
        }

        $.showMessageBox({
            content: 'Do you want to delete this report?',
            type: 'confirm',
            onYesAction: function() {
                $timeout(function() {
                    Restangular.one('reports').remove({ReportIDs: $scope.report.ReportID}).then(function(response) {
                        $scope.loadReportList(true);
                        $scope.$emit('LOAD_TRIP_LIST');
                    }, function(response) {
                        $.showMessageBox({content: response.data.message.join('<br/>')});
                    })
                })
            }
        });
    }

    /**
     * File upload
     */

    /**
     * removeAttachment
     */
    $scope.removeAttachment = function(obj, el, index) {
        obj.DeletedFileIDs.push(el.FileID);
        obj.Attachments.splice(index, 1);
    }

    /**
     * Hide the file popover or any element we need to hide
     */
    $scope.hideElement = function(el) {
        jQuery(el).css('display', 'none');
    }


    /**
     * Listener for updating report claimed amount and its state
     */
    $scope.$on('UPDATE_REPORT_CLAIM', function() {
        var totalTrip = $scope.report.Trips.length;
        var totalTripClaimed = 0;
        $scope.report.Claimed = 0;
        angular.forEach($scope.report.Trips, function(trip, k) {
            $scope.report.Claimed += trip.Claimed;

            if (trip.IsClaimed) {
                totalTripClaimed++;
            }
        });

        if (totalTripClaimed == totalTrip) {
            $scope.report.IsClaimed = true;
        } else {
            $scope.report.IsClaimed = false;
        }
    });

    $scope.loadReportList = function(returnToList) {
        $scope.$emit('LOAD_REPORT_LIST');
        if (returnToList) {
            $('#report-detail-wrapper').hide();
            $('#report-list-wrapper').show();
        }
    }


    $scope.collapsePECategories = function(trip) {
        trip.peCatCollapseStatus = !trip.peCatCollapseStatus;
        if (trip.Items.length) {
            angular.forEach(trip.Items, function(item, k) {
                item.collapse = trip.peCatCollapseStatus;
            });
        }
    }

    $scope.triggerTruncateTable = function(table) {
        if (typeof table == 'undefined') table = 'cat';
        console.log('triggerTruncateTable ' + table);
        $timeout(function(){
            truncateReportDetailTableText('te', table);
        }, 200);
    };

    $scope.$watch('report.Date', function (newVal, oldVal, scope) {
        if (typeof $scope.report !== 'undefined' && typeof oldVal !== 'undefined') {
            if (newVal != oldVal && /^\d{4}-\d{2}-\d{2}$/.test(newVal)) {
                $scope.report.Reference = 'R' + newVal.replace(/-/g, '');
            }
        }
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

}]);
