'use strict';

rciSpaApp.controller('PECtrl', function($scope, $rootScope, Restangular, $route, $location, localStorageService, $timeout) {
    /*Object click status*/
    $scope.clicked = {
        periodRange: false,
        listItemOnly: false,
        checkAll: false,
        collapseAll: true,
        numOfCatExpanded: 0
    };
    $scope.dateCRDisplay = 'Select month';
    $scope.filtering = false;
    $scope.resetFilter = false;
    $scope.$watch('clicked.numOfCatExpanded', function(newValue, oldValue) {
        if (newValue == 0) {
            $scope.clicked.collapseAll = true;
        }
    })

    $scope.peItems = $scope.PECats = $scope.tmpPeList = [];

    var peData = [];
    var peCats = [];
    var step = 0;
    var intervalPELoad;
    $scope.loadMorePe = function() {
        if (peData.length && peData[0].length > step) {
            var rows = (peData[0].slice(step, step + 5));
            $scope.$apply(function() {
                $scope.PECats = $scope.PECats.concat(rows);
                $scope.tmpPeList = $scope.PECats;
            });
            step += 5;
        }
        if (angular.isDefined(peData[0]) && peData[0].length == $scope.PECats.length) {
            clearInterval(intervalPELoad);
        }
    }

    var today = new Date();
    var dd = today.getDate();
    var mm = today.getMonth() + 1;
    var yyyy = today.getFullYear();
    if (dd < 10) {
        dd = '0' + dd
    }
    if (mm < 10) {
        mm = '0' + mm
    }

    //Set default peroidFrom is the first date of current month
    $scope.tmpPeriodFrom = yyyy + '-' + mm + '-' + '01';

    $scope.getPEList = function(reload) {
        if (localStorageService.isSupported()) {
            var userPE = localStorageService.get('userPE');

            if (userPE && !reload) {
                peData = angular.fromJson(userPE);
                //$scope.loadMorePe();
                $scope.peItems = peData[1];
                $scope.totalAmount = peData[2];

                $rootScope.loadedModule++;

                if (typeof peData[3] !== 'undefined') {
                    var pePeriods = peData[3];
                    $scope.usePeriodRange = peData[3].usePeriodRange;
                    $scope.periodFrom = peData[3].dateFrom;
                    $scope.periodTo = peData[3].dateTo;

                    $scope.tmpUsePeriodRange = angular.copy($scope.usePeriodRange);
                    $scope.tmpPeriodFrom = angular.copy($scope.periodFrom);
                    $scope.tmpPeriodTo = angular.copy($scope.periodTo);
                }

                $scope.PECats = peData[0];
                $rootScope.isLoadingAnalytic = false;
                $("body").removeClass("loading");
                return false;

                // Set interval to 500ms to render Categories items in queue
//                intervalPELoad = setInterval(function() {
//                    $scope.loadMorePe();
//                }, 500);
                return false;
            }
        }
        try {
            var defaultDate = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
        } catch (err) {
            var defaultDate = new Date();
        }

        $scope.usePeriodRange = angular.copy($scope.tmpUsePeriodRange);
        $scope.periodFrom = angular.copy($scope.tmpPeriodFrom);
        $scope.periodTo = angular.copy($scope.tmpPeriodTo);

        if (!$scope.periodFrom) {
            $scope.periodFrom = defaultDate.getFullYear() + '-' + ('0' + (defaultDate.getMonth() + 1)).slice(-2) + '-01';
        }

        if (new Date($scope.periodFrom) > new Date($scope.periodTo)) {
            $.showMessageBox({content: 'End Date must be equal or greater than Start Date'});
            return false;
        }
        Restangular.one('categories').customGET('',
                {
                    app: 'personal_expense',
                    dateFrom: $scope.periodFrom,
                    dateTo: $scope.periodTo
                })
                .then(function(response) {
                    $rootScope.isLoadingAnalytic = false;
                    $("body").removeClass("loading");
                    peData = arrangeToMultiLevelCats(response);
                    peData.push({
                        usePeriodRange: $scope.usePeriodRange,
                        dateFrom: $scope.periodFrom,
                        dateTo: $scope.periodTo
                    });

                    if (localStorageService.isSupported()) {
                        localStorageService.add('userPE', angular.toJson(peData));
                    }

                    //Reset categories array
                    $scope.PECats = peData[0];
                    step = 0;
                    $scope.peItems = peData[1];
                    $scope.totalAmount = peData[2];

                    $scope.filtering = false;

                    $rootScope.loadedModule++;
                }, function(response) {
                    $rootScope.loadedModule++;
                    if (response.status !== 200) {
                        $.showMessageBox({content: response.data.message.join('<br/>')});
                    }
                });
    };
    $scope.getPEList();

    /**
     * Delete selected item
     */
    $scope.deleteItems = function() {
        var itemDeleteList = [];
        $('#pe-item-list tbody tr[class^="cat-lv"].item .app-icon.icon-checkedbox-sqr').each(function(k, v) {
            itemDeleteList.push($(v).attr('data-id'));
        });

        if (itemDeleteList.length == 0) {
            $.showMessageBox({content: "Please select item(s) to remove.", boxTitle: 'REMOVE ITEM(S)', boxTitleClass: ''});
            return;
        }

        $.showMessageBox({
            content: '<p>Are you sure you want to remove selected item(s) from PersonalExpense?</p><p>Please note, the receipt/invoice relating to this item will not be removed from your ReceiptBox.</p>',
            boxTitle: 'REMOVE ITEM(S)',
            boxTitleClass: '',
            type: 'confirm',
            onYesAction: function() {
                $timeout(function() {
                    Restangular.one('categories').customPUT({ItemIDs: itemDeleteList.join(',')}, 'unassign').then(function(response) {
                        $scope.$emit('RELOAD_PE_LIST', true);
                        $scope.$emit('RELOAD_RECEIPT_LIST', true);
                    }, function(response) {
                        $.showMessageBox({content: response.data.message.join('<br/>')});
                    });
                });
            }
        });
    }

    $scope.export = function() {
        window.onbeforeunload = null;
        var m_names = new Array();
        m_names["01"] = "January";
        m_names["02"] = "February";
        m_names["03"] = "March";
        m_names["04"] = "April";
        m_names["05"] = "May";
        m_names["06"] = "June";
        m_names["07"] = "July";
        m_names["08"] = "August";
        m_names["09"] = "September";
        m_names["10"] = "October";
        m_names["11"] = "November";
        m_names["12"] = "December";

        var title;

        var contentMessage = "Export PersonalExpense data for the period of<br>";
        if ((typeof ($scope.periodTo) == 'undefined') || ($scope.periodFrom == $scope.periodTo)) {
            var str = $scope.periodFrom;
            var yearFrom = str.substring(0, 4);
            var monthFrom = str.substring(5, 7);
            contentMessage = contentMessage + m_names[monthFrom] + " " + yearFrom + "<br>in CSV format";
            title = "PersonalExpense in " + m_names[monthFrom] + " " + yearFrom;
        } else {
            var str1 = $scope.periodFrom;
            var str2 = $scope.periodTo;
            var yearFrom = str1.substring(0, 4);
            var monthFrom = str1.substring(5, 7);
            var yearTo = str2.substring(0, 4);
            var monthTo = str2.substring(5, 7);
            contentMessage = contentMessage + m_names[monthFrom] + " " + yearFrom + " - " + m_names[monthTo] + " " + yearTo + "<br>in CSV format";
            title = "PersonalExpense from " + m_names[monthFrom] + " " + yearFrom + " to " + m_names[monthTo] + " " + yearTo;
        }

        $.showMessageBoxPopup({
            content: '<p class="default-text">' + contentMessage + '</p>',
            type: 'confirm',
            boxTitle: 'EXPORT',
            boxTitleClass: '',
            onYesAction: function() {
                $timeout(function() {
                    Restangular.one('export').customGET('export', {
                        'app': 'personal_expense',
                        'type': 'csv',
                        'dateFrom': $scope.periodFrom,
                        'dateTo': $scope.periodTo,
                        'title': title
                    }).then(function(response) {
                        if (response.FileName) {
                            window.location.href = API_URL + '/export/download?fileName=' + response.FileName;
                        }

                        //Re-bind the beforeunload event
                        setTimeout(function() {
                            window.onbeforeunload = function() {
                                //The message is displayed well in Chrome, but in Firefox we cannot override the default message
                                return 'ReceiptClub says:';
                            };
                        }, 500);
                    }, function(response) {
                        if (response.status !== 200) {
                            $.showMessageBoxPopup({
                                content: response.data.message.join('<br/>'),
                                type: 'alert',
                                boxTitle: 'EXPORT ERROR',
                                boxTitleClass: '',
                            });
                        }

                        //Re-bind the beforeunload event
                        window.onbeforeunload = function() {
                            //The message is displayed well in Chrome, but in Firefox we cannot override the default message
                            return 'ReceiptClub says:';
                        };
                    });
                });
            }
        });
    }
    $scope.toggleRange = function() {
        $scope.filtering = true;
        if (typeof ($scope.tmpUsePeriodRange) == 'undefined' || !$scope.tmpUsePeriodRange) {
            $('#personal-expense-wrapper .filterdate .date-to .ui-datepicker-month').trigger('change');
            $scope.tmpUsePeriodRange = true;
        } else {
            $scope.tmpUsePeriodRange = false;
            $scope.tmpPeriodTo = undefined;
        }
    }
    $scope.toggle_drd_filter = function()
    {
        if ($scope.showDRD_datefilter) {
            $scope.showDRD_datefilter = false;
        } else {
            $scope.showDRD_datefilter = true;
        }
        if ($scope.showDRD_datefilterRange) {
            $scope.showDRD_datefilterRange = false
        }
        $('#personal-expense-wrapper .filterdate .date-to .ui-datepicker-month').trigger('change');
    }
    $scope.setDatefilterCR = function()
    {
        try {
            var defaultDate = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
        } catch (err) {
            var defaultDate = new Date();
        }
        $scope.toggle_drd_filter();
        $scope.filteringRange = false;
        $scope.filtering = true;
        $scope.tmpPeriodFrom = defaultDate.getFullYear() + '-' + ('0' + (defaultDate.getMonth() + 1)).slice(-2) + '-01';
        $scope.tmpPeriodTo = angular.copy(defaultDate.getFullYear() + '-' + ('0' + (defaultDate.getMonth() + 1)).slice(-2) + '-' + ('0' + defaultDate.getDate()).slice(-2));
    };
    $scope.hideAllFilter = function() {
        $scope.showDRD_datefilter = false;
        $scope.showDRD_datefilterRange = false;
        $scope.blockRageActive = false;
        $scope.filtering = true;
    }
    $scope.setDatefilterRange = function()
    {
        $scope.showDRD_datefilter = false;
        $scope.showDRD_datefilterRange = true;
        $scope.blockRageActive = true;
        $scope.filteringRange = true;
    }
    $scope.initRange = function() {
        $('.app-pe .filterdate .checkbox-range').prop('checked', $scope.usePeriodRange);
    }
    $rootScope.$on('OPEN_APPFROMDASHBOARD', function (even, app,dataPertype) {
        if(app == 'persional-expense'){
            if(dataPertype){
                $rootScope.isLoadingAnalytic = true;
                $timeout(function(){
                    try { var defaultDate = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone); } catch (err) { var defaultDate = new Date();}
                    if(dataPertype.amountMonth == 1){
                        $scope.goToPE();
                        var datefrom = defaultDate.getFullYear() + '-' + ('0' + (defaultDate.getMonth() + 1)).slice(-2) + '-01';
                        $scope.tmpPeriodFrom = $scope.tmpPeriodTo = datefrom;
                        $('#personal-expense-wrapper .filterdate .date-from').datepicker("setDate", datefrom);
                        $scope.tmpUsePeriodRange = false;
                        $scope.getPEList(true);
                        if($('#personal-expense-wrapper #checkbox-date').is(':checked')) {
                            $('#personal-expense-wrapper #checkbox-date').prop('checked', false);
                        }
//                        $location.path('/personal-expense/analytic/'+datefrom+'/');
                    }else if(dataPertype.amountMonth == 3){
                        $scope.goToPE();
                        if(defaultDate.getMonth() - 1 <= 0) {
                            var datefrom = defaultDate.getFullYear()-1  + '-' +(12 + defaultDate.getMonth() - 1 ) + '-' + '01';
                        } else {
                            var datefrom = defaultDate.getFullYear()  + '-' +('0' + (defaultDate.getMonth() - 1) ).slice(-2)+ '-' + '01';
                        }
                        var dateto = defaultDate.getFullYear() + '-' + ('0' + (defaultDate.getMonth() + 1)).slice(-2) + '-01';
                        $scope.tmpUsePeriodRange = true;
                        $scope.tmpPeriodFrom = datefrom;
                        $scope.tmpPeriodTo = dateto;
                        $('#personal-expense-wrapper .filterdate .date-from').datepicker("setDate", datefrom);
                        $('#personal-expense-wrapper .filterdate .date-to').datepicker("setDate", dateto);
                        $scope.getPEList(true);
                        if(!$('#personal-expense-wrapper #checkbox-date').is(':checked')) {
                            $('#personal-expense-wrapper #checkbox-date').prop('checked', true);
                        }
//                        $location.path('/personal-expense/analytic/'+datefrom+'/'+dateto+'/');
                    }else{
                        var datefrom = defaultDate.getFullYear() + '-' + ('0' + (defaultDate.getMonth() + 1)).slice(-2) + '-01';
                        $location.path('/personal-expense/analytic/'+datefrom+'/');
                        $timeout(function(){
                            $('#analytic-merchant-tab').click();
                        },1000);
                        $scope.getPEList(true);
                    }
                });
            }
        }
    });
    
    $scope.goToPE = function() {
        $location.url('/personal-expense');
        $('#nav-expense-management').click();
        setTimeout(function() {
            $('.page-app').hide();
            $('#ngview-wrapper').hide();
            $('#home-page-wrapper').hide();
            $('#personal-expense-wrapper').show();

            $('#container').removeClass('landing-wrapper');
            $('#top-header').removeClass('hide').addClass('show');
            $('#sidebar-right').removeClass('hide').addClass('show');
            $('body').removeClass('front').removeClass('profile');
        }, 1000);
    }
    
    // Listener for updating PE local storage
    $rootScope.$on("UPDATE_PE_LOCAL_STORAGE", function(event, message) {
        try {
            var defaultDate = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
        } catch (err) {
            var defaultDate = new Date();
        }

        Restangular.one('categories').customGET('',
                {
                    app: 'personal_expense',
                    dateFrom: defaultDate.getFullYear() + '-' + ('0' + (defaultDate.getMonth() + 1)).slice(-2) + '-01'
                }).then(function(response) {
            var peData = arrangeToMultiLevelCats(response);

            if (localStorageService.isSupported()) {
                localStorageService.add('userPE', JSON.stringify(peData));
            }

            $rootScope.loadedModule++;
        }, function(response) {
            $rootScope.loadedModule++;
            if (response.status !== 200) {
                $.showMessageBox({content: response.data.message.join('<br/>')});
            }
        });
    });

    $rootScope.$on('RELOAD_PE_LIST', function(event, loadFromServer) {
        $scope.getPEList(loadFromServer);
    });
});

function arrangeToMultiLevelCats(categories) {
    var arrangedCatArray = [];
    var arrangedItemArray = [];
    var totalAmount = 0;
    var tmp, tmpItem, itemClass;
    var catGroup = [];
    var len = 0;
    angular.forEach(categories, function(cat, k) {
        tmp = angular.copy(cat);  //clone data to keep original data
        tmp.check = false;
        tmp.parentCollapse = true;
        tmp.masterCollapse = true;
        tmp.collapseDisabled = false;
        tmp.expand = false;
        tmp.CategoryName = cat.Name;
        tmp.Type = 'category';
        tmp.class = 'cat-lv' + (cat.Depth + 1);
        itemClass = 'cat-lv' + (cat.Depth + 2) + ' item';
        if (tmp.Amount == null) {
            tmp.Amount = '0';
        }
        if (cat.Depth == 0) {
            if (catGroup.length > 0) {
                if (catGroup[len - 1].Depth == 1) {
                    catGroup[len - 1].collapseDisabled = true;
                }
                arrangedCatArray.push(catGroup);
            }

            catGroup = [];
            len = 0;
            tmp.display = true;
            tmp.parentCollapse = false;
            tmp.masterCollapse = false;
            catGroup.push(tmp);
            len++;
            if (cat.Amount) {
                totalAmount += parseFloat(cat.Amount);
            }
        } else if (cat.Depth == 1) {
            if (catGroup[len - 1].Depth == 1) {
                catGroup[len - 1].collapseDisabled = true;
            }
            tmp.parentCollapse = false;
            tmp.display = true;
            tmp.index = len;
            catGroup.push(tmp);
            len++;
        } else if (cat.Depth == 2) {
            tmp.display = true;
            tmp.index = len;
            catGroup.push(tmp);
            len++;
        }

        if (cat.Items.length) {
            angular.forEach(cat.Items, function(item, k) {
                tmpItem = item;
                tmpItem.class = itemClass;
                tmpItem.check = false;
                if (cat.Depth == 0) {
                    tmpItem.masterCollapse = true;
                } else {
                    tmpItem.parentCollapse = true;
                }
                tmpItem.CategoryName = cat.Name;
                //tmpItem.ExpensePeriodFrom = new Date(tmpItem.ExpensePeriodFrom * 1000);
                //tmpItem.PurchaseTime = new Date(tmpItem.PurchaseTime * 1000);
                tmpItem.Type = 'item';
                tmpItem.index = len;
                tmpItem.hasAttachment = (tmpItem.Attachments.length > 0) ? true : false;
                tmpItem.Amount = parseFloat(tmpItem.Amount);
                catGroup.push(tmpItem);
                len++;

                arrangedItemArray.push(tmpItem);
            });
        }
    });

    //Add the last catGroup to array
    if (catGroup.length > 0) {
        if (catGroup[len - 1].Depth == 1) {
            catGroup[len - 1].collapseDisabled = true;
        }
        arrangedCatArray.push(catGroup);
    }

    return [arrangedCatArray, arrangedItemArray, totalAmount];
}
