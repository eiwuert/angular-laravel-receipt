/**
 * Amazon service that uses aws javascript sdk
 */
app.service('AwsS3Sdk', function($timeout, $rootScope) {
    /**
     * S3 service's instance
     */
    var _s3 = new AWS.S3();

    /**
     * Specified bucket for manual receipt upload
     * and attachments upload
     *
     * @type {string}
     */
    var BUCKET_MANUAL, BUCKET_ATTACHMENTS, BUCKET_RECEIPT;

    /**
     * Register credentials for using service
     *
     * @param  ticket   Contain value of cretendial provided by api server
     * @return
     */
    this.setConfig = function(ticket) {
        var creds = new AWS.Credentials(ticket.key, ticket.secret, ticket.token);
        _s3       = new AWS.S3({credentials:creds});
    };

    /**
     * Set bucket info which is sent by server to configuration
     *
     * @param list
     */
    this.setBuckets = function (list) {
        BUCKET_MANUAL      = list.manual;
        BUCKET_ATTACHMENTS = list.attachment;
        BUCKET_RECEIPT     = list.receipt;
    };

    /**
     * Function to return Content-Type header by file extension
     *
     * @param    fileName    string   Name of file
     * @returns  string
     */
    this.detectContentType = function (fileName) {
        var ext = fileName.split('.').pop();
        var ctype;

        switch (ext) {
            case 'jpg'  :
            case 'jpge' :
                ctype = "image/jpeg";
                break;
            case 'png' :
            case 'gif' :
            case 'bmp' :
            case 'tiff' :
                ctype = "image/" + ext;
                break;
            case 'pdf' :
                ctype = "application/pdf";
                break;
            case 'txt' :
                ctype = "text/plain";
                break;
            default :
                ctype =  "application/octet-stream";
        }

        return ctype;
    };

    /**
     * Upload a file to specified bucket
     *
     * @param  options   object   Input parameters
     *         bucket  : Bucket where file stay
     *         keyName : Key name of file
     *         data    : File data
     *         progressCallback : function to call when uploading
     *         successCallback  : function to call when file is fetched successfully
     *         failCallback     : function to call when file is failed to fetch
     * @return
     */
    this.uploadFile = function(options){
        var settings = {
            bucket  : null,
            keyName : null,
            data    : null,
            progressCallback : null,
            successCallback  : null,
            failCallback     : null
        } ;

        jQuery.extend(settings, options);
        if(!settings.bucket || !settings.keyName) return false;

        var params = {
            Bucket : settings.bucket,
            Key    : settings.keyName,
            Body   : settings.data,
            ContentType : this.detectContentType(settings.keyName)
        };

        //Upload via s3 service
        var request = _s3.putObject(params, function(err, data) {
            if (err) {
                if (typeof settings.failCallback == "function")
                    settings.failCallback();

                return false;
            }

            if (typeof settings.successCallback == "function")
                settings.successCallback();
        });

        if (typeof settings.progressCallback == "function") {
            request.on('httpUploadProgress', function (progress) {
                var percent = parseInt((progress.loaded / progress.total) * 100);

                settings.progressCallback(percent);
            });
        }
    };

    /**
     * Upload a file using multi parts method (under testing)
     *
     */
    this.uploadMultiParts = function (fileName, fileData, bucket, progressCallback, successCallback, failCallback) {
        var params = {
            Bucket : bucket,
            Key    : fileName
            //Body   : fileData
        };
        //var uploadId;
        var partSize = 1024 * 1024; //1MB - not work. minimum allowed size is 5NB


        //Upload via s3 service
        var request = _s3.createMultipartUpload(params, function(err, dataA) {
            if (err) {
                console.log(err, err.stack); // an error occurred
                //if (typeof failCallback != "undefined" && failCallback) failCallback();
                return false;
            }

            console.log('Step 1: Create multi upload');

            params.UploadId = dataA.UploadId;

            var complete  = false;
            //var partNum   = 0;
            var partCount = 0;
            var uploaded  = 0;
            var totalPart = Math.ceil(fileData.size / partSize);

            for (var i=0; i < totalPart; i++) {
                var startPoint = i * partSize;
                var endPoint   = Math.min(startPoint + partSize, fileData.size);
                var blob       = fileData.slice(startPoint, endPoint);

                params.PartNumber = i+1;
                params.Body       = blob;
                _s3.uploadPart(params, function(err, dataB) {
                    if (err) {
                        console.log(err, err.stack); // an error occurred
                        return false;
                    }

                    uploaded++;

                    if (uploaded == totalPart) {
                        delete params.PartNumber;
                        delete params.Body;

                        console.log('Step 3: Detect upload success');
                        _s3.listParts(params, function(err, dataC) {
                            var partsInfo = new Array();
                            for (var k=0; k<dataC.Parts.length; k++) {
                                partsInfo.push({
                                    ETag: dataC.Parts[k].ETag,
                                    PartNumber: dataC.Parts[k].PartNumber
                                })
                            }

                            params.MultipartUpload = {Parts : partsInfo};

                            console.log(params);
                            _s3.completeMultipartUpload(params, function(err, dataD){
                                if (err) {
                                    console.log(err, err.stack); // an error occurred
                                    return false;
                                }

                                console.log('Step 4: Close uploade gate!');
                                console.log(dataD);
                            });
                        })

                    }
                });
            }
        });
    };

    /**
     * Read a file from specified bucket
     *
     * @param  options   object    Input parameters
     *         bucket  : Bucket where file stay
     *         keyName : Key name of file
     *         successCallback : function to call when file is fetched successfully
     *         failCallback    : function to call when file is failed to fetch
     * @return
     */
    this.getFile = function(options){
        var settings = {
            bucket          : null,
            keyName         : null,
            successCallback : null,
            failCallback    : null
        } ;

        jQuery.extend(settings, options);
        if(!settings.bucket || !settings.keyName) return false;

        var params = {
            Bucket : settings.bucket,
            Key    : settings.keyName
        };

        //Get from s3 service
        _s3.getObject(params, function(err, data) {
            if (err) {
                console.log(err, err.stack); // an error occurred
                if (typeof settings.failCallback == "function")
                    settings.failCallback();
                return false;
            }

            if (typeof settings.successCallback == "function")
                settings.successCallback(data);
        });
    };

    /**
     * Delete a file from specified bucket
     *
     * @param  keyName   Key name of file in bucket
     * @param  bucket    Target bucket
     * @param  callback  Callback function
     * @return
     */
    this.deleteFile = function(keyName, bucket, callback){
        var params = {
            Bucket : bucket,
            Key    : keyName
        };

        //Get from s3 service
        _s3.deleteObject(params, function(err, data) {
            if (err) {
                console.log(err, err.stack); // an error occurred
                return false;
            }

            if (typeof callback != "undefined") callback(data);
        });
    };


    /**
     * Upload manual receipt image
     *
     * @param  args    object    Inputs {keyName, data, progressCallback, successCallback, failCallback}
     * @return
     */
     this.uploadManualReceipt = function(args){
        if (typeof args != "undefined") args.bucket = BUCKET_MANUAL;
        this.uploadFile(args);
    };

    /**
     * Upload receipt image
     *
     * @param  args    object    Inputs {bucket, keyName, data, progressCallback, successCallback, failCallback}
     * @return
     */
     this.uploadReceipt = function(args){
        this.uploadFile(args);
    };

    /**
     * Upload attachments
     *
     * @param  args    object    Inputs {keyName, data, progressCallback, successCallback, failCallback}
     * @return
     */
     this.uploadAttachment = function(args){
         if (typeof args != "undefined") args.bucket = BUCKET_ATTACHMENTS;
         this.uploadFile(args);
    };

     /**
     * Get a processed receipt image
     *
     * @param   args    object    Inputs {bucket, keyName, successCallback, failCallback}
     * @return
     */
     this.getReceiptPdf = function(args){
        this.getFile(args);
    };

    /**
     * Get a pre-signed url for external usage
     *
     * @param  action  The name of the operation to call
     * @return
     */
     this.getPreSignedUrl = function(action, fileName, successCallback, failCallback){
        //Currently specify for fileStorage bucket
        var op;
        var params = {
            Bucket: BUCKET_MANUAL,
            Key: fileName
        };

        switch(action) {
            case 'put':
                op = "putObject";
                break;
            default:
                op = "getObject";
        }

        url = _s3.getSignedUrl(op, params, function (err, url) {
            if (err) {
                console.log(err, err.stack); // an error occurred
                return false;
            }

            if (typeof successCallback != "undefined") successCallback(url);
        });
    };

    /**
     * Get a pre-signed url for external usage
     *
     * @param  action  The name of the operation to call
     * @return
     */
    this.getSignedUploadUrl = function(fileName, fileType, successCallback, failCallback){
        //Currently specify for fileStorage bucket
        var op,
            params = {
                Bucket: BUCKET_MANUAL,
                Key: fileName
            },
            accessKeyId = _s3.config.credentials.accessKeyId,
            secretKey   = _s3.config.credentials.secretAccessKey,
            mime_type   = "image/jpeg",
            now = new Date(),
            expires = Math.ceil((now.getTime() + 60000)/1000), // 60 seconds from now
            amz_headers = "x-amz-acl:public-read",

            put_request = "PUT\n\n"+mime_type+"\n"+expires+"\n"+amz_headers+"\n/"+BUCKET_MANUAL+"/"+fileName,

            //var signature = AWS.util.crypto.hmac('sha1', secretKey).update(put_request).digest('base64');
            signature = AWS.util.crypto.hmac(secretKey, put_request, 'base64', 'sha1');

        console.log(signature);

        signature = signature.replace('+','%2B').replace('/','%2F').replace('=','%3D');
        signature = encodeURIComponent(signature.trim());

        var url = 'https://'+BUCKET_MANUAL+'.s3.amazonaws.com/'+fileName;
        var credentials = {
            signed_request: url+"?AWSAccessKeyId="+accessKeyId+"&Expires="+expires+"&Signature="+signature,
            url: url
        };

        //return JSON.stringify(credentials);

        return credentials.signed_request;
        //x-amz-security-token
     };
});
