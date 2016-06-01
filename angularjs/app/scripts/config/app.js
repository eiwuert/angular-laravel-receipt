//TODO: Should use angular constant for contain API URL
var CLIENT_URL = "",
    API_URL = "",
    OCR_URL = "",
    ENVIRONMENT = "",
    PUSH_SERVER_URL = "",
    UPLOAD_SERVER_URL = "",
    CURRENT_WEB_URL = document.URL,
    DEV_IP = ['192.168.1.87'],
    DEV_ENVIRONMENT = "",
    VIP_CODE = "1eaca9c43d65423779f3c21538257bb4a1034b554d28547a34a489d0326849ea";

/**
 * Secret door for live server
 */
var vipOnly = function (fallBackSite, trueSite) {
    var authorized = false;

    if (typeof(localStorage) !== "undefined" && localStorage.conquered)
        authorized = true;

    if ((CURRENT_WEB_URL.indexOf(VIP_CODE) != -1)) {
        localStorage.conquered = true;
        authorized = true;
        if (typeof trueSite != 'undefined') location.href = trueSite;
    }

    if (!authorized)
        location.href = fallBackSite;
};

/**
 * Recognize environment
 */
if ((CURRENT_WEB_URL.indexOf("//localhost/") != -1)) {
    ENVIRONMENT = 'development';
}
else if ((CURRENT_WEB_URL.indexOf("//pre-production.receiptclub.com/site") != -1)) {
    ENVIRONMENT = 'pre-production';
}
else if ((CURRENT_WEB_URL.indexOf("//receiptclub.com/site") != -1)) {
    ENVIRONMENT = 'production';
}
else if ((CURRENT_WEB_URL.indexOf("//www.receiptclub.com") != -1) ||
    (CURRENT_WEB_URL.indexOf("//receiptclub.com") != -1)) {
    ENVIRONMENT = 'construction';
}else{
  DEV_IP.forEach(function(val, index){
    if ((CURRENT_WEB_URL.indexOf(val) != -1)) {
      ENVIRONMENT     = 'dev_development';
      DEV_ENVIRONMENT = val;
    }
  });
}

/**
 * Apply configs
 */
if (ENVIRONMENT == 'dev_development') {
    CLIENT_URL        = "http://" + DEV_ENVIRONMENT + "/rci/angularjs/app";
    API_URL           = "http://" + DEV_ENVIRONMENT + "/rci/php-rest-api/v1";
    OCR_URL           = "http://" + DEV_ENVIRONMENT + "/rci/php-rest-api/v1/receipt-images";
    PUSH_SERVER_URL   = "https://pre-push.receiptclub.com";
    UPLOAD_SERVER_URL = "https://pre-siphon.receiptclub.com";
}
if (ENVIRONMENT == 'development') {
    CLIENT_URL        = "http://localhost/rci/angularjs/app";
    API_URL           = "http://localhost/rci/php-rest-api/v1";
    OCR_URL           = "http://localhost/rci/php-rest-api/v1/receipt-images";
    PUSH_SERVER_URL   = "https://pre-push.receiptclub.com";
    UPLOAD_SERVER_URL = "https://pre-siphon.receiptclub.com";
}
else if (ENVIRONMENT == 'production') {
    CLIENT_URL        = "https://receiptclub.com/site";
    API_URL           = "https://receiptclub.com/site/api/v1";
    OCR_URL           = "https://receiptclub.com/site/api/v1/receipt-images";
    PUSH_SERVER_URL   = "https://push.receiptclub.com";
    UPLOAD_SERVER_URL = "https://siphon.receiptclub.com";

    vipOnly("https://receiptclub.com", CLIENT_URL + "/#!/");
}
else if (ENVIRONMENT == 'pre-production') {
    CLIENT_URL        = "https://pre-production.receiptclub.com/site";
    API_URL           = "https://pre-production.receiptclub.com/site/api/v1";
    OCR_URL           = "https://pre-production.receiptclub.com/site/api/v1/receipt-images";
    PUSH_SERVER_URL   = "https://pre-push.receiptclub.com";
    UPLOAD_SERVER_URL = "https://pre-siphon.receiptclub.com";

    vipOnly("https://pre-production.receiptclub.com", CLIENT_URL + "/#!/");
}
else if (ENVIRONMENT == 'construction') {
    CLIENT_URL        = "https://receiptclub.com";
    API_URL           = "https://receiptclub.com/api/v1";
    OCR_URL           = "https://receiptclub.com/api/v1/receipt-images";
    PUSH_SERVER_URL   = "https://push.receiptclub.com";
    UPLOAD_SERVER_URL = "https://siphon.receiptclub.com";
}
