rciSpaApp.controller('AnalyticCtrl', ['$scope', '$rootScope', '$location', 'Restangular', '$filter', '$route', function($scope, $rootScope, $location, Restangular, $filter, $route){
    $scope.chartType = 'column';
    $scope.filterBy = 'Category';
    $scope.monthRange = 1;
    $scope.TotalAmount = 0;
    $scope.filtering = false;
    $scope.resetFilter = false;

    try {
        var defaultDate = new timezoneJS.Date(new Date(), $rootScope.loggedInUser.Timezone);
    } catch (err) {
        var defaultDate = new Date();
    }

    if ($route.current.params.hasOwnProperty('dateFrom')) {
        $scope.periodFrom = $route.current.params.dateFrom;
    } else {
        $scope.periodFrom = defaultDate.getFullYear() + '-' + ('0' + defaultDate.getMonth()).slice(-2) + '-01';
    }

    if ($route.current.params.hasOwnProperty('dateTo')) {
        $scope.periodTo = $route.current.params.dateTo;
    } else {
        $scope.periodTo = defaultDate.getFullYear() + '-' + ('0' + (defaultDate.getMonth() + 1)).slice(-2) + '-' + ('0' + defaultDate.getDate()).slice(-2);
    }

    var currentPath = $location.path();

    $scope.currentApp = "personal_expense";

    if (currentPath.toLowerCase().indexOf("personal-expense") >= 0) {
        $scope.currentApp = "personal_expense";
    } else if (currentPath.toLowerCase().indexOf("business-expense") >= 0) {
        $scope.currentApp = "business_expense";
    } else if (currentPath.toLowerCase().indexOf("education-expense") >= 0) {
        $scope.currentApp = "education_expense";
    } else if (currentPath.toLowerCase().indexOf("personal-assets") >= 0) {
        $scope.currentApp = "personal_assets";
    } else if (currentPath.toLowerCase().indexOf("business-assets") >= 0) {
        $scope.currentApp = "business_assets";
    }

    $scope.tmpPeriodFrom = angular.copy($scope.periodFrom);
    $scope.tmpPeriodTo = angular.copy($scope.periodTo);

    $scope.gridData = [];

    $scope.chartData = {
        chart: {
            type: 'column',
            animation: false,
//            margin: [85, 30, 300, 40],
            //backgroundColor: 'rgba(0,0,0,0)'
            plotBackgroundColor: null,
            plotBorderWidth: null,
            plotShadow: false
        },
        title: {
            text: ''
        },
        credits : {
            text: ''
        },
        subtitle: {
            text: '',
            align: 'left',
            x: 3,
            y: 31,
            style: {
                color: '#333',
                fontSize: '12px',
                fontWeight: 'bold',
                fontFamily:'Arial'
            }
        },
        legend: {
            enabled: false
        },
        series: [{
            data: [],
            dataLabels: {
                //enabled: true,
                rotation: -90,
                color: '#fff',
                align: 'right',
                x: 4,
                y: 10,
                style: {
                    fontSize: '12px',
                    fontFamily: 'Verdana, sans-serif'
                    //textShadow: '0 0 3px black'
                }
            }
        }]
    }
    var periodTitle = '';
    $scope.renderChart = function() {
        $scope.isTrendChartMode = false;
        $scope.periodFrom = angular.copy($scope.tmpPeriodFrom);
        $scope.periodTo = angular.copy($scope.tmpPeriodTo);

        Restangular.one('categories').getList('analytics', {
            app: $scope.currentApp,
            filter: $scope.filterBy,
            dateFrom: $scope.periodFrom,
            dateTo: $scope.periodTo
        }).then(function(response) {
            $rootScope.isLoadingAnalytic = false;
            $("body").removeClass("loading");
            $scope.monthRange = parseInt(response.Months);
            $scope.TotalAmount = parseFloat(response.TotalAmount).toFixed(2);
            $scope.chartData.chart.type = $scope.chartType;
            $scope.chartData.series[0].data = [];
            $scope.chartData.series[0].dataLabels.enabled = true;
            if ($scope.chartType == 'column') {
                $scope.chartData.series[0].dataLabels.enabled = false;
            }
            if ($scope.chartType == 'pie') {
                delete $scope.chartData.colors;
                //delete $scope.chartData.tooltip;
                $scope.chartData.tooltip = {
                    formatter: function() {
                        return 'Amount of <b>'+ this.point.name +'</b><br/>'+
                            'for ' + periodTitle + ' is ' + Highcharts.numberFormat(this.y, 2);
                    }
                }
                $scope.chartData.series[0]
                $scope.chartData.plotOptions = {
                    pie: {
                            allowPointSelect: true,
                            cursor: 'pointer',
                            dataLabels: {
                            enabled: true,
                            color: '#000000',
                            connectorColor: '#000000',
                            format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                            animation: false
                        }
                    },
                    series: {
                        animation: false
                    }
                }
                $scope.chartData.series[0].dataLabels.color = '#000';
                $scope.chartData.series[0].dataLabels.rotation = 0;
            } else {
                $scope.chartData.series[0].dataLabels.color = '#fff';
                $scope.chartData.series[0].dataLabels.rotation = -90;
                $scope.chartData.colors = ['#0e4d9a'];
                $scope.chartData.tooltip = {
                    formatter: function() {
                        return 'Amount of <b>'+ this.x +'</b><br/>'+
                            'for ' + periodTitle + ' is ' + Highcharts.numberFormat(this.y, 2);
                    }
                }
                $scope.chartData.xAxis = {
                    gridLineWidth: 0,
                    minorGridLineWidth: 0,
                    lineColor: 'transparent',

                    categories: [],
                    labels: {
                        rotation: -90,
                        align: 'left',
                        style: {
                            fontSize: '12px',
                            fontFamily: 'Verdana, sans-serif',
                            color: '#fe9a0e'
                        }
                    }
                }

                $scope.chartData.yAxis = {
                    min: 0,
                        title: {
                        text: ''
                    }
                };
                $scope.chartData.series[0].type = null;
                $scope.chartData.plotOptions = {}
                $scope.chartData.plotOptions[$scope.chartType] = {
                    animation: false
                }
            }

            // Reset current data grid
            $scope.gridData = [];

            $scope.filtering = false;

            if ($scope.filterBy == 'Category') {
                angular.forEach(response.Categories, function(v, k) {
                    var item = {
                        ID: v.CategoryID,
                        Name: v.Name,
                        //Currency: 'USD',
                        Currency: $rootScope.loggedInUser.CurrencyCode,
                        Amount: parseFloat(v.Amount),
                        AverageAmount: parseFloat(v.AverageAmount),
                        Type: 'Category',
                        IsHighlight: false
                    }
                    $scope.gridData.push(item);

                    if ($scope.chartType == 'pie') {
                        $scope.chartData.xAxis.categories = [];
                        $scope.chartData.series[0].data.push([v.Name, parseFloat(v.Amount)]);
                    } else {
                        $scope.chartData.xAxis.categories.push(v.Name);
                        $scope.chartData.series[0].data.push(parseFloat(v.Amount));
                    }
                });
            } else {
                angular.forEach(response.Merchants, function(v, k) {
                    var item = {
                        ID: v.MerchantID,
                        Name: v.MerchantName,
                        //Currency: 'USD',
                        Currency: $rootScope.loggedInUser.CurrencyCode,
                        Amount: parseFloat(v.Amount),
                        AverageAmount: parseFloat(v.AverageAmount),
                        Type: 'Merchant',
                        IsHighlight: false
                    }
                    $scope.gridData.push(item);

                    if ($scope.chartType == 'pie') {
                        $scope.chartData.xAxis.categories = [];
                        $scope.chartData.series[0].data.push([v.MerchantName, parseFloat(v.Amount)]);
                    } else {
                        $scope.chartData.xAxis.categories.push(v.MerchantName);
                        $scope.chartData.series[0].data.push(parseFloat(v.Amount));
                    }
                });
            }
            //console.debug($scope.chartData.chart);
            jQuery('#chart-container').highcharts($scope.chartData);
            }, function(response) {
            if (response.status !== 200) {
                console.log(response.data.message);
            }
        });
    }

    $scope.viewTrendChart = function(item) {

        if ($("#chart-table a.icon-line-chart").hasClass("disabled")) {
            return;
        } else {
            $("#chart-colum").removeClass("active");
            $("#chart-pie").removeClass("active");
            angular.forEach($scope.gridData, function(v, k) {
                v.isHighlight = false;
            })
            $scope.chartType = 'line';
            var trendName = item.Name;
            var id = item.ID;
            item.isHighlight = true;

            if ($scope.monthRange < 2) {
                return false;
            }

            $scope.trendName = trendName;
            $scope.isTrendChartMode = true;

            $scope.periodFrom = angular.copy($scope.tmpPeriodFrom);
            $scope.periodTo = angular.copy($scope.tmpPeriodTo);

            Restangular.one('categories').getList('analytics', {
                id: id,
                app: $scope.currentApp,
                filter: $scope.filterBy,
                dateFrom: $scope.periodFrom,
                dateTo: $scope.periodTo
            }).then(function(response) {
                $scope.monthRange = parseInt(response.Months);

                var trendChartData = {
                    chart: {
                        type: 'line',
                        plotBackgroundColor: null,
                        plotBorderWidth: null,
                        plotShadow: false,
                        margin: [ 85, 30, 120, 40],
                        backgroundColor: 'rgba(0,0,0,0)',
                        animation: false
                    },
                    title : {
                        text: ''
                    },
                    credits: {
                        text: ''
                    },
                    xAxis: {
                        gridLineColor: '#C6C6C6',
                        gridLineWidth: 0.8,
                        gridLineDashStyle: 'longdash',
                        //categories: [],
                        labels : {
                            rotation: -90,
                            y: 40,
                            style: {
                                fontWeight: 'bold',
                                fontFamily: 'Arial',
                                fontSize: '11px',
                                color: '#000'
                            }
                        }
                    },
                    yAxis: {
                        gridLineColor: '#C6C6C6',
                        gridLineWidth: 0.8,
                        gridLineDashStyle: 'longdash',
                        title: {
                            text: ''
                        },
                        labels : {
                            formatter: function() {
                                return this.value;
                            },
                            style: {
                                fontWeight: 'bold',
                                fontFamily: 'Arial',
                                fontSize: '13px'
                            }
                        },
                        min: 0,
                            plotLines: [{
                            value: 0,
                            width: 1,
                            color: '#808080',
                            fontFamily:'Arial'
                        }]
                    },
                    plotOptions: {
                        line: {
                            animation: false,
                            dataLabels: {
                                enabled: false,
                                formatter: function() {
                                    return Highcharts.numberFormat(this.y, 2);
                                }
                            },
                            events: {
                                legendItemClick: function () {
                                   return false;
                                }
                            }
                        }
                    },
                    tooltip: {
                        formatter: function() {
                            return '<b> '+ this.series.name +'</b><br/>'+
                                this.x +': '+ this.y;
                        }
                    },
                    series: [{
                        name: $scope.trendName,
                        color:'#1794E8',
                        data: []
                    }]
                }

                trendChartData.xAxis.categories = [];
                var monthNames = [ "January", "February", "March", "April", "May", "June",
                    "July", "August", "September", "October", "November", "December" ];

                if ($scope.filterBy == 'Category') {
                    angular.forEach(response.Categories, function(v, k) {
                        var month = new Date(v.Date * 1000).getMonth();

                        trendChartData.xAxis.categories.push(monthNames[month]);
                        trendChartData.series[0].data.push(parseFloat(v.Amount));
                    });
                } else {
                    angular.forEach(response.Merchants, function(v, k) {
                        var month = new Date(v.Date * 1000).getMonth();

                        trendChartData.xAxis.categories.push(monthNames[month]);
                        trendChartData.series[0].data.push(parseFloat(v.Amount));
                    });
                }

                $scope.filtering = false;

                jQuery('#chart-container').highcharts(trendChartData);
            }, function(response) {
                if (response.status !== 200) {
                    console.log(response.data.message);
                }
            });
        }
    }

    $scope.$watch('chartType + filterBy', function(n, o, s) {
        if($scope.chartType != 'line') {
            $scope.setPeriodFilter();
        }
    });

    $scope.setPeriodFilter = function() {
        //$scope.showDateFilter = !$scope.showDateFilter;
         $("#calendar-wrapper").css("display", "none");
        periodTitle = $filter('date')($scope.periodFrom, 'MMM-yyyy') + ' - ' +  $filter('date')($scope.periodTo, 'MMM-yyyy');
        $scope.renderChart();
    }

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

    $scope.goToBE = function() {
        $location.url('/business-expense');
        $('#nav-expense-management').click();
        setTimeout(function() {
            $('.page-app').hide();
            $('#ngview-wrapper').hide();
            $('#home-page-wrapper').hide();
            $('#business-expense-wrapper').show();

            $('#container').removeClass('landing-wrapper');
            $('#top-header').removeClass('hide').addClass('show');
            $('#sidebar-right').removeClass('hide').addClass('show');
            $('body').removeClass('front').removeClass('profile');
        }, 1000);
    }

    $scope.goToEE = function() {
        $location.url('/education-expense');
        $('#nav-expense-management').click();
        setTimeout(function() {
            $('.page-app').hide();
            $('#ngview-wrapper').hide();
            $('#home-page-wrapper').hide();
            $('#education-expense-wrapper').show();

            $('#container').removeClass('landing-wrapper');
            $('#top-header').removeClass('hide').addClass('show');
            $('#sidebar-right').removeClass('hide').addClass('show');
            $('body').removeClass('front').removeClass('profile');
        }, 1000);
    }

    $scope.goToPA = function() {
        $location.url('/personal-assets');
        $('#nav-expense-management').click();
        setTimeout(function() {
            $('.page-app').hide();
            $('#ngview-wrapper').hide();
            $('#home-page-wrapper').hide();
            $('#personal-assets-wrapper').show();

            $('#container').removeClass('landing-wrapper');
            $('#top-header').removeClass('hide').addClass('show');
            $('#sidebar-right').removeClass('hide').addClass('show');
            $('body').removeClass('front').removeClass('profile');
        }, 1000);
    }

    $scope.goToBA = function() {
        $location.url('/business-assets');
        $('#nav-expense-management').click();
        setTimeout(function() {
            $('.page-app').hide();
            $('#ngview-wrapper').hide();
            $('#home-page-wrapper').hide();
            $('#business-assets-wrapper').show();

            $('#container').removeClass('landing-wrapper');
            $('#top-header').removeClass('hide').addClass('show');
            $('#sidebar-right').removeClass('hide').addClass('show');
            $('body').removeClass('front').removeClass('profile');
        }, 1000);
    }

    $scope.backtoDashboard = function(){
        $location.path('/dashboard');
        $('#menu-dashboard').click();
        setTimeout(function(){
            $('#menu-dashboard').removeClass('blue');
        },10);
    }
}]);
