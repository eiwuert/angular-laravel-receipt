var userData = localStorage.getItem('ls.userData');
userData = JSON.parse(userData);
if (!userData) {
    userData = {CurrencyCode: 'USD'}
}

// Define the tours!
var RBTour = {
    id: "receipt-box-tour",
    steps: [
        {
            content: "This area is called your <strong>ReceiptBox</strong>.",
            target: 'menu-receiptbox',
            placement: "right",
            yOffset: -20
        },
        {
            content: "Let's get started. Click on the <strong>Upload Receipt</strong> button bellow to start uploading your receipt.",
            target: '#rbUpload',
            placement: "top",
            xOffset: 13
        },
        {
            content: "When your receipt is ready, click on it to goto the <strong>Receipt Details</strong> Screen and check and verify the details on your receipt.",
            target: '#merchant-col',
            placement: "top",
            yOffset: 25,
            xOffset: 35
        }
    ]
};

var RDTourStepArray = [
    {
        content: "This is the <strong>Receipt Details</strong> screen.  Whenever you click on a receipt / invoice you'll come to this screen.",
        target: 'rd-title',
        placement: "bottom",
        xOffset: 'center',
        arrowOffset: 'center'
    },
    {
        content: "This is the <strong>Invoice Details</strong> screen.  Whenever you click on a receipt / invoice you'll come to this screen.",
        target: 'rd-title',
        placement: "bottom",
        xOffset: 'center',
        arrowOffset: 'center'
    },
    {
        content: "Check this section for information relating to the receipt merchant. You can update this information if you like.",
        target: 'rd-merchant-wrapper',
        placement: "left"
    },
    // Paper receipt tooltips
    {
        content: "This is an image of your original receipt.",
        target: ['.rd-ori-content', '.content-left-create-receipt', 'container-left', '.box-pdf-container'],
        placement: "right"
    },
    // End paper receipt tooltips
    // Invoice receipt tooltips
    {
        content: "This is an image of your original invoice.",
        target: '.rd-ori-content',
        placement: "right"
    },
    // End invoice receipt tooltips
    // Email receipt tooltips
    {
        content: "This is your original email receipt.",
        target: '.email-content-container',
        placement: "right"
    },
    // End email receipt tooltips
    {
        content: "You can edit these totals if they do not match with the ones on the original receipt.",
        target: '.box-main-amount',
        placement: "left"

    },
    {
        content: "Check these items next to make sure they tally with your receipt.",
        target: '#receipt-subtotal',
        placement: "top",
        xOffset: 'center',
        arrowOffset: 'center'
    },
    {
        content: "This area shows the line items on your receipt. Check these to make sure they came in ok. If you aren't too concerned about the line items don't worry about checking them.",
        target: '.table-scroll',
        placement: "top",
        xOffset: 'center',
        arrowOffset: 'center',
        yOffset: -20
    },
    {
        content: "Use this option to categorize the whole receipt as one <strong>Combined Item</strong>. This means you won't save any line item details for this receipt, rather a single item called <strong>Combined item</strong>.",
        target: '.one_item_row',
        placement: "left",
        yOffset: -20
    },
    {
        content: "Use this option to categorize all the items listed in the <strong>Line items</strong> section as a single category.<br/><br/>For example: If you have 7 items, using this option would categorize all 7 items as <strong>Grocery</strong>.",
        target: '.all_item_row',
        placement: "left",
        yOffset: -20
    },
    {
        content: "Use this option to categorize selected items in the line items section in a single category.<br/><br/>For Example: if you had 7 items, you could select 4 of them, and use this option to categorize all 4 items as <strong>Grocery</strong> without affecting the other 3.",
        target: '.selected_item_row',
        placement: "left",
        yOffset: -20
    },
    {
        content: "Use this option to categorize each line item individually.<br/><br/>For Example: you have 7 items, you can set a category for each item individually.",
        target: '.footer-top label',
        placement: "right",
        yOffset: -23
    },
    {
        content: "You can select the APP you'd like to categorize this item into.",
        target: '.one_item_row .app-col .btn-group',
        placement: "top",
        xOffset: 'center',
        arrowOffset: 'center',
        yOffset: -20
    },
    {
        content: "And choose your category here.",
        target: '.one_item_row .cat-col',
        placement: "top",
        xOffset: 'center',
        arrowOffset: 'center',
        yOffset: -15
    },
    {
        content: "Once you are done categorizing all items on this receipt, click on the <strong>Validate</strong> button.",
        target: ['#btn-validate-receipt-detail',],
        placement: "left",
        xOffset: -15,
        arrowOffset: 'top',
        yOffset: -20
    },
    {
        content: "Click on the <strong>convert</strong> button to convert this to your home currency: <strong>" + userData.CurrencyCode + "</strong>.",
        target: '#curentcyLabel',
        placement: "left",
        yOffset: -25
    },
    {
        content: "When you finish with this receipt, just click on the close button to go back to receipt box.",
        target: 'rd-close',
        placement: "bottom",
        xOffset: -280,
        arrowOffset: 270
    }
];

var BuildUpRDTour = function () {
    var stepsArray = RDTourStepArray.slice();
    //if it's invoice receipt remove item
    ($("#rd-title").hasClass('invoice-receipt-title')) ? stepsArray.splice(0, 1) : stepsArray.splice(1, 1);
    //if it's invoice remove item
    ($("#rd-title").hasClass('invoice-receipt-title')) ? stepsArray.splice(2, 1) : stepsArray.splice(3, 1);
    ($("#rd-title").hasClass('invoice-receipt-title')) ? stepsArray.splice(3, 1) : stepsArray.splice(4, 1);
    //if it's email receipt -> remove item 2 in array
    ($(".box-pdf-container").hasClass('email-receipt-html')) ? stepsArray.splice(2,1) : stepsArray.splice(3,1);

    var tour = {
        id: "receipt-detail-tour",
        steps: stepsArray
    };

    return tour;
}


var PETour = {
    id: "personal-expense-tour",
    steps: [
        {
            content: "Welcome to your <strong>PersonalExpense</strong> application. This is the main <strong>PersonalExpense</strong> frame. In this area, you can see expenses categorized by their main categories.",
            target: 'menu-personal-expense',
            placement: "right",
            yOffset: -20
        },
        {
            content: "Click on this button to expand the category and reveal subcategories, along with any amounts that have been categorized at this level.",
            target: '#personal-expense-wrapper .tb-pe.app-pe tr .icon-circle',
            placement: "top",
            xOffset: -23
        },
        {
            content: "Use this area to select a date, or range of dates to view your expenses by <strong>PersonalExpense</strong> Category.",
            target: '#personal-expense-wrapper .filterdate .checkbox-range',
            placement: "right",
            yOffset: -25,
            onNext: function () {
                $('#personal-expense-wrapper .btn.add-items').click();
            }
        },
        {
            content: "Click here to add an expense at the APP level. You can choose whether to create a manual receipt, or select one from the <strong>ReceiptBox</strong>.",
            target: '#personal-expense-wrapper .btn.add-items',
            placement: "right",
            yOffset: -20
        }
    ]
};

var BETour = {
    id: "business-expense-tour",
    steps: [
        {
            content: "Welcome to your <strong>BusinessExpense</strong> application. This is the main <strong>BusinessExpense</strong> frame. In this area, you can see expenses categorized by their main categories.",
            target: 'menu-business-expense',
            placement: "left",
            yOffset: -20
        },
        {
            content: "Click on this button to expand the category and reveal subcategories, along with any amounts that have been categorized at this level.",
            target: '#business-expense-wrapper .tb-pe.app-be tr .icon-circle',
            placement: "top",
            xOffset: -23
        },
        {
            content: "Use this area to select a date, or range of dates to view your expenses by <strong>BusinessExpense</strong> Category.",
            target: '#business-expense-wrapper .filterdate .checkbox-range',
            placement: "right",
            yOffset: -25,
            onNext: function () {
                $('#business-expense-wrapper .btn.add-items').click();
            }
        },
        {
            content: "Click here to add an expense at the APP level. You can choose whether to create a manual receipt, or select one from the <strong>ReceiptBox</strong>.",
            target: '#business-expense-wrapper .btn.add-items',
            placement: "right",
            yOffset: -20
        }
    ]
};

var EETour = {
    id: "education-expense-tour",
    steps: [
        {
            content: "Welcome to your <strong>EducationExpense</strong> application. This is the main <strong>EducationExpense</strong> frame. In this area, you can see expenses categorized by their main categories.",
            target: 'menu-education-expense',
            placement: "left",
            yOffset: -20
        },
        {
            content: "Click on this button to expand the category and reveal subcategories, along with any amounts that have been categorized at this level.",
            target: '#education-expense-wrapper .tb-pe.app-ee tr .icon-circle',
            placement: "top",
            xOffset: -23
        },
        {
            content: "Use this area to select a date, or range of dates to view your expenses by <strong>EducationExpense</strong> Category.",
            target: '#education-expense-wrapper .filterdate .checkbox-range',
            placement: "right",
            yOffset: -25,
            onNext: function () {
                $('#education-expense-wrapper .btn.add-items').click();
            }
        },
        {
            content: "Click here to add an expense at the APP level. You can choose whether to create a manual receipt, or select one from the <strong>ReceiptBox</strong>.",
            target: '#education-expense-wrapper .btn.add-items',
            placement: "right",
            yOffset: -20
        }
    ]
};

var PATour = {
    id: "personal-assets-tour",
    steps: [
        {
            content: "Welcome to your <strong>PersonalAssets</strong> application. This is the main <strong>PersonalAssets</strong> frame. In this area, you can see expenses categorized by their main categories.",
            target: '#personal-assets-wrapper .utmaltergothic',
            placement: "bottom",
            arrowOffset: "center",
            xOffset: "center"
        },
        {
            content: "Click on this button to expand the category and reveal subcategories, along with any amounts that have been categorized at this level.",
            target: '#personal-assets-wrapper .tb-pe.app-pa tr .icon-circle',
            placement: "top",
            xOffset: -23
        },
        {
            content: "Use this area to select a date, or range of dates to view your expenses by <strong>PersonalAssets</strong> Category.",
            target: '#personal-assets-wrapper .filterdate .checkbox-range',
            placement: "right",
            yOffset: -25,
            onNext: function () {
                $('#personal-assets-wrapper .btn.add-items').click();
            }
        },
        {
            content: "Click here to add an expense at the APP level. You can choose whether to create a manual receipt, or select one from the <strong>ReceiptBox</strong>.",
            target: '#personal-assets-wrapper .btn.add-items',
            placement: "right",
            yOffset: -20
        }
    ]
};

var BATour = {
    id: "business-assets-tour",
    steps: [
        {
            content: "Welcome to your <strong>BusinessAssets</strong> application. This is the main <strong>BusinessAssets</strong> frame. In this area, you can see expenses categorized by their main categories.",
            target: '#business-assets-wrapper .utmaltergothic',
            placement: "bottom",
            arrowOffset: "center",
            xOffset: "center"
        },
        {
            content: "Click on this button to expand the category and reveal subcategories, along with any amounts that have been categorized at this level.",
            target: '#business-assets-wrapper .tb-pe.app-ba tr .icon-circle',
            placement: "top",
            xOffset: -23
        },
        {
            content: "Use this area to select a date, or range of dates to view your expenses by <strong>BusinessAssets</strong> Category.",
            target: '#business-assets-wrapper .filterdate .checkbox-range',
            placement: "right",
            yOffset: -25,
            onNext: function () {
                $('#business-assets-wrapper .btn.add-items').click();
            }
        },
        {
            content: "Click here to add an expense at the APP level. You can choose whether to create a manual receipt, or select one from the <strong>ReceiptBox</strong>.",
            target: '#business-assets-wrapper .btn.add-items',
            placement: "right",
            yOffset: -20
        }
    ]
};

hopscotch.registerHelper('onStart', function () {
    $('#global-mask').addClass('active');
    $('.HelloZoomXClose').mouseover();
    //$('.hopscotch-active').removeClass('hopscotch-active');
    //$(hopscotch.getCurrTarget()).addClass('hopscotch-active');
    //$('*', $(hopscotch.getCurrTarget())).addClass('hopscotch-active');
});

hopscotch.registerHelper('onEnd', function () {
    $('#global-mask').removeClass('active');
    if (hopscotch.getCurrTour().id == 'personal-expense-tour') {
        $('#personal-expense-wrapper .btn.add-items').click();
    }
    //$('.hopscotch-active').removeClass('hopscotch-active');
});

hopscotch.registerHelper('onNext', function () {
    //$('.hopscotch-active').removeClass('hopscotch-active');
    //$(hopscotch.getCurrTarget()).addClass('hopscotch-active');
    //$('*', $(hopscotch.getCurrTarget())).addClass('hopscotch-active');
});

var setUpTour = function (tour) {
    tour.onStart = ["onStart"];
    tour.onEnd = ["onEnd"];
    tour.onClose = ["onEnd"];
    tour.onError = ["onEnd"];
    tour.onNext = ["onNext"];
    if ($(window).width() < 1280) {
        tour.bubbleWidth = 200;
    } else {
        tour.bubbleWidth = 280;
    }
};


// Start the tour!
$(document).on('click', '#rd-start-tour', function () {
    $(this).blur();
    var tourRun = BuildUpRDTour();
    setUpTour(tourRun);
    hopscotch.endTour().startTour(tourRun,0);
});

$(document).on('click', '.home-start-tour', function () {
    $(this).blur();
    if ($('#receiptbox-wrapper').is(':visible')) {
        //var tour = RBTour;
    } else if ($('#personal-expense-wrapper').is(':visible')) {
        //var tour = PETour;
    } else if ($('#education-expense-wrapper').is(':visible')) {
        //var tour = EETour;
    } else if ($('#business-expense-wrapper').is(':visible')) {
        //var tour = BETour;
    } else if ($('#personal-assets-wrapper').is(':visible')) {
        //var tour = PATour;
    } else if ($('#business-assets-wrapper').is(':visible')) {
        //var tour = BATour;
    }

    if (typeof(tour) != 'undefined') {
        setUpTour(tour);
        hopscotch.endTour().startTour(tour);
    }
});

// Next steps when enter and space key is pressed
$(document).keypress(function (e) {
    if (e.which == 13 || e.which == 32) {
        if (hopscotch.getCurrTour()) {
            hopscotch.nextStep();
        }
    }
});
