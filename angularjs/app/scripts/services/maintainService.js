/**
 * Service functions for check and apply Maintenance
 *
 */
app.service('MaintainService', function($rootScope, $location, Restangular, localStorageService) {
    $rootScope.maintain = {};
    var NOTICE_DIV = '#maintain-notice';
    var MAINTAIN_INFO = {};

    /**
     * Function to apply maintenance record
     *
     */
    function goMaintainMode (workingTime)
    {
        $location.path('/maintenance');
        $('#sidebar-right2').hide();
        var clock;
        clock = $('.clock-countdown').FlipClock({
            clockFace: 'HourlyCounter',
            countdown: true,
            autoStart: false,
            callbacks: {
                stop: function() {
                    window.location.reload(true);
                }
            }
        });
        clock.setTime(workingTime);
        clock.start();
    };

    /*
     * Check Server Maintenance
     */
    this.getMaintenanceStatus = function()
    {
        Restangular.one('maintenance').customGET('status').then(function (response) {
            if(response.status == "success") {
                goMaintainMode(response.data.TimeLeft);
            } else {
                if ($location.path() == '/maintenance') {
                    $location.path('/');
                }
            }
        }, function(response){
            var res = response.data;

            if (typeof res.upcoming.StartTime != 'undefined') {
                $rootScope.upcomingMaintain = {
                    startTime : res.upcoming.StartTime,
                    endTime   : res.upcoming.EndTime
                };
            }
        });
    };


    /**
     * Set time to display maintain notification
     * Rule: set counter only for maintenance record which are within today
     */
    this.checkUpcomingMaintenance = function ()
    {
        if (!angular.isDefined($rootScope.upcomingMaintain.startTime))
            return;

        var maintain = $rootScope.upcomingMaintain;
        var timeLeft = maintain.startTime - (new Date().getTime() / 1000);

        if (timeLeft < 24 * 3600) {
            MAINTAIN_INFO.startTime   = maintain.startTime;
            MAINTAIN_INFO.workingTime = maintain.endTime - maintain.startTime;

            //Notice user two hours before maintain
            this.setMaintainNoticeTimeout();

            $(NOTICE_DIV + ' button').on('click', function(){
                $(NOTICE_DIV).hide();
            });
        }
    };

    /**
     * Function to display notice of maintenance for users
     *
     */
    function displayMaintainNotice (minute)
    {
        var msg = "Server is going to be maintained in less than ";

        if (minute >= 60) {
            msg += Math.ceil ( minute / 60 ) + " hour(s)!!";
        } else {
            msg += minute + " minutes!!";
        }

        $(NOTICE_DIV + ' .notice-text').text(msg);
        $(NOTICE_DIV).show();
    };

    /**
     * Function to set time to display maintenance notice
     *
     */
    this.setMaintainNoticeTimeout = function ()
    {
        var minLeft = Math.floor ( ( MAINTAIN_INFO.startTime - (new Date().getTime() / 1000) ) / 60 );
        var timeFlags = [120, 60, 30, 20, 10, 5, 4, 3, 2, 1];

        //For the purpose of testing, set minLeft = 5 and seconds per minute is 3 (default is 60) : (minLeft - flag) * 6 * 1000)
        //minLeft = 5;

        angular.forEach (timeFlags, function(flag, k) {
            if (minLeft >= flag) {
                console.log('add clock for flag: ' + flag);
                console.log(MAINTAIN_INFO);
                setTimeout(function(){
                    displayMaintainNotice(flag);
                }, (minLeft - flag) * 60 * 1000);
            }
        });

        //Set the tick-tack timeout for apply maintenance when the time left is 0
        setTimeout(function () {
            $(NOTICE_DIV).hide();
            //goMaintainMode(MAINTAIN_INFO.workingTime);
        }, minLeft * 60 * 1000);
    };
});