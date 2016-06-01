$(window).resize(function(){
	$('.app-table-child-wrapper').each(function(){
		var heightThis = $(this).height();
		var heightChild = $(this).children('.app-table-child').height();
		if ( heightThis > heightChild ) {
			$(this).closest('.app-table').find('.col-non').addClass('no-sroll');
			$(this).closest('.app-table').find('.col-apv, .col-tre').removeClass('pa-scroll');
			$(this).closest('.app-table').find('.col-tre').removeClass('pa-scroll');
		} else {
			$(this).closest('.app-table').find('.col-non').removeClass('no-sroll');
			$(this).closest('.app-table').find('.col-apv, .col-tre').addClass('pa-scroll');
			$(this).closest('.app-table').find('.col-tre').addClass('pa-scroll');
		}
	});
	$('#rd-items-table').each(function(){
		var heightThis = $(this).height();
		if ( heightThis > 94 ) {
			$(this).closest('#rd-items-table-wrapper').find('.share_head').show();
		} else {
			$(this).closest('#rd-items-table-wrapper').find('.share_head').hide();
		}
	});
	var b = $(window).height();
	var a = $('#sidebar-right').offset().top;
	var d = $('#sidebar-right2').offset().top;
	c = b - a - 10 ;
	e = b - d - 10;
	$('#sidebar-right').css('max-height', c);
	$('#sidebar-right2').css('max-height', e);

	$('.app-box').width($(window).width() - $('#container .sidebar-ad').width() - 20);

})

$(document).keydown(function(){
	if(event.keyCode == 27){  //escape key
		$('.guid-slide').css({'opacity': 0, 'z-index': '-100'});
	}
})

$('body').on('mouseenter', function(){
	$('.app-box').width($(window).width() - $('#container .sidebar-ad').width() - 20);
	var b = $(window).height();
	var a = $('#sidebar-right').offset().top;
	var d = $('#sidebar-right2').offset().top;
	c = b - a - 10 ;
	e = b - d - 10;
	$('#sidebar-right').css('max-height', c);
	$('#sidebar-right2').css('max-height', e);
	$('#rd-items-table').each(function(){
		var heightThis = $(this).height();
		if ( heightThis > 94 ) {
			$(this).closest('#rd-items-table-wrapper').find('.share_head').show();
		} else {
			$(this).closest('#rd-items-table-wrapper').find('.share_head').hide();
		}
	});
	$('.app-table-child-wrapper').each(function(){
		var heightThis = $(this).height();
		var heightChild = $(this).children('.app-table-child').height();
		if ( heightThis > heightChild ) {
			$(this).closest('.app-table').find('.col-non').addClass('no-sroll');
			$(this).closest('.app-table').find('.col-apv, .col-tre').removeClass('pa-scroll');
			$(this).closest('.app-table').find('.col-tre').removeClass('pa-scroll');
		} else {
			$(this).closest('.app-table').find('.col-non').removeClass('no-sroll');
			$(this).closest('.app-table').find('.col-apv, .col-tre').addClass('pa-scroll');
			$(this).closest('.app-table').find('.col-tre').addClass('pa-scroll');
		}
	});

})
function clickLink(e){
	e = e || window.event;
	var target = e.target || e.srcElement;
	var a = '#' + target.getAttribute("data-guid");
	$(a).css({'opacity': 1, 'z-index': 100});
	// caroufredsel slide
    if ($('#tutorial-slider-receiptbox').css('opacity') == '1'){
        $("#tutorial-slider-receiptbox .main-slide").carouFredSel({
            circular    : false,
            infinite    : false,
            scroll      : {
                fx          : "crossfade"
            },
            auto: {
                timeoutDuration: 4000,
                duration: 1000,
                play: false,
            },
            items       : {
                visible     : 1,
            },
            prev: {
                button: '.prev-button',
                key: 37,
            },
            next: {
                button: '.next-button',
                key: 39,
            },
            pagination  : ".slider-paging"
        });
        $("#tutorial-slider-travelexpense .main-slide").carouFredSel({
            circular    : false,
            infinite    : false,
            scroll      : {
                fx          : "crossfade"
            },
            auto: {
                timeoutDuration: 4000,
                duration: 1000,
                play: false,
            },
            items       : {
                visible     : 1,
            },
            prev: {
                button: '.prev-travel-button',
            },
            next: {
                button: '.next-travel-button',
            },
            pagination  : ".slider-travel-paging"
        });
    };
    if ($('#tutorial-slider-travelexpense').css('opacity') == '1'){
        $("#tutorial-slider-travelexpense .main-slide").carouFredSel({
            circular    : false,
            infinite    : false,
            scroll      : {
                fx          : "crossfade"
            },
            auto: {
                timeoutDuration: 4000,
                duration: 1000,
                play: false,
            },
            items       : {
                visible     : 1,
            },
            prev: {
                button: '.prev-travel-button',
                key: 37,
            },
            next: {
                button: '.next-travel-button',
                key: 39,
            },
            pagination  : ".slider-travel-paging"
        });
        $("#tutorial-slider-receiptbox .main-slide").carouFredSel({
            circular    : false,
            infinite    : false,
            scroll      : {
                fx          : "crossfade"
            },
            auto: {
                timeoutDuration: 4000,
                duration: 1000,
                play: false,
            },
            items       : {
                visible     : 1,
            },
            prev: {
                button: '.prev-button',
            },
            next: {
                button: '.next-button',
            },
            pagination  : ".slider-paging"
        });
    };
    $('.app-box').width($(window).width() - $('#container .sidebar-ad').width() - 20);
}