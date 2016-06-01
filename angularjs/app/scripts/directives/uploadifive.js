'use strict';
rciSpaApp.directive('rciUploadifive', function($rootScope) {
    return {
        restrict: 'A',
        scope: {
            files: '=ngModel',
            token: '=token',
            elementId: '@elementId',
            entityId: '=entityId',
            entityName: '@entityName',
            userId: '=',
            uploader: '=',
            queueId: '@'
        },
        link: function(scope, element, attrs, ngModel) {
            var opts = scope.$eval(attrs.rciUploadifive);
            var defaultType = 'image/*';
            var postData = {};
            var queueID = '';
            var totalUpload = 0;
            var totalSelect = 0;
            var totalSeconds = 0;
            var stepper;
            var old_time;
            var first_upload = true;
            var totalSeconds = 0;
            var uploadCancelStatus = true;
            function updatedisplay(watch) {
                var mini =  watch.getElapsed().milliseconds/10;
                mini = parseInt(mini);
                (mini < 10) ? mini = ("0" + mini) : mini;
                document.getElementById('stopwatch-upload').innerHTML = watch.toString() + "." + mini;
            }
            var stopwatchUpload = new Stopwatch(updatedisplay, 50);
            if (opts.queueId) {
                queueID = opts.queueId;
            }

            if (scope.queueId) {
                queueID = scope.queueId;
            }

            element.prop('id', scope.elementId);
            if (typeof scope.entityName !== 'undefined' && scope.entityName == 'receipt_image'
                && typeof scope.userId !== 'undefined' && !angular.isDefined(opts.dontCallOcr)) {
                postData = {uid: scope.userId, location: 'Oregon', AUTH_TOKEN: scope.token, uploadType: 'upload'}
            } else if (typeof scope.entityId !== 'undefined' && typeof scope.entityName !== 'undefined') {
                postData = {EntityID: scope.entityId, EntityName: scope.entityName,  AUTH_TOKEN: scope.token, uploadType: 'upload'}
            }
            element.uploadifive({
                'auto'             : true,
                'dnd'              : false,
                'multi'            : true,
                'buttonClass'      : opts.buttonClass || 'nav upload-rc',
                'buttonText'       : opts.buttonText || '<div class="app-icon"></div>UPLOAD RECEIPT',
                'width'            : opts.width || 90,
                'height'           : opts.height || 36,
                'simUploadLimit'   : 9,
                'removeCompleted'  : true,
                'queueID'          : queueID || false,
                'uploadScript'     : scope.uploader || API_URL + '/attachments', // image upload method
                'formData'         : postData,
                'queueSizeLimit': opts.queueSizeLimit || 999,
                'fileType' : opts.fileType || defaultType,
                'fileSizeLimit': opts.fileSizeLimit || '10MB',
                'overrideEvents': ['onError'],
                'onSelect' : function(queue) {
                    $rootScope.receiptUploadFinished = 0;
                    $('.app-rb .message_upload').addClass('totalFilesingle');
                    scope.$parent.tmpuploadViaMobile = false;
                    stopwatchUpload.reset();
                    if($rootScope.ocrStatus) {
                        $('.message-upload-custom').addClass('uploading-receipt');
                        $('.message-upload-custom').show();
                        $('.message_upload').hide();
                        if (totalSelect == 0) {
                            totalSelect = queue.count;
                        } else {
                            totalSelect += queue.selected;
                        }
                        if (first_upload) {
                            old_time = new Date();
                        }
                        first_upload = false;
                        scope.$parent.tmpTotalFileUpload = totalSelect;
                        scope.$parent.tmpTotalUploaded = 0;
                        $('#uploadifive-paper-receipt input').attr('disabled', 'disabled');
                        $('.message_converted .wrap-top-watch').show();
                        $('.message_upload .mesage-upload-file').empty();
                        $('.wrap-top-watch').show();
                        $('.message_converted').hide();
                        var msgAlert = '';
                        msgAlert = 'Uploading.....';
                        $('.message_upload .mesage-upload-file').empty().text(msgAlert);
                        stopwatchUpload.start();
                        $('.message_upload').show();
                        $('.stopwatch-upload').show();
                    }else{
                        $('.app-rb .message_upload').removeClass('totalFilesingle');
                        uploadCancelStatus = false;
                        $('.upload-new-receipt').uploadifive('clearQueue');
                        setTimeout(function(){
                            $('.wrap-top-watch').hide();
                            $('.message_upload').show();
                            $('.message_upload').addClass('uploadalertHeight');
                            $('.message-upload-custom').addClass('uploading-receipt');
                            $('#paper-receipt-queue').empty();
                            $('.message_upload .mesage-upload-file').empty().append('<p>Currently we are experiencing heavy volumes so we cannot process your uploads at this time; please try back later again</p>');
                            setTimeout(function(){
                                $('.message_upload').removeClass('uploadalertHeight');
                                $('.message_upload').hide();
                                $('.message_upload .mesage-upload-file').empty();
                                $('.wrap-top-watch').show();
                                $('.message-upload-custom').removeClass('uploading-receipt');
                            },10000);
                            $('#uploadifive-paper-receipt input').removeAttr('disabled');
                        },50);
                    }
                },
                'onAddQueueItem' : function(file) {
                    if($rootScope.ocrStatus){
                        var fileType = [];
                        if(scope.entityName == 'receipt_image' ) {
                            fileType = ["image/jpeg","image/gif","image/png","application/pdf"];
                        }else if(scope.entityName == 'receipt' ||scope.entityName == 'receipt_item' ||scope.entityName == 'manual_image')
                        {
                            fileType = ["image/jpeg","image/gif","image/png","image/doc","application/pdf"];
                        }
                        $('span.filename').parent().css({"float":"left"});
                        file.queueItem.find('.filename').html(file.name.substr(0,25) +' ...');
                        file.queueItem.find('.fileinfo').html('( ' + (file.size / 1024 / 1024).toFixed(1) + ' MB)');
                        var fileTypeofFile = String(file.type);
                        if(fileType.indexOf(fileTypeofFile) < 0)
                        {
                            $.showMessageBox({content: 'Invalid file type, upload will be aborted'});
                            element.uploadifive('cancel', file, true);
                            setTimeout(function(){
                                $('#uploadifive-paper-receipt input').removeAttr('disabled');
                            },50);
                        }
                    }
                },
                'onProgress'   : function(file, e) {
                    if (e.lengthComputable) {
                        var percent = Math.round((e.loaded / e.total) * 100);
                    }
                    file.queueItem.find('.fileinfo').html('  (' + (e.total / 1024 / 1024).toFixed(1) + ' MB)');
                    file.queueItem.find('.progress-bar').css('width', percent + '%');
                },
                'onUploadComplete' : function(file, data) {
                    $('.app-rb .message_upload').removeClass('totalFilesingle');
                        if (scope.entityName != 'receipt_image') {
                            scope.$apply(function() {
                                scope.files.push(scope.$eval(data));
                                jQuery('#rd-save').addClass('show').removeClass('hide');
                                scope.$parent.userChangedContent = true;
                            });
                        } else if (scope.entityName == 'receipt_image' && opts.dontCallOcr) {
                            scope.$apply(function() {
                                scope.files = scope.$eval(data);
                                scope.$parent.showReceiptImage = 1;
                                jQuery('#rd-save').addClass('show').removeClass('hide');
                                scope.$parent.userChangedContent = true;
                            });
                        } else if (scope.entityName == 'receipt_image' && !opts.dontCallOcr) {
                            $('#upload-receipt').tooltip('hide');
                            $('.snap-rc').tooltip('hide');
                        }
                        totalUpload++;
                        if(scope.entityName == 'receipt_image') {
                            $('.message_upload').removeClass('totalFilesingle');
                            var new_time = new Date();
                            var tmpTime = new_time.getTime() - old_time.getTime();
                            var totalSeconds = ~~(tmpTime / 1000);
                            var msgAlert = '';
                            msgAlert = totalUpload + ' of ' + totalSelect + ' receipt(s) uploaded...';
                            $('.message_upload .mesage-upload-file').empty().text(msgAlert);
                            if($rootScope.socketConnected == true){
                                $('.message_converted').show();
                            }else{
                                $('.message_converted').hide();
                                element.uploadifive('cancel', file, true);
                            }
                            $('.message_upload').show();
                            $('.stopwatch-upload').show();
                            if(totalUpload == 1){
                                var timeoutUpload;
                                (totalSelect == 1) ? timeoutUpload = 3000 : timeoutUpload = 3000
                                if($rootScope.socketConnected == true) {
                                    scope.$parent.stopwatchJSConvert();
                                    scope.$parent.tmpTimeFirst = new Date();
                                    scope.$parent.stopConvert.start();
                                    $('.message_converted span#wrap-top-watchConvert').hide();
                                    $('.message_converted span#message_convert').text('Transmitting to OCR servers...');
                                    $('.message_converted').show();
                                    setTimeout(function(){
                                        $('.wrap-top-watch #stopwatch-convert').show();
                                        $('.message_converted span#wrap-top-watchConvert').show();
                                        $('.message_converted span#message_convert').text('Converting to digital format...');
                                    },timeoutUpload);
//                            $('.message_converted .wrap-top-watch').hide();
                                }else{

                                }

                            }
                        }
                },
                'onQueueComplete': function (queueData) {
                    if ($rootScope.ocrStatus) {
                        $rootScope.receiptUploadFinished = totalUpload;
                        stopwatchUpload.reset();
                        element.uploadifive('clearQueue');
                        var new_time = new Date();
                        var tmpTime = new_time.getTime() - old_time.getTime();
                        var totalSeconds = (tmpTime / 1000);
                        totalSeconds = parseFloat(totalSeconds).toFixed(1);

                        if (scope.entityName == 'receipt_image' && !opts.dontCallOcr) {
                            $('#upload-receipt').tooltip('hide');
                            $('.snap-rc').tooltip('hide');
                            var msg = "";
                            //Show message
                            var msgAlert = '';
                            (totalSeconds < 10) ? totalSeconds = '0' + totalSeconds : totalSeconds;
                            var avgSeconds = ((totalSeconds / totalUpload).toFixed(1));
                            (avgSeconds < 10) ? avgSeconds = '0' + avgSeconds : avgSeconds;
                            msgAlert = totalUpload + ' of ' + totalSelect + ' receipt(s) uploaded in ' + totalSeconds + ' second(s) (avg ' + avgSeconds + ')';
                            $('.message_upload .mesage-upload-file').empty().text(msgAlert);
                            $('.message_upload .wrap-top-watch').hide();
                            //End message
                            $('#uploadifive-' + scope.elementId).attr('title', msg)
                                .attr('data-original-title', msg)
                                .attr('data-placement', 'bottom')
                                .attr('data-html', true)
                            $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                            $('#uploadifive-' + scope.elementId).tooltip('hide');
                            setTimeout(function () {
                                totalUpload = 0;
                                totalSelect = 0;
                                first_upload = true;
                            }, 3000);
//                            affter 1 minute
                            scope.$parent.timeoutForError = setTimeout(function () {
                                console.log('>>>>>>>>>>GO receive receipts from Uploadify>>>>>>>');
                                scope.$parent.receiveNewReceipts();
                                console.log('>>>>>>>>>>receive receipts from Uploadify>>>>>>>');
                                setTimeout(function(){
                                    if (scope.$parent.tmpTotalUploaded != scope.$parent.tmpTotalFileUpload) {
                                        setTimeout(function(){
                                            scope.$parent.getReciptIfError();
                                        },10000);
                                    }
                                },1000);
                            }, 25000);

                            if (!$rootScope.socketConnected) {
                                setTimeout(function () {
                                    $('#receive-button').click();
                                    $('#uploadifive-paper-receipt input').removeAttr('disabled');
                                }, 60000);
                            }
                            //End Hide Message
                        }
                        stopwatchUpload.stop();
                    }
                },
                'onError' : function(errorType, file) {
                   // stopwatchUpload.restart();
                    if (errorType == 'QUEUE_LIMIT_EXCEEDED') {
                        $.showMessageBox({content: 'The maximum number of queue items has been reached (' + opts.queueSizeLimit + ').  Please select fewer files.'});
                        element.uploadifive('cancel', file, true);
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                        setTimeout(function(){
                            $('#uploadifive-paper-receipt input').removeAttr('disabled');
                        },50);
                    } else if (errorType == 'FILE_SIZE_LIMIT_EXCEEDED') {
                        $.showMessageBox({content: 'The size of the file exceeds the limit that was set (' + opts.fileSizeLimit + ')'});
                        element.uploadifive('cancel', file, true);
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                        setTimeout(function(){
                            $('#uploadifive-paper-receipt input').removeAttr('disabled');
                        },50);
                    } else if (errorType == 'FORBIDDEN_FILE_TYPE') {
                        $.showMessageBox({content: 'Invalid file type.'});
                        element.uploadifive('cancel', file, true);
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                        setTimeout(function(){
                            $('#uploadifive-paper-receipt input').removeAttr('disabled');
                        },50);
                    } else {
                        //QUEUE_LIMIT_EXCEEDED, UPLOAD_LIMIT_EXCEEDED, FILE_SIZE_LIMIT_EXCEEDED, FORBIDDEN_FILE_TYPE, and 404_FILE_NOT_FOUND
                        $.showMessageBox({content: 'The file ' + file.name + ' could not be uploaded: ' + errorType});
                        element.uploadifive('cancel', file, true);
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                        setTimeout(function(){
                            $('#uploadifive-paper-receipt input').removeAttr('disabled');
                        },50);
                    }
                    //$('#paper-receipt-queue').empty();
                }
            });
        }
    }
});
/*
* Uploadifive for receipt details
* */
rciSpaApp.directive('rciUploadReceipt', function($rootScope) {
    return {
        restrict: 'A',
        scope: {
            files: '=ngModel',
            token: '=token',
            elementId: '@elementId',
            entityId: '=entityId',
            entityName: '@entityName',
            userId: '=',
            uploader: '=',
            queueId: '@'
        },
        link: function(scope, element, attrs, ngModel) {
            var opts = scope.$eval(attrs.rciUploadReceipt);
            var defaultType = 'image/*';
            var postData = {};
            var queueID = '';
            var totalUpload = 0;
            var totalSelect = 0;
            var totalSeconds = 0;
            var stepper;
            var old_time;
            var first_upload = true;
            var totalSeconds = 0;
            if (opts.queueId) {
                queueID = opts.queueId;
            }

            if (scope.queueId) {
                queueID = scope.queueId;
            }

            element.prop('id', scope.elementId);
            if (typeof scope.entityName !== 'undefined' && scope.entityName == 'receipt_image'
                && typeof scope.userId !== 'undefined' && !angular.isDefined(opts.dontCallOcr)) {
                postData = {uid: scope.userId, location: 'Oregon', AUTH_TOKEN: scope.token, uploadType: 'upload'}
            } else if (typeof scope.entityId !== 'undefined' && typeof scope.entityName !== 'undefined') {
                postData = {EntityID: scope.entityId, EntityName: scope.entityName,  AUTH_TOKEN: scope.token, uploadType: 'upload'}
            }
            element.uploadifive({
                'auto'             : true,
                'dnd'              : false,
                'multi'            : true,
                'buttonClass'      : opts.buttonClass || 'nav upload-rc',
                'buttonText'       : opts.buttonText || '<div class="app-icon"></div>UPLOAD RECEIPT',
                'width'            : opts.width || 90,
                'height'           : opts.height || 36,
                'simUploadLimit'   : 1,
                'removeCompleted'  : true,
                'queueID'          : queueID || false,
                'uploadScript'     : scope.uploader || API_URL + '/attachments', // image upload method
                'formData'         : postData,
                'queueSizeLimit': opts.queueSizeLimit || 999,
                'fileType' : opts.fileType || defaultType,
                'fileSizeLimit': opts.fileSizeLimit || '10MB',
                'overrideEvents': ['onError'],
                'onAddQueueItem' : function(file) {
                    var fileType = [];
                    if(scope.entityName == 'receipt_image') {
                        fileType = ["image/jpeg","image/gif","image/png"];
                    }else if(scope.entityName == 'receipt')
                    {
                        fileType = ["image/jpeg","image/gif","image/png","image/doc"];
                    }else{
                        fileType = ["image/jpeg","image/gif","image/png","image/doc"];
                    }
                    $('span.filename').parent().css({"float":"left"});
                    if(fileType.indexOf(file.type) < 0)
                    {
                        $.showMessageBox({content: 'Invalid file type, upload will be aborted'});
                        element.uploadifive('cancel', file, true);
                    }
                },
                'onProgress'   : function(file, e) {
                    if (e.lengthComputable) {
                        var percent = Math.round((e.loaded / e.total) * 100);
                    }
                    file.queueItem.find('.progress-bar').css('width', percent + '%');
                },
                'onUploadComplete' : function(file, data) {
                    if (scope.entityName != 'receipt_image') {
                        scope.$apply(function() {
                            scope.files.push(scope.$eval(data));
                            jQuery('#rd-save').addClass('show').removeClass('hide');
                            scope.$parent.userChangedContent = true;
                        });
                    } else if (scope.entityName == 'receipt_image' && opts.dontCallOcr) {
                        scope.$apply(function() {
                            scope.files = scope.$eval(data);
                            scope.$parent.showReceiptImage = 1;
                            jQuery('#rd-save').addClass('show').removeClass('hide');
                            scope.$parent.userChangedContent = true;
                        });
                    } else if (scope.entityName == 'receipt_image' && !opts.dontCallOcr) {
                        $('#upload-receipt').tooltip('hide');
                        $('.snap-rc').tooltip('hide');
                    }
                    totalUpload++;
                },
                'onQueueComplete' : function(queueData) {
                    element.uploadifive('clearQueue');
                    if (scope.entityName == 'receipt_image' && !opts.dontCallOcr) {
                        $('#upload-receipt').tooltip('hide');
                        $('.snap-rc').tooltip('hide');
                        $('#uploadifive-' + scope.elementId).attr('title', msg)
                            .attr('data-original-title', msg)
                            .attr('data-placement', 'bottom')
                            .attr('data-html', true)
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                    }
                },
                'onError' : function(errorType, file) {
                    if (errorType == 'QUEUE_LIMIT_EXCEEDED') {
                        $.showMessageBox({content: 'The maximum number of queue items has been reached (' + opts.queueSizeLimit + ').  Please select fewer files.'});
                        element.uploadifive('cancel', file, true);
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                    } else if (errorType == 'FILE_SIZE_LIMIT_EXCEEDED') {
                        $.showMessageBox({content: 'The size of the file exceeds the limit that was set (' + opts.fileSizeLimit + ')'});
                        element.uploadifive('cancel', file, true);
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                    } else if (errorType == 'FORBIDDEN_FILE_TYPE') {
                        $.showMessageBox({content: 'Invalid file type.'});
                        element.uploadifive('cancel', file, true);
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                    } else {
                        //QUEUE_LIMIT_EXCEEDED, UPLOAD_LIMIT_EXCEEDED, FILE_SIZE_LIMIT_EXCEEDED, FORBIDDEN_FILE_TYPE, and 404_FILE_NOT_FOUND
                        $.showMessageBox({content: 'The file ' + file.name + ' could not be uploaded: ' + errorType});
                        element.uploadifive('cancel', file, true);
                        $('#uploadifive-' + scope.elementId).tooltip({container: '#uploadifive-' + scope.elementId});
                        $('#uploadifive-' + scope.elementId).tooltip('hide');
                    }
                }
            });
        }
    }
});

/*
* Uploadifive for attachments of receipt and item
* */
rciSpaApp.directive('rciUploadFile', function($rootScope) {
    return {
        restrict: 'A',
        scope: {
            files: '=ngModel',
            token: '=token',
            elementId: '@elementId',
            entityId: '=entityId',
            entityName: '@entityName',
            userId: '=',
            uploader: '=',
            queueId: '@'
        },
        link: function(scope, element, attrs, ngModel) {
            var opts         = scope.$eval(attrs.rciUploadFile);
            var defaultType  = 'image/*';
            var postData     = {};
            var queueID      = '';
            var totalUpload  = 0;
            var totalSelect  = 0;
            var totalSeconds = 0;
            var stepper;
            var old_time;
            var first_upload = true;
            var totalSeconds = 0;
            if (opts.queueId) {
                queueID = opts.queueId;
            }

            if (scope.queueId) {
                queueID = scope.queueId;
            }

            element.prop('id', scope.elementId);
            if (typeof scope.entityName !== 'undefined' && scope.entityName == 'receipt_image'
                && typeof scope.userId !== 'undefined' && !angular.isDefined(opts.dontCallOcr)) {
                postData = {uid: scope.userId, location: 'Oregon', AUTH_TOKEN: scope.token, uploadType: 'upload'}
            } else if (typeof scope.entityId !== 'undefined' && typeof scope.entityName !== 'undefined') {
                postData = {EntityID: scope.entityId, EntityName: scope.entityName,  AUTH_TOKEN: scope.token, uploadType: 'upload'}
            }
            element.uploadifive({
                'auto'             : true,
                'dnd'              : false,
                'multi'            : true,
                'buttonClass'      : opts.buttonClass || 'nav upload-rc',
                'buttonText'       : opts.buttonText || '<div class="app-icon"></div>UPLOAD RECEIPT',
                'width'            : opts.width || 90,
                'height'           : opts.height || 36,
                'simUploadLimit'   : 1,
                'removeCompleted'  : true,
                'queueID'          : queueID || false,
                'uploadScript'     : scope.uploader || API_URL + '/attachments', // image upload method
                'formData'         : postData,
                'queueSizeLimit': opts.queueSizeLimit || 999,
                'fileType' : opts.fileType || defaultType,
                'fileSizeLimit': opts.fileSizeLimit || '10MB',
                'overrideEvents': ['onError'],
                'onAddQueueItem' : function(file) {
                    var fileType = [];
                    if(scope.entityName == 'receipt_image') {
                        fileType = ["image/jpeg","image/gif","image/png"];
                    }else if(scope.entityName == 'receipt')
                    {
                        fileType = ["image/jpeg","image/gif","image/png","image/doc"];
                    }else{
                        fileType = ["image/jpeg","image/gif","image/png","image/doc"];
                    }
                    $('span.filename').parent().css({"float":"left"});
                    if(fileType.indexOf(file.type) < 0)
                    {
                        $.showMessageBox({content: 'Invalid file type, upload will be aborted'});
                        element.uploadifive('cancel', file, true);
                    }
                },
                'onProgress'   : function(file, e) {
                    if (e.lengthComputable) {
                        var percent = Math.round((e.loaded / e.total) * 100);
                    }
                    file.queueItem.find('.progress-bar').css('width', percent + '%');
                },
                'onUploadComplete' : function(file, data) {
                    
                },
                'onQueueComplete' : function(queueData) {
                    
                },
                'onError' : function(errorType, file) {
                    
                }
            });
        }
    }
});
