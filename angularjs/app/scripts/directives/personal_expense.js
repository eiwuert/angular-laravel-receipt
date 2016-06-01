/**
 * Directive to help opening ReceiptBox or Receipt Detail screen from a certain app to add expense(s)
 */
rciSpaApp.directive('addExpense', function ($timeout) {
    return {
        restrict: 'E',
        scope: {
            app: '@',
            from: '=',
            to: '=',
            customtext: '@'
        },
        template: '<div class="add-items-wrapper"><a class="btn add-items" href="" ng-bind-template="Add Expense {{customtext}}"></a></div>',
        link: function (scope, element, attrs) {
            var button = $(element).find('.add-items');
            var dateFrom = scope.from;
            var dateTo = scope.to;
            var today = new Date();
            var dateToday = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2)+ '-' + today.getDate();
            var dateReturn = dateToday;

            if (scope.app == 'personal_expense') {
                var appAbbr = 'PE';
                var wrapper = 'personal-expense-wrapper';
            } else if (scope.app == 'education_expense') {
                var appAbbr = 'EE';
                var wrapper = 'education-expense-wrapper';
            } else if (scope.app == 'business_expense') {
                var appAbbr = 'BE';
                var wrapper = 'business-expense-wrapper';
            } else if (scope.app == 'personal_assets') {
                var appAbbr = 'PA';
                var wrapper = 'personal-assets-wrapper';
            } else if (scope.app == 'business_assets') {
                var appAbbr = 'BA';
                var wrapper = 'business-assets-wrapper';
            } else if (scope.app == 'travel_expense') {
                var appAbbr = 'TE';
                var wrapper = 'trip-detail-wrapper';
            }

            scope.$watch('from', function (newValue, oldValue) {
                dateFrom = newValue;
                if ((typeof (dateTo) == 'undefined') || (dateTo == dateFrom)) {
                    dateReturn = dateFrom;
                    categoryInfo.date = dateReturn;
                } else {
                    dateReturn = dateToday;
                    categoryInfo.date = dateReturn;
                }
            });

            scope.$watch('to', function (newValue, oldValue) {
                dateTo = newValue;
                if ((typeof (dateTo) == 'undefined') || (dateTo == dateFrom)) {
                    dateReturn = dateFrom;
                    categoryInfo.date = dateReturn;
                } else {
                    dateReturn = dateToday;
                    categoryInfo.date = dateReturn;
                }
            });

            var categoryInfo = {
                app: scope.app,
                appAbbr: appAbbr,
                date: dateReturn
            };

            button.popover({
                html: true,
                placement: 'bottom',
                content: '<div class="add-expense-popover custom-popover">\
                        <ul class="unstyled">\
                            <li><button class="btn btn-success btn-open-mr">Manually</button></li>\
                            <li>or from</li>\
                            <li><button class="btn btn-success btn-open-rb">ReceiptBox</button></li>\
                        </ul>\
                    </div>'
            }).click(function (e) {
                    e.stopPropagation();
                    if ($(this).hasClass('clicked')) {
                        $(this).popover('hide').removeClass('clicked');
                    } else {
                        $(this).popover('show').addClass('clicked');

                        //When clicking outside of the popover, close it
                        $('body').on('click', function () {
                            if (button.hasClass('clicked')) {
                                button.popover('hide').removeClass('clicked');
                            }
                        });
                        $('.add-expense-popover').on('click', function (e) {
                            e.stopPropagation();
                        });

                    }

                    $('.btn-open-mr').on('click', function () {
                        $timeout(function () {
                            button.popover('hide').removeClass('clicked');

                            $('#loading-indicator').css('display', 'block');
                            $('#rb-receipt-list .app-table-child tbody').removeClass('clicked');
                            $('#receipt-detail-wrapper').css('display', 'block');
                            $('#' + wrapper).css('display', 'none');
                            $('#top-header').addClass('hide').removeClass('show');
                            $('#sidebar-right').addClass('hide').removeClass('show');

                            if (scope.app == 'travel_expense') {
                                var tripInfo = {
                                    tripID: scope.$parent.currentTrip.TripID,
                                    reference: scope.$parent.currentTrip.Reference,
                                    tripType: scope.$parent.tripType,
                                    dateFrom: scope.$parent.dateFrom,
                                    dateTo: scope.$parent.dateTo
                                };

                                scope.$emit('RB_ADD_EXPENSE_MANUAL', scope.openFrom, categoryInfo, tripInfo);
                            } else {
                                scope.$emit('RB_ADD_EXPENSE_MANUAL', scope.openFrom, categoryInfo);
                            }
                        });
                    });

                    $('.btn-open-rb').on('click', function () {
                        $timeout(function () {
                            button.popover('hide').removeClass('clicked');

                            if (scope.app == 'travel_expense') {
                                var tripInfo = {
                                    tripID: scope.$parent.currentTrip.TripID,
                                    reference: scope.$parent.currentTrip.Reference,
                                    tripType: scope.$parent.tripType,
                                    dateFrom: scope.$parent.dateFrom,
                                    dateTo: scope.$parent.dateTo
                                };
                                $('#backHistory').attr('back-button', 'trip-detail-wrapper');
                                scope.$parent.$emit('OPEN_RB_ADD_ITEMS', categoryInfo, tripInfo);
                            } else if (scope.app == 'education_expense') {
                                $('#backHistory').attr('back-button', 'education-expense-wrapper');
                                scope.$parent.$emit('OPEN_RB_ADD_ITEMS', categoryInfo);
                            } else if (scope.app == 'business_expense') {
                                $('#backHistory').attr('back-button', 'business-expense-wrapper');
                                scope.$parent.$emit('OPEN_RB_ADD_ITEMS', categoryInfo);
                            } else if (scope.app == 'personal_assets') {
                                $('#backHistory').attr('back-button', 'personal-assets-wrapper');
                                scope.$parent.$emit('OPEN_RB_ADD_ITEMS', categoryInfo);
                            } else if (scope.app == 'business_assets') {
                                $('#backHistory').attr('back-button', 'business-assets-wrapper');
                                scope.$parent.$emit('OPEN_RB_ADD_ITEMS', categoryInfo);
                            } else {
                                $('#backHistory').attr('back-button', 'personal-expense-wrapper');
                                scope.$parent.$emit('OPEN_RB_ADD_ITEMS', categoryInfo);
                            }

                            $('#receiptbox-wrapper').show();
                            $('#' + wrapper).hide();
                        });
                    });
                });
        }
    }
});

/**
 * Directive to parse & display Personal Expense category group
 */
rciSpaApp.directive('optPeCatGroup', function ($filter, $timeout, $rootScope, $compile) {
    return {
        strict: 'A',
        scope: {
            categories: '=',
            currApp: '@',
            ref: '@',
            openFrom: '@',
            hideAddItem: '=',
            from: '=',
            to: '='
        },
        link: function (scope, element, attrs) {
            scope.openShowMoreModal = function (rowIndex) {
                $rootScope.displayShowMore(scope.categories[rowIndex]);
            }
            var dateFrom = scope.from;
            var dateTo = scope.to;
            var today = new Date();
            var dateToday = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2)+ '-' + today.getDate();
            var dateReturn = dateToday;

            //Enable sorting for item mode and disable for category mode
            var toggleSortIcons = function(toEnable) {
                var sortIcons = parentWrapperClass + ' th .icon-arrow-ud, ' +
                    parentWrapperClass + ' th .icon-arrow-u, ' +
                    parentWrapperClass + ' th .icon-arrow-d';

                $(sortIcons).css('visibility', toEnable ? 'visible' : 'hidden');
            };

            $.tablesorter.addParser({
                id: 'rc-date-parser',
                is: function (s, table, cell) {
                    return false;
                },
                format: function (s, table, cell, cellIndex) {
                    if (cellIndex == 5) {
                        return $(cell).attr('data-date') || s;
                    }

                    return s;
                },
                type: 'text'
            });

            var wrapperId = $(element).attr('wrapper-id');
            var tbodyTpl, tbodyRowTpl, itemTpl, rowTpl;


            var parentWrapperClass = '.tb-pe.app-pe';
            if (wrapperId == 'te-pe-item-list') {
                parentWrapperClass = '.tb-pe.app-te';
            } else if (wrapperId == 'ee-item-list') {
                parentWrapperClass = '.tb-pe.app-ee';
            } else if (wrapperId == 'be-item-list') {
                parentWrapperClass = '.tb-pe.app-be';
            } else if (wrapperId == 'pa-item-list') {
                parentWrapperClass = '.tb-pe.app-pa';
            } else if (wrapperId == 'ba-item-list') {
                parentWrapperClass = '.tb-pe.app-ba';
            }
            scope.$watch('categories', function (o, n) {

                if($(parentWrapperClass + ' th.col-itm i').hasClass('icon-explore-cat')) {
                    var currentState = "item";
                    //Disable sorting
                    toggleSortIcons(true);
                } else {
                    var currentState = "category";
                    //Enable sorting
                    toggleSortIcons(false);
                }
                tbodyTpl = '';
                rowTpl = '';
                tbodyRowTpl = '';
                itemTpl = '';

                // Switch to Category view mode
                $(parentWrapperClass + ' th.col-itm i').removeClass('icon-explore-cat').addClass('icon-explore-item');
                var itemTableRowIndex = 0;
                angular.forEach(scope.categories, function (v1, k1) {
                    tbodyTpl += '<tbody>';
                    tbodyRowTpl = '';
                    angular.forEach(v1, function (row, rowIndex) {
                        rowTpl = '';
                        var showHideRow = (row.parentCollapse || row.masterCollapse || (scope.listItemOnly && row.Type != 'item')) ? ' hide' : '';
                        rowTpl += '<tr class="' + row.class + showHideRow + '">\
                            <td class="col-chk"><div class="app-icon icon-checkbox-sqr" data-id="' + row.ItemID + '"></div></td>\
                           <td class="align-left col-cat">';

                        var circleClass = 'icon-circle-p';
                        if (row.expand) {
                            circleClass = 'icon-circle-m';
                        }

                        var collapseClass = '';
                        if (row.collapseDisabled) {
                            collapseClass += 'collapse-disabled';
                            circleClass += ' hide';
                        }

                        rowTpl += '<div class="wrap ' + collapseClass + '">';
                        rowTpl += '<i class="app-icon icon-circle fix-nomargin ' + circleClass + '"></i>\
                               <i class="app-icon icon-folder"></i>\
                               <div class="uppercase cat-name text-ellipsis"><span category-id="' + row.CategoryID + '">' + row.CategoryName + '</span></div>\
                            </div>\
                           </td>\
                           <td class="align-right col-amo"><div class="wrap-indent-right">' + $filter('itemPrice')(row.Amount) + '</div></td>\
                           <td class="align-left col-itm">';

                        if (row.Type == 'item') {
                            rowTpl += '<a href="" receipt-id="' + row.ReceiptID + '" item-id="' + row.ItemID + '" class="wrap-indent-left text-ellipsis" open-from="' + scope.openFrom + '">' + row.Name + '</a>';
                        } else {
                            rowTpl += '&nbsp;';
                        }

                        rowTpl += '</td>\
                               <td class="align-left col-mrc">';

                        if (row.Type == 'item') {
                            rowTpl += '<div class="wrap-indent-left text-ellipsis">' + row.MerchantName + '</div>';
                        } else {
                            rowTpl += '&nbsp;';
                        }

                        rowTpl += '</td>\
                               <td class="col-dat" data-date="' + row.PurchaseTime + '">' + $filter('formatDate')(row.PurchaseTime, 'dd-MMM-yyyy') + '</td>\
                               <td class="col-exp">';

                        if (scope.currApp != 'te' && row.Type == 'item') {
                            rowTpl += $filter('formatDate')(row.ExpensePeriodFrom, 'MMM-yyyy')
                        } else if (scope.currApp == 'te' && row.Type == 'item') {
                            rowTpl += scope.ref;
                        }

                        rowTpl += '</td>\
                                   <td class="col-inf">';

                        if (row.Type == 'item') {
                            rowTpl += '<a class="app-icon icon-more icon-more-grey" title="Feature coming soon"></a>'

                            //Temporary disable Show More
                            /*
                            rowTpl += '<a ng-click="$event.stopPropagation(); openShowMoreModal(' + rowIndex + ')" class="app-icon icon-more';

                            if (row.More.IsEmpty) {
                                rowTpl += ' icon-more-grey';
                            }

                            rowTpl += '"></a>';
                             */
                        } else {
                            rowTpl += '&nbsp;';
                        }

                        rowTpl += '</td>\
                                    <td class="col-att">';

                        if (row.Type == 'item') {
                            itemTableRowIndex++;
                            var attachmentHtml = '';
                            rowTpl += '<a id="item-attachment-' + row.ItemID + '" class="app-icon icon-attachment';

                            if (row.Attachments.length) {
                                attachmentHtml = '<span>' + row.Attachments.length + '</span>';
                            } else {
                                rowTpl += ' no-file';
                            }

                            var eleId = "item-attachment-" + row.ItemID + "-popover";
                            rowTpl += '" onclick="showPopupAttachmentByEleId(this, '+ itemTableRowIndex +');" ng-click="$event.stopPropagation">' + attachmentHtml + '</a>';
                        } else {
                            rowTpl += '&nbsp;';
                        }

                        //Add popover for attachments
//                        if (row.Type == 'item' && row.Attachments.length) {
                        if (row.Type == 'item') {
                            rowTpl += '<div class="pov-list-attachment" id="item-attachment-' + row.ItemID + '-popover">\
                                    <div class="list-attachment-wrapper">\
                                        <ul class="unstyled" id="attachment-ul-item-wrap">\
                                            <li><div class="file-name"><b>Attachment</b></div></li>';
                            angular.forEach(row.Attachments, function (file, k) {
                                rowTpl += '<li>\
                                        <div class="file-name">\
                                            <a target="_blank" href="' + file.FilePath + '" title="Click to view or download this file">' + file.FileName + '</a>\
                                        </div>\
                                        <div class="clearfix border-bottom"></div>\
                                    </li>';
                            });
                            rowTpl += '</ul>\
                                        <div class="actachment-control">\
                                            <div id="item-attachment-queue-{{ $index}}"></div>' +
                                            '<a class="pull-right" href="javascript:void(0)" onclick="hidePopupAttachments()"><i class="icon-remove-circle"></i> Close</a>\
                                            <div class="clearfix"></div>\
                                        </div>\
                                    </div>\
                                    <div class="arrow-side-right"></div>\
                                </div>';
                        }

                        rowTpl += '</td>';

                        rowTpl += '</tr>';



                        if (row.Type == 'item') {
                            itemTpl += rowTpl;
                        }

                        tbodyRowTpl += rowTpl;
                    });

                    tbodyTpl += tbodyRowTpl + '</tbody>';
                });

                $(element).html($compile(tbodyTpl)(scope));

                if(currentState == "item") {
                    $(parentWrapperClass + ' th.col-itm i').click();
                }
            });

            // Event to process collapse & expand
            $('#' + wrapperId).on('click', 'td.col-cat .app-icon.icon-circle', function (e) {
                var currentState = $(this).hasClass('icon-circle-p') ? 'close' : 'open';

                var parent = $(this).parents('tr');
                var parentLevel = parent.attr('class').match(/cat-lv([0-9]+)/)[1];
                var nextLevel = parseInt(parentLevel) + 1;

                if (currentState == 'close') {
                    $(this).removeClass('icon-circle-p').addClass('icon-circle-m');

                    if (parentLevel == 1) {
                        $(parent).nextUntil('tbody', 'tr.cat-lv' + nextLevel).removeClass('hide');
                    } else if (parentLevel > 1) {
                        $(parent).nextUntil('tr.cat-lv' + parentLevel).removeClass('hide');
                    }
                } else {
                    $(this).removeClass('icon-circle-m').addClass('icon-circle-p');

                    if (parentLevel == 1) {
                        $(parent).nextUntil('tbody').addClass('hide').find('.app-icon.icon-circle').removeClass('icon-circle-m').addClass('icon-circle-p');
                    } else if (parentLevel > 1) {
                        $(parent).nextUntil('tr.cat-lv' + parentLevel).addClass('hide');
                    }
                }
            });

            // Event to process collapse & expand all categories
            $(parentWrapperClass + ' th.col-cat .icon-circle').on('click', function (e) {
                var currentState = $(this).hasClass('icon-circle-p') ? 'close' : 'open';

                if (currentState == 'close') {
                    $(this).removeClass('icon-circle-p').addClass('icon-circle-m');
                    $(parentWrapperClass + ' td.col-cat .app-icon.icon-circle').removeClass('icon-circle-p').addClass('icon-circle-m');
                    $(parentWrapperClass + ' tbody tr[class^="cat-lv"]').removeClass('hide');
                } else {
                    $(this).removeClass('icon-circle-m').addClass('icon-circle-p');
                    $(parentWrapperClass + ' td.col-cat .app-icon.icon-circle').removeClass('icon-circle-m').addClass('icon-circle-p');
                    $(parentWrapperClass + ' tbody tr[class^="cat-lv2"]').addClass('hide');
                    $(parentWrapperClass + ' tbody tr[class^="cat-lv3"]').addClass('hide');
                    $(parentWrapperClass + ' tbody tr[class^="cat-lv4"]').addClass('hide');
                }
            });

            // Event to process item/category view mode
            $(parentWrapperClass + ' th.col-itm i').on('click', function (e) {
                // item mode
                if ($(this).hasClass('icon-explore-item')) {
                    $(element).html('<thead>' +
                        '<tr>' +
                        '    <th></th>' +
                        '    <th>category</th>' +
                        '    <th>Amount</th>' +
                        '    <th>item</th>' +
                        '    <th>merchant</th>' +
                        '    <th>date</th>' +
                        '    <th>period</th>' +
                        '    <th>more</th>' +
                        '    <th>attachment</th>' +
                        '    <th>deal</th>' +
                        '    <th>share</th>' +
                        '</tr>' +
                        '</thead>');

                    $(element).append($compile(itemTpl)(scope));
                    $('#' + wrapperId).tablesorter({headers: {5: { sorter: 'rc-date-parser' }}});

                    $(this).addClass('icon-explore-cat').removeClass('icon-explore-item');
                    $(parentWrapperClass + ' th.col-cat .icon-circle').addClass('hide').addClass('icon-circle-p').removeClass('icon-circle-m');
                    $(element).find('.col-cat .wrap').addClass('padding-leftmost');
                    $('#' + wrapperId + ' tbody tr[class^="cat-lv"].item').removeClass('hide');

                    //Enable sorting for item mode
                    toggleSortIcons(true);
                } else { // category mode
                    $("#" + wrapperId).trigger("destroy");
                    $(element).html($compile(tbodyTpl)(scope));

                    $(this).addClass('icon-explore-item').removeClass('icon-explore-cat');
                    $(parentWrapperClass + ' th.col-cat .icon-circle').removeClass('hide');

                    truncatePETableText(scope.currApp);

                    //Enable sorting for category mode
                    toggleSortIcons(false);
                }
            });

            // Event to process individual checkbox
            $('#' + wrapperId).on('click', 'td.col-chk .app-icon.icon-checkbox-sqr', function (e) {
                if ($(this).hasClass('icon-checkedbox-sqr')) {
                    $(this).removeClass('icon-checkedbox-sqr');
                } else {
                    $(this).addClass('icon-checkedbox-sqr');
                }
            });

            // Event to process check all check boxes
            $(parentWrapperClass + ' th.col-chk .app-icon.icon-checkbox-sqr').on('click', function (e) {
                var isChecked;
                if ($(this).hasClass('icon-checkedbox-sqr')) {
                    $(this).removeClass('icon-checkedbox-sqr');
                    isChecked = false;
                } else {
                    $(this).addClass('icon-checkedbox-sqr');
                    isChecked = true;
                }

                $('#' + wrapperId + ' .col-chk .app-icon.icon-checkbox-sqr').each(function (k, v) {
                    if (!isChecked) {
                        $(this).removeClass('icon-checkedbox-sqr');
                    } else {
                        $(this).addClass('icon-checkedbox-sqr');
                    }
                });
            });

            // Sort by amount
            $(parentWrapperClass + " th.col-amo").on('click', function () {
                $("#" + wrapperId + " thead").find("th:eq(2)").trigger("sort");
                return false;
            });

            // Sort by merchant
            $(parentWrapperClass + " th.col-mrc").on('click', function () {
                $("#" + wrapperId + " thead").find("th:eq(4)").trigger("sort");
                return false;
            });

            // Sort by date
            $(parentWrapperClass + " th.col-dat").on('click', function () {
                $("#" + wrapperId + " thead").find("th:eq(5)").trigger("sort");
                return false;
            });

            //Bind event mouseenter to the span element & show popover
            $('#' + wrapperId).on('mouseenter', 'tr:not(.item) .cat-name > span', function (e) {
                e.stopPropagation();
                var spanElement = $(this);
                if (!scope.hideAddItem) {
                    $(this).popover({
                        html: true,
                        placement: 'right',
                        content: '<div class="cat-add-expense-popover custom-popover">\
                            <ul class="unstyled inline" style="margin: 0 0 1px 0;">\
                                <li><b>' + spanElement.html().toUpperCase() + '</b>&nbsp;</li>\
                            </ul>\
                        </div>',
                        container: spanElement.parent()
                    });

                    spanElement.popover('show');

                    if (spanElement.width()>spanElement.parent().width()) {
                       var difWidth = spanElement.width() - spanElement.parent().width();
                       var curLeft = parseInt(spanElement.parent().find('.popover').css('left'));
                       spanElement.parent().find('.popover').css('left', curLeft-difWidth + 'px');
                    }
                }

                //Bind event mouseleave to this element, which is the parent of both the span element and the popover
                $(spanElement).parent().bind('mouseleave', function (e) {
                    e.stopPropagation();
                    spanElement.popover('hide');
                });
            });

            //Bind event mouseenter to the span element & show popover
            $('#' + wrapperId).on('mouseenter', 'tr:not(.item) .col-amo > div.wrap-indent-right', function (e) {
                e.stopPropagation();
                var divAmoutElement = $(this);
                var spanElement = $(this).parent().prev(".col-cat").find(".cat-name > span");

                if (!scope.hideAddItem) {
                    $(this).popover({
                        html: true,
                        placement: 'right',
                        content: '<div class="cat-add-expense-popover custom-popover">\
                            <ul class="unstyled inline" style="margin: 0 0 1px 0;">\
                                <li>Add Expense</li>\
                                <li><button class="btn-mini btn-success btn-cat-open-mr">Manually</button></li>\
                                <li>or from</li>\
                                <li><button class="btn-mini btn-success btn-cat-open-rb">ReceiptBox</button></li>\
                            </ul>\
                        </div>',
                        container: divAmoutElement.parent()
                    });

                    divAmoutElement.popover('show');

                    if (divAmoutElement.width()>divAmoutElement.parent().width()) {
                       var difWidth = divAmoutElement.width() - divAmoutElement.parent().width();
                       var curLeft = parseInt(divAmoutElement.parent().find('.popover').css('left'));
                       divAmoutElement.parent().find('.popover').css('left', curLeft-difWidth + 'px');
                    }

                    var curLeft = parseInt(divAmoutElement.parent().find('.popover').css('left'));
                   divAmoutElement.parent().find('.popover').css('left', curLeft-10 + 'px')
                }

                var categoryInfo;

                scope.$watch('from', function (newValue, oldValue) {
                    dateFrom = newValue;
                    if ((typeof (dateTo) == 'undefined') || (dateTo == dateFrom)) {
                        dateReturn = dateFrom;
                        categoryInfo.date = dateReturn;
                    } else {
                        dateReturn = dateToday;
                        categoryInfo.date = dateReturn;
                    }
                });

                scope.$watch('to', function (newValue, oldValue) {
                    dateTo = newValue;
                    if ((typeof (dateTo) == 'undefined') || (dateTo == dateFrom)) {
                        dateReturn = dateFrom;
                        categoryInfo.date = dateReturn;
                    } else {
                        dateReturn = dateToday;
                        categoryInfo.date = dateReturn;
                    }
                });

                if (scope.openFrom == 'personal-expense-wrapper') {
                    categoryInfo = {
                        categoryID: spanElement.attr('category-id'),
                        categoryName: spanElement.text(),
                        app: 'personal_expense',
                        appAbbr: 'PE',
                        date: dateReturn
                    };
                } else if (scope.openFrom == 'business-expense-wrapper') {
                    categoryInfo = {
                        categoryID: spanElement.attr('category-id'),
                        categoryName: spanElement.text(),
                        app: 'business_expense',
                        appAbbr: 'BE',
                        date: dateReturn
                    };
                } else if (scope.openFrom == 'education-expense-wrapper') {
                    categoryInfo = {
                        categoryID: spanElement.attr('category-id'),
                        categoryName: spanElement.text(),
                        app: 'education_expense',
                        appAbbr: 'EE',
                        date: dateReturn
                    };
                } else if (scope.openFrom == 'personal-assets-wrapper') {
                    categoryInfo = {
                        categoryID: spanElement.attr('category-id'),
                        categoryName: spanElement.text(),
                        app: 'personal_assets',
                        appAbbr: 'PA',
                        date: dateReturn
                    };
                } else if (scope.openFrom == 'business-assets-wrapper') {
                    categoryInfo = {
                        categoryID: spanElement.attr('category-id'),
                        categoryName: spanElement.text(),
                        app: 'business_assets',
                        appAbbr: 'BA',
                        date: dateReturn
                    };
                } else if (scope.openFrom == 'trip-detail-wrapper') {
                    categoryInfo = {
                        categoryID: spanElement.attr('category-id'),
                        categoryName: spanElement.text(),
                        app: 'travel_expense',
                        appAbbr: 'TE',
                        date: dateReturn
                    };

                }

                $('.btn-cat-open-mr').on('click', function () {
                    divAmoutElement.popover('hide');
                    $('#loading-indicator').css('display', 'block');
                    $('#rb-receipt-list .app-table-child tbody').removeClass('clicked');
                    $('#receipt-detail-wrapper').css('display', 'block');
                    $('#' + scope.openFrom).css('display', 'none');
                    $('#top-header').addClass('hide').removeClass('show');
                    $('#sidebar-right').addClass('hide').removeClass('show');

                    $timeout(function () {
                        if (scope.openFrom == 'trip-detail-wrapper') {
                            var tripInfo = {
                                tripID: scope.$parent.currentTrip.TripID,
                                reference: scope.$parent.currentTrip.Reference,
                                tripType: scope.$parent.tripType,
                                dateFrom: scope.$parent.dateFrom,
                                dateTo: scope.$parent.dateTo
                            };

                            scope.$emit('RB_ADD_EXPENSE_MANUAL', scope.openFrom, categoryInfo, tripInfo);
                        } else {
                            scope.$emit('RB_ADD_EXPENSE_MANUAL', scope.openFrom, categoryInfo);
                        }
                    });

                });

                $('.btn-cat-open-rb').on('click', function () {
                    divAmoutElement.popover('hide');
                    $timeout(function () {
                        if (scope.openFrom == 'trip-detail-wrapper') {
                            var tripInfo = {
                                tripID: scope.$parent.currentTrip.TripID,
                                reference: scope.$parent.currentTrip.Reference,
                                tripType: scope.$parent.tripType,
                                dateFrom: scope.$parent.dateFrom,
                                dateTo: scope.$parent.dateTo
                            };
                            scope.$parent.$emit('OPEN_RB_ADD_ITEMS', categoryInfo, tripInfo);
                        } else {
                            scope.$parent.$emit('OPEN_RB_ADD_ITEMS', categoryInfo);
                        }
                    });

                    $('#receiptbox-wrapper').show();
                    $('#' + scope.openFrom).hide();
                });

                //Bind event mouseleave to this element, which is the parent of both the span element and the popover
                $(divAmoutElement).parent().bind('mouseleave', function (e) {
                    e.stopPropagation();
                    divAmoutElement.popover('hide');
                });
            });

            /**
             * View expense detail
             *
             * FIXME: currently clone from openRd directive. We should define a reusable function for easier maintenance
             */
            $('#' + wrapperId).on('click', 'td.col-itm a', function (e) {
                e.preventDefault();

                var element = $(this);
                var receiptId = element.attr('receipt-id') || 0;
                var itemId = element.attr('item-id') || 0;
                var verifyStatus;


                $timeout(function () {
                  var spanElement = element.parent().parent().find(".cat-name > span");
                    var categoryInfo = {
                      categoryID: spanElement.attr('category-id'),
                      categoryName: spanElement.text(),
                      app: 'travel_expense',
                      appAbbr: 'TE',
                      date: dateReturn
                    };

                    $('#loading-indicator').css('display', 'block');
                    $('#rb-receipt-list .app-table-child tbody').removeClass('clicked');
                    element.parents('tbody').addClass('clicked');

                    $('#receipt-detail-wrapper').css('display', 'block');
                    $('#top-header').addClass('hide').removeClass('show');
                    $('#sidebar-right').addClass('hide').removeClass('show');
                    $('#receiptbox-wrapper').css('display', 'none');

                    if (scope.openFrom == 'trip-detail-wrapper') {
                        var tripInfo = {
                            tripID: scope.$parent.currentTrip.TripID,
                            reference: scope.$parent.currentTrip.Reference,
                            tripType: scope.$parent.tripType,
                            dateFrom: scope.$parent.dateFrom,
                            dateTo: scope.$parent.dateTo
                        };

                        scope.$emit('LOAD_RECEIPT_DETAIL', receiptId, itemId, scope.openFrom, verifyStatus, categoryInfo, tripInfo, true);
                    } else {
                        scope.$emit('LOAD_RECEIPT_DETAIL', receiptId, itemId, scope.openFrom, verifyStatus);
                    }

//                   if (scope.$parent.openFromApp) {
//                       if (scope.$parent.openFromApp == 'personal_expense') {
//                           var wrapper = 'personal-expense-wrapper';
//                           scope.$emit('LOAD_RECEIPT_DETAIL', receiptId, itemId, wrapper, verifyStatus, scope.$parent.categoryInfo);
//                       } else if (scope.$parent.openFromApp == 'travel_expense' && typeof(scope.$parent.tripInfo) !== 'undefined') {
//                           var wrapper = 'trip-detail-wrapper';
//                           scope.$emit('LOAD_RECEIPT_DETAIL', receiptId, itemId, wrapper, verifyStatus, scope.$parent.categoryInfo, scope.$parent.tripInfo);
//                       }
//                   } else {
//                       if (angular.isDefined(scope.openFrom)) {
//                           $('#' + scope.openFrom).css('display', 'none');
//                       }
//
//                       scope.$emit('LOAD_RECEIPT_DETAIL', receiptId, itemId, scope.openFrom, verifyStatus);
//                   }
                });
            });
        }
    }
});

/**
 * Purpose: Expand or collapse branches of category tree
 * + We will use this directive in the category tree of Receipt Detail screen
 */
rciSpaApp.directive('toggleBranch', function ($timeout) {
    return function (scope, element, attrs) {
        $timeout(function () {
            $(element).on('click', 'td.col-cat .app-icon.icon-circle-small', function () {
                var currentState = $(this).hasClass('icon-circle-p') ? 'close' : 'open';
                var parent = $(this).parents('tr');
                var parentLevel = parent.attr('class').match(/cat-lv([0-9]+)/)[1];
                var nextLevel = parseInt(parentLevel) + 1;
                if (currentState == 'close') {
                    $(this).removeClass('icon-circle-p').addClass('icon-circle-m');

                    if (parentLevel == 1) {
                        $(parent).nextUntil('tbody', 'tr.cat-lv' + nextLevel).removeClass('hide');
                    } else if (parentLevel > 1) {
                        $(parent).nextUntil('tr.cat-lv' + parentLevel).removeClass('hide');
                    }
                } else {
                    $(this).removeClass('icon-circle-m').addClass('icon-circle-p');

                    if (parentLevel == 1) {
                        $(parent).nextUntil('tbody').addClass('hide').find('.app-icon.icon-circle').removeClass('icon-circle-m').addClass('icon-circle-p');
                    } else if (parentLevel > 1) {
                        $(parent).nextUntil('tr.cat-lv' + parentLevel).addClass('hide');
                    }
                }
            });
        })
    }
});

/**
 * Purpose: Search category by keyword in the category tree
 * + We will use this directive in the category tree of Receipt Detail screen
 */
rciSpaApp.directive('searchCat', function ($timeout) {
    return function (scope, element, attrs) {
        $(element).bind('keyup', function() {
            var str = $(this).val().trim().toLowerCase();
            if (str == '') {
                $timeout(function() {
                    $('.icon-circle-small').css('width', 14);
                    $('.categories-tbl-wrapper tr').removeClass('superhide supershow');
                });
            } else {
                $timeout(function() {
                    $('.icon-circle-small').css('width', 0);
                    for (var i = 0; i < scope.$parent.categoryTree.length; i++) {
                        for (var j = 0; j < scope.$parent.categoryTree[i].length; j++) {
                            var catTableRow = $('tr[category-id="' + scope.$parent.categoryTree[i][j].CategoryID + '"]');
                            if (!(new RegExp(str)).test(scope.$parent.categoryTree[i][j].CategoryName.toLowerCase())) {
                                if (! catTableRow.hasClass('hide')) {
                                    catTableRow.addClass('superhide');
                                } else if (catTableRow.hasClass('supershow')) {
                                    catTableRow.removeClass('supershow');
                                }
                            } else if (catTableRow.hasClass('hide')) {
                                catTableRow.addClass('supershow');
                            } else if (catTableRow.hasClass('superhide')) {
                                catTableRow.removeClass('superhide');
                            }
                        }
                    }
                });
            }
        });
    }
});

rciSpaApp.directive('outPeriodResetFilter', function ($timeout) {
    return function (scope, element, attrs) {
//        $('body').bind('click', function() {
//            if (! $('#personal-expense-wrapper').is(':visible') && ! $('#analytic').is(':visible')) {
//                return false;
//            }
//
//            if (scope.tmpUsePeriodRange == scope.usePeriodRange && scope.tmpPeriodFrom == scope.periodFrom && scope.tmpPeriodTo == scope.periodTo) {
//                return false;
//            }
//
//            scope.tmpUsePeriodRange = angular.copy(scope.usePeriodRange);
//            scope.tmpPeriodFrom = angular.copy(scope.periodFrom);
//            scope.tmpPeriodTo = angular.copy(scope.periodTo);
//            scope.filtering = false;
//            scope.resetFilter = true;
//
//            $timeout(function() {
//                $('.calendar-inline.date-from').datepicker('setDate', scope.tmpPeriodFrom);
//                $('.calendar-inline.date-to').datepicker('setDate', scope.tmpPeriodTo);
//
//                if (typeof scope.tmpUsePeriodRange == 'undefined') {
//                    scope.tmpUsePeriodRange = false;
//                }
//                $('.app-pe .filterdate .checkbox-range').prop('checked', scope.tmpUsePeriodRange);
//            });
//        });
//
//        $(element).children().bind('click', function(e) {
//            e.stopPropagation();
//        });
//
//        $('#menu-personal-expense, a').bind('click', function(e) {
//            e.stopPropagation();
//        });
    }
});
