<div class="shell">
    <span class="left">&copy; 2014 - ReceiptClub</span>
    <span class="right">
        Design by <a href="http://receiptclub.com/" target="_blank" title="receiptclub.com">receiptclub.com</a>
    </span>
</div>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="http://momentjs.com/downloads/moment.js"></script>
<!-- End Footer -->
<Script Language="JavaScript">
    var d = new Date();
    var utcDay = d.getUTCDate();
    var utcYear = d.getUTCFullYear();
    var utcMonth = d.getUTCMonth() + 1;
    var utcDate = utcYear + '-' + utcMonth + '-' + utcDay;
    
    function GetTime() {
        var dt = new Date();
        var def = dt.getTimezoneOffset()/60;
        var gmt = (dt.getHours() + def);
        
        var _GMT = check24(((gmt) > 24) ? ((gmt) - 24) : (gmt));

        document.getElementById('_GMT').innerHTML = utcDate + ' | ' + (IfZero(_GMT) + ":" + IfZero(dt.getMinutes()) + ":" + IfZero(dt.getSeconds()));
        setTimeout("GetTime()", 1000);
    }
    function IfZero(num) {
        return ((num <= 9) ? ("0" + num) : num);
    }
    function check24(hour) {
        return (hour >= 24) ? hour - 24 : hour;
    }
    
    Calendar.setup({
        trigger    : "StartTime",
        inputField : "StartTime",
        showTime   : 24,
        dateFormat : "%Y-%m-%d %H:%M",
        onSelect   : function() { this.hide() },
        minuteStep : 1
    });

    Calendar.setup({
        trigger    : "EndTime",
        inputField : "EndTime",
        showTime   : 24,
        dateFormat : "%Y-%m-%d %H:%M",
        onSelect   : function() { this.hide() },
        minuteStep : 1
    });
</Script>