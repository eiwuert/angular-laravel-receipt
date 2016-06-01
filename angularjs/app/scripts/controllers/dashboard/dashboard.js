rciSpaApp.controller('DBCtrl', function($scope, $timeout, $rootScope, Restangular, $route, $location, localStorageService){
    $('.app-db .app-headmenu').resizeHeight();
    $scope.screenName = 'Dashboard';

    $scope.screenNumber = 3;
    $scope.$on('OPEN_DASHBOARD', function(e, tabNav){
        $('#menu-dashboard').removeClass('blue');
        $timeout(function(){
            $scope.screenName = 'My Account';
            switch(tabNav){
                case 'help':
                    $scope.screenName = 'Help Center';
                    $scope.screenNumber = 2;
                    $('#db-help_center').click();
                    break;
                case 'about':
                    $scope.screenName = 'About Us';
                    $scope.screenNumber = 1;
                    $('#db-about-us').click();
                    break;
                case 'dashbroad':
                    $scope.screenName = 'Dashboard';
                    $scope.screenNumber = 3;
                    $('#menu-dashboard').addClass('blue');
                    $('.db-boxs#db-dashboard').show();
                    break;
                case 'mysetting':
                    $scope.screenName = 'My Account';
                    $scope.screenNumber = 0;
                    break;
            }
        });
    });

    /*
    * Get Persional Expense in current month
    * */
    $scope.databoxExpense = [ 
        {
            'personal_expense': 
            [
                { code: 'totalthismoth', name: 'Total of this month', param: 0,amountMonth: 1},
                { code: '3recentmonth', name: 'Recent 3 months', param: 0,amountMonth : 3},
                { code: 'totalofmerchant', name: 'Total No of merchant', param: 0,amountMonth : 0}
            ],
        },
        {   'business_expense':
            [
                { code: 'totalthismoth', name: 'Total of this month', param: 0,amountMonth: 1},
                { code: '3recentmonth', name: 'Recent 3 months', param: 0,amountMonth : 3},
                { code: 'totalofmerchant', name: 'Total No of merchant', param: 0,amountMonth : 0}
            ]
        }];
    
    $scope.countExpense = function(){
        try { var date = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone); } catch (err) { var date = new Date(); }
        $scope.dateFrom = date.getFullYear()  + '-' +('0' + (date.getMonth() + 1)).slice(-2)+ '-' + '01';
        if(date.getMonth() - 1 <= 0) {
            $scope.dateFrom3month = date.getFullYear()-1  + '-' +(12 + date.getMonth() - 1 ) + '-' + '01';
        } else {
            $scope.dateFrom3month = date.getFullYear()  + '-' +('0' + (date.getMonth() - 1) ).slice(-2)+ '-' + '01';
        }
        $scope.dateTo = date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
        angular.forEach($scope.databoxExpense,function(v,k){
            var arrayKeys = Object.keys(v);
            var appName = arrayKeys[0];
            var app = v[appName];
            Restangular.one('categories').customGET('',{app: appName,dateFrom: $scope.dateFrom, dateTo: $scope.dateTo}).then(function(response) {
                var item = 0;
                angular.forEach(response, function (value, key){
                    if(value.Amount != null){
                        item += parseFloat(value.Amount);
                    }
                });
                angular.forEach(app,function(v,k){
                    if(v.code == 'totalthismoth'){
                        v.param = $rootScope.loggedInUser.CurrencyCode + ' '+item.toFixed(2);
                    }
                });
            });
            Restangular.one('categories').customGET('',{app: appName,dateFrom: $scope.dateFrom3month, dateTo: $scope.dateTo}).then(function(response) {
                var item = 0;
                angular.forEach(response, function (value, key){
                    if(value.Amount != null){
                        item += parseFloat(value.Amount);
                    }
                });
                angular.forEach(app,function(v,k){
                    if(v.code == '3recentmonth'){
                        v.param = $rootScope.loggedInUser.CurrencyCode + ' ' +item.toFixed(2);
                    }
                });
            });
            Restangular.one('categories').getList('analytics', {app: appName,filter: 'Merchant',dateFrom: $scope.dateFrom3month, dateTo: $scope.dateTo }).then(function(response) {
                angular.forEach(app,function(v,k){
                    if(v.code == 'totalofmerchant'){
                        v.param = response[1].length;
                    }
                });
            }, function(response) {
                if (response.status !== 200) {
                    console.log(response.data.message);
                }
            });
        });
    }
    $scope.countExpense();

    /**
     * Count Travel Expense
     */
    $scope.countTravelexpense = [
        { totalReport: '-', name: 'All Reports', code: 'all'},
        { totalReport: '-', name: 'Draft Reports', code: 'draft'},
        { totalReport: '-', name: 'Submitted Reports', code: 'submitted'},
        { totalReport: '-', name: 'Pending Reports', code: 'pending'},
        { totalReport: '-', name: 'Approved Reports', code: 'approved'},
        { totalReport: '-', name: 'Rejected Reports', code: 'rejected'}
    ];
    $scope.filterTypeList = [
        { totalofRC: '-', code: 'all', name: 'All Receipts'},
        { totalofRC: '-', code: 'newReceipts', name: 'New Receipts'},
        { totalofRC: '-', code: 'digitalReceipts', name: 'Digital Receipts'},
        { totalofRC: '-', code: 'emailReceipts', name: 'Email Receipts'},
        { totalofRC: '-', code: 'paperReceipts', name: 'Paper Receipts'},
        { totalofRC: '-', code: 'manualReceipts', name: 'Manual Receipts'},
        { totalofRC: '-', code: 'nonReceipts', name: 'Non-Receipts'},
        { totalofRC: '-', code: 'paperInvoices', name: 'Paper Invoices'},
        { totalofRC: '-', code: 'electronicInvoices', name: 'Electronic Invoices'}
    ];
    $scope.$on('DB_UPDATE_COUNT', function (event, type, data){
        $timeout(function() {
            switch (type) {
                case 'travel':
                    $scope.countTravelexpense = data;
                    break;
                case 'receipt':
                    $scope.filterTypeList = data;
                    break;
            }
        });
    })
  });

