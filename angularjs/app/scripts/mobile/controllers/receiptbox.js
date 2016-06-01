rciSpaApp.controller('RBCtrl', function($scope, $timeout, $rootScope, Restangular, $route, $location, localStorageService){
    /**
     * Mark receipt as opened and update its value in local storage
     * @param object receipt
     */
    $scope.markAsOpen = function(receipt) {
        receipt.IsOpened = 1;
        receipt.VerifyStatus = 1;

        if (localStorageService.isSupported()) {
            var userReceipts = localStorageService.get('userReceipts');
            if (userReceipts) {
                var receipts = angular.fromJson(userReceipts);
                for (var i in receipts) {
                    if (receipts[i].ReceiptID == receipt.ReceiptID) {
                        receipts[i].IsOpened = 1;
                        receipts[i].VerifyStatus = 1;
                        localStorageService.add('userReceipts', angular.toJson(receipts));
                        break;
                    }
                }
            }
        }
    }

    $scope.paperReceipt = [];
    //Check to show the datepicker
    $scope.showDatepicker = false;
    //The beginning date of filter
    var date = new Date();
    $scope.dateFrom = (date.getFullYear() - 1) + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
    $scope.dateTo = date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);

    //$scope.datePeriodDisplay = $filter('onlyDate')(date.toString());
    $scope.dateFromDisplay = new Date($scope.dateFrom).toString();
    $scope.dateToDisplay = new Date($scope.dateTo).toString();

    $scope.transaction = 'CreatedTime';
    $scope.filterType = 'all';

    $scope.sortField = null;
    $scope.sortValue = 0;

    $scope.receiptList = [];

    //Store IDs of items/receipts which have checkbox selected
    $scope.itemDeleteList = [];
    $scope.receiptDeleteList = [];

    $scope.openFromApp = null;

    /*
     * Load receipt items by pieces
     */
    var rbData = [];
    var piece  = 0;
    var distance = null;
    $scope.loadedAllRBList = false;
    $scope.loadMoreReceipt = function(resetDistance) {
        if (distance === null || resetDistance) {
            piece    = 0;
            // Add more height for always show vertical scroll bar if data list is long
            distance = Math.ceil($('#rb-receipt-list').height()/24 + 24);
        }

        if (isNaN(distance)) {
            distance = 20;
        }

        if (rbData.length > piece) {
            var rows = rbData.slice(piece, piece + distance);
            $scope.receiptList = $scope.receiptList.concat(rows);
            piece += distance;
        }
        if (rbData.length == $scope.receiptList.length && $scope.receiptList.length > 0) {
            $scope.loadedAllRBList = true;
        }
    }

    /**
     * Temporary solution to load latest RB data from server. After loaded, we will operate with local storage
     * @type {boolean}
     */
    var loadLatestRbData = true;

    /**
     * Get receipt list
     * @param reload
     * @returns {boolean}
     */
    $scope.getReceiptList = function(reload) {
        if (localStorageService.isSupported() && !loadLatestRbData) {
            var userReceipts = localStorageService.get('userReceipts');
            if (userReceipts && !reload) {
                //$scope.receiptList = angular.fromJson(userReceipts);
                rbData = angular.fromJson(userReceipts);
                $scope.loadMoreReceipt();
                $rootScope.loadedModule++;
                return false;
            }
        }

        Restangular.one('receipts').getList().then(function(response) {
            angular.forEach(response, function(v, k) {
                angular.forEach(v.Items, function(vi, ki) {
                    if (vi.ExpensePeriod) {
                        vi.ExpensePeriod = new Date(vi.ExpensePeriod);
                    } else {
                        vi.ExpensePeriod = new Date();
                    }
                });
            });

            if (localStorageService.isSupported()) {
                localStorageService.add('userReceipts', angular.toJson(response));
                loadLatestRbData = false;
            }

            //$scope.receiptList = response;
            rbData = angular.fromJson(response);
            if (reload) {
                $scope.receiptList = [];
            }
            $scope.loadMoreReceipt(reload);

//            $('.app-table-child-wrapper').resizeHeight();

            $rootScope.loadedModule++;
        }, function(response) {
            $rootScope.loadedModule++;
            if (response.status !== 200) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            }
        });
    };

    /**
     * Listener for loading receipts
     *
     * @param loadFromServer
     *     If true, will get latest data from server and update to local storage
     * @param receipt
     *     If set, it's receipt instance, we don't get latest data from server, just update receipt data in
     * local storage
     */
    $rootScope.$on('RELOAD_RECEIPT_LIST', function(even, loadFromServer, receipt) {
        if (loadFromServer) {
            $scope.getReceiptList(loadFromServer);
            return;
        }

        if (localStorageService.isSupported()) {
            var userReceipts = localStorageService.get('userReceipts');
            if (userReceipts) {
                var receipts = angular.fromJson(userReceipts);

                for (var i in receipts) {
                    if (receipts[i].ReceiptID == receipt.ReceiptID) {
                        var items = [];
                        for (var j in receipt.Items) {
                            if (receipt.Items[j].Name) {
                                items.push(receipt.Items[j]);
                            }
                        }
                        receipt.Items = items;
                        receipts[i] = receipt;
                        //$scope.receiptList = receipts;
                        rbData = receipts;
                        $scope.receiptList = [];
                        $scope.loadMoreReceipt(true);
                        localStorageService.add('userReceipts', angular.toJson(receipts));
                        break;
                    }
                }
            }
        }

    });
    $scope.$watch('loggedInUser', function(newValue, oldValue, scope) {
        $scope.getReceiptList();
        // Notify to listener to update local storage
        $rootScope.$broadcast('UPDATE_PE_LOCAL_STORAGE');
    });

    //.. watch change on route to reload receipt list if needs
    $scope.$on('$routeChangeStart', function(next, current) {
        if (current.params.hasOwnProperty('reload')) {
            $scope.getReceiptList(true);
        }
    });

    $rootScope.$on('OPEN_RB_ADD_ITEMS', function(event, app) {
        $('#rb-back-to-app').addClass('show').removeClass('hide');
        $scope.openFromApp = app;
    });

    $scope.backToApp = function() {
        $('#receiptbox-wrapper').hide();
        if ($scope.openFromApp == 'trip_detail') {
            $('#trip-detail-wrapper').show();
        }
        if ($scope.openFromApp == 'personal_expense') {
            $('#personal-expense-wrapper').show();
        }
    }

    /**
     * Function to set check box status
     *
     * @param object element
     */
    $scope.setCheckboxStatus = function(element) {
        // Set new checked status
        element.IsChecked = !element.IsChecked;

        // Has item?
        if (element.hasOwnProperty('Items') && element.Items.length) {
            angular.forEach(element.Items, function(item, key) {
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

    $scope.setCollapseStatus = function(element) {
        element.IsCollapsed = !element.IsCollapsed;
    }

    /**
     * Filter receipts
     */
    $scope.filterReceipts = function() {
        if (new Date($scope.dateFrom) > new Date($scope.dateTo)) {
            $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
            return false;
        }

        Restangular.one('receipts').customGET('',
            {
                from: $scope.dateFrom,
                to: $scope.dateTo,
                transaction: $scope.transaction,
                type: $scope.filterType,
                sortField: $scope.sortField,
                sortValue: $scope.sortValue
            })
            .then(function(response) {
                angular.forEach(response, function(v, k) {
                    angular.forEach(v.Items, function(vi, ki) {
                        if (vi.ExpensePeriod) {
                            vi.ExpensePeriod = new Date(vi.ExpensePeriod);
                        }
                    });
                });

                //$scope.receiptList = response;
                rbData = response;
                $scope.receiptList = [];
                $scope.loadMoreReceipt(true);
            }, function(response) {
                if (response.status !== 200) {
                    $.showMessageBox({content: response.data.message.join('<br/>')});
                }
            });
    }

    /**
     * Sort receipts
     */
    $scope.sortReceipts = function(field) {
        if (field !== $scope.sortField) {
            $scope.sortField = field;
        }

        $scope.sortValue = $scope.sortValue == 0 ? 1 : 0;

        $scope.filterReceipts();
    }

    /**
     * Receive new receipts
     */
    //Auto update
    var backgroundReceive = window.setInterval(function(){ $scope.receiveNewReceipts() }, 60000);
    $scope.$on('$routeChangeSuccess', function(event, currentRoute, previousRoute) {
        if (currentRoute.currentPath != '/receiptbox') {
            clearInterval(backgroundReceive);
        } else {
            backgroundReceive = window.setInterval(function(){ $scope.receiveNewReceipts() }, 60000);
        }
    });

    //Manual update
    $scope.receiveNewReceipts = function() {
        $('.app-nav-block .app-icon.receive').toggleClass('animated-receive');
        $timeout (function(){
            $('.app-nav-block .app-icon.receive').toggleClass('animated-receive');
        }, 1400);
        Restangular.one('receipts').customGET('receive').then(function(response) {
            var receiptUpdateList = response;
            receiptUpdateList = receiptUpdateList.concat($scope.receiptList);
            $scope.receiptList = receiptUpdateList;

            if (localStorageService.isSupported()) {
                localStorageService.add('userReceipts', angular.toJson($scope.receiptList));
            }
        }, function(response) {
            if (response.status != 200) {
                clearInterval(backgroundReceive);
                $.showMessageBox({content: response.data.message.join('<br/>')});
            }
        });
    }

    //Snap Receipt for mobile
    $scope.uploadURL = $rootScope.ocrUploaderUrl;
    $scope.uploadUserID = $rootScope.loggedInUser.UserID;
    $scope.uploadLocation = 'Oregon';
    $scope.triggerTakeSnap = function(){
        $("#takePictureField").trigger('click');
    }
    $scope.deviceSupportCamera = deviceIsMobileDevice();
    $('#snap_upload_form').ajaxForm({
        success: function(data) {
            //alert('Image successful uploaded');
        },
        error: function(data) {
            //alert('got some errors!');
        }
    });
});

function triggerUploadImage(files){
    if (files.length>0) {
        $('#snap_upload_form').submit();
    }
}