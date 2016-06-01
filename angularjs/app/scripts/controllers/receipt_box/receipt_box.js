rciSpaApp.controller('RBCtrl', function ($q, $scope, $timeout, $filter, $rootScope, Restangular, $route, $location, localStorageService, restAngularService, $compile, AwsS3Sdk) {
  $('#upload-receipt,.snap-rc').tooltip();

  /**
   * Mark receipt as opened and update its value in local storage
   * @param object receipt
   */
  $scope.markAsOpen = function (receipt) {
    angular.forEach($scope.receiptList, function (v, k) {
      if (v.ReceiptID == receipt.ReceiptID) {
        v.IsOpened = 1;
        v.NewReceiptUploaded = false;
        if (receipt.VerifyStatus == 0) {
          v.VerifyStatus = 1;
        }
      }
    });
  }

  $scope.paperReceipt = [];

  //The beginning date of filter
  try {
    var date = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
  } catch (err) {
    var date = new Date();
  }

  $scope.dateFrom = (date.getFullYear() - 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
  $scope.dateTo = date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
  $rootScope.manualReceipt = false;
  $scope.transaction = 'CreatedTime';
  $scope.filterType = 'all';
  $scope.uploadSelected = $rootScope.r

  $scope.allDate = 1;

  $scope.receiptList = [];

  //Store IDs of items/receipts which have checkbox selected
  $scope.itemDeleteList = [];
  $scope.receiptDeleteList = [];

  $scope.openFromApp = null;
  $scope.tmpTotalArchived = 0;
  $rootScope.keepUploadArea = false;
  //Use this flag to indicate whether the user is using the filters
  //$scope.filtering = false;

  //Use this flag to indicate whether the user is using the archived filter the first time
  $scope.filterArchivedFirstTime = true;

  //Use temporary filter variables before 'Go' button is clicked
  $scope.tmpFilterType = angular.copy($scope.filterType);
  $scope.tmpAllDate = angular.copy($scope.allDate);
  $scope.tmpDateFrom = angular.copy($scope.dateFrom);
  $scope.tmpDateTo = angular.copy($scope.dateTo);

  $scope.filterTypeList = [
    {code: 'all', name: 'All Receipts', newreceipt: 0, totalofRC: 0},
    {code: 'newReceipts', name: 'New Receipts', newreceipt: 0, totalofRC: 0},
    {code: 'digitalReceipts', name: 'Digital Receipts', newreceipt: 0, totalofRC: 0},
    {code: 'emailReceipts', name: 'Email Receipts', newreceipt: 0, totalofRC: 0},
    {code: 'paperReceipts', name: 'Paper Receipts', newreceipt: 0, totalofRC: 0},
    {code: 'manualReceipts', name: 'Manual Receipts', newreceipt: 0, totalofRC: 0},
    {code: 'nonReceipts', name: 'Non-Receipts', newreceipt: 0, totalofRC: 0},
    {code: 'paperInvoices', name: 'Paper Invoices', newreceipt: 0, totalofRC: 0},
    {code: 'electronicInvoices', name: 'Electronic Invoices', newreceipt: 0, totalofRC: 0}
  ];

  $scope.tmpFilterTypeName = 'All Receipts';

  $scope.tmpFilterDate = 'All Dates';
  angular.forEach($scope.filterTypeList, function (type, k) {

    if (type.code == $scope.tmpFilterType) {
      $scope.tmpFilterTypeName = type.name;
    }

  })

  $scope.selectFilterType = function (ftype) {
    $scope.tmpFilterTypeName = ftype.name;
    $scope.tmpFilterType = ftype.code;
  }

  //Format date for display
  $scope.dateFromDisplay = new Date($scope.tmpDateFrom).toString();
  $scope.dateToDisplay = new Date($scope.tmpDateTo).toString();

  /*
   * Load receipt items by pieces
   */

  var rbData = [];
  $scope.loadedAllReceipt = false;
  $scope.receiptListLimit = 5;
  $scope.receiptListOffset = 0;
  $scope.receiptListTotal = 0;

  $scope.loadMoreReceipt = function (direction, resetOffset, callbackLoadMore) {
    if (typeof callbackLoadMore == 'undefined') callbackLoadMore = null;

    $scope.isLoadedSuccess = false;
    $scope.receiptListLimit = Math.ceil($('#rb-receipt-list').height() / 25 - 1);

    if (resetOffset) {
      $scope.receiptListOffset = 0;
      $scope.isLastPage = false;
      $scope.isFirstPage = true;
    }


    if (!$scope.loadedAllReceipt && direction == 'last') {
      return false;
    }

    if (direction == 'first') {

      if ($scope.receiptListOffset == 0 || $scope.isFirstPage) {
        $scope.isLoadedSuccess = true;
        return false;
      }

      $scope.isFirstPage = true;
      $scope.receiptListOffset = 0;
      $scope.isLastPage = false;

    } else if (direction == 'last') {
      if ($scope.isLastPage) {
        $scope.isLoadedSuccess = true;
        return false;
      }
      $scope.isLastPage = true;
      $scope.isFirstPage = false;
      $scope.receiptListOffset = $scope.TotalReceipts - $scope.receiptListLimit;

    } else if (direction == 'previous') {
      if ($scope.isFirstPage) {
        $scope.isLoadedSuccess = true;
        return false;
      }
      $scope.isFirstPage = false;
      $scope.isLastPage = false;


      $scope.receiptListOffset -= $scope.receiptListLimit;

    } else if (direction == 'next') {

      $scope.isLastPage = false;
      $scope.isFirstPage = false;


      if (($scope.receiptList.length + $scope.receiptListOffset) >= $scope.TotalReceipts) {
        $scope.isLastPage = true;
        $scope.isLoadedSuccess = true;
        return false;
      }

      if (!resetOffset) {
        $scope.receiptListOffset += $scope.receiptListLimit;
      }

    }

    if ($scope.receiptListOffset < 0) {
      $scope.isFirstPage = true;
      $scope.isLoadedSuccess = true;
      $scope.receiptListOffset = 0;
    }

    if (direction == 'filter') {
      $scope.getReceiptList(false, false, true, callbackLoadMore);
    } else {
      $scope.getReceiptList(false, true, false, callbackLoadMore);

      if (($scope.receiptList.length + $scope.receiptListOffset) >= $scope.TotalReceipts) {
        $scope.isLastPage = true;
      }
    }


    if ($scope.receiptListOffset <= 0) {
      $scope.isFirstPage = true;
    }

    setTimeout(function () {

      jQuery('#rb-receipt-list td.col-typ span, #rb-receipt-list td.col-app span, #rb-receipt-list td.col-exp span').tooltip();

    }, 1000);
  }

  /**
   * Temporary solution to load latest RB data from server. After loaded, we will operate with local storage
   * @type {boolean}
   */
  var loadLatestRbData = true;

  /*
   * Go to receipt box from dashbroard
   */

  $rootScope.filterReciptFromDashboard = function (filterOb) {
    $scope.selectFilterType(filterOb);
    $scope.filterReceipts(filterOb);
  }

  $scope.isLoadedSuccess = false;
  var currentItemLoaded = 0;

  /**
   * Get receipt list
   * @param offset
   * @param reload
   * @returns {boolean}
   */

  $scope.getReceiptList = function (reload, offset, filter, callback) {

    //get limit number to query server for get receipts.
    $scope.receiptListLimit = Math.ceil($('#rb-receipt-list').height() / 25 - 1);
    /*
     * @param limitReceipt  get receipts limit by screen height.
     * @param offset        Offset to get receipts from offset to (limit + offset).
     */
    var paramQuery = {
      limitReceipt: $scope.receiptListLimit
    };

    if (!reload) {

      /*
       * if have param offset the query will get number of receipts start at offset value
       */
      if (offset) {
        paramQuery.offsetReceipt = $scope.receiptListOffset;
      }

      /*
       * If have a filter attr or in filter session.
       */
      if (filter || $scope.isFilter) {
        if ($scope.filterType == 0) {
          if ($scope.tmpFilterType == 'newReceipts') {
            paramQuery.type = $scope.tmpFilterType;
          }
        } else {
          paramQuery.filterByType = $scope.filterType;
        }

        if ((!$scope.allDate)) {

          if ($scope.dateFrom && $scope.dateTo) {
            // Get datetime object for filter date from
            var tmpdateFrom = new Date($scope.dateFrom);

            // Get datetime object for filter date to
            var tmpdateTo = new Date($scope.dateTo);

            //Add one day for datetime object filter from
            tmpdateTo.setDate(tmpdateTo.getDate() + 1);

            tmpdateFrom.setDate(tmpdateFrom.getDate() + 1);

            //set filter date from and date to To query filter with date
            paramQuery.from = tmpdateFrom.getTime();
            paramQuery.to = tmpdateTo.getTime();
          }

        }
      }

      /**
       *
       * @type {boolean} if $scope.reloadTotal set to true --> set param to query reloadTotal is true.
       * Server count receipt total when response
       */
      paramQuery.reloadTotal = ($scope.reloadTotal) ? 1 : 0;
      /**
       *
       * @type {boolean} $scope.markNotNew set to true --> set param to query markNotNew is true.
       * get is new receipts and mark it not new (IsNew = 0)
       */
      paramQuery.markNotNew = paramQuery.NewReceipt = ($scope.isReceiptGetManual) ? 1 : 0;

      /**
       * Server query to get list receipts
       */

      //Abort request if previous request not done
      var parameter = {
        api: 'receipts',
        param: paramQuery
      }

      //Call service
      restAngularService.getReceiptLists(parameter).then(function (response) {
        // If get receipts successfully.

        /*
         * Bind data for showing when paging page.
         * Timeout is solution for case : if go to last page via "next" button so fast
         */

        if (!$scope.isReceiptGetManual) {
          $timeout(function () {
            if ((response.receipts.length != 0)) {
              $scope.tmpToal = ($scope.reloadTotal) ? response.totalReceipt : $scope.TotalReceipts;
              $scope.tmpReceiptLenght = $scope.receiptListOffset + response.receipts.length;
              $scope.tmpReceiptLenght = ($scope.tmpReceiptLenght > $scope.tmpToal) ? response.totalReceipt : $scope.tmpReceiptLenght;
              $scope.tmpReceiptListOffset = $scope.receiptListOffset + 1;
              $scope.tmpReceiptTotalList = $scope.TotalReceipts = $scope.tmpToal;
              $scope.filterNameReceipt = $scope.tmpFilterName;
            }
          });
        }

        /**
         *  if upload and get receipts flow is not working normaly.
         */

        if ($scope.isReceiptGetManual) {
          $scope.directUploads.converted += response.receipts.length;
          if ($scope.directUploads.uploaded != $scope.directUploads.total) {
            $('.message_converted').hide();
            $('.app-rb .message_upload').addClass('totalFilesingle');
            $('.message-upload-custom, .message_upload').show();
            $('.message_upload .mesage-upload-file').empty().append('Currently we are experiencing heavy volumes so we cannot process your uploads at this time; please try back later again');
            $scope.resetUploadQueue();
            $scope.isLoadedSuccess = true;
            return;
          } else {
            var arrayInsert = [];
            arrayInsert.obReceipt = response.receipts;
            $scope.insertReceipts(arrayInsert, true);
            $timeout(function () {
              $scope.tmpReceiptTotalList += response.receipts.length;
              $scope.TotalReceipts = $scope.tmpToal = $scope.tmpReceiptTotalList;
              $scope.isReceiptGetManual = false;
            });
          }
          return;
        }

        /*
         *  Set receipt list
         * */

        //$timeout(function () {
        if (response) {
          $scope.receiptList = response.receipts;
          $scope.loadedAllReceipt = true;
          $scope.isLoadedSuccess = true;
          $scope.tmpFilterName = $scope.tmpFilterTypeName;
          $rootScope.loadedModule++;

          if (typeof callback != 'undefined' && callback) callback();

        }
        $scope.reloadTotal = false;

        //});


        /**
         *
         * @type {boolean} Check if response receipt have a receipt is new -> set to not new
         */
        var isNewReipt = false;
        angular.forEach(response.receipts, function (v, k) {
          isNewReipt = (v.IsNew == 1) ? true : isNewReipt;

        });

        //Update all receipt is not new
        if (isNewReipt) {
          $scope.updateReceiptNotNew();
        }

      });

    }
    $('.app-table-child-wrapper').resizeHeight();
  };

  /**
   * @function bindReceiptID  Function to bind ID list for navigation in receipt details.
   * @param    type           Receipt type
   */
  $scope.filterReceiptID = function (filterName, type) {
    //if(type != 0){
    //  if(typeof $scope.tmpListID == 'undefined'){
    //    $scope.tmpListID = $rootScope.idReceiptsList;
    //  }else{
    //    $rootScope.idReceiptsList = $scope.tmpListID;
    //  }
    //  angular.forEach($rootScope.idReceiptsList, function(v, k){
    //    if(v.ReceiptType != type){
    //      console.log(v.ReceiptType);
    //      //delete $rootScope.idReceiptsList[k];
    //    }
    //  });
    //  console.log($rootScope.idReceiptsList);
    //}
  }

  $scope.currentArchived = 0;
  var totalArchivedReceipts = [];

  //Call function get receipt list
  $timeout(function () {
    $scope.filterReceipts();
    $scope.countingNewReceipt();
  });


  $rootScope.$on('FILTER_RECEIPT_LIST', function (e) {
    $scope.filterReceipts();
    $scope.countingNewReceipt();
  });

  /**
   * Next page when user click next/previous button in RD
   */
  $rootScope.$on('PAGING_RECEIPT_LIST', function (e, data, direction) {
    if (!$scope.rdDirection) {
      $scope.rdDirection = direction;
    }
    var dataCountNext = data + 2;
    var dataCountPrev = data - 1;

    if ($scope.rdDirection != direction && direction == 'previous') {
      dataCountPrev = data - 2;
    }

    var prevFlag = (($scope.receiptListOffset - $scope.receiptListLimit) <= 0) ? $scope.receiptListOffset : $scope.receiptListOffset - $scope.receiptListLimit;

    if (data <= 0 || data == $scope.receiptListOffset) return false;

    if ((dataCountNext > ($scope.receiptListOffset + $scope.receiptListLimit)) && direction == 'next') {
      $timeout(function () {
        $scope.loadMoreReceipt('next');
      }, 500);
    } else if ((dataCountPrev < prevFlag) && direction == 'previous') {
      $timeout(function () {
        $scope.loadMoreReceipt('previous');
      });
    }

  });


  /**
   * Listener for loading receipts
   *
   * @param loadFromServer
   *     If true, will get latest data from server and update to local storage
   * @param receipt
   *     If set, it's receipt instance, we don't get latest data from server, just update receipt data in
   * local storage
   */
  $rootScope.$on('RELOAD_RECEIPT_LIST', function (even, loadFromServer, receipt) {
    if (loadFromServer) {
      if (typeof receipt == 'undefined') {
        //QuyPV: 2014-07-21
        //Hacking for temporary solution of reload receipt list from server
        loadLatestRbData = true;
        //$scope.getReceiptList(false); 17/11
      } else {
        //$scope.getReceiptList(loadFromServer, receipt); 17/11
      }
      return;
    }
    if (receipt.ExpensePeriod == "undefined-MX" || receipt.ExpensePeriod == "MX") {
      receipt.ExpensePeriod = "Mixed";
    }
    var items = [];
    var itemIndex = 0;
    var tempExpensePeriod = receipt.Items[0].ExpensePeriod;
    var theSameExpensePeriod = true;

    for (var j in receipt.Items) {
      if (receipt.Items[j].Name) {
        items.push(receipt.Items[j]);
      }

      if (tempExpensePeriod != receipt.Items[j].ExpensePeriod) {
        theSameExpensePeriod = false;
      }
      if (receipt.HasCombinedItem != 0 && itemIndex == 0 && receipt.Items[j].IsJoined == 0) {
        break;
      }

      itemIndex++;
    }
    if (theSameExpensePeriod == true) {
      receipt.ExpensePeriod = $filter('formatDate')(tempExpensePeriod, 'MMM-yyyy');
      ;
    }
    receipt.Items = items;

    var rawReceipt = {};
    angular.forEach(receipt, function (value, key) {
      if (!angular.isFunction(value) && key != 'parentResource' && key != 'restangularCollection' && key != 'route') {
        if (key == 'ReceiptID') {
          rawReceipt[key] = parseInt(value);
        } else {
          rawReceipt[key] = value;
        }
      }
    });

    for (var j in $scope.receiptList) {
      if ($scope.receiptList[j].ReceiptID == receipt.ReceiptID) {
        $scope.receiptList[j] = receipt;
        $scope.receiptList[j].clicked = true;
        $scope.receiptList[j].IsOpened = 1;
        break;
      }
    }

  });

  $scope.$watch('loadedAllReceipt', function (newValue, oldValue, scope) {
    if (newValue) {
      $scope.isLoadedSuccess = true;
    }
  });

  $scope.$watch('receiptListTotal', function (newValue, oldValue, scope) {
    if (newValue) {
      $scope.countingNewReceipt();
    }
  });

  //.. watch change on route to reload receipt list if needs
  $scope.$on('$routeChangeStart', function (next, current) {
    if (current.params.hasOwnProperty('reload')) {

      //$scope.getReceiptList(true); 17/11
    }
  });

  $rootScope.$on('RB_ADD_EXPENSE_MANUAL', function (event, openFrom, categoryInfo, tripInfo) {
    $scope.openFromApp = categoryInfo.app;
    $scope.categoryInfo = categoryInfo;
    $scope.tripInfo = tripInfo;

    $scope.$emit('LOAD_RECEIPT_DETAIL', 0, 0, openFrom, 0, categoryInfo, tripInfo);
    $('#rb-back-to-app').addClass('show').removeClass('hide');
  });

  $rootScope.$on('OPEN_RB_ADD_ITEMS', function (event, categoryInfo, tripInfo) {
    $scope.openFromApp = categoryInfo.app;
    $scope.categoryInfo = categoryInfo;
    $scope.tripInfo = tripInfo;

    $('#nav-expense-management a').removeClass('aqua');
    $('#menu-receiptbox').addClass('green');

    $('#rb-back-to-app').addClass('show').removeClass('hide');
  });

  $rootScope.$on('CLOSE_RB_ADD_ITEMS', function (event, menu) {
    $('#menu-receiptbox').removeClass('green');
    $('#nav-expense-management #' + menu).addClass('aqua');

    $scope.openFromApp = null;
    $scope.tripInfo = null;
  });

  /**
   * Event to clear category and trip info data when cancel add expense flow manually
   */
  $rootScope.$on('RB_CLEAR_BACK_TO_APP', function (event, menu) {
    $scope.openFromApp = null;
    $scope.tripInfo = null;
  });

  $scope.backToApp = function () {
    $('#receiptbox-wrapper').hide();
    $('#menu-receiptbox').removeClass('green');

    if ($scope.openFromApp == 'travel_expense') {
      $('#nav-expense-management #menu-travel-expense').addClass('aqua');
      if (typeof $scope.tripInfo != 'undefined') {
        $scope.$emit('LOAD_TRIP_DETAIL', $scope.tripInfo.tripID, $scope.tripInfo.tripType,
          $scope.tripInfo.dateFrom, $scope.tripInfo.dateTo);
      } else {
        $('#trip-detail-wrapper').show();
      }
    }

    if ($scope.openFromApp == 'personal_expense') {
      $('#personal-expense-wrapper').show();
      $('#nav-expense-management #menu-personal-expense').addClass('aqua');
    }
    if ($scope.openFromApp == 'education_expense') {
      $('#education-expense-wrapper').show();
      $('#nav-expense-management #menu-education-expense').addClass('aqua');
    }
    if ($scope.openFromApp == 'business_expense') {
      $('#business-expense-wrapper').show();
      $('#nav-expense-management #menu-business-expense').addClass('aqua');
    }
    if ($scope.openFromApp == 'personal_assets') {
      $('#personal-assets-wrapper').show();
      $('#nav-expense-management #menu-personal-assets').addClass('aqua');
    }
    if ($scope.openFromApp == 'business_assets') {
      $('#business-assets-wrapper').show();
      $('#nav-expense-management #menu-business-assets').addClass('aqua');
    }
  }

  /**
   * Function to set check box status
   *
   * @param object element
   */
  $scope.setCheckboxStatus = function (element) {
    // Set new checked status
    element.IsChecked = !element.IsChecked;

    // Has item?
    if (element.hasOwnProperty('Items') && element.Items.length) {
      angular.forEach(element.Items, function (item, key) {
        // If parent element is checked, set all child items checked status to false, recursive call will set
        // back to true
        item.IsChecked = true;
        if (element.IsChecked) {
          item.IsChecked = false;
        }
        $scope.setCheckboxStatus(item);
      });
    }
  }

  $scope.isCheckAll = false;
  $scope.toggleCheckAll = function () {
    $scope.isCheckAll = !$scope.isCheckAll;
    angular.forEach($scope.receiptList, function (receipt, index) {
      if (!receipt.IsReported) {
        receipt.IsChecked = $scope.isCheckAll;
        $scope.updateDeleteList($scope.receiptDeleteList, receipt);
        angular.forEach(receipt.Items, function (item, i) {
          item.IsChecked = $scope.isCheckAll;
          $scope.updateDeleteList($scope.itemDeleteList, item);
        });
      }
    });
  }

  $scope.setCollapseStatus = function (element) {
    element.IsCollapsed = !element.IsCollapsed;
  }

  /* Update item's category */
  $scope.updateItemCategory = function (item) {
    var updateData = {
      ItemID: item.ItemID,
      App: item.CategoryApp,
      CategoryID: item.CategoryID
    };

    if (item.hasOwnProperty('TripID')) {
      updateData.TripID = item.TripID;
    } else {
      updateData.ExpensePeriod = item.ExpensePeriod;
    }

    Restangular.one('categories').customPUT(updateData, 'assign').then(function (response) {
    }, function (response) {
      $.showMessageBox({content: response.data.message.join('<br/>')});
    })
  }

  /* Update category app menu */
  $scope.loadCategory = function (item) {
    angular.forEach($scope.receiptList, function (rv, rk) {
      angular.forEach(rv.Items, function (v, k) {
        if (v.ItemID === item.ItemID) {
          angular.forEach($rootScope.categories, function (catV, catK) {
            if (item.CategoryApp === catV.App.MachineName) {
              item.Categories = catV.arrangedCatList;
            }
          });
        }
      });
    });
  }

  $scope.fillCategoryCombo = function () {
    angular.forEach($scope.receiptList, function (rv, rk) {
      angular.forEach(rv.Items, function (item, k) {
        angular.forEach($rootScope.categories, function (catV, catK) {
          if (item.CategoryApp === catV.App.MachineName) {
            item.Categories = catV.Categories;
          }
        });
      });
    });
  }

  /*
   * Filter receipts
   */
  $scope.filterReceipts = function (filter, ignoreInvalidDateRange, callback) {
    resetShiftClick();
    $scope.tmpFilterName = $scope.tmpFilterTypeName;
    $scope.allDate = angular.copy($scope.tmpAllDate);
    $scope.dateFrom = angular.copy($scope.tmpDateFrom);
    $scope.dateTo = angular.copy($scope.tmpDateTo);
    if (!$scope.allDate && new Date($scope.dateFrom) > new Date($scope.dateTo) && !ignoreInvalidDateRange) {
      $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
      return false;
    }
    switch ($scope.tmpFilterType) {
      case 'digitalReceipts':
        $scope.filterType = 1;
        break;

      case 'emailReceipts':
        $scope.filterType = 2;
        break;

      case 'paperReceipts':
        $scope.filterType = 3;
        break;

      case 'manualReceipts':
        $scope.filterType = 4;
        break;

      case 'nonReceipts':
        $scope.filterType = 5;
        break;

      case 'paperInvoices':
        $scope.filterType = 6;
        break;

      case 'electronicInvoices':
        $scope.filterType = 7;
        break;

      default:
        $scope.filterType = 0;
    }

    /**
     * Refresh list receipt ID for navigation in receipt details
     */
    $scope.filterReceiptID($scope.tmpFilterType, $scope.filterType);

    /**
     *
     * @type {isFilter} isFilter set to true. Talk to the system to know session filter is running
     */
    $scope.isFilter = true;

    /**
     *
     * @type {reloadTotal} reloadTotal set to true. Reload Total receipt variable
     */
    $scope.reloadTotal = true;

    /**
     *
     * @type {isLoadedSuccess}
     */
    $scope.isLoadedSuccess = false;

    /**
     * @function loadMoreReceipt
     */
    $scope.loadMoreReceipt('filter', true);

  }

  /*
   * Event when push server ping to get new receipts
   * */
  $scope.countPush = 0;
  $scope.$on('RB_RECEIVE_RECEIPTS', function (e, dataNewReceipt) {
    if (dataNewReceipt) {
      if (dataNewReceipt.uploadType != 'upload') {
        $scope.insertReceiptWithoutUpload(dataNewReceipt);
      } else {
        $scope.insertReceipts(dataNewReceipt);
      }
    }
  });

  $scope.tmpReceiptConverted = [];

  /**
   * @function insertReceiptWithoutUpload if receipt not come via a upload method.
   * @param dataNewReceipt
   */

  $scope.insertReceiptWithoutUpload = function (dataNewReceipt) {

    //$scope.$apply();
    $timeout(function () {
      $scope.updateReceiptNotNew();

      $scope.reloadTotal = true;
      $scope.loadMoreReceipt('filter', true, function () {
        angular.forEach($scope.receiptList, function (v, k) {
          if (dataNewReceipt.obReceipt.ReceiptID == v.ReceiptID) {
            v.NewReceiptUploaded = true;
          }
        });

        /**
         //     * Display message after receipt inserted.
         //     */

        $('.wrap-top-watch').hide();

        $('.message_upload').show();

        $('.message_upload').addClass('totalFilesingle');

        $('.app-rb .totalFileUpload').addClass('uploading-receipt');

        if (dataNewReceipt.uploadType == 'email') {
          $('.message_upload .mesage-upload-file').empty().text('1 Email receipt converted successfully.');
        }
        else if (dataNewReceipt.uploadType == 'android') {

          $('.message_upload .mesage-upload-file').empty().text('1 receipt uploaded from mobile converted successfully.');

        } else {

          $('.message_upload .mesage-upload-file').empty().text('1 receipt has been processed.');

        }

        //Reset Message upload area.
        $timeout(function () {
          $('.message_upload').removeClass('totalFilesingle');
          $('.message_upload').hide();
          $('.message_upload .mesage-upload-file').empty();
          $('.wrap-top-watch').show();
        }, 7000);

        //Recount the numbers in filter.
        $scope.countingNewReceipt();
      });
    });


    //$timeout(function () {
    //  Restangular.one('receipts').getList('', params).then(function (response) {
    //    console.log("It's true!");
    //    $scope.getReceiptList(true, false, true, function(){
    //      console.log('abc');
    //    });
    //
    //    $timeout(function () {
    //      console.log(dataNewReceipt.obReceipt);
    //      console.log($scope.receiptList);
    //
    //      angular.forEach($scope.receiptList, function (v, k) {
    //        if (dataNewReceipt.obReceipt.ReceiptID == v.ReceiptID) {
    //          console.log("your beautiful, It's true");
    //          v.NewReceiptUploaded = true;
    //        }
    //      });
    //    });
    //    /**
    //     * Display message after receipt inserted.
    //     */
    //
    //    $('.wrap-top-watch').hide();
    //
    //    $('.message_upload').show();
    //
    //    $('.message_upload').addClass('totalFilesingle');
    //
    //    $('.app-rb .totalFileUpload').addClass('uploading-receipt');
    //
    //    if (dataNewReceipt.uploadType == 'email') {
    //      $('.message_upload .mesage-upload-file').empty().text('1 Email receipt converted successfully.');
    //    }
    //    else if (dataNewReceipt.uploadType == 'android') {
    //
    //      $('.message_upload .mesage-upload-file').empty().text('1 receipt uploaded from mobile converted successfully.');
    //
    //    } else {
    //
    //      $('.message_upload .mesage-upload-file').empty().text('1 receipt has been processed.');
    //
    //    }
    //
    //    //Reset Message upload area.
    //    $timeout(function () {
    //      $('.message_upload').removeClass('totalFilesingle');
    //      $('.message_upload').hide();
    //      $('.message_upload .mesage-upload-file').empty();
    //      $('.wrap-top-watch').show();
    //    }, 7000);
    //
    //    //Recount the numbers in filter.
    //    $scope.countingNewReceipt();
    //
    //
    //  });
    //}, 100);
  }

  /**
   * @function Function Insert New receipt
   * @param    dataNewReceipt
   * @param    isReceiptComeError
   * @returns  {boolean}
   */

  $scope.insertReceipts = function (dataNewReceipt, isReceiptComeError) {

    /**
     * @variable startTransmitting
     * Set is false to stop animation transmitting in receipt box, upload area
     */
    $timeout(function () {
      $scope.startTransmitting = false;
    });

    if (dataNewReceipt.obReceipt && dataNewReceipt.obReceipt.length != 0) {
      var receiptCommingType = '';
      if ((typeof(isReceiptComeError) == 'undefined') || !isReceiptComeError) {
        $scope.totalTimeConverted = (parseFloat($scope.totalTimeConverted) > parseFloat(dataNewReceipt.processTime)) ? $scope.totalTimeConverted : dataNewReceipt.processTime;
        receiptCommingType = dataNewReceipt.uploadType;
        $scope.directUploads.converted++;
        var newReceipt = dataNewReceipt.obReceipt;

        /**
         * add new receipt to first index of current receipt list
         */
        $scope.tmpReceiptConverted.push(newReceipt);

      } else {
        angular.forEach(dataNewReceipt.obReceipt, function (v, k) {
          $scope.tmpReceiptConverted.push(v);
          receiptCommingType = 'upload';
        });
      }

      /*
       * Case detect upload type
       */
      switch (receiptCommingType) {
        case 'upload':
          //Calculate
          var converted = $scope.directUploads.converted;
          var totalUpload = $scope.directUploads.total;
          //if receipt number of converted greater than -> return;
          if (converted > totalUpload) {

            $timeout(function () {
              $scope.keepUploadArea = false;
            })

            $('.wrap-top-watch, .message_converted').hide();
            $('.message_upload').show();
            $('.message_upload').addClass('totalFilesingle');
            $('.wrap-top-watch').hide();
            $('.app-rb .totalFileUpload').addClass('uploading-receipt');
            $('.message_upload .mesage-upload-file').empty().text('Receipt from your last interrupted upload converted...');
            if ($scope.isReceiptGetManual) {
              $timeout(function () {
                angular.forEach($scope.receiptList, function (v, k) {
                  angular.forEach($scope.tmpReceiptConverted, function (value, key) {
                    if (v.ReceiptID == value.ReceiptID) {
                      v.NewReceiptUploaded = true;
                    }
                  });
                });
                $scope.tmpReceiptConverted = [];
              }, 500);
            } else {
              $scope.loadMoreReceipt('filter', true, function () {
                angular.forEach($scope.receiptList, function (v, k) {
                  angular.forEach($scope.tmpReceiptConverted, function (value, key) {
                    if (v.ReceiptID == value.ReceiptID) {
                      v.NewReceiptUploaded = true;
                    }
                  })
                })

                $scope.tmpReceiptConverted = [];
              });
            }

            $scope.resetUploadQueue();
            $scope.isLoadedSuccess = true;
            $scope.finishConvert = true;
            return false;
          } else {
            //if upload area is disable.
            if (!$rootScope.keepUploadArea) {
              if ($rootScope.inAppScreen == 'RECEIPT_BOX') {
                $rootScope.openUploadArea();
              }
            }

            // Update dont show PE Guide anymore
            $timeout(function () {
              Restangular.one('users').customPUT({kind: "pe", value: 0}, 'update-show-guide').then(function (resData) {

              }, function (response) {
                console.log(response.status);
              });
            })
            /**
             *
             * @variable {boolean} firstConverted if first receipt converted set this variable to true.
             */

            $timeout(function () {
              $scope.firstConverted = true;
            });

            var timeoutDisplay = ($scope.directUploads.uploaded == 1) ? 3000 : 0;

            $('.message_converted p').fadeIn(timeoutDisplay);

            $('.message_converted #message_convert').fadeIn(timeoutDisplay);

            var msgAlertProgress = '' + converted + ' of ' + totalUpload + ' receipt(s) received';

            $('.message_converted #message_convert').empty().text(msgAlertProgress);
            $('.message_converted #message_convert').css('color', '#4fba83');

            $('.message_upload').fadeIn(timeoutDisplay);

            //Process last insert
            if (converted == totalUpload) {
              $rootScope.openUploadArea();

              $timeout(function () {
                $scope.firstConverted = false;
              });

              $('.message_converted #message_convert').removeAttr("style");

              var totalSecondsConvert;

              //if time convert less than 100 miniseconds -> set default to 100 (0.1 second).
              totalSecondsConvert = ($scope.totalTimeConverted < 100) ? 100 : $scope.totalTimeConverted;

              totalSecondsConvert = parseFloat($scope.totalTimeConverted / 1000).toFixed(1);

              var avgSeconds = (totalSecondsConvert / converted).toFixed(2);

              avgSeconds = (avgSeconds < 10) ? '0' + avgSeconds : avgSeconds;

              totalSecondsConvert = (totalSecondsConvert < 10) ? '0' + totalSecondsConvert : totalSecondsConvert;

              $timeout(function () {

                if ((typeof isReceiptComeError == 'undefined') || !isReceiptComeError) {
                  //Generate message of conversion summary
                  $('.message_converted span#message_convert').empty().append('' + converted + ' of ' + totalUpload + ' receipt(s) digitized &nbsp;&nbsp; in ' + totalSecondsConvert + ' second(s) &nbsp;(avg ' + avgSeconds + ')');
                } else {
                  //Generate message of conversion summary
                  $('.message_converted span#message_convert').empty().append('' + converted + ' of ' + totalUpload + ' receipt(s) digitized');
                }

              }, 300);

              $('.message_converted #stopwatch-upload').hide();

              $('#uploadifive-paper-receipt input').removeAttr('disabled');

              $scope.directUploads.isUploading = false;

              $scope.resetUploadQueue();

              $scope.totalTimeConverted = 0;

              $scope.finishConvert = true;

              $scope.reloadTotal = true;
              $scope.loadMoreReceipt('filter', true, function () {
                angular.forEach($scope.receiptList, function (v, k) {
                  angular.forEach($scope.tmpReceiptConverted, function (value, key) {
                    if (v.ReceiptID == value.ReceiptID) {
                      v.NewReceiptUploaded = true;
                    }
                  })
                })

                $scope.tmpReceiptConverted = [];
              });

              //Cancel timeout error
              $timeout.cancel($scope.timeoutFilter);

              $scope.isLoadedSuccess = true;
            }

            /**
             *  @function countingNewReceipt
             *  Recount Total receipt variable
             */

            $scope.countingNewReceipt();
          }
          break;
        default:
          $scope.finishConvert = true;
          break;
      }
    }
  }

  /**
   *@function  updateReceiptNotNew set all receipt is not new
   */

  $scope.updateReceiptNotNew = function () {
    $timeout(function () {
      /**
       * Set param to query to server
       * @type {Array}
       */
      var parameter = {
        api: 'receipts',
        param: {
          markNotNew: 1,
          NewReceipt: 1
        }
      };
      restAngularService.getReceiptLists(parameter).then();
    }, 1000);
  }

  /*
   * Receive new receipt via upload local
   **/
  $scope.receiveReceipts = function () {
    $('#receive-button .app-icon.receive').toggleClass('animated-receive');
    try {

      $scope.reloadTotal = true;
      $scope.isReceiptGetManual = true;
      $scope.getReceiptList();

    } catch (err) {
      console.debug(err);
      alert("Error occurred when getting data\nClick OK to refresh browser and try again.");
      window.document.location.reload();
    }

    $timeout(function () {
      $('#receive-button .app-icon.receive').toggleClass('animated-receive');
    }, 1000);
  }

  /**
   * Delete selected receipt
   */
  $scope.deleteReceiptItem = function () {
    /*
     * If user is uploading receipt -> Don't delete everythink .
     */

    if ($scope.directUploads.isUploading) {
      return;
    }


    /*
     * Check the conditions to prepare delete receipt items
     */
    if ($scope.itemDeleteList.length == 0 && $scope.receiptDeleteList.length == 0) {
      $.showMessageBox({
        content: "Please select receipt(s) to delete",
        boxTitle: 'DELETE RECEIPT(S)',
        boxTitleClass: ''
      });
      return;
    }

    $.showMessageBox({
      content: 'Do you want to delete selected receipt(s) / item(s)?',
      boxTitle: 'DELETE RECEIPT(S) / ITEM(S)',
      boxTitleClass: '',
      type: 'confirm',
      onYesAction: function () {
        //var removed = false;
        $scope.deletingReceipt = true;
        if ($scope.receiptDeleteList.length > 0) {
          for (var i = 0; i < $scope.receiptDeleteList.length; i++) {
            $('tbody[receipt-id="' + $scope.receiptDeleteList[i] + '"] tr').addClass('gonna-die');
          }
          $timeout(function () {
            Restangular.one('receipts').remove({ReceiptIDs: $scope.receiptDeleteList.join(',')}).then(function (response) {

              // Notify to listener to update local storage
              var updatedItem = 0;
              $scope.filterTypeList[0].totalofRC = $scope.filterTypeList[0].totalofRC - $scope.receiptDeleteList.length;
              $timeout(function () {
                $('.wrap-top-watch').hide();
                $('.message_upload').show();
                $('.message_upload').addClass('totalFilesingle');
                $('.message_upload .mesage-upload-file').empty().text($scope.receiptDeleteList.length + ' receipt(s) deleted');
                if ($scope.isCheckAll) {
                  $scope.isCheckAll = !$scope.isCheckAll;
                }

                if (response.RefreshTrip) {
                  $scope.$emit('LOAD_TRIP_LIST');
                }

                if (response.RefreshReport) {
                  $scope.$emit('LOAD_REPORT_LIST');
                }

                $scope.deletingReceipt = false;
                $scope.reloadTotal = true;
                $scope.filterReceipts();
                $scope.countingNewReceipt();
                $scope.receiptDeleteList = [];
                $timeout(function () {
                  $('.message_upload').removeClass('totalFilesingle');
                  $('.message_upload').hide();
                  $('.message_upload .mesage-upload-file').empty();
                  $('.wrap-top-watch').show();
                }, 4000);
              });
            }, function (response) {
              $('.gonna-die').removeClass('gonna-die');
              $.showMessageBox({content: response.data.message.join('<br/>')});
            });
          });
        } else if ($scope.itemDeleteList.length > 0) {
          for (var i = 0; i < $scope.itemDeleteList.length; i++) {
            $('tr[item-id="' + $scope.itemDeleteList[i] + '"]').addClass('gonna-die');
          }

          $timeout(function () {
            Restangular.one('items').remove({ItemIDs: $scope.itemDeleteList.join(',')}).then(function (response) {
              $scope.loadMoreReceipt();
              $('.wrap-top-watch').hide();
              $('.message_upload').show();
              $('.message_upload').addClass('totalFilesingle');
              $('.message_upload .mesage-upload-file').empty().text($scope.itemDeleteList.length + ' item(s) deleted');

              // Notify to listener to update local storage
              $scope.$emit('RELOAD_RECEIPT_LIST', true);
              $scope.$emit('RELOAD_PE_LIST', true);

              if (response.RefreshTrip) {
                $scope.$emit('LOAD_TRIP_LIST');
              }

              if (response.RefreshReport) {
                $scope.$emit('LOAD_REPORT_LIST');
              }
              $timeout(function () {
                $('.message_upload').removeClass('totalFilesingle');
                $('.message_upload').hide();
                $('.message_upload .mesage-upload-file').empty();
                $('.wrap-top-watch').show();
              }, 4000);
              $scope.deletingReceipt = false;
              $scope.isCheckAll = !$scope.isCheckAll;
            }, function (response) {
              $('.gonna-die').removeClass('gonna-die');
              $.showMessageBox({content: response.data.message.join('<br/>')});
            });
          });
        }
      }
    });
  }

  /**
   * Add element ID to checked list when it's checkbox is selected/unselected
   *
   * @param checkedList   Scope receipt/item list
   * @param element       Scope receipt/item
   */

  $scope.updateDeleteList = function (deleteList, element, flagDelete) {
    $timeout(function () {
      var index, id;
      if (typeof flagDelete == 'undefined') {
        flagDelete = element.IsChecked;
      }
      if (element.hasOwnProperty('Items')) {
        index = deleteList.indexOf(element.ReceiptID);
        id = element.ReceiptID;
        //Remove a receipt means remove all child items -> need only to keep receiptID on behalf, remove child item IDs
        angular.forEach(element.Items, function (item, k) {
          $scope.updateDeleteList($scope.itemDeleteList, item, false);
        });
      } else {
        index = deleteList.indexOf(element.ItemID);
        id = element.ItemID;
      }
      if (flagDelete) {
        if (index < 0) {
          deleteList.push(id);
        }
      } else {
        if (index > -1) {
          deleteList.splice(index, 1);
        }
      }
    });
  }

  //Snap Receipt for mobile
  $scope.uploadURL = $rootScope.ocrUploaderUrl;
  $scope.uploadUserID = $rootScope.loggedInUser.UserID;
  $scope.uploadLocation = 'Oregon';
  $scope.triggerTakeSnap = function () {
    $("#takePictureField").trigger('click');
  }
  $scope.deviceSupportCamera = deviceIsMobileDevice();
  $('#snap_upload_form').ajaxForm({
    success: function (data) {
    },
    error: function (data) {
    }
  });

  /*
   * Counting new receipt filter by receiptTypeCode.
   */

  $scope.countingNewReceipt = function () {
    var sumNewReceipt = [];
    var sumTotalReceipt = [];
    Restangular.one('receipts').customGET('count-receipts').then(function (response) {
      $scope.TotalReceipts = response.TotalReceipts;
      $scope.TotalNewReceipt = response.TotalNewReceipt;
      $scope.TotalReceiptByType = response.ReceiptByType;
      if ($scope.TotalReceiptByType) {
        angular.forEach($scope.TotalReceiptByType, function (v, k) {
          sumNewReceipt.push(parseInt(v.new));
          sumTotalReceipt.push(parseInt(v.total));
        });
      }
      $scope.filterTypeList[0].totalofRC = $scope.TotalReceipts;
      $scope.filterTypeList[1].newreceipt = $scope.TotalNewReceipt;
      $scope.filterTypeList[1].totalofRC = $scope.TotalNewReceipt;
      for (var i = 2; i < 8; i++) {
        $scope.filterTypeList[i].newreceipt = sumNewReceipt[i - 2];
        $scope.filterTypeList[i].totalofRC = sumTotalReceipt[i - 2];
      }

      //Update Dashboard screen
      $rootScope.$broadcast('DB_UPDATE_COUNT', 'receipt', $scope.filterTypeList);
    }, function (response) {
      if (response.status !== 200) {
        console.log(response);
      }
    });
  }

  /*
   * Support variables for direct uploading to s3
   */

  $scope.uploadQueue = [];

  $scope.resetUploadQueue = function () {
    $('#receiptImageForm')[0].reset();
    $scope.directUploads = {
      isUploading: false,
      isProcessing: false,
      uploaded: 0,
      converted: 0,
      total: 0,
      success: 0,
      timeStartUpload: 0,
      timeStartConvert: 0,
      queueSizeLimit: 9,
      fileSizeLimit: 10 * 1024 * 1024 //10 MB
    }
  }
  $scope.resetUploadQueue();


  /*
   * Trigger choose receipt image to upload
   */


  $scope.chooseImage = function () {
    /**
     * Recheck OCR Healthy to Upload
     */
    $rootScope.checkOCRStatus(function (res) {
      $rootScope.ocrStatus = res;
    });

    /**
     * If is loading receipt return
     */
    if ($scope.directUploads.isUploading || $scope.directUploads.isProcessing) return;

    /**
     * @type {boolean} isUploadingReceipt set to false
     */
    $scope.isUploadingReceipt = false;

    /**
     *
     * @type {number} totalTimeConverted reset total time converted.
     */
    $scope.totalTimeConverted = 0;

    $('#input-rb-upload').unbind('change').click();

    $('.message_upload').removeClass('totalFilesingle');

    $timeout(function () {
      $('#input-rb-upload').change(function () {

        /*
         *  Validate input before upload
         */

        if (this.files.length > 0) {

          //Limit file number
          if (this.files.length > 9) {

            $.showMessageBox({content: 'The maximum number of queue items has been reached (' + $scope.directUploads.queueSizeLimit + ').  Please select fewer files.'});

            $scope.resetUploadQueue();

            return false;
          }

          //Limit file type
          var fileType = ["image/jpeg", "image/gif", "image/png", "application/pdf"];

          for (var i = 0; i < this.files.length; i++) {

            if (fileType.indexOf(this.files[i].type) < 0) {

              $.showMessageBox({content: 'Invalid file type, upload will be aborted'});

              $scope.resetUploadQueue();

              return false;
            }
          }

          //Limit file size
          for (var i = 0; i < this.files.length; i++) {
            if (this.files[i].size > $scope.directUploads.fileSizeLimit) {

              $.showMessageBox({content: 'The size of a file exceeds the limit that was set (10MB)'});

              $scope.resetUploadQueue();

              return false;
            }
          }

          /**
           * Can't get socket identifier and ocr status return invalid
           */
          if (!$rootScope.socketIdentifier || !$rootScope.ocrStatus) {
            /**
             * Message If can't connect to Upload server and Ocr server
             */
            $.showMessageBox({content: 'Currently we are experiencing heavy volumes so we cannot process your uploads at this time; please try back later again'});

            /**
             * Reset current upload quere
             */
            $scope.resetUploadQueue();

            /**
             * Return
             */
            return false;
          }

          $scope.socketIdentifier = angular.copy($rootScope.socketIdentifier);


          // fetch uploadQueueFile object
          $scope.uploadQueueFile = new Array();

          var inputs = this.files;

          angular.forEach(inputs, function (file, k) {

            var orgFileName = (file.name.split('').length > 30) ? file.name.slice(0, 30) + '...' : file.name;

            //Generate an unique file name to save on s3
            var unqFileName = $rootScope.loggedInUser.UserID + "_" + "r_" + guid() + "." + orgFileName.split('.').pop();

            var fileSize = (file.size / 1024 / 1024).toFixed(2);

            //Create upload queue
            $scope.uploadQueueFile.push({
              orgName: orgFileName,
              unqName: unqFileName,
              fileData: file,
              fileSize: fileSize
              //dataIndex:0
            });

          });

          /*
           * Delete  NewReceiptUploaded properties of all receipts
           */

          $timeout(function () {

            angular.forEach($scope.receiptList, function (v, k) {
              v.NewReceiptUploaded = false;
              indexDbServer.userReceipts.update(v);
            });

          });

        }

        $scope.uploadImage();

      });
    }, 500);
  }

  $scope.$on('FILE_UPLOAD_STATUS', function (e, name, status) {
    if (name && status) {
      if (status == 'success') {
        var iUpload = document.getElementById(name);

        iUpload.className = iUpload.className + " upload-success";

        iUpload.parentNode.className = iUpload.parentNode.className + " uploadLi-success";

        // Continue run flow when receipts uploaded.
        $scope.uploadFileSuccess();

      } else {

        var iUpload = document.getElementById(name);

        iUpload.className = iUpload.className + " upload-failed";
      }
    }
  });

  $scope.closeUpload = function () {
    $rootScope.keepUploadArea = false;
    $rootScope.closeUploadArea();
  }

  $scope.uploadImage = function () {

    /*
     * Start upload:
     * add Class total file single to .message_upload            Set height of upload line to higher
     * set property for #resetUploadCtr is checked               Reset stopwatch upload
     * add class uploading-receipt to .message-upload-custom     Keep uploading receipt message to show if user click evrywhere
     * set property for #startUploadCtr is checked               Start stopwatch upload
     * */

    $('.percent-loading').width('0%');

    $scope.finishUpload = false;

    $scope.finishConvert = false;

    $('.message_converted').hide();

    $scope.uploadLineMg = true;


    $('#resetUploadCtr').prop("checked", true);                  //Reset Stopwatch Upload

    $('.message-upload-custom').addClass('uploading-receipt');

    $('.message-upload-custom, .message_upload').show();

    $('#startUploadCtr').prop("checked", true);                  // Start stopwatch Upload

    $('.wrap-top-watch').show();

    //$('.cls-second').hide();

    //Set start time for counting
    $scope.directUploads.timeStartUpload = (new Date()).getTime();

    $scope.directUploads.total = $scope.uploadQueueFile.length;

    var msgAlert = $scope.directUploads.uploaded + ' of ' + $scope.directUploads.total + ' receipt(s) uploaded...';

    $('.message_upload .mesage-upload-file').empty().append(msgAlert);

    $timeout(function () {

      $rootScope.keepUploadArea = true;
      $scope.directUploads.isUploading = true;

    });

    $('.wrap-progess-upload-area').show();

    if (!$rootScope.ocrStatus) {
      $scope.resetUploadQueue();
      return false;
    }

    var sizeUpload = 0;
    $scope.uploadlFileStatistics = 0;
    $scope.percentUploaded = 0;

    //Get ul in uploading receipt area.
    var ulList = document.getElementById("ulListUpload");

    angular.forEach($scope.uploadQueueFile, function (file, k) {

      sizeUpload += file.fileData.size;

    });

    //prepare parameter to upload file.
    var parameter = {
      fieldFile: 'fileUploads[]',
      obFiles: $scope.uploadQueueFile,
      auth: $rootScope.loggedInUser.Token,
      socketIdentifier: $scope.socketIdentifier,
      urlApi: UPLOAD_SERVER_URL + '/upload'
    };

    //create instance pushSocket
    var pushServer = new socketPushModule();

    //select method uploadPushServer to upload file.
    pushServer.uploadPushServer(parameter, function (loadedPercent, sizeUploaded, sizeTotal) {

      //callback return percent of process.
      $('.percent-loading').width(loadedPercent + '%');

      $timeout(function () {

        sizeUploaded = sizeUploaded.toFixed(2);
        sizeTotal = sizeTotal.toFixed(2);
        sizeUploaded = (parseFloat(sizeUploaded) < 10) ? '0' + sizeUploaded : sizeUploaded;
        sizeTotal = (parseFloat(sizeTotal) < 10) ? '0' + sizeTotal : sizeTotal;
        $scope.uploadlFileStatistics = sizeUploaded + ' of ' + sizeTotal + ' MB';
        $scope.percentUploaded = loadedPercent + '%';

      });

    }, function () {
      //Callback if upload process is success

    }, function () {
      $.showMessageBox({content: file.orgName + ' failed to upload.'});
    });
    //---------------------------------------

  }

  $scope.uploadFileSuccess = function () {
    /*
     *  Remove class totalFilesingle of .message_upload      Set height of upload line to
     *  Set text for upload line
     */

    $('.app-rb .message_upload').removeClass('totalFilesingle');

    //Callback If upload done.
    $scope.directUploads.uploaded++;

    if ($scope.directUploads.uploaded > 0) {
      $timeout(function () {

        $scope.uploadLineMg = false;

      });
    }

    $scope.directUploads.isProcessing = true;

    //if number of uploaded = 1 set text for convert line
    if ($scope.directUploads.uploaded == 1) {

      $('.message_converted span#message_convert').text('Transmitting to OCR Engine');
      $('.message_converted').show();
      $timeout(function () {
        $scope.firstConverted = false;
        $scope.startTransmitting = true;
      });
    }

    var msgAlert = $scope.directUploads.uploaded + ' of ' + $scope.directUploads.total + ' receipt(s) uploaded...';

    $('.message_upload .mesage-upload-file').empty().text(msgAlert);

    if ($scope.directUploads.uploaded == $scope.directUploads.total) {

      //File upload success
      $scope.doPostQueueUploadWorks();

    }

  }


  $scope.doPostQueueUploadWorks = function () {

    //$('.cls-second').show();

    $('#resetUploadCtr').prop("checked", true); // Reset Stopwatch Upload

    //Generate message to show up
    $('.wrap-top-watch').hide();

    var totalSeconds = parseFloat(~~(((new Date()).getTime() - $scope.directUploads.timeStartUpload) / 1000)).toFixed(1);

    (totalSeconds < 10) ? totalSeconds = '0' + totalSeconds : totalSeconds;

    var avgSeconds = ((totalSeconds / $scope.directUploads.uploaded).toFixed(2));

    (avgSeconds < 10) ? avgSeconds = '0' + avgSeconds : avgSeconds;

    var msgAlert = $scope.directUploads.uploaded + ' of ' + $scope.directUploads.total + ' receipt(s) uploaded <span style="padding-left: 4px;">in</span> ' + totalSeconds + ' second(s) &nbsp;(avg ' + avgSeconds + ')';

    $('.message_upload .mesage-upload-file').empty().append(msgAlert);


    $('#uploadifive-paper-receipt input').removeAttr('disabled');

    //End Hide Message
    $('#resetUploadCtr').prop("checked", true); // Stop upload stopwatch

    $scope.finishUpload = true;

    //Set timeout to filter if receipt converted  !ilter= receipt uploaded
    $scope.timeoutFilter = $timeout(function () {
      if ($scope.directUploads.converted != $scope.directUploads.total) {
        $scope.isReceiptGetManual = true;
        $scope.loadMoreReceipt('filter', true);
        $scope.reloadTotal = true;
      }

    }, 3 * 60 * 1000); //2.5 minute
  }

  /**
   * Show modal box for receipt or item
   *
   * @param itemData Receipt or Item object
   */
  $scope.openShowMoreModal = function (itemData) {
    $rootScope.displayShowMore(itemData);
  }


});


function triggerUploadImage(files) {
  if (files.length > 0) {
    $('#snap_upload_form').submit();
  }
}
function triggerOpenImagePupup() {
  setTimeout(function () {
    $('.manualReceipt_image#receipt-image').click();
  });
}

function filter_receipt_type_by_code(query, obj) {
  var new_obj = {};
  for (var i in obj) {
    var emp_st = obj[i].code;
    if (emp_st == query) {
      new_obj = obj[i];
    }
  }
  return new_obj;
}
