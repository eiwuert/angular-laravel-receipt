<div class="shell">
    <span class="left">&copy; 2014 - ReceiptClub</span>
    <span class="right">
        Design by <a href="http://receiptclub.com/" target="_blank" title="receiptclub.com">receiptclub.com</a>
    </span>
</div>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="http://momentjs.com/downloads/moment.js"></script>
<script src="http://css-tricks.com/examples/HorzScrolling/jquery.mousewheel.js"></script>
<script src="http://code.jquery.com/ui/1.11.2/jquery-ui.js"></script>
<script src="http://www.bootstrap-switch.org/dist/js/bootstrap-switch.js"></script>

<!-- End Footer -->
<Script Language="JavaScript">
    //Show loading screen on submitting
    $('#btn-submit').click(function() {
        $('#loading-overlay').show();
    });
    $("[name='Searchable']").bootstrapSwitch();
    
//    $(".auto-gene-findname").prop('disabled', true);
    $(".auto-gene-findname").val($(".merchant-name-input").val());
    
    function addField(value) {
        var allInput = $(value).prev('div').find('input');
        var firstInput = allInput.first();
        var html = $(firstInput)[0].outerHTML;
        $(value).prev('div').append(html);
        
        allInput = $(value).prev('div').find('input');
        var lastInput = allInput.last();
        $(lastInput).val('');
        $(lastInput).removeClass('auto-gene-findname');
    }
    $('.check-color').on('blur', function() {
        var valueOfInput = $(this).val();
        if (valueOfInput.length >= 5) {
            var substr = valueOfInput.substr(0, 5);
            if ((substr.indexOf('GEN') != -1) || (substr.indexOf('gen') != -1)) {
                $(this).removeClass('light-orange');
                $(this).removeClass('light-green');
                $(this).addClass('light-orange');    
            } else if ((substr.indexOf('BOT') != -1) || (substr.indexOf('bot') != -1)) {
                $(this).removeClass('light-orange');
                $(this).removeClass('light-green');
                $(this).addClass('light-green');    
            }
        } else {
            return;
        }
    });
        
    $('.merchant-name-input').keyup(function() {
        this.value = this.value.toLocaleUpperCase();
        $(".auto-gene-findname").val(this.value);
    });
    
    $(function() {
      $( ".findname-tooltip, .field-tooltip, .searchable-tooltip, .non-searchable-tooltip, .img-logo-tooltip, .merchant-name-tooltip" ).tooltip({
        show: {
            effect: "slideDown",
            delay: 250
        },
        hide: {
            effect: "slideUp",
            delay: 250
        },
        content: function () {
            return $(this).prop('title');
        },
        position: {
            my: "center top+0",
            at: "center+10 bottom"
        }
      });
      
      $( ".merchant-name-tooltip" ).tooltip({
        show: {
            effect: "slideDown",
            delay: 250
        },
        hide: {
            effect: "slideUp",
            delay: 250
        },
        content: function () {
            return $(this).prop('title');
        },
        position: {
            my: "left top+0",
            at: "left bottom"
        }
      });
    });
  
    $(".search-table-outter, .search-table-outter-nomargin").mousewheel(function(event, delta) {
        this.scrollLeft -= (delta * 60);
        event.preventDefault();
    });
        
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
