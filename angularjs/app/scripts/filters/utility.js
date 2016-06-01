'use strict';
angular.module('rciSpaApp.utilityFilters', [])
    .filter('itemStatus', function() {
        return function(status) {
            status = parseInt(status);
            switch (status) {
                case 1:
                    return 'Auto-categorized';
                case 2:
                    return 'Categorized';
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
			if (!angular.isDefined(receipt)) {
				return '';
			}

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
                            return 'New: Paper Invoice';
                        case 7:
                            return 'New: Electronic Invoices';
                        default:
                            return '';
                    }

                case 2:
                    return 'Validated';

                case 3:
                    return 'Unrecognized';

                case 4:
                    return 'Modified'

                default:
                    return 'Awaiting Validation';
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
    .filter('fullDateWithDash', function() {
        return function(dateObj) {
            if (!angular.isObject(dateObj) && !angular.isDate(dateObj)) {
                return '';
            }

//            var dateArr = dateObj.toDateString().split(' ');
            return dateObj.getFullYear() + '-' + dateObj.getMonth() + '-' + dateObj.getDate();
        };
    })
    .filter('trustURL', function($sce) {
        return function (val) {
//            return $sce.trustAsResourceUrl(val);
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
    .filter('onlyMonthSpaceYear', function() {
        return function(dateStr) {
            if (! dateStr) {
                return '';
            }

            var datePieces = dateStr.split(' ');
            return datePieces[1] + ' ' + datePieces[3];
        };
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

                case 'education_expense':
                    return 'EE';

                case 'personal_assets':
                    return 'PA';

                case 'business_expense':
                    return 'BE';

                case 'business_assets':
                    return 'BA';
                default:
                    return '';
            }
        };
    })
    .filter('categoryAppName', function() {
        return function(str) {
            if (!str) return;

            switch (str.toLowerCase()) {
                case 'pe':
                case 'personal_expense':
                    return 'PersonalExpense';

                case 'te':
                case 'travel_expense':
                    return 'TravelExpense';

                case 'ee':
                case 'education_expense':
                    return 'EducationExpense';

                case 'pa':
                case 'personal_assets':
                    return 'PersonalAssets';

                case 'ba':
                case 'business_assets':
                    return 'BusinessAssets';

                case 'be':
                case 'business_expense':
                    return 'BusinessExpense';

                case 'mx':
                    return 'Mixed';

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
    })
    .filter('formatDate', function() {
        return function(date, format) {
            if (!date) {    return '';    }

            if (angular.isObject(date)) {
                date = date.toISOString();
            }

            // remove incorrect expense period (format like "1969", "1970"
            if (date.indexOf('1969') != -1 || date.indexOf('1970') != -1) {
                return '';
            }

            var tmp = date.split('T');
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var dateParts = tmp[0].split('-');

            switch (format) {
                case 'dd-MMM-yyyy':
                    return dateParts[2] + '-' + months[parseInt(dateParts[1]) - 1] + '-' + dateParts[0];

                case 'HH:mm:ss':
                    return tmp[1].substr(0, 8);

                case 'yyyy-MM-dd':
                    return tmp[0];

                case 'MMM-yyyy':
                    return months[parseInt(dateParts[1]) - 1] + '-' + dateParts[0];

                default:
                    return '';
            }
        }
    })
    .filter('receiptTypeFilterFormat', function() {
        return function(str) {
            switch (str) {
                case 'digitalReceipts':
                    return 'Digital';

                case 'newReceipts':
                    return 'New';

                case 'emailReceipts':
                    return 'Email';

                case 'paperReceipts':
                    return 'Paper';

                case 'manualReceipts':
                    return 'Manual';

                case 'nonReceipts':
                    return 'Non -';

                case 'paperInvoices':
                    return 'Paper Invoice';

                case 'electronicInvoices':
                    return 'Electronic Invoice';

                case 'archivedReceipts':
                    return 'Archived';

                default:
                    return '';
            }
        }
    })
    .filter('receiptTypeFilterFullFormat', function() {
        return function(str) {
            switch (str) {
                case 'digitalReceipts':
                    return 'Digital Receipts';

                case 'emailReceipts':
                    return 'Email Receipts';

                case 'paperReceipts':
                    return 'Paper Receipts';

                case 'manualReceipts':
                    return 'Manual Receipts';

                case 'nonReceipts':
                    return 'Non - Receipts';

                case 'paperInvoices':
                    return 'Paper Invoices';

                case 'electronicInvoices':
                    return 'Electronic Invoices';

                case 'archivedReceipts':
                    return 'Archived Receipts';

                default:
                    return '';
            }
        }
    })
    .filter('tripTypeFilterFullFormat', function() {
        return function(str) {
            switch (str) {
                case 'past':
                    return 'Past Trips';

                case 'current':
                    return 'Current Trips';

                case 'upcoming':
                    return 'Upcoming Trips';

                case 'reported':
                    return 'Reported Trips';

                case 'archived':
                    return 'Archived Trips';

                case 'all':
                    return 'All Trips';

                default:
                    return '';
            }
        }
    })
    .filter('forApprover', function() {
        return function(str) {
            if (typeof str == 'undefined') return;

            switch (str.toLowerCase()) {
                case 'pending'   : return 'Pending Approval';
                case 'approved'  : return 'Approved';
                case 'rejected'  : return 'Rejected';
            }
        }
    })
  .filter('forTrip', function() {
    return function(str) {
      if (typeof str == 'undefined') return;

      switch (str.toLowerCase()) {
        case ''   : return 'Draft';
        case 'draft'  : return 'Draft - in Report';
        case 'submitted'  : return 'Pending Approval';
        case 'approved'  : return 'Approved';
        case 'rejected'  : return 'Rejected';
      }
    }
  })
    .filter('forSubmitter', function() {
        return function(str) {
            if (typeof str == 'undefined') return;

            switch (str.toLowerCase()) {
                case 'draft'     : return 'Draft';
                case 'submitted' : return 'Pending Approval';
                case 'approved'  : return 'Approved';
                case 'rejected'  : return 'Rejected';
            }
        }
    })
    .filter('reportStatus', function() {
        return function(str) {
            if (typeof str == 'undefined') return;

            switch (str.toLowerCase()) {
                case 'draft'     : return 'Draft';
                case 'submitted' : return 'Pending Approval';
                case 'pending'   : return 'Pending Approval';
                case 'approved'  : return 'Approved';
                case 'rejected'  : return 'Rejected';
            }
        }
    })
    .filter('reportTypeFilterFullFormat', function() {
        return function(str) {
            switch (str) {
                case 'draft':
                    return 'Draft Reports';

                case 'submitted':
                    return 'Submitted Reports';

                case 'pending':
                    return 'Pending Reports';

                case 'approved':
                    return 'Approved Reports';

                case 'rejected':
                    return 'Rejected Reports';

                case 'archived':
                    return 'Archived Reports';

                case 'all':
                    return 'All Reports';

                default:
                    return '';
            }
        }
    })
    /**
     * Dynamic filter that could parse defined filters and run it
     * Work as a directive to call a filter dynamically via scope variable
     */
    .filter('dynamicFilter', function($filter){
        return function(value, flt) {
            if (angular.isDefined(flt)) {
                flt = flt.split(":");

                return $filter(flt[0])(value, flt[1] || null);
            } else {
                return value;
            }
        }
    });
