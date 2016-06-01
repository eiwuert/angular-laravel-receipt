'use strict';
angular.module('rciSpaApp.utilityFilters', [])
    .filter('itemStatus', function() {
        return function(status) {
            status = parseInt(status);
            switch (status) {
                case 1:
                    return 'Auto-categorized';
                case 2:
                    return 'User categorized';
                default:
                    return 'Not categorized';
            }
        };
    })
    .filter('receiptTypeFilter', function() {
        return function(type) {
            type = parseInt(type);
            switch (type) {
                case 1:
                    return 'Digital Receipt';
                case 2:
                    return 'Email Receipt';
                case 3:
                    return 'Paper Receipt';
                case 4:
                    return 'Manual Receipt';
                case 5:
                    return 'Non-Receipt';
                case 6:
                    return 'Paper Invoice';
                case 7:
                    return 'Electronic Invoice';

                default:
                    return '';
            }
        };
    })
    .filter('receiptTypeAbbrFilter', function() {
        return function(type) {
            type = parseInt(type);
            switch (type) {
                case 1:
                    return 'DR';
                case 2:
                    return 'ER';
                case 3:
                    return 'PR';
                case 4:
                    return 'MR';
                case 5:
                    return 'NR';
                case 6:
                    return 'PI';
                case 7:
                    return 'EI';

                default:
                    return '';
            }
        };
    })
    .filter('itemPrice', function() {
        return function(price) {
            price = parseFloat(price);

            if (isNaN(price)) {
                return '0.00';
            }
            return price.toFixed(2);
        };
    })
    .filter('receiptStatus', function() {
        return function(receipt) {
            var status = parseInt(receipt.VerifyStatus);
            switch (status) {
                case 0:
                    var type = parseInt(receipt.ReceiptType);
                    switch (type) {
                        case 1:
                            return 'New: Digital Receipt';
                        case 2:
                            return 'New: Email Receipt';
                        case 3:
                            return 'New: Paper Receipt';
                        case 4:
                            return 'New: Manual Receipt';
                        case 5:
                            return 'New: Non-receipt';
                        case 6:
                            return 'New: Paper Invoices';
                        case 7:
                            return 'New: Electronic Invoices';
                        default:
                            return '';
                    }

                case 2:
                    return 'User verified';
                default:
                    return 'Awaiting Verification';
            }
        };
    })
    .filter('monthDashYear', function() {
        return function(dateObj) {
            if (!angular.isObject(dateObj) && !angular.isDate(dateObj)) {
                return '';
            }

            var dateArr = dateObj.toDateString().split(' ');
            return dateArr[1] + '-' + dateArr[3];
        };
    })
    .filter('onlyDate', function() {
        return function(dateStr) {
            if (! dateStr) {
                return '';
            }

            if (angular.isObject(dateStr)) {
                dateStr = dateStr.toISOString().replace('T', ' ');
            }

            var dateArr = dateStr.split(' ');
            var dateObjArr = new Date(dateArr[0] + 'T' + dateArr[1]).toDateString().split(' ');
            return dateObjArr[2] + '-' + dateObjArr[1] + '-' + dateObjArr[3];
        };
    })
    .filter('onlyDateWithSpace', function() {
        return function (dateStr) {
            if (! dateStr) {
                return '';
            }

            var datePieces = dateStr.split(' ');
            return datePieces[2] + ' ' + datePieces[1] + ' ' + datePieces[3];

        }
    })
    .filter('onlyTime', function() {
        return function(dateStr) {
            if (!dateStr) {
                return '';
            }

            var dateArr = dateStr.split(' ');
            return dateArr[1];
        };
    })
    .filter('firstChar', function() {
        return function(str) {
            if (!str) {
                return '';
            }

            return str.substr(0, 1)
        };
    })
    .filter('categoryAbbr', function() {
        return function(str) {
            switch (str) {
                case 'personal_expense':
                    return 'PE';

                case 'travel_expense':
                    return 'TE';

                default:
                    return '';
            }
        };
    })
    .filter('tripLegInName', function() {
        return function(leg) {
            leg = parseInt(leg);
            if (leg) {
                return '- Leg ' + leg;
            } else {
                return '';
            }
        }
    })
    .filter('tripLeg', function() {
        return function(leg) {
            leg = parseInt(leg);
            if (leg) {
                return 'Leg ' + leg;
            } else {
                return '';
            }
        }
    });
