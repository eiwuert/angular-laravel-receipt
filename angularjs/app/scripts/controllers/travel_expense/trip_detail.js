rciSpaApp.controller('TripDetailCtrl', ['$scope', '$route', 'Restangular', '$location', '$rootScope', '$timeout', function($scope, $route, Restangular, $location, $rootScope, $timeout){
    $scope.viewTrip = function(id) {
        $scope.$emit('LOAD_TRIP_DETAIL', id);
    }
    $scope.tripList = [];
    $scope.filterType = 'all';
    $scope.dateFrom;
    $scope.dateTo;

  $rootScope.inAppScreen == 'TRIP_DETAIL';

    $scope.selectedReport = null;
    $scope.responseMessage = [];

    //Hack: Manually trigger resize height function for pe table in Trip Detail in case it
    //cannot trigger automatically in some cases:
    //Step 1: set max height to fix the limit height of table
    $scope.fixLimitHeightForPeTable = function () {
        var h = $('#td-pe-child').height();
        $('#td-pe-child').css('max-height', h + 'px');
    }
    $timeout(function(){
        $scope.fixLimitHeightForPeTable();
    })
    //Step 2: re-caculate max height of table on window resize
    $(window).resize(function() {
        $('#td-pe-child').css('max-height', 'none');
        $timeout(function(){
            $scope.fixLimitHeightForPeTable();
        }, 1000)
    });

    /**
     * Listener to load trip detail
     */
    $rootScope.$on('LOAD_TRIP_DETAIL', function(event, tripId, filterType, dateFrom, dateTo, reload) {
        reload = typeof reload != 'undefined' ? reload : true;

        if (reload) {
            jQuery('.page-app').hide();
            jQuery('#ngview-wrapper').hide();
            jQuery('#trip-detail-wrapper').show();
        }

        tripId = parseInt(tripId);

        if (angular.isDefined(filterType)) {
            $scope.filterType = filterType;
        }

        if (angular.isDefined(dateFrom)) {
            $scope.dateFrom = dateFrom;
        }

        if (angular.isDefined(dateTo)) {
            $scope.dateTo = dateTo;
        }

        Restangular.one('trips').get({tripID: tripId}).then(function(response) {
            $scope.currentTrip = response;

            //Parse numeric strings to integer type
            $scope.currentTrip.Leg = parseInt($scope.currentTrip.Leg);
            $scope.currentTrip.IsArchived = parseInt($scope.currentTrip.IsArchived);

            if ($scope.currentTrip.Leg) {
                $scope.currentTrip.LegText = 'Leg ' + $scope.currentTrip.Leg;
                $scope.currentTrip.LegInName = '- Leg ' + $scope.currentTrip.Leg;
            } else {
                $scope.currentTrip.LegText = '';
                $scope.currentTrip.LegInName = '';
            }

            var arrangedArrays = arrangeToMultiLevelCats($scope.currentTrip.Categories);
            $scope.itemCategories = arrangedArrays[0];
            $scope.itemsOnlyList = arrangedArrays[1];
            $scope.tempCategories = $scope.itemCategories;

            $scope.$emit('TRIP_GET_CURRENT_LIST', function(givenList){
                $scope.tripList = givenList;
            });

            $timeout(function(){
                truncatePETableText('te');
                $('span.limit-title a').ellipsis();
                $('.trip-detail-title h4.limit-title span').html('Trip Expense for ' + $('span.limit-title a').html());
            });

            $(window).resize(function() {
                $('span.limit-title a').ellipsis();
                $('.trip-detail-title h4.limit-title span').html('Trip Expense for ' + $('span.limit-title a').html());
            });
        }, function(response) {
            if (response.status !== 200) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            }
        });
    });

    $scope.originTrip = {};
    /**
     * Fill trip data when user hover trip name
     */
    $scope.fillTripData = function(trip) {
        $scope.originTrip   = $scope.currentTrip;
        $scope.currentTrip = trip;

        $timeout(function() {
            $('span.limit-title a').ellipsis();
            $('.trip-detail-title h4.limit-title span').html('Trip Expense for ' + $('span.limit-title a').html());
        });
    }

    /**
     * Restore trip data to original
     */
    $scope.restoreTripData = function() {
        $scope.currentTrip = $scope.originTrip;
        $timeout(function() {
            $('span.limit-title a').ellipsis();
            $('.trip-detail-title h4.limit-title span').html('Trip Expense for ' + $('span.limit-title a').html());
        });
    }

    /**
     * Save current trip information
     */
    $scope.saveTrip = function() {
        var items = [];
        var tmp = angular.copy($scope.currentTrip);

//        if (angular.isObject(tmp.StartDate)) {
//            tmp.StartDate = tmp.StartDate.toLocaleString();
//        }
//        if (angular.isObject(tmp.EndDate)) {
//            tmp.EndDate = tmp.EndDate.toLocaleString();
//        }

        var pattern = /^\d{4}-\d{2}-\d{2}$/;
        if (pattern.test(tmp.StartDate)) {
            tmp.StartDate += 'T00:00:00.000Z';
        }

        if (pattern.test(tmp.EndDate)) {
            tmp.EndDate += 'T00:00:00.000Z';
        }

        items.push(tmp);
        Restangular.one('trips').customPUT({"data": items}, '').then(function(response) {
            if ($scope.currentTrip.TripID == response[0].TripID) {
                $scope.currentTrip.State = response[0].State;
            }

            for (var i = 0; i < $scope.tripList.length; i++) {
                if ($scope.tripList[i].TripID == $scope.currentTrip.TripID) {
                    var cloneTrip = {};
                    jQuery.extend(cloneTrip, $scope.currentTrip);
                    delete cloneTrip.Categories;
                    delete cloneTrip.List;

                    $scope.tripList[i] = cloneTrip;
                    break;
                }
            }

            $scope.loadTripList(false);
        }, function(response) {
            $.showMessageBox({content: response.data.message.join('<br/>')});
        });
    }

    $scope.quickSave = function(id, field, value, oldValue) {
        $timeout(function(){
            $scope.currentTrip.editStatus = 'saving';
        })

        Restangular.one('trips').customPUT({'TripID': id, 'Field': field, 'Value' : value}, 'quick-save')
            .then(function(response) {
                $scope.currentTrip.editStatus = '';
                if (field == 'StartDate' || field == 'Reference') {
                    $scope.currentTrip.State = response.State;
                    $scope.currentTrip.Reference = response.Reference;
                }

                for (var i = 0; i < $scope.tripList.length; i++) {
                    if ($scope.tripList[i].TripID == $scope.currentTrip.TripID) {
                        var cloneTrip = {};
                        jQuery.extend(cloneTrip, $scope.currentTrip);
                        delete cloneTrip.Categories;
                        delete cloneTrip.List;

                        $scope.tripList[i] = cloneTrip;
                        break;
                    }
                }

                $scope.loadTripList(false);
            }, function(response) {
                $scope.currentTrip.editStatus = '';
                $.showMessageBox({content: response.data.message.join('<br/>')});
                //Reset the value to its old value
                $scope.currentTrip[field] = oldValue;
            });
    }

    /**
     * Archive current trip
     */
    $scope.archiveTrip = function() {
        $.showMessageBox({
            content: ! $scope.currentTrip.IsArchived ? 'Do you want to archive this trip?' : 'Do you want to unarchive this trip?',
            type: 'confirm',
            onYesAction: function() {
                var items = [$scope.currentTrip.TripID];
                $timeout(function() {
                    Restangular.one('trips').customPUT({"TripIDs": items, 'Archived' : ! $scope.currentTrip.IsArchived}, 'archive')
                        .then(function(response) {
                            $scope.loadTripList(true);
                        }, function(response) {
                            $.showMessageBox({content: response.data.message.join('<br/>')});
                        });
                })
            }
        });
    }

    /**
     * Delete trip
     */
    $scope.delete = function() {
      console.log($scope.currentTrip);
        $.showMessageBox({
            content: '<p style="font-size: 16px;">Are you sure you want to delete this trip?</p>' +
                '<p style="font-size: 16px;">Please note, the receipt/invoice and items belonging to this trip will not be deleted from your ReceiptBox.</p>',
            type: 'confirm',
            onYesAction: function() {
                $timeout(function() {
                    Restangular.one('trips').remove({TripIDs: $scope.currentTrip.TripID}).then(function(response) {
                        $scope.loadTripList(true);
                        $rootScope.$broadcast('RELOAD_RECEIPT_LIST', true);
                    }, function(response) {
                        $.showMessageBox({content: response.data.message.join('<br/>')});
                    })
                })
            }
        });
    }

    /**
     * Delete selected item
     */
    $scope.deleteItems = function() {
        var itemDeleteList = [];
        $('#te-pe-item-list tbody tr[class^="cat-lv"].item .app-icon.icon-checkedbox-sqr').each(function(k, v) {
            itemDeleteList.push($(v).attr('data-id'));
        });

        if (itemDeleteList.length == 0) {
            $.showMessageBox({content: "Please select item(s) to remove.", boxTitle: 'REMOVE ITEM(S)', boxTitleClass: ''});
            return;
        }

        $.showMessageBox({
            content: '<p>Are you sure you want to remove selected item(s) from this trip?</p><p>Please note, the receipt/invoice relating to this item will not be removed from your ReceiptBox.</p>',
            boxTitle: 'REMOVE ITEM(S)',
            boxTitleClass: '',
            type: 'confirm',
            onYesAction: function() {
                $timeout(function() {
                    Restangular.one('categories').customPUT({ItemIDs: itemDeleteList.join(',')}, 'unassign').then(function(response) {
                        $scope.$emit('LOAD_TRIP_DETAIL', $scope.currentTrip.TripID);
                        $scope.$emit('LOAD_TRIP_LIST');
                        $rootScope.$broadcast('RELOAD_RECEIPT_LIST', true);

                        if ($scope.currentTrip.ReportID) {
                            $scope.$emit('LOAD_REPORT_LIST');
                        }
                    }, function(response) {
                        $.showMessageBox({content: response.data.message.join('<br/>')});
                    });
                });
            }
        });
    }

    $scope.numOfCatExpaned = 0;
    $scope.clicked = {
        checkAll : false,
        listItemOnly: false,
        collapseAll : true,
        numOfCatExpanded : 0
    };
    $scope.$watch('clicked.numOfCatExpanded', function(newValue, oldValue){
        if (newValue == 0) {
            $scope.clicked.collapseAll = true;
        }
    })

    /**
     * Toogle View Item/Category Mode
     */
    $scope.toogleViewItem = function() {
        $scope.clicked.listItemOnly = !$scope.clicked.listItemOnly;

        if ($scope.clicked.listItemOnly) {
            $scope.itemCategories = [];
        } else {
            $scope.itemCategories = $scope.tempCategories;
            $timeout(function(){
                truncatePETableText('te');
            })
        }
    }

    /**
     * Check all function
     */
    $scope.doMasterCheck = function () {
        $scope.clicked.checkAll = !$scope.clicked.checkAll;
        /*
         angular.forEach($scope.peItems, function (item, k) {
         item.check = $scope.clicked.checkAll;
         })
         if ($scope.clicked.listItemOnly == true ) {
         angular.forEach($scope.tmpPeList, function (catGroup, k) {
         angular.forEach(catGroup, function(row, kr){
         row.check = $scope.clicked.checkAll;
         })
         })
         } else {
         */
        angular.forEach($scope.itemCategories, function (catGroup, k) {
            angular.forEach(catGroup, function(row, kr){
                row.check = $scope.clicked.checkAll;
            })
        })
    }

  /*
   * Print Trip.
   * show dialog preview report
   * */
  $scope.printTrip = function(){

    $('.print-report-wrap').show();
    $scope.selectActionReport('all');
  }

  /*
   * Export Report To PDF
   * */
  $scope.exportPDF = function() {
    $('.print-download').show();
    window.location.href = API_URL + '/trips/download-pdf?filePath=' + $scope.UrlToDownload;
    $timeout(function(){
      $('.print-download').hide();
      $('.print-report-wrap').hide();
    },1500);
  }

  /*
   * Selection action to generation report
   */
  $scope.selectActionReport = function (action) {
    $scope.isGeneratingReport = false;
    $scope.UrlToDownload = $scope.urlReporat = "";
    $('.loadding-pdf').show();
    $('.box-pdf-viewer object').hide();
    $scope.printAction = action;
    var trips = [];
    angular.forEach($scope.tripList, function(v, k) {
      if (v.IsChecked == true || v.IsChecked == 1) {
        trips.push(v.TripID);
      }
    });

    Restangular.one('trips').customGET('print?tripID=' + $scope.currentTrip.TripID + '&itemType=' + $scope.printAction).then(function (response) {
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
  }

    /**
     * Collapse all function
     */
    $scope.performMasterCollapse = function(){
        $scope.clicked.collapseAll = !$scope.clicked.collapseAll;
        if ($scope.clicked.collapseAll == false) {
            $scope.clicked.numOfCatExpanded = $scope.itemCategories.length;
        } else {
            $scope.clicked.numOfCatExpanded = 0;
        }
        angular.forEach($scope.itemCategories, function(catGroup, k){
            angular.forEach(catGroup, function(row, kr){
                if (row.Depth == '0') {
                    row.expand = !$scope.clicked.collapseAll;
                } else {
                    row.masterCollapse = $scope.clicked.collapseAll;
                }
            });
        });
    }

    $scope.loadTripList = function(returnToList) {
        $scope.$emit('LOAD_TRIP_LIST');
        if (returnToList) {
            $('#trip-detail-wrapper').hide();
            $('#trip-list-wrapper').show();
        }

        //$scope.tripList = $scope.$emit('TRIP_GET_CURRENT_LIST');
    };

    //Trip info table: hide the dropdown icon when editing trip name
    $('#td-trip-name input').on('focus', function(){
        $('#td-trip-name i.icon-arrow-dropdown').hide();
    });
    $('#td-trip-name input').on('blur', function(){
        $('#td-trip-name i.icon-arrow-dropdown').show();
    });

    $scope.loadReportDetail = function(reportId) {
        $scope.$emit('LOAD_REPORT_DETAIL', reportId, 'all', $scope.dateFromDisplay, $scope.dateToDisplay);
    }

    $scope.openAddToReportForm = function() {
        $scope.responseMessage = [];
        $scope.reports = [];

        Restangular.one('reports?role=submitter&type=draft').getList().then(function(response) {
            $scope.reports = response;
        }, function(response) {
            if (response.status !== 200) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            }
        });

        jQuery('#addToReportBox').modal('show');
    }

    $scope.pickReport = function(report) {
        if ($scope.selectedReport != report) {
            $scope.selectedReport = report;
        }
    }

    $scope.addToReport = function() {
        if ($scope.selectedReport) {
            Restangular.one('trips').customPOST({
                TripID: $scope.currentTrip.TripID,
                ReportID: $scope.selectedReport.ReportID
            }, 'add-to-report').then(function(response) {
                    $scope.currentTrip.Report = response.Report;
                    $scope.currentTrip.ReportID = response.ReportDetail.ReportID;
                    $scope.report = response.ReportDetail;

                    Restangular.one('trips').customGET('items', {tripIDs: $scope.currentTrip.TripID}).then(function(response) {
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

                    $scope.quickSaveForClaimedValue();

                    //Reload trip list
                    $scope.$emit('LOAD_REPORT_LIST');
                    $scope.$emit('LOAD_TRIP_LIST');
                }, function(response) {
                    if (response.status !== 200) {
                        $scope.responseMessage = response.message;
                    }
                });

                    jQuery('#addToReportBox').modal('hide');

                }, function(response) {
                    if (response.status !== 200) {
                        $.showMessageBox({content: response.data.message.join('<br/>')});
                    }
                });
        } else {
            $.showMessageBox({content: 'You must select a report.'});
        }
    };

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
                if (response.RefreshTripList) {
                    $scope.$emit('LOAD_TRIP_LIST');
                }
            }, function(response) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            });
    }

}]);
