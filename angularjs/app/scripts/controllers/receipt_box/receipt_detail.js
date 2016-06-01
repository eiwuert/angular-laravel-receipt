'use strict';
rciSpaApp.controller('ReceiptDetailCtrl',
    ['$scope', '$rootScope', 'Restangular', '$route','restAngularService', 'localStorageService', '$timeout', 'openExchange', '$filter', 'AwsS3Sdk', '$q', '$compile', '$location',
        function ($scope, $rootScope, Restangular, $route,restAngularService, localStorageService, $timeout, openExchange, $filter, AwsS3Sdk, $q, $compile, $location) {
            /**
             * Supported currencies
             * @type object
             */
            $scope.globalCurrencies = openExchange.getCurrencies();
            $scope.tmpCategoryMethod = '';
            // New rule:
            // Initially show just the top 7 global currencies, with an option for more (...) - when clicked it will show the
            // rest of the currencies in ascending alphabetic order. Also add the country names as a tool tip
            // Use the following top 7: USD, EUR, JPY, GBP, AUD, CHF, CAD
            $scope.top7GlobalCurrencies = {};
            angular.forEach($scope.globalCurrencies, function (name, code) {
                switch (code) {
                    case 'USD':
                    case 'EUR':
                    case 'JPY':
                    case 'GBP':
                    case 'AUD':
                    case 'CHF':
                    case 'CAD':
                        $scope.top7GlobalCurrencies[code] = name;
                        break;
                }
            });


            $scope.currencies = $scope.top7GlobalCurrencies;

            $scope.paymentTypes = [
                {PaymentTypeID: 4, PaymentTypeName: "Credit card"},
                {PaymentTypeID: 1, PaymentTypeName: "Cash"},
                {PaymentTypeID: 2, PaymentTypeName: "Check"},
                {PaymentTypeID: 3, PaymentTypeName: "Debit card"}
            ]

            $scope.pageTitle = 'Create Manual Receipt';
            $scope.receiptId = 0;

            $scope.firstShowPDF = 0;
            $scope.nextItem = 0;
            $scope.prevItem = 0;

            $scope.idQickCategory = "";
            $scope.reAssignTmpAmount = false;
            $scope.receiptInSaving = false;

            $scope.categoryTree = [];
            $scope.showRefresh = false;
            $scope.showPdfViewer = true;

            $scope.setDefaultReceiptItem = function (num, categoryInfo, tripInfo, forceCategory) {
                var results = [], tmp;

                try {
                    var currentMonthYear = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
                } catch (err) {
                    var currentMonthYear = new Date();
                }


                for (var i = 0; i < num; i++) {
                    tmp = {
                        ItemID: 0,
                        Name: "",
                        Amount: "",
                        CategorizeStatus: "",
                        IsChecked: false,
                        IsJoined: 0,
                        ExpensePeriod: currentMonthYear,
                        DeletedFileIDs: [],
                        Attachments: [],
                        CategoryApp: $rootScope.defaultApp,
                        CategoryAppAbbr: $rootScope.defaultAppAbbr,
                        CategoryID: null,
                        CategoryName: null,
                        TripID: null,
                        Reference: null,
                        forceCategory: forceCategory ? forceCategory : false,
                        Tags: ''
                    };

                    if (angular.isDefined(categoryInfo)) {
                        tmp.CategoryApp = categoryInfo.app;
                        tmp.CategoryAppAbbr = categoryInfo.appAbbr;
                        tmp.CategoryID = categoryInfo.categoryID;
                        tmp.CategoryName = categoryInfo.categoryName;

                        if (tmp.CategoryID) {
                            tmp.CategorizeStatus = 2;
                        }

                        if (angular.isDefined(tmp.CategoryID) && tmp.CategoryID) {
                            tmp.forceCategory = true;
                        }
                    }
                    if (angular.isDefined(tripInfo)) {
                        tmp.TripID = tripInfo.tripID;
                        tmp.Reference = tripInfo.reference;
                        tmp.forceCategory = true;
                        tmp.CategorizeStatus = 2;
                    }

                    results.push(tmp);
                }

                return results;
            }

            $rootScope.$on('LOAD_RECEIPT_DETAIL', function (event, receiptId, itemId, openFrom, verifyStatus, categoryInfo, tripInfo, autoDisabled) {
                $scope.openRDFrom = 'receiptbox-wrapper';
                if (openFrom) {
                    $scope.openRDFrom = openFrom;
                }

                if ($scope.openRDFrom != 'trip-detail-wrapper') {
                    $scope.menuOpenRDFrom = 'menu-' + $scope.openRDFrom.replace('-wrapper', '');
                    if ($scope.openRDFrom == 'personal-expense-wrapper') {
                        $scope.initializedApp = 'personal_expense';
                    }
                    if ($scope.openRDFrom == 'education-expense-wrapper') {
                        $scope.initializedApp = 'education_expense';
                    }
                    if ($scope.openRDFrom == 'personal-assets-wrapper') {
                        $scope.initializedApp = 'personal_assets';
                    }
                    if ($scope.openRDFrom == 'business-expense-wrapper') {
                        $scope.initializedApp = 'business_expense';
                    }
                    if ($scope.openRDFrom == 'business-assets-wrapper') {
                        $scope.initializedApp = 'business_assets';
                    }
                } else {
                    $scope.menuOpenRDFrom = 'menu-travel-expense';
                    $scope.initializedApp = 'travel_expense';
                    $("#close-with-tooltip").tooltip({
                        placement: "bottom",
                        title: "Back to travel expense"
                    });
                }

                if (receiptId) {
                    $scope.isNewManualReceipt = false;
                    $scope.isManualReceipt    = false;
                    $scope.pageTitle          = 'Receipt Details';
                    $scope.receiptId          = parseInt(receiptId);
                    $scope.showPdfViewer      = true;
                    $rootScope.manualReceipt  = true;
                } else {
                    $scope.receiptId          = 0;
                    $scope.isNewManualReceipt = true;
                    $scope.isManualReceipt    = true;
                    $scope.showPdfViewer      = false;
                    $rootScope.manualReceipt  = true;
                }

                if (angular.isDefined(categoryInfo)) {
                    $scope.initializedApp = categoryInfo.app;

                }

                $scope.tripInfo = tripInfo;
                $scope.categoryInfo = categoryInfo;
                $scope.autoDisabled = autoDisabled;

                $scope.initReceiptDetail(verifyStatus, categoryInfo, tripInfo);
            });

            $scope.$on('$routeChangeStart', function (event, currentRoute, previousRoute) {
                if (currentRoute.currentPath === '/receipt-detail' && currentRoute.params.hasOwnProperty('receiptId')) {
                    $scope.isNewManualReceipt = false;
                    $scope.showPdfViewer      = true;
                    $scope.pageTitle = 'Receipt Details';
                    $scope.receiptId = parseInt(currentRoute.params.receiptId);
                } else {
                    $scope.receiptId = 0;
                }
                $scope.initReceiptDetail();
            });

          $scope.setPaginationValue = function(callBack){
            // Assign value for next & previous button
            if ($rootScope.idReceiptsList.length) {
              var lastItemPos = $rootScope.idReceiptsList.length - 1;

              var currentPos = $rootScope.idReceiptsList.indexOf(String($scope.receiptId));

              // Not found, receipt id may not exist
              if (currentPos < 0) {

              } else if (currentPos == 0) { // Viewing receipt is the first, don't display Previous button
                $scope.nextItem = $rootScope.idReceiptsList[currentPos + 1];
                $scope.prevItem = 0;
              } else if (currentPos > 0 && currentPos < lastItemPos) { // Viewing receipt is in first & last position item range
                $scope.nextItem = $rootScope.idReceiptsList[currentPos + 1];
                $scope.prevItem = $rootScope.idReceiptsList[currentPos - 1];
              } else if (currentPos == lastItemPos) { // Viewing receipt is the last, don't display Next button
                $scope.nextItem = 0;
                $scope.prevItem = $rootScope.idReceiptsList[currentPos - 1];
              }

              //Callback for next page receipt box
              if(typeof callBack != "undefined") callBack(currentPos);
            }
          }

          /**
           * @event Handle detected user hit back button.
           */

          $rootScope.$on('$locationChangeStart', function (event, newUrl, oldUrl) {
            /*
            * Check current screen
            * If user already in receipt detail screen -> continue redirect
            * */
            var res = (new RegExp('receipt-detail')).test(oldUrl);
            if (res) {
              /*
              * If new url different old url and user changed content in receipt details
              * */
              if (newUrl != oldUrl) {
                if ($scope.userChangedContent) {

                  /**
                   * Stop redirect
                   */
                  event.preventDefault();

                  /*
                  * Show comfirmation box if user changed content
                  * */
                    $.showMessageBox({
                      content: 'Do you want to save your changes to this receipt?',
                      boxTitle: 'SAVE RECEIPT',
                      boxTitleClass: '',
                      labelYes: 'Save',
                      labelNo: "Don't Save",
                      type: 'confirm',
                      onYesAction: function () {
                        $timeout(function () {
                          $scope.userChangedContent = false;
                          $scope.save($scope.receipt, true, true, function () {
                            $scope.close();
                            $rootScope.changeRoute('/receiptbox');
                          });
                        });
                      },
                      onNoAction: function () {
                        $timeout(function () {
                          $scope.userChangedContent = false;
                          $scope.close();
                          $rootScope.changeRoute('/receiptbox');
                        });
                      }
                    });
                }
              }
            }
          });

            $scope.initReceiptDetail = function (verifyStatus, categoryInfo, tripInfo) {
                $scope.$watch('userChangedContent', function (newval, oldval) {
                    if (newval) {
                      /**
                       * Handle detect user reload this app
                       * @param event
                       * @returns {string}
                       */
                      window.onbeforeunload = function (event) {
                        return "Do you want to save your changes to this receipt?";
                      };
                    }
                });

                $scope.useQuickCategorization     = false;
                $scope.requireUserConvertCurrency = false;
                $scope.isResetCurrency            = true;
                $scope.hideAfterReset             = true;
                $scope.convertCurrencyPartFlag    = true;
                $scope.noItemSelected             = true;
                $scope.categoryTree               = $rootScope.categories[0].preparedCatList;
                $scope.treeApp                    = '';
                $scope.receiptInProcessing        = false;

                // Don't show ads loading in case user create manual receipt
                // 20141204: QuyPV: skip ads cause manual receipt unable to apply css for all elements and make
                // layout broken >> we dont skip the ads but let it appears  in a short time (0.5s)
                var $element = $('#loading-indicator');
                var adShowTime = 500; // 0.5s
                if (!$scope.isNewManualReceipt) {
                    // Generate a random number to show advertising. All images in index.html#loading-indicator
                    var totalAd = $element.find('img').length;
                    var eleShow = 1;
                    adShowTime = 3000; //3s for verified receipts

                    if (totalAd) {
                        eleShow = Math.floor(Math.random() * totalAd) + 1;
                    }

                    $element.find('img').removeClass('show').addClass('hide');
                    $element.find('#advert-' + eleShow).removeClass('hide').addClass('show');

                    if (verifyStatus == 0) {
                        adShowTime = 5000;
                    }
                }

                $timeout(function () {
                    $element.hide();
                }, adShowTime);

                $scope.isSaved = false;
                $('.replace-or-ignore a').tooltip();
                $('#resetToOriginalCurrency').tooltip();
                $scope.userChangedContent = false;
                jQuery('#rd-container input, #rd-container textarea, #rd-container select').on('keypress', function (e) {
                    $scope.userChangedContent = true;
                }).on('change', function (e) {
                    $scope.userChangedContent = true;
                });
                jQuery('#rd-container').on('keypress', '.item_status_col input', function (e) {
                    $scope.userChangedContent = true;
                });
                // Helper variables
                $scope.selectedAmount = '';
                $scope.variousAmount = '';
                $scope.responseMessage = [];

                /**
                 * The variable contain the number that set maximum item to display in Receipt Detail in order to avoid performance
                 * render issue
                 * @type {number}
                 */
                var itemPiece = 0;
                var itemPieceStep = 5;
                var totalItem = 0;
                $scope.convertedCurrency = false;
                $scope.showConfirmConvertMessage = false;
                $scope.convertingMessage = '';
                $scope.convertingMessageHtmlClass = 'blue';
                $scope.ignoreWarning = false;

                $scope.setPaginationValue();

                jQuery('.icon-btn-back-to-prev-app').tooltip();
                if ($scope.receiptId) {
                    $("#pdfContainer").empty(); // Empty receipt pdf.
                    $.fn.resizeHeight('receipt_detail');
                    var paramsQuery = {
                      api   : 'receipts',
                      param : {
                        receiptID: $scope.receiptId
                      }
                    }

                  restAngularService.getReceiptById(paramsQuery).then(function (response) {
                        $scope.lblReceiptType = $filter('receiptTypeFilter')(response.ReceiptType);
                        totalItem = response.Items.length;
                        angular.forEach(response.Items, function (v, k) {
                            if (0 == k && 0 == v.IsJoined && 1 == response.HasCombinedItem) {
                                response.categorizeMethod = 'one_item';
                            }

                            if (v.ExpensePeriod && v.ExpensePeriod.indexOf('1969') == -1 && v.ExpensePeriod.indexOf('1970') == -1) {
                            } else {
                                try {
                                    v.ExpensePeriod = new timezoneJS.Date(new Date(response.PurchaseTime), $rootScope.loggedInUser.Timezone).toISOString();
                                } catch (err) {
                                    v.ExpensePeriod = new Date(response.PurchaseTime).toISOString();
                                }
                            }
                            v.Tags = v.Tags.join(',');
                        });

                        var tmpItems = response.Items;
                        delete response.Items;

                        $scope.receipt = response;
                        $scope.pageTitle = ($scope.receipt.ReceiptType == 6) ? "Invoice Details" : "Receipt Details";
                        $scope.receipt.forceCategory = false;
                        $scope.receipt.CategoryApp = $rootScope.defaultApp;
                        $scope.receipt.CategoryAppAbbr = $rootScope.defaultAppAbbr;
                        $scope.receipt.Memo = response.Memo;
                        $scope.receipt.RebateAmount = response.RebateAmount;
                        $scope.receipt.EmailSender = response.EmailSender;

                        $scope.isManualReceipt = ($scope.receipt.ReceiptType == 4);

                        if ($scope.receipt.MerchantCountry != 'undefined') {
                            $( "#MerchantCountryCode" ).val($scope.receipt.MerchantCountry);
                        }
                      var hasItemCategorized = false;
                        for (var i in tmpItems) {
                            if (i == 0 && $scope.receipt.HasCombinedItem == 1 && !tmpItems[0].CategoryID && tmpItems[0].IsJoined == 0) {
                                $scope.receipt.CategoryApp = tmpItems[0].CategoryApp;
                                $scope.receipt.CategoryAppAbbr = tmpItems[0].CategoryAppAbbr;
                                break;
                            }

                            if (angular.isDefined(tmpItems[i].CategoryID) && tmpItems[i].CategoryID != 0 && tmpItems[i].CategoryApp == 'personal_expense') {
                                hasItemCategorized = true;
                                break;
                            }

                            if (angular.isDefined(tmpItems[i].CategoryID) && tmpItems[i].CategoryID != 0 && tmpItems[i].CategoryApp == 'education_expense') {
                                hasItemCategorized = true;
                                break;
                            }

                            if (angular.isDefined(tmpItems[i].CategoryID) && tmpItems[i].CategoryID != 0 && tmpItems[i].CategoryApp == 'business_expense') {
                                hasItemCategorized = true;
                                break;
                            }

                            if (angular.isDefined(tmpItems[i].CategoryID) && tmpItems[i].CategoryID != 0 && tmpItems[i].CategoryApp == 'personal_assets') {
                                hasItemCategorized = true;
                                break;
                            }

                            if (angular.isDefined(tmpItems[i].CategoryID) && tmpItems[i].CategoryID != 0 && tmpItems[i].CategoryApp == 'business_assets') {
                                hasItemCategorized = true;
                                break;
                            }

                            if (angular.isDefined(tmpItems[i].CategoryID) && tmpItems[i].CategoryID != 0 && tmpItems[i].TripID > 0) {
                                hasItemCategorized = true;
                                break;
                            }
                        }

                        if ($scope.receipt.App == 'MX') {
                            var mixedDescription = "When a receipt has items that have been categorized into different applications (for example PersonalExpense and TravelExpense), the receipt is automatically classified as a 'Mixed' expense receipt.";
                            jQuery('#app-cat-mx strong').tooltip({title: mixedDescription});
                        } else {
                            jQuery('#app-cat-mx strong').tooltip({title: $filter('categoryAppName')($scope.receipt.App)});
                        }

                        if ($scope.receipt.PaymentType) {
                            $scope.receipt.PaymentType = parseInt($scope.receipt.PaymentType);
                        } else {
                            $scope.receipt.PaymentType = 4;
                        }

                        if ($scope.receipt.CurrencyCode != $rootScope.loggedInUser.CurrencyCode) {
                            $scope.isResetCurrency = false;
                            $scope.hideAfterReset = false;
                            $scope.convertedCurrency = true;
                        }

                      if (typeof response.ReceiptImage.FilePath !== 'undefined') {
                        $scope.showPdfViewer = true;
                        $scope.receipt.ReceiptImage.FilePath = response.ReceiptImage.FilePath;

                        /*
                         * Set path to display PDF
                         * */
                        if (!$('#info-pdf').hasClass("hide")) {
                          $('#info-pdf').addClass("hide");
                        }
                        if ($scope.receipt.ReceiptImage.FilePath) {
                          var loadPdfEvent = function (pdfFile) {
                            $scope.loadPdfUrl(pdfFile.Body);
                          }

                          AwsS3Sdk.getReceiptPdf({
                              bucket  : $scope.receipt.ReceiptImage.FileBucket,
                              keyName : $scope.receipt.ReceiptImage.FilePath,
                              successCallback : loadPdfEvent
                          });
                        }

                        //show div tooltip for pdf box
                        $scope.firstShowPDF = true;
                        $('.loading-pdf-receipt').show();
                        $('#pdfContainer').bind("DOMSubtreeModified", function () {
                          if (!($('#pdfContainer').is(':empty'))) {
                            setTimeout(function () {
                              $('.loading-pdf-receipt').hide();
                              if ($scope.firstShowPDF) {
                                $(".tutocrpdf").show().css('opacity', '0.9');
                              }
                            }, 200);
                          }
                        });
                      } else {
                        delete $scope.receipt.ReceiptImage.FilePath;
                      }
                        // Comment by KhanhDN on 2013-10-13: This function should run in API server side
                        $scope.receipt.DeletedItems = [];
                        $scope.receipt.OriginalCurrency = $scope.receipt.CurrencyCode;
                        $scope.receipt.Items = [];

                        // Show currency converter if home currency is not equal receipt currency
                        if ($rootScope.loggedInUser.CurrencyCode != $scope.receipt.CurrencyCode && !$scope.receipt.ExchangeRate) {
                            $scope.showConfirmConvertMessage = true;
                        }
                        if (tmpItems.length > itemPiece + itemPieceStep) {
                            var rows = tmpItems.slice(itemPiece, itemPieceStep);

                            $scope.receipt.Items = $scope.receipt.Items.concat(rows);
                            // Set interval to 500ms to render receipt items in queue
                            var timerLimitId = setInterval(function () {
                                itemPiece += itemPieceStep;
                                var tmpRows = tmpItems.slice(itemPiece, itemPiece + itemPieceStep);
                                if (tmpRows.length) {
                                    $scope.receipt.Items = $scope.receipt.Items.concat(tmpRows);
                                  if(!$scope.$$phase) $scope.$apply();
                                }

                                if (tmpRows.length < itemPieceStep) {
                                    itemPiece = 0;
                                    clearInterval(timerLimitId);
                                    $scope.itemTotal = $scope.getItemSubTotal();
                                    if (!$scope.receipt.Items.length) {
                                        $scope.addMoreItem();
                                    }
                                    $scope.autoPopulateCategory(categoryInfo, tripInfo);

                                    // ++ @see http://113.160.50.82/dev/issues/31478
                                    if ($scope.receipt.DigitalTotal > 0 && 0 == $scope.receipt.Items[0].IsJoined && 0 == $scope.receipt.HasCombinedItem && (verifyStatus == 0 || !hasItemCategorized)) {
                                        $scope.receipt.ExpensePeriod = $scope.receipt.Items[0].ExpensePeriod;
                                        $scope.receipt.forceCategory = true;
                                        $scope.categorizeReceipt('individual_item');
                                        $scope.receipt.categorizeMethod = 'individual_item';
                                        if (categoryInfo) {
                                            //console.debug('more items - set $scope.receipt.CategoryAppAbbr = %s', categoryInfo.appAbbr);
                                            $scope.receipt.CategoryApp = categoryInfo.app;
                                            $scope.receipt.CategoryAppAbbr = categoryInfo.appAbbr;
                                            $scope.receipt.Items[0].CategoryApp = categoryInfo.app;
                                            $scope.performSelectCat({Name: categoryInfo.categoryName, CategoryID: categoryInfo.categoryID}, $scope.receipt.Items[0], 0);
                                            $scope.setExpensePeriodForAllItems(categoryInfo.app);
                                        } else if ($scope.initializedApp) {
                                            $scope.setExpensePeriodForAllItems($scope.initializedApp);
                                        } else if ($rootScope.defaultApp) {
                                            $scope.setExpensePeriodForAllItems($rootScope.defaultApp);
                                        }
                                    } else if ($scope.receipt.DigitalTotal <= 0 && 0 == $scope.receipt.Items[0].IsJoined && 0 == $scope.receipt.HasCombinedItem) {
                                        $scope.categorizeReceipt('individual_item');
                                        $scope.receipt.categorizeMethod = 'individual_item';
                                    }else{
                                      if (categoryInfo) {
                                        $scope.receipt.CategoryApp = categoryInfo.app;
                                        $scope.receipt.CategoryAppAbbr = categoryInfo.appAbbr;
                                        $scope.receipt.CategoryName = categoryInfo.categoryName;
                                      }else{
                                        $scope.receipt.CategoryApp = $rootScope.defaultApp;
                                        $scope.receipt.CategoryAppAbbr = $rootScope.defaultAppAbbr;
                                      }
                                    }
                                  if(!$scope.$$phase) $scope.$apply();
                                }
                            }, 500);
                            intervalIdArr.push(timerLimitId);
                        } else {
                            $scope.receipt.Items = tmpItems;
                            $scope.itemTotal = $scope.getItemSubTotal();
                            $scope.autoPopulateCategory(categoryInfo, tripInfo);
                            if (!$scope.receipt.Items.length) {
                                $scope.addMoreItem();
                            }
                            // @see http://113.160.50.82/dev/issues/31478
                            if ($scope.receipt.DigitalTotal > 0 && 0 == $scope.receipt.Items[0].IsJoined && 0 == $scope.receipt.HasCombinedItem && (verifyStatus == 0 || !hasItemCategorized)) {
//                                $scope.categorizeReceipt('one_item');
//                                $scope.receipt.categorizeMethod = 'one_item';
                                $scope.categorizeReceipt('individual_item');
                                $scope.receipt.categorizeMethod = 'individual_item';
                                $scope.receipt.ExpensePeriod = $scope.receipt.Items[0].ExpensePeriod;
                                $scope.receipt.forceCategory = true;

                                if (categoryInfo) {
                                    $scope.receipt.CategoryApp = categoryInfo.app;
                                    $scope.receipt.CategoryAppAbbr = categoryInfo.appAbbr;
                                    //console.debug('less items - set $scope.receipt.CategoryAppAbbr = %s', categoryInfo.appAbbr);
                                    $scope.receipt.Items[0].CategoryApp = categoryInfo.app;
                                    $scope.performSelectCat({Name: categoryInfo.categoryName, CategoryID: categoryInfo.categoryID}, $scope.receipt.Items[0], 0);
                                    $scope.setExpensePeriodForAllItems(categoryInfo.app);
                                } else if ($scope.initializedApp) {
                                    $scope.setExpensePeriodForAllItems($scope.initializedApp);
                                } else if ($rootScope.defaultApp) {
                                    $scope.setExpensePeriodForAllItems($rootScope.defaultApp);
                                }

                                //$scope.setExpensePeriodForAllItems($scope.initializedApp);

                                jQuery('#categorize-section .app-col .btn-group').tooltip('destroy');
                            } else if ($scope.receipt.DigitalTotal <= 0 && 0 == $scope.receipt.Items[0].IsJoined && 0 == $scope.receipt.HasCombinedItem) {
                                $scope.categorizeReceipt('individual_item');
                                $scope.receipt.categorizeMethod = 'individual_item';
                            }else{
                              if($scope.openRDFrom == 'receiptbox-wrapper'){
                                $scope.receipt.CategoryApp = $rootScope.defaultApp;
                                $scope.receipt.CategoryAppAbbr = $rootScope.defaultAppAbbr;
                              }else{
                                $scope.receipt.CategoryApp = categoryInfo.app;
                                $scope.receipt.CategoryAppAbbr = categoryInfo.appAbbr;
                                $scope.receipt.CategoryName = categoryInfo.categoryName;
                              }

                            }

                            // @see http://113.160.50.82/dev/issues/29270
                            /*
                             if ($scope.receipt.Items.length == 1 && !$scope.receipt.Items[0].Name && categoryInfo) {
                             $scope.categorizeReceipt('one_item');
                             $scope.autoPopulateCategory(categoryInfo, tripInfo, true);
                             jQuery('#categorize-section .app-col .btn-group').tooltip('destroy');
                             $scope.receipt.categorizeMethod = 'one_item';
                             }
                             */
                        }

                        if ($scope.receipt.VerifyStatus == 0) {
                            $scope.receipt.VerifyStatus = 1;
                        }

                        var updatedData = {IsOpened: $scope.receipt.IsOpened, VerifyStatus: $scope.receipt.VerifyStatus};
                        indexDbServer.userReceipts.query('ReceiptID').only($scope.receipt.ReceiptID).modify(updatedData).execute().done(function (data) {
                        });

                        $scope.receipt.convertCurrencyHistory = [];
                        $scope.receipt.PrevPurchaseTime = $scope.receipt.PurchaseTime;
                        $scope.receipt.ExtraField = $scope.receipt.ExtraField ? $scope.receipt.ExtraField : 'Tip';
                        $scope.receipt.OriginalVerifyStatus = angular.copy($scope.receipt.VerifyStatus);
                        $scope.receipt.IsRecentlyConverted = false;

                        if ($scope.receipt.CurrencyCode != $rootScope.loggedInUser.CurrencyCode) {
                            $scope.receipt.convertCurrencyHistory = [$scope.receipt.CurrencyCode];
                        }

                        // Don't show item total error if user combined item
                        if ($scope.receipt.HasCombinedItem == 1) {
                            // Populate category data
                            $scope.receipt.CategoryName = $scope.receipt.Items[0].CategoryName;
                            $scope.receipt.CategoryID = $scope.receipt.Items[0].CategoryID;
                        }

                        if ($scope.receipt.IsReported) {
                            $scope.responseMessage = ['This receipt is submitted. You can not modify or delete item'];
                        }
                        $scope.tmpCategoryMethod = $scope.receipt.categorizeMethod;
                        $scope.TmpCurrencyCode = $scope.receipt.CurrencyCode;
                        if(angular.isDefined(categoryInfo)){
                            if($scope.initializedApp == 'personal_expense' || $scope.initializedApp == 'business_expense'
                                    || $scope.initializedApp == 'education_expense' || $scope.initializedApp == 'personal_assets'
                                    || $scope.initializedApp == 'business_assets'){
                                var currentMount = (new Date()).getMonth();
                                var categoryInfoDate = categoryInfo.date.substring(5,7);
                                var purchaseDate = categoryInfo.date;
                                //If category info return month match current month. Set purchaseTime to current date time.
                                if(parseInt(currentMount) == parseInt(categoryInfoDate)){
                                    var purchaseDate = categoryInfo.date.substring(0,7);
                                }
                                //$scope.receipt.PurchaseTime = new timezoneJS.Date(new Date(purchaseDate), $rootScope.loggedInUser.Timezone).toISOString();
                                angular.forEach($scope.receipt.Items, function (v, k) {
                                   v.ExpensePeriod =  new timezoneJS.Date(new Date(purchaseDate), $rootScope.loggedInUser.Timezone).toISOString();
                                });
                            }
                        }
                      if($scope.isManualReceipt){
                        $scope.lblReceiptType = 'Manual Receipt';
                      }
                    }, function (response) {
                        if (response.status !== 200) {
                            $scope.responseMessage = response.data.message;
                        }
                    });
                } else {
                  $timeout(function(){
                    $scope.receipt = {};
                    $scope.receipt.ReceiptID = 0;
                    $scope.receipt.Attachments = [];
                    $scope.receipt.ReceiptType = 4;
                    $scope.receipt.PaymentType = 4;
                    //$scope.receipt.CurrencyCode = 'USD';
                    $scope.receipt.CurrencyCode = $rootScope.loggedInUser.CurrencyCode;
                    $scope.TmpCurrencyCode = $scope.receipt.CurrencyCode;
                    $scope.receipt.Items = $scope.setDefaultReceiptItem(3, categoryInfo, tripInfo, verifyStatus == 0 ? true : false);
                    $scope.receipt.DeletedItems = [];
                    $scope.receipt.DeletedFileIDs = [];
                    $scope.receipt.ItemCount = 0;
                    $scope.receipt.OriginalCurrency = $scope.receipt.CurrencyCode = $rootScope.loggedInUser.CurrencyCode;
                    $scope.receipt.ImagePath = '';
                    $scope.receipt.Memo = '';
                    $scope.receipt.EmailSender = null;
                    $scope.receipt.RebateAmount = null;
                    $scope.receipt.App = angular.isDefined(categoryInfo) ? categoryInfo.appAbbr : undefined;
                    $scope.receipt.CategoryApp = $rootScope.defaultApp;
                    $scope.receipt.CategoryAppAbbr = $rootScope.defaultAppAbbr;
                    $scope.receipt.CurrencyConverted = 0;
                    $scope.receipt.convertCurrencyHistory = [];
                    $scope.receipt.ExtraField = 'Tip';
                    $scope.receipt.IsRecentlyConverted = false;
                    $scope.receipt.VerifyStatus = 1;

                    try {
                      $scope.receipt.PurchaseTime = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone).toISOString();
                    } catch (err) {
                      $scope.receipt.PurchaseTime = new Date().toISOString();
                    }

                    $scope.receipt.PrevPurchaseTime = angular.copy($scope.receipt.PurchaseTime);

                    //$scope.ignoreWarning = true;
                    $scope.itemTotal = null;

                    if(angular.isDefined(categoryInfo)){
                        if($scope.initializedApp == 'personal_expense' || $scope.initializedApp == 'business_expense'
                                || $scope.initializedApp == 'education_expense' || $scope.initializedApp == 'personal_assets'
                                || $scope.initializedApp == 'business_assets'){
                            var currentMount = (new Date()).getMonth();
                            var categoryInfoDate = categoryInfo.date.substring(5,7);
                            var purchaseDate = categoryInfo.date;
                            //If category info return month match current month. Set purchaseTime to current date time.
                            if(parseInt(currentMount) == parseInt(categoryInfoDate)){
                                var purchaseDate = categoryInfo.date.substring(0,7);
                            }
                            $scope.receipt.PurchaseTime = new timezoneJS.Date(new Date(purchaseDate), $rootScope.loggedInUser.Timezone).toISOString();
                            angular.forEach($scope.receipt.Items, function (v, k) {
                               v.ExpensePeriod =  new timezoneJS.Date(new Date(purchaseDate), $rootScope.loggedInUser.Timezone).toISOString();
                            });
                        }
                    }
                  });
                }

                setTimeout(function () {
                    jQuery('#rd-items-table .btn-group').tooltip();
                    alignAppCatDropdown();
                }, 2000);

                if ($scope.initializedApp == 'personal_expense') {
                    var sc = '<script>' +
                        '$("#close-with-tooltip").tooltip("destroy");' +
                        '$("#close-with-tooltip").tooltip({' +
                        'placement: "bottom",' +
                        'title: "Back to PersonalExpense",' +
                        'animation: true' +
                        '});' +
                        '</script>';
                    $('#close-with-tooltip').empty().append(sc);
                } else if ($scope.initializedApp == 'education_expense') {
                    var sc = '<script>' +
                        '$("#close-with-tooltip").tooltip("destroy");' +
                        '$("#close-with-tooltip").tooltip({' +
                        'placement: "bottom",' +
                        'title: "Back to EducationExpense",' +
                        'animation: true' +
                        '});' +
                        '</script>';
                    $('#close-with-tooltip').empty().append(sc);
                } else if ($scope.initializedApp == 'business_expense') {
                    var sc = '<script>' +
                        '$("#close-with-tooltip").tooltip("destroy");' +
                        '$("#close-with-tooltip").tooltip({' +
                        'placement: "bottom",' +
                        'title: "Back to BusinessExpense",' +
                        'animation: true' +
                        '});' +
                        '</script>';
                    $('#close-with-tooltip').empty().append(sc);
                } else if ($scope.initializedApp == 'personal_assets') {
                    var sc = '<script>' +
                        '$("#close-with-tooltip").tooltip("destroy");' +
                        '$("#close-with-tooltip").tooltip({' +
                        'placement: "bottom",' +
                        'title: "Back to PersonalAssets",' +
                        'animation: true' +
                        '});' +
                        '</script>';
                    $('#close-with-tooltip').empty().append(sc);
                } else if ($scope.initializedApp == 'business_assets') {
                    var sc = '<script>' +
                        '$("#close-with-tooltip").tooltip("destroy");' +
                        '$("#close-with-tooltip").tooltip({' +
                        'placement: "bottom",' +
                        'title: "Back to BusinessAssets",' +
                        'animation: true' +
                        '});' +
                        '</script>';
                    $('#close-with-tooltip').empty().append(sc);
                } else if ($scope.initializedApp == 'travel_expense') {
                    var sc = '<script>' +
                        '$("#close-with-tooltip").tooltip("destroy");' +
                        '$("#close-with-tooltip").tooltip({' +
                        'placement: "bottom",' +
                        'title: "Back to Trip",' +
                        'animation: true' +
                        '});' +
                        '</script>';
                    $('#close-with-tooltip').empty().append(sc);
                } else {
                    var sc = '<script>' +
                        '$("#close-with-tooltip").tooltip("destroy");' +
                        '$("#close-with-tooltip").tooltip({' +
                        'placement: "bottom",' +
                        'title: "Back to ReceiptBox",' +
                        'animation: true' +
                        '});' +
                        '</script>';
                    $('#close-with-tooltip').empty().append(sc);
                }
                $scope.resetUploadQueue();
                $rootScope.createRDAdBlock();
                $scope.lblReceiptType = "";
            }

            /**
             * Function to populate category and trip info to item automatically
             *
             * @param   object    item             Item line of receipts
             * @param   object    categoryInfo     Category data
             * @param   object    tripInfo         Trip data
             */
            $scope.populateCatToItem = function(item, categoryInfo, tripInfo) {
                item.CategoryID = parseInt(item.CategoryID);
                if (angular.isDefined(categoryInfo)) {
                    item.CategoryID = categoryInfo.categoryID;
                    //$scope.userChangedContent = true;
                    item.CategoryApp = categoryInfo.app;
                    item.CategoryAppAbbr = categoryInfo.appAbbr;
                } else {
                    if(!angular.isDefined(item.CategoryApp)) {
                        item.CategoryApp = $rootScope.defaultApp;
                        item.CategoryAppAbbr = $rootScope.defaultAppAbbr;
                    }
                    return;
                }
                if (item.CategoryID && !item.CategorizeStatus) {
                    item.CategorizeStatus = 2;
                }
                item.CategoryName = categoryInfo.categoryName;
                if (angular.isDefined(tripInfo) && !$scope.autoDisabled) {
                    item.TripID = tripInfo.tripID;
                    item.forceCategory = true;
                    item.Reference = tripInfo.reference;
                }
            };

            /**
             * Function to populate category and trip info to ALL items of receipt automatically
             * Add forcePopulate to allow function run even item has no ItemID property
             *
             * @param   object    categoryInfo     Category data
             * @param   object    tripInfo         Trip data
             * @param   boolean   forcePopulate    Item line of receipts
             */
            $scope.autoPopulateCategory = function (categoryInfo, tripInfo, forcePopulate) {
                angular.forEach($scope.receipt.Items, function (item, k) {
                    if (item.ItemID || forcePopulate) {
                        $scope.populateCatToItem(item, categoryInfo, tripInfo)
                    }
                });
            }
            $scope.printImage = function () {
                $('#receipt-image-wrapper').find('img').receiptPrint();
            };
            $scope.preValidateReceipt = function () {
                $scope.responseMessage = [];

                if (!$scope.receipt.PurchaseTime) {
                    $scope.responseMessage.push('Purchase Date is required field.');
                    $scope.responseCode = 500;
                    return false;
                }

                if (angular.isUndefined($scope.receipt.MerchantName) || $scope.receipt.MerchantName == '') {
                    $scope.responseMessage.push('Merchant Name is required field.');
                    $scope.responseCode = 500;
                    return false;
                }

                return true;
            }

            /**
             * Function to show confirmation box before saving and
             * perform yes/no callback functions
             */
            $scope.confirmToSave = function(yesCallBack, noCallBack) {
                $.showMessageBox({
                    content: 'Do you want to save your changes to this receipt?',
                    boxTitle: 'SAVE RECEIPT',
                    boxTitleClass: '',
                    labelYes: 'Save',
                    labelNo: "Don't Save",
                    type: 'full-confirm',
                    onYesAction: function () {
                        if (!$scope.preValidateReceipt()) {
                            return false;
                        }

                        if ($scope.requireUserConvertCurrency) {
                            $.showMessageBox({
                                content: 'Your currency conversion is not finished yet. Please convert currency before saving this receipt.',
                                boxTitle: 'CURRENCY CONVERSION',
                                boxTitleClass: '',
                                type: 'alert'
                            });

                            return false;
                        }

                        if (typeof yesCallBack != 'undefined') yesCallBack();
                    },
                    onNoAction: function () {
                        if (typeof noCallBack != 'undefined') noCallBack();
                    }
                });

                $('.btn-confirm-no').css({
                    marginRight: 5,
                    width: 135
                });
            };

            /**
             * Process when click Close button
             */
            $scope.close = function (openUploadDialog) {
                if ($scope.userChangedContent) {
                    //Use confirmation box to ask before save
                    $scope.confirmToSave(
                        function yesAction () {
                            $timeout(function () {
                                $scope.userChangedContent = false;
                                $scope.save($scope.receipt, false);
                                $scope.close(openUploadDialog);

                                if ($scope.initializedApp) {
                                    var menu = 'menu-personal-expense';
                                    if ($scope.initializedApp == 'travel_expense') {
                                        menu = 'menu-travel-expense';
                                    }
                                    if ($scope.initializedApp == 'education_expense') {
                                        menu = 'menu-education-expense';
                                    }
                                    if ($scope.initializedApp == 'business_expense') {
                                        menu = 'menu-business-expense';
                                    }
                                    if ($scope.initializedApp == 'personal_assets') {
                                        menu = 'menu-personal-assets';
                                    }
                                    if ($scope.initializedApp == 'business_assets') {
                                        menu = 'menu-business-assets';
                                    }
                                    $scope.$emit('CLOSE_RB_ADD_ITEMS', menu);
                                }
                            });
                        },
                        function noAction () {
                            $scope.userChangedContent = false;
                            $scope.close(openUploadDialog);

                            if (angular.isDefined($scope.initializedApp)) {
                                var menu = 'menu-personal-expense';
                                if ($scope.initializedApp == 'travel_expense') {
                                    menu = 'menu-travel-expense';
                                }
                                if ($scope.initializedApp == 'education_expense') {
                                    menu = 'menu-education-expense';
                                }
                                if ($scope.initializedApp == 'business_expense') {
                                    menu = 'menu-business-expense';
                                }
                                if ($scope.initializedApp == 'personal_assets') {
                                    menu = 'menu-personal-assets';
                                }
                                if ($scope.initializedApp == 'business_assets') {
                                    menu = 'menu-business-assets';
                                }
                                $scope.$emit('CLOSE_RB_ADD_ITEMS', menu);
                            }
                        }
                    );
                } else {
                    window.onbeforeunload = null;
                    delete $scope.receipt;
                    delete $scope.OriginalAmount;
                    delete $scope.oneItemClicked;
                    delete $scope.tmpAmount;
                    delete $scope.isResetCurrency;
                    delete $scope.initializedApp;

                    $scope.itemIsCheckAll = false;
                    $scope.responseMessage = [];
                    $('#categorize-section .selected_item_row .cat-col .btn-group').tooltip('hide');
                    jQuery('.page-app').css('display', 'none');
                    jQuery('#top-header').removeClass('hide').addClass('show');
                    jQuery('#sidebar-right').removeClass('hide').addClass('show');
                    jQuery('#' + $scope.openRDFrom).css('display', 'block');

                    if ($scope.menuOpenRDFrom != 'menu-receiptbox') {
                        $('#' + $scope.menuOpenRDFrom).addClass('aqua');
                        $('#menu-receiptbox').removeClass('green');
                    } else {
                        $('.aqua').removeClass('aqua');
                        $('#menu-receiptbox').addClass('green');
                    }
                    jQuery('.icon-fx').removeClass('red');
                    //Always close the datepicker when Receipt Detail screen is closed
                    jQuery('#purchase_date').datepicker('hide');
                    $scope.hideAfterReset = false;
                }
                return false;
            }

            /**
             * Event on change of merchant auto complete select
             */
            $scope.$on('RD_CHANGE_MERCHANT', function(e, merchant){
                $scope.receipt.MerchantID           = merchant.MerchantID;
                $scope.receipt.MerchantName         = merchant.Name;
                $scope.receipt.MerchantLogo         = merchant.Logo;
                $scope.receipt.MerchantAddress      = merchant.Address || null;
            })

            /*
             * Load merchant logo on merchant name change
             */
            $scope.reloadMerchantLogo = function(){
                if (!angular.isDefined($rootScope.merchantAC))
                    return false;

                if ($scope.receipt.MerchantAddress == '')
                    $scope.receipt.MerchantAddress = null;

                var merchant;
                for (var i = 0; i < $rootScope.merchantAC.length; i++) {
                    merchant = $rootScope.merchantAC[i];
                    if (merchant.Name == $scope.receipt.MerchantName && merchant.Address == $scope.receipt.MerchantAddress){
                        $scope.$emit('RD_CHANGE_MERCHANT', merchant);
                        break;
                    }
                }
            }

          $scope.getReceiptById = function(recieptID, callBack){
            Restangular.one('receipts').get({receiptID: recieptID}).then(function (response) {
              if(response){
                if(typeof callBack != 'undefined') callBack(response);
              }
            });
          }



            /**
             * Submit the receipt to server
             *
             * @param object receipt
             * @param confirmVerification   show confirmation box
             * @param closeAfterSave        Close the RD screen after saving complete
             * @param callBackAfterSave     Function to call after saving complete
             */
            $scope.save = function (receipt, confirmVerification, closeAfterSave, callBackAfterSave) {
                if (typeof confirmVerification == 'undefined') confirmVerification = true;
                if (typeof closeAfterSave == "undefined") closeAfterSave = false;
                closeAfterSave = false;
                $scope.receiptInSaving = true;
                $scope.txtLoading      = 'Saving...';
                receipt.MerchantCountry = $( "#MerchantCountryCode" ).val();

                if (confirmVerification && $scope.requireUserConvertCurrency) {
                    $.showMessageBox({
                        content: 'You have entered a foreign currency transaction. You must convert this amount to your home currency ' + $rootScope.loggedInUser.CurrencyCode + ' before saving.',
                        boxTitle: 'Currency Conversion',
                        boxTitleClass: '',
                        type: 'alert'
                    });
                  $scope.receiptInSaving = false;
                    return false;
                }

                if (confirmVerification && typeof (receipt.MerchantName) == 'undefined') {
                        $.showMessageBox({
                            content: 'Please specify Merchant name.',
                            type: 'alert'
                        });
                  $scope.receiptInSaving = false;
                        return false;
                }

                //Only update receipt currency code if currency convert is already processed
                if (!$scope.requireUserConvertCurrency) {
                    $scope.receipt.CurrencyCode = angular.copy($scope.TmpCurrencyCode);
                }

                $scope.userChangedContent = false;

                // Truncate merchant address if its length is larger than 45 characters
                if (receipt.MerchantAddress && receipt.MerchantAddress.length > 45) {
                    receipt.MerchantAddress = receipt.MerchantAddress.substr(0, 45);
                }

                //Remove expense period of new items
                angular.forEach(receipt.Items, function (item, ik) {
                    if (item.Name.length > 0 && !(item.CategoryID > 0)) {
                        item.ExpensePeriod = '';
                    }

                    // remove incorrect expense period (format like "1969-12-01T00:00:00"
                    if (item.ExpensePeriod && !angular.isObject(item.ExpensePeriod)) {
                        if (item.ExpensePeriod.indexOf('1969') != -1 || item.ExpensePeriod.indexOf('1970') != -1) {
                            item.ExpensePeriod = '';
                        }
                    }
                });

                angular.forEach(receipt.Items, function(v, k){
                  if(!v.Amount && !v.Name){
                    receipt.Items.splice(k, 2);
                  }
                });

                // In update case
              if (receipt.ReceiptID) {
                $scope.isSaved = true;
                $scope.responseCode = 200;
                $scope.responseMessage = ['Data saved.'];
                var tmpVerifyStatus = $scope.receipt.VerifyStartus;
                Restangular.one('receipts').customPUT(receipt, '').then(function (response) {
                  if($scope.isDeleteImageMR){
                    Restangular.all('attachments').customDELETE('manual-image', {receiptID: receipt.ReceiptID});
                  }
                  // New attributes for Receipt: App, Cate, Period
                  var receiptApp = null, receiptCategory = null, receiptPeriod = null;
                  // remove incorrect expense period (format like "1969"
                  if (receipt.ExpensePeriod) {
                    if (receipt.ExpensePeriod.indexOf('1969') != -1 || receipt.ExpensePeriod.indexOf('1970') != -1) {
                      receipt.ExpensePeriod = '';
                    }
                  }

                  var originalReceiptPeriod = receipt.ExpensePeriod;
                  receipt.Items = response.Items;

                  receipt.ReceiptData = response.ReceiptData;
                  receipt.ReceiptItemData = response.ReceiptItemData;

                  if (response.VerifyStatusChanged && receipt.VerifyStatus == 2) {
                    $scope.reAssignTmpAmount = true;
                  }

                  angular.forEach(receipt.Items, function (item, ik) {
                    if (item.ExpensePeriod) {
                      if (item.ExpensePeriod.indexOf('1969') != -1 || item.ExpensePeriod.indexOf('1970') != -1) {
                        item.ExpensePeriod = '';
                      }
                    }

                    if (angular.isObject(item.Tags)) {
                      item.Tags = item.Tags.join(',');
                    }
                  });


                  if (receipt.Items.length && receipt.Items[0].Name) {
                    angular.forEach(receipt.Items, function (v, k) {
                      if (!k) {
                        receiptApp = v.CategoryAppAbbr;
                        receiptCategory = v.CategoryName;
                        receiptPeriod = v.ExpensePeriod;

                        // Format receipt period for easy comparison
                        if (((receiptApp == 'PE') || (receiptApp == 'BE') || (receiptApp == 'EE')) || (receiptApp == 'PA') || (receiptApp == 'BA') && receiptPeriod) {
                          receiptPeriod = receiptPeriod.substr(0, 8) + '01';
                        } else if (receiptApp == 'TE') {
                          receiptPeriod = v.Reference;
                        }
                      }

                      if (v.Name == '') {
                        return false;
                      }

                      // Items within receipts belong to different apps, the receipt will get an attribute of “MX” (short for “Mixed”)
                      // under the app column.
                      if (receiptApp != v.CategoryAppAbbr) {
                        receiptApp = 'MX';
                      }

                      if (receiptApp == 'MX') { // If app is MX, all category and period should be MX too
                        receiptCategory = 'Mixed';
                        receiptPeriod = 'MX';
                      } else {
                        if (receiptCategory != v.CategoryName) {
                          receiptCategory = 'Mixed';
                        }

                        var itemPeriod;
                        if (((v.CategoryAppAbbr == 'PE') || (v.CategoryAppAbbr == 'BE') || (v.CategoryAppAbbr == 'EE')) || (v.CategoryAppAbbr == 'PA') || (v.CategoryAppAbbr == 'BA') && v.ExpensePeriod) {
                          itemPeriod = v.ExpensePeriod.substr(0, 8) + '01';
                        } else if (v.CategoryAppAbbr == 'TE') {
                          itemPeriod = v.Reference;
                        }

                        if (itemPeriod != receiptPeriod) {
                          receiptPeriod = 'MX';
                        }
                      }

                      //Set default app by the last item which was categorized
                      if (v.CategoryApp && v.CategoryAppAbbr && v.CategoryID && v.ExpensePeriod) {
                        $rootScope.defaultApp = v.CategoryApp;
                        $rootScope.defaultAppAbbr = v.CategoryAppAbbr;
                      }
                    });

                    receipt.App = receiptApp;
                    receipt.Category = receiptCategory;
                    receipt.ExpensePeriod = receiptPeriod;

                    // Combined item doesn't has mixed item
                    if (receipt.HasCombinedItem == 1) {
                      receipt.App = receipt.Items[0].CategoryAppAbbr;
                      receipt.Category = receipt.Items[0].CategoryName;
                      receipt.ExpensePeriod = receipt.Items[0].ExpensePeriod;

                      if (receipt.App == 'TE') {
                        receipt.ExpensePeriod = receipt.Items[0].Reference;
                      }
                    }

                    if (((receiptApp == 'PE') || (receiptApp == 'BE') || (receiptApp == 'EE')) || (receiptApp == 'PA') || (receiptApp == 'BA') && receipt.ExpensePeriod != 'MX') {
                      receipt.ExpensePeriod = $filter('formatDate')(receipt.ExpensePeriod, 'MMM-yyyy');
                    }
                  }

                  // Collapse the receipt if it is verified
                  if (receipt.VerifyStatus == 2) {
                    receipt.IsCollapsed = 1;
                  } else {
                    receipt.IsCollapsed = 0;
                  }
                  // Notify to listener to update local storage

                  $rootScope.$broadcast('RELOAD_RECEIPT_LIST', false, angular.copy(receipt));
                  //$rootScope.$broadcast('RELOAD_RECEIPT_LIST', false, receipt);
                  //$rootScope.$broadcast('UPDATE_PE_LOCAL_STORAGE');

                  $scope.$emit('RELOAD_PE_LIST', true);
                  $scope.$emit('RELOAD_BE_LIST', true);
                  $scope.$emit('RELOAD_EE_LIST', true);
                  $scope.$emit('RELOAD_PA_LIST', true);
                  $scope.$emit('RELOAD_BA_LIST', true);

                  //Check whether repsonse data indicates that we need to refresh the trip list. If so, do it.
                  if (response.RefreshTripList) {
                    $scope.$emit('LOAD_TRIP_LIST');

                    if (angular.isDefined($rootScope.filterReportType) && angular.isDefined($rootScope.filterReportFrom) && angular.isDefined($rootScope.filterReportTo)) {
                      $scope.$emit('LOAD_REPORT_LIST');
                    }
                  }

                  if (parseInt(response.SetDefaultApp) >= 0) {
                    $rootScope.defaultApp = receipt.Items[response.SetDefaultApp].CategoryApp;
                    $rootScope.defaultAppAbbr = receipt.Items[response.SetDefaultApp].CategoryAppAbbr;
                  }

                  if (angular.isDefined($scope.tripInfo)) {
                    $scope.$emit('LOAD_TRIP_DETAIL', $scope.tripInfo.tripID, $scope.tripInfo.tripType, $scope.tripInfo.dateFrom, $scope.tripInfo.dateTo, false);
                  }

                  jQuery('#app-cat-mx strong').tooltip('destroy');
                  if (receipt.App == 'MX') {
                    var mixedDescription = "When a receipt has items that have been categorized into different applications (for example PersonalExpense and TravelExpense), the receipt is automatically classified as a 'Mixed' expense receipt.";
                    jQuery('#app-cat-mx strong').tooltip({title: mixedDescription});
                  } else {
                    jQuery('#app-cat-mx strong').tooltip({title: $filter('categoryAppName')(receipt.App)});
                  }

                  // Restore receipt original expense period
                  receipt.ExpensePeriod = originalReceiptPeriod;

                  //Mark saving flag as done to enable availability of other receipt functions
                  $scope.receiptInSaving = false;

                  //Get Merchant Auto complete widget up-to-date
                  $rootScope.reloadMerchantAC('#merchant-name-info-1');

                  //callBack function after saving complete
                  if (typeof callBackAfterSave != 'undefined') callBackAfterSave();
                }, function (response) {
                  if (tmpVerifyStatus) {
                    $scope.receipt.VerifyStatus = tmpVerifyStatus;
                  }

                  $scope.responseMessage = response.data.message;
                  $scope.responseCode = response.status;
                  $scope.receiptInSaving = false;
                });
              } else {
                $timeout(function () {
                  Restangular.all('receipts').post(receipt).then(function (response) {
                    if (typeof closeAfterSave != "undefined" && closeAfterSave) {
                      $scope.close();
                    } else {
                      //Manual update id of new created receipt
                      $timeout(function () {
                        $scope.receipt.ReceiptID = response.ReceiptID;
                        $scope.getReceiptById(response.ReceiptID, function (data) {
                          $scope.receipt.Items = [];
                          angular.forEach(data.Items, function (v, k) {
                            $scope.receipt.Items.push(v);
                          });
                          $scope.receiptInSaving = false;
                        });
                      });
                    }

                    $rootScope.$broadcast('RELOAD_RECEIPT_LIST', true, receipt);

                    $scope.$emit('RELOAD_PE_LIST', true);
                    $scope.$emit('RELOAD_BE_LIST', true);
                    $scope.$emit('RELOAD_EE_LIST', true);
                    $scope.$emit('RELOAD_PA_LIST', true);
                    $scope.$emit('RELOAD_BA_LIST', true);

                    //Check whether repsonse data indicates that we need to refresh the trip list. If so, do it.
                    if (response.RefreshTripList) {
                      $scope.$emit('LOAD_TRIP_LIST');

                      if (angular.isDefined($rootScope.filterReportType) && angular.isDefined($rootScope.filterReportFrom) && angular.isDefined($rootScope.filterReportTo)) {
                        $scope.$emit('LOAD_REPORT_LIST');
                      }
                    }

                    if (angular.isDefined($scope.tripInfo)) {
                      $scope.$emit('LOAD_TRIP_DETAIL', $scope.tripInfo.tripID, $scope.tripInfo.tripType, $scope.tripInfo.dateFrom, $scope.tripInfo.dateTo, false);
                    }

                    //Mark saving flag as done to enable availability of other receipt functions
                    $scope.receiptInSaving = false;

                    //Get Merchant Auto complete widget up-to-date
                    $rootScope.reloadMerchantAC('#merchant-name-info-1');

                    /**
                     * Reload receipt list.
                     */
                    $scope.$emit('FILTER_RECEIPT_LIST', true);

                    $timeout(function () {
                      //callBack function after saving complete
                      if (typeof callBackAfterSave != 'undefined') callBackAfterSave(receipt.Items);
                    });

                  }, function (response) {
                    $scope.responseMessage = response.data.message;
                  });
                });

              }
                $("#popup-memo").val($scope.receipt.Memo);
            }

            /**
             * Add empty item to receipt items
             */
            $scope.addMoreItem = function () {
                if ($scope.receipt.categorizeMethod != 'one_item') {
                    $scope.receipt.Items.push({
                        "ItemID": 0,
                        "Name": "",
                        "Amount": "",
                        "CategorizeStatus": "",
                        "CategoryID": 0,
                        "IsJoined": 0,
                        "IsChecked": false,
                        "ExpensePeriod": new timezoneJS.Date(new Date($scope.receipt.PurchaseTime), $rootScope.loggedInUser.Timezone).toISOString(),
                        "DeletedFileIDs": [],
                        "Attachments": [],
                        "Tags": ''
                    });
                }

                //Populate category info for Add Expense story
                if ($scope.categoryInfo) {
                    var len = $scope.receipt.Items.length;
                    $scope.populateCatToItem($scope.receipt.Items[len-1], $scope.categoryInfo, $scope.tripInfo);
                }
            };

            /**
             * Function to handling checkbox checked status
             */
            $scope.itemIsCheckAll = false;
            $scope.checkAll = function () {
                $scope.itemIsCheckAll = !$scope.itemIsCheckAll;
                $scope.noItemSelected = !$scope.noItemSelected;
                angular.forEach($scope.receipt.Items, function (v, k) {
                    if ((0 == v.IsJoined || v.IsJoined === undefined) && v.Name) {
                        v.IsChecked = $scope.itemIsCheckAll;
                    }
                });
            }

            /**
             * Show confirmation box when user click Delete link
             */
            $scope.showDeleteConfirmationBox = function () {
                var hasCheckedItem = false;
                angular.forEach($scope.receipt.Items, function (v, k) {
                    if (v.IsChecked) {
                        hasCheckedItem = true;
                        return;
                    }
                });

                var boxType = 'alert';
                var boxMessage = 'Please select at least item to delete.';
                if (hasCheckedItem) {
                    boxType = 'confirm';

                    if ($scope.receipt.categorizeMethod != 'one_item') {
                        $scope.deleteAction = 'delete-selected-item';
                        boxMessage = 'Are you sure you want to delete selected item(s)?';
                    } else if ('one_item' == $scope.receipt.categorizeMethod) {
                        $scope.deleteAction = 'delete-combined-item';
                        boxMessage = '<p class="text-left">\
                <label class="radio">\
                    <input type="radio" name="deleteAction" value="delete-combined-item" checked="checked">\
                Only delete "Combined Item" and bring back the individual items list.\
                </label>\
                </p>\
                <p class="text-left">\
                    <label class="radio">\
                    <input type="radio" name="deleteAction" value="delete-all-item">\
                    Delete all items.\
                    </label>\
                </p>';
                    } else {
                        return;
                    }
                }

                $.showMessageBox({
                    content: boxMessage,
                    type: boxType,
                    labelNo: 'Cancel',
                    labelYes: 'Delete',
                    onYesAction: function () {
                        $scope.deleteSelectedItem();
                    }
                });

                $('input[name="deleteAction"]').on('click', function (e) {
                    $scope.deleteAction = this.value;
                });
            }

            /**
             * Remove selected items
             * @returns void
             */
            $scope.deleteSelectedItem = function () {
                if (!$scope.deleteAction) {
                    return;
                }
                $scope.userChangedContent = true;

                // In case delete only combined item. We have to remove the first item in the list and switch all joined item back
                // to individual list
                if ('delete-combined-item' == $scope.deleteAction) {
                    $scope.receipt.categorizeMethod = 'individual_item';
                    $scope.receipt.HasCombinedItem = 0;
                    $scope.receipt.DeletedItems.push($scope.receipt.Items[0].ItemID);
                    $scope.receipt.Items.splice(0, 1);
                    $scope.switchItemJoinedStatus(0);
                } else if ('delete-all-item' == $scope.deleteAction) {
                    angular.forEach($scope.receipt.Items, function (v, k) {
                        $scope.receipt.DeletedItems.push(v.ItemID);
                        $scope.receipt.Items = [];
                    });
                } else {
                    var tmpArr = [];
                    angular.forEach($scope.receipt.Items, function (v, k) {
                        if (!v.IsChecked) {
                            tmpArr.push(v);
                        } else {
                            $scope.receipt.DeletedItems.push(v.ItemID);
                        }
                    });

                    // In case user select all item except Combined Item, we should delete it too
                    if ($scope.receipt.DeletedItems.length == $scope.receipt.Items.length - 1
                        && $scope.receipt.Items[0].IsJoined
                        && 1 == $scope.receipt.HasCombinedItem) {
                        $scope.receipt.DeletedItems.push($scope.receipt.Items[0].ItemID);
                        $scope.receipt.Items = [];
                    } else {
                        $scope.receipt.Items = tmpArr;
                    }
                }

                // If all items is removed, should put one item row in order allow user add more item later on
                if ($scope.receipt.Items.length == 0) {
                    $scope.receipt.Items = $scope.setDefaultReceiptItem(1);
                }

                $scope.updateItemSubTotal();
                $scope.$emit('UPDATE_ITEM_COUNT');
              if(!$scope.$$phase) $scope.$apply();
            }

            /**
             * Categorize receipt items as one item.
             *
             * Combine rules:
             * - User can combine all individual items in a receipt into one "Combined Item"
             * - We still store data for items that the Receipts has before, so user can switch back. List of these items still
             * appear in the RDS, but in gray, not editable.
             * - When each receipt once is converted, the items will be marked "joined", and only the "Combined Item" will be
             * available for use in analytics etc.
             * - Deleting the "Combined Item" will lead user to make a choice: bring back the individual items list? or delete all
             * the items? User decides
             * - Categorizing the "Combined Item" will not affect the categories of each individual item
             * - Attachments which belong to individual items stay the same, separated from the "Combined Item"
             * - Switching back and forth between "individual items" and "combined item" will not delete anything, we keep all,
             * unless user wants to delete.
             */
            $scope.categorizeReceiptAsOneItem = function () {
                var appInUsed = $rootScope.defaultApp;
                var appAbbrInUsed = $rootScope.defaultAppAbbr;
                if ($scope.categoryInfo) {
                    appInUsed = $scope.categoryInfo.app;
                    appAbbrInUsed = $scope.categoryInfo.appAbbr;
                }
                var categoryID  = $scope.receipt.CategoryID | 0;
//                $scope.itemTotal = $scope.receipt.DigitalTotal;
                $scope.itemTotal = $scope.getItemSubTotal();
                $scope.variousAmount = $scope.getItemSubTotal();

                // Do nothing if receipt has combined item
                if ($scope.receipt.HasCombinedItem == 1) {
                    $scope.receipt.Items[0].Name = 'Combined Item';
                    $scope.receipt.Items[0].Amount = $scope.variousAmount;
                    $scope.receipt.Items[0].CategoryApp = appInUsed;
                    $scope.receipt.Items[0].CategoryAppAbbr = appAbbrInUsed;
                    $scope.receipt.Items[0].CategoryID = categoryID;

                    return false;
                }

                $scope.ignoreMessageCombineItem = function(){
                    $scope.userChangedContent = true;
                    $scope.receipt.Subtotal   = $scope.receipt.Items[0].Amount;
                    $scope.compareWithSumOfItemlTotal();
                }

                // Set default action is delete combined item
                $scope.deleteAction = 'delete-combined-item';
                var tmpCategoryName = angular.copy($scope.receipt.CategoryName);
                var combinedItem = {
                    "ItemID": 0,
                    "Name": "Combined Item",
                    "Amount": $scope.getItemSubTotal(),
                    "CategorizeStatus": 0, // Not categorized
                    "IsChecked": false,
                    "ExpensePeriod": new timezoneJS.Date(new Date($scope.receipt.PurchaseTime), $rootScope.loggedInUser.Timezone).toISOString(),
                    "DeletedFileIDs": [],
                    "Attachments": [],
                    "IsJoined": 0,
                    "CategoryID": categoryID,
                    "CategoryApp": appInUsed,
                    "CategoryAppAbbr": appAbbrInUsed,
                    "CategoryName" :tmpCategoryName,
                    "TripID": typeof ($scope.tripInfo) !== 'undefined' ? $scope.tripInfo.tripID : null,
                    "Reference": typeof ($scope.tripInfo) !== 'undefined' ? $scope.tripInfo.reference : null,
                    "forceCategory": true
                };
                // Add combinedItem to beginning of receipt items
                $scope.receipt.Items.unshift(combinedItem);
                $scope.selectedAmount = '';
                $scope.CategorizeStatus = 0; // Not categorized
                // Mark receipt as used combine item
                $scope.receipt.HasCombinedItem = 1;
              if(!$scope.$$phase) $scope.$apply();
            }

            /**
             * Categorize selected receipt items
             */
            $scope.categorizeReceiptSelectedItem = function () {
              var tmpTotalAmount = 0;
              angular.forEach($scope.receipt.Items, function (v, k) {
                if (v.IsChecked && v.Name != '' && !isNaN(v.Amount)) {
                  tmpTotalAmount += parseFloat(v.Amount);
                  v.CategorizeStatus = 0; // Not categorized
                  v.CategoryApp = $rootScope.defaultApp;
                  v.CategoryAppAbbr = $rootScope.defaultAppAbbr;
                }
              });

              $scope.variousAmount = '';
              $scope.selectedAmount = tmpTotalAmount.toFixed(2);

            }

            /**
             * Delete combined item
             */
            $scope.removeCombinedItem = function () {
                if (!$scope.receipt.Items.length) {
                    return;
                }
                if ($scope.receipt.Items[0].Name.indexOf('Combined Item') != -1 && $scope.receipt.HasCombinedItem == 1) {
                    if ($scope.receipt.Items[0].ItemID > 0) {
                        $scope.receipt.DeletedItems.push($scope.receipt.Items[0].ItemID);
                    }
                    $scope.receipt.Items.splice(0, 1);
                }
            }

            /**
             * Categorize receipt or items of receipt
             * @param string type
             */
            $scope.idItemActach = false;
            $scope.categorizeReceipt = function (type) {
                $scope.useQuickCategorization = true;
                $scope.receipt.CategorizeStatus = 0;
                $scope.receipt.CategoryID = 0;
                $scope.receipt.ExpensePeriod = null;
                $scope.clearReceiptCategory();
                $scope.tmpCategoryType =type;

                jQuery('#categorize-section .cat-col .btn-group').tooltip('destroy');
                if (!$scope.categoryInfo) {
                    jQuery('#categorize-section .' + type + '_row .cat-col .btn-group').attr('title', 'Select a Category').tooltip('show');
                    $scope.receipt.CategoryApp = $rootScope.defaultApp;
                    $scope.receipt.CategoryAppAbbr = $rootScope.defaultAppAbbr;
                }
                $timeout(function(){
                    switch (type) {
                        case 'one_item':
                            $timeout(function(){
                            $scope.categorizeReceiptAsOneItem();
                            $scope.switchItemJoinedStatus(1);
                            $scope.itemIsCheckAll = false;
                            $scope.noItemSelected = true;
                            angular.forEach($scope.receipt.Items, function (v, k) {
                                if (!$scope.categoryInfo) {
                                    v.CategoryAppAbbr = $rootScope.defaultAppAbbr;
                                    v.CategoryApp = $rootScope.defaultApp;
                                }else{
                                    v.CategoryAppAbbr = $scope.receipt.CategoryAppAbbr;
                                    v.CategoryApp = $scope.receipt.CategoryApp;

                                }
                                v.IsChecked = false;
                            });
                            $scope.compareWithSumOfItemlTotal();
                                $('#rd-items-table tbody tr:first .item_name_col .form-checkbox').attr('disabled','disabled');
                            });

                            break;
                        case 'selected_item':

                            $scope.removeCombinedItem();
                            $scope.receipt.HasCombinedItem = 0;
                            $scope.switchItemJoinedStatus(0);
                            $scope.categorizeReceiptSelectedItem();
                            $scope.noItemSelected = false;
                            $scope.itemTotal = $scope.getItemSubTotal();
                            if (!$scope.receipt.Items.length) {
                                $scope.addMoreItem();
                            }
                            $('#rd-items-table tbody tr:first .item_name_col .form-checkbox').removeAttr('disabled');
                            break;
                        case 'all_item':
                            $scope.switchItemJoinedStatus(0);
                            $scope.removeCombinedItem();
                            $scope.receipt.HasCombinedItem = 0;
                            $scope.itemIsCheckAll = false;
                            $scope.checkAll();
                            $scope.noItemSelected = false;
                            $scope.categorizeReceiptSelectedItem();
                            $scope.itemTotal = $scope.getItemSubTotal();
                            if (!$scope.receipt.Items.length) {
                                $scope.addMoreItem();
                            }
                            $('#rd-items-table tbody tr:first .item_name_col .form-checkbox').removeAttr('disabled');
                            break;
                        default:
                            $scope.variousAmount = '';
                            $scope.selectedAmount = '';
                            $scope.switchItemJoinedStatus(0);
                            $scope.itemTotal = $scope.getItemSubTotal();
                            $scope.removeCombinedItem();
                            $scope.receipt.HasCombinedItem = 0;
                            if (!$scope.receipt.Items.length) {
                                $scope.addMoreItem();
                            }
                            $('#rd-items-table tbody tr:first .item_name_col .form-checkbox').removeAttr('disabled');
                            break;
                    }
                });


                // Populate app & category in QUICK CATEGORIZATION box
                if ($scope.categoryInfo) {
                    var categoryInfo = {
                        Name: $scope.categoryInfo.categoryName,
                        CategoryID: $scope.categoryInfo.categoryID,
                        MachineName: $scope.categoryInfo.app,
                        AbbrName: $scope.categoryInfo.appAbbr
                    }
                    $scope.updateReceiptCategoryMenu(categoryInfo, $scope.receipt);
                    $scope.performReceiptSelectCat(categoryInfo, $scope.receipt);
                }

              if(!$scope.$$phase) $scope.$apply();
            }

            /*
            * Get compare status when combine item
            * */

            $scope.compareWithSumOfItemlTotal = function(){
                    var total = parseFloat($scope.receipt.Subtotal);
                    if(parseFloat($scope.receipt.Items[0].Amount) != total){
                        $scope.messageWarning = true;
                    }else{
                        $scope.messageWarning = false;
                    }
                    $scope.showMessageWarning = true;
            }

            /* Update category app menu */
            $scope.loadCategory = function (el, appName, option) {
                if (!appName)
                    return;
                if (option == 'reload') {
                    el.Categories = [];
                    if (el.hasOwnProperty('CategoryName')) {
                        el.CategoryName = '';
                    }
                    if (el.hasOwnProperty('categorizeCat')) {
                        el.categorizeCat = 0;
                    }
                    if (el.hasOwnProperty('CategoryID')) {
                        el.CategoryID = 0;
                    }
                } else if (appName != $scope.treeApp) {
                    angular.forEach($rootScope.categories, function (catV, catK) {
                        if (appName === catV.App.MachineName) {
                            $scope.categoryTree = angular.copy(catV.preparedCatList);
                            $scope.treeApp = appName;
                            return true;
                        }
                    });
                }
            }

            /* Functions of directive dropdown categories and app */
            $scope.clearReceiptCategory = function () {
                $scope.receipt.CategoryAppAbbr = '';
                $scope.receipt.CategoryApp = '';
                $scope.receipt.CategoryName = '';
                $scope.receipt.CategorizeStatus = 0;
                $scope.receipt.Categories = [];
            }

            $scope.updateCategoryMenu = function (app, ele, index) {
                ele.CategoryApp = app.MachineName;
                ele.CategoryAppAbbr = app.AbbrName;
                $scope.loadCategory(ele, ele.CategoryApp, 'reload');
                ele.CategorizeStatus = 0;
                if (typeof index !== 'undefined') {
                    jQuery('#rd-items-table .app_col .btn-group').eq(index)
                        .attr('title', app.AbbrName + ' - ' + app.Name)
                        .attr('data-original-title', app.AbbrName + ' - ' + app.Name);

                    // Reset all existing category tooltip
                    //jQuery('#categorize-section .cat-col .btn-group').attr('title', 'Select a Category').attr('data-original-title', 'Select a Category');
                    jQuery('#rd-items-table .cat_col .btn-group').eq(index)
                        .attr('title', 'Select a Category')
                        .attr('data-original-title', 'Select a Category');
                }

                // @see http://113.160.50.82/dev/issues/29947
                // Combined item categorization must work in both directions
                // Vietnh (10/01/2014): Combined item categorization will also categorize all other items
                if (index == 0 && $scope.receipt.HasCombinedItem == 1) {
                    $scope.receipt.CategoryApp = app.MachineName;
                    $scope.receipt.CategoryAppAbbr = app.AbbrName;
                    $scope.receipt.CategorizeStatus = 0;
                    $scope.loadCategory($scope.receipt, $scope.receipt.CategoryApp, 'reload');
                    $scope.triggerLoadCategory($scope.receipt, 'one_item_row');
                    angular.forEach($scope.receipt.Items, function (item, key) {
                        if (key) {
                            item.CategoryApp = app.MachineName;
                            item.CategoryAppAbbr = app.AbbrName;
                        }
                    });
                }
            }

            $scope.triggerLoadCategory = function (ele, index, selectedType) {
                if(selectedType != "item"){
                    var quickCategoryElement = $('categorize-combine');
                    if($scope.idQickCategory){
                        quickCategoryElement = $($scope.idQickCategory);
                    }
                    var quickOffset = $('#'+quickCategoryElement.selector).offset();
                    $timeout(function(){
                        $('#CategoriesBox').css({ top: quickOffset.top + 'px' });
                    });
                }else{
                    var itemCategory = $('#rd-items-table-wrapper').offset();
                    var documentHeight = $(document).height();
                    var bottomOffset = documentHeight - itemCategory.top;
                    $timeout(function(){
                        if(parseFloat($('#CategoriesBox').height()) < parseFloat(bottomOffset)){
                            $('#CategoriesBox').css({ top: itemCategory.top +'px' });
                        }
                    });
                }

                selectedType = typeof selectedType === 'undefined' ? 'receipt' : selectedType;
//        if (!ele.hasOwnProperty('Categories') || ele.Categories.length == 0) {
                $scope.loadCategory(ele, ele.CategoryApp);
//        }

//        $scope.prepareCategoryTree(ele.Categories, ele.CategoryID);
                if (ele.CategoryID == 0) {
                    $scope.noCurrentCat = true;
                } else {
                    $scope.noCurrentCat = false;
                }

                $scope.currentSelectedType = selectedType;
                $scope.currentSelectedIndex = index;

                //Show the categories modal box
                if (isNaN(index)) {
                    var target = $('#categorize-section tr.' + index + ' .cat-col');
                } else {
                    var target = $('#rd-items-table tr:eq(' + index + ') .cat_col');
                }
                target.parent().addClass('item-highlight');
                if (index == 'one_item_row') {
                    $('#rd-items-table tr:eq(0)').addClass('item-highlight');
                } else if (index == 'all_item_row' || index == 'selected_item_row') {
                    $('#rd-items-table tr.selected').addClass('item-highlight');
                } else if (index == 0 && $scope.receipt.HasCombinedItem) {
                    $('#categorize-section tr').addClass('one_item_row');
                    $('#categorize-section tr.one_item_row').addClass('item-highlight');
                }

                var windowWidth = $(window).width();
                if (windowWidth < 1260) {
                    var offset = 35;
                } else if (windowWidth > 1280 && windowWidth < 1380) {
                    var offset = 19;
                } else {
                    var offset = 4;
                }
                $('#CategoriesBox').on('shown', function () {
                    $timeout(function () {
                        $('#CategoriesBox .search-bar-wrapper input').focus();
                    });
                })
                    .on('hidden', function () {
                        //always empty the search input before close the categories box
                        $('#CategoriesBox .search-bar-wrapper input').val('');
                        //return the category tree to normal
                        $('.icon-circle-small').css('width', 14).addClass('icon-circle-p').removeClass('icon-circle-m');
                        $('.categories-tbl-wrapper tr').removeClass('superhide supershow');
                        $('.categories-tbl-wrapper tr.cat-lv2, .categories-tbl-wrapper tr.cat-lv3').addClass('hide');


                        target.parent().removeClass('item-highlight');
                        if (index == 'one_item_row') {
                            $('#rd-items-table tr:eq(0)').removeClass('item-highlight');
                        } else if (index == 'all_item_row' || index == 'selected_item_row') {
                            $('#rd-items-table tr.selected').removeClass('item-highlight');
                        } else if (index == 0 && $scope.receipt.HasCombinedItem) {
                            $('#categorize-section tr.one_item_row').removeClass('item-highlight');
                        }
                    })
                    .appendTo('body').modal('show').css({
                        bottom: 16,
                        left: target.offset().left + target.width() - offset,
                        top: 'initial',
                        marginLeft: 0
                    });

                $('.modal-backdrop.in').css('opacity', 0);

                $('#CategoriesBox .categories-tbl-wrapper').css({
                    height: $('#CategoriesBox').height() - 70
                });

                $(window).resize(function () {
                    windowWidth = $(this).width();
                    if (windowWidth < 1260) {
                        offset = 35;
                    } else if (windowWidth > 1280 && windowWidth < 1380) {
                        offset = 19;
                    } else {
                        offset = 4;
                    }
                    $('#CategoriesBox').css({
                        left: target.parents().offset().left + target.width() - offset
                    });
                    $('#CategoriesBox .categories-tbl-wrapper').css({
                        height: $('#CategoriesBox').height() - 70
                    });
                });

                if (!ele.CategoryAppAbbr) {
                    var msg = 'Please select an Application first';

                    if (!angular.isNumber(index)) {
                        jQuery('#categorize-section .' + index + ' .app-col .btn-group')
                            .attr('title', msg)
                            .attr('data-original-title', msg)
                            .tooltip('show');
                    } else {
                        jQuery('#rd-items-table .app_col .btn-group').eq(index)
                            .attr('title', msg)
                            .attr('data-original-title', msg)
                            .tooltip('show');
                    }
                }
            }
            $scope.preparePerformSelectCat = function (cat) {
                $('#CategoriesBox').modal('hide');

                if ($scope.currentSelectedType == 'receipt') {
                    if (cat == 0) {
                        $scope.uncategorize($scope.receipt);
                    } else {
                        $scope.performReceiptSelectCat(cat, $scope.receipt);
                    }
                }

                if ($scope.currentSelectedType == 'item') {
                    if (cat == 0) {
                        $scope.uncategorize($scope.receipt.Items[$scope.currentSelectedIndex]);
                    } else {
                        $scope.performSelectCat(cat, $scope.receipt.Items[$scope.currentSelectedIndex], $scope.currentSelectedIndex);
                    }
                }
            }

            $scope.uncategorize = function (ele) {
                ele.CategoryName = null;
                ele.CategoryID = 0;
                ele.CategorizeStatus = 0;
                ele.ExpensePeriod = null;
                if (typeof ele.VerifyStatus !== 'undefined' && ele.VerifyStatus == 2) {
                    ele.VerifyStatus = 1;
                }
                if (typeof ele.TripID !== 'undefined') {
                    ele.TripID = null;
                }

                $scope.userChangedContent = true;

                // Added on 2013-11-22: when Combined Item is categorized, auto change the item name to "Combined Item - <direct category name>"
                if ($scope.receipt.HasCombinedItem == 1) {
                    ele.Name = 'Combined Item - Uncategorize';
                }

                // Combined item categorization must work in both directions
                if ($scope.receipt.HasCombinedItem == 1) {
                    $scope.receipt.CategoryName = null;
                    $scope.receipt.CategoryID = 0;
                    $scope.receipt.CategorizeStatus = 0;
                    $scope.receipt.ExpensePeriod = null;

                    if (ele.CategoryApp == 'travel_expense' && angular.isDefined($scope.tripInfo)) {
                        $scope.receipt.Reference = $scope.tripInfo.reference;
                    }

                    angular.forEach($scope.receipt.Items, function (item, key) {
                        if (item.Name != '' && item.Amount != '') {
                            if (key == 0) {
                                item.Name = 'Combined Item - Uncategorize';
                            }
                            item.CategoryName = null;
                            item.CategoryID = 0;
                            item.CategorizeStatus = 0;
                            item.ExpensePeriod = null;
                        }
                    });
                }
            }

            $scope.performSelectCat = function (cat, ele, index) {
                ele.CategoryName = cat.Name;
                ele.CategoryID = cat.CategoryID;
                ele.CategorizeStatus = 2;
                $scope.userChangedContent = true;

                if (!ele.hasOwnProperty('ExpensePeriod') || !ele.ExpensePeriod) {
                    try {
                        ele.ExpensePeriod = new timezoneJS.Date(new Date($scope.receipt.PurchaseTime), $rootScope.loggedInUser.Timezone).toISOString();
                    } catch (err) {
                        ele.ExpensePeriod = new Date($scope.receipt.PurchaseTime).toISOString();
                    }
                }

                // Added on 2013-11-08: if users enter amount, category but leave item name blank -> auto-generate item name (take the lowest level category name)
                if (!ele.Name && ele.Amount) {
                    ele.Name = ele.CategoryName;
                }

                // Added on 2013-11-22: when Combined Item is categorized, auto change the item name to "Combined Item - <direct category name>"
                if (ele.Name && ele.Name.indexOf('Combined Item') != -1 && $scope.receipt.HasCombinedItem == 1) {
                    ele.Name = 'Combined Item - ' + ele.CategoryName;
                }

                //jQuery('#categorize-section .cat-col .btn-group').attr('title', cat.Name).attr('data-original-title', cat.Name);
                if (typeof index !== 'undefined') {
                    jQuery('#rd-items-table .cat_col .btn-group').eq(index)
                        .attr('title', cat.Name)
                        .attr('data-original-title', cat.Name);
                }

                // Show Trip Reference as default if user comes from Travel Expense app
                if (ele.CategoryApp == 'travel_expense' && angular.isDefined($scope.tripInfo)) {
                    ele.Reference = $scope.tripInfo.reference;
                }

                // @see http://113.160.50.82/dev/issues/29947
                // Combined item categorization must work in both directions
                if (index == 0 && $scope.receipt.HasCombinedItem == 1) {
                    $scope.receipt.CategoryName = cat.Name;
                    $scope.receipt.CategoryID = cat.CategoryID;
                    $scope.receipt.CategorizeStatus = 2;

                    $scope.receipt.ExpensePeriod = ele.ExpensePeriod;

                    if (ele.CategoryApp == 'travel_expense' && angular.isDefined($scope.tripInfo)) {
                        $scope.receipt.Reference = $scope.tripInfo.reference;
                    }

                    angular.forEach($scope.receipt.Items, function (item, key) {
                        if (key && item.Name != '' && item.Amount != '') {
                            item.CategoryName = cat.Name;
                            item.CategoryID = cat.CategoryID;
                            item.CategorizeStatus = 2;
                        }
                    });
                }
            }

            $scope.performReceiptSelectCat = function (cat, receipt) {
                $scope.performSelectCat(cat, receipt);
                jQuery('#categorize-section .cat-col .btn-group').attr('title', cat.Name).attr('data-original-title', cat.Name);

                if (receipt.categorizeMethod == 'one_item') {
                    $scope.performSelectCat(cat, receipt.Items[0], 0);

                } else if (receipt.categorizeMethod == 'selected_item' || receipt.categorizeMethod == 'all_item') {
                    angular.forEach(receipt.Items, function (item, k) {
                        if (receipt.HasCombinedItem > 0 && k == 0) {
                            return;
                        }

                        if (item.IsChecked == true) {
                            $scope.performSelectCat(cat, item, k);
                        }
                    });
                }
            }

            $scope.updateReceiptCategoryMenu = function (app, receipt) {
                if (receipt.categorizeMethod == 'one_item') {
                    // Synchronize with all items App combo box
                    $scope.updateCategoryMenu(app, receipt);
                    $scope.updateCategoryMenu(app, receipt.Items[0], 0);

                } else if (receipt.categorizeMethod == 'selected_item' || receipt.categorizeMethod == 'all_item') {
                    if (receipt.categorizeMethod == 'all_item') {
                        $scope.noItemSelected = false;
                    }
                    //return if no item is selected
                    else if ($scope.noItemSelected) {
                        //$.showMessageBox({content: 'Please select at least one item before categorizing'});
                        //return;
                    }

                    $scope.updateCategoryMenu(app, receipt);
                    angular.forEach(receipt.Items, function (item, k) {
                        if (receipt.HasCombinedItem > 0 && k == 0) {
                            return;
                        }

                        if (item.IsChecked == true) {
                            $scope.updateCategoryMenu(app, item, k);
                        }
                    });
                }

                jQuery('#categorize-section .app-col .btn-group')
                    .attr('title', app.AbbrName + ' - ' + app.MachineName)
                    .attr('data-original-title', app.AbbrName + ' - ' + app.Name);

                // Reset all existing category tooltip
                jQuery('#categorize-section .cat-col .btn-group').attr('title', '').attr('data-original-title', 'Select a Category');
                jQuery('#rd-items-table .cat_col .btn-group')
                    .attr('title', 'Select a Category')
                    .attr('data-original-title', 'Select a Category');
                $scope.userChangedContent = true;
            }
            /**
             * Watch receipt.ExpensePeriod changes, will update the related element on the form
             */

            $scope.$watch('receipt.ExpensePeriod', function (newValue, oldValue, scope) {
                if (!$scope.useQuickCategorization)
                    return false;
                if (newValue && $scope.receipt.categorizeMethod == 'one_item') {
                    angular.forEach($scope.receipt.Items, function (item) {
                        item.ExpensePeriod = newValue;
                    });
                } else if (newValue && $scope.receipt.categorizeMethod == 'selected_item' || newValue && $scope.receipt.categorizeMethod == 'all_item') {
                    angular.forEach($scope.receipt.Items, function (v, k) {
                        if (v.IsChecked) {
                            v.ExpensePeriod = newValue;
                        }
                    });
                }

            });

            $scope.UserChangedContent = function(){
                $scope.userChangedContent = true;
            }

            /**
             * Watch ExpensePeriod of Combined item changes, will update the related element on the form
             */
            $scope.$watch('receipt.Items[0].ExpensePeriod', function (newValue, oldValue, scope) {
                if (newValue && newValue != $scope.receipt.ExpensePeriod && $scope.receipt.categorizeMethod == 'one_item') {
                    $scope.receipt.ExpensePeriod = newValue;
                }
            });

            /**
             * Watch receipt.TripID changes, will update the related element on the form
             */
            $scope.$watch('receipt.TripID', function (newValue, oldValue, scope) {
                if (newValue && $scope.receipt.categorizeMethod == 'one_item') {
                    angular.forEach($scope.receipt.Items, function (item, key) {
                        item.TripID = newValue;
                        item.Reference = $scope.receipt.Reference;
                    });
                } else if (newValue && $scope.receipt.categorizeMethod == 'selected_item' || newValue && $scope.receipt.categorizeMethod == 'all_item') {
                    angular.forEach($scope.receipt.Items, function (v, k) {
                        if (v.IsChecked) {
                            v.TripID = newValue;
                            v.Reference = $scope.receipt.Reference;
                        }
                    });
                }
            });
            /**
             * Watch TripID of Combined Item changes, will update the related element on the form
             */
            $scope.$watch('receipt.Items[0].TripID', function (newValue, oldValue, scope) {
                if (newValue && newValue != $scope.receipt.TripID && $scope.receipt.categorizeMethod == 'one_item') {
                    $scope.receipt.TripID = newValue;
                    $scope.receipt.Reference = $scope.receipt.Items[0].Reference;
                }
            });

            $scope.deleteReceipt = function (receipt) {
                $.showMessageBox({
                    content: 'Are you sure you want to delete this receipt?',
                    boxTitle: 'DELETE RECEIPT',
                    boxTitleClass: '',
                    type: 'confirm',
                    onYesAction: function () {
                        $timeout(function () {
                          $scope.deletingReceipt = true;
                          $scope.txtLoading      = 'Deleting...';
                            Restangular.one('receipts').remove({ReceiptIDs: receipt.ReceiptID}).then(function (response) {

                              $scope.$emit('FILTER_RECEIPT_LIST');
                              $scope.$emit('RELOAD_PE_LIST', true);
                              $scope.$emit('RELOAD_BE_LIST', true);
                              $scope.$emit('RELOAD_EE_LIST', true);
                              $scope.$emit('RELOAD_PA_LIST', true);
                              $scope.$emit('RELOAD_BA_LIST', true);

                              if (response.RefreshTrip) {
                                $scope.$emit('LOAD_TRIP_LIST');
                              }

                              if (response.RefreshReport) {
                                $scope.$emit('LOAD_REPORT_LIST');
                              }

                              $timeout(function(){

                                $scope.close();
                                $scope.deletingReceipt = false;
                                $scope.txtLoading      = '';
                              }, 1000);

                            }, function (response) {
                                $scope.responseMessage = response.data.message;
                            });
                        });
                    }
                });
            }
            $scope.setCurrentTotalAmount = function () {
                $scope.currentAmount = angular.copy(parseFloat($scope.getItemSubTotal()).toFixed(2));
            }

            $scope.setWidthError = function () {
                var h = $('.info-items').width();
                var k = $('.total-container').width();
                var l = h - k - 80;
                $('.info-items .text-error').width(l);
                $('.info-items .text-error').each(function(){
                    var str = $(this).not('i').text()
                    var str = str.replace(/\s+/g, ' ');
                    $(this).attr('title', str);
                })
            }

            $scope.updateReceipt = function()
            {
                $scope.receipt.DigitalTotal = parseFloat($scope.getItemSubTotal()).toFixed(2);
                $scope.compareWithSumOfSubtotal();
            }
          $scope.updateItemSubTotal = function (e) {
            $timeout(function () {
              $scope.itemTotal = parseFloat($scope.getItemSubTotal()).toFixed(2);
              if (!$scope.receipt.ReceiptID) {
                $scope.receipt.Subtotal = $scope.itemTotal;
                $scope.updateReceiptTotalAmount();
                $scope.compareWithSumOfSubtotal();
              } else {
                if ($scope.receipt.HasCombinedItem == 1) {
                  //$scope.receipt.Subtotal = $scope.receipt.Items[0].amount;
                  $scope.compareWithSumOfItemlTotal();
                }
              }

            if($scope.isManualReceipt){
              $scope.updateSubtotal();
              $scope.userChangedContent = true;
            }
            });
          }

          /**
           * Fuction to listen sub total variable change
           */
          //
          $scope.updateSubtotalWithTotal = function(){
            $timeout(function(){
              var tmpTotal      = parseFloat($scope.receipt.DigitalTotal) | 0;
              var tmpTax        = parseFloat($scope.receipt.Tax) | 0;
              var tmpExtraValue = parseFloat($scope.receipt.ExtraValue) | 0;
              var tmpSubTotal   = tmpTotal - tmpTax - tmpExtraValue;
              console.log(tmpTotal);
              console.log(tmpTax);
              console.log(tmpExtraValue);
              tmpSubTotal = (tmpSubTotal <=  0) ? 0 : tmpSubTotal;

              $scope.receipt.Subtotal = tmpSubTotal.toFixed(2);
              //compareWithSumOfSubtotal
            },50);
            $scope.userChangedContent = true;
          }

            /*
            * Replace subtotal with item total
            * */
            $scope.updateSubtotal = function(){
                    $timeout(function(){
                        $scope.receipt.Subtotal = parseFloat($scope.getItemSubTotal()).toFixed(2);
                        $scope.updateReceiptTotalAmount();
                    });
                $scope.userChangedContent = true;
            }

            $scope.getItemSubTotal = function () {
                var subtotal = 0;
                    if (typeof $scope.receipt.Items !== 'undefined') {
                        if($scope.receipt.HasCombinedItem != 1){
                            angular.forEach($scope.receipt.Items, function (v, k) {
                                if (v.Amount && v.IsJoined != 1) {
                                    subtotal += parseFloat(v.Amount);
                                }
                            });
                        }else{
                            angular.forEach($scope.receipt.Items, function (v, k) {
                                if(k>=1){
                                    if (v.Amount) {
                                        subtotal += parseFloat(v.Amount);
                                    }
                                }
                            });
                        }
                    }
                return subtotal.toFixed(2);
            }
            /**
             * Hide the element
             * @param el Element selector to hide
             */
            $scope.hideElement = function (el, index) {
                if (typeof index !== 'undefined') {
                    el = el.replace('[index]', index);
                }

                jQuery(el).css('display', 'none');
            }
            /**
             * Remove the attachment file from list
             *
             * @param obj Receipt or Item
             * @param el  File element
             * @param index The position of element to delete
             */
            $scope.removeAttachment = function (obj, el, index, e) {
                obj.DeletedFileIDs.push(el.FileID);
                obj.Attachments.splice(index, 1);
                $scope.userChangedContent = true;
                e.stopPropagation();
            }
            /**
             * Switch all item status in case receipt was used Combined Item
             */
            $scope.switchItemJoinedStatus = function (status) {
                angular.forEach($scope.receipt.Items, function (v, k) {
                    if (0 == k && 1 == $scope.receipt.HasCombinedItem) {
                        v.IsJoined = status == 0 ? 1 : 0;
                    } else {
                        v.IsJoined = status;
                    }
                });
            }
            /**
             * Show modal box for receipt or item
             *
             * @param type 'receipt' or 'item'
             */
            $scope.openShowMoreModal = function (type) {
              if(!$scope.receipt.IsReported){
                if (type == 'receipt') {
                  $rootScope.displayShowMore($scope.receipt);
                } else {
                  $rootScope.displayShowMore($scope.receipt.Items[0]);
                }
              }
            }
            $scope.zoomOutImages = function(){
                jQuery('.HelloZoomArea').each(function () {
                    jQuery(this).remove();
                });
            }

            $('body').delegate('.list-attachment', 'click', function (e) {
                e.stopPropagation();
            });

            $('body').bind('click', function (e) {
                $('.list-attachment').fadeOut();
            });

            // Listener for changing input/textarea value when user drag & drop text
            $('#rd-container input, #rd-container textarea').on('drop', function (e) {
                e.preventDefault();
                this.value = selectionTmpValue;
                $(this).trigger('input');
                $scope.userChangedContent = true;
            });
            /**
             * Listener for counting items in receipt
             */
            $scope.$on('UPDATE_ITEM_COUNT', function (event, message) {
                $scope.receipt.ItemCount = 0;
                angular.forEach($scope.receipt.Items, function (v, k) {
                    if (jQuery.trim(v.Name)) {
                        $scope.receipt.ItemCount++;
                    }
                });

                // Don't count combined item
                if (1 == $scope.receipt.HasCombinedItem) {
                    $scope.receipt.ItemCount--;
                }
            });

            $scope.showCurrencyConverterBox = function () {
                $('#currencyConverterBox').appendTo('body').modal({show: true});
            }

            $scope.confirmConvertCurrency = function (currencyCode) {
                $scope.TmpCurrencyCode = currencyCode;
                $scope.userChangedContent = true;
                $scope.convertedCurrency = false;
                if ($scope.TmpCurrencyCode != $rootScope.loggedInUser.CurrencyCode) {
                    $scope.requireUserConvertCurrency = true;
                    $scope.showConfirmConvertMessage = true;
                    $scope.hideAfterReset = false;
                } else {
                    $scope.requireUserConvertCurrency = false;
                    $scope.showConfirmConvertMessage = false;
                    $scope.hideAfterReset = true;
                    $('#rd-receipt-currency .icon-fx').removeClass('red');
                    $scope.resetToOriginalCurrency();
                }

                /*
                if ($scope.receipt.CurrencyConverted != 0 && $scope.convertCurrencyPartFlag) {
                    var receiptOriginalData = JSON.parse($scope.receipt.ReceiptData);
                    var receiptOriginalItemData = JSON.parse($scope.receipt.ReceiptItemData);
                    $scope.receipt.Subtotal = parseFloat(receiptOriginalData.Subtotal).toFixed(2);
                    $scope.receipt.DigitalTotal = receiptOriginalData.DigitalTotal;
                    $scope.receipt.Tax = receiptOriginalData.Tax;
                    $scope.receipt.ExtraValue = receiptOriginalData.ExtraValue;
                    $scope.receipt.Items = receiptOriginalItemData;
                    $scope.receipt.ExchangeRate = receiptOriginalData.ExchangeRate;
                    $scope.updateItemSubTotal();
                    $scope.receipt.PrevPurchaseTime = angular.copy($scope.receipt.PurchaseTime);
                    $scope.convertCurrencyPartFlag = false;
                    $scope.receipt.IsRecentlyConverted = true;
                    if ($scope.TmpCurrencyCode == $rootScope.loggedInUser.CurrencyCode) {
                        $scope.isResetCurrency = true;
                    }
                }
                */
            }

            /**
             * Convert original currency to home currency
             */
            $scope.convertCurrency = function (auto) {
                var receiptOriginalData = JSON.parse($scope.receipt.ReceiptData);
                var receiptOriginalItemData = JSON.parse($scope.receipt.ReceiptItemData);

                $scope.receipt.Subtotal = parseFloat(receiptOriginalData.Subtotal).toFixed(2);
                $scope.receipt.DigitalTotal = receiptOriginalData.DigitalTotal;
                $scope.receipt.Tax = receiptOriginalData.Tax;
                $scope.receipt.ExtraValue = receiptOriginalData.ExtraValue;
                angular.forEach(receiptOriginalItemData, function (valueOrigin, keyOrigin) {
                    angular.forEach($scope.receipt.Items, function (valueCurrent, keyCurrent) {
                        if(valueOrigin.ItemID == valueCurrent.ItemID) {
                            valueCurrent.Amount = valueOrigin.Amount;
                        }
                    });
                });

                //$scope.receipt.Items = receiptOriginalItemData;
                $scope.receipt.ExchangeRate = receiptOriginalData.ExchangeRate;
                $scope.updateItemSubTotal();

                $scope.receipt.CurrencyConverted = 0;
                $scope.receipt.convertCurrencyHistory = [];

                //Cancel if receipt is in saving progress to avoid conflict
                if ($scope.receiptInSaving == true) {
                    return false;
                }

                auto = typeof auto == 'undefined' ? false : true;
                if (angular.isDefined($scope.receipt.VerifyStatus) && $scope.receipt.VerifyStatus != 2 && $scope.receipt.VerifyStatus != 0) {
                    $.showMessageBox({
                        content: 'Please Validate the receipt before currency converting',
                        type: 'alert'
                    });

                    return false;
                }

                $timeout(function () {
                    $('#rd-receipt-currency .icon-fx').addClass('red');
                });

                if ($scope.TmpCurrencyCode != $rootScope.loggedInUser.CurrencyCode) {
                    $scope.receipt.convertCurrencyHistory.unshift($scope.TmpCurrencyCode);
                }

                $scope.userChangedContent = true;
                $scope.showConfirmConvertMessage = false;

                if (!auto) {
                    $scope.convertingMessage = 'Converting, please wait... >>>>>>>>>';
                } else {
                    $scope.convertingMessage = 'Reconverting on new purchase date, please wait... >>>>>>>>>';
                }

                $scope.convertingMessageHtmlClass = 'red';

                $scope.tmpAmount = {};
                $scope.tmpAmount.exchangeRate = 1;
                $scope.tmpAmount.items = angular.copy($scope.receipt.Items);
                $scope.tmpAmount.subtotal = angular.copy($scope.receipt.Subtotal);
                $scope.tmpAmount.digitalTotal = angular.copy($scope.receipt.DigitalTotal);
                $scope.tmpAmount.tax = angular.copy($scope.receipt.Tax);
                $scope.tmpAmount.extraValue = angular.copy($scope.receipt.ExtraValue);

                if ($scope.receipt.CurrencyConverted == 0) {
                    $scope.processConvertCurrency($scope.receipt.PurchaseTime, $scope.TmpCurrencyCode, $rootScope.loggedInUser.CurrencyCode).then(function (successCallback) {
                        $scope.receipt.IsRecentlyConverted = true;
                        $scope.convertCurrencyPartFlag = true;
                        $scope.convertingMessageHtmlClass = 'blue';
                        $scope.applyNewAmount();
                    }, function (errorCallback) {
                        alert(errorCallback);
                    });
                } else {
                    var fromCode, toCode;
                    fromCode = $rootScope.loggedInUser.CurrencyCode;
                    toCode = $scope.receipt.convertCurrencyHistory[1];

                    if (auto && $scope.convertCurrencyPartFlag) {
                        $scope.processConvertCurrency($scope.receipt.PrevPurchaseTime, fromCode, toCode).then(function (successCallback) {
                            $scope.receipt.PrevPurchaseTime = angular.copy($scope.receipt.PurchaseTime);

                            $scope.processConvertCurrency($scope.receipt.PurchaseTime, $scope.receipt.convertCurrencyHistory[0], fromCode).then(function (successCallback) {
                                $scope.receipt.IsRecentlyConverted = true;
                                $scope.applyNewAmount();
                                $scope.convertCurrencyPartFlag = true;
                            }, function (errorCallback) {
                                alert(errorCallback);
                            });
                        }, function (errorCallback) {
                            alert(errorCallback);
                        });
                    } else {
                        $scope.processConvertCurrency($scope.receipt.PurchaseTime, $scope.receipt.convertCurrencyHistory[0], fromCode).then(function (successCallback) {
                            $scope.receipt.IsRecentlyConverted = true;
                            $scope.applyNewAmount();
                            $scope.convertCurrencyPartFlag = true;
                        }, function (errorCallback) {
                            alert(errorCallback);
                        });
                    }


                }

                $scope.receipt.CurrencyConverted = 1;
            }

            /**
             * Apply new exchange rate for all amount in the receipt
             */
            $scope.applyNewAmount = function () {
                $scope.requireUserConvertCurrency = false;
                $scope.convertingMessage = '';
                $scope.convertedCurrency = true;
                $scope.receipt.Subtotal = $scope.tmpAmount.subtotal.toFixed(2);
                $scope.receipt.DigitalTotal = $scope.tmpAmount.digitalTotal;
                $scope.receipt.Tax = $scope.tmpAmount.tax;
                $scope.receipt.ExtraValue = $scope.tmpAmount.extraValue;
                $scope.receipt.Items = $scope.tmpAmount.items;
                $scope.receipt.ExchangeRate = $scope.tmpAmount.exchangeRate;

                $scope.updateItemSubTotal();
            }

            /**
             * Function process converting currency
             *
             * @param purchaseDate
             * @param fromCode
             * @param toCode
             * @returns {promise|*|promise|Function|promise}
             */
            $scope.processConvertCurrency = function (purchaseDate, fromCode, toCode) {
                var deferred = $q.defer();
                $scope.isResetCurrency = false;
                openExchange.getExchangeRate(purchaseDate, fromCode).then(function (data) {
                    if (data.hasOwnProperty('error') && data.error) {
                        deferred.reject(data.description);
                    } else {
                        angular.forEach(data.rates, function (rate, code) {
                            if (code == toCode) {
                                $scope.tmpAmount.exchangeRate = rate;
                                return;
                            }
                        });

                        // Apply new rate for all items
                        angular.forEach($scope.tmpAmount.items, function (item, k) {
                            item.Amount *= $scope.tmpAmount.exchangeRate;
                        });

                        $scope.tmpAmount.tax *= $scope.tmpAmount.exchangeRate;
                        $scope.tmpAmount.subtotal *= $scope.tmpAmount.exchangeRate;
                        $scope.tmpAmount.digitalTotal *= $scope.tmpAmount.exchangeRate;

                        if (!isNaN($scope.tmpAmount.extraValue)) {
                            $scope.tmpAmount.extraValue *= $scope.tmpAmount.exchangeRate;
                        }

                        deferred.resolve($scope.tmpAmount);
                    }
                });

                return deferred.promise;
            }

            /**
             * Reset to original currency
             */
            $scope.resetToOriginalCurrency = function () {
                var receiptOriginalData = JSON.parse($scope.receipt.ReceiptData);
                var receiptOriginalItemData = JSON.parse($scope.receipt.ReceiptItemData);

                for (var i in receiptOriginalItemData) {
                    if (!receiptOriginalItemData[i].TripID) {
                        receiptOriginalItemData[i].TripID = null;
                    }
                }

                $scope.userChangedContent = true;
                $scope.showConfirmConvertMessage = false;
                $scope.requireUserConvertCurrency = false;
                $scope.convertedCurrency = true;
                $scope.receipt.Subtotal = parseFloat(receiptOriginalData.Subtotal).toFixed(2);
                $scope.receipt.DigitalTotal = receiptOriginalData.DigitalTotal;
                $scope.receipt.Tax = receiptOriginalData.Tax;
                $scope.receipt.ExtraValue = receiptOriginalData.ExtraValue;
                $scope.receipt.Items = receiptOriginalItemData;
                $scope.receipt.ExchangeRate = receiptOriginalData.ExchangeRate;
                $scope.updateItemSubTotal();
                $scope.TmpCurrencyCode = $rootScope.loggedInUser.CurrencyCode;
                $scope.receipt.CurrencyConverted = 0;
                $scope.receipt.convertCurrencyHistory = [];
                $scope.receipt.IsRecentlyConverted = true;
                $scope.isResetCurrency = true;
                $scope.hideAfterReset = true;

                $timeout(function () {
                    $('#rd-receipt-currency .icon-fx').removeClass('red');
                });
            }

            $(window).resize(function () {
                alignAppCatDropdown();
            });

            /**
             * Calculate receipt Digital Total based on Subtotal, Tax and Extra Field
             */
            $scope.updateReceiptTotalAmount = function () {
              if($scope.receipt.HasCombinedItem == 1){
                $scope.compareWithSumOfItemlTotal();
              }

                var subtotal = parseFloat($scope.receipt.Subtotal) || 0;
                var tax = parseFloat($scope.receipt.Tax) || 0;
                var extra = parseFloat($scope.receipt.ExtraValue) || 0;
                var tmpTotal = subtotal + tax + extra;
                tmpTotal = tmpTotal.toFixed(2);

                if (subtotal > 0) {
                    $scope.receipt.DigitalTotal = tmpTotal;
                }

                $scope.responseMessage = [];
            }

            $scope.enableSaveMerchantCountry = function () {
                if($scope.receipt.MerchantCountry != $( "#MerchantCountryCode" ).val()) {
                    $scope.userChangedContent = true;
                }
            }



            /**
             * Auto fix receipt amount when receipt is loaded
             */
            $scope.initReceiptAmount = function () {
                var tmpTotal = 0;
                var subtotal = parseFloat($scope.receipt.Subtotal) || 0;
                var total = parseFloat($scope.receipt.DigitalTotal) || 0;
                var tax = parseFloat($scope.receipt.Tax) || 0;
                var extra = parseFloat($scope.receipt.ExtraValue) || 0;
                var tmpTotal = subtotal + tax + extra;
                tmpTotal = tmpTotal.toFixed(2);
                $scope.tmpppm = tmpTotal;
                $scope.responseMessage = ['Total does not match with the sum of Subtotal, Tax...'];
                if (tmpTotal != total && total > 0 && subtotal > 0 && tax > 0 && extra > 0) {
                    $scope.responseMessage = ['Total does not match with the sum of Subtotal, Tax...'];
                    $scope.totalNotMatchSub = true;
                } else { // Auto correct fields amount
                    $scope.totalNotMatchSub = false;
                    if (subtotal == 0 && (total - (tax + extra) > 0)) {
                        $scope.receipt.Subtotal = total - (tax + extra);
                    } else if (subtotal > 0 && tax == 0 && extra > 0 && (total - subtotal - extra > 0)) {
                        $scope.receipt.Tax = total - subtotal - extra;
                    } else if (subtotal > 0 && tax > 0 && extra == 0 && (total - subtotal - tax > 0)) {
                        $scope.receipt.ExtraValue = total - subtotal - tax;
                    } else if (total == 0) {
                        $scope.receipt.DigitalTotal = subtotal + tax + extra;
                    }
                    $scope.responseMessage = [];
                }
            }

            $scope.compareWithSumOfSubtotal = function () {
                var subtotal = parseFloat($scope.receipt.Subtotal) || 0;
                var total = parseFloat($scope.receipt.DigitalTotal) || 0;
                var tax = parseFloat($scope.receipt.Tax) || 0;
                var extra = parseFloat($scope.receipt.ExtraValue) || 0;
                if (total - (tax + extra) > 0) {
                    subtotal = total - (tax + extra);
                    $scope.receipt.Subtotal = parseFloat(subtotal.toFixed(2));

                }

                var tmpTotal = subtotal + tax + extra;
                tmpTotal = tmpTotal.toFixed(2);

                if ($scope.receipt.HasCombinedItem) {
                    $scope.itemTotal = $scope.getItemSubTotal();
                }
                total = total.toFixed(2);
                tmpTotal = parseFloat(tmpTotal);
                total = parseFloat(total);

                if (total != tmpTotal) {
                    $scope.responseMessage = ['Total does not match with the sum of Subtotal, Tax...'];
                    $scope.totalNotMatchSub = true;
                } else {
                    $scope.responseMessage = [];
                    $scope.totalNotMatchSub = false;
                }
            }

            /*
             * As soon as the selected items have been categorized i.e the category has been selected, you should automatically
             * de-select those items, and reset the “APP” and “Category” boxes for the ‘Categorize selected items’ radio button.
             * Set focus to the ‘categorize items individually’ radio button when done
             */
            $scope.switchToCategorizeIndividualMode = function () {
                $scope.receipt.categorizeMethod = 'individual_item';
                $scope.categorizeReceipt('individual_item');
                $scope.itemIsCheckAll = false;
                angular.forEach($scope.receipt.Items, function (v, k) {
                    if (v.IsChecked) {
                        v.IsChecked = false;
                    }
                });
            }

            // Listener for updating Subtotal/Total amount
            var tmpAmountValue = 0;
            $('#receipt-subtotal, #receipt-total').on('focus', function (e) {
                e.preventDefault();
                tmpAmountValue = this.value;
            }).on('blur', function (e) {
                e.preventDefault();
                if (this.value == 0) {
                    this.value = tmpAmountValue;
                    $(this).trigger('input');
                }
            });

            /**
             * Ipad view only: disable Original Receipt tab, show Extracted Receipt tab
             */
            $scope.changeClassOri = function () {
                if ($('#rd-ori-title').attr('class') == 'unactive') {
                    $('#rd-ori-title').attr('class', 'active');
                    $('#rd-ext-title').attr('class', 'unactive');
                }
            }
            $scope.changeClassExt = function () {
                if ($('#rd-ext-title').attr('class') == 'unactive') {
                    $('#rd-ext-title').attr('class', 'active');
                    $('#rd-ori-title').attr('class', 'unactive');
                }
            }
            $scope.toggleExtractedReceiptTab = function () {
                $('#rd-col-content .container-col1').addClass('show-if-size-change').hide();
                $('#rd-col-content .container-col2').show();
                if ($('#small-screen').attr('class') == 'unactive') {
                    $('#small-screen').attr('class', 'active');
                }
            }
            /**
             * Ipad view only: disable Original Receipt tab, show Extracted Receipt tab
             */
            $scope.toggleOriginalReceiptTab = function () {
                $('#rd-col-content .container-col2').addClass('show-if-size-change').hide();
                $('#rd-col-content .container-col1').show();
                if ($('#small-screen').attr('class') == 'active') {
                    $('#small-screen').attr('class', 'unactive');
                }
            }

            $scope.$watch('receipt.PurchaseTime', function (newValue, oldValue, scope) {
                if (!$('#receipt-detail-wrapper:visible').length) {
                    return false;
                }

                if (!oldValue && newValue) {
                    return false;
                }

                if (newValue && newValue == jQuery('#tmp_purchase_date').val()) {
                    //When purchase date of the receipt is changed, we need to change all expense periods by it
                    if ($scope.receipt.Items.length) {
                        angular.forEach($scope.receipt.Items, function (v, k) {
                            try {
                                v.ExpensePeriod = new timezoneJS.Date(new Date($scope.receipt.PurchaseTime), $rootScope.loggedInUser.Timezone).toISOString();
                            } catch (err) {
                                v.ExpensePeriod = new Date($scope.receipt.PurchaseTime).toISOString();
                            }
                        });
                    }

                    //Check the case that we need to execute the currency conversion
                    if (!$scope.isResetCurrency && $scope.receipt.ExchangeRate && $scope.TmpCurrencyCode != $rootScope.loggedInUser.HomeCurrency) {
                        //We only allow user to choose date which year is equal or greater than 1999
                        if (new Date(newValue).getFullYear() < 1999) {
                            $.showMessageBox({
                                content: 'The currency exchange is available from 1999. Please select your purchase date again.',
                                type: 'alert'
                            });

                            return false;
                        }

                        //Hide the datetimepicker
                        $('#purchase_date').datetimepicker('hide');

                        //Do the currency conversion automatically
                        $scope.convertCurrency(true);
                    }
                }
            });

          /**
           * Open Receipt Detail screen by ReceiptID
           * @param id int
           */
          $scope.openRdById = function (id, direction) {
            var app = angular.copy($scope.openRDFrom);
            var tripInfo = angular.copy($scope.tripInfo);
            var categoryInfo = angular.copy($scope.categoryInfo);
            if (id) {
              if ($scope.UserChangedContent == true) {
                $scope.confirmToSave(function () {
                  $timeout(function(){
                    $scope.save($scope.receipt, false, false, function () {
                      $scope.setPaginationValue(function (data) {
                          $rootScope.$emit('PAGING_RECEIPT_LIST', data, direction);
                      });
                      $scope.$emit('LOAD_RECEIPT_DETAIL', id, undefined, app, undefined, categoryInfo, tripInfo);
                    })
                  });
                });
              } else {

                $scope.setPaginationValue(function (data) {
                    $rootScope.$emit('PAGING_RECEIPT_LIST', data, direction);
                });
                $scope.$emit('LOAD_RECEIPT_DETAIL', id, undefined, app, undefined, categoryInfo, tripInfo);

              }
            }

          }

            /**
             * Open file upload box
             */
            $scope.chooseImage = function () {
                //Validate before allowing upload
                if (!$scope.receipt.MerchantName || !$scope.receipt.DigitalTotal) {
                    var total = parseFloat($scope.receipt.DigitalTotal) || 0;
                    var msg   = '<li class="';
                    msg += (!$scope.receipt.MerchantName)? 'incorrect':'';
                    msg += '"><i class="app-icon icon-success"></i>Merchant Name is required</li>';
                    msg += '<li class="';
                    msg += (total <= 0)? 'incorrect':'';
                    msg += '"><i class="app-icon icon-success"></i>Total Amount must be greater than zero</li>';

                    $.showMessageBox({
                        boxTitle: 'Upload Results',
                        boxTitleClass: 'show utmaltergothic',
                        boxClass: 'box-validation error',
                        content: '<h2 class="utmaltergothic">Upload Unsuccessful</h2><p>Please make corrections to element(s) marked in red:</p><ul style="margin-left: 44px;" class="unstyled">' + msg + '</ul>',
                        type: 'alert'
                    });
                    return false;
                }

                //Trigger upload automatically when a file is selected
                $('#receipt-image').click();
                $('#receipt-image').unbind('change').change('change', function(){
                    if (this.files && this.files[0]) {
                        $scope.receiptInProcessing = true;

                        //Show blank pdfViewer with loading
                        $scope.loadPdfUrl("");
                        $("#pdfContainer").empty();
                        $('.loading-pdf-receipt').show();

                        if (!$scope.receipt.ReceiptID) {
                            //Save non-saved receipt before uploading
                            $scope.save($scope.receipt, false, false, function afterSave(res){

                              if($scope.isManualReceipt){
                                $scope.lblReceiptType = "Manual Receipt";
                              }
                                //then upload image to receipt ID
                                if ($scope.receipt.ReceiptID) {
                                    $scope.uploadImage();
                                }
                            });
                        } else {
                            $scope.uploadImage();
                        }
                    }
                });
            }

            /**
             * Upload selected receipt's image to server
             */
            $scope.uploadImage = function() {
                //Upload attached image for manual receipt
                var imgInput = $('#receipt-image')[0];
                if (imgInput.files && imgInput.files[0]) {
                    var file        = imgInput.files[0];
                    var orgFileName = file.name;
                    //Generate an unique file name to save on s3
                    var unqFileName = $rootScope.loggedInUser.UserID + "_" +
                        Math.round(new Date().getTime() / 1000) + "_" +
                        $scope.receipt.ReceiptID +
                        "." + orgFileName.split('.').pop();

                    AwsS3Sdk.uploadManualReceipt({
                        keyName : unqFileName,
                        data    : file,
                        successCallback :
                            function() {
                                $timeout(function() {
                                    $scope.requestImageConversion(unqFileName, orgFileName);
                                });

                                //Clear upload stack
                                $('#receipt-image')[0].files = []
                            },
                        failCallback :
                            function () {
                                //Fail callback: Cancel processing status in case of upload fail
                                $scope.receiptInProcessing = false;
                            }
                    });
                }
            };

            /**
             * Load pdf in pdf viewer
             */
            $scope.loadPdfUrl = function (newUrl) {
                $timeout(function(){
                    $scope.pdfUrl = newUrl;
                    $scope.showPdfViewer = true;
                })
            };

            /**
             * Upload selected receipt's image to server
             */
            $scope.requestImageConversion = function(unqFileName , orgFileName) {
                //Receipt ID is required for inserting to db
                if ($scope.receipt.ReceiptID) {
                    Restangular.all('attachments')
                        .customPOST({
                            fileName    : unqFileName,
                            oriFileName : orgFileName,
                            receiptID   : $scope.receipt.ReceiptID
                        }, 'manual-process')
                    .then(function(response){
                        //then - success
                        AwsS3Sdk.getReceiptPdf({
                            bucket  : response.bucket,
                            keyName : response.keyName,
                            successCallback :
                                function (pdfFile) {
                                    $scope.loadPdfUrl(pdfFile.Body);
                                    $scope.receiptInProcessing = false;
                                    $('.loading-pdf-receipt').hide();
                                }
                        });
                    }, function(response){
                        //then - fail
                        //Cancel processing status in case of convert fail
                        $scope.receiptInProcessing = false;
                    });
                }
            }

            /**
             * Delete receipt image
             */
            $scope.deleteReceiptImage = function () {
              $scope.lblReceiptType = 'Manual Receipt';
              $.showMessageBox({
                content: 'Delete this image?',
                boxTitle: 'DELETE IMAGE',
                boxTitleClass: '',
                labelYes: 'Delete',
                labelNo: "Cancel",
                type: 'confirm',
                onYesAction: function () {
                  $timeout(function () {
                    $scope.isDeleteImageMR = true;
                    var imgInput = $('#receipt-image')[0];
                    if (imgInput.files && imgInput.files[0]) {
                      delete imgInput.files[0];
                    }
                    $scope.userChangedContent = true;
                    $scope.showPdfViewer = false;
                  });
                },
                onNoAction: function () {
                  return false;
                }
              });
            }

            /**
             * Support variables for direct uploading to s3
             */
            $scope.uploadQueue = [];

            $scope.resetUploadQueue = function() {
                $('#attachUploadForm')[0].reset();
                $scope.directUploads = {
                    isUploading  : false,
                    isProcessing : false,
                    uploaded     : 0,
                    total        : 0,
                    queueSizeLimit   : 9,
                    fileSizeLimit    : 10 * 1024 * 1024, //10 MB
                    triggerEle       : '',
                    entityID         : 0,
                    entityName       : ""
                }
            }

            /**
            * Function to make key name of attachment files in aws
            * Following s3 naming convention of ReceiptClub
            *
            */
            var generateAttachKeyName = function (fileName) {
                var reveseUid = $rootScope.loggedInUser.UserID.toString().split("").reverse().join("");
                var keyName   = $rootScope.loggedInUser.CountryCode.toLowerCase() + '/' +
                    reveseUid + '/' + $scope.receipt.ReceiptID +'-clip-' +
                    guid(true) + '-' + fileName;

                return keyName;
            };

          /**
           * Open file upload box for Attachment
           */
          $scope.chooseAttachment = function (eleType, entityID, eleIndex) {

            $scope.tmpEleType = eleType;
            $scope.tmpEntityID = entityID;
            $scope.tmpEleIndex = eleIndex;

            //Validate before allowing upload
            if (!$scope.receipt.MerchantName || !$scope.receipt.DigitalTotal) {
              var total = parseFloat($scope.receipt.DigitalTotal) || 0;
              var msg = '<li class="';
              msg += (!$scope.receipt.MerchantName) ? 'incorrect' : '';
              msg += '"><i class="app-icon icon-success"></i>Merchant Name is required</li>';
              msg += '<li class="';
              msg += (total <= 0) ? 'incorrect' : '';
              msg += '"><i class="app-icon icon-success"></i>Total Amount must be greater than zero</li>';

              $.showMessageBox({
                boxTitle: 'Upload Results',
                boxTitleClass: 'show utmaltergothic',
                boxClass: 'box-validation error',
                content: '<h2 class="utmaltergothic">Upload Unsuccessful</h2><p>Please make corrections to element(s) marked in red:</p><ul style="margin-left: 44px;" class="unstyled">' + msg + '</ul>',
                type: 'alert'
              });
              return false;
            }

            if ($scope.receipt.IsReported) return false;

            if ($scope.directUploads.isUploading || $scope.directUploads.isProcessing) return;

            if (!eleType) return false;

            $('#input-attachment').unbind('change').click();
            $('#input-attachment').change(function () {
              if (this.files.length > 0) {
                //Validate input before upload
                //Limit file number
                if (this.files.length > 9) {
                  $.showMessageBox({content: 'The maximum number of queue items has been reached (' + $scope.directUploads.queueSizeLimit + ').  Please select fewer files.'});
                  $scope.resetUploadQueue();
                  return false;
                }
                //Limit file type
                var fileType = ["image/jpeg", "image/gif", "image/png"];
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

                $scope.tmpFiles = this.files;

                if (!$scope.receipt.ReceiptID) {
                  //Save non-saved receipt before uploading
                  $scope.save($scope.receipt, false, false, function(res) {
                      $scope.uploadAttachmentFile($scope.tmpEleType, $scope.receipt.ReceiptID, $scope.tmpEleIndex, $scope.tmpFiles);
                  });
                } else {
                  $scope.uploadAttachmentFile($scope.tmpEleType, $scope.tmpEntityID, $scope.tmpEleIndex, $scope.tmpFiles);
                }
              }
            });
            $scope.userChangedContent = true;
          }

          $scope.uploadAttachmentFile = function(eleType, entityID, eleIndex, files){

            //Upload
            $scope.uploadQueue = new Array();
            var inputs = files;
            $scope.directUploads.entityID = entityID;

            if (eleType == "receipt") {
              $scope.directUploads.triggerEle = "#receipt-attachment";
              $scope.directUploads.entityName = "receipt";
            } else {
              $scope.directUploads.triggerEle = "#item-attachment-" + eleIndex;
              $scope.directUploads.entityName = "receipt_item";
            }

            angular.forEach(inputs, function (file, k) {
              var type = (eleType == "item") ? "i" : "r";

              var orgFileName = file.name;
              //Generate an unique file name to save on s3
              var reveseUid = $rootScope.loggedInUser.UserID.toString().split("").reverse().join("");
              var s3KeyName = generateAttachKeyName(orgFileName);

              //Create upload queue
              $scope.uploadQueue.push({
                orgName: orgFileName,
                unqName: s3KeyName,
                fileData: file,
                progress: 0
              });
              if (k == inputs.length - 1) $scope.uploadAttachment(eleIndex);

            });

            $scope.userChangedContent = true;
          }

            /**
             * Upload selected attachments to server
             */
            $scope.uploadAttachment = function(eleIndex) {
                var eleQueueWrapper = '#upload-attach-queue';
                $(eleQueueWrapper).empty();

                $scope.directUploads.isUploading = true;
                $scope.directUploads.total       = $scope.uploadQueue.length;

                //$scope.doPreQueueUploadWorks();

                angular.forEach($scope.uploadQueue, function (file, k) {
                    //Create visual progress bar
                    var seedId = "attachment-scale-upload-" + k;
                    var spanFileSize = '( ' + (file.fileData.size / 1024 / 1024).toFixed(1) + ' MB)';
                    var seed = '<div class="uploadifive-queue-item" id="' + seedId + '">' +
                        '<div>' +
                            '<span class="file-name text-ellipsis">' + file.orgName + '</span>' +
                            '<span class="file-size">' + spanFileSize + '</span>' +
                            '<span class="file-percent" ng-bind="uploadQueue[' + k + '].progress + \'%\'"></span></div>' +
                        '<div class="progress">' +
                            '<div class="progress-bar" style="width: 0%;"></div></div></div>';
                    $(eleQueueWrapper).append($compile(seed)($scope));
                    $(eleQueueWrapper).fadeIn(2000);

                    //Start upload
                    AwsS3Sdk.uploadAttachment ({
                        keyName : file.unqName,
                        data    : file.fileData,
                        progressCallback :
                            function(percent) {
                                //Progress callback
                                $timeout(function() {
                                    $scope.uploadQueue[k].progress = percent;
                                    $('#' + seedId + ' .progress-bar' ).width(percent + '%');
                                });
                            },
                        successCallback:
                            function () {
                                //Success callback
                                $timeout(function() {
                                    $('#' + seedId).fadeOut(2000).remove();
                                    $scope.directUploads.uploaded++;
                                    $scope.directUploads.success++;
                                    $scope.directUploads.isProcessing = true;
                                    //$scope.doProgressUploadWorks();

                                    //Request save db record
                                    $scope.saveAttachFile(file.unqName, file.orgName, $scope.directUploads.entityID,$scope.directUploads.entityName, eleIndex);

                                    if ($scope.directUploads.uploaded == $scope.directUploads.total) {
                                        //$scope.doPostQueueUploadWorks();
                                        $($scope.directUploads.triggerEle).click();
                                        $scope.resetUploadQueue();
                                    }
                            });
                        }
                    });
                })
            };

            /**
             * Upload selected receipt's image to server
             */
            $scope.saveAttachFile = function(unqFileName , orgFileName, entityID, entityName, eleIndex) {
                //Receipt or Item ID is required for inserting to db
                if (entityID) {
                    var params = {
                        fileName   : unqFileName,
                        orgName    : orgFileName,
                        entityID   : entityID,
                        entityName : entityName
                    };
                    Restangular.all('attachments').customPOST(params, 'save-file').then(function(response){
                        //TODO: callback to show uploaded file to attach popover
                        $timeout (function(){
                            if (entityName == 'receipt') {
                                $scope.receipt.Attachments.push(response);
                            } else {
                                $scope.receipt.Items[eleIndex].Attachments.push(response);
                            }
                        });
                    }, function(response){
                        //Cancel processing status in case of convert fail
                        $scope.receiptInProcessing = false;
                    });
                }
            }

            /**
             * Set instant receipt status to validated
             * @param receipt object
             *
             * When users click on OK button: system will set the status as "Validated", except following cases, system will show a dialog :
             * + If receipt is unrecognized, dialog shows “Cannot Validate Unrecognized Receipt”
             * + Otherwise if there are other errors, say: “To validate receipt the following error(s) must be corrected:”
             *  - Total Amount cannot be zero
             *  - Merchant Unrecognized
             *  - No Item(s) have been categorized
             * + Warning messages: (does not prevent Validation)
             *  - Item total does not match subtotal
             *  - Please check purchase date (it is current date by default)
             *  - Purchase date is greater than 12 months
             */
            $scope.setInstantReceiptStatus = function (receipt) {
                $scope.receiptInSaving = true;
                $scope.txtLoading      = 'Validating...';
                $scope.responseMessage = [];
                // Don't validate unrecognized receipt
                if (receipt.VerifyStatus == 3) {
                    $.showMessageBox({
                        content: 'Cannot Validate Unrecognized Receipt.',
                        type: 'alert'
                    });

                    $scope.receiptInSaving = false;
                    $scope.txtLoading      = '';
                    return false;
                }
                var msg = '';
                var hasError = false;
                var total = parseFloat(receipt.DigitalTotal) || 0;
                var subtotal = parseFloat(receipt.Subtotal) || 0;
                var tax = parseFloat(receipt.Tax) || 0;
                var extraValue = parseFloat(receipt.ExtraValue) || 0;
                var subtotalPlusTax = subtotal + tax + extraValue;

                msg += '<li class="';
                if (receipt.MerchantName == '' || receipt.MerchantName == 'Merchant Unrecognized' || (typeof receipt.MerchantName == 'undefined')) {
                    msg += 'incorrect';
                    hasError = true;
                }

                msg += '"><i class="app-icon icon-success"></i>Merchant Name is required</li>';

                msg += '<li class="';
                if (total <= 0) {
                    msg += 'incorrect';
                    hasError = true;
                }
                msg += '"><i class="app-icon icon-success"></i>Total Amount must be greater than zero</li>';

                msg += '<li class="';
                if ((total.toFixed(2) != subtotalPlusTax.toFixed(2)) || (total <= 0)) {
                    msg += 'incorrect';
                    hasError = true;
                }
                msg += '"><i class="app-icon icon-success"></i>Total Amount must equal Subtotal plus Tax</li>';

                var hasItemCategorized = false;
                console.log(receipt.Items);
                for (var i in receipt.Items) {
                    if (i == 0 && receipt.HasCombinedItem == 1 && !receipt.Items[0].CategoryID && receipt.Items[0].IsJoined == 0) {
                        hasItemCategorized = true;
                        break;
                    }

                    if (angular.isDefined(receipt.Items[i].CategoryID) && receipt.Items[i].CategoryID != 0 && receipt.Items[i].CategoryApp == 'personal_expense' && receipt.Items[i].CategoryID != null) {
                        hasItemCategorized = true;
                        break;
                    }

                    if (angular.isDefined(receipt.Items[i].CategoryID) && receipt.Items[i].CategoryID != 0 && receipt.Items[i].CategoryApp == 'education_expense' && receipt.Items[i].CategoryID != null) {
                        hasItemCategorized = true;
                        break;
                    }

                    if (angular.isDefined(receipt.Items[i].CategoryID) && receipt.Items[i].CategoryID != 0 && receipt.Items[i].CategoryApp == 'business_expense' && receipt.Items[i].CategoryID != null) {
                        hasItemCategorized = true;
                        break;
                    }

                    if (angular.isDefined(receipt.Items[i].CategoryID) && receipt.Items[i].CategoryID != 0 && receipt.Items[i].CategoryApp == 'personal_assets' && receipt.Items[i].CategoryID != null) {
                        hasItemCategorized = true;
                        break;
                    }

                    if (angular.isDefined(receipt.Items[i].CategoryID) && receipt.Items[i].CategoryID != 0 && receipt.Items[i].CategoryApp == 'business_assets' && receipt.Items[i].CategoryID != null) {
                        hasItemCategorized = true;
                        break;
                    }

                    if (angular.isDefined(receipt.Items[i].CategoryID) && receipt.Items[i].CategoryID != 0 && receipt.Items[i].TripID > 0) {
                        hasItemCategorized = true;
                        break;
                    }
                }

                // Check has at least 1 items in receipt
                var hasItemInReceipt = false;
                for (var i in receipt.Items) {
                    if (receipt.Items[i].Name != '' && receipt.Items[i].Amount != '') {
                        hasItemInReceipt = true;
                        break
                    }
                }

                msg += '<li class="';
                if (!hasItemCategorized || !hasItemInReceipt) {
                    msg += 'incorrect';
                    hasError = true;
                }
                if (!hasItemInReceipt) {
                    $scope.responseMessage = ['Please add at least 1 item (with item name and amount) for receipt'];
                }
                msg += '"><i class="app-icon icon-success"></i>Item(s) or Combined Item must be categorized</li>';


                var boxClass = hasError ? 'box-validation error' : 'box-validation success';

                if (hasError) {
                    $.showMessageBox({
                        boxTitle: 'Validation Results',
                        boxTitleClass: 'show utmaltergothic',
                        boxClass: 'box-validation error',
                        content: '<h2 class="utmaltergothic">Validation Unsuccessful</h2><p>Please make corrections to element(s) marked in red:</p><ul style="margin-left: 44px;" class="unstyled">' + msg + '</ul>',
                        type: 'alert'
                    });
                    $scope.receiptInSaving = false;
                    $scope.txtLoading      = '';
                    return false;
                }

                receipt.VerifyStatus = 2;
                //$scope.requireUserConvertCurrency = false;
                $scope.save(receipt, false);

                $timeout(function(){
                    if ($scope.showConfirmConvertMessage) {
//                        $scope.convertCurrency();
                    }
                }, 3000);

            }

            //$scope.$watch('userChangedContent', function(newVal, oldVal){
            //  if(newVal){
            //
            //  }
            //})
            /*
             * Merchant change (empty or unrecognized)
             * No item(s) have been categorized
             */
            $scope.$watchCollection('receipt', function (receipt, oldVal) {

                var hasError = false;
                if (receipt && angular.isDefined($scope.receipt.OriginalVerifyStatus) && $scope.receipt.OriginalVerifyStatus == 2) {
                    if (typeof oldVal !== 'undefined' && !$scope.receipt.IsRecentlyConverted) {
                        if (receipt.Subtotal != oldVal.Subtotal || receipt.Tax != oldVal.Tax
                            || receipt.ExtraValue != oldVal.ExtraValue || receipt.DigitalTotal != oldVal.DigitalTotal) {
                            hasError = true;
                        }
                    }
                    if ($scope.receipt.MerchantName == '' || $scope.receipt.MerchantName == 'Merchant Unrecognized') {
                        hasError = true;
                    }
                    var hasItemCategorized = false;
                    for (var i in $scope.receipt.Items) {
                        if (i == 0 && $scope.receipt.HasCombinedItem == 1 && !$scope.receipt.Items[0].CategoryID && $scope.receipt.Items[0].IsJoined == 0) {
                            break;
                        }

                        if (angular.isDefined($scope.receipt.Items[i].CategoryID) && $scope.receipt.Items[i].CategoryID != 0 && $scope.receipt.Items[i].CategoryID != null) {
                            hasItemCategorized = true;
                            break;
                        }
                    }

                    if (!hasItemCategorized) {
                        hasError = true;
                    }
                } else if (receipt) {
                    if (typeof oldVal !== 'undefined' && !$scope.receipt.IsRecentlyConverted) {
                        if (receipt.Subtotal != oldVal.Subtotal || receipt.Tax != oldVal.Tax
                            || receipt.ExtraValue != oldVal.ExtraValue || receipt.DigitalTotal != oldVal.DigitalTotal) {
                            hasError = true;
                        }
                    }
                }

                if (hasError) {
                    $scope.receipt.VerifyStatus = 1;
                }

                if (receipt) {
                    $scope.receipt.IsRecentlyConverted = false;
                }
            }, true);


            /**
             * Save, close receipt detail form and go to the receipt box
             */

            $scope.goToReceiptBox = function (receipt) {

                $timeout(function(){
                   $rootScope.inAppScreen == 'RECEIPT_BOX';
                });

                var goodToGo = function () {
                    $scope.userChangedContent = false;

                    if($scope.categoryInfo){
                        $rootScope.$emit('OPEN_RB_ADD_ITEMS', $scope.categoryInfo, $scope.tripInfo);
                        jQuery('.page-app').css('display', 'none');
                        jQuery('#top-header').removeClass('hide').addClass('show');
                        jQuery('#sidebar-right').removeClass('hide').addClass('show');
                        $('#receiptbox-wrapper').show();
                    }else{
                        $scope.menuOpenRDFrom = 'menu-receiptbox';
                        $scope.openRDFrom = 'receiptbox-wrapper';
                        $('#rb-back-to-app').removeClass('show').addClass('hide');
                        $scope.close();
                    }
                };

                //Ask to save changes of receipt
                if ($scope.userChangedContent) {
                    //Use confirmation box to ask before save
                    $scope.confirmToSave(function yesAction () {
                        $scope.save(receipt, false);

                        $timeout(function(){
                            goodToGo();
                        });
                    }, function noAction () {
                        goodToGo();
                    });
                } else {
                    goodToGo();
                }
            };

            $scope.checkItemSelected = function (item) {
                if (item.IsChecked) {
                    $scope.noItemSelected = false;
                    var tmp = true;
                    for (var i = 0; i < $scope.receipt.Items.length; i++) {
                        if(item.ExpensePeriod == $scope.receipt.Items[i].ExpensePeriod){
                          $timeout(function(){
                            $scope.receipt.Items[i].CategoryApp = $rootScope.defaultApp;
                            $scope.receipt.Items[i].CategoryAppAbbr = $rootScope.defaultAppAbbr;
                          });
                        }
                        if($scope.receipt.Items[i].Name && $scope.receipt.Items[i].Amount){
                            $scope.receipt.Items[i].CategoryApp = $rootScope.defaultApp;
                            $scope.receipt.Items[i].CategoryAppAbbr = $rootScope.defaultAppAbbr;
                            if (!$scope.receipt.Items[i].IsChecked && !$scope.receipt.Items[i].IsJoined) {
                                tmp = false;
                                break;
                            }
                        }
                    }
                    $scope.itemIsCheckAll = tmp;
                    if($scope.itemIsCheckAll){

                    }else{
                        if($scope.tmpCategoryType != 'all_item'){
                            $timeout(function(){
                                $('tr.selected_item_row td:first input[type="radio"]').click();
                            });
                            $timeout(function(){
                                $('tr.selected_item_row td:first input[type="radio"]').click();
                            });
                        }
                    }

                } else{
                    $scope.noItemSelected = true;
                    $scope.itemIsCheckAll = false;
                    for (var i = 0; i < $scope.receipt.Items.length; i++) {
                        if ($scope.receipt.Items[i].IsChecked && !$scope.receipt.Items[i].IsJoined) {
                            $scope.noItemSelected = false;
                            break;
                        }
                    }
                    if($scope.noItemSelected){
                        $timeout(function(){
                            $('tr.footer-top td:first input[type="radio"]').click();
                        });
                        $timeout(function(){
                            $('tr.footer-top td:first input[type="radio"]').click();
                        });
                    }else{
                        $timeout(function(){
                            $('tr.selected_item_row td:first input[type="radio"]').click();
                        });
                        $timeout(function(){
                            $('tr.selected_item_row td:first input[type="radio"]').click();
                        });
                    }
                }
            }

            /**
             * prepare data for categories to build them together as a tree in the categories popup
             */
            $scope.prepareCategoryTree = function (categories, selectedCatID) {
                var tmp, catGroup = [], len = 0, hasCurrentCat = 0, parentKey;
                //empty the tree first because it is a shared variable
                $scope.categoryTree = [];
                angular.forEach(categories, function (cat, k) {
                    tmp = angular.copy(cat);  //clone data to keep original data
                    tmp.check = false;
                    tmp.parentCollapse = true;
                    tmp.masterCollapse = true;
                    tmp.collapseDisabled = false;
                    tmp.expand = false;
                    tmp.CategoryName = cat.Name;
                    tmp.Type = 'category';
                    tmp.class = 'cat-lv' + (cat.Depth + 1);
                    tmp.current = false;
                    if (cat.CategoryID == selectedCatID) {
                        tmp.current = true;
                        hasCurrentCat = cat.Depth;
                    }
                    if (cat.Depth == 0) {
                        if (hasCurrentCat > 0) {
                            for (var i = 0; i < catGroup.length; i++) {
                                if (i == 0) {
                                    catGroup[i].expand = true;
                                }

                                if (catGroup[i].Depth == 1) {
                                    catGroup[i].parentCollapse = false;
                                    catGroup[i].masterCollapse = false;
                                    if (hasCurrentCat == 2) {
                                        parentKey = i;
                                    }
                                }

                                if (hasCurrentCat == 2) {
                                    if (catGroup[i].Depth == 2 && catGroup[i].current) {
                                        catGroup[parentKey].expand = true;

                                        for (var j = (parentKey + 1); ; j++) {
                                            catGroup[j].parentCollapse = false;
                                            catGroup[j].masterCollapse = false;
                                            if (catGroup[j].Depth == 1) {
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        //Push the cat group to category tree, each group is a branch
                        if (catGroup.length > 0) {
                            if (catGroup[len - 1].Depth == 1) {
                                catGroup[len - 1].collapseDisabled = true;
                            }
                            $scope.categoryTree.push(catGroup);
                        }

                        //Empty the cat group, so we will start to add a new branch
                        catGroup = [];
                        hasCurrentCat = 0;
                        len = 0;
                        tmp.display = true;
                        tmp.parentCollapse = false;
                        tmp.masterCollapse = false;
                        if (hasCurrentCat > 0) {
                            hasCurrentCat = 0;
                        }
                        catGroup.push(tmp);
                        len++;
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
                        tmp.collapseDisabled = true;
                        catGroup.push(tmp);
                        len++;
                    }
                });

                //Add the last catGroup to array
                if (catGroup.length > 0) {
                    if (catGroup[len - 1].Depth == 1) {
                        catGroup[len - 1].collapseDisabled = true;
                    }
                    $scope.categoryTree.push(catGroup);
                }
            }
            /*
            * Watch for select categorize
            * */
            $scope.$watch('noItemSelected', function (newValue, oldValue, scope) {
                if (newValue) {
                    if($scope.tmpCategoryType == 'selected_item'){
                        $timeout(function(){
                            $('tr.footer-top td:first input[type="radio"]').click();
                        });
                        $timeout(function(){
                            $('tr.footer-top td:first input[type="radio"]').click();
                        });
                    }
                }
            });
            $scope.$watch('receipt.DigitalTotal', function (newValue, oldValue, scope) {
                if (newValue) {
                    $scope.compareWithSumOfSubtotal();
                }
            });
            $scope.$watch('itemIsCheckAll', function (newValue, oldValue, scope) {
                if (newValue) {
                    $timeout(function(){
                        $('tr.all_item_row td:first input[type="radio"]').click();
                    });
                    $timeout(function(){
                        $('tr.all_item_row td:first input[type="radio"]').click();
                    });
                }
            });

            $scope.popupEmailInfo = function(){
                $('#infor-email').toggle();
            }

            /*
            * ********************************************************************
            * */
             $scope.setExpensePeriodForAllItems = function (app) {
                //console.debug('App = %s', app);
                if (app == 'travel_expense' && angular.isDefined($scope.tripInfo)) {
                    $scope.receipt.Reference = $scope.tripInfo.reference;
                }

                angular.forEach($scope.receipt.Items, function (item, key) {
                    if (key && item.Name != '' && item.Amount != '') {
                        item.CategoryApp = app;
                        item.forceCategory = true;

                        if (app == 'travel_expense' && angular.isDefined($scope.tripInfo)) {
                            item.Reference = $scope.tripInfo.reference;
                        }
                    }
                });
            }

          $(document).bind('keypress', function(event) {

            if( event.which === 78 && event.shiftKey ) {
              $scope.openRdById($scope.nextItem, 'next');
            }else if(event.which === 80 && event.shiftKey){
              $scope.openRdById($scope.prevItem, 'previous');
            }
          });

        }]);

function alignAppCatDropdown() {
    if (jQuery('#rd-items-table-head th.app_head').width()) {
        jQuery('#categorize-section th.app-col').width(jQuery('#rd-items-table-head th.app_head').width());
    }

    if (jQuery('#rd-items-table-head th.item_name_head').width() && jQuery('#rd-items-table-head th.amount_head').width()) {
        jQuery('#rd-items-table-wrapper .total-container').width(jQuery('#rd-items-table-head th.item_name_head').width() + jQuery('#rd-items-table-head th.amount_head').width());
    }

    if (jQuery('#rd-items-table-head th.cat_head').width()) {
        jQuery('#categorize-section th.cat-col').width(jQuery('#rd-items-table-head th.cat_head').width());
    }

    if (jQuery('#rd-items-table-head th.period_head').width()) {
        jQuery('#categorize-section th.period-col').width(jQuery('#rd-items-table-head th.period_head').width());
    }

    jQuery('#categorize-section td.cat-col .btn-group > .btn-label').width(jQuery('#rd-items-table tr:first td.cat_col .btn-group > .btn').width() - 3);
    if ($(window).width() <= 1366) {
        jQuery('#rd-items-table tr td.cat_col .btn-group .btn.category-name').css('margin-left', '3px');
    } else {

    }

    // Resize #categorize-section height
    var catSectionHeight = jQuery('#categorize-section table').height();
    jQuery('#categorize-section').height(catSectionHeight);
    jQuery('#categorize-section .ads-section').height(catSectionHeight);

    // Re-align paper clip & share icon
    var paperClipPosition = $('#rd-items-table-head th.attachment_head i').offset();
    var shareIconPosition = $('#rd-items-table td.share_col i').eq(0).offset();

    if (paperClipPosition) {
        $('#rd-items-table .attachment-popover').offset({left: paperClipPosition.left});
    }

    if (shareIconPosition) {
        $('#rd-items-table-head th.share_head i').offset({left: shareIconPosition.left});
    }

}
