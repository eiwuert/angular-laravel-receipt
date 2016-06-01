$(window).resize(function(){
    var h = $('.info-items').width();
    var k = $('.total-container').width();
    var l = h - k - 80;
    $('.info-items .text-error').width(l);
    $('.info-items .text-error').each(function(){
        var str = $(this).not('i').text()
        var str = str.replace(/\s+/g, ' ');
        $(this).attr('title', str);
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

	$('.safari .app-box').width($(window).width() - $('#container .sidebar-ad').outerWidth() - 20);

})

$(document).keydown(function(event){
	 if(event.keyCode == 27){  //escape key
		$('.guid-slide').css({'opacity': 0, 'z-index': '-100'});
	}
})

$('body').on('mouseenter', function(){
	//$('.app-box').width($(window).width() - $('#container .sidebar-ad').width() - 20);
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
function clickLink(e) {

}

