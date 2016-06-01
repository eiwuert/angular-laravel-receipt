rciSpaApp.directive('zoom', function() {
    return function(scope, element, attrs) {
        if ($(element).hasClass('zoom-image') || $(element).hasClass('rd-ori-content')) {
            var options = {
                wrapperId: 'receipt-image-wrapper',
                printable: true,
                savable: true,
                draggable: false,
                closeButtons: '#rd-close',
                closeButtonOnCenter: false,
                event: $(element).hasClass('rd-ori-content') ? 'click' : 'hover'
            };

            $(element).helloZoom(options);
            $('*', $(element)).helloZoom(options);
        }
        if ($(element).hasClass('zoom-raw-text')) {
            $(element).helloZoom({
                wrapperId: 'receipt-raw-wrapper',
                savable: false,
                event: 'click',
                draggable: false,
                mailable: false,
                type: 'html',
                title: '<div class="raw-text-header"><h4>Optical Character Recognition</h4><h5>Powered by ROCR</h5></div>',
                headerClass: "raw-text-zoom-header"
            });
        }
        if ($(element).hasClass('zoom-normal')) {
            $(element).helloZoom({
                wrapperId: $(element).attr('data-target'),
                printable: true,
                savable: true,
                draggable: false,
                type: 'html'
            });
        }
        if ($(element).hasClass('zoom-manual-img')) {
            $(element).helloZoom({
                wrapperId: 'receipt-manual-img-wrapper',
                printable: true,
                savable: true,
                event: 'click',
                closeButtons: '#rd-close'
            });
        }
        if ($(element).hasClass('zoom-text')) {
            $('.icon-magnifier-grey').helloZoom({
                type: 'html',
                wrapperId: 'raw-text'
            });
        }

        if ($(element).hasClass('zoom-invoice-image')) {
            var options = {
                wrapperId: 'invoice-image-wrapper',
                printable: true,
                savable: true,
                draggable: false,
                closeButtons: '#rd-close',
                event: 'click',
                extraClass: 'invoice-receipt-zoom',
                closeButtonOnCenter: false,
                event: $(element).hasClass('rd-ori-content') ? 'click' : 'hover'
            };
            $(element).helloZoom(options);
            $('*', $(element)).helloZoom(options);
        }
    }
});
rciSpaApp.directive('detectPositionImage', function() {
    return {
        restrict: 'A',
        link : function (scope, element, attrs) {
            element.find('.images-zoom').on('click', function (e) {
                if(!element.hasClass('HelloZoomImage'))
                {
                    var height = $(this).height();
                    var offset = $(this).offset();
                    var positionx = e.clientY - offset.top;
                    setTimeout(function(){
                        var heightNew = $('.HelloZoomImageWrapper img').height();
                        var offsetNew = parseInt(Math.round(heightNew * positionx / height));
                        var heightWrap = $('.HelloZoomImageWrapper').height();
                        if(heightWrap < heightNew)
                        {
                            $('.HelloZoomImageWrapper').scrollTop(offsetNew - 150);
                        }
                    },50);
                }
            });
        }
    }
});
rciSpaApp.directive('textBlock', function() {
    return {
        restrict: 'E',
        scope: {
            label: '@label',
            length: '@length',
            name: '@name',
            id: '@wrapperId',
            readonly: '@readonly',
            value: '@value'
        },
        template: '<div class="form-wrapper wrap-text" id="{{id}}">' +
                '<label>{{label}}:</label>' +
                '<input type="text" class="form-text {{length}} {{readonly}}" name="{{name}}" {{readonly}} value="value" />' +
                '</div>'
    };
});

rciSpaApp.directive('rdResizeHeight', function() {
    return function(scope, element, attrs) {
        $(element).resizeHeight('receipt_detail');
        $(window).resize(function() {
            $(element).resizeHeight('receipt_detail');
        });
    }
});

rciSpaApp.directive('filePopover', function($compile) {
    return {
        restrict: 'E',
        scope: {
            wrapperId: '@wrapperId',
            attachments: '=attachments'
        },
        replace: true,
        template: '<a id="{{wrapperId}}" class="app-icon icon-attachment ng-class:{\'no-file\': !attachments.length}" ng-click="$event.stopPropagation(); showFilePopover(wrapperId);"><span ng-show="attachments.length">{{ attachments.length }}</span></a>',
        //template: '<a id="{{wrapperId}}" class="app-icon icon-attachment ng-class:{\'no-file\': !attachments.length}"><span ng-show="attachments.length">{{ attachments.length }}</span></a>',
        link: function(scope, element, attrs) {
            scope.showFilePopover = function(wrapperId) {
                var offset = jQuery('#' + wrapperId).position();
                jQuery('.list-attachment').css('display', 'none');
                jQuery('#' + wrapperId + '-popover').css({top: offset.top - 20 + 'px', left: offset.left - 265 + 'px', 'display': 'block'});

                $('body').bind('click', function(e) {
                    $('.list-attachment').fadeOut();
                });

                $('.list-attachment').on('click', function(e) {
                    e.stopPropagation();
                });

            }
        }
    }
});

rciSpaApp.directive('hidePopover', function() {
    return function(scope, element, attrs) {

    }
});

/**
 * Directive of dropdown App menu for receipt form in RD screen
 */
rciSpaApp.directive('rdDropdownReceiptApp', function($timeout) {
    return {
        scope: {
            appMenu: "=appMenu",
            receipt: "=currReceipt",
            toDisable: "@toDisable"
        },
        restrict: 'E',
        replace: true,
        require: '^ReceiptDetailCtrl',
        controller: function($scope) {
            $scope.selectedApp = '';
            $timeout(function() {
                if (angular.isObject($scope.receipt) && !$scope.receipt.hasOwnProperty('CategoryAppAbbr')) {
                    $scope.receipt.CategoryAppAbbr = '';
                }
            }, 1000);
            $scope.$watch('toDisable', function(newValue, oldValue) {
                if (newValue == 'true' && oldValue == 'false') {
                    $scope.receipt.CategoryAppAbbr = '';
                    $scope.receipt.categorizeApp = '';
                    $scope.selectedApp = '';
                }
            });
            $scope.updateCategoryMenu = function(appMName, appAName) {
                $scope.receipt.categorizeApp = appMName;
                $scope.receipt.CategoryAppAbbr = appAName;
                $scope.selectedApp = appAName;
                $scope.$parent.loadCategory($scope.receipt, $scope.receipt.categorizeApp, 'reload');
                $scope.receipt.CategorizeStatus = 0;
            }
        },
        template: '<div class="btn-group">\
                <button class="btn" ng-disabled="toDisable==\'true\'">{{selectedApp}}&nbsp;</button>\
                <button class="btn dropdown-toggle" ng-disabled="toDisable==\'true\'" data-toggle="dropdown">\
                    <span class="caret"></span>\
                </button>\
                <ul class="dropdown-menu limit-width" role="menu" aria-labelledby="dropdownMenu">\
                    <li><a tabindex="-1" href="" ng-repeat="app in appMenu" ng-click="updateCategoryMenu(app.App.MachineName, app.App.AbbrName);">{{app.App.AbbrName}}</a></li>\
                </ul>\
            </div>'
    }
});
/**
 * Directive of dropdown Category menu for receipt form in RD screen
 */
rciSpaApp.directive('rdDropdownReceiptCat', function($timeout) {
    return {
        scope: {
            catMenu: "=catMenu",
            receipt: "=currReceipt",
            toDisable: "@toDisable"
        },
        restrict: 'E',
        replace: true,
        require: '^ReceiptDetailCtrl',
        controller: function($scope) {
            $scope.selectedCat = '';
            $timeout(function() {
                if (angular.isObject($scope.receipt) && !$scope.receipt.hasOwnProperty('CategoryName')) {
                    $scope.receipt.CategoryName = '';
                }
            }, 1000);
            $scope.$watch('toDisable', function(newValue, oldValue) {
                if (newValue == 'true' && oldValue == 'false') {
                    $scope.receipt.CategoryName = '';
                    $scope.receipt.categorizeCat = 0;
                    $scope.selectedCat = '';
                }
            });
            $scope.performSelectCat = function(cat) {
                $scope.receipt.CategoryName = cat.Name;
                $scope.selectedCat = cat.Name;
                $scope.receipt.categorizeCat = cat.CategoryID;
                $scope.receipt.CategorizeStatus = 2;
            }
            $scope.$watch('receipt.categorizeCat', function(newValue, oldValue) {
                if (newValue == 0) {
                    $scope.receipt.CategoryName = '';
                    $scope.selectedCat = '';
                }
            });
        },
        template: '<div class="btn-group">\
                <button class="btn" ng-disabled="toDisable==\'true\'">{{selectedCat}}&nbsp;</button>\
                <button class="btn dropdown-toggle" data-toggle="dropdown" ng-disabled="toDisable==\'true\'">\
                    <span class="caret"></span>\
                </button>\
                <ul class="dropdown-menu limit-width" role="menu" aria-labelledby="dropdownMenu">\
                    <li class="dropdown-submenu" ng-class="{\'no-submenu\': catGrandpa.Childs.length==0 }" ng-repeat="catGrandpa in catMenu">\
                        <a tabindex="-1" href="" ng-click="performSelectCat(catGrandpa)">{{catGrandpa.Name}}</a>\
                        <ul class="dropdown-menu">\
                            <li class="dropdown-submenu pull-left" ng-class="{\'no-submenu\': catParent.Childs.length==0 }" ng-repeat="catParent in catGrandpa.Childs">\
                                <a tabindex="-1" href="" ng-click="performSelectCat(catParent)">{{catParent.Name}}</a>\
                                <ul class="dropdown-menu">\
                                    <li><a tabindex="-1" href=""  ng-repeat="catChild in catParent.Childs" ng-click="performSelectCat(catChild)">{{catChild.Name}}</a></li>\
                                </ul>\
                            </li>\
                        </ul>\
                    </li>\
                </ul>\
            </div>'
    }
});
/**
 * Directive of dropdown App menu for item form in RD screen
 */
rciSpaApp.directive('rdDropdownItemApp', function($timeout) {
    return {
        scope: {
            appMenu: "=appMenu",
            item: "=currItem",
            receipt: "=currReceipt",
            toDisable: "@toDisable"
        },
        restrict: 'E',
        replace: true,
        require: '^ReceiptDetailCtrl',
        controller: function($scope) {
            $scope.selectedApp = '';
            $timeout(function() {
                if (!$scope.item.hasOwnProperty('CategoryAppAbbr')) {
                    $scope.item.CategoryAppAbbr = '';
                }
            }, 1000);
            $scope.resetApp = function() {
                $scope.item.CategoryAppAbbr = '';
                $scope.item.CategoryApp = '';
                $scope.selectedApp = '';
                $scope.item.CategorizeStatus = 0;
            }
            $scope.loadBindedApp = function(app) {
                if ($scope.item.CategoryApp == app.App.MachineName) {
                    $scope.item.CategoryAppAbbr = app.App.AbbrName;
                    $scope.selectedApp = app.App.AbbrName;
                }
            }
            $scope.updateCategoryMenu = function(appMName, appAName) {
                $scope.item.CategoryApp = appMName;
                $scope.item.CategoryAppAbbr = appAName;
                $scope.selectedApp = appAName;
                $scope.$parent.loadCategory($scope.item, $scope.item.CategoryApp, 'reload');
                $scope.item.CategorizeStatus = 0;
            }
            $scope.$watch('item.CategorizeStatus', function(newValue, oldValue) {
                if (newValue != oldValue) {
                    if ($scope.receipt.categorizeApp != '') {
                        if ($scope.receipt.categorizeMethod == 'selected_item') {
                            if ($scope.item.IsChecked) {
                                if ($scope.item.CategoryApp != $scope.receipt.categorizeApp) {
                                    $scope.onWatchUpdate($scope.receipt.categorizeApp, 'no-reload-cat');
                                }
                            }
                        }
                    }
                }
            })
            $scope.$watch('receipt.categorizeApp', function(newValue, oldValue) {
                if (typeof newValue != 'undefined' && newValue != oldValue && newValue != '') {
                    if ($scope.toDisable == 'false') {
                        if ($scope.receipt.categorizeMethod == 'selected_item') {
                            if ($scope.item.IsChecked == true) {
                                $scope.onWatchUpdate(newValue);
                            }
                        } else {
                            $scope.onWatchUpdate(newValue);
                        }
                    }
                }
            })
            $scope.onWatchUpdate = function(newValue, option) {
                $scope.item.CategoryApp = newValue;
                $scope.item.CategoryAppAbbr = $scope.receipt.CategoryAppAbbr;
                $scope.selectedApp = $scope.receipt.CategoryAppAbbr;
                if (option != 'no-reload-cat') {
                    $scope.$parent.loadCategory($scope.item, $scope.item.CategoryApp);
                }
            }
        },
        template: '<div class="btn-group">\
                <button class="btn" ng-disabled="toDisable==\'true\'">{{selectedApp}}&nbsp;</button>\
                <button class="btn dropdown-toggle" ng-disabled="toDisable==\'true\'" data-toggle="dropdown">\
                    <span class="caret"></span>\
                </button>\
                <ul class="dropdown-menu limit-width" role="menu" aria-labelledby="dropdownMenu">\
                    <li><a tabindex="-1" href="" ng-repeat="app in appMenu" ng-click="updateCategoryMenu(app.App.MachineName, app.App.AbbrName);" ng-init="loadBindedApp(app)">{{app.App.AbbrName}}</a></li>\
                </ul>\
            </div>'
    }
});

/**
 * Directive of dropdown Category menu for item form in RD screen
 */
rciSpaApp.directive('rdDropdownItemCat', function($timeout) {
    return {
        scope: {
            catMenu: "=catMenu",
            item: "=currItem",
            receipt: "=currReceipt",
            toDisable: "@toDisable"
        },
        restrict: 'E',
        replace: true,
        require: '^ReceiptDetailCtrl',
        controller: function($scope) {
            $scope.selectedCat = '';
            $scope.triggerLoadCategory = function() {
                if ($scope.item.CategoryApp) {
                    $scope.$parent.loadCategory($scope.item, $scope.item.CategoryApp);
                }
            }
            $scope.resetCat = function() {
                $scope.item.CategoryName = '';
                $scope.item.CategoryID = 0;
                $scope.selectedCat = '';
                $scope.item.CategorizeStatus = 0;
            }
            if ($scope.item.CategoryID != 0) {
                $scope.selectedCat = $scope.item.CategoryName;
            }
            $scope.performSelectCat = function(cat) {
                $scope.item.CategoryName = cat.Name;
                $scope.selectedCat = cat.Name;
                $scope.item.CategoryID = cat.CategoryID;
                $scope.item.CategorizeStatus = 2;
            }
            $scope.$watch('item.CategoryID', function(newValue, oldValue) {
                if (newValue == 0 && newValue != oldValue) {
                    $scope.resetCat();
                }
            });
            $scope.$watch('receipt.categorizeCat', function(newValue, oldValue) {
                if (typeof newValue != 'undefined' && newValue != oldValue && $scope.receipt.categorizeApp != '') {
                    if ($scope.toDisable == 'false') {
                        if ($scope.receipt.categorizeMethod == 'selected_item') {
                            if ($scope.item.IsChecked == true) {
                                $scope.onWatchUpdate(newValue);
                            }
                        } else {
                            $scope.onWatchUpdate(newValue);
                        }
                    }
                }
            })
            $scope.onWatchUpdate = function(newValue) {
                $scope.item.CategoryID = newValue;
                $scope.item.CategoryName = $scope.receipt.CategoryName;
                $scope.selectedCat = $scope.receipt.CategoryName;
            }
        },
        template: '<div class="btn-group">\
                <button class="btn" ng-disabled="toDisable==\'true\'">{{selectedCat}}&nbsp;</button>\
                <button class="btn dropdown-toggle" data-toggle="dropdown" ng-disabled="toDisable==\'true\'" ng-click="triggerLoadCategory()">\
                    <span class="caret"></span>\
                </button>\
                <ul class="dropdown-menu limit-width" role="menu" aria-labelledby="dropdownMenu">\
                    <li class="dropdown-submenu" ng-class="{\'no-submenu\': catGrandpa.Childs.length==0 }" ng-repeat="catGrandpa in catMenu">\
                        <a tabindex="-1" href="" ng-click="performSelectCat(catGrandpa)">{{catGrandpa.Name}}</a>\
                        <ul class="dropdown-menu">\
                            <li class="dropdown-submenu pull-left" ng-class="{\'no-submenu\': catParent.Childs.length==0 }" ng-repeat="catParent in catGrandpa.Childs">\
                                <a tabindex="-1" href="" ng-click="performSelectCat(catParent)">{{catParent.Name}}</a>\
                                <ul class="dropdown-menu">\
                                    <li><a tabindex="-1" href=""  ng-repeat="catChild in catParent.Childs" ng-click="performSelectCat(catChild)">{{catChild.Name}}</a></li>\
                                </ul>\
                            </li>\
                        </ul>\
                    </li>\
                </ul>\
            </div>'
    }
});

rciSpaApp.directive('attachmentPopup', function() {
    return {
        restrict: 'A',
        scope: {id: '@'},
        link: function(scope, element, attrs) {
            element.bind('click', function(e) {
                e.stopPropagation();
                var offset = element.position();
                $('.list-attachment').css('display', 'none');
                $('#' + this.id + '-popover').css({top: offset.top - 20 + 'px', left: offset.left - 265 + 'px', 'display': 'block'});
            });
        }
    }
});

/**
 * Blur To Add More Item
 *
 * After user enter the receipt item name then they move to next fields, system will automatically add new item row
 */
rciSpaApp.directive('blurToAddMoreItem', function() {
    return function(scope, element, attrs) {
        element.bind('blur', function(e) {
            if (this.value) {
                scope.$emit('UPDATE_ITEM_COUNT');
                var parentCurrentItem = $(this).parent().parent();
                if(scope.$parent.tmpCategoryType == 'all_item'){
                    parentCurrentItem.addClass('selected');
                    $(parentCurrentItem).find('input[type="checkbox"]').click();
                }
            }
            var obj = scope.$eval(attrs.blurToAddMoreItem);
            if (this.value && obj.isLast) {
                scope.$apply(obj.callback);
            }
        });
        alignAppCatDropdown();
    }
});
/**
 * When user select CATEGORIZATION is combined item
 */
rciSpaApp.directive('blurAmountCol', function($timeout) {
    return{
        restrict: 'A',
        link : function(scope, element, attrs)
        {
            $scope = scope.$parent;
            //Event when user click out amount item field.
            element.bind('blur', function (e) {
                if ($scope.receipt.categorizeMethod == 'one_item') {
                    if ($scope.receipt.DigitalTotal > $scope.receipt.Tax) {
                        $scope.compareWithSumOfSubtotal();
                    } else {
                        $scope.responseMessage = ['Total can not be less than Tax !'];
                        $.showMessageBox({ content: 'Total can not be less than Tax !'});
                        $scope.receipt.DigitalTotal = $scope.receipt.Items[0].Amount;
                        $scope.compareWithSumOfSubtotal();
                    }
                    $('#amount-col-receipt-details').trigger('click');
                }
            });
            //event on drop text to amount col
            element.bind('drop', function (e) {
                e.preventDefault();
                this.value = selectionTmpValue;
                $(this).trigger('input');
                scope.$parent.userChangedContent = true;
                if($scope.receipt.categorizeMethod == 'one_item'){
                    $scope.receipt.DigitalTotal = $scope.receipt.Items[0].Amount;
                    $scope.compareWithSumOfItemlTotal();
                }else{
                    $scope.updateReceipt();
                }
            });

            element.bind('keyup change input paste', function (e) {
                var $this = $(this);
                var val = $this.val();
                var valLength = val.length;
                var maxCount = 12;

                if ($(this).val().indexOf('.') != -1) {
                    return;
                } else if ($(this).val().indexOf('.') == -1) {
                    if (valLength > maxCount) {
                        $this.val($this.val().substring(0, maxCount));
                    }
                }
            });
        }
    }
});
rciSpaApp.directive('dropTextNameCol', function() {
    return{
        restrict: 'A',
        link : function(scope, element, attrs)
        {
            element.bind('drop', function (e) {
                e.preventDefault();
                this.value = selectionTmpValue;
                $(this).trigger('input');
                scope.$parent.userChangedContent = true;
            });
        }
    }
});

rciSpaApp.directive('eventBindScroll', function($rootScope,$timeout) {
   return {
       restrict: 'A',
       link: function(scope,element,attrs){
           element.bind('scroll', function (e) {
                $timeout(function(){
                    $( ".tutocrpdf").animate({opacity: 0},500);
                },100);


           });
    }
   }
});


rciSpaApp.directive('selectionEventPdf', function($rootScope) {
    return {
        restrict: 'A',
        link: function(scope,element,attrs){
//            element.bind('mouseover', function (e) {
//                $( ".tutocrpdf").animate({opacity: 0.9},500);
//            });
//            element.bind('mouseleave', function (e) {
//                $( ".tutocrpdf").animate({opacity: 0},500);
//            });
        }
    }
});

rciSpaApp.directive('ngPdfRci', function($rootScope) {
    return {
        restrict: 'E',
        scope: {
            pdfpath : "=pdfpath"
        },
        templateUrl: 'views/rciViewerPDF.html',
        link: function (scope, element, attrs, rootScope) {
            var SCROLLBAR_PADDING = 20;
            var VERTICAL_PADDING = 5;
            var MAX_AUTO_SCALE = 1.25;

            PDFJS.workerSrc = 'components/pdfJS/src/worker_loader.js';

            var query = document.location.href.replace(/^[^?]*(\?([^#]*))?(#.*)?/, '$2');
            var queryParams = query ? JSON.parse('{' + query.split('&').map(function (a) {
                return a.split('=').map(decodeURIComponent).map(JSON.stringify).join(': ');
            }).join(',') + '}') : {};

            scope.urlpdf = queryParams.file || scope.$parent.pdfUrl;
            scope.scale = +queryParams.scale || 0.8;

            scope.pdfinit = function () {
                scope.urlpdf = scope.$parent.pdfUrl;
                PDFJS.getDocument(scope.urlpdf).then(function (pdf) {
                  var numPages = pdf.numPages;
                    scope.pdf = pdf;
                   var MAX_NUM_PAGES = 50;
                    var ii = Math.min(MAX_NUM_PAGES, numPages);
                  for (var i = 1; i <= ii; i++) {
                    var promise = Promise.resolve();
                    var anchor = document.createElement('a');
                    anchor.setAttribute('name', 'page');
                    anchor.setAttribute('title', 'Page');
                    jQuery("#pdfContainer").append(anchor);
                    promise = promise.then(function (pageNum, anchor) {
                      return pdf.getPage(pageNum).then(function (page) {
                        var viewport = page.getViewport(scope.scale);
                        var container = document.createElement('div');
                        container.id = 'pageContainer' + pageNum;
                        container.className = 'pageContainer';
                        container.style.width = viewport.width + 'px';
                        container.style.height = viewport.height + 'px';

                        anchor.appendChild(container);
                        jQuery("#pdfContainer a div").css('margin', 'auto');

                        return page.getOperatorList().then(function (opList) {
                          var svgGfx = new PDFJS.SVGGraphics(page.commonObjs, page.objs);
                          return svgGfx.getSVG(opList, viewport).then(function (svg) {
                            container.appendChild(svg);
                          });
                        });
                      });
                    }.bind(null, i, anchor));
                  }
                });
              scope.updateAutoZoom();
            }

            scope.reloadPDF = function () {
              scope.$parent.firstShowPDF = false;
              var MAX_NUM_PAGES = 50;
              var numPages =  scope.pdf.numPages;
              jQuery("#pdfContainer").empty();
              var ii = Math.min(MAX_NUM_PAGES, numPages);
              for (var i = 1; i <= ii; i++) {
                var promise = Promise.resolve();
                var anchor = document.createElement('a');
                anchor.setAttribute('name', 'page');
                anchor.setAttribute('title', 'Page');
                jQuery("#pdfContainer").append(anchor);
                promise = promise.then(function (pageNum, anchor) {
                  return scope.pdf.getPage(pageNum).then(function (page) {
                    var viewport = page.getViewport(scope.scale);
                    var container = document.createElement('div');
                    container.id = 'pageContainer' + pageNum;
                    container.className = 'pageContainer';
                    container.style.width = viewport.width + 'px';
                    container.style.height = viewport.height + 'px';

                    anchor.appendChild(container);
                    jQuery("#pdfContainer a div").css('margin', 'auto');

                    return page.getOperatorList().then(function (opList) {
                      var svgGfx = new PDFJS.SVGGraphics(page.commonObjs, page.objs);
                      return svgGfx.getSVG(opList, viewport).then(function (svg) {
                        container.appendChild(svg);
                      });
                    });
                  });
                }.bind(null, i, anchor));
              }

              setTimeout(function () {
                if (scope.$parent.selectAllPdfLevel == 2) {
                  scope.$parent.selectAllPdfLevel--;
                  scope.$parent.selectPdf();
                }
              });
            }



            scope.$parent.pdfZoomIn = function () {
                scope.scale = (scope.scale < 0.3) ? 0.2 : scope.scale - 0.2;
                $("#scaleSelect").val('5');
                scope.reloadPDF();
            }

            scope.$parent.pdfZoomOut = function () {
                scope.scale = (scope.scale > 0) ? parseFloat(scope.scale) + 0.2 : scope.scale;
                $("#scaleSelect").val('5');
                scope.reloadPDF();
            }

          scope.$parent.printPDF = function () {
            if (scope.$parent.receipt.ReceiptType == 2) {

              var b64encoded = btoa(Uint8ToBase64(scope.urlpdf));
              var container = $('#pdfContainer');
              var width = parseFloat(container.width());
              var height = parseFloat(container.height());
              var print = window.open('data:application/pdf;base64,' + b64encoded, "print", 'width=' + width + ',height=' + height + ',resizable=no,scrollbars=yes');
              print.focus();
              print.print();
              window.opener.focus();
              print.close();
            } else {
              var newElem = document.createElement("div");
              newElem.innerHTML = "<img style='margin:auto;' src='" + window.imagesPDFSRC + "' />";
              var newWin = window.open();
              newWin.document.write(newElem.innerHTML);
              newWin.location.reload();
              newWin.focus();
              newWin.print();
              newWin.close();
            }

          }

            /*
            * Function to detect 3 level of button OCR
            * */
            scope.$parent.selectAllPdfLevel = 0;
            scope.$parent.selectPdf= function(){
                scope.$parent.selectAllPdfLevel++;
                if(scope.$parent.selectAllPdfLevel == 1){
                    var range = document.createRange();
                    range.selectNode(document.getElementById('pdfContainer'));
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);

                    //Set text
                    scope.$parent.pdfOcaText = "Show OCR";
                    $( ".tutocrpdf").addClass('hightlight-text');
                    $( ".tutocrpdf i").show();
                    $( ".tutocrpdf").removeClass('ballon-tooltip-pdf');
                    scope.$parent.ballonTextPdf = "Highlighting Invisible Layer of</br> Optical Character Recognition (OCR)";
                    setTimeout(function(){
                        $(".tutocrpdf").show().css('opacity', '1');
                    });

                }else if(scope.$parent.selectAllPdfLevel == 2){
                    scope.$parent.pdfOcaText = "Show Image";
                    $('#image-pdf-viewer').hide();
                    $('.wrap-pdfViewer').css('background','#333');
                    $('#content-text-pdf-viewer').css('fill','#fff');
                    $('#content-text-pdf-viewer').css('fill-opacity','1');
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    scope.$parent.ballonTextPdf = "Displaying Invisible Layer of <br> Optical Character Recognition (OCR)";
                    setTimeout(function(){
                        $(".tutocrpdf").show().css('opacity', '0.9');
                    });
                }else{
                    scope.$parent.pdfOcaText = "Highlight OCR";
                    scope.$parent.selectAllPdfLevel  = 0;
                    $('#image-pdf-viewer').show();
                    $('.wrap-pdfViewer').css('background','#fff');
                    $('#content-text-pdf-viewer').css('fill','#000');
                    $('#content-text-pdf-viewer').css('fill-opacity','0');
                    $('.icon-pdf-tut-close').show();
                    $('.arrow-pdf-tut').show();
                    scope.$parent.ballonTextPdf = "Highlight, drag & drop any text from this image to your digital receipt";
                    setTimeout(function(){
                        $(".tutocrpdf").show().css('opacity', '0.9');
                    });
                }

            }

            scope.$parent.showInfoPdf = function(){
                    $('#info-pdf').toggleClass("hide");
            }

            scope.$parent.roatePdf = function(){
                var canvas = document.getElementById('pdfContainer');
                var width  = canvas.offsetWidth;
                var height = canvas.offsetHeight;
                if (canvas.getAttribute('class') === 'rotate0') {
                    canvas.setAttribute('class', 'rotate90');
                    canvas.style.width = (height * 1.4) + 'px';
                    canvas.style.height = width + 'px';
                } else if (canvas.getAttribute('class') === 'rotate90') {
                    canvas.setAttribute('class', 'rotate180');
                    canvas.removeAttribute("style");
                } else if (canvas.getAttribute('class') === 'rotate180') {
                    canvas.setAttribute('class', 'rotate270');
                    canvas.style.width = (height * 0.8) + 'px';
                    canvas.style.height = width + 'px';
                } else {
                    canvas.setAttribute('class', 'rotate0');
                    canvas.removeAttribute("style");
                }
            }

            /**
             * Function to download PDF receipt
             */
            scope.$parent.pdfViewDownload = function () {
                function downloadByUrl() {
                    downloadManager.downloadUrl(url, filename);
                }
                var url = scope.$parent.receipt.ReceiptImage.FilePath.split('#')[0];
                var filename = scope.$parent.receipt.MerchantName + " receipt";
                var downloadManager = new DownloadManager();
                scope.pdf.getData().then(
                    function getDataSuccess(data) {
                        var blob = PDFJS.createBlob(data, 'application/pdf');
                        downloadManager.download(blob, url, filename);
                    },
                    downloadByUrl // Error occurred try downloading with just the url.
                ).then(null, downloadByUrl);
            }

            $('#scaleSelect').on('change', function () {
                scope.autoZoom();
              console.log('day');
            });

            scope.updateAutoZoom = function(){
                $("#scaleSelect").val('page-fit');
            }

            /*
            * Function to zoom receipt pdf
            * */
            scope.autoZoom = function () {
                var value = $('#scaleSelect').val();
                var hPadding = PresentationMode.active ? 0 : SCROLLBAR_PADDING;
                var vPadding = PresentationMode.active ? 0 : VERTICAL_PADDING;
                var widthWrap =  $('.wrap-pdfViewer').width();
                var heightWrap = $('.wrap-pdfViewer').height();
                var pageWidthScale = (document.getElementById('pdfContainer').clientWidth - hPadding) /
                    widthWrap * 0.9;
                var pageHeightScale = (document.getElementById('pdfContainer').clientHeight - vPadding) /
                    heightWrap * 0.9;
                switch (value) {
                    case 'page-width':
                        scope.scale = pageWidthScale;
                        break;
                    case 'page-height':
                        scope.scale = pageHeightScale;
                        break;
                    case 'page-fit':
                        scope.scale = Math.min(pageWidthScale, pageHeightScale);
                        break;
                    case 'auto':
                        scope.scale = Math.min(MAX_AUTO_SCALE, pageWidthScale);
                        break;
                    default:
                        scope.scale = parseFloat(value);
                }
                scope.reloadPDF();
            };

            scope.$watch('pdfpath', function (nval,oval) {
                scope.$parent.firstShowPDF = true;
                scope.$parent.pdfOcaText = "Highlight OCR";
                scope.selectAllPdfLevel  = 0;
                scope.scale = 0.8;
                $( ".tutocrpdf i").show();
                $('#image-pdf-viewer').show();
                $("#pdfContainer").empty();
                $( ".tutocrpdf").removeClass('ballon-tooltip-pdf');
                $('.wrap-pdfViewer').css('background','#fff');
                $('#content-text-pdf-viewer').css('fill','#000');
                $('#content-text-pdf-viewer').css('fill-opacity','0');
                scope.$parent.selectAllPdfLevel = 0;
                scope.$parent.ballonTextPdf = "Highlight, drag & drop any text from this image to your digital receipt";
                if(nval){
                    scope.pdfinit();
                }
            });
        }
    };

});
